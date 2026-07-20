<?php
// students/import.php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/xlsx_reader.php';

requireAdmin();

$page_title = 'Import Mahasiswa dari Excel';
$results = null;

try {
    $db = Database::getInstance()->getConnection();
    $courseNames = $db->query("SELECT DISTINCT course_name FROM classes ORDER BY course_name")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log("Error fetching course names: " . $e->getMessage());
    $courseNames = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Token keamanan tidak valid. Silahkan coba lagi.';
    } elseif (empty($_FILES['excel_file']['name'])) {
        $error = 'File Excel (.xlsx) wajib diupload.';
    } else {
        $extension = strtolower(pathinfo($_FILES['excel_file']['name'], PATHINFO_EXTENSION));
        $selectedCourse = trim($_POST['course_name'] ?? '');

        if ($extension !== 'xlsx') {
            $error = 'File harus berformat .xlsx (Excel 2007 ke atas). File .xls lama tidak didukung.';
        } else {
            try {
                $tmpDir = __DIR__ . '/../uploads/imports';
                if (!is_dir($tmpDir)) {
                    mkdir($tmpDir, 0777, true);
                }
                $tmpPath = $tmpDir . '/' . uniqid() . '_' . basename($_FILES['excel_file']['name']);
                move_uploaded_file($_FILES['excel_file']['tmp_name'], $tmpPath);

                $sheets = readXlsxAllSheets($tmpPath);
                @unlink($tmpPath);

                $success = [];
                $failed = [];

                foreach ($sheets as $sheetName => $rows) {
                    $className = trim($sheetName);

                    // Cari kelas yang namanya cocok dengan nama sheet
                    if ($selectedCourse !== '') {
                        $stmt = $db->prepare("SELECT id FROM classes WHERE LOWER(name) = LOWER(?) AND course_name = ?");
                        $stmt->execute([$className, $selectedCourse]);
                    } else {
                        $stmt = $db->prepare("SELECT id FROM classes WHERE LOWER(name) = LOWER(?)");
                        $stmt->execute([$className]);
                    }
                    $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    if (empty($matches)) {
                        $failed[] = "Sheet '{$sheetName}': kelas '{$className}' belum ada di sistem. Buat kelasnya dulu, atau cek penulisan nama sheet.";
                        continue;
                    }
                    if (count($matches) > 1) {
                        $failed[] = "Sheet '{$sheetName}': ada lebih dari 1 kelas bernama '{$className}' (mata kuliah beda). Pilih Mata Kuliah spesifik di form import supaya tidak ambigu.";
                        continue;
                    }
                    $classId = $matches[0]['id'];

                    // Baris pertama = header, dilewati
                    for ($i = 1; $i < count($rows); $i++) {
                        $row = $rows[$i];
                        // Kolom: 0 = No., 1 = NIM, 2 = Nama Mahasiswa
                        $nim = trim($row[1] ?? '');
                        $name = trim($row[2] ?? '');

                        if (empty($nim) && empty($name)) {
                            continue;
                        }
                        if (empty($nim) || empty($name)) {
                            $failed[] = "Sheet '{$sheetName}' baris " . ($i + 1) . ": NIM atau Nama kosong.";
                            continue;
                        }

                        $rowLabel = "Sheet '{$sheetName}' - {$name} ({$nim})";

                        try {
                            $stmt = $db->prepare("SELECT id FROM students WHERE nim = ?");
                            $stmt->execute([$nim]);
                            $existingStudent = $stmt->fetch(PDO::FETCH_ASSOC);

                            if ($existingStudent) {
                                $studentId = $existingStudent['id'];
                                $stmt = $db->prepare("SELECT id FROM class_students WHERE student_id = ? AND class_id = ?");
                                $stmt->execute([$studentId, $classId]);
                                if ($stmt->fetch()) {
                                    $failed[] = "{$rowLabel}: sudah terdaftar di kelas {$className}.";
                                    continue;
                                }
                                $stmt = $db->prepare("INSERT INTO class_students (student_id, class_id) VALUES (?, ?)");
                                $stmt->execute([$studentId, $classId]);
                                $success[] = "{$rowLabel}: mahasiswa sudah ada, ditambahkan ke kelas {$className} juga.";
                            } else {
                                $stmt = $db->prepare("INSERT INTO students (name, nim, class_id) VALUES (?, ?, ?) RETURNING id");
                                $stmt->execute([sanitizeInput($name), sanitizeInput($nim), $classId]);
                                $newId = $stmt->fetchColumn();

                                $stmt = $db->prepare("INSERT INTO class_students (student_id, class_id) VALUES (?, ?)");
                                $stmt->execute([$newId, $classId]);

                                $success[] = "{$rowLabel}: berhasil ditambahkan ke kelas {$className}.";
                            }
                        } catch (PDOException $e) {
                            $failed[] = "{$rowLabel}: Gagal simpan ke database ({$e->getMessage()}).";
                        }
                    }
                }

                $results = ['success' => $success, 'failed' => $failed];

                if (!empty($success)) {
                    logUserActivity('Import mahasiswa dari Excel', ['jumlah_berhasil' => count($success), 'jumlah_sheet' => count($sheets)]);
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

                    <div class="alert alert-info alert-permanent">
                        <i class="fas fa-info-circle"></i> <strong>Format file Excel (.xlsx):</strong>
                        <p class="mb-1 mt-2">Satu file bisa berisi <strong>beberapa sheet</strong> — nama tiap sheet harus <strong>persis sama</strong> dengan nama kelas yang sudah ada di sistem (contoh: <code>TK-48-05</code>).</p>
                        <p class="mb-1">Tiap sheet punya 3 kolom: <strong>No.</strong> | <strong>NIM</strong> | <strong>Nama Mahasiswa</strong>. Baris pertama (header) dilewati otomatis.</p>
                        <p class="mb-0">Kalau ada beberapa kelas dengan nama sama tapi mata kuliah beda, pilih <strong>Mata Kuliah</strong> di bawah supaya tidak ambigu.</p>
                    </div>

                    <form method="POST" action="" enctype="multipart/form-data" data-validate>
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                        <div class="form-group">
                            <label for="course_name">Mata Kuliah <span class="text-muted">(opsional, isi kalau ada nama kelas yang sama untuk mata kuliah berbeda)</span></label>
                            <select name="course_name" id="course_name" class="form-control">
                                <option value="">-- Tidak dipilih (otomatis kalau nama kelas unik) --</option>
                                <?php foreach ($courseNames as $course): ?>
                                    <option value="<?php echo htmlspecialchars($course); ?>"><?php echo htmlspecialchars($course); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

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
