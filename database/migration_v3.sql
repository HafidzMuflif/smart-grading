-- Migration v3: Mahasiswa bisa terdaftar di lebih dari 1 kelas (many-to-many)
-- Jalankan file ini di pgAdmin Query Tool pada database smart_grading

-- Tabel relasi many-to-many: mahasiswa terdaftar di kelas apa saja
CREATE TABLE IF NOT EXISTS class_students (
    id SERIAL PRIMARY KEY,
    student_id INTEGER REFERENCES students(id) ON DELETE CASCADE,
    class_id INTEGER REFERENCES classes(id) ON DELETE CASCADE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(student_id, class_id)
);

CREATE INDEX IF NOT EXISTS idx_class_students_student ON class_students(student_id);
CREATE INDEX IF NOT EXISTS idx_class_students_class ON class_students(class_id);

-- Migrasikan data lama: setiap mahasiswa yang sudah punya class_id,
-- daftarkan ke class_students juga
INSERT INTO class_students (student_id, class_id)
SELECT id, class_id FROM students
WHERE class_id IS NOT NULL
ON CONFLICT (student_id, class_id) DO NOTHING;

-- Catatan: kolom students.class_id SENGAJA TIDAK dihapus, supaya migrasi ini
-- aman dijalankan tanpa merusak data lama. Kolom ini sudah tidak dipakai lagi
-- oleh aplikasi setelah update ini — sumber kebenaran sekarang class_students.
