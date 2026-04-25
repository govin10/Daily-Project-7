<?php
session_start();
require_once __DIR__ . '/config/db.php';
if (isset($_SESSION['user_id'])) redirect('/dashboard.php');

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama     = trim($_POST['nama']          ?? '');
    $email    = trim($_POST['email']         ?? '');
    $password = trim($_POST['password']      ?? '');
    $konfirm  = trim($_POST['konfirm_pass']  ?? '');
    $kamar    = trim($_POST['nomor_kamar']   ?? '');

    if (empty($nama) || empty($email) || empty($password) || empty($kamar)) {
        $error = 'Semua field wajib diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid.';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter.';
    } elseif ($password !== $konfirm) {
        $error = 'Konfirmasi password tidak cocok.';
    } else {
        try {
            $db   = getDB();
            $check = $db->prepare("SELECT id FROM users WHERE email = ?");
            $check->execute([$email]);
            if ($check->fetch()) {
                $error = 'Email sudah terdaftar. Gunakan email lain.';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $db->prepare(
                    "INSERT INTO users (nama, email, password, role, nomor_kamar, status)
                     VALUES (?, ?, ?, 'penghuni', ?, 'aktif')"
                );
                $stmt->execute([$nama, $email, $hash, $kamar]);
                redirect('/login.php?registered=1');
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
    <title>Registrasi — KontrakanKu</title>
    <meta name="description" content="Daftar akun penghuni kontrakan">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="auth-wrapper">
    <div class="auth-card fade-in" style="max-width:480px">
        <div class="auth-logo">
            <div class="auth-logo-icon">🏠</div>
            <h1>KontrakanKu</h1>
            <p>Daftar sebagai penghuni baru</p>
        </div>

        <div class="auth-form">
            <h2>Buat Akun Baru</h2>
            <p class="subtitle">Isi data diri Anda untuk mendaftar</p>

            <?php if ($error): ?>
            <div class="alert alert-danger" data-dismiss="5000">
                <i class="fa-solid fa-circle-xmark"></i>
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="nama">Nama Lengkap</label>
                    <div class="input-group">
                        <i class="fa-solid fa-user input-icon"></i>
                        <input type="text" id="nama" name="nama" class="form-control"
                            placeholder="Nama lengkap Anda"
                            value="<?= htmlspecialchars($_POST['nama'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="email">Alamat Email</label>
                    <div class="input-group">
                        <i class="fa-solid fa-envelope input-icon"></i>
                        <input type="email" id="email" name="email" class="form-control"
                            placeholder="contoh@email.com"
                            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="nomor_kamar">Nomor Kamar</label>
                    <div class="input-group">
                        <i class="fa-solid fa-door-open input-icon"></i>
                        <input type="text" id="nomor_kamar" name="nomor_kamar" class="form-control"
                            placeholder="Contoh: A1, B2, 101"
                            value="<?= htmlspecialchars($_POST['nomor_kamar'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-group">
                        <i class="fa-solid fa-lock input-icon"></i>
                        <input type="password" id="password" name="password" class="form-control"
                            placeholder="Minimal 6 karakter" required autocomplete="new-password">
                        <i class="fa-regular fa-eye toggle-pw"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label for="konfirm_pass">Konfirmasi Password</label>
                    <div class="input-group">
                        <i class="fa-solid fa-lock input-icon"></i>
                        <input type="password" id="konfirm_pass" name="konfirm_pass" class="form-control"
                            placeholder="Ulangi password Anda" required autocomplete="new-password">
                        <i class="fa-regular fa-eye toggle-pw"></i>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top:8px">
                    <i class="fa-solid fa-user-plus"></i>
                    Daftar Sekarang
                </button>
            </form>
        </div>

        <div class="auth-footer">
            Sudah punya akun? <a href="<?= BASE_URL ?>/login.php">Masuk di sini</a>
        </div>
    </div>
</div>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
