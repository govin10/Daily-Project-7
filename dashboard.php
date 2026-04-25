<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$db     = getDB();
$userId = currentUserId();
$today  = date('Y-m-d');
$bulan  = (int)date('n');
$tahun  = (int)date('Y');

// --- Biaya bulan ini ---
$stmtBiaya = $db->prepare("SELECT * FROM biaya_bulanan WHERE bulan = ? AND tahun = ? LIMIT 1");
$stmtBiaya->execute([$bulan, $tahun]);
$biayaBulanIni = $stmtBiaya->fetch();

// --- Status pembayaran user bulan ini ---
$statusBayar = null;
if ($biayaBulanIni) {
    $stmtStatus = $db->prepare(
        "SELECT * FROM pembayaran WHERE user_id = ? AND biaya_id = ? LIMIT 1"
    );
    $stmtStatus->execute([$userId, $biayaBulanIni['id']]);
    $statusBayar = $stmtStatus->fetch();
}

// --- Total pengeluaran bulan ini ---
$stmtPengeluaran = $db->prepare(
    "SELECT COALESCE(SUM(jumlah), 0) as total FROM pengeluaran
     WHERE MONTH(tanggal) = ? AND YEAR(tanggal) = ?"
);
$stmtPengeluaran->execute([$bulan, $tahun]);
$totalPengeluaran = (float)$stmtPengeluaran->fetchColumn();

// --- Total iuran yang sudah lunas bulan ini ---
$stmtLunas = $db->prepare(
    "SELECT COUNT(*) as cnt, COALESCE(SUM(bb.biaya_per_orang), 0) as total
     FROM pembayaran p
     JOIN biaya_bulanan bb ON p.biaya_id = bb.id
     WHERE bb.bulan = ? AND bb.tahun = ? AND p.status = 'lunas'"
);
$stmtLunas->execute([$bulan, $tahun]);
$lunasData = $stmtLunas->fetch();
$totalIuranTerkumpul = (float)$lunasData['total'];

// --- Saldo ---
$saldo = $totalIuranTerkumpul - $totalPengeluaran;

// --- Trend 6 bulan terakhir ---
$trendLabels  = [];
$trendIuran   = [];
$trendKeluar  = [];
for ($i = 5; $i >= 0; $i--) {
    $m = (int)date('n', strtotime("-$i months"));
    $y = (int)date('Y', strtotime("-$i months"));
    $trendLabels[] = namaBulan($m) . ' ' . $y;

    $stI = $db->prepare(
        "SELECT COALESCE(SUM(bb.biaya_per_orang),0) as total
         FROM pembayaran p JOIN biaya_bulanan bb ON p.biaya_id = bb.id
         WHERE bb.bulan = ? AND bb.tahun = ? AND p.status = 'lunas'"
    );
    $stI->execute([$m, $y]);
    $trendIuran[] = (float)$stI->fetchColumn();

    $stK = $db->prepare(
        "SELECT COALESCE(SUM(jumlah),0) FROM pengeluaran WHERE MONTH(tanggal)=? AND YEAR(tanggal)=?"
    );
    $stK->execute([$m, $y]);
    $trendKeluar[] = (float)$stK->fetchColumn();
}

// --- 5 notifikasi terbaru ---
$stmtNotif = $db->prepare(
    "SELECT * FROM notifikasi WHERE user_id = ? ORDER BY created_at DESC LIMIT 5"
);
$stmtNotif->execute([$userId]);
$notifList = $stmtNotif->fetchAll();

// --- 5 pengeluaran terbaru ---
$stmtPng = $db->prepare(
    "SELECT p.*, u.nama as input_nama FROM pengeluaran p
     LEFT JOIN users u ON p.input_by = u.id
     ORDER BY p.tanggal DESC, p.created_at DESC LIMIT 5"
);
$stmtPng->execute();
$pengeluaranList = $stmtPng->fetchAll();

$pageTitle    = 'Dashboard';
$pageSubtitle = 'Selamat datang, ' . currentUserName() . '!';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Stats Cards -->
<div class="stats-grid fade-in">
    <div class="stat-card purple">
        <div class="stat-icon"><i class="fa-solid fa-file-invoice-dollar"></i></div>
        <div class="stat-label">Tagihan Bulan Ini</div>
        <div class="stat-value"><?= $biayaBulanIni ? formatRupiah($biayaBulanIni['biaya_per_orang']) : 'Belum diset' ?></div>
        <div class="stat-sub"><?= namaBulan($bulan) . ' ' . $tahun ?></div>
    </div>
    <div class="stat-card <?= ($statusBayar && $statusBayar['status'] === 'lunas') ? 'green' : 'red' ?>">
        <div class="stat-icon">
            <i class="fa-solid <?= ($statusBayar && $statusBayar['status'] === 'lunas') ? 'fa-circle-check' : 'fa-circle-xmark' ?>"></i>
        </div>
        <div class="stat-label">Status Pembayaran</div>
        <div class="stat-value"><?= ($statusBayar && $statusBayar['status'] === 'lunas') ? 'Lunas ✓' : 'Belum Bayar' ?></div>
        <div class="stat-sub">
            <?php if ($statusBayar && $statusBayar['status'] === 'lunas' && $statusBayar['tanggal_bayar']): ?>
                Dibayar: <?= date('d M Y', strtotime($statusBayar['tanggal_bayar'])) ?>
            <?php else: ?>
                Segera selesaikan pembayaran
            <?php endif; ?>
        </div>
    </div>
    <div class="stat-card orange">
        <div class="stat-icon"><i class="fa-solid fa-receipt"></i></div>
        <div class="stat-label">Total Pengeluaran Bersama</div>
        <div class="stat-value"><?= formatRupiah($totalPengeluaran) ?></div>
        <div class="stat-sub">Bulan <?= namaBulan($bulan) ?></div>
    </div>
    <div class="stat-card <?= $saldo >= 0 ? 'green' : 'red' ?>">
        <div class="stat-icon"><i class="fa-solid fa-wallet"></i></div>
        <div class="stat-label">Saldo Kas</div>
        <div class="stat-value"><?= formatRupiah(abs($saldo)) ?></div>
        <div class="stat-sub"><?= $saldo >= 0 ? 'Saldo positif' : '⚠ Deficit' ?></div>
    </div>
</div>

<!-- Charts + Notifikasi -->
<div class="grid-2 fade-in">
    <!-- Chart Trend -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fa-solid fa-chart-line" style="color:var(--accent-primary)"></i> Trend Keuangan 6 Bulan</h3>
        </div>
        <div class="chart-container">
            <canvas id="trendChart"></canvas>
        </div>
    </div>

    <!-- Notifikasi Terbaru -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fa-solid fa-bell" style="color:var(--warning)"></i> Notifikasi Terbaru</h3>
            <a href="<?= BASE_URL ?>/notifikasi.php" class="btn btn-secondary btn-sm">Lihat Semua</a>
        </div>
        <?php if (empty($notifList)): ?>
        <div class="empty-state">
            <i class="fa-regular fa-bell"></i>
            <p>Belum ada notifikasi</p>
        </div>
        <?php else: ?>
        <div class="notif-list">
            <?php foreach ($notifList as $n): ?>
            <div class="notif-item <?= $n['is_read'] ? 'read' : '' ?>">
                <div class="notif-dot"></div>
                <div class="notif-content">
                    <div class="notif-title"><?= htmlspecialchars($n['judul']) ?></div>
                    <div class="notif-msg"><?= htmlspecialchars(mb_strimwidth($n['pesan'], 0, 80, '...')) ?></div>
                    <div class="notif-time"><?= date('d M Y, H:i', strtotime($n['created_at'])) ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Rincian Biaya + Pengeluaran Terbaru -->
<div class="grid-2 fade-in" style="margin-top:20px">
    <!-- Rincian Biaya Bulan Ini -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fa-solid fa-calculator" style="color:var(--info)"></i> Rincian Biaya <?= namaBulan($bulan) ?></h3>
            <a href="<?= BASE_URL ?>/biaya.php" class="btn btn-secondary btn-sm">Detail</a>
        </div>
        <?php if ($biayaBulanIni): ?>
        <div>
            <?php
            $items = [
                ['label' => 'Sewa Kontrakan', 'value' => $biayaBulanIni['total_sewa'],   'icon' => 'fa-house'],
                ['label' => 'Listrik',         'value' => $biayaBulanIni['total_listrik'],'icon' => 'fa-bolt'],
                ['label' => 'Air',             'value' => $biayaBulanIni['total_air'],    'icon' => 'fa-droplet'],
                ['label' => 'WiFi / Internet', 'value' => $biayaBulanIni['total_wifi'],   'icon' => 'fa-wifi'],
                ['label' => 'Lainnya',         'value' => $biayaBulanIni['total_lain'],   'icon' => 'fa-ellipsis'],
            ];
            $totalBiaya = array_sum(array_column($items, 'value'));
            foreach ($items as $item): if ($item['value'] <= 0) continue; ?>
            <div class="d-flex align-center justify-between" style="padding:10px 0;border-bottom:1px solid var(--border)">
                <div class="d-flex align-center gap-8">
                    <div style="width:32px;height:32px;background:var(--bg-input);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:14px;color:var(--accent-primary)">
                        <i class="fa-solid <?= $item['icon'] ?>"></i>
                    </div>
                    <span style="font-size:13px"><?= $item['label'] ?></span>
                </div>
                <span style="font-size:13px;font-weight:600"><?= formatRupiah($item['value']) ?></span>
            </div>
            <?php endforeach; ?>
            <div class="d-flex align-center justify-between" style="padding:12px 0;border-top:2px solid var(--border-accent);margin-top:4px">
                <span style="font-weight:700;font-size:14px">Tagihan / Orang</span>
                <span style="font-weight:800;font-size:18px;color:var(--accent-primary)"><?= formatRupiah($biayaBulanIni['biaya_per_orang']) ?></span>
            </div>
            <p class="text-muted fs-12" style="margin-top:4px">Total <?= $biayaBulanIni['jumlah_penghuni'] ?> penghuni aktif</p>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <i class="fa-solid fa-file-invoice-dollar"></i>
            <p>Belum ada biaya yang diinput untuk bulan ini</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Pengeluaran Terbaru -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fa-solid fa-receipt" style="color:var(--warning)"></i> Pengeluaran Terbaru</h3>
            <a href="<?= BASE_URL ?>/pengeluaran.php" class="btn btn-secondary btn-sm">Lihat Semua</a>
        </div>
        <?php if (empty($pengeluaranList)): ?>
        <div class="empty-state">
            <i class="fa-solid fa-receipt"></i>
            <p>Belum ada pengeluaran tercatat</p>
        </div>
        <?php else: ?>
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Tanggal</th>
                        <th class="text-right">Jumlah</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pengeluaranList as $p): ?>
                    <tr>
                        <td>
                            <div style="font-weight:500"><?= htmlspecialchars($p['nama_item']) ?></div>
                            <?php if ($p['keterangan']): ?>
                            <div class="text-muted fs-12"><?= htmlspecialchars(mb_strimwidth($p['keterangan'], 0, 40, '...')) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted"><?= date('d M Y', strtotime($p['tanggal'])) ?></td>
                        <td class="text-right fw-600 text-warning"><?= formatRupiah($p['jumlah']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Trend Chart
(function() {
    const ctx = document.getElementById('trendChart').getContext('2d');
    const labels  = <?= json_encode($trendLabels) ?>;
    const iuran   = <?= json_encode($trendIuran) ?>;
    const keluar  = <?= json_encode($trendKeluar) ?>;

    const grdIuran  = createGradient(ctx, 'rgba(124,111,239,0.4)', 'rgba(124,111,239,0.02)');
    const grdKeluar = createGradient(ctx, 'rgba(245,158,11,0.35)', 'rgba(245,158,11,0.02)');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels,
            datasets: [
                {
                    label: 'Iuran Terkumpul',
                    data: iuran,
                    borderColor: '#7c6fef',
                    backgroundColor: grdIuran,
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#7c6fef',
                    pointRadius: 4,
                },
                {
                    label: 'Pengeluaran',
                    data: keluar,
                    borderColor: '#f59e0b',
                    backgroundColor: grdKeluar,
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#f59e0b',
                    pointRadius: 4,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { labels: { color: '#9090b0', font: { size: 12, family: 'Inter' } } },
                tooltip: {
                    callbacks: {
                        label: (ctx) => ' ' + formatRupiah(ctx.raw)
                    }
                }
            },
            scales: {
                x: {
                    ticks: { color: '#6a6a8a', font: { size: 11 } },
                    grid:  { color: 'rgba(255,255,255,0.04)' }
                },
                y: {
                    ticks: {
                        color: '#6a6a8a', font: { size: 11 },
                        callback: (v) => 'Rp ' + (v/1000).toFixed(0) + 'k'
                    },
                    grid: { color: 'rgba(255,255,255,0.04)' }
                }
            }
        }
    });
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
