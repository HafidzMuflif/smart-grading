<?php
// students/edit.php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireAdmin();

$page_title = 'Edit Mahasiswa';
$id = intval($_GET['id'] ?? $_POST['id'] ?? 0);

if ($id <= 0) {
    $_SESSION['error'] = 'ID mahasiswa tidak valid.';
    redirect('index.php');
    exit();
}

try {
    $db = Database::getInstance()->getConnection();

    $stmt = $db->prepare("SELECT * FROM students WHERE id = ?");
    $stmt->execute([$id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        $_SESSION['error'] = 'Mahasiswa tidak ditemukan.';
        redirect('index.php');
        exit();
    }

    $classes = $db->query("SELECT id, name, course_name FROM classes ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $db->prepare("SELECT class_id FROM class_students WHERE student_id = ?");
    $stmt->execute([$id]);
    $enrolledClassIds = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'class_id');
} catch (PDOException $e) {
    error_log("Error fetching student: " . $e->getMessage());
    $_SESSION['error'] = 'Gagal memuat data mahasiswa.';
    redirect('index.php');
    exit();
}

$name = $student['name'];
$nim = $student['nim'];
$selectedClassIds = $enrolledClassIds;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Token keamanan tidak valid. Silahkan coba lagi.';
    } else {
        $name = sanitizeInput($_POST['name'] ?? '');
        $nim = sanitizeInput($_POST['nim'] ?? '');
        $selectedClassIds = array_map('intval', $_POST['class_ids'] ?? []);

        if (empty($name) || empty($nim) || empty($selectedClassIds)) {
            $error = 'Nama, NIM, dan minimal 1 Kelas harus diisi.';
        } else {
            try {
                // Cek NIM unik (kecuali milik mahasiswa ini sendiri)
                $stmt = $db->prepare("SELECT id FROM students WHERE nim = ? AND id != ?");
                $stmt->execute([$nim, $id]);
                if ($stmt->fetch()) {
                    $error = "NIM '{$nim}' sudah dipakai mahasiswa lain.";
                } else {
                    $db->beginTransaction();

                    // class_id lama diisi kelas pertama saja, untuk kompatibilitas mundur
                    $stmt = $db->prepare("UPDATE students SET name = ?, nim = ?, class_id = ? WHERE id = ?");
                    $stmt->execute([$name, $nim, $selectedClassIds[0], $id]);

                    // Reset enrollment lama, insert ulang sesuai pilihan baru
                    $stmt = $db->prepare("DELETE FROM class_students WHERE student_id = ?");
                    $stmt->execute([$id]);

                    $stmt = $db->prepare("INSERT INTO class_students (student_id, class_id) VALUES (?, ?)");
                    foreach ($selectedClassIds as $classId) {
                        $stmt->execute([$id, $classId]);
                    }

                    $db->commit();

                    logUserActivity('Mengubah data mahasiswa', ['id' => $id, 'name' => $name]);

                    $_SESSION['success'] = "Data mahasiswa '{$name}' berhasil diperbarui.";
                    redirect('index.php');
                    exit();
                }
            } catch (PDOException $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                error_log("Error updating student: " . $e->getMessage());
                $error = 'Gagal memperbarui data mahasiswa. Silahkan coba lagi.';
            }
        }
    }
}

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="d-flex align-items-center mb-4">
                <a href="index.php" class="btn btn-outline-secondary btn-sm mr-3">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <h1 class="h3 mb-0"><i class="fas fa-user-edit"></i> Edit Mahasiswa</h1>
            </div>

            <div class="card">
                <div class="card-body">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="" data-validate>
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="id" value="<?php echo $id; ?>">

                        <div class="form-group">
                            <label for="nim">NIM</label>
                            <input type="text"
                                   name="nim"
                                   id="nim"
                                   class="form-control"
                                   value="<?php echo htmlspecialchars($nim); ?>"
                                   maxlength="20"
                                   required
                                   autofocus>
                        </div>

                        <div class="form-group">
                            <label for="name">Nama Lengkap</label>
                            <input type="text"
                                   name="name"
                                   id="name"
                                   class="form-control"
                                   value="<?php echo htmlspecialchars($name); ?>"
                                   maxlength="100"
                                   required>
                        </div>

                        <div class="form-group">
                            <label>Kelas / Mata Kuliah yang Diikuti</label>
                            <div class="border rounded p-3" style="max-height: 250px; overflow-y: auto;">
                                <?php foreach ($classes as $class): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="class_ids[]"
                                               value="<?php echo $class['id']; ?>" id="class_<?php echo $class['id']; ?>"
                                               <?php echo in_array($class['id'], $selectedClassIds) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="class_<?php echo $class['id']; ?>">
                                            <?php echo htmlspecialchars($class['name']); ?> — <?php echo htmlspecialchars($class['course_name']); ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <small class="form-text text-muted">Centang lebih dari satu kalau mahasiswa ini ikut beberapa mata kuliah/kelas sekaligus.</small>
                        </div>

                        <div class="form-group mb-0 mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Simpan Perubahan
                            </button>
                            <a href="index.php" class="btn btn-secondary">Batal</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
