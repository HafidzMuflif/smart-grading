<?php
// classes/edit.php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireAdmin();

$page_title = 'Edit Kelas';
$id = intval($_GET['id'] ?? $_POST['id'] ?? 0);

if ($id <= 0) {
    $_SESSION['error'] = 'ID kelas tidak valid.';
    redirect('index.php');
    exit();
}

try {
    $db = Database::getInstance()->getConnection();

    // Ambil data kelas yang mau diedit
    $stmt = $db->prepare("SELECT * FROM classes WHERE id = ?");
    $stmt->execute([$id]);
    $class = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$class) {
        $_SESSION['error'] = 'Kelas tidak ditemukan.';
        redirect('index.php');
        exit();
    }
} catch (PDOException $e) {
    error_log("Error fetching class: " . $e->getMessage());
    $_SESSION['error'] = 'Gagal memuat data kelas.';
    redirect('index.php');
    exit();
}

$name = $class['name'];
$course_name = $class['course_name'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Token keamanan tidak valid. Silahkan coba lagi.';
    } else {
        $name = sanitizeInput($_POST['name'] ?? '');
        $course_name = sanitizeInput($_POST['course_name'] ?? '');

        if (empty($name) || empty($course_name)) {
            $error = 'Nama kelas dan mata kuliah harus diisi.';
        } else {
            try {
                $stmt = $db->prepare("UPDATE classes SET name = ?, course_name = ? WHERE id = ?");
                $stmt->execute([$name, $course_name, $id]);

                logUserActivity('Mengubah data kelas', ['id' => $id, 'name' => $name]);

                $_SESSION['success'] = "Kelas '{$name}' berhasil diperbarui.";
                redirect('index.php');
                exit();
            } catch (PDOException $e) {
                error_log("Error updating class: " . $e->getMessage());
                $error = 'Gagal memperbarui kelas. Silahkan coba lagi.';
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
                <h1 class="h3 mb-0"><i class="fas fa-edit"></i> Edit Kelas</h1>
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
                            <label for="name">Nama Kelas</label>
                            <input type="text"
                                   name="name"
                                   id="name"
                                   class="form-control"
                                   value="<?php echo htmlspecialchars($name); ?>"
                                   maxlength="100"
                                   required
                                   autofocus>
                        </div>

                        <div class="form-group">
                            <label for="course_name">Mata Kuliah</label>
                            <input type="text"
                                   name="course_name"
                                   id="course_name"
                                   class="form-control"
                                   value="<?php echo htmlspecialchars($course_name); ?>"
                                   maxlength="100"
                                   required>
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
