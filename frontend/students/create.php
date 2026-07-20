<?php
// students/create.php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireAdmin();

$page_title = 'Tambah Mahasiswa';
$name = '';
$nim = '';
$selectedClassIds = [];
if (!empty($_GET['class_id'])) {
    $selectedClassIds = [intval($_GET['class_id'])];
}

try {
    $db = Database::getInstance()->getConnection();
    $classes = $db->query("SELECT id, name, course_name FROM classes ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching classes: " . $e->getMessage());
    $classes = [];
}

if (empty($classes)) {
    $_SESSION['error'] = 'Belum ada kelas. Buat kelas terlebih dahulu sebelum menambah mahasiswa.';
    redirect('../classes/create.php');
    exit();
}

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
                // Cek NIM unik
                $stmt = $db->prepare("SELECT id FROM students WHERE nim = ?");
                $stmt->execute([$nim]);
                if ($stmt->fetch()) {
                    $error = "NIM '{$nim}' sudah terdaftar. Gunakan NIM lain, atau kalau mahasiswa ini sudah ada, tambahkan kelas lewat halaman Edit.";
                } else {
                    $db->beginTransaction();

                    // class_id lama dibiarkan terisi kelas pertama saja untuk kompatibilitas mundur
                    $stmt = $db->prepare("INSERT INTO students (name, nim, class_id) VALUES (?, ?, ?) RETURNING id");
                    $stmt->execute([$name, $nim, $selectedClassIds[0]]);
                    $newStudentId = $stmt->fetchColumn();

                    $stmt = $db->prepare("INSERT INTO class_students (student_id, class_id) VALUES (?, ?)");
                    foreach ($selectedClassIds as $classId) {
                        $stmt->execute([$newStudentId, $classId]);
                    }

                    $db->commit();

                    logUserActivity('Menambah mahasiswa baru', ['name' => $name, 'nim' => $nim]);

                    $_SESSION['success'] = "Mahasiswa '{$name}' berhasil ditambahkan ke " . count($selectedClassIds) . " kelas.";
                    redirect('index.php');
                    exit();
                }
            } catch (PDOException $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                error_log("Error creating student: " . $e->getMessage());
                $error = 'Gagal menyimpan data mahasiswa. Silahkan coba lagi.';
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
                <h1 class="h3 mb-0"><i class="fas fa-user-plus"></i> Tambah Mahasiswa</h1>
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

                        <div class="form-group">
                            <label for="nim">NIM</label>
                            <input type="text"
                                   name="nim"
                                   id="nim"
                                   class="form-control"
                                   placeholder="Contoh: 101032300136"
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
                                   placeholder="Contoh: Moch. Hafidz Muflif Leksono"
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
                                <i class="fas fa-save"></i> Simpan Mahasiswa
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
