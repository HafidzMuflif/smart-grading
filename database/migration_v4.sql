-- Migration v4: Nomor absen mahasiswa per kelas, dan folder upload per ujian
-- Jalankan file ini di pgAdmin Query Tool pada database smart_grading

-- Nomor absen bersifat per-ENROLLMENT (bukan per-mahasiswa global), karena
-- nomor urut mahasiswa di kolom "No." pada Excel berbeda-beda tiap kelas.
ALTER TABLE class_students ADD COLUMN IF NOT EXISTS absen INTEGER;
CREATE INDEX IF NOT EXISTS idx_class_students_absen ON class_students(class_id, absen);

-- Folder fisik penyimpanan jawaban untuk suatu ujian (dibuat sekali saat ujian
-- dibuat, namanya berbasis judul ujian supaya mudah ditelusuri manual, dan
-- TIDAK berubah walau judul ujian diedit kemudian).
ALTER TABLE exams ADD COLUMN IF NOT EXISTS upload_folder VARCHAR(255);
