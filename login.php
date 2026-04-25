<?php
session_start();
require_once __DIR__ . '/config/db.php';
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') redirect('/admin/dashboard.php');
    else redirect('/dashboard.php');
}

$error   = '';
$success = '';

if (isset($_GET['err'])) {
    $error = match($_GET['err']) {
        'nonaktif' => 'Akun Anda telah dinonaktifkan. Hubungi admin.',
        default    => 'Terjadi kesalahan, silakan coba lagi.'
    };
}
if (isset($_GET['registered'])) $success = 'Registrasi berhasil! Silakan login.';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        $error = 'Email dan password wajib diisi.';
    } else {
        try {
            $db   = getDB();
            $stmt = $db->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                if ($user['status'] === 'nonaktif') {
                    $error = 'Akun Anda telah dinonaktifkan. Hubungi admin.';
                } else {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['nama']    = $user['nama'];
                    $_SESSION['email']   = $user['email'];
                    $_SESSION['role']    = $user['role'];
                    $_SESSION['status']  = $user['status'];
                    $_SESSION['kamar']   = $user['nomor_kamar'];

                    if ($user['role'] === 'admin') redirect('/admin/dashboard.php');
                    else redirect('/dashboard.php');
                }
            } else {
                $error = 'Email atau password salah.';
            }
        } catch (Exception $e) {
            $error = 'Terjadi kesalahan server.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — KontrakanKu</title>
    <meta name="description" content="Login ke Sistem Keuangan Kontrakan">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="auth-wrapper">
    <div class="auth-card fade-in">
        <div class="auth-logo">
            <div class="auth-logo-icon">🏠</div>
            <h1>KontrakanKu</h1>
            <p>Sistem Keuangan Kontrakan</p>
        </div>

        <div class="auth-form">
            <h2>Selamat Datang!</h2>
            <p class="subtitle">Masuk untuk mengelola keuangan kontrakan</p>

            <?php if ($error): ?>
            <div class="alert alert-danger" data-dismiss="5000">
                <i class="fa-solid fa-circle-xmark"></i>
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <?php if ($success): ?>
            <div class="alert alert-success" data-dismiss="5000">
                <i class="fa-solid fa-circle-check"></i>
                <?= htmlspecialchars($success) ?>
            </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="email">Alamat Email</label>
                    <div class="input-group">
                        <i class="fa-solid fa-envelope input-icon"></i>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            class="form-control"
                            placeholder="contoh@email.com"
                            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                            required
                            autocomplete="email"
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-group">
                        <i class="fa-solid fa-lock input-icon"></i>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="form-control"
                            placeholder="Masukkan password"
                            required
                            autocomplete="current-password"
                        >
                        <i class="fa-regular fa-eye toggle-pw"></i>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top:8px">
                    <i class="fa-solid fa-right-to-bracket"></i>
                    Masuk
                </button>
            </form>
        </div>

        <div class="auth-footer">
            Belum punya akun? <a href="<?= BASE_URL ?>/register.php">Daftar sebagai Penghuni</a>
        </div>
    </div>
</div>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
