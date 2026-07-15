<?php
// exams/delete.php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireLogin();

$id = intval($_GET['id'] ?? 0);

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

    // Hapus file fisik kunci jawaban & rubrik kalau ada
    $frontendRoot = realpath(__DIR__ . '/..');
    foreach (['answer_key_path', 'rubric_path'] as $field) {
        if (!empty($exam[$field])) {
            $filePath = $frontendRoot . '/' . $exam[$field];
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
        }
    }

    // Hapus ujian (submissions & scores terkait otomatis terhapus via ON DELETE CASCADE)
    $stmt = $db->prepare("DELETE FROM exams WHERE id = ?");
    $stmt->execute([$id]);

    logUserActivity('Menghapus ujian', ['id' => $id, 'title' => $exam['title']]);

    $_SESSION['success'] = "Ujian '{$exam['title']}' berhasil dihapus.";
} catch (PDOException $e) {
    error_log("Error deleting exam: " . $e->getMessage());
    $_SESSION['error'] = 'Gagal menghapus ujian. Silahkan coba lagi.';
}

redirect('index.php');
exit();
