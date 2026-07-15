<?php
// users/delete.php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireAdmin();

$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    $_SESSION['error'] = 'ID dosen tidak valid.';
    redirect('index.php');
    exit();
}

if ($id === (int) $_SESSION['user_id']) {
    $_SESSION['error'] = 'Tidak bisa menghapus akun sendiri.';
    redirect('index.php');
    exit();
}

try {
    $db = Database::getInstance()->getConnection();

    $stmt = $db->prepare("SELECT username FROM users WHERE id = ? AND role = 'dosen'");
    $stmt->execute([$id]);
    $dosen = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$dosen) {
        $_SESSION['error'] = 'Akun dosen tidak ditemukan.';
        redirect('index.php');
        exit();
    }

    $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$id]);

    logUserActivity('Menghapus akun dosen', ['id' => $id, 'username' => $dosen['username']]);

    $_SESSION['success'] = "Akun dosen '{$dosen['username']}' berhasil dihapus.";
} catch (PDOException $e) {
    error_log("Error deleting dosen: " . $e->getMessage());
    $_SESSION['error'] = 'Gagal menghapus akun dosen.';
}

redirect('index.php');
exit();
