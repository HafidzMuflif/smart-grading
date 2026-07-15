import os
import logging
from datetime import datetime
from typing import Dict

logger = logging.getLogger(__name__)


class DetailedReportService:
    """Membuat laporan penilaian dalam format MARKDOWN (.md): rekapitulasi
    nilai per bagian, analisis kelebihan/kekurangan, saran perbaikan, dan
    kesimpulan — hasil dari AIGradingService. Format Markdown dipilih karena
    lebih mudah dibaca/diproses lebih lanjut dibanding PDF."""

    def __init__(self, output_dir: str):
        self.output_dir = os.path.join(output_dir, 'md_reports')
        os.makedirs(self.output_dir, exist_ok=True)

    def generate(self, exam_data: Dict, student_data: Dict, grading_result: Dict, output_filename: str = None) -> str:
        if not output_filename:
            safe_nim = str(student_data.get('nim', 'unknown'))
            safe_name = self._slugify(student_data.get('name', 'mahasiswa'))
            output_filename = f"{safe_nim}_{safe_name}.md"

        filepath = os.path.join(self.output_dir, output_filename)

        md = self._build_markdown(exam_data, student_data, grading_result)

        with open(filepath, 'w', encoding='utf-8') as f:
            f.write(md)

        logger.info(f"Detailed MD report generated: {filepath}")
        return filepath

    def _build_markdown(self, exam_data: Dict, student_data: Dict, grading_result: Dict) -> str:
        lines = []
        sections = grading_result.get('sections', [])

        lines.append(f"# Laporan Penilaian Ujian")
        lines.append(f"## {exam_data.get('title', '-')}")
        lines.append("")

        # A. Identitas
        lines.append("## A. Identitas Mahasiswa")
        lines.append("")
        lines.append("| Field | Keterangan |")
        lines.append("|---|---|")
        lines.append(f"| Nama | {student_data.get('name', '-')} |")
        lines.append(f"| NIM | {student_data.get('nim', '-')} |")
        lines.append(f"| Kelas | {exam_data.get('class_name', '-')} |")
        lines.append(f"| Ujian | {exam_data.get('title', '-')} |")
        lines.append(f"| Tanggal Dinilai | {datetime.now().strftime('%d %B %Y')} |")
        lines.append("")

        # B. Rekapitulasi Nilai
        lines.append("## B. Rekapitulasi Nilai")
        lines.append("")
        lines.append("| Bagian | Bobot | Skor Mentah | Level | Nilai Akhir |")
        lines.append("|---|---|---|---|---|")
        for sec in sections:
            lines.append(
                f"| {sec.get('name', '-')} | {sec.get('bobot_persen', 0)}% | "
                f"{sec.get('skor_mentah', 0)} | Level {sec.get('level', '-')} ({sec.get('level_label', '-')}) | "
                f"{sec.get('nilai_akhir', 0):.2f} |"
            )
        lines.append(f"| **TOTAL** | **100%** | - | - | **{grading_result.get('nilai_total', 0):.2f}** |")
        lines.append("")
        lines.append(f"**Nilai Akhir: {grading_result.get('nilai_total', 0):.2f}** — "
                      f"Huruf: **{grading_result.get('huruf', '-')}** "
                      f"({grading_result.get('keterangan_huruf', '-')})")
        lines.append("")

        # C. Analisis per bagian
        lines.append("## C. Analisis Jawaban per Bagian")
        lines.append("")
        for idx, sec in enumerate(sections, 1):
            lines.append(f"### C.{idx}. {sec.get('name', '-')} (Skor: {sec.get('skor_mentah', 0)}/100)")
            lines.append("")

            if sec.get('kelebihan'):
                lines.append("**✅ Kelebihan (Yang Dipahami dengan Baik)**")
                lines.append("")
                for point in sec['kelebihan']:
                    lines.append(f"- {point}")
                lines.append("")

            if sec.get('kekurangan'):
                lines.append("**⚠️ Kekurangan & Area Perbaikan**")
                lines.append("")
                for point in sec['kekurangan']:
                    lines.append(f"- {point}")
                lines.append("")

            if sec.get('interpretasi'):
                lines.append(f"**💡 Interpretasi Pemahaman Proses:** {sec['interpretasi']}")
                lines.append("")

        # D. Saran Perbaikan
        if grading_result.get('saran_perbaikan'):
            lines.append("## D. Saran Perbaikan")
            lines.append("")
            for saran in grading_result['saran_perbaikan']:
                lines.append(f"### Untuk {saran.get('bagian', '-')}")
                lines.append("")
                for item in saran.get('items', []):
                    lines.append(f"- {item}")
                lines.append("")

        # E. Kesimpulan
        lines.append("## E. Kesimpulan")
        lines.append("")
        if grading_result.get('kesimpulan'):
            lines.append(grading_result['kesimpulan'])
            lines.append("")

        if grading_result.get('potensi'):
            lines.append("**Potensi yang Terlihat:**")
            lines.append("")
            for point in grading_result['potensi']:
                lines.append(f"- {point}")
            lines.append("")

        if grading_result.get('area_diperkuat'):
            lines.append("**Area yang Perlu Diperkuat:**")
            lines.append("")
            for point in grading_result['area_diperkuat']:
                lines.append(f"- {point}")
            lines.append("")

        lines.append("---")
        lines.append("")
        lines.append("*Laporan ini dibuat secara otomatis oleh Smart Grading AI berdasarkan rubrik "
                      "penilaian yang telah ditentukan. Dosen tetap dapat meninjau dan menyesuaikan nilai akhir.*")

        return "\n".join(lines)

    def _slugify(self, text: str) -> str:
        text = text.lower().strip()
        text = ''.join(c if c.isalnum() or c == ' ' else '' for c in text)
        return text.replace(' ', '_')[:50]
