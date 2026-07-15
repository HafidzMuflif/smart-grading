# Smart Grading - Sistem Koreksi Ujian Otomatis

Smart Grading adalah aplikasi berbasis web yang mengotomatiskan proses pengoreksian lembar ujian berbasis tulisan tangan (esai, uraian) menggunakan teknologi OCR (Optical Character Recognition).

## Fitur Utama
- **Upload Lembar Jawaban**: Upload jawaban mahasiswa dalam format PDF
- **Upload Kunci Jawaban**: Upload kunci jawaban sebagai referensi
- **Upload Rubrik Penilaian**: Upload rubrik untuk menentukan kriteria penilaian
- **OCR Processing**: Ekstraksi teks dari tulisan tangan menggunakan EasyOCR
- **Penilaian Otomatis**: Membandingkan jawaban dengan kunci berdasarkan rubrik
- **Laporan PDF**: Generate laporan nilai dalam format PDF
- **Manajemen Data**: Kelola mahasiswa, kelas, dan ujian

## Teknologi
- **Backend**: Python 3.8+ (FastAPI)
- **Frontend**: PHP 8.2
- **OCR**: EasyOCR
- **Database**: PostgreSQL
- **PDF Generation**: ReportLab
- **Deployment**: Docker Compose

## Instalasi

### Prasyarat
- Docker & Docker Compose
- Python 3.8+
- PHP 8.2+
- PostgreSQL 15+

### Langkah Instalasi

1. Clone repository:
```bash
git clone https://github.com/yourusername/smart-grading.git
cd smart-grading