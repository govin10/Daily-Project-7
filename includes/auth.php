<?php
// =============================================
// Auth Helper Functions
// =============================================

function requireLogin(): void {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
    if ($_SESSION['status'] === 'nonaktif') {
        session_destroy();
        header('Location: ' . BASE_URL . '/login.php?err=nonaktif');
        exit;
    }
}

function requireAdmin(): void {
    requireLogin();
    if ($_SESSION['role'] !== 'admin') {
        header('Location: ' . BASE_URL . '/dashboard.php');
        exit;
    }
}

function isAdmin(): bool {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function currentUserId(): int {
    return (int)($_SESSION['user_id'] ?? 0);
}

function currentUserName(): string {
    return $_SESSION['nama'] ?? 'User';
}

function currentUserRole(): string {
    return $_SESSION['role'] ?? 'penghuni';
}

function countUnreadNotif(): int {
    if (!isLoggedIn()) return 0;
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT COUNT(*) FROM notifikasi WHERE user_id = ? AND is_read = 0");
        $stmt->execute([currentUserId()]);
        return (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}
