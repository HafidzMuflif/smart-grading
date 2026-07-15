<?php
// users/index.php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireAdmin();

$page_title = 'Kelola Dosen';

try {
    $db = Database::getInstance()->getConnection();
    $dosenList = $db->query("
        SELECT u.id, u.username, u.email, u.created_at,
               COALESCE(string_agg(c.name, ', ' ORDER BY c.name), '-') as classes
        FROM users u
        LEFT JOIN teacher_classes tc ON tc.user_id = u.id
        LEFT JOIN classes c ON c.id = tc.class_id
        WHERE u.role = 'dosen'
        GROUP BY u.id, u.username, u.email, u.created_at
        ORDER BY u.username
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching dosen list: " . $e->getMessage());
    $dosenList = [];
    $_SESSION['error'] = 'Gagal memuat data dosen.';
}

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0"><i class="fas fa-chalkboard-teacher"></i> Kelola Dosen</h1>
                <a href="create.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Tambah Akun Dosen
                </a>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-list"></i> Daftar Dosen</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($dosenList)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <p class="text-muted">Belum ada akun dosen</p>
                            <a href="create.php" class="btn btn-primary btn-sm">
                                <i class="fas fa-plus"></i> Tambah Dosen Pertama
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>Kelas Diampu</th>
                                        <th>Dibuat</th>
                                        <th class="text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($dosenList as $dosen): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($dosen['username']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($dosen['email']); ?></td>
                                            <td>
                                                <?php if ($dosen['classes'] === '-'): ?>
                                                    <span class="badge badge-secondary">Belum ada kelas</span>
                                                <?php else: ?>
                                                    <?php foreach (explode(', ', $dosen['classes']) as $cname): ?>
                                                        <span class="badge badge-primary"><?php echo htmlspecialchars($cname); ?></span>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('d/m/Y', strtotime($dosen['created_at'])); ?></td>
                                            <td class="text-right">
                                                <a href="edit.php?id=<?php echo $dosen['id']; ?>" class="btn btn-sm btn-outline-secondary" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="delete.php?id=<?php echo $dosen['id']; ?>"
                                                   class="btn btn-sm btn-outline-danger"
                                                   title="Hapus"
                                                   data-confirm-delete
                                                   data-confirm-message="Yakin ingin menghapus akun dosen '<?php echo htmlspecialchars($dosen['username']); ?>'?">
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
