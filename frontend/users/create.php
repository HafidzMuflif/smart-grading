<?php
// users/create.php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireAdmin();

$page_title = 'Tambah Akun Dosen';
$username = '';
$email = '';
$selectedClassIds = [];

try {
    $db = Database::getInstance()->getConnection();
    $classes = $db->query("SELECT id, name, course_name FROM classes ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching classes: " . $e->getMessage());
    $classes = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Token keamanan tidak valid. Silahkan coba lagi.';
    } else {
        $username = sanitizeInput($_POST['username'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $selectedClassIds = array_map('intval', $_POST['class_ids'] ?? []);

        if (empty($username) || empty($email) || empty($password)) {
            $error = 'Username, email, dan password harus diisi.';
        } elseif (!validateEmail($email)) {
            $error = 'Format email tidak valid.';
        } elseif (strlen($password) < 6) {
            $error = 'Password minimal 6 karakter.';
        } else {
            try {
                $stmt = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
                $stmt->execute([$username, $email]);
                if ($stmt->fetch()) {
                    $error = 'Username atau email sudah dipakai.';
                } else {
                    $db->beginTransaction();

                    $passwordHash = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = $db->prepare("INSERT INTO users (username, password_hash, email, role) VALUES (?, ?, ?, 'dosen') RETURNING id");
                    $stmt->execute([$username, $passwordHash, $email]);
                    $newUserId = $stmt->fetchColumn();

                    if (!empty($selectedClassIds)) {
                        $stmt = $db->prepare("INSERT INTO teacher_classes (user_id, class_id) VALUES (?, ?)");
                        foreach ($selectedClassIds as $classId) {
                            $stmt->execute([$newUserId, $classId]);
                        }
                    }

                    $db->commit();

                    logUserActivity('Membuat akun dosen baru', ['username' => $username]);

                    $_SESSION['success'] = "Akun dosen '{$username}' berhasil dibuat.";
                    redirect('index.php');
                    exit();
                }
            } catch (PDOException $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                error_log("Error creating dosen account: " . $e->getMessage());
                $error = 'Gagal menyimpan akun dosen. Silahkan coba lagi.';
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
                <h1 class="h3 mb-0"><i class="fas fa-user-plus"></i> Tambah Akun Dosen</h1>
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
                            <label for="username">Username</label>
                            <input type="text" name="username" id="username" class="form-control"
                                   value="<?php echo htmlspecialchars($username); ?>" maxlength="50" required autofocus>
                        </div>

                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" name="email" id="email" class="form-control"
                                   value="<?php echo htmlspecialchars($email); ?>" maxlength="100" required>
                        </div>

                        <div class="form-group">
                            <label for="password">Password</label>
                            <input type="password" name="password" id="password" class="form-control"
                                   minlength="6" required>
                            <small class="form-text text-muted">Minimal 6 karakter.</small>
                        </div>

                        <div class="form-group">
                            <label>Kelas yang Diampu</label>
                            <?php if (empty($classes)): ?>
                                <p class="text-muted">Belum ada kelas. <a href="../classes/create.php">Buat kelas dulu</a>.</p>
                            <?php else: ?>
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
                                <small class="form-text text-muted">Dosen hanya akan melihat kelas yang dicentang di dashboard-nya.</small>
                            <?php endif; ?>
                        </div>

                        <div class="form-group mb-0 mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Simpan Akun Dosen
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
