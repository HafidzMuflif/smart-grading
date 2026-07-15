<?php
// exams/index.php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireLogin();

$page_title = 'Kelola Ujian';

try {
    $db = Database::getInstance()->getConnection();
    $classFilter = classAccessWhereClause('e.class_id');
    $exams = $db->query("
        SELECT e.*, c.name as class_name,
               (SELECT COUNT(*) FROM submissions sub WHERE sub.exam_id = e.id) as submission_count,
               (SELECT COUNT(*) FROM submissions sub WHERE sub.exam_id = e.id AND sub.status = 'completed') as graded_count
        FROM exams e
        LEFT JOIN classes c ON e.class_id = c.id
        WHERE 1=1 {$classFilter}
        ORDER BY e.created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching exams: " . $e->getMessage());
    $exams = [];
    $_SESSION['error'] = 'Gagal memuat data ujian.';
}

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0"><i class="fas fa-file-alt"></i> Kelola Ujian</h1>
                <a href="create.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Buat Ujian Baru
                </a>
            </div>

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-list"></i> Daftar Ujian</h5>
                    <input type="text" class="form-control form-control-sm table-search" style="max-width: 220px;" placeholder="Cari ujian...">
                </div>
                <div class="card-body">
                    <?php if (empty($exams)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <p class="text-muted">Belum ada ujian yang dibuat</p>
                            <a href="create.php" class="btn btn-primary btn-sm">
                                <i class="fas fa-plus"></i> Buat Ujian Pertama
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Judul Ujian</th>
                                        <th>Kelas</th>
                                        <th>Submission</th>
                                        <th>Dinilai</th>
                                        <th>Dibuat</th>
                                        <th class="text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($exams as $exam): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($exam['title']); ?></strong></td>
                                            <td>
                                                <?php if ($exam['class_name']): ?>
                                                    <span class="badge badge-primary"><?php echo htmlspecialchars($exam['class_name']); ?></span>
                                                <?php else: ?>
                                                    <span class="badge badge-secondary">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo $exam['submission_count']; ?> submission</td>
                                            <td>
                                                <?php if ($exam['submission_count'] > 0): ?>
                                                    <span class="badge badge-<?php echo $exam['graded_count'] == $exam['submission_count'] ? 'success' : 'warning'; ?>">
                                                        <?php echo $exam['graded_count']; ?> / <?php echo $exam['submission_count']; ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('d/m/Y', strtotime($exam['created_at'])); ?></td>
                                            <td class="text-right">
                                                <a href="view.php?id=<?php echo $exam['id']; ?>" class="btn btn-sm btn-outline-info" title="Lihat Detail">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="edit.php?id=<?php echo $exam['id']; ?>" class="btn btn-sm btn-outline-secondary" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="delete.php?id=<?php echo $exam['id']; ?>"
                                                   class="btn btn-sm btn-outline-danger"
                                                   title="Hapus"
                                                   data-confirm-delete
                                                   data-confirm-message="Yakin ingin menghapus ujian '<?php echo htmlspecialchars($exam['title']); ?>'? Semua submission dan nilai terkait juga akan terhapus.">
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
