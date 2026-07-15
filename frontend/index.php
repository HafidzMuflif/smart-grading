<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Grading - Sistem Koreksi Ujian Otomatis</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white text-center">
                        <h3>Smart Grading</h3>
                        <p>Sistem Koreksi Ujian Otomatis</p>
                    </div>
                    <div class="card-body">
                        <div class="text-center mb-4">
                            <h5>Selamat Datang</h5>
                            <p class="text-muted">Silahkan login untuk melanjutkan</p>
                        </div>
                        <a href="login.php" class="btn btn-primary btn-lg btn-block">Login</a>
                        <div class="mt-3 text-center">
                            <small class="text-muted">Belum punya akun? Hubungi Administrator</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>