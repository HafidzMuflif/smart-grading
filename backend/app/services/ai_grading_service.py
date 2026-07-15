import json
import logging
import re
from typing import Dict, List

import google.generativeai as genai
from pdf2image import convert_from_path
from io import BytesIO

from app.config import Config

logger = logging.getLogger(__name__)


class AIGradingService:
    """Menilai lembar jawaban mahasiswa (hasil scan/foto, termasuk tulisan
    tangan) terhadap rubrik penilaian, menggunakan Gemini vision (Google AI
    Studio). Hasilnya berupa analisis kualitatif terstruktur (bukan sekadar
    angka), mengikuti prinsip proses vs hasil seperti pada rubrik 4-level."""

    def __init__(self):
        if not Config.GEMINI_API_KEY or Config.GEMINI_API_KEY.startswith('isi-dengan'):
            raise ValueError(
                "GEMINI_API_KEY belum diisi di file .env backend. "
                "Isi dulu dengan API key dari aistudio.google.com/api-keys sebelum memakai fitur AI grading."
            )
        genai.configure(api_key=Config.GEMINI_API_KEY)
        self.model = genai.GenerativeModel(Config.GEMINI_MODEL)

    def _pdf_to_images(self, pdf_path: str, dpi: int = 150) -> List[bytes]:
        """Konversi tiap halaman PDF jadi gambar PNG (raw bytes) untuk dikirim ke Gemini vision."""
        images = convert_from_path(pdf_path, dpi=dpi)
        encoded = []
        for img in images:
            buffer = BytesIO()
            img.save(buffer, format="PNG")
            encoded.append(buffer.getvalue())
        return encoded

    def _build_prompt(self, student_name: str, student_nim: str, rubric_text: str, answer_key_text: str) -> str:
        return f"""Anda adalah asisten dosen yang bertugas menilai lembar jawaban ujian mahasiswa berdasarkan rubrik penilaian dan kunci jawaban yang diberikan.

DATA MAHASISWA:
- Nama: {student_name}
- NIM: {student_nim}

KUNCI JAWABAN / SOLUSI REFERENSI (gunakan sebagai acuan jawaban yang benar):
---
{answer_key_text}
---

RUBRIK PENILAIAN (gunakan ini sebagai acuan skor, level, bobot, dan formula):
---
{rubric_text}
---

TUGAS ANDA:
1. Baca lembar jawaban mahasiswa pada gambar yang dilampirkan (termasuk tulisan tangan, coretan, dan tabel).
2. Bandingkan jawaban mahasiswa dengan KUNCI JAWABAN / SOLUSI REFERENSI di atas untuk menilai keakuratan.
3. Nilai SETIAP bagian/komponen sesuai struktur dan level (1-4) yang didefinisikan di RUBRIK PENILAIAN.
4. Gunakan prinsip proses (cara berpikir, kelengkapan analisis) DAN hasil (keakuratan jawaban akhir dibanding kunci jawaban) sesuai bobot yang tercantum di rubrik.
5. Hitung skor mentah, level, dan nilai akhir per bagian sesuai formula yang ada di rubrik.
6. Jumlahkan menjadi nilai akhir total (skala 0-100), lalu konversi ke huruf sesuai tabel konversi di rubrik (jika ada).
7. Berikan analisis konstruktif: kelebihan yang sudah dipahami dengan baik, kekurangan/area perbaikan, dan interpretasi pemahaman proses mahasiswa untuk SETIAP bagian.
8. Berikan saran perbaikan konkret per bagian, serta kesimpulan umum (potensi yang terlihat & area yang perlu diperkuat).
9. Gunakan Bahasa Indonesia yang profesional, membangun, dan tidak menghakimi — fokus pada proses pembelajaran mahasiswa.

PENTING - FORMAT OUTPUT:
Balas HANYA dengan JSON valid (tanpa markdown code fence, tanpa teks pembuka/penutup), dengan struktur PERSIS seperti ini:

{{
  "sections": [
    {{
      "name": "nama bagian sesuai rubrik, misal: Bagian A - Analisis Entitas & Atribut",
      "bobot_persen": 30,
      "skor_mentah": 51,
      "level": 2,
      "level_label": "Cukup",
      "nilai_akhir": 15.3,
      "kelebihan": ["poin kelebihan 1", "poin kelebihan 2"],
      "kekurangan": ["poin kekurangan 1", "poin kekurangan 2"],
      "interpretasi": "paragraf interpretasi pemahaman proses mahasiswa untuk bagian ini"
    }}
  ],
  "nilai_total": 51.0,
  "huruf": "D",
  "keterangan_huruf": "Perlu perbaikan signifikan",
  "saran_perbaikan": [
    {{"bagian": "Bagian A", "items": ["saran 1", "saran 2"]}}
  ],
  "potensi": ["poin potensi mahasiswa 1", "poin potensi mahasiswa 2"],
  "area_diperkuat": ["area yang perlu diperkuat 1", "area yang perlu diperkuat 2"],
  "kesimpulan": "paragraf kesimpulan umum tentang performa mahasiswa"
}}

Pastikan angka nilai_akhir tiap bagian dan nilai_total dihitung secara matematis benar sesuai formula di rubrik, bukan estimasi."""

    def detect_student_identity(self, pdf_path: str) -> Dict:
        """Baca halaman pertama lembar jawaban, deteksi nama & NIM mahasiswa
        yang tertulis di situ. Dipakai untuk fitur upload massal (folder)."""
        try:
            images = self._pdf_to_images(pdf_path, dpi=120)
            if not images:
                raise Exception("Tidak bisa membaca file PDF.")

            prompt = """Lihat gambar lembar jawaban ujian ini. Cari nama mahasiswa dan NIM yang tertulis
(biasanya di bagian atas/header lembar jawaban).

Balas HANYA dengan JSON valid (tanpa markdown code fence), format PERSIS:
{"name": "nama yang terbaca, atau string kosong jika tidak ketemu", "nim": "NIM yang terbaca (hanya angka), atau string kosong jika tidak ketemu"}"""

            content = [prompt, {"mime_type": "image/png", "data": images[0]}]

            response = self.model.generate_content(
                content,
                generation_config=genai.types.GenerationConfig(max_output_tokens=1024, temperature=0.1)
            )

            result = self._parse_json_response(self._extract_text(response))
            return {
                "name": str(result.get("name", "")).strip(),
                "nim": re.sub(r'\D', '', str(result.get("nim", "")))
            }
        except Exception as e:
            logger.error(f"Error detecting student identity: {e}")
            return {"name": "", "nim": ""}

    def grade_exam(self, answer_sheet_pdf_path: str, rubric_text: str, answer_key_text: str, student_name: str, student_nim: str) -> Dict:
        """Nilai satu lembar jawaban mahasiswa terhadap rubrik. Mengembalikan
        dict sesuai skema yang diminta di prompt (sections, nilai_total, dst)."""
        try:
            images = self._pdf_to_images(answer_sheet_pdf_path)
            if not images:
                raise Exception("Tidak bisa mengkonversi PDF lembar jawaban menjadi gambar.")

            prompt = self._build_prompt(student_name, student_nim, rubric_text, answer_key_text)

            content = [prompt]
            for img_bytes in images:
                content.append({"mime_type": "image/png", "data": img_bytes})

            response = self.model.generate_content(
                content,
                generation_config=genai.types.GenerationConfig(
                    max_output_tokens=16384,
                    temperature=0.2,
                )
            )

            raw_text = self._extract_text(response)
            return self._parse_json_response(raw_text)

        except Exception as e:
            logger.error(f"Error in AI grading (Gemini): {e}")
            raise Exception(f"Gagal memanggil Gemini API: {e}")

    def _extract_text(self, response) -> str:
        """Ambil teks dari respons Gemini dengan aman, dan log alasan kalau
        jawabannya terpotong (misal kehabisan token buat 'thinking')."""
        try:
            candidate = response.candidates[0]
            finish_reason = getattr(candidate, 'finish_reason', None)
            if finish_reason is not None and str(finish_reason) not in ('1', 'STOP', 'FinishReason.STOP'):
                logger.warning(f"Gemini finish_reason tidak normal: {finish_reason} (kemungkinan jawaban terpotong)")

            parts_text = []
            for part in candidate.content.parts:
                if hasattr(part, 'text') and part.text:
                    parts_text.append(part.text)

            if parts_text:
                return "".join(parts_text)
        except Exception as e:
            logger.warning(f"Gagal ekstrak via candidates, fallback ke response.text: {e}")

        return response.text

    def _parse_json_response(self, raw_text: str) -> Dict:
        """Bersihkan & parse respons JSON dari Gemini, dengan fallback kalau
        model tetap membungkus jawabannya dengan markdown code fence."""
        text = raw_text.strip()
        text = re.sub(r'^```(?:json)?\s*', '', text)
        text = re.sub(r'\s*```$', '', text)

        try:
            return json.loads(text)
        except json.JSONDecodeError as e:
            logger.error(f"Gagal parse JSON dari Gemini: {e}\nRaw response: {raw_text[:2000]}")
            raise Exception("Respons AI tidak dalam format JSON yang valid. Coba proses ulang.")
