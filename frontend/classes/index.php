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
               (SELECT COUNT(*) FROM class_students cs WHERE cs.class_id = c.id) as student_count,
               (SELECT COUNT(*) FROM exams e WHERE e.class_id = c.id) as exam_count
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
} catch (PDOException $e) {
    error_log("Error fetching classes: " . $e->getMessage());
    $classes = [];
    $groupedByCourse = [];
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

            <?php if (empty($classes)): ?>
                <div class="card">
                    <div class="card-body">
                        <div class="text-center py-5">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <p class="text-muted">Belum ada kelas yang dibuat</p>
                            <a href="create.php" class="btn btn-primary btn-sm">
                                <i class="fas fa-plus"></i> Buat Kelas Pertama
                            </a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($groupedByCourse as $courseName => $classesInCourse): ?>
                    <div class="card mb-3">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-book"></i> <?php echo htmlspecialchars($courseName); ?></h5>
                        </div>
                        <div class="card-body">
                            <div id="classAccordion<?php echo md5($courseName); ?>">
                                <?php foreach ($classesInCourse as $idx => $class): ?>
                                    <div class="card mb-2">
                                        <div class="card-header py-2">
                                            <div class="d-flex justify-content-between align-items-center flex-wrap">
                                                <span style="cursor: pointer;" data-toggle="collapse" data-target="#classDetail<?php echo $class['id']; ?>">
                                                    <i class="fas fa-chevron-down mr-2"></i>
                                                    <strong><?php echo htmlspecialchars($class['name']); ?></strong>
                                                </span>
                                                <div>
                                                    <span class="badge badge-primary"><?php echo $class['student_count']; ?> mahasiswa</span>
                                                    <span class="badge badge-info"><?php echo $class['exam_count']; ?> ujian</span>
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
                                                </div>
                                            </div>
                                        </div>
                                        <div id="classDetail<?php echo $class['id']; ?>" class="collapse" data-parent="#classAccordion<?php echo md5($courseName); ?>">
                                            <div class="card-body py-2">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <small class="text-muted">Dibuat <?php echo date('d/m/Y', strtotime($class['created_at'])); ?></small>
                                                    <a href="../students/create.php?class_id=<?php echo $class['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-user-plus"></i> Tambah Mahasiswa
                                                    </a>
                                                </div>
                                                <?php if (empty($class['students'])): ?>
                                                    <p class="text-muted mb-0 small">Belum ada mahasiswa terdaftar di kelas ini.</p>
                                                <?php else: ?>
                                                    <table class="table table-sm mb-0">
                                                        <thead>
                                                            <tr><th>NIM</th><th>Nama</th></tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($class['students'] as $student): ?>
                                                                <tr>
                                                                    <td><code><?php echo htmlspecialchars($student['nim']); ?></code></td>
                                                                    <td><?php echo htmlspecialchars($student['name']); ?></td>
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
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
