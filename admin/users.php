<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$db  = getDB();
$msg = $msgType = '';

// Ubah status penghuni
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $uid = (int)$_GET['toggle'];
    $cur = $db->prepare("SELECT status FROM users WHERE id = ? AND role = 'penghuni'");
    $cur->execute([$uid]);
    $row = $cur->fetch();
    if ($row) {
        $newStatus = $row['status'] === 'aktif' ? 'nonaktif' : 'aktif';
        $db->prepare("UPDATE users SET status = ? WHERE id = ?")->execute([$newStatus, $uid]);
        $msg = "Status penghuni berhasil diubah ke $newStatus.";
        $msgType = 'success';
    }
    header('Location: users.php?msg=' . urlencode($msg));
    exit;
}

if (isset($_GET['msg'])) { $msg = $_GET['msg']; $msgType = 'success'; }

// Daftar semua penghuni
$search = trim($_GET['q'] ?? '');
if ($search) {
    $stmt = $db->prepare(
        "SELECT * FROM users WHERE role='penghuni' AND (nama LIKE ? OR email LIKE ? OR nomor_kamar LIKE ?)
         ORDER BY nomor_kamar, nama"
    );
    $like = "%$search%";
    $stmt->execute([$like, $like, $like]);
} else {
    $stmt = $db->query("SELECT * FROM users WHERE role='penghuni' ORDER BY nomor_kamar, nama");
}
$penghuniList = $stmt->fetchAll();

$pageTitle    = 'Data Penghuni';
$pageSubtitle = 'Kelola akun penghuni kontrakan';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header fade-in">
    <div>
        <h1>Data Penghuni</h1>
        <p>Manajemen akun dan status penghuni kontrakan</p>
    </div>
</div>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType ?> fade-in" data-dismiss="4000">
    <i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<!-- Search -->
<div class="card fade-in mb-20">
    <form method="GET" class="d-flex gap-12">
        <div class="input-group" style="flex:1">
            <i class="fa-solid fa-search input-icon"></i>
            <input type="text" name="q" class="form-control" placeholder="Cari nama, email, atau nomor kamar..." value="<?= htmlspecialchars($search) ?>">
        </div>
        <button type="submit" class="btn btn-primary">Cari</button>
        <?php if ($search): ?>
        <a href="users.php" class="btn btn-secondary">Reset</a>
        <?php endif; ?>
    </form>
</div>

<!-- Stats -->
<?php
$aktifCount    = count(array_filter($penghuniList, fn($u) => $u['status'] === 'aktif'));
$nonaktifCount = count($penghuniList) - $aktifCount;
?>
<div class="stats-grid fade-in" style="grid-template-columns:repeat(auto-fit,minmax(180px,1fr));margin-bottom:20px">
    <div class="stat-card purple">
        <div class="stat-icon"><i class="fa-solid fa-users"></i></div>
        <div class="stat-label">Total Penghuni</div>
        <div class="stat-value"><?= count($penghuniList) ?></div>
    </div>
    <div class="stat-card green">
        <div class="stat-icon"><i class="fa-solid fa-user-check"></i></div>
        <div class="stat-label">Aktif</div>
        <div class="stat-value"><?= $aktifCount ?></div>
    </div>
    <div class="stat-card red">
        <div class="stat-icon"><i class="fa-solid fa-user-xmark"></i></div>
        <div class="stat-label">Nonaktif</div>
        <div class="stat-value"><?= $nonaktifCount ?></div>
    </div>
</div>

<!-- Tabel Penghuni -->
<div class="card fade-in">
    <div class="card-header">
        <h3>Daftar Penghuni <?= $search ? '— hasil pencarian "' . htmlspecialchars($search) . '"' : '' ?></h3>
    </div>
    <?php if (empty($penghuniList)): ?>
    <div class="empty-state">
        <i class="fa-solid fa-users"></i>
        <p>Belum ada penghuni terdaftar</p>
    </div>
    <?php else: ?>
    <div class="table-wrapper">
        <table class="table">
            <thead>
                <tr>
                    <th>Penghuni</th>
                    <th>Email</th>
                    <th>Kamar</th>
                    <th>Bergabung</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($penghuniList as $u): ?>
                <tr>
                    <td>
                        <div class="d-flex align-center gap-12">
                            <div class="user-avatar" style="width:36px;height:36px;font-size:14px;flex-shrink:0"><?= strtoupper(substr($u['nama'], 0, 1)) ?></div>
                            <div class="fw-600"><?= htmlspecialchars($u['nama']) ?></div>
                        </div>
                    </td>
                    <td class="text-muted"><?= htmlspecialchars($u['email']) ?></td>
                    <td>
                        <span class="chip">Kamar <?= htmlspecialchars($u['nomor_kamar'] ?? '—') ?></span>
                    </td>
                    <td class="text-muted"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                    <td>
                        <?php if ($u['status'] === 'aktif'): ?>
                        <span class="badge badge-success">Aktif</span>
                        <?php else: ?>
                        <span class="badge badge-danger">Nonaktif</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($u['status'] === 'aktif'): ?>
                        <a href="?toggle=<?= $u['id'] ?>"
                           class="btn btn-sm btn-danger"
                           data-confirm="Nonaktifkan penghuni <?= htmlspecialchars($u['nama']) ?>?">
                            <i class="fa-solid fa-user-xmark"></i> Nonaktifkan
                        </a>
                        <?php else: ?>
                        <a href="?toggle=<?= $u['id'] ?>" class="btn btn-sm btn-success">
                            <i class="fa-solid fa-user-check"></i> Aktifkan
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
