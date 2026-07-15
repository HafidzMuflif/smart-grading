<?php
// exams/edit.php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireLogin();

$page_title = 'Edit Ujian';
$id = intval($_GET['id'] ?? $_POST['id'] ?? 0);

if ($id <= 0) {
    $_SESSION['error'] = 'ID ujian tidak valid.';
    redirect('index.php');
    exit();
}

try {
    $db = Database::getInstance()->getConnection();

    $stmt = $db->prepare("SELECT * FROM exams WHERE id = ?");
    $stmt->execute([$id]);
    $exam = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$exam) {
        $_SESSION['error'] = 'Ujian tidak ditemukan.';
        redirect('index.php');
        exit();
    }

    if (!canAccessClass($exam['class_id'])) {
        $_SESSION['error'] = 'Anda tidak memiliki akses ke ujian ini.';
        redirect('index.php');
        exit();
    }

    $classes = $db->query("SELECT id, name, course_name FROM classes WHERE 1=1 " . classAccessWhereClause('id') . " ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching exam: " . $e->getMessage());
    $_SESSION['error'] = 'Gagal memuat data ujian.';
    redirect('index.php');
    exit();
}

$title = $exam['title'];
$class_id = $exam['class_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Token keamanan tidak valid. Silahkan coba lagi.';
    } else {
        $title = sanitizeInput($_POST['title'] ?? '');
        $class_id = intval($_POST['class_id'] ?? 0);

        if (empty($title) || $class_id <= 0) {
            $error = 'Judul ujian dan kelas harus diisi.';
        } else {
            try {
                $answerKeyPath = $exam['answer_key_path'];
                $rubricPath = $exam['rubric_path'];
                $frontendRoot = realpath(__DIR__ . '/..');
                $uploadDir = __DIR__ . '/../uploads/exams';

                // Ganti kunci jawaban kalau upload file baru
                if (!empty($_FILES['answer_key']['name'])) {
                    $absoluteKeyPath = uploadFile($_FILES['answer_key'], $uploadDir, ['md']);
                    $answerKeyPath = ltrim(str_replace($frontendRoot, '', realpath($absoluteKeyPath)), '/\\');
                }

                // Ganti rubrik kalau upload file baru
                if (!empty($_FILES['rubric']['name'])) {
                    $absoluteRubricPath = uploadFile($_FILES['rubric'], $uploadDir, ['md']);
                    $rubricPath = ltrim(str_replace($frontendRoot, '', realpath($absoluteRubricPath)), '/\\');
                }

                $stmt = $db->prepare("
                    UPDATE exams SET title = ?, class_id = ?, answer_key_path = ?, rubric_path = ?
                    WHERE id = ?
                ");
                $stmt->execute([$title, $class_id, $answerKeyPath, $rubricPath, $id]);

                logUserActivity('Mengubah data ujian', ['id' => $id, 'title' => $title]);

                $_SESSION['success'] = "Ujian '{$title}' berhasil diperbarui.";
                redirect('index.php');
                exit();
            } catch (Exception $e) {
                error_log("Error updating exam: " . $e->getMessage());
                $error = 'Gagal memperbarui ujian: ' . $e->getMessage();
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
                <h1 class="h3 mb-0"><i class="fas fa-edit"></i> Edit Ujian</h1>
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
                        <input type="hidden" name="id" value="<?php echo $id; ?>">

                        <div class="form-group">
                            <label for="title">Judul Ujian</label>
                            <input type="text"
                                   name="title"
                                   id="title"
                                   class="form-control"
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
                            <label>Kunci Jawaban (Markdown)</label>
                            <?php if ($exam['answer_key_path']): ?>
                                <div class="alert alert-light border py-2 mb-2">
                                    <i class="fas fa-file-alt text-danger"></i> File saat ini: <?php echo htmlspecialchars(basename($exam['answer_key_path'])); ?>
                                </div>
                            <?php endif; ?>
                            <div class="upload-zone">
                                <input type="file" name="answer_key" accept=".md" style="display:none;">
                                <div class="upload-zone-content">
                                    <i class="fas fa-file-alt fa-2x text-danger mb-2"></i>
                                    <p class="mb-0">Klik untuk ganti file kunci jawaban (opsional)</p>
                                </div>
                                <div class="file-preview mt-2"></div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Rubrik Penilaian (Markdown)</label>
                            <?php if ($exam['rubric_path']): ?>
                                <div class="alert alert-light border py-2 mb-2">
                                    <i class="fas fa-clipboard-list text-info"></i> File saat ini: <?php echo htmlspecialchars(basename($exam['rubric_path'])); ?>
                                </div>
                            <?php endif; ?>
                            <div class="upload-zone">
                                <input type="file" name="rubric" accept=".md" style="display:none;">
                                <div class="upload-zone-content">
                                    <i class="fas fa-clipboard-list fa-2x text-info mb-2"></i>
                                    <p class="mb-0">Klik untuk ganti file rubrik (opsional)</p>
                                </div>
                                <div class="file-preview mt-2"></div>
                            </div>
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
