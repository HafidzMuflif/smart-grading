<?php
// exams/delete_submission.php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireLogin();

$submissionId = intval($_GET['submission_id'] ?? 0);
$examId = intval($_GET['exam_id'] ?? 0);

if ($submissionId <= 0 || $examId <= 0) {
    $_SESSION['error'] = 'Data tidak valid.';
    redirect('index.php');
    exit();
}

try {
    $db = Database::getInstance()->getConnection();

    $stmt = $db->prepare("
        SELECT sub.id, sub.answer_sheet_path, s.name as student_name, e.class_id
        FROM submissions sub
        JOIN students s ON sub.student_id = s.id
        JOIN exams e ON sub.exam_id = e.id
        WHERE sub.id = ? AND sub.exam_id = ?
    ");
    $stmt->execute([$submissionId, $examId]);
    $submission = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$submission) {
        $_SESSION['error'] = 'Submission tidak ditemukan.';
        redirect('view.php?id=' . $examId);
        exit();
    }

    if (!canAccessClass($submission['class_id'])) {
        $_SESSION['error'] = 'Anda tidak memiliki akses ke submission ini.';
        redirect('index.php');
        exit();
    }

    // Hapus file jawaban fisik di sisi frontend (kalau ada dan bukan placeholder upload massal)
    if (!empty($submission['answer_sheet_path']) && strpos($submission['answer_sheet_path'], 'bulk-upload/') !== 0) {
        $frontendRoot = realpath(__DIR__ . '/..');
        $filePath = $frontendRoot . '/' . $submission['answer_sheet_path'];
        if (file_exists($filePath)) {
            @unlink($filePath);
        }
    }

    // Hapus laporan analisis AI (JSON) di sisi backend, kalau ada
    $analysisPath = realpath(__DIR__ . '/../..') . '/backend/reports/analysis/submission_' . $submissionId . '.json';
    if (file_exists($analysisPath)) {
        @unlink($analysisPath);
    }

    // Hapus submission (scores ikut terhapus otomatis via ON DELETE CASCADE)
    $stmt = $db->prepare("DELETE FROM submissions WHERE id = ?");
    $stmt->execute([$submissionId]);

    logUserActivity('Menghapus submission', ['submission_id' => $submissionId, 'student' => $submission['student_name']]);

    $_SESSION['success'] = "Submission '{$submission['student_name']}' berhasil dihapus.";
} catch (PDOException $e) {
    error_log("Error deleting submission: " . $e->getMessage());
    $_SESSION['error'] = 'Gagal menghapus submission. Silahkan coba lagi.';
}

redirect('view.php?id=' . $examId);
exit();
