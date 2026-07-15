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
} catch (PDOException $e) {
    error_log("Error fetching student: " . $e->getMessage());
    $_SESSION['error'] = 'Gagal memuat data mahasiswa.';
    redirect('index.php');
    exit();
}

$name = $student['name'];
$nim = $student['nim'];
$class_id = $student['class_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Token keamanan tidak valid. Silahkan coba lagi.';
    } else {
        $name = sanitizeInput($_POST['name'] ?? '');
        $nim = sanitizeInput($_POST['nim'] ?? '');
        $class_id = intval($_POST['class_id'] ?? 0);

        if (empty($name) || empty($nim) || $class_id <= 0) {
            $error = 'Nama, NIM, dan Kelas harus diisi.';
        } else {
            try {
                // Cek NIM unik (kecuali milik mahasiswa ini sendiri)
                $stmt = $db->prepare("SELECT id FROM students WHERE nim = ? AND id != ?");
                $stmt->execute([$nim, $id]);
                if ($stmt->fetch()) {
                    $error = "NIM '{$nim}' sudah dipakai mahasiswa lain.";
                } else {
                    $stmt = $db->prepare("UPDATE students SET name = ?, nim = ?, class_id = ? WHERE id = ?");
                    $stmt->execute([$name, $nim, $class_id, $id]);

                    logUserActivity('Mengubah data mahasiswa', ['id' => $id, 'name' => $name]);

                    $_SESSION['success'] = "Data mahasiswa '{$name}' berhasil diperbarui.";
                    redirect('index.php');
                    exit();
                }
            } catch (PDOException $e) {
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
                            <label for="class_id">Kelas</label>
                            <select name="class_id" id="class_id" class="form-control" required>
                                <option value="">-- Pilih Kelas --</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?php echo $class['id']; ?>" <?php echo $class_id == $class['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($class['name']); ?> — <?php echo htmlspecialchars($class['course_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
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
