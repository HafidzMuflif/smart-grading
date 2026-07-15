<?php
// students/delete.php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireAdmin();

$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    $_SESSION['error'] = 'ID mahasiswa tidak valid.';
    redirect('index.php');
    exit();
}

try {
    $db = Database::getInstance()->getConnection();

    $stmt = $db->prepare("SELECT name FROM students WHERE id = ?");
    $stmt->execute([$id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        $_SESSION['error'] = 'Mahasiswa tidak ditemukan.';
        redirect('index.php');
        exit();
    }

    // Hapus mahasiswa (submissions terkait otomatis terhapus via ON DELETE CASCADE)
    $stmt = $db->prepare("DELETE FROM students WHERE id = ?");
    $stmt->execute([$id]);

    logUserActivity('Menghapus mahasiswa', ['id' => $id, 'name' => $student['name']]);

    $_SESSION['success'] = "Mahasiswa '{$student['name']}' berhasil dihapus.";
} catch (PDOException $e) {
    error_log("Error deleting student: " . $e->getMessage());
    $_SESSION['error'] = 'Gagal menghapus mahasiswa. Silahkan coba lagi.';
}

redirect('index.php');
exit();
