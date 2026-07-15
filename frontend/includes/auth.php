<?php
/**
 * Authentication Helper Functions
 * File: includes/auth.php
 * Fungsi: Mengelola autentikasi dan otorisasi pengguna
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

/**
 * Memeriksa apakah user adalah dosen
 * @return bool True jika dosen, false jika bukan
 */
function isDosen() {
    return isset($_SESSION['user_role']) && 
           $_SESSION['user_role'] === 'dosen';
}

/**
 * Memaksa user untuk login (redirect ke login jika belum)
 * Gunakan untuk halaman yang membutuhkan autentikasi
 */
function requireLogin() {
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        redirect(BASE_URL . '/login.php');
        exit();
    }
}

/**
 * Memaksa user untuk memiliki role admin
 * Gunakan untuk halaman yang hanya boleh diakses admin
 */
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        $_SESSION['error'] = "Akses ditolak. Hanya admin yang diizinkan.";
        redirect(BASE_URL . '/dashboard.php');
        exit();
    }
}

/**
 * Memaksa user untuk memiliki role dosen atau admin
 * Gunakan untuk halaman yang hanya boleh diakses dosen/admin
 */
function requireDosen() {
    requireLogin();
    if (!isDosen() && !isAdmin()) {
        $_SESSION['error'] = "Akses ditolak. Hanya dosen atau admin yang diizinkan.";
        redirect(BASE_URL . '/dashboard.php');
        exit();
    }
}

/**
 * Mendapatkan data user yang sedang login
 * @return array|null Data user atau null jika tidak login
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT id, username, email, role FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting current user: " . $e->getMessage());
        return null;
    }
}

/**
 * Memeriksa apakah user memiliki akses ke resource tertentu
 * @param int $resource_owner_id ID pemilik resource
 * @return bool True jika memiliki akses
 */
function hasAccess($resource_owner_id = null) {
    // Admin punya akses penuh
    if (isAdmin()) {
        return true;
    }
    
    // Dosen hanya bisa akses resource miliknya sendiri
    if (isDosen()) {
        if ($resource_owner_id === null) {
            return true;
        }
        return $_SESSION['user_id'] == $resource_owner_id;
    }
    
    return false;
}

/**
 * Mendapatkan role user dalam format yang lebih mudah dibaca
 * @return string Role dalam bahasa Indonesia
 */
function getUserRoleDisplay() {
    if (!isLoggedIn()) {
        return 'Guest';
    }
    
    $role = $_SESSION['user_role'];
    $roleMap = [
        'admin' => 'Administrator',
        'dosen' => 'Dosen'
    ];
    
    return $roleMap[$role] ?? ucfirst($role);
}

/**
 * Membuat session login
 * @param array $user Data user dari database
 */
function createUserSession($user) {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['login_time'] = time();
    
    // Regenerate session ID untuk keamanan
    session_regenerate_id(true);
}

/**
 * Menghapus session login (logout)
 */
function destroyUserSession() {
    $_SESSION = array();
    
    // Hapus session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    session_destroy();
}

/**
 * Memeriksa apakah session sudah expired
 * @param int $max_lifetime Maksimum waktu session dalam detik (default: 1 jam)
 * @return bool True jika expired
 */
function isSessionExpired($max_lifetime = 3600) {
    if (!isset($_SESSION['login_time'])) {
        return true;
    }
    
    return (time() - $_SESSION['login_time']) > $max_lifetime;
}

/**
 * Memeriksa apakah user sudah login dan session tidak expired
 * @param int $max_lifetime Maksimum waktu session
 * @return bool True jika valid
 */
function isValidSession($max_lifetime = 3600) {
    if (!isLoggedIn()) {
        return false;
    }
    
    if (isSessionExpired($max_lifetime)) {
        destroyUserSession();
        return false;
    }
    
    return true;
}

/**
 * Logging aktivitas user
 * @param string $activity Aktivitas yang dilakukan
 * @param array $data Data tambahan
 */
function logUserActivity($activity, $data = []) {
    if (!isLoggedIn()) {
        return;
    }
    
    $log_entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'user_id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'activity' => $activity,
        'data' => $data,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ];
    
    $log_line = json_encode($log_entry) . PHP_EOL;
    
    $log_dir = __DIR__ . '/../logs/';
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    file_put_contents($log_dir . 'user_activity.log', $log_line, FILE_APPEND);
}
?>