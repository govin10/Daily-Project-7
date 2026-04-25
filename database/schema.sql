-- =============================================
-- Sistem Keuangan Kontrakan - Database Schema
-- =============================================

CREATE DATABASE IF NOT EXISTS `db_kontrakan`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `db_kontrakan`;

-- -------------------------------------------
-- Table: users
-- -------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id`           INT AUTO_INCREMENT PRIMARY KEY,
    `nama`         VARCHAR(100)                        NOT NULL,
    `email`        VARCHAR(100)                        NOT NULL UNIQUE,
    `password`     VARCHAR(255)                        NOT NULL,
    `role`         ENUM('admin','penghuni')             NOT NULL DEFAULT 'penghuni',
    `nomor_kamar`  VARCHAR(10)                         NULL,
    `status`       ENUM('aktif','nonaktif')             NOT NULL DEFAULT 'aktif',
    `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------
-- Table: biaya_bulanan
-- -------------------------------------------
CREATE TABLE IF NOT EXISTS `biaya_bulanan` (
    `id`               INT AUTO_INCREMENT PRIMARY KEY,
    `bulan`            TINYINT      NOT NULL COMMENT '1-12',
    `tahun`            YEAR         NOT NULL,
    `total_sewa`       DECIMAL(12,2) NOT NULL DEFAULT 0,
    `total_listrik`    DECIMAL(12,2) NOT NULL DEFAULT 0,
    `total_air`        DECIMAL(12,2) NOT NULL DEFAULT 0,
    `total_wifi`       DECIMAL(12,2) NOT NULL DEFAULT 0,
    `total_lain`       DECIMAL(12,2) NOT NULL DEFAULT 0,
    `jumlah_penghuni`  INT          NOT NULL DEFAULT 0,
    `biaya_per_orang`  DECIMAL(12,2) NOT NULL DEFAULT 0,
    `created_by`       INT          NULL,
    `created_at`       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_bulan_tahun` (`bulan`, `tahun`),
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------
-- Table: pembayaran
-- -------------------------------------------
CREATE TABLE IF NOT EXISTS `pembayaran` (
    `id`            INT AUTO_INCREMENT PRIMARY KEY,
    `user_id`       INT  NOT NULL,
    `biaya_id`      INT  NOT NULL,
    `status`        ENUM('lunas','belum') NOT NULL DEFAULT 'belum',
    `tanggal_bayar` DATE NULL,
    `keterangan`    TEXT NULL,
    `updated_by`    INT  NULL,
    `updated_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_user_biaya` (`user_id`, `biaya_id`),
    FOREIGN KEY (`user_id`)  REFERENCES `users`(`id`)         ON DELETE CASCADE,
    FOREIGN KEY (`biaya_id`) REFERENCES `biaya_bulanan`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------
-- Table: pengeluaran
-- -------------------------------------------
CREATE TABLE IF NOT EXISTS `pengeluaran` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `nama_item`  VARCHAR(150)  NOT NULL,
    `jumlah`     DECIMAL(12,2) NOT NULL,
    `keterangan` TEXT          NULL,
    `tanggal`    DATE          NOT NULL,
    `input_by`   INT           NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`input_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------
-- Table: notifikasi
-- -------------------------------------------
CREATE TABLE IF NOT EXISTS `notifikasi` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `user_id`    INT  NOT NULL,
    `judul`      VARCHAR(150) NOT NULL,
    `pesan`      TEXT         NOT NULL,
    `is_read`    TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------
-- NOTE: Jalankan setup.php di browser untuk membuat
--       akun admin pertama secara otomatis.
--       URL: http://localhost/system-keuangan-kontrakan/setup.php
-- -------------------------------------------
