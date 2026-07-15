<?php
// exams/create.php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireLogin();

$page_title = 'Buat Ujian Baru';
$title = '';
$class_id = intval($_GET['class_id'] ?? 0);

try {
    $db = Database::getInstance()->getConnection();
    $classes = $db->query("SELECT id, name, course_name FROM classes WHERE 1=1 " . classAccessWhereClause('id') . " ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching classes: " . $e->getMessage());
    $classes = [];
}

if (empty($classes)) {
    $_SESSION['error'] = 'Belum ada kelas. Buat kelas terlebih dahulu sebelum membuat ujian.';
    redirect('../classes/create.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Token keamanan tidak valid. Silahkan coba lagi.';
    } else {
        $title = sanitizeInput($_POST['title'] ?? '');
        $class_id = intval($_POST['class_id'] ?? 0);

        if (empty($title) || $class_id <= 0) {
            $error = 'Judul ujian dan kelas harus diisi.';
        } elseif (!canAccessClass($class_id)) {
            $error = 'Anda tidak memiliki akses ke kelas ini.';
        } elseif (empty($_FILES['answer_key']['name'])) {
            $error = 'Kunci jawaban (PDF) wajib diupload.';
        } elseif (empty($_FILES['rubric']['name'])) {
            $error = 'Rubrik penilaian (PDF) wajib diupload — dipakai AI untuk menilai jawaban mahasiswa.';
        } else {
            try {
                $answerKeyPath = null;
                $rubricPath = null;

                $uploadDir = __DIR__ . '/../uploads/exams';
                $frontendRoot = realpath(__DIR__ . '/..');

                // Upload kunci jawaban (wajib, PDF)
                $absoluteKeyPath = uploadFile($_FILES['answer_key'], $uploadDir, ['md']);
                $answerKeyPath = ltrim(str_replace($frontendRoot, '', realpath($absoluteKeyPath)), '/\\');

                // Upload rubrik (wajib, PDF)
                $absoluteRubricPath = uploadFile($_FILES['rubric'], $uploadDir, ['md']);
                $rubricPath = ltrim(str_replace($frontendRoot, '', realpath($absoluteRubricPath)), '/\\');

                $stmt = $db->prepare("
                    INSERT INTO exams (title, class_id, answer_key_path, rubric_path)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$title, $class_id, $answerKeyPath, $rubricPath]);

                logUserActivity('Membuat ujian baru', ['title' => $title]);

                $_SESSION['success'] = "Ujian '{$title}' berhasil dibuat.";
                redirect('index.php');
                exit();
            } catch (Exception $e) {
                error_log("Error creating exam: " . $e->getMessage());
                $error = 'Gagal menyimpan ujian: ' . $e->getMessage();
            }
        }
    }
}

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-md-7">
            <div class="d-flex align-items-center mb-4">
                <a href="index.php" class="btn btn-outline-secondary btn-sm mr-3">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <h1 class="h3 mb-0"><i class="fas fa-plus-circle"></i> Buat Ujian Baru</h1>
            </div>

            <div class="card">
                <div class="card-body">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="" enctype="multipart/form-data" data-validate>
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                        <div class="form-group">
                            <label for="title">Judul Ujian</label>
                            <input type="text"
                                   name="title"
                                   id="title"
                                   class="form-control"
                                   placeholder="Contoh: UTS Sistem Operasi"
                                   value="<?php echo htmlspecialchars($title); ?>"
                                   maxlength="200"
                                   required
                                   autofocus>
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

                        <div class="form-group">
                            <label>Kunci Jawaban (Markdown) <span class="text-danger">*</span></label>
                            <div class="upload-zone">
                                <input type="file" name="answer_key" accept=".md" required style="display:none;">
                                <div class="upload-zone-content">
                                    <i class="fas fa-file-alt fa-2x text-danger mb-2"></i>
                                    <p class="mb-0">Klik atau drag file .md kunci jawaban ke sini</p>
                                </div>
                                <div class="file-preview mt-2"></div>
                            </div>
                            <small class="form-text text-muted">Format Markdown (.md), berisi solusi/jawaban referensi — dipakai AI sebagai acuan.</small>
                        </div>

                        <div class="form-group">
                            <label>Rubrik Penilaian (Markdown) <span class="text-danger">*</span></label>
                            <div class="upload-zone">
                                <input type="file" name="rubric" accept=".md" required style="display:none;">
                                <div class="upload-zone-content">
                                    <i class="fas fa-clipboard-list fa-2x text-info mb-2"></i>
                                    <p class="mb-0">Klik atau drag file .md rubrik ke sini</p>
                                </div>
                                <div class="file-preview mt-2"></div>
                            </div>
                            <small class="form-text text-muted">Wajib format Markdown (.md) berisi kriteria & level penilaian — dipakai AI untuk menilai jawaban mahasiswa.</small>
                        </div>

                        <div class="form-group mb-0 mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Simpan Ujian
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
