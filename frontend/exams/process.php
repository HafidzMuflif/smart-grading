<?php
// exams/process.php
// Mengirim kunci jawaban ujian ke backend FastAPI untuk diproses (OCR + grading, GRATIS - tanpa API key)
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
    exit();
}

if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    $_SESSION['error'] = 'Token keamanan tidak valid.';
    redirect('index.php');
    exit();
}

$examId = intval($_POST['exam_id'] ?? 0);

if ($examId <= 0) {
    $_SESSION['error'] = 'ID ujian tidak valid.';
    redirect('index.php');
    exit();
}

try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT * FROM exams WHERE id = ?");
    $stmt->execute([$examId]);
    $exam = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$exam) {
        $_SESSION['error'] = 'Ujian tidak ditemukan.';
        redirect('index.php');
        exit();
    }

    if (empty($exam['answer_key_path'])) {
        $_SESSION['error'] = 'Ujian ini belum punya kunci jawaban. Upload dulu lewat halaman Edit.';
        redirect('view.php?id=' . $examId);
        exit();
    }

    $frontendRoot = realpath(__DIR__ . '/..');
    $answerKeyFullPath = $frontendRoot . '/' . $exam['answer_key_path'];

    if (!file_exists($answerKeyFullPath)) {
        $_SESSION['error'] = 'File kunci jawaban tidak ditemukan di server.';
        redirect('view.php?id=' . $examId);
        exit();
    }

    // Kirim ke backend FastAPI via multipart/form-data
    $ch = curl_init();
    $postFields = [
        'exam_id' => (string) $examId,
        'answer_key' => new CURLFile($answerKeyFullPath, 'application/pdf', basename($answerKeyFullPath)),
    ];

    if (!empty($exam['rubric_path'])) {
        $rubricFullPath = $frontendRoot . '/' . $exam['rubric_path'];
        if (file_exists($rubricFullPath)) {
            $postFields['rubric'] = new CURLFile($rubricFullPath, 'application/octet-stream', basename($rubricFullPath));
        }
    }

    curl_setopt($ch, CURLOPT_URL, API_BASE_URL . '/api/process/exam');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, API_TIMEOUT);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        throw new Exception("Tidak bisa terhubung ke backend: {$curlError}. Pastikan backend FastAPI (python3 run.py) sedang berjalan di " . API_BASE_URL);
    }

    if ($httpCode !== 200) {
        throw new Exception("Backend merespon dengan error (HTTP {$httpCode}): " . $response);
    }

    $result = json_decode($response, true);

    // Tandai semua submission pending sebagai 'processing'
    $stmt = $db->prepare("UPDATE submissions SET status = 'processing' WHERE exam_id = ? AND status = 'pending'");
    $stmt->execute([$examId]);

    logUserActivity('Memproses penilaian ujian (mode gratis)', ['exam_id' => $examId]);

    $_SESSION['success'] = 'Permintaan penilaian otomatis (mode gratis) berhasil dikirim ke backend. ' . ($result['message'] ?? '');
} catch (Exception $e) {
    error_log("Error processing exam: " . $e->getMessage());
    $_SESSION['error'] = 'Gagal memproses ujian: ' . $e->getMessage();
}

redirect('view.php?id=' . $examId);
exit();
