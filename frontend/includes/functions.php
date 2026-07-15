<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db_connection.php';

function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function uploadFile($file, $targetDir, $allowedExtensions = null) {
    if ($allowedExtensions === null) {
        $allowedExtensions = ALLOWED_EXTENSIONS;
    }
    
    // Check file size
    if ($file['size'] > UPLOAD_MAX_SIZE) {
        throw new Exception("File size exceeds maximum allowed size");
    }
    
    // Check file extension
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedExtensions)) {
        throw new Exception("File type not allowed. Allowed: " . implode(', ', $allowedExtensions));
    }
    
    // Generate unique filename
    $filename = uniqid() . '_' . basename($file['name']);
    $targetPath = $targetDir . '/' . $filename;
    
    // Create directory if not exists
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new Exception("Failed to upload file");
    }
    
    return $targetPath;
}

function callAPI($endpoint, $method = 'GET', $data = null, $files = null) {
    $url = API_BASE_URL . $endpoint;
    $ch = curl_init();
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($files) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        } else {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }
    }
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, API_TIMEOUT);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        throw new Exception('API Error: ' . curl_error($ch));
    }
    
    curl_close($ch);
    
    return json_decode($response, true);
}

function redirect($url) {
    header("Location: $url");
    exit();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_role']);
}

function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

/**
 * Ambil daftar ID kelas yang boleh diakses user saat ini.
 * Admin: semua kelas. Dosen: hanya kelas yang di-assign ke dia.
 * @return array|null null berarti "semua kelas" (khusus admin)
 */
function getAccessibleClassIds() {
    if (isAdmin()) {
        return null; // null = tidak difilter, akses semua kelas
    }

    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT class_id FROM teacher_classes WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'class_id');
    } catch (PDOException $e) {
        error_log("Error fetching accessible classes: " . $e->getMessage());
        return [];
    }
}

/**
 * Cek apakah user saat ini boleh mengakses kelas tertentu.
 */
function canAccessClass($classId) {
    if (isAdmin()) {
        return true;
    }
    $accessible = getAccessibleClassIds();
    return in_array($classId, $accessible);
}

/**
 * Bangun klausa SQL "AND class_id IN (...)" sesuai akses user.
 * Kembalikan string kosong kalau admin (tidak perlu filter).
 */
function classAccessWhereClause($columnName = 'class_id') {
    if (isAdmin()) {
        return '';
    }
    $ids = getAccessibleClassIds();
    if (empty($ids)) {
        return " AND 1=0"; // dosen belum di-assign kelas apapun -> tidak lihat apa-apa
    }
    $idsEscaped = implode(',', array_map('intval', $ids));
    return " AND {$columnName} IN ({$idsEscaped})";
}
?>