<?php
// classes/delete.php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireAdmin();

$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    $_SESSION['error'] = 'ID kelas tidak valid.';
    redirect('index.php');
    exit();
}

try {
    $db = Database::getInstance()->getConnection();

    // Ambil nama kelas dulu untuk pesan & log
    $stmt = $db->prepare("SELECT name FROM classes WHERE id = ?");
    $stmt->execute([$id]);
    $class = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$class) {
        $_SESSION['error'] = 'Kelas tidak ditemukan.';
        redirect('index.php');
        exit();
    }

    // Hapus kelas (students & exams terkait otomatis terhapus via ON DELETE CASCADE di schema)
    $stmt = $db->prepare("DELETE FROM classes WHERE id = ?");
    $stmt->execute([$id]);

    logUserActivity('Menghapus kelas', ['id' => $id, 'name' => $class['name']]);

    $_SESSION['success'] = "Kelas '{$class['name']}' berhasil dihapus.";
} catch (PDOException $e) {
    error_log("Error deleting class: " . $e->getMessage());
    $_SESSION['error'] = 'Gagal menghapus kelas. Silahkan coba lagi.';
}

redirect('index.php');
exit();
