<?php
// users/edit.php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireAdmin();

$page_title = 'Edit Akun Dosen';
$id = intval($_GET['id'] ?? $_POST['id'] ?? 0);

if ($id <= 0) {
    $_SESSION['error'] = 'ID dosen tidak valid.';
    redirect('index.php');
    exit();
}

try {
    $db = Database::getInstance()->getConnection();

    $stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND role = 'dosen'");
    $stmt->execute([$id]);
    $dosen = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$dosen) {
        $_SESSION['error'] = 'Akun dosen tidak ditemukan.';
        redirect('index.php');
        exit();
    }

    $classes = $db->query("SELECT id, name, course_name FROM classes ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $db->prepare("SELECT class_id FROM teacher_classes WHERE user_id = ?");
    $stmt->execute([$id]);
    $assignedClassIds = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'class_id');
} catch (PDOException $e) {
    error_log("Error fetching dosen: " . $e->getMessage());
    $_SESSION['error'] = 'Gagal memuat data dosen.';
    redirect('index.php');
    exit();
}

$username = $dosen['username'];
$email = $dosen['email'];
$selectedClassIds = $assignedClassIds;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Token keamanan tidak valid. Silahkan coba lagi.';
    } else {
        $username = sanitizeInput($_POST['username'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        $newPassword = $_POST['password'] ?? '';
        $selectedClassIds = array_map('intval', $_POST['class_ids'] ?? []);

        if (empty($username) || empty($email)) {
            $error = 'Username dan email harus diisi.';
        } elseif (!validateEmail($email)) {
            $error = 'Format email tidak valid.';
        } elseif (!empty($newPassword) && strlen($newPassword) < 6) {
            $error = 'Password baru minimal 6 karakter.';
        } else {
            try {
                $stmt = $db->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
                $stmt->execute([$username, $email, $id]);
                if ($stmt->fetch()) {
                    $error = 'Username atau email sudah dipakai user lain.';
                } else {
                    $db->beginTransaction();

                    if (!empty($newPassword)) {
                        $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);
                        $stmt = $db->prepare("UPDATE users SET username = ?, email = ?, password_hash = ? WHERE id = ?");
                        $stmt->execute([$username, $email, $passwordHash, $id]);
                    } else {
                        $stmt = $db->prepare("UPDATE users SET username = ?, email = ? WHERE id = ?");
                        $stmt->execute([$username, $email, $id]);
                    }

                    // Reset assignment kelas, lalu insert ulang sesuai pilihan baru
                    $stmt = $db->prepare("DELETE FROM teacher_classes WHERE user_id = ?");
                    $stmt->execute([$id]);

                    if (!empty($selectedClassIds)) {
                        $stmt = $db->prepare("INSERT INTO teacher_classes (user_id, class_id) VALUES (?, ?)");
                        foreach ($selectedClassIds as $classId) {
                            $stmt->execute([$id, $classId]);
                        }
                    }

                    $db->commit();

                    logUserActivity('Mengubah akun dosen', ['id' => $id, 'username' => $username]);

                    $_SESSION['success'] = "Akun dosen '{$username}' berhasil diperbarui.";
                    redirect('index.php');
                    exit();
                }
            } catch (PDOException $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                error_log("Error updating dosen account: " . $e->getMessage());
                $error = 'Gagal memperbarui akun dosen. Silahkan coba lagi.';
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
                <h1 class="h3 mb-0"><i class="fas fa-user-edit"></i> Edit Akun Dosen</h1>
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
                            <label for="password">Password Baru</label>
                            <input type="password" name="password" id="password" class="form-control" minlength="6">
                            <small class="form-text text-muted">Kosongkan kalau tidak mau mengganti password.</small>
                        </div>

                        <div class="form-group">
                            <label>Kelas yang Diampu</label>
                            <?php if (empty($classes)): ?>
                                <p class="text-muted">Belum ada kelas.</p>
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
                            <?php endif; ?>
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
