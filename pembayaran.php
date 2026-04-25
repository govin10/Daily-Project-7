<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$db     = getDB();
$userId = currentUserId();

// Filter
$filterTahun = (int)($_GET['tahun'] ?? date('Y'));

// Semua biaya bulanan
$stmtBiaya = $db->prepare(
    "SELECT * FROM biaya_bulanan WHERE tahun = ? ORDER BY bulan DESC"
);
$stmtBiaya->execute([$filterTahun]);
$biayaList = $stmtBiaya->fetchAll();

// Map biaya_id => status pembayaran user ini
$stmtBayarku = $db->prepare(
    "SELECT p.* FROM pembayaran p
     JOIN biaya_bulanan bb ON p.biaya_id = bb.id
     WHERE p.user_id = ? AND bb.tahun = ?"
);
$stmtBayarku->execute([$userId, $filterTahun]);
$bayarMap = [];
foreach ($stmtBayarku->fetchAll() as $row) {
    $bayarMap[$row['biaya_id']] = $row;
}

$pageTitle    = 'Pembayaran Saya';
$pageSubtitle = 'Status tagihan & riwayat pembayaran';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header fade-in">
    <div>
        <h1>Status Pembayaran</h1>
        <p>Riwayat tagihan dan status pembayaran Anda</p>
    </div>
</div>

<!-- Filter Tahun -->
<div class="card fade-in mb-20">
    <form method="GET" class="d-flex align-center gap-12">
        <div class="form-group" style="margin:0">
            <label>Tahun</label>
            <select name="tahun" class="form-control" onchange="this.form.submit()">
                <?php for ($y = date('Y'); $y >= date('Y') - 2; $y--): ?>
                <option value="<?= $y ?>" <?= $y === $filterTahun ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </div>
    </form>
</div>

<?php if (empty($biayaList)): ?>
<div class="card fade-in">
    <div class="empty-state">
        <i class="fa-solid fa-file-invoice"></i>
        <p>Belum ada tagihan untuk tahun <?= $filterTahun ?></p>
    </div>
</div>
<?php else: ?>

<!-- Summary -->
<?php
$totalLunas = 0; $totalBelum = 0;
foreach ($biayaList as $b) {
    $s = $bayarMap[$b['id']]['status'] ?? 'belum';
    if ($s === 'lunas') $totalLunas++;
    else $totalBelum++;
}
?>
<div class="stats-grid fade-in" style="grid-template-columns:repeat(auto-fit,minmax(180px,1fr));margin-bottom:20px">
    <div class="stat-card green">
        <div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div>
        <div class="stat-label">Sudah Lunas</div>
        <div class="stat-value"><?= $totalLunas ?> bulan</div>
    </div>
    <div class="stat-card red">
        <div class="stat-icon"><i class="fa-solid fa-circle-xmark"></i></div>
        <div class="stat-label">Belum Bayar</div>
        <div class="stat-value"><?= $totalBelum ?> bulan</div>
    </div>
</div>

<!-- Tabel Status Pembayaran -->
<div class="card fade-in">
    <div class="card-header">
        <h3>Riwayat Tagihan <?= $filterTahun ?></h3>
    </div>
    <div class="table-wrapper">
        <table class="table">
            <thead>
                <tr>
                    <th>Periode</th>
                    <th>Total Biaya</th>
                    <th>Tagihan/Orang</th>
                    <th>Status</th>
                    <th>Tanggal Bayar</th>
                    <th>Keterangan</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($biayaList as $b):
                    $bayar   = $bayarMap[$b['id']] ?? null;
                    $status  = $bayar['status'] ?? 'belum';
                    $total   = $b['total_sewa'] + $b['total_listrik'] + $b['total_air'] + $b['total_wifi'] + $b['total_lain'];
                ?>
                <tr>
                    <td>
                        <div class="fw-600"><?= namaBulan($b['bulan']) . ' ' . $b['tahun'] ?></div>
                        <div class="text-muted fs-12"><?= $b['jumlah_penghuni'] ?> penghuni</div>
                    </td>
                    <td><?= formatRupiah($total) ?></td>
                    <td class="fw-600 text-accent"><?= formatRupiah($b['biaya_per_orang']) ?></td>
                    <td>
                        <?php if ($status === 'lunas'): ?>
                        <span class="badge badge-success"><i class="fa-solid fa-check"></i> Lunas</span>
                        <?php else: ?>
                        <span class="badge badge-danger"><i class="fa-solid fa-xmark"></i> Belum Bayar</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-muted">
                        <?= ($bayar && $bayar['tanggal_bayar']) ? date('d M Y', strtotime($bayar['tanggal_bayar'])) : '—' ?>
                    </td>
                    <td class="text-muted"><?= ($bayar && $bayar['keterangan']) ? htmlspecialchars($bayar['keterangan']) : '—' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
