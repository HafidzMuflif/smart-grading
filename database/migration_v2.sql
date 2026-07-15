-- Migration v2: Role-based access control (Admin/Dosen)
-- Jalankan file ini di pgAdmin Query Tool pada database smart_grading
-- (untuk instalasi baru, sudah otomatis termasuk lewat schema.sql)

-- Tabel relasi many-to-many: dosen mengajar kelas apa saja
CREATE TABLE IF NOT EXISTS teacher_classes (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    class_id INTEGER REFERENCES classes(id) ON DELETE CASCADE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(user_id, class_id)
);

CREATE INDEX IF NOT EXISTS idx_teacher_classes_user ON teacher_classes(user_id);
CREATE INDEX IF NOT EXISTS idx_teacher_classes_class ON teacher_classes(class_id);

-- Pastikan role user cuma 'admin' atau 'dosen'
-- (Tidak dipaksa pakai CHECK constraint supaya tidak konflik data lama)
