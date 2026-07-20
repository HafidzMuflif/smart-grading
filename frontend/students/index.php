<?php
// students/index.php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

requireAdmin();

$page_title = 'Kelola Mahasiswa';

try {
    $db = Database::getInstance()->getConnection();

    $classes = $db->query("
        SELECT c.id, c.name, c.course_name
        FROM classes c
        ORDER BY c.course_name, c.name
    ")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($classes as &$class) {
        $stmt = $db->prepare("
            SELECT s.id, s.name, s.nim
            FROM students s
            JOIN class_students cs ON cs.student_id = s.id
            WHERE cs.class_id = ?
            ORDER BY s.name
        ");
        $stmt->execute([$class['id']]);
        $class['students'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    unset($class);

    // Kelompokkan per mata kuliah (course_name), supaya tidak ditampilkan berulang
    $groupedByCourse = [];
    foreach ($classes as $class) {
        $groupedByCourse[$class['course_name']][] = $class;
    }

    // Mahasiswa yang belum terdaftar di kelas manapun (edge case, misal habis dihapus dari semua kelas)
    $unassigned = $db->query("
        SELECT id, name, nim FROM students
        WHERE id NOT IN (SELECT student_id FROM class_students)
        ORDER BY name
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error fetching students: " . $e->getMessage());
    $classes = [];
    $groupedByCourse = [];
    $unassigned = [];
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
                <div class="alert alert-warning alert-permanent">
                    <i class="fas fa-exclamation-triangle"></i>
                    Belum ada kelas yang dibuat. <a href="../classes/create.php">Buat kelas terlebih dahulu</a> sebelum menambah mahasiswa.
                </div>
            <?php else: ?>
                <?php foreach ($groupedByCourse as $courseName => $classesInCourse): ?>
                    <div class="card mb-3">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-book"></i> <?php echo htmlspecialchars($courseName); ?></h5>
                        </div>
                        <div class="card-body">
                            <div id="studentClassAccordion<?php echo md5($courseName); ?>">
                                <?php foreach ($classesInCourse as $class): ?>
                                    <div class="card mb-2">
                                        <div class="card-header py-2" style="cursor: pointer;" data-toggle="collapse" data-target="#studentClass<?php echo $class['id']; ?>">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span>
                                                    <i class="fas fa-chevron-down mr-2"></i>
                                                    <strong><?php echo htmlspecialchars($class['name']); ?></strong>
                                                </span>
                                                <span class="badge badge-primary"><?php echo count($class['students']); ?> mahasiswa</span>
                                            </div>
                                        </div>
                                        <div id="studentClass<?php echo $class['id']; ?>" class="collapse" data-parent="#studentClassAccordion<?php echo md5($courseName); ?>">
                                            <div class="card-body py-2">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <input type="text" class="form-control form-control-sm table-search" style="max-width: 220px;" placeholder="Cari di kelas ini...">
                                                    <a href="create.php?class_id=<?php echo $class['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-plus"></i> Tambah ke Kelas Ini
                                                    </a>
                                                </div>
                                                <?php if (empty($class['students'])): ?>
                                                    <p class="text-muted mb-0 small">Belum ada mahasiswa di kelas ini.</p>
                                                <?php else: ?>
                                                    <table class="table table-sm table-hover mb-0">
                                                        <thead>
                                                            <tr><th>NIM</th><th>Nama</th><th class="text-right">Aksi</th></tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($class['students'] as $student): ?>
                                                                <tr>
                                                                    <td><code><?php echo htmlspecialchars($student['nim']); ?></code></td>
                                                                    <td><?php echo htmlspecialchars($student['name']); ?></td>
                                                                    <td class="text-right">
                                                                        <a href="edit.php?id=<?php echo $student['id']; ?>" class="btn btn-sm btn-outline-secondary" title="Edit">
                                                                            <i class="fas fa-edit"></i>
                                                                        </a>
                                                                        <a href="delete.php?id=<?php echo $student['id']; ?>"
                                                                           class="btn btn-sm btn-outline-danger"
                                                                           title="Hapus"
                                                                           data-confirm-delete
                                                                           data-confirm-message="Yakin ingin menghapus mahasiswa '<?php echo htmlspecialchars($student['name']); ?>'? Ini akan menghapusnya dari SEMUA kelas yang diikuti, beserta submission ujiannya.">
                                                                            <i class="fas fa-trash"></i>
                                                                        </a>
                                                                    </td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php if (!empty($unassigned)): ?>
                    <div class="card mb-3 border-warning">
                        <div class="card-header py-2 bg-light" style="cursor: pointer;" data-toggle="collapse" data-target="#studentClassUnassigned">
                            <div class="d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-exclamation-triangle text-warning mr-2"></i><strong>Belum Punya Kelas</strong></span>
                                <span class="badge badge-warning"><?php echo count($unassigned); ?> mahasiswa</span>
                            </div>
                        </div>
                        <div id="studentClassUnassigned" class="collapse">
                            <div class="card-body py-2">
                                <table class="table table-sm table-hover mb-0">
                                    <thead><tr><th>NIM</th><th>Nama</th><th class="text-right">Aksi</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($unassigned as $student): ?>
                                            <tr>
                                                <td><code><?php echo htmlspecialchars($student['nim']); ?></code></td>
                                                <td><?php echo htmlspecialchars($student['name']); ?></td>
                                                <td class="text-right">
                                                    <a href="edit.php?id=<?php echo $student['id']; ?>" class="btn btn-sm btn-outline-secondary" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
