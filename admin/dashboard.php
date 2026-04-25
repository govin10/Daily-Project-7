<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$db    = getDB();
$bulan = (int)date('n');
$tahun = (int)date('Y');

// --- Jumlah penghuni aktif ---
$stmtPenghuni = $db->query(
    "SELECT COUNT(*) FROM users WHERE role = 'penghuni' AND status = 'aktif'"
);
$totalPenghuni = (int)$stmtPenghuni->fetchColumn();

// --- Biaya bulan ini ---
$stmtBiaya = $db->prepare("SELECT * FROM biaya_bulanan WHERE bulan = ? AND tahun = ? LIMIT 1");
$stmtBiaya->execute([$bulan, $tahun]);
$biayaBulanIni = $stmtBiaya->fetch();

// --- Total iuran terkumpul bulan ini ---
$stmtLunas = $db->prepare(
    "SELECT COALESCE(SUM(bb.biaya_per_orang),0)
     FROM pembayaran p JOIN biaya_bulanan bb ON p.biaya_id = bb.id
     WHERE bb.bulan = ? AND bb.tahun = ? AND p.status = 'lunas'"
);
$stmtLunas->execute([$bulan, $tahun]);
$totalIuran = (float)$stmtLunas->fetchColumn();

// --- Total pengeluaran bulan ini ---
$stmtPng = $db->prepare(
    "SELECT COALESCE(SUM(jumlah),0) FROM pengeluaran WHERE MONTH(tanggal)=? AND YEAR(tanggal)=?"
);
$stmtPng->execute([$bulan, $tahun]);
$totalPengeluaran = (float)$stmtPng->fetchColumn();

// --- Kas tambahan bulan ini ---
$stmtKas = $db->prepare(
    "SELECT COALESCE(SUM(jumlah),0) FROM kas_tambahan WHERE bulan=? AND tahun=?"
);
try {
    $stmtKas->execute([$bulan, $tahun]);
    $totalKasTambahan = (float)$stmtKas->fetchColumn();
} catch (Exception $e) {
    // Tabel belum ada (sebelum setup ulang)
    $totalKasTambahan = 0;
}

$saldo = $totalIuran + $totalKasTambahan - $totalPengeluaran;

// --- Status pembayaran penghuni bulan ini ---
$stmtStatusBayar = $db->prepare(
    "SELECT u.id, u.nama, u.nomor_kamar,
            COALESCE(p.status, 'belum') as status_bayar
     FROM users u
     LEFT JOIN pembayaran p ON p.user_id = u.id
         AND p.biaya_id = (SELECT id FROM biaya_bulanan WHERE bulan = ? AND tahun = ? LIMIT 1)
     WHERE u.role = 'penghuni' AND u.status = 'aktif'
     ORDER BY u.nomor_kamar"
);
$stmtStatusBayar->execute([$bulan, $tahun]);
$statusPembayaran = $stmtStatusBayar->fetchAll();

$jmlLunas = count(array_filter($statusPembayaran, fn($r) => $r['status_bayar'] === 'lunas'));
$jmlBelum = count($statusPembayaran) - $jmlLunas;

// --- Trend 6 bulan ---
$trendLabels = []; $trendIuran = []; $trendKeluar = [];
for ($i = 5; $i >= 0; $i--) {
    $m = (int)date('n', strtotime("-$i months"));
    $y = (int)date('Y', strtotime("-$i months"));
    $trendLabels[] = namaBulan($m);

    $si = $db->prepare("SELECT COALESCE(SUM(bb.biaya_per_orang),0) FROM pembayaran p JOIN biaya_bulanan bb ON p.biaya_id=bb.id WHERE bb.bulan=? AND bb.tahun=? AND p.status='lunas'");
    $si->execute([$m,$y]); $trendIuran[] = (float)$si->fetchColumn();

    $sk = $db->prepare("SELECT COALESCE(SUM(jumlah),0) FROM pengeluaran WHERE MONTH(tanggal)=? AND YEAR(tanggal)=?");
    $sk->execute([$m,$y]); $trendKeluar[] = (float)$sk->fetchColumn();
}

// --- Pengeluaran 5 terbaru ---
$stmtPngList = $db->query("SELECT p.*,u.nama FROM pengeluaran p LEFT JOIN users u ON p.input_by=u.id ORDER BY p.tanggal DESC LIMIT 5");
$pengeluaranList = $stmtPngList->fetchAll();

$pageTitle    = 'Dashboard Admin';
$pageSubtitle = 'Ringkasan keuangan & manajemen kontrakan';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Stat Cards -->
<div class="stats-grid fade-in">
    <div class="stat-card purple">
        <div class="stat-icon"><i class="fa-solid fa-users"></i></div>
        <div class="stat-label">Total Penghuni Aktif</div>
        <div class="stat-value"><?= $totalPenghuni ?> orang</div>
        <div class="stat-sub"><a href="users.php" style="color:var(--accent-primary)">Kelola penghuni →</a></div>
    </div>
    <div class="stat-card green">
        <div class="stat-icon"><i class="fa-solid fa-money-bill-trend-up"></i></div>
        <div class="stat-label">Iuran Terkumpul</div>
        <div class="stat-value"><?= formatRupiah($totalIuran) ?></div>
        <div class="stat-sub"><?= namaBulan($bulan) . ' ' . $tahun ?> — <?= $jmlLunas ?>/<?= count($statusPembayaran) ?> lunas</div>
    </div>
    <div class="stat-card orange">
        <div class="stat-icon"><i class="fa-solid fa-receipt"></i></div>
        <div class="stat-label">Total Pengeluaran</div>
        <div class="stat-value"><?= formatRupiah($totalPengeluaran) ?></div>
        <div class="stat-sub">Bulan <?= namaBulan($bulan) ?></div>
    </div>
    <div class="stat-card <?= $saldo >= 0 ? 'green' : 'red' ?>">
        <div class="stat-icon"><i class="fa-solid fa-wallet"></i></div>
        <div class="stat-label">Saldo Kas</div>
        <div class="stat-value"><?= formatRupiah(abs($saldo)) ?></div>
        <div class="stat-sub"><?= $saldo >= 0 ? '✅ Saldo positif' : '⚠️ Deficit' ?></div>
    </div>
</div>

<!-- Quick Actions -->
<div class="d-flex gap-12 flex-wrap fade-in mb-24">
    <a href="biaya.php" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Input Biaya Bulan Ini</a>
    <a href="pembayaran.php" class="btn btn-success"><i class="fa-solid fa-money-check-dollar"></i> Update Pembayaran</a>
    <a href="notifikasi.php" class="btn btn-warning"><i class="fa-solid fa-bell"></i> Kirim Pengingat</a>
    <a href="pengeluaran.php" class="btn btn-secondary"><i class="fa-solid fa-receipt"></i> Input Pengeluaran</a>
    <a href="kas.php" class="btn btn-info"><i class="fa-solid fa-piggy-bank"></i> Tambah Saldo Kas</a>
</div>

<!-- Chart + Status Pembayaran -->
<div class="grid-2 fade-in">
    <div class="card">
        <div class="card-header">
            <h3><i class="fa-solid fa-chart-bar" style="color:var(--accent-primary)"></i> Trend 6 Bulan Terakhir</h3>
        </div>
        <div class="chart-container">
            <canvas id="trendChart"></canvas>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3><i class="fa-solid fa-money-check-dollar" style="color:var(--success)"></i> Status Bayar — <?= namaBulan($bulan) ?></h3>
            <div class="d-flex gap-8">
                <span class="badge badge-success"><?= $jmlLunas ?> Lunas</span>
                <span class="badge badge-danger"><?= $jmlBelum ?> Belum</span>
            </div>
        </div>

        <?php if (empty($statusPembayaran)): ?>
        <div class="empty-state">
            <i class="fa-solid fa-users"></i>
            <p>Belum ada penghuni terdaftar</p>
        </div>
        <?php else: ?>
        <div style="max-height:320px;overflow-y:auto">
            <table class="table">
                <tbody>
                    <?php foreach ($statusPembayaran as $sp): ?>
                    <tr>
                        <td>
                            <div class="d-flex align-center gap-12">
                                <div class="user-avatar" style="width:34px;height:34px;font-size:13px;flex-shrink:0">
                                    <?= strtoupper(substr($sp['nama'], 0, 1)) ?>
                                </div>
                                <div>
                                    <div class="fw-600" style="font-size:13px"><?= htmlspecialchars($sp['nama']) ?></div>
                                    <div class="text-muted fs-12">Kamar <?= htmlspecialchars($sp['nomor_kamar']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="text-right">
                            <?php if ($sp['status_bayar'] === 'lunas'): ?>
                            <span class="badge badge-success"><i class="fa-solid fa-check"></i> Lunas</span>
                            <?php else: ?>
                            <span class="badge badge-danger"><i class="fa-solid fa-xmark"></i> Belum</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Recent Pengeluaran -->
<div class="card fade-in" style="margin-top:20px">
    <div class="card-header">
        <h3><i class="fa-solid fa-receipt" style="color:var(--warning)"></i> Pengeluaran Terbaru</h3>
        <a href="pengeluaran.php" class="btn btn-secondary btn-sm">Lihat Semua</a>
    </div>
    <?php if (empty($pengeluaranList)): ?>
    <div class="empty-state"><i class="fa-solid fa-receipt"></i><p>Belum ada pengeluaran</p></div>
    <?php else: ?>
    <div class="table-wrapper">
        <table class="table">
            <thead><tr>
                <th>Item</th><th>Tanggal</th><th>Dicatat Oleh</th><th class="text-right">Jumlah</th>
            </tr></thead>
            <tbody>
                <?php foreach ($pengeluaranList as $p): ?>
                <tr>
                    <td class="fw-600"><?= htmlspecialchars($p['nama_item']) ?></td>
                    <td class="text-muted"><?= date('d M Y', strtotime($p['tanggal'])) ?></td>
                    <td><?= htmlspecialchars($p['nama'] ?? 'Sistem') ?></td>
                    <td class="text-right fw-600 text-warning"><?= formatRupiah($p['jumlah']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<script>
(function() {
    const ctx = document.getElementById('trendChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($trendLabels) ?>,
            datasets: [
                {
                    label: 'Iuran Masuk',
                    data: <?= json_encode($trendIuran) ?>,
                    backgroundColor: 'rgba(124,111,239,0.7)',
                    borderRadius: 6,
                },
                {
                    label: 'Pengeluaran',
                    data: <?= json_encode($trendKeluar) ?>,
                    backgroundColor: 'rgba(245,158,11,0.7)',
                    borderRadius: 6,
                }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { labels: { color: '#9090b0', font: { size: 12 } } },
                tooltip: { callbacks: { label: (c) => ' ' + formatRupiah(c.raw) } }
            },
            scales: {
                x: { ticks: { color: '#6a6a8a', font:{size:11} }, grid: { color: 'rgba(255,255,255,0.04)' } },
                y: { ticks: { color:'#6a6a8a', font:{size:11}, callback:(v)=>'Rp '+(v/1000).toFixed(0)+'k' }, grid:{color:'rgba(255,255,255,0.04)'} }
            }
        }
    });
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
