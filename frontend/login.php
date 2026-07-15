<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/db_connection.php';
require_once 'includes/auth.php';

// Redirect jika sudah login
if (isLoggedIn()) {
    redirect('dashboard.php');
    exit();
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = "Username dan password harus diisi";
    } else {
        try {
            $db = Database::getInstance()->getConnection();
            
            // Cari user berdasarkan username
            $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Verifikasi password
            if ($user && password_verify($password, $user['password_hash'])) {
                // Buat session
                createUserSession($user);
                
                // Log aktivitas login
                logUserActivity('Login', ['success' => true]);
                
                // Redirect ke halaman yang diminta sebelumnya atau dashboard
                $redirect = $_SESSION['redirect_after_login'] ?? 'dashboard.php';
                unset($_SESSION['redirect_after_login']);
                
                redirect($redirect);
            } else {
                $error = "Username atau password salah";
                logUserActivity('Login failed', ['username' => $username]);
            }
        } catch (PDOException $e) {
            $error = "Terjadi kesalahan sistem. Silahkan coba lagi.";
            error_log("Login error: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Smart Grading</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 420px;
        }
        .login-card .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-card .logo h2 {
            color: #4a5568;
            font-weight: 700;
        }
        .login-card .logo p {
            color: #718096;
        }
        .login-card .form-group {
            margin-bottom: 20px;
        }
        .login-card .form-group label {
            font-weight: 600;
            color: #4a5568;
        }
        .login-card .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px;
            font-weight: 600;
            font-size: 16px;
            width: 100%;
            color: white;
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        .login-card .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        .login-card .alert {
            border-radius: 10px;
        }
        .login-card .footer-text {
            text-align: center;
            margin-top: 20px;
            color: #718096;
            font-size: 14px;
        }
        .login-card .footer-text a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        .login-card .footer-text a:hover {
            text-decoration: underline;
        }
        .login-card .input-group-prepend .input-group-text {
            background: #f7fafc;
            border-right: none;
        }
        .login-card .form-control {
            border-left: none;
        }
        .login-card .form-control:focus {
            border-color: #667eea;
            box-shadow: none;
        }
    </style>
</head>
<body>
    <div class="login-card">
            <div class="logo">
                <h2>📚 Smart Grading</h2>
                <p>Sistem Koreksi Ujian Otomatis</p>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success" role="alert">
                    <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="username">Username</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                        </div>
                        <input type="text" 
                               name="username" 
                               id="username" 
                               class="form-control" 
                               placeholder="Masukkan username" 
                               required 
                               autofocus>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        </div>
                        <input type="password" 
                               name="password" 
                               id="password" 
                               class="form-control" 
                               placeholder="Masukkan password" 
                               required>
                        <div class="input-group-append">
                            <button type="button" class="btn btn-outline-secondary" onclick="togglePassword()">
                                <i class="fas fa-eye" id="toggleIcon"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-login">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </button>
                </div>
            </form>
            
            <div class="footer-text">
                <p>Belum punya akun? <a href="#">Hubungi Administrator</a></p>
                <p class="text-muted" style="font-size: 12px; margin-top: 10px;">
                    <i class="fas fa-info-circle"></i> Demo: admin / password
                </p>
            </div>
        </div>
    
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function togglePassword() {
        const password = document.getElementById('password');
        const icon = document.getElementById('toggleIcon');
        
        if (password.type === 'password') {
            password.type = 'text';
            icon.className = 'fas fa-eye-slash';
        } else {
            password.type = 'password';
            icon.className = 'fas fa-eye';
        }
    }
    </script>
</body>
</html>