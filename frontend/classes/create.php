<?php
// classes/create.php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireAdmin();

$page_title = 'Tambah Kelas';
$name = '';
$course_name = '';

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
                $db = Database::getInstance()->getConnection();
                $stmt = $db->prepare("INSERT INTO classes (name, course_name) VALUES (?, ?)");
                $stmt->execute([$name, $course_name]);

                logUserActivity('Membuat kelas baru', ['name' => $name]);

                $_SESSION['success'] = "Kelas '{$name}' berhasil dibuat.";
                redirect('index.php');
                exit();
            } catch (PDOException $e) {
                error_log("Error creating class: " . $e->getMessage());
                $error = 'Gagal menyimpan kelas. Silahkan coba lagi.';
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
                <h1 class="h3 mb-0"><i class="fas fa-plus-circle"></i> Tambah Kelas Baru</h1>
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
                            <label for="name">Nama Kelas</label>
                            <input type="text"
                                   name="name"
                                   id="name"
                                   class="form-control"
                                   placeholder="Contoh: TK-47-03"
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
                                   placeholder="Contoh: Sistem Operasi"
                                   value="<?php echo htmlspecialchars($course_name); ?>"
                                   maxlength="100"
                                   required>
                        </div>

                        <div class="form-group mb-0 mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Simpan Kelas
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
