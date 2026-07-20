<?php
// exams/view.php
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

    $stmt = $db->prepare("
        SELECT e.*, c.name as class_name
        FROM exams e
        LEFT JOIN classes c ON e.class_id = c.id
        WHERE e.id = ?
    ");
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

    $page_title = $exam['title'];

    // Mahasiswa di kelas ujian ini (untuk dropdown upload submission)
    $stmt = $db->prepare("
        SELECT s.id, s.name, s.nim
        FROM students s
        JOIN class_students cs ON cs.student_id = s.id
        WHERE cs.class_id = ?
        ORDER BY s.name
    ");
    $stmt->execute([$exam['class_id']]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Daftar submission untuk ujian ini
    $stmt = $db->prepare("
        SELECT sub.*, s.name as student_name, s.nim,
               (SELECT AVG(sc.score) FROM scores sc WHERE sc.submission_id = sub.id) as avg_score
        FROM submissions sub
        JOIN students s ON sub.student_id = s.id
        WHERE sub.exam_id = ?
        ORDER BY s.name
    ");
    $stmt->execute([$id]);
    $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error fetching exam detail: " . $e->getMessage());
    $_SESSION['error'] = 'Gagal memuat detail ujian.';
    redirect('index.php');
    exit();
}

// Handle upload submission jawaban mahasiswa
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_submission') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'Token keamanan tidak valid.';
    } else {
        $student_id = intval($_POST['student_id'] ?? 0);

        if ($student_id <= 0 || empty($_FILES['answer_sheet']['name'])) {
            $_SESSION['error'] = 'Pilih mahasiswa dan upload file jawaban (PDF).';
        } else {
            try {
                $uploadDir = __DIR__ . '/../uploads/submissions/' . $id;
                $absolutePath = uploadFile($_FILES['answer_sheet'], $uploadDir, ['pdf']);
                $frontendRoot = realpath(__DIR__ . '/..');
                $relativePath = ltrim(str_replace($frontendRoot, '', realpath($absolutePath)), '/\\');

                // Kirim salinan file ke backend FastAPI supaya bisa di-OCR & dinilai nanti
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, API_BASE_URL . '/api/upload/answersheet');
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, [
                    'exam_id' => (string) $id,
                    'student_id' => (string) $student_id,
                    'file' => new CURLFile(realpath($absolutePath), 'application/pdf', basename($absolutePath)),
                ]);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                curl_exec($ch);
                $backendError = curl_error($ch);
                curl_close($ch);

                if ($backendError) {
                    error_log("Gagal forward submission ke backend: " . $backendError);
                }

                // Cek apakah mahasiswa ini sudah submit sebelumnya
                $stmt = $db->prepare("SELECT id FROM submissions WHERE exam_id = ? AND student_id = ?");
                $stmt->execute([$id, $student_id]);
                $existing = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($existing) {
                    $stmt = $db->prepare("UPDATE submissions SET answer_sheet_path = ?, status = 'pending' WHERE id = ?");
                    $stmt->execute([$relativePath, $existing['id']]);
                } else {
                    $stmt = $db->prepare("
                        INSERT INTO submissions (exam_id, student_id, answer_sheet_path, status)
                        VALUES (?, ?, ?, 'pending')
                    ");
                    $stmt->execute([$id, $student_id, $relativePath]);
                }

                logUserActivity('Upload jawaban mahasiswa', ['exam_id' => $id, 'student_id' => $student_id]);
                $_SESSION['success'] = 'Jawaban mahasiswa berhasil diupload.';
            } catch (Exception $e) {
                error_log("Error uploading submission: " . $e->getMessage());
                $_SESSION['error'] = 'Gagal upload jawaban: ' . $e->getMessage();
            }
        }
    }
    redirect('view.php?id=' . $id);
    exit();
}

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="d-flex align-items-center mb-4">
                <a href="index.php" class="btn btn-outline-secondary btn-sm mr-3">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div>
                    <h1 class="h3 mb-0"><?php echo htmlspecialchars($exam['title']); ?></h1>
                    <small class="text-muted">
                        <i class="fas fa-school"></i> <?php echo htmlspecialchars($exam['class_name'] ?? 'N/A'); ?>
                    </small>
                </div>
            </div>

            <div class="row">
                <!-- Info & Aksi Ujian -->
                <div class="col-md-4">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-info-circle"></i> Info Ujian</h5>
                        </div>
                        <div class="card-body">
                            <p class="mb-2">
                                <i class="fas fa-file-alt text-danger"></i> Kunci Jawaban:
                                <?php if ($exam['answer_key_path']): ?>
                                    <br><span class="text-muted"><?php echo htmlspecialchars(basename($exam['answer_key_path'])); ?></span>
                                <?php else: ?>
                                    <span class="text-danger">Belum diupload</span>
                                <?php endif; ?>
                            </p>
                            <p class="mb-3">
                                <i class="fas fa-clipboard-list text-info"></i> Rubrik Penilaian:
                                <?php if ($exam['rubric_path']): ?>
                                    <br><span class="text-muted"><?php echo htmlspecialchars(basename($exam['rubric_path'])); ?></span>
                                <?php else: ?>
                                    <span class="text-danger">Belum diupload — upload dulu lewat halaman Edit sebelum bisa dianalisis AI.</span>
                                <?php endif; ?>
                            </p>

                            <?php if (empty($submissions)): ?>
                                <div class="alert alert-warning alert-permanent py-2 mb-0">
                                    <i class="fas fa-exclamation-triangle"></i> Belum ada jawaban mahasiswa yang diupload.
                                </div>
                            <?php else: ?>
                                <small class="text-muted d-block">
                                    Gunakan tombol <strong>"Analisis AI"</strong> di tabel submission untuk menilai jawaban tiap mahasiswa berdasarkan rubrik & kunci jawaban.
                                </small>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Upload Jawaban Mahasiswa -->
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-upload"></i> Upload Jawaban Mahasiswa</h5>
                            <a href="bulk_upload.php?id=<?php echo $id; ?>" class="btn btn-sm btn-outline-primary" title="Upload banyak file sekaligus, AI deteksi nama otomatis">
                                <i class="fas fa-robot"></i> Upload Massal
                            </a>
                        </div>
                        <div class="card-body">
                            <?php if (empty($students)): ?>
                                <p class="text-muted mb-0">Belum ada mahasiswa di kelas ini.</p>
                            <?php else: ?>
                                <form method="POST" action="" enctype="multipart/form-data" data-validate>
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <input type="hidden" name="action" value="upload_submission">

                                    <div class="form-group">
                                        <label for="student_id">Mahasiswa</label>
                                        <select name="student_id" id="student_id" class="form-control" required>
                                            <option value="">-- Pilih Mahasiswa --</option>
                                            <?php foreach ($students as $student): ?>
                                                <option value="<?php echo $student['id']; ?>">
                                                    <?php echo htmlspecialchars($student['nim'] . ' - ' . $student['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="form-group mb-0">
                                        <div class="upload-zone">
                                            <input type="file" name="answer_sheet" accept=".pdf" required style="display:none;">
                                            <div class="upload-zone-content">
                                                <i class="fas fa-cloud-upload-alt fa-2x text-primary mb-2"></i>
                                                <p class="mb-0">Klik atau drag file jawaban (PDF)</p>
                                            </div>
                                            <div class="file-preview mt-2"></div>
                                        </div>
                                    </div>

                                    <button type="submit" class="btn btn-primary btn-block mt-3">
                                        <i class="fas fa-upload"></i> Upload Jawaban
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Daftar Submission -->
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
                            <h5 class="mb-0"><i class="fas fa-list"></i> Submission Mahasiswa (<?php echo count($submissions); ?>)</h5>
                            <div>
                                <a href="<?php echo API_BASE_URL; ?>/api/report/exam/<?php echo $id; ?>/zip" class="btn btn-sm btn-outline-success" title="Download semua laporan Markdown (ZIP)">
                                    <i class="fas fa-file-archive"></i> Semua Laporan (ZIP)
                                </a>
                                <a href="<?php echo API_BASE_URL; ?>/api/report/exam/<?php echo $id; ?>/excel" class="btn btn-sm btn-outline-primary" title="Download rekap nilai (Excel)">
                                    <i class="fas fa-file-excel"></i> Excel Nilai
                                </a>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if (empty($submissions)): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">Belum ada jawaban mahasiswa yang diupload</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>NIM</th>
                                                <th>Nama</th>
                                                <th>Status</th>
                                                <th>Nilai</th>
                                                <th>Diupload</th>
                                                <th class="text-right">Analisis AI</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($submissions as $sub): ?>
                                                <tr>
                                                    <td><code><?php echo htmlspecialchars($sub['nim']); ?></code></td>
                                                    <td><?php echo htmlspecialchars($sub['student_name']); ?></td>
                                                    <td>
                                                        <?php
                                                        $statusMap = [
                                                            'pending' => ['label' => 'Menunggu', 'class' => 'secondary'],
                                                            'processing' => ['label' => 'Diproses', 'class' => 'warning'],
                                                            'completed' => ['label' => 'Selesai', 'class' => 'success'],
                                                            'failed' => ['label' => 'Gagal', 'class' => 'danger'],
                                                        ];
                                                        $status = $statusMap[$sub['status']] ?? ['label' => $sub['status'], 'class' => 'secondary'];
                                                        ?>
                                                        <span class="badge badge-<?php echo $status['class']; ?>"><?php echo $status['label']; ?></span>
                                                    </td>
                                                    <td>
                                                        <?php if ($sub['avg_score'] !== null): ?>
                                                            <strong><?php echo number_format($sub['avg_score'], 1); ?></strong>
                                                        <?php else: ?>
                                                            <span class="text-muted">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($sub['created_at'])); ?></small></td>
                                                    <td class="text-right">
                                                        <button type="button"
                                                                class="btn btn-sm btn-outline-primary btn-ai-grade"
                                                                data-submission-id="<?php echo $sub['id']; ?>"
                                                                title="Analisis dengan AI">
                                                            <i class="fas fa-robot"></i> Analisis AI
                                                        </button>
                                                        <?php if ($sub['status'] === 'completed'): ?>
                                                            <a href="<?php echo API_BASE_URL; ?>/api/report/detailed/<?php echo $sub['id']; ?>"
                                                               class="btn btn-sm btn-outline-success"
                                                               download
                                                               title="Download Laporan Markdown (.md)">
                                                                <i class="fas fa-file-alt"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                        <a href="delete_submission.php?submission_id=<?php echo $sub['id']; ?>&exam_id=<?php echo $id; ?>"
                                                           class="btn btn-sm btn-outline-danger"
                                                           title="Hapus Submission"
                                                           data-confirm-delete
                                                           data-confirm-message="Yakin ingin menghapus submission '<?php echo htmlspecialchars($sub['student_name']); ?>'? File jawaban dan hasil analisis AI (kalau ada) akan ikut terhapus.">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.btn-ai-grade').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const submissionId = this.getAttribute('data-submission-id');
            const originalHtml = this.innerHTML;

            this.disabled = true;
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menganalisis...';

            fetch('<?php echo API_BASE_URL; ?>/api/grade/submission/' + submissionId + '/ai', {
                method: 'POST'
            })
            .then(function(response) {
                return response.json().then(function(data) {
                    return { ok: response.ok, data: data };
                });
            })
            .then(function(result) {
                if (result.ok) {
                    alert('Analisis selesai! Nilai: ' + result.data.nilai_total + ' (' + result.data.huruf + ')');
                    location.reload();
                } else {
                    alert('Gagal: ' + (result.data.detail || 'Terjadi kesalahan.'));
                    btn.disabled = false;
                    btn.innerHTML = originalHtml;
                }
            })
            .catch(function(err) {
                alert('Tidak bisa terhubung ke backend. Pastikan server FastAPI sedang berjalan.');
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            });
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>
