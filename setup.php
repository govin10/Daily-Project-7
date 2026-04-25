<?php
// =============================================
// Setup Script — Buat Database & Admin Pertama
// Akses SEKALI SAJA: http://localhost/system-keuangan-kontrakan/setup.php
// =============================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'db_kontrakan');
define('BASE_URL', '/system-keuangan-kontrakan');

$steps  = [];
$errors = [];

try {
    // 1. Connect tanpa database dulu
    $pdo = new PDO("mysql:host=" . DB_HOST . ";charset=utf8mb4", DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    // 2. Buat database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $steps[] = '✅ Database <strong>' . DB_NAME . '</strong> berhasil dibuat.';
    $pdo->exec("USE `" . DB_NAME . "`");

    // 3. Buat tabel users
    $pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
        `id`          INT AUTO_INCREMENT PRIMARY KEY,
        `nama`        VARCHAR(100) NOT NULL,
        `email`       VARCHAR(100) NOT NULL UNIQUE,
        `password`    VARCHAR(255) NOT NULL,
        `role`        ENUM('admin','penghuni') NOT NULL DEFAULT 'penghuni',
        `nomor_kamar` VARCHAR(10) NULL,
        `status`      ENUM('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
        `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $steps[] = '✅ Tabel <strong>users</strong> siap.';

    // 4. Buat tabel biaya_bulanan
    $pdo->exec("CREATE TABLE IF NOT EXISTS `biaya_bulanan` (
        `id`              INT AUTO_INCREMENT PRIMARY KEY,
        `bulan`           TINYINT NOT NULL,
        `tahun`           YEAR NOT NULL,
        `total_sewa`      DECIMAL(12,2) NOT NULL DEFAULT 0,
        `total_listrik`   DECIMAL(12,2) NOT NULL DEFAULT 0,
        `total_air`       DECIMAL(12,2) NOT NULL DEFAULT 0,
        `total_wifi`      DECIMAL(12,2) NOT NULL DEFAULT 0,
        `total_lain`      DECIMAL(12,2) NOT NULL DEFAULT 0,
        `jumlah_penghuni` INT NOT NULL DEFAULT 0,
        `biaya_per_orang` DECIMAL(12,2) NOT NULL DEFAULT 0,
        `created_by`      INT NULL,
        `created_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `uq_bulan_tahun` (`bulan`,`tahun`),
        FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $steps[] = '✅ Tabel <strong>biaya_bulanan</strong> siap.';

    // 5. Buat tabel pembayaran
    $pdo->exec("CREATE TABLE IF NOT EXISTS `pembayaran` (
        `id`            INT AUTO_INCREMENT PRIMARY KEY,
        `user_id`       INT NOT NULL,
        `biaya_id`      INT NOT NULL,
        `status`        ENUM('lunas','belum') NOT NULL DEFAULT 'belum',
        `tanggal_bayar` DATE NULL,
        `keterangan`    TEXT NULL,
        `updated_by`    INT NULL,
        `updated_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `uq_user_biaya` (`user_id`,`biaya_id`),
        FOREIGN KEY (`user_id`)  REFERENCES `users`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`biaya_id`) REFERENCES `biaya_bulanan`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $steps[] = '✅ Tabel <strong>pembayaran</strong> siap.';

    // 6. Buat tabel pengeluaran
    $pdo->exec("CREATE TABLE IF NOT EXISTS `pengeluaran` (
        `id`         INT AUTO_INCREMENT PRIMARY KEY,
        `nama_item`  VARCHAR(150) NOT NULL,
        `jumlah`     DECIMAL(12,2) NOT NULL,
        `keterangan` TEXT NULL,
        `tanggal`    DATE NOT NULL,
        `input_by`   INT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`input_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $steps[] = '✅ Tabel <strong>pengeluaran</strong> siap.';

    // 7. Buat tabel notifikasi
    $pdo->exec("CREATE TABLE IF NOT EXISTS `notifikasi` (
        `id`         INT AUTO_INCREMENT PRIMARY KEY,
        `user_id`    INT NOT NULL,
        `judul`      VARCHAR(150) NOT NULL,
        `pesan`      TEXT NOT NULL,
        `is_read`    TINYINT(1) NOT NULL DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $steps[] = '✅ Tabel <strong>notifikasi</strong> siap.';

    // 8. Insert admin jika belum ada
    $adminEmail = 'admin@kontrakan.com';
    $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $check->execute([$adminEmail]);

    if (!$check->fetch()) {
        $hash = password_hash('admin123', PASSWORD_BCRYPT);
        $ins  = $pdo->prepare("INSERT INTO users (nama, email, password, role, status) VALUES (?,?,?,'admin','aktif')");
        $ins->execute(['Admin Kontrakan', $adminEmail, $hash]);
        $steps[] = '✅ Akun admin dibuat: <strong>admin@kontrakan.com</strong> / <strong>admin123</strong>';
    } else {
        $steps[] = 'ℹ️ Akun admin sudah ada.';
    }

    $success = true;

} catch (PDOException $e) {
    $errors[] = 'Error: ' . $e->getMessage();
    $success  = false;
}

?><!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup — KontrakanKu</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background: #0b0b18; color: #f0f0ff; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .box { background: #111128; border: 1px solid rgba(255,255,255,0.08); border-radius: 16px; padding: 40px; max-width: 520px; width: 100%; margin: 24px; }
        .box h1 { font-size: 22px; margin-bottom: 8px; }
        .box p.sub { color: #9090b0; font-size: 13px; margin-bottom: 28px; }
        .step { padding: 10px 14px; background: rgba(255,255,255,0.04); border-radius: 8px; margin-bottom: 8px; font-size: 13.5px; line-height: 1.5; }
        .step strong { color: #a0a0ff; }
        .err { background: rgba(239,68,68,0.12); color: #ef4444; border-left: 3px solid #ef4444; }
        .success-box { text-align: center; margin-top: 24px; padding: 20px; background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.3); border-radius: 12px; }
        .success-box h2 { color: #10b981; font-size: 18px; margin-bottom: 8px; }
        .btn { display: inline-block; margin-top: 16px; padding: 12px 24px; background: linear-gradient(135deg,#7c6fef,#a855f7); color: #fff; border-radius: 8px; text-decoration: none; font-weight: 600; }
        .warn { margin-top: 16px; padding: 12px; background: rgba(245,158,11,0.12); border-left: 3px solid #f59e0b; border-radius: 6px; color: #f59e0b; font-size: 12.5px; }
    </style>
</head>
<body>
    <div class="box">
        <h1>🚀 Setup KontrakanKu</h1>
        <p class="sub">Inisialisasi database dan akun admin</p>

        <?php foreach ($steps as $s): ?>
            <div class="step"><?= $s ?></div>
        <?php endforeach; ?>

        <?php foreach ($errors as $e): ?>
            <div class="step err"><?= htmlspecialchars($e) ?></div>
        <?php endforeach; ?>

        <?php if ($success): ?>
        <div class="success-box">
            <h2>✅ Setup Berhasil!</h2>
            <p style="font-size:13px;color:#9090b0">Database dan semua tabel telah dibuat. Silakan login ke aplikasi.</p>
            <a href="<?= BASE_URL ?>/login.php" class="btn">➡ Pergi ke Login</a>
        </div>
        <div class="warn">
            ⚠️ <strong>Penting:</strong> Hapus atau amankan file <code>setup.php</code> setelah setup selesai agar keamanan terjaga.
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
