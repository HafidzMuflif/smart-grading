import os
import glob
import json
import re
import uuid
import shutil
import logging
from datetime import datetime
from typing import Optional

from fastapi import FastAPI, File, UploadFile, Form, HTTPException, Depends
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import FileResponse
from sqlalchemy.orm import Session
from sqlalchemy import text

from app.config import Config
from app.database.db_manager import get_db
from app.services.ocr_service import OCRService
from app.services.grading_service import GradingService
from app.services.report_service import ReportService
from app.services.ai_grading_service import AIGradingService
from app.services.detailed_report_service import DetailedReportService
from pypdf import PdfReader

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

app = FastAPI(title="Smart Grading API", version="1.0.0")

# CORS middleware
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Initialize services
ocr_service = OCRService()
grading_service = GradingService()
report_service = ReportService(Config.REPORT_DIR)
detailed_report_service = DetailedReportService(Config.REPORT_DIR)

_ai_grading_service = None


def get_ai_grading_service() -> AIGradingService:
    """Lazy-init supaya server tetap bisa jalan meski GEMINI_API_KEY belum diisi,
    error baru muncul saat endpoint AI grading benar-benar dipanggil."""
    global _ai_grading_service
    if _ai_grading_service is None:
        _ai_grading_service = AIGradingService()
    return _ai_grading_service


def _extract_pdf_text(pdf_path: str) -> str:
    """Ekstrak teks dari PDF digital (bukan hasil scan), dipakai untuk rubrik."""
    reader = PdfReader(pdf_path)
    return "\n".join(page.extract_text() or "" for page in reader.pages)


@app.get("/")
def read_root():
    return {"message": "Smart Grading API is running"}


@app.post("/api/upload/answersheet")
async def upload_answer_sheet(
    exam_id: str = Form(...),
    student_id: str = Form(...),
    file: UploadFile = File(...)
):
    """Upload student answer sheet. Called by the frontend right after a
    submission is saved, so the backend has its own copy to OCR later."""
    try:
        if not file.filename.lower().endswith('.pdf'):
            raise HTTPException(status_code=400, detail="Only PDF files are allowed")

        upload_dir = os.path.join(Config.UPLOAD_DIR, exam_id, student_id)
        os.makedirs(upload_dir, exist_ok=True)

        file_path = os.path.join(upload_dir, file.filename)
        with open(file_path, "wb") as buffer:
            shutil.copyfileobj(file.file, buffer)

        return {
            "status": "success",
            "filename": file.filename,
            "path": file_path
        }
    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"Error uploading answer sheet: {e}")
        raise HTTPException(status_code=500, detail=str(e))


def _parse_rubric_file(rubric_path: str) -> dict:
    """Parse a simple 'key=value' rubric text file. Falls back to sensible
    defaults for anything missing or if the file can't be parsed."""
    rubric = {"max_score": 100.0, "keyword_weight": 0.6, "similarity_weight": 0.4}

    if not rubric_path or not rubric_path.lower().endswith('.txt'):
        return rubric

    try:
        with open(rubric_path, 'r', encoding='utf-8', errors='ignore') as f:
            for line in f:
                line = line.strip()
                if not line or '=' not in line:
                    continue
                key, value = line.split('=', 1)
                key = key.strip()
                value = value.strip()
                if key in ('max_score', 'keyword_weight', 'similarity_weight'):
                    try:
                        rubric[key] = float(value)
                    except ValueError:
                        pass
    except Exception as e:
        logger.warning(f"Could not parse rubric file, using defaults: {e}")

    return rubric


def _find_submission_file(exam_id: str, student_id: str) -> Optional[str]:
    """Locate the backend-side copy of a student's answer sheet, uploaded
    earlier via /api/upload/answersheet."""
    pattern = os.path.join(Config.UPLOAD_DIR, str(exam_id), str(student_id), "*.pdf")
    matches = glob.glob(pattern)
    return matches[0] if matches else None


@app.post("/api/process/exam")
async def process_exam(
    exam_id: str = Form(...),
    answer_key: UploadFile = File(...),
    rubric: Optional[UploadFile] = File(None),
    db: Session = Depends(get_db)
):
    """Process grading for every pending/processing submission of an exam:
    OCR the answer key, OCR each student's answer sheet, grade it, and
    persist the score back to the database."""
    try:
        # 1. Simpan kunci jawaban
        exam_upload_dir = os.path.join(Config.UPLOAD_DIR, exam_id)
        os.makedirs(exam_upload_dir, exist_ok=True)

        key_path = os.path.join(exam_upload_dir, "answer_key.pdf")
        with open(key_path, "wb") as buffer:
            shutil.copyfileobj(answer_key.file, buffer)

        # 2. OCR kunci jawaban
        key_text = ocr_service.extract_text_from_pdf(key_path)

        # 3. Simpan & parse rubrik (opsional)
        rubric_data = {"max_score": 100.0, "keyword_weight": 0.6, "similarity_weight": 0.4}
        if rubric is not None and rubric.filename:
            rubric_path = os.path.join(exam_upload_dir, f"rubric_{rubric.filename}")
            with open(rubric_path, "wb") as buffer:
                shutil.copyfileobj(rubric.file, buffer)
            rubric_data = _parse_rubric_file(rubric_path)

        # 4. Ambil submission yang menunggu diproses
        submissions = db.execute(
            text("""
                SELECT id, student_id
                FROM submissions
                WHERE exam_id = :exam_id AND status IN ('pending', 'processing')
            """),
            {"exam_id": exam_id}
        ).fetchall()

        processed = 0
        failed = 0
        results = []

        for sub in submissions:
            submission_id = sub.id
            student_id = sub.student_id

            try:
                answer_sheet_path = _find_submission_file(exam_id, student_id)

                if not answer_sheet_path:
                    raise Exception(
                        "File jawaban tidak ditemukan di backend. "
                        "Pastikan submission diupload lewat frontend terlebih dahulu."
                    )

                # OCR jawaban mahasiswa
                student_text = ocr_service.extract_text_from_pdf(answer_sheet_path)

                # Nilai jawaban
                grading_result = grading_service.grade_answer(student_text, key_text, rubric_data)

                # Simpan nilai ke tabel scores
                db.execute(
                    text("""
                        INSERT INTO scores (submission_id, question_number, score, max_score, feedback)
                        VALUES (:submission_id, 1, :score, :max_score, :feedback)
                    """),
                    {
                        "submission_id": submission_id,
                        "score": grading_result["score"],
                        "max_score": rubric_data["max_score"],
                        "feedback": grading_result["feedback"],
                    }
                )

                # Update status submission
                db.execute(
                    text("""
                        UPDATE submissions
                        SET status = 'completed', processed_at = :processed_at
                        WHERE id = :submission_id
                    """),
                    {"processed_at": datetime.utcnow(), "submission_id": submission_id}
                )

                db.commit()
                processed += 1
                results.append({
                    "submission_id": submission_id,
                    "student_id": student_id,
                    "score": grading_result["score"],
                    "status": "completed"
                })

            except Exception as sub_error:
                db.rollback()
                logger.error(f"Failed grading submission {submission_id}: {sub_error}")

                db.execute(
                    text("UPDATE submissions SET status = 'failed' WHERE id = :submission_id"),
                    {"submission_id": submission_id}
                )
                db.commit()

                failed += 1
                results.append({
                    "submission_id": submission_id,
                    "student_id": student_id,
                    "status": "failed",
                    "error": str(sub_error)
                })

        return {
            "status": "completed",
            "exam_id": exam_id,
            "total_submissions": len(submissions),
            "processed": processed,
            "failed": failed,
            "results": results
        }

    except Exception as e:
        logger.error(f"Error processing exam {exam_id}: {e}")
        raise HTTPException(status_code=500, detail=str(e))


@app.post("/api/grade/student")
async def grade_student_answer(
    student_answer: str = Form(...),
    key_answer: str = Form(...),
    max_score: float = Form(100)
):
    """Grade a single student answer (utility endpoint, not tied to DB)."""
    try:
        rubric = {
            "max_score": max_score,
            "keyword_weight": 0.6,
            "similarity_weight": 0.4
        }

        result = grading_service.grade_answer(student_answer, key_answer, rubric)
        return {
            "status": "success",
            "result": result
        }
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@app.get("/api/report/{exam_id}")
async def generate_report(exam_id: str, db: Session = Depends(get_db)):
    """Generate a PDF grading report for an exam, using real data from the database."""
    try:
        exam_row = db.execute(
            text("""
                SELECT e.title, c.name as class_name
                FROM exams e
                LEFT JOIN classes c ON e.class_id = c.id
                WHERE e.id = :exam_id
            """),
            {"exam_id": exam_id}
        ).fetchone()

        if not exam_row:
            raise HTTPException(status_code=404, detail="Exam not found")

        exam_data = {
            "title": exam_row.title,
            "class_name": exam_row.class_name or "N/A"
        }

        score_rows = db.execute(
            text("""
                SELECT s.name as student_name, s.nim,
                       COALESCE(AVG(sc.score), 0) as score
                FROM submissions sub
                JOIN students s ON sub.student_id = s.id
                LEFT JOIN scores sc ON sc.submission_id = sub.id
                WHERE sub.exam_id = :exam_id
                GROUP BY s.name, s.nim
                ORDER BY s.name
            """),
            {"exam_id": exam_id}
        ).fetchall()

        scores = [
            {"student_name": row.student_name, "nim": row.nim, "score": float(row.score)}
            for row in score_rows
        ]

        if not scores:
            raise HTTPException(status_code=400, detail="No submissions found for this exam yet")

        report_path = report_service.generate_grading_report(exam_data, scores)

        return FileResponse(
            report_path,
            media_type='application/pdf',
            filename=os.path.basename(report_path)
        )
    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"Error generating report for exam {exam_id}: {e}")
        raise HTTPException(status_code=500, detail=str(e))


@app.post("/api/grade/submission/{submission_id}/ai")
async def grade_submission_with_ai(submission_id: int, db: Session = Depends(get_db)):
    """Nilai satu submission mahasiswa memakai AI (Gemini vision) berdasarkan
    rubrik + kunci jawaban ujian (format Markdown), lalu simpan skor & buat
    laporan Markdown detail."""
    try:
        ai_service = get_ai_grading_service()

        submission_row = db.execute(
            text("""
                SELECT sub.id, sub.exam_id, sub.student_id,
                       e.title as exam_title, e.rubric_path, e.answer_key_path,
                       s.name as student_name, s.nim as student_nim,
                       c.name as class_name
                FROM submissions sub
                JOIN exams e ON sub.exam_id = e.id
                JOIN students s ON sub.student_id = s.id
                LEFT JOIN classes c ON e.class_id = c.id
                WHERE sub.id = :submission_id
            """),
            {"submission_id": submission_id}
        ).fetchone()

        if not submission_row:
            raise HTTPException(status_code=404, detail="Submission not found")

        if not submission_row.rubric_path:
            raise HTTPException(
                status_code=400,
                detail="Ujian ini belum punya file rubrik (Markdown). Upload rubrik dulu lewat halaman Edit Ujian."
            )

        if not submission_row.answer_key_path:
            raise HTTPException(
                status_code=400,
                detail="Ujian ini belum punya file kunci jawaban (Markdown). Upload dulu lewat halaman Edit Ujian."
            )

        # Rubrik & kunci jawaban disimpan oleh PHP sebagai path relatif terhadap
        # folder frontend/ (mis. "uploads/exams/xxx_rubrik.md"). Cari file
        # fisiknya dari sisi backend.
        frontend_root = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", "..", "frontend"))
        rubric_full_path = os.path.join(frontend_root, submission_row.rubric_path)
        answer_key_full_path = os.path.join(frontend_root, submission_row.answer_key_path)

        if not os.path.exists(rubric_full_path):
            raise HTTPException(status_code=400, detail=f"File rubrik tidak ditemukan di server: {submission_row.rubric_path}")
        if not os.path.exists(answer_key_full_path):
            raise HTTPException(status_code=400, detail=f"File kunci jawaban tidak ditemukan di server: {submission_row.answer_key_path}")

        answer_sheet_path = _find_submission_file(str(submission_row.exam_id), str(submission_row.student_id))
        if not answer_sheet_path:
            raise HTTPException(
                status_code=400,
                detail="File jawaban mahasiswa tidak ditemukan di backend. Upload ulang lewat halaman detail ujian."
            )

        with open(rubric_full_path, 'r', encoding='utf-8', errors='ignore') as f:
            rubric_text = f.read()
        with open(answer_key_full_path, 'r', encoding='utf-8', errors='ignore') as f:
            answer_key_text = f.read()

        if not rubric_text.strip():
            raise HTTPException(status_code=400, detail="File rubrik kosong.")
        if not answer_key_text.strip():
            raise HTTPException(status_code=400, detail="File kunci jawaban kosong.")

        grading_result = ai_service.grade_exam(
            answer_sheet_pdf_path=answer_sheet_path,
            rubric_text=rubric_text,
            answer_key_text=answer_key_text,
            student_name=submission_row.student_name,
            student_nim=submission_row.student_nim
        )

        nilai_total = float(grading_result.get("nilai_total", 0))
        kesimpulan_singkat = grading_result.get("kesimpulan", "")[:500]

        db.execute(
            text("DELETE FROM scores WHERE submission_id = :submission_id"),
            {"submission_id": submission_id}
        )
        db.execute(
            text("""
                INSERT INTO scores (submission_id, question_number, score, max_score, feedback)
                VALUES (:submission_id, 1, :score, 100, :feedback)
            """),
            {"submission_id": submission_id, "score": nilai_total, "feedback": kesimpulan_singkat}
        )
        db.execute(
            text("UPDATE submissions SET status = 'completed', processed_at = :now WHERE id = :submission_id"),
            {"now": datetime.utcnow(), "submission_id": submission_id}
        )
        db.commit()

        # Simpan hasil analisis lengkap ke disk supaya laporan PDF bisa dibuat ulang tanpa panggil AI lagi
        analysis_dir = os.path.join(Config.REPORT_DIR, "analysis")
        os.makedirs(analysis_dir, exist_ok=True)
        analysis_path = os.path.join(analysis_dir, f"submission_{submission_id}.json")
        with open(analysis_path, "w", encoding="utf-8") as f:
            json.dump(grading_result, f, ensure_ascii=False, indent=2)

        return {
            "status": "success",
            "submission_id": submission_id,
            "nilai_total": nilai_total,
            "huruf": grading_result.get("huruf"),
            "sections_count": len(grading_result.get("sections", []))
        }

    except HTTPException:
        raise
    except Exception as e:
        db.rollback()
        logger.error(f"Error in AI grading for submission {submission_id}: {e}")
        raise HTTPException(status_code=500, detail=str(e))


@app.get("/api/report/detailed/exam/{exam_id}/available")
async def list_available_ai_reports(exam_id: int, db: Session = Depends(get_db)):
    """Kembalikan daftar submission_id (dalam satu ujian) yang sudah punya
    hasil analisis AI (bukan sekadar status completed dari mode gratis)."""
    try:
        submission_ids = db.execute(
            text("SELECT id FROM submissions WHERE exam_id = :exam_id"),
            {"exam_id": exam_id}
        ).fetchall()

        available = []
        for row in submission_ids:
            analysis_path = os.path.join(Config.REPORT_DIR, "analysis", f"submission_{row.id}.json")
            if os.path.exists(analysis_path):
                available.append(row.id)

        return {"exam_id": exam_id, "available_submission_ids": available}
    except Exception as e:
        logger.error(f"Error listing available AI reports for exam {exam_id}: {e}")
        raise HTTPException(status_code=500, detail=str(e))


@app.get("/api/report/detailed/{submission_id}")
async def get_detailed_report(submission_id: int, db: Session = Depends(get_db)):
    """Download laporan Markdown detail hasil AI grading untuk satu submission."""
    try:
        analysis_path = os.path.join(Config.REPORT_DIR, "analysis", f"submission_{submission_id}.json")

        if not os.path.exists(analysis_path):
            raise HTTPException(
                status_code=404,
                detail="Belum ada analisis AI untuk submission ini. Proses dulu lewat endpoint /api/grade/submission/{id}/ai"
            )

        with open(analysis_path, "r", encoding="utf-8") as f:
            grading_result = json.load(f)

        submission_row = db.execute(
            text("""
                SELECT sub.id, e.title as exam_title,
                       s.name as student_name, s.nim as student_nim,
                       c.name as class_name
                FROM submissions sub
                JOIN exams e ON sub.exam_id = e.id
                JOIN students s ON sub.student_id = s.id
                LEFT JOIN classes c ON e.class_id = c.id
                WHERE sub.id = :submission_id
            """),
            {"submission_id": submission_id}
        ).fetchone()

        if not submission_row:
            raise HTTPException(status_code=404, detail="Submission not found")

        exam_data = {"title": submission_row.exam_title, "class_name": submission_row.class_name or "N/A"}
        student_data = {"name": submission_row.student_name, "nim": submission_row.student_nim}

        report_path = detailed_report_service.generate(exam_data, student_data, grading_result)

        return FileResponse(
            report_path,
            media_type='text/markdown',
            filename=os.path.basename(report_path)
        )

    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"Error generating detailed report for submission {submission_id}: {e}")
        raise HTTPException(status_code=500, detail=str(e))


def _normalize_text(s: str) -> str:
    """Normalisasi teks untuk pencocokan nama (lowercase, hapus spasi berlebih & tanda baca)."""
    s = (s or '').lower().strip()
    s = re.sub(r'[^a-z0-9\s]', '', s)
    s = re.sub(r'\s+', ' ', s)
    return s


@app.post("/api/upload/bulk-detect")
async def bulk_detect_and_assign(
    exam_id: int = Form(...),
    file: UploadFile = File(...),
    db: Session = Depends(get_db)
):
    """Upload SATU file jawaban (dipanggil berulang oleh frontend untuk tiap
    file dalam upload massal). AI membaca nama/NIM di halaman pertama,
    mencocokkan ke roster mahasiswa kelas ujian ini, lalu otomatis membuat
    submission kalau match ditemukan."""
    try:
        ai_service = get_ai_grading_service()

        if not file.filename.lower().endswith('.pdf'):
            raise HTTPException(status_code=400, detail="Hanya file PDF yang didukung.")

        exam_row = db.execute(
            text("SELECT id, class_id FROM exams WHERE id = :exam_id"),
            {"exam_id": exam_id}
        ).fetchone()
        if not exam_row:
            raise HTTPException(status_code=404, detail="Ujian tidak ditemukan.")

        # Simpan file sementara untuk dibaca AI
        tmp_dir = os.path.join(Config.UPLOAD_DIR, "tmp_bulk")
        os.makedirs(tmp_dir, exist_ok=True)
        tmp_path = os.path.join(tmp_dir, f"{uuid.uuid4().hex}_{file.filename}")
        with open(tmp_path, "wb") as buffer:
            shutil.copyfileobj(file.file, buffer)

        identity = ai_service.detect_student_identity(tmp_path)
        detected_name = identity.get("name", "")
        detected_nim = identity.get("nim", "")

        # Ambil roster mahasiswa di kelas ujian ini
        students = db.execute(
            text("""
                SELECT s.id, s.name, s.nim
                FROM students s
                JOIN class_students cs ON cs.student_id = s.id
                WHERE cs.class_id = :class_id
            """),
            {"class_id": exam_row.class_id}
        ).fetchall()

        matched_student = None

        # 1. Coba cocokkan via NIM dulu (paling reliable)
        if detected_nim:
            for s in students:
                if re.sub(r'\D', '', s.nim) == detected_nim:
                    matched_student = s
                    break

        # 2. Kalau belum ketemu, coba cocokkan via nama (exact match setelah dinormalisasi)
        if not matched_student and detected_name:
            norm_detected = _normalize_text(detected_name)
            candidates = [s for s in students if _normalize_text(s.name) == norm_detected]
            if len(candidates) == 1:
                matched_student = candidates[0]
            elif len(candidates) == 0:
                # 3. Fallback: partial containment match (misal AI cuma baca nama depan)
                partial_candidates = [
                    s for s in students
                    if norm_detected and (norm_detected in _normalize_text(s.name) or _normalize_text(s.name) in norm_detected)
                ]
                if len(partial_candidates) == 1:
                    matched_student = partial_candidates[0]

        if not matched_student:
            os.remove(tmp_path)
            return {
                "filename": file.filename,
                "matched": False,
                "detected_name": detected_name,
                "detected_nim": detected_nim,
                "message": "Tidak ditemukan mahasiswa yang cocok di kelas ini. Upload manual lewat form 'Upload Jawaban Mahasiswa'."
            }

        # Pindahkan file ke folder resmi backend/uploads/{exam_id}/{student_id}/
        final_dir = os.path.join(Config.UPLOAD_DIR, str(exam_id), str(matched_student.id))
        os.makedirs(final_dir, exist_ok=True)
        final_path = os.path.join(final_dir, file.filename)
        shutil.move(tmp_path, final_path)

        # Buat / update submission
        existing = db.execute(
            text("SELECT id FROM submissions WHERE exam_id = :exam_id AND student_id = :student_id"),
            {"exam_id": exam_id, "student_id": matched_student.id}
        ).fetchone()

        if existing:
            db.execute(
                text("UPDATE submissions SET answer_sheet_path = :path, status = 'pending' WHERE id = :id"),
                {"path": f"bulk-upload/{file.filename}", "id": existing.id}
            )
        else:
            db.execute(
                text("""
                    INSERT INTO submissions (exam_id, student_id, answer_sheet_path, status)
                    VALUES (:exam_id, :student_id, :path, 'pending')
                """),
                {"exam_id": exam_id, "student_id": matched_student.id, "path": f"bulk-upload/{file.filename}"}
            )
        db.commit()

        return {
            "filename": file.filename,
            "matched": True,
            "detected_name": detected_name,
            "detected_nim": detected_nim,
            "student_id": matched_student.id,
            "student_name": matched_student.name,
            "student_nim": matched_student.nim
        }

    except HTTPException:
        raise
    except Exception as e:
        db.rollback()
        logger.error(f"Error in bulk detect/assign: {e}")
        raise HTTPException(status_code=500, detail=str(e))


@app.get("/api/report/exam/{exam_id}/zip")
async def download_all_reports_zip(exam_id: int, db: Session = Depends(get_db)):
    """Download SEMUA laporan Markdown mahasiswa (yang sudah dianalisis AI)
    dalam satu ujian, dikemas jadi satu file ZIP."""
    import zipfile
    from io import BytesIO
    from fastapi.responses import StreamingResponse

    try:
        exam_row = db.execute(
            text("SELECT title FROM exams WHERE id = :exam_id"),
            {"exam_id": exam_id}
        ).fetchone()
        if not exam_row:
            raise HTTPException(status_code=404, detail="Ujian tidak ditemukan.")

        submissions = db.execute(
            text("""
                SELECT sub.id, s.name as student_name, s.nim as student_nim
                FROM submissions sub
                JOIN students s ON sub.student_id = s.id
                WHERE sub.exam_id = :exam_id
                ORDER BY s.name
            """),
            {"exam_id": exam_id}
        ).fetchall()

        zip_buffer = BytesIO()
        included_count = 0

        with zipfile.ZipFile(zip_buffer, 'w', zipfile.ZIP_DEFLATED) as zf:
            for sub in submissions:
                analysis_path = os.path.join(Config.REPORT_DIR, "analysis", f"submission_{sub.id}.json")
                if not os.path.exists(analysis_path):
                    continue

                with open(analysis_path, "r", encoding="utf-8") as f:
                    grading_result = json.load(f)

                md_content = detailed_report_service._build_markdown(
                    {"title": exam_row.title, "class_name": ""},
                    {"name": sub.student_name, "nim": sub.student_nim},
                    grading_result
                )

                safe_name = detailed_report_service._slugify(sub.student_name)
                zf.writestr(f"{sub.student_nim}_{safe_name}.md", md_content)
                included_count += 1

        if included_count == 0:
            raise HTTPException(status_code=404, detail="Belum ada mahasiswa yang selesai dianalisis AI di ujian ini.")

        zip_buffer.seek(0)
        safe_title = re.sub(r'[^a-zA-Z0-9_-]', '_', exam_row.title)

        return StreamingResponse(
            zip_buffer,
            media_type="application/zip",
            headers={"Content-Disposition": f"attachment; filename={safe_title}_laporan.zip"}
        )

    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"Error creating ZIP for exam {exam_id}: {e}")
        raise HTTPException(status_code=500, detail=str(e))


@app.get("/api/report/exam/{exam_id}/excel")
async def download_scores_excel(exam_id: int, db: Session = Depends(get_db)):
    """Download rekap nilai seluruh mahasiswa dalam satu ujian sebagai file Excel."""
    from openpyxl import Workbook
    from openpyxl.styles import Font, PatternFill, Alignment
    from io import BytesIO
    from fastapi.responses import StreamingResponse

    try:
        exam_row = db.execute(
            text("""
                SELECT e.title, c.name as class_name
                FROM exams e
                LEFT JOIN classes c ON e.class_id = c.id
                WHERE e.id = :exam_id
            """),
            {"exam_id": exam_id}
        ).fetchone()
        if not exam_row:
            raise HTTPException(status_code=404, detail="Ujian tidak ditemukan.")

        rows = db.execute(
            text("""
                SELECT s.name as student_name, s.nim as student_nim,
                       sub.status,
                       COALESCE(AVG(sc.score), NULL) as nilai,
                       MAX(sc.feedback) as feedback
                FROM submissions sub
                JOIN students s ON sub.student_id = s.id
                LEFT JOIN scores sc ON sc.submission_id = sub.id
                WHERE sub.exam_id = :exam_id
                GROUP BY s.name, s.nim, sub.status
                ORDER BY s.name
            """),
            {"exam_id": exam_id}
        ).fetchall()

        wb = Workbook()
        ws = wb.active
        ws.title = "Rekap Nilai"

        ws['A1'] = exam_row.title
        ws['A1'].font = Font(bold=True, size=14)
        ws['A2'] = f"Kelas: {exam_row.class_name or '-'}"
        ws.merge_cells('A1:E1')
        ws.merge_cells('A2:E2')

        headers = ['NIM', 'Nama', 'Status', 'Nilai', 'Catatan Singkat']
        header_row = 4
        for col_idx, header in enumerate(headers, 1):
            cell = ws.cell(row=header_row, column=col_idx, value=header)
            cell.font = Font(bold=True, color="FFFFFF")
            cell.fill = PatternFill(start_color="2E4057", end_color="2E4057", fill_type="solid")
            cell.alignment = Alignment(horizontal='center')

        status_label = {
            'pending': 'Menunggu', 'processing': 'Diproses',
            'completed': 'Selesai', 'failed': 'Gagal'
        }

        for i, row in enumerate(rows, start=header_row + 1):
            ws.cell(row=i, column=1, value=row.student_nim)
            ws.cell(row=i, column=2, value=row.student_name)
            ws.cell(row=i, column=3, value=status_label.get(row.status, row.status))
            ws.cell(row=i, column=4, value=round(row.nilai, 2) if row.nilai is not None else '-')
            ws.cell(row=i, column=5, value=row.feedback or '-')

        for col_letter, width in [('A', 18), ('B', 30), ('C', 14), ('D', 10), ('E', 50)]:
            ws.column_dimensions[col_letter].width = width

        excel_buffer = BytesIO()
        wb.save(excel_buffer)
        excel_buffer.seek(0)

        safe_title = re.sub(r'[^a-zA-Z0-9_-]', '_', exam_row.title)

        return StreamingResponse(
            excel_buffer,
            media_type="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
            headers={"Content-Disposition": f"attachment; filename={safe_title}_nilai.xlsx"}
        )

    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"Error creating Excel for exam {exam_id}: {e}")
        raise HTTPException(status_code=500, detail=str(e))
