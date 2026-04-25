<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$db     = getDB();
$userId = currentUserId();

// Mark as read
if (isset($_GET['mark_all'])) {
    $db->prepare("UPDATE notifikasi SET is_read = 1 WHERE user_id = ?")->execute([$userId]);
    redirect('/notifikasi.php');
}
if (isset($_GET['read'])) {
    $db->prepare("UPDATE notifikasi SET is_read = 1 WHERE id = ? AND user_id = ?")->execute([(int)$_GET['read'], $userId]);
    redirect('/notifikasi.php');
}

// Daftar notifikasi
$stmt = $db->prepare(
    "SELECT * FROM notifikasi WHERE user_id = ? ORDER BY created_at DESC"
);
$stmt->execute([$userId]);
$notifList = $stmt->fetchAll();

$unread = array_filter($notifList, fn($n) => !$n['is_read']);

$pageTitle    = 'Notifikasi';
$pageSubtitle = count($unread) . ' pesan belum dibaca';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header fade-in">
    <div>
        <h1>Notifikasi</h1>
        <p>Pengingat dan informasi dari admin kontrakan</p>
    </div>
    <?php if (!empty($unread)): ?>
    <a href="?mark_all=1" class="btn btn-secondary">
        <i class="fa-solid fa-check-double"></i> Tandai Semua Dibaca
    </a>
    <?php endif; ?>
</div>

<?php if (empty($notifList)): ?>
<div class="card fade-in">
    <div class="empty-state">
        <i class="fa-regular fa-bell"></i>
        <p>Belum ada notifikasi</p>
    </div>
</div>
<?php else: ?>
<div class="card fade-in">
    <?php foreach ($notifList as $n): ?>
    <div class="notif-item <?= $n['is_read'] ? 'read' : '' ?>" style="padding:18px 0;display:flex;gap:16px;align-items:flex-start;border-bottom:1px solid var(--border)">
        <div style="padding-top:4px">
            <div class="notif-dot" style="width:12px;height:12px"></div>
        </div>
        <div style="flex:1">
            <div class="d-flex align-center justify-between" style="margin-bottom:6px">
                <span style="font-weight:<?= $n['is_read'] ? '500' : '700' ?>;font-size:14px">
                    <?= htmlspecialchars($n['judul']) ?>
                </span>
                <?php if (!$n['is_read']): ?>
                <span class="badge badge-info" style="font-size:10px">Baru</span>
                <?php endif; ?>
            </div>
            <p style="font-size:13.5px;color:var(--text-secondary);line-height:1.6;margin-bottom:8px">
                <?= nl2br(htmlspecialchars($n['pesan'])) ?>
            </p>
            <div class="d-flex align-center justify-between">
                <span class="text-muted fs-12">
                    <i class="fa-regular fa-clock"></i>
                    <?= date('d M Y, H:i', strtotime($n['created_at'])) ?>
                </span>
                <?php if (!$n['is_read']): ?>
                <a href="?read=<?= $n['id'] ?>" class="btn btn-secondary btn-sm">
                    <i class="fa-solid fa-check"></i> Tandai Dibaca
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
