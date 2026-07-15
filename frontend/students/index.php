<?php
// students/index.php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireAdmin();

$page_title = 'Kelola Mahasiswa';

// Filter berdasarkan kelas (opsional, dari query string)
$filterClassId = intval($_GET['class_id'] ?? 0);

try {
    $db = Database::getInstance()->getConnection();

    // Daftar kelas untuk dropdown filter
    $classes = $db->query("SELECT id, name FROM classes ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

    if ($filterClassId > 0) {
        $stmt = $db->prepare("
            SELECT s.*, c.name as class_name
            FROM students s
            LEFT JOIN classes c ON s.class_id = c.id
            WHERE s.class_id = ?
            ORDER BY s.name ASC
        ");
        $stmt->execute([$filterClassId]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $students = $db->query("
            SELECT s.*, c.name as class_name
            FROM students s
            LEFT JOIN classes c ON s.class_id = c.id
            ORDER BY s.name ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("Error fetching students: " . $e->getMessage());
    $students = [];
    $classes = [];
    $_SESSION['error'] = 'Gagal memuat data mahasiswa.';
}

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0"><i class="fas fa-users"></i> Kelola Mahasiswa</h1>
                <div>
                    <a href="import.php" class="btn btn-outline-success">
                        <i class="fas fa-file-excel"></i> Import Excel
                    </a>
                    <a href="create.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Tambah Mahasiswa
                    </a>
                </div>
            </div>

            <?php if (empty($classes)): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    Belum ada kelas yang dibuat. <a href="../classes/create.php">Buat kelas terlebih dahulu</a> sebelum menambah mahasiswa.
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
                    <h5 class="mb-0"><i class="fas fa-list"></i> Daftar Mahasiswa</h5>
                    <div class="d-flex" style="gap: 10px;">
                        <?php if (!empty($classes)): ?>
                        <form method="GET" action="" class="mb-0">
                            <select name="class_id" class="form-control form-control-sm" onchange="this.form.submit()" style="min-width: 180px;">
                                <option value="0">Semua Kelas</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?php echo $class['id']; ?>" <?php echo $filterClassId == $class['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($class['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                        <?php endif; ?>
                        <input type="text" class="form-control form-control-sm table-search" style="max-width: 220px;" placeholder="Cari mahasiswa...">
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($students)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <p class="text-muted">
                                <?php echo $filterClassId > 0 ? 'Tidak ada mahasiswa di kelas ini' : 'Belum ada mahasiswa yang ditambahkan'; ?>
                            </p>
                            <?php if (!empty($classes)): ?>
                                <a href="create.php" class="btn btn-primary btn-sm">
                                    <i class="fas fa-plus"></i> Tambah Mahasiswa Pertama
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>NIM</th>
                                        <th>Nama</th>
                                        <th>Kelas</th>
                                        <th>Terdaftar</th>
                                        <th class="text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($students as $student): ?>
                                        <tr>
                                            <td><code><?php echo htmlspecialchars($student['nim']); ?></code></td>
                                            <td><strong><?php echo htmlspecialchars($student['name']); ?></strong></td>
                                            <td>
                                                <?php if ($student['class_name']): ?>
                                                    <span class="badge badge-primary"><?php echo htmlspecialchars($student['class_name']); ?></span>
                                                <?php else: ?>
                                                    <span class="badge badge-secondary">Belum ada kelas</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('d/m/Y', strtotime($student['created_at'])); ?></td>
                                            <td class="text-right">
                                                <a href="edit.php?id=<?php echo $student['id']; ?>" class="btn btn-sm btn-outline-secondary" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="delete.php?id=<?php echo $student['id']; ?>"
                                                   class="btn btn-sm btn-outline-danger"
                                                   title="Hapus"
                                                   data-confirm-delete
                                                   data-confirm-message="Yakin ingin menghapus mahasiswa '<?php echo htmlspecialchars($student['name']); ?>'? Semua data submission ujiannya juga akan terhapus.">
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

<?php include '../includes/footer.php'; ?>
