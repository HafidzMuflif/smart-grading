<?php
// dashboard.php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Cek autentikasi
requireLogin();

// Cek session expired
if (!isValidSession(3600)) {
    $_SESSION['error'] = 'Session telah berakhir. Silahkan login kembali.';
    redirect('login.php');
    exit();
}

include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <h1 class="h3 mb-4">Dashboard</h1>
            
            <!-- Welcome Message -->
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <i class="fas fa-user-circle"></i> Selamat datang, <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>!
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            
            <!-- Statistics Cards -->
            <?php
            $db = Database::getInstance()->getConnection();
            $classFilter = classAccessWhereClause('class_id');

            // Get statistics (terfilter sesuai kelas yang diampu, kecuali admin)
            $studentCount = $db->query("SELECT COUNT(*) as count FROM students WHERE 1=1 {$classFilter}")->fetch(PDO::FETCH_ASSOC)['count'];
            $classCountQuery = isAdmin()
                ? "SELECT COUNT(*) as count FROM classes"
                : "SELECT COUNT(*) as count FROM classes WHERE 1=1 " . classAccessWhereClause('id');
            $classCount = $db->query($classCountQuery)->fetch(PDO::FETCH_ASSOC)['count'];
            $examCount = $db->query("SELECT COUNT(*) as count FROM exams e WHERE 1=1 " . classAccessWhereClause('e.class_id'))->fetch(PDO::FETCH_ASSOC)['count'];

            // Recent exams
            $recentExams = $db->query("
                SELECT e.*, c.name as class_name
                FROM exams e
                LEFT JOIN classes c ON e.class_id = c.id
                WHERE 1=1 " . classAccessWhereClause('e.class_id') . "
                ORDER BY e.created_at DESC
                LIMIT 5
            ")->fetchAll(PDO::FETCH_ASSOC);

            // Kelas & mahasiswa yang diampu (khusus dosen, admin tidak perlu ini di dashboard)
            $myClasses = [];
            if (!isAdmin()) {
                $stmt = $db->prepare("
                    SELECT c.id, c.name, c.course_name
                    FROM classes c
                    JOIN teacher_classes tc ON tc.class_id = c.id
                    WHERE tc.user_id = ?
                    ORDER BY c.name
                ");
                $stmt->execute([$_SESSION['user_id']]);
                $myClasses = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($myClasses as &$class) {
                    $stmt = $db->prepare("SELECT name, nim FROM students WHERE class_id = ? ORDER BY name");
                    $stmt->execute([$class['id']]);
                    $class['students'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                }
                unset($class);
            }
            ?>
            
            <div class="row">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-users"></i> Total Mahasiswa</h5>
                            <p class="card-text display-4"><?php echo $studentCount; ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-school"></i> Total Kelas</h5>
                            <p class="card-text display-4"><?php echo $classCount; ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-file-alt"></i> Total Ujian</h5>
                            <p class="card-text display-4"><?php echo $examCount; ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-user-tag"></i> Role</h5>
                            <p class="card-text display-6"><?php echo getUserRoleDisplay(); ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row mt-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-bolt"></i> Aksi Cepat</h5>
                        </div>
                        <div class="card-body">
                            <div class="list-group">
                                <?php if (isAdmin()): ?>
                                <a href="students/index.php" class="list-group-item list-group-item-action">
                                    <i class="fas fa-users"></i> Kelola Mahasiswa
                                    <span class="badge badge-primary float-right"><?php echo $studentCount; ?></span>
                                </a>
                                <a href="classes/index.php" class="list-group-item list-group-item-action">
                                    <i class="fas fa-school"></i> Kelola Kelas
                                    <span class="badge badge-success float-right"><?php echo $classCount; ?></span>
                                </a>
                                <a href="users/index.php" class="list-group-item list-group-item-action">
                                    <i class="fas fa-chalkboard-teacher"></i> Kelola Dosen
                                </a>
                                <?php endif; ?>
                                <a href="exams/index.php" class="list-group-item list-group-item-action">
                                    <i class="fas fa-file-alt"></i> Kelola Ujian
                                    <span class="badge badge-info float-right"><?php echo $examCount; ?></span>
                                </a>
                                <a href="exams/create.php" class="list-group-item list-group-item-action list-group-item-primary">
                                    <i class="fas fa-plus-circle"></i> Buat Ujian Baru
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-clock"></i> Ujian Terakhir</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recentExams)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">Belum ada ujian yang dikelola</p>
                                    <a href="exams/create.php" class="btn btn-primary btn-sm">
                                        <i class="fas fa-plus"></i> Buat Ujian Pertama
                                    </a>
                                </div>
                            <?php else: ?>
                                <ul class="list-group">
                                    <?php foreach ($recentExams as $exam): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong><?php echo htmlspecialchars($exam['title']); ?></strong>
                                                <br>
                                                <small class="text-muted">
                                                    <i class="fas fa-school"></i> <?php echo htmlspecialchars($exam['class_name'] ?? 'N/A'); ?>
                                                    <i class="fas fa-calendar ml-2"></i> <?php echo date('d/m/Y H:i', strtotime($exam['created_at'])); ?>
                                                </small>
                                            </div>
                                            <a href="exams/view.php?id=<?php echo $exam['id']; ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if (isAdmin()): ?>
            <!-- Recent Activity (Admin Only) -->
            <div class="row mt-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-history"></i> Aktivitas Terakhir</h5>
                        </div>
                        <div class="card-body">
                            <?php
                            // Get recent activity from log file
                            $log_file = __DIR__ . '/logs/user_activity.log';
                            if (file_exists($log_file)) {
                                $lines = file($log_file);
                                $lines = array_reverse($lines);
                                $recent = array_slice($lines, 0, 10);
                                
                                if (!empty($recent)) {
                                    echo '<ul class="list-group">';
                                    foreach ($recent as $line) {
                                        $data = json_decode($line, true);
                                        if ($data) {
                                            $icon = $data['activity'] === 'Login' ? 'fa-sign-in-alt' : 'fa-user';
                                            echo '<li class="list-group-item d-flex justify-content-between align-items-center">';
                                            echo '<div>';
                                            echo '<i class="fas ' . $icon . ' text-info"></i> ';
                                            echo '<strong>' . htmlspecialchars($data['username']) . '</strong> ';
                                            echo htmlspecialchars($data['activity']);
                                            echo '</div>';
                                            echo '<small class="text-muted">' . $data['timestamp'] . '</small>';
                                            echo '</li>';
                                        }
                                    }
                                    echo '</ul>';
                                } else {
                                    echo '<p class="text-muted text-center py-3">Belum ada aktivitas</p>';
                                }
                            } else {
                                echo '<p class="text-muted text-center py-3">Log aktivitas belum tersedia</p>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <!-- Kelas Saya & Mahasiswa (Dosen) -->
            <div class="row mt-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-school"></i> Kelas Saya & Mahasiswa</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($myClasses)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-info-circle fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">Anda belum di-assign ke kelas manapun. Hubungi admin untuk mengatur akses kelas Anda.</p>
                                </div>
                            <?php else: ?>
                                <div id="myClassesAccordion">
                                    <?php foreach ($myClasses as $idx => $class): ?>
                                        <div class="card mb-2">
                                            <div class="card-header py-2" style="cursor: pointer;" data-toggle="collapse" data-target="#classCollapse<?php echo $class['id']; ?>">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span>
                                                        <i class="fas fa-chevron-down mr-2"></i>
                                                        <strong><?php echo htmlspecialchars($class['name']); ?></strong>
                                                        — <?php echo htmlspecialchars($class['course_name']); ?>
                                                    </span>
                                                    <span class="badge badge-primary"><?php echo count($class['students']); ?> mahasiswa</span>
                                                </div>
                                            </div>
                                            <div id="classCollapse<?php echo $class['id']; ?>" class="collapse <?php echo $idx === 0 ? 'show' : ''; ?>" data-parent="#myClassesAccordion">
                                                <div class="card-body py-2">
                                                    <?php if (empty($class['students'])): ?>
                                                        <p class="text-muted mb-0 small">Belum ada mahasiswa di kelas ini.</p>
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
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>