<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$db     = getDB();
$userId = currentUserId();

// Filter
$filterBulan = (int)($_GET['bulan'] ?? date('n'));
$filterTahun = (int)($_GET['tahun'] ?? date('Y'));

// Biaya bulanan untuk periode filter
$stmtAll = $db->prepare(
    "SELECT * FROM biaya_bulanan WHERE tahun = ? ORDER BY bulan DESC"
);
$stmtAll->execute([$filterTahun]);
$biayaList = $stmtAll->fetchAll();

// Biaya bulan aktif (filter)
$stmtAktif = $db->prepare(
    "SELECT * FROM biaya_bulanan WHERE bulan = ? AND tahun = ? LIMIT 1"
);
$stmtAktif->execute([$filterBulan, $filterTahun]);
$biayaAktif = $stmtAktif->fetch();

$pageTitle    = 'Biaya Bulanan';
$pageSubtitle = 'Rincian tagihan kontrakan';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header fade-in">
    <div>
        <h1>Biaya Bulanan</h1>
        <p>Rincian pembagian biaya kontrakan per bulan</p>
    </div>
</div>

<!-- Filter -->
<div class="card fade-in mb-20">
    <form method="GET" class="d-flex align-center gap-12 flex-wrap">
        <div class="form-group" style="margin:0">
            <label>Bulan</label>
            <select name="bulan" class="form-control" onchange="this.form.submit()">
                <?php for ($m = 1; $m <= 12; $m++): ?>
                <option value="<?= $m ?>" <?= $m === $filterBulan ? 'selected' : '' ?>><?= namaBulan($m) ?></option>
                <?php endfor; ?>
            </select>
        </div>
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

<?php if ($biayaAktif): ?>
<?php
    $total = $biayaAktif['total_sewa'] + $biayaAktif['total_listrik']
           + $biayaAktif['total_air']  + $biayaAktif['total_wifi'] + $biayaAktif['total_lain'];
?>
<!-- Rincian Biaya Aktif -->
<div class="grid-2 fade-in mb-20">
    <div class="card">
        <div class="card-header">
            <h3><i class="fa-solid fa-file-invoice-dollar" style="color:var(--accent-primary)"></i>
                Rincian Biaya <?= namaBulan($filterBulan) . ' ' . $filterTahun ?>
            </h3>
        </div>
        <?php
        $items = [
            ['label' => '🏠 Sewa Kontrakan', 'value' => $biayaAktif['total_sewa']],
            ['label' => '⚡ Listrik',          'value' => $biayaAktif['total_listrik']],
            ['label' => '💧 Air',              'value' => $biayaAktif['total_air']],
            ['label' => '📶 WiFi/Internet',    'value' => $biayaAktif['total_wifi']],
            ['label' => '📦 Lainnya',          'value' => $biayaAktif['total_lain']],
        ];
        foreach ($items as $it): if ($it['value'] <= 0) continue; ?>
        <div class="d-flex justify-between align-center" style="padding:12px 0;border-bottom:1px solid var(--border)">
            <span style="font-size:13.5px"><?= $it['label'] ?></span>
            <span class="fw-600"><?= formatRupiah($it['value']) ?></span>
        </div>
        <?php endforeach; ?>
        <div class="d-flex justify-between align-center" style="padding:14px 0">
            <span class="fw-700">Total Semua Biaya</span>
            <span class="fw-700 fs-24 text-accent"><?= formatRupiah($total) ?></span>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3><i class="fa-solid fa-users" style="color:var(--success)"></i> Pembagian per Penghuni</h3>
        </div>
        <div style="text-align:center;padding:20px 0">
            <div style="font-size:48px;font-weight:800;background:var(--accent-gradient);-webkit-background-clip:text;-webkit-text-fill-color:transparent;line-height:1.1">
                <?= formatRupiah($biayaAktif['biaya_per_orang']) ?>
            </div>
            <p style="color:var(--text-secondary);margin-top:8px;font-size:13px">
                per orang / bulan
            </p>
            <div style="margin-top:20px;padding:16px;background:var(--bg-card);border-radius:var(--radius-sm)">
                <div style="font-size:13px;color:var(--text-secondary)">Dari total</div>
                <div class="fw-600"><?= formatRupiah($total) ?></div>
                <div style="font-size:13px;color:var(--text-secondary);margin-top:4px">dibagi <?= $biayaAktif['jumlah_penghuni'] ?> penghuni aktif</div>
            </div>
        </div>
    </div>
</div>
<?php else: ?>
<div class="card fade-in mb-20">
    <div class="empty-state">
        <i class="fa-solid fa-file-invoice-dollar"></i>
        <p>Belum ada data biaya untuk <?= namaBulan($filterBulan) . ' ' . $filterTahun ?></p>
        <?php if (isAdmin()): ?>
        <a href="<?= BASE_URL ?>/admin/biaya.php" class="btn btn-primary" style="margin-top:16px">Input Biaya</a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Riwayat Biaya -->
<?php if (!empty($biayaList)): ?>
<div class="card fade-in">
    <div class="card-header">
        <h3>Riwayat Biaya <?= $filterTahun ?></h3>
    </div>
    <div class="table-wrapper">
        <table class="table">
            <thead>
                <tr>
                    <th>Periode</th>
                    <th>Sewa</th>
                    <th>Listrik</th>
                    <th>Air</th>
                    <th>WiFi</th>
                    <th>Jml Penghuni</th>
                    <th class="text-right">Per Orang</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($biayaList as $b): ?>
                <tr>
                    <td class="fw-600"><?= namaBulan($b['bulan']) . ' ' . $b['tahun'] ?></td>
                    <td><?= formatRupiah($b['total_sewa']) ?></td>
                    <td><?= formatRupiah($b['total_listrik']) ?></td>
                    <td><?= formatRupiah($b['total_air']) ?></td>
                    <td><?= formatRupiah($b['total_wifi']) ?></td>
                    <td><?= $b['jumlah_penghuni'] ?> orang</td>
                    <td class="text-right fw-600 text-accent"><?= formatRupiah($b['biaya_per_orang']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
