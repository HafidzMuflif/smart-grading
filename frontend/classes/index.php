<?php
// classes/index.php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireAdmin();

$page_title = 'Kelola Kelas';

try {
    $db = Database::getInstance()->getConnection();
    $classes = $db->query("
        SELECT c.*,
               (SELECT COUNT(*) FROM students s WHERE s.class_id = c.id) as student_count,
               (SELECT COUNT(*) FROM exams e WHERE e.class_id = c.id) as exam_count
        FROM classes c
        ORDER BY c.created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching classes: " . $e->getMessage());
    $classes = [];
    $_SESSION['error'] = 'Gagal memuat data kelas.';
}

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0"><i class="fas fa-school"></i> Kelola Kelas</h1>
                <a href="create.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Tambah Kelas
                </a>
            </div>

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-list"></i> Daftar Kelas</h5>
                    <input type="text" class="form-control table-search" style="max-width: 250px;" placeholder="Cari kelas...">
                </div>
                <div class="card-body">
                    <?php if (empty($classes)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <p class="text-muted">Belum ada kelas yang dibuat</p>
                            <a href="create.php" class="btn btn-primary btn-sm">
                                <i class="fas fa-plus"></i> Buat Kelas Pertama
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Nama Kelas</th>
                                        <th>Mata Kuliah</th>
                                        <th>Jumlah Mahasiswa</th>
                                        <th>Jumlah Ujian</th>
                                        <th>Dibuat</th>
                                        <th class="text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($classes as $class): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($class['name']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($class['course_name']); ?></td>
                                            <td>
                                                <span class="badge badge-primary"><?php echo $class['student_count']; ?> mahasiswa</span>
                                            </td>
                                            <td>
                                                <span class="badge badge-info"><?php echo $class['exam_count']; ?> ujian</span>
                                            </td>
                                            <td><?php echo date('d/m/Y', strtotime($class['created_at'])); ?></td>
                                            <td class="text-right">
                                                <a href="edit.php?id=<?php echo $class['id']; ?>" class="btn btn-sm btn-outline-secondary" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="delete.php?id=<?php echo $class['id']; ?>"
                                                   class="btn btn-sm btn-outline-danger"
                                                   title="Hapus"
                                                   data-confirm-delete
                                                   data-confirm-message="Yakin ingin menghapus kelas '<?php echo htmlspecialchars($class['name']); ?>'? Semua data mahasiswa dan ujian terkait juga akan terhapus.">
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
