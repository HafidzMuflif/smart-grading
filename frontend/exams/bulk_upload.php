<?php
// exams/bulk_upload.php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireLogin();

$id = intval($_GET['id'] ?? $_POST['exam_id'] ?? 0);

if ($id <= 0) {
    $_SESSION['error'] = 'ID ujian tidak valid.';
    redirect('index.php');
    exit();
}

try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT e.*, c.name as class_name FROM exams e LEFT JOIN classes c ON e.class_id = c.id WHERE e.id = ?");
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
} catch (PDOException $e) {
    error_log("Error fetching exam: " . $e->getMessage());
    $_SESSION['error'] = 'Gagal memuat data ujian.';
    redirect('index.php');
    exit();
}

$page_title = 'Upload Massal - ' . $exam['title'];
$results = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['bulk_files'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Token keamanan tidak valid. Silahkan coba lagi.';
    } else {
        $files = $_FILES['bulk_files'];
        $fileCount = count($files['name']);
        $results = ['matched' => [], 'unmatched' => [], 'errors' => []];

        for ($i = 0; $i < $fileCount; $i++) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                $results['errors'][] = $files['name'][$i] . ': gagal upload.';
                continue;
            }

            $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
            if ($ext !== 'pdf') {
                $results['errors'][] = $files['name'][$i] . ': bukan file PDF, dilewati.';
                continue;
            }

            // Forward ke backend untuk dideteksi & dicocokkan
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, API_BASE_URL . '/api/upload/bulk-detect');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, [
                'exam_id' => (string) $id,
                'file' => new CURLFile($files['tmp_name'][$i], 'application/pdf', $files['name'][$i]),
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                $results['errors'][] = $files['name'][$i] . ": tidak bisa terhubung ke backend ({$curlError}).";
                continue;
            }

            $data = json_decode($response, true);

            if ($httpCode !== 200 || !$data) {
                $results['errors'][] = $files['name'][$i] . ': ' . ($data['detail'] ?? 'gagal diproses backend.');
                continue;
            }

            if ($data['matched']) {
                $results['matched'][] = $data;
            } else {
                $results['unmatched'][] = $data;
            }
        }

        if (!empty($results['matched'])) {
            logUserActivity('Upload massal jawaban', ['exam_id' => $id, 'jumlah_matched' => count($results['matched'])]);
        }
    }
}

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="d-flex align-items-center mb-4">
                <a href="view.php?id=<?php echo $id; ?>" class="btn btn-outline-secondary btn-sm mr-3">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div>
                    <h1 class="h3 mb-0"><i class="fas fa-folder-open"></i> Upload Massal Jawaban</h1>
                    <small class="text-muted"><?php echo htmlspecialchars($exam['title']); ?> — <?php echo htmlspecialchars($exam['class_name']); ?></small>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <div class="alert alert-info alert-permanent">
                        <i class="fas fa-robot"></i> Pilih banyak file PDF sekaligus (jawaban seluruh/sebagian mahasiswa di kelas ini).
                        AI akan membaca nama & NIM di tiap file, lalu otomatis mencocokkan ke mahasiswa terdaftar.
                        <strong>Setiap file memanggil AI satu kali</strong> — perhatikan jumlah file untuk efisiensi kuota API.
                    </div>

                    <form method="POST" action="" enctype="multipart/form-data" id="bulkUploadForm">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="exam_id" value="<?php echo $id; ?>">

                        <div class="form-group">
                            <label>Pilih File PDF (bisa banyak sekaligus)</label>
                            <input type="file" name="bulk_files[]" id="bulkFileInput" accept=".pdf" multiple class="form-control-file" required>
                            <small class="form-text text-muted">Tips: buka folder berisi jawaban, lalu pilih semua file PDF sekaligus (Ctrl/Cmd + A).</small>
                        </div>

                        <div id="selectedFilesList" class="mb-3"></div>

                        <button type="submit" class="btn btn-primary" id="bulkSubmitBtn">
                            <i class="fas fa-upload"></i> Proses & Deteksi Otomatis
                        </button>
                    </form>
                </div>
            </div>

            <?php if ($results !== null): ?>
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-list-check"></i> Hasil Deteksi</h5>
                    </div>
                    <div class="card-body">
                        <p>
                            <span class="badge badge-success">Berhasil dicocokkan: <?php echo count($results['matched']); ?></span>
                            <span class="badge badge-warning">Tidak ketemu: <?php echo count($results['unmatched']); ?></span>
                            <?php if (!empty($results['errors'])): ?>
                                <span class="badge badge-danger">Error: <?php echo count($results['errors']); ?></span>
                            <?php endif; ?>
                        </p>

                        <?php if (!empty($results['matched'])): ?>
                            <h6 class="text-success mt-3"><i class="fas fa-check-circle"></i> Berhasil Dicocokkan</h6>
                            <table class="table table-sm">
                                <thead><tr><th>File</th><th>Terdeteksi</th><th>Dicocokkan ke</th></tr></thead>
                                <tbody>
                                    <?php foreach ($results['matched'] as $m): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($m['filename']); ?></td>
                                            <td><?php echo htmlspecialchars($m['detected_name'] ?: '-'); ?> (<?php echo htmlspecialchars($m['detected_nim'] ?: '-'); ?>)</td>
                                            <td><strong><?php echo htmlspecialchars($m['student_name']); ?></strong> — <?php echo htmlspecialchars($m['student_nim']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>

                        <?php if (!empty($results['unmatched'])): ?>
                            <h6 class="text-warning mt-3"><i class="fas fa-exclamation-triangle"></i> Tidak Ditemukan Kecocokan</h6>
                            <p class="text-muted small">File ini perlu diupload manual lewat form "Upload Jawaban Mahasiswa" di halaman detail ujian.</p>
                            <table class="table table-sm">
                                <thead><tr><th>File</th><th>Terdeteksi AI</th></tr></thead>
                                <tbody>
                                    <?php foreach ($results['unmatched'] as $u): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($u['filename']); ?></td>
                                            <td><?php echo htmlspecialchars($u['detected_name'] ?: '(nama tidak terbaca)'); ?> (<?php echo htmlspecialchars($u['detected_nim'] ?: '-'); ?>)</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>

                        <?php if (!empty($results['errors'])): ?>
                            <h6 class="text-danger mt-3"><i class="fas fa-times-circle"></i> Error</h6>
                            <ul class="small">
                                <?php foreach ($results['errors'] as $e): ?>
                                    <li><?php echo htmlspecialchars($e); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>

                        <a href="view.php?id=<?php echo $id; ?>" class="btn btn-primary btn-sm mt-2">
                            <i class="fas fa-eye"></i> Lihat Detail Ujian
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.getElementById('bulkFileInput').addEventListener('change', function() {
    const list = document.getElementById('selectedFilesList');
    if (this.files.length === 0) {
        list.innerHTML = '';
        return;
    }
    let html = '<div class="alert alert-light border py-2"><strong>' + this.files.length + ' file dipilih:</strong><ul class="mb-0 small">';
    for (let i = 0; i < this.files.length; i++) {
        html += '<li>' + this.files[i].name + '</li>';
    }
    html += '</ul></div>';
    list.innerHTML = html;
});

document.getElementById('bulkUploadForm').addEventListener('submit', function() {
    const btn = document.getElementById('bulkSubmitBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses (mohon tunggu, ini bisa memakan waktu)...';
});
</script>

<?php include '../includes/footer.php'; ?>
