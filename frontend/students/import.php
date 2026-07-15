<?php
// students/import.php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/xlsx_reader.php';

requireAdmin();

$page_title = 'Import Mahasiswa dari Excel';
$results = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Token keamanan tidak valid. Silahkan coba lagi.';
    } elseif (empty($_FILES['excel_file']['name'])) {
        $error = 'File Excel (.xlsx) wajib diupload.';
    } else {
        $extension = strtolower(pathinfo($_FILES['excel_file']['name'], PATHINFO_EXTENSION));
        if ($extension !== 'xlsx') {
            $error = 'File harus berformat .xlsx (Excel 2007 ke atas). File .xls lama tidak didukung.';
        } else {
            try {
                $db = Database::getInstance()->getConnection();

                // Simpan file sementara untuk dibaca
                $tmpDir = __DIR__ . '/../uploads/imports';
                if (!is_dir($tmpDir)) {
                    mkdir($tmpDir, 0777, true);
                }
                $tmpPath = $tmpDir . '/' . uniqid() . '_' . basename($_FILES['excel_file']['name']);
                move_uploaded_file($_FILES['excel_file']['tmp_name'], $tmpPath);

                $rows = readXlsxRows($tmpPath);
                @unlink($tmpPath); // hapus file sementara setelah dibaca

                // Baris pertama dianggap header, mulai dari baris ke-2
                $success = [];
                $failed = [];

                // Cache daftar kelas (nama -> id) supaya tidak query berulang
                $classMap = [];
                $classRows = $db->query("SELECT id, name FROM classes")->fetchAll(PDO::FETCH_ASSOC);
                foreach ($classRows as $c) {
                    $classMap[strtolower(trim($c['name']))] = $c['id'];
                }

                for ($i = 1; $i < count($rows); $i++) {
                    $row = $rows[$i];
                    $name = trim($row[0] ?? '');
                    $nim = trim($row[1] ?? '');
                    $className = trim($row[2] ?? '');

                    if (empty($name) && empty($nim) && empty($className)) {
                        continue; // baris kosong, skip
                    }

                    $rowLabel = "Baris " . ($i + 1) . " ({$name})";

                    if (empty($name) || empty($nim) || empty($className)) {
                        $failed[] = "{$rowLabel}: Nama, NIM, atau Kelas kosong.";
                        continue;
                    }

                    $classKey = strtolower($className);
                    if (!isset($classMap[$classKey])) {
                        $failed[] = "{$rowLabel}: Kelas '{$className}' tidak ditemukan di sistem.";
                        continue;
                    }

                    // Kalau dosen (bukan admin) somehow akses ini... tapi ini admin-only page jadi aman.
                    $classId = $classMap[$classKey];

                    try {
                        $stmt = $db->prepare("SELECT id FROM students WHERE nim = ?");
                        $stmt->execute([$nim]);
                        if ($stmt->fetch()) {
                            $failed[] = "{$rowLabel}: NIM '{$nim}' sudah terdaftar.";
                            continue;
                        }

                        $stmt = $db->prepare("INSERT INTO students (name, nim, class_id) VALUES (?, ?, ?)");
                        $stmt->execute([sanitizeInput($name), sanitizeInput($nim), $classId]);
                        $success[] = "{$rowLabel}: berhasil ditambahkan ke kelas {$className}.";
                    } catch (PDOException $e) {
                        $failed[] = "{$rowLabel}: Gagal simpan ke database ({$e->getMessage()}).";
                    }
                }

                $results = ['success' => $success, 'failed' => $failed];

                if (!empty($success)) {
                    logUserActivity('Import mahasiswa dari Excel', ['jumlah_berhasil' => count($success)]);
                }

            } catch (Exception $e) {
                error_log("Error importing Excel: " . $e->getMessage());
                $error = 'Gagal membaca file Excel: ' . $e->getMessage();
            }
        }
    }
}

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="d-flex align-items-center mb-4">
                <a href="index.php" class="btn btn-outline-secondary btn-sm mr-3">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <h1 class="h3 mb-0"><i class="fas fa-file-excel"></i> Import Mahasiswa dari Excel</h1>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> <strong>Format file Excel (.xlsx):</strong>
                        <p class="mb-1 mt-2">Kolom A = Nama, Kolom B = NIM, Kolom C = Nama Kelas (harus persis sama dengan nama kelas yang sudah ada di sistem).</p>
                        <p class="mb-0">Baris pertama dianggap header (judul kolom) dan akan dilewati otomatis.</p>
                        <table class="table table-sm table-bordered mt-2 mb-0 bg-white">
                            <thead><tr><th>Nama</th><th>NIM</th><th>Kelas</th></tr></thead>
                            <tbody>
                                <tr><td>Budi Santoso</td><td>101032400001</td><td>TK-48-05</td></tr>
                                <tr><td>Siti Aminah</td><td>101032400002</td><td>TK-48-05</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <form method="POST" action="" enctype="multipart/form-data" data-validate>
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                        <div class="form-group">
                            <label>File Excel (.xlsx)</label>
                            <div class="upload-zone">
                                <input type="file" name="excel_file" accept=".xlsx" required style="display:none;">
                                <div class="upload-zone-content">
                                    <i class="fas fa-file-excel fa-2x text-success mb-2"></i>
                                    <p class="mb-0">Klik atau drag file .xlsx ke sini</p>
                                </div>
                                <div class="file-preview mt-2"></div>
                            </div>
                        </div>

                        <div class="form-group mb-0 mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-upload"></i> Import Sekarang
                            </button>
                            <a href="index.php" class="btn btn-secondary">Batal</a>
                        </div>
                    </form>
                </div>
            </div>

            <?php if ($results !== null): ?>
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-list-check"></i> Hasil Import</h5>
                    </div>
                    <div class="card-body">
                        <p>
                            <span class="badge badge-success">Berhasil: <?php echo count($results['success']); ?></span>
                            <span class="badge badge-danger">Gagal: <?php echo count($results['failed']); ?></span>
                        </p>

                        <?php if (!empty($results['success'])): ?>
                            <h6 class="text-success mt-3"><i class="fas fa-check-circle"></i> Berhasil</h6>
                            <ul class="small">
                                <?php foreach ($results['success'] as $msg): ?>
                                    <li><?php echo htmlspecialchars($msg); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>

                        <?php if (!empty($results['failed'])): ?>
                            <h6 class="text-danger mt-3"><i class="fas fa-times-circle"></i> Gagal</h6>
                            <ul class="small">
                                <?php foreach ($results['failed'] as $msg): ?>
                                    <li><?php echo htmlspecialchars($msg); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>

                        <a href="index.php" class="btn btn-primary btn-sm mt-2">
                            <i class="fas fa-users"></i> Lihat Daftar Mahasiswa
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
