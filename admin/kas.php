<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$db = getDB();

// Auto-migrate — buat tabel jika belum ada
$db->exec("CREATE TABLE IF NOT EXISTS `kas_tambahan` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `judul`      VARCHAR(150) NOT NULL,
    `jumlah`     DECIMAL(12,2) NOT NULL,
    `keterangan` TEXT NULL,
    `tanggal`    DATE NOT NULL,
    `bulan`      TINYINT NOT NULL,
    `tahun`      YEAR NOT NULL,
    `input_by`   INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`input_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$msg = $msgType = '';

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_kas') {
        $judul   = trim($_POST['judul']      ?? '');
        $jumlah  = (float)($_POST['jumlah']  ?? 0);
        $ket     = trim($_POST['keterangan'] ?? '');
        $tanggal = $_POST['tanggal'] ?? date('Y-m-d');
        $bulan   = (int)date('n', strtotime($tanggal));
        $tahun   = (int)date('Y', strtotime($tanggal));

        if (empty($judul) || $jumlah <= 0) {
            $msg = 'Keterangan dan jumlah wajib diisi dengan benar.';
            $msgType = 'danger';
        } else {
            $db->prepare(
                "INSERT INTO kas_tambahan (judul, jumlah, keterangan, tanggal, bulan, tahun, input_by)
                 VALUES (?,?,?,?,?,?,?)"
            )->execute([$judul, $jumlah, $ket ?: null, $tanggal, $bulan, $tahun, currentUserId()]);
            $msg = 'Saldo kas berhasil ditambahkan!';
            $msgType = 'success';
        }
    }

    if ($action === 'delete_kas' && isset($_POST['id'])) {
        $db->prepare("DELETE FROM kas_tambahan WHERE id=?")->execute([(int)$_POST['id']]);
        $msg = 'Entri kas berhasil dihapus.';
        $msgType = 'success';
    }
}

// Filter
$filterBulan = (int)($_GET['bulan'] ?? date('n'));
$filterTahun = (int)($_GET['tahun'] ?? date('Y'));

// Kas tambahan periode ini
$stmtKas = $db->prepare(
    "SELECT k.*, u.nama as input_nama
     FROM kas_tambahan k LEFT JOIN users u ON k.input_by = u.id
     WHERE k.bulan=? AND k.tahun=?
     ORDER BY k.tanggal DESC, k.created_at DESC"
);
$stmtKas->execute([$filterBulan, $filterTahun]);
$kasList = $stmtKas->fetchAll();
$totalKasBulan = (float)array_sum(array_column($kasList, 'jumlah'));

// Iuran lunas periode ini
$stmtIuran = $db->prepare(
    "SELECT COALESCE(SUM(bb.biaya_per_orang),0)
     FROM pembayaran p JOIN biaya_bulanan bb ON p.biaya_id=bb.id
     WHERE bb.bulan=? AND bb.tahun=? AND p.status='lunas'"
);
$stmtIuran->execute([$filterBulan, $filterTahun]);
$totalIuran = (float)$stmtIuran->fetchColumn();

// Pengeluaran periode ini
$stmtPng = $db->prepare(
    "SELECT COALESCE(SUM(jumlah),0) FROM pengeluaran WHERE MONTH(tanggal)=? AND YEAR(tanggal)=?"
);
$stmtPng->execute([$filterBulan, $filterTahun]);
$totalPengeluaran = (float)$stmtPng->fetchColumn();

$totalPemasukan = $totalIuran + $totalKasBulan;
$saldo          = $totalPemasukan - $totalPengeluaran;

$pageTitle    = 'Saldo Kas';
$pageSubtitle = 'Tambah & pantau kas kontrakan';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header fade-in">
    <div>
        <h1>Saldo Kas Kontrakan</h1>
        <p>Kelola pemasukan tambahan di luar iuran bulanan</p>
    </div>
    <button class="btn btn-primary" data-modal-open="modalKas">
        <i class="fa-solid fa-plus"></i> Tambah Saldo Kas
    </button>
</div>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType ?> fade-in" data-dismiss="4000">
    <i class="fa-solid <?= $msgType === 'success' ? 'fa-circle-check' : 'fa-circle-xmark' ?>"></i>
    <?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

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

<!-- Stats -->
<div class="stats-grid fade-in" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr));margin-bottom:20px">
    <div class="stat-card green">
        <div class="stat-icon"><i class="fa-solid fa-money-bill-trend-up"></i></div>
        <div class="stat-label">Iuran Terkumpul</div>
        <div class="stat-value"><?= formatRupiah($totalIuran) ?></div>
        <div class="stat-sub">Pembayaran lunas</div>
    </div>
    <div class="stat-card blue">
        <div class="stat-icon"><i class="fa-solid fa-piggy-bank"></i></div>
        <div class="stat-label">Kas Tambahan</div>
        <div class="stat-value"><?= formatRupiah($totalKasBulan) ?></div>
        <div class="stat-sub"><?= namaBulan($filterBulan) . ' ' . $filterTahun ?></div>
    </div>
    <div class="stat-card purple">
        <div class="stat-icon"><i class="fa-solid fa-hand-holding-dollar"></i></div>
        <div class="stat-label">Total Pemasukan</div>
        <div class="stat-value"><?= formatRupiah($totalPemasukan) ?></div>
        <div class="stat-sub">Iuran + Kas Tambahan</div>
    </div>
    <div class="stat-card orange">
        <div class="stat-icon"><i class="fa-solid fa-receipt"></i></div>
        <div class="stat-label">Total Pengeluaran</div>
        <div class="stat-value"><?= formatRupiah($totalPengeluaran) ?></div>
    </div>
    <div class="stat-card <?= $saldo >= 0 ? 'green' : 'red' ?>">
        <div class="stat-icon"><i class="fa-solid fa-wallet"></i></div>
        <div class="stat-label">Saldo Bersih</div>
        <div class="stat-value"><?= formatRupiah(abs($saldo)) ?></div>
        <div class="stat-sub"><?= $saldo >= 0 ? '✅ Positif' : '⚠️ Deficit' ?></div>
    </div>
</div>

<!-- Ringkasan Keuangan -->
<div class="card fade-in mb-20">
    <div class="card-header">
        <h3><i class="fa-solid fa-scale-balanced" style="color:var(--accent-primary)"></i>
            Ringkasan Keuangan — <?= namaBulan($filterBulan) . ' ' . $filterTahun ?>
        </h3>
    </div>
    <div style="max-width:480px">
        <?php foreach ([
            ['label' => 'Iuran Penghuni (lunas)',   'value' => $totalIuran,       'color' => 'success', 'sign' => '+'],
            ['label' => 'Kas Tambahan',              'value' => $totalKasBulan,    'color' => 'info',    'sign' => '+'],
            ['label' => 'Total Pengeluaran',         'value' => $totalPengeluaran, 'color' => 'warning', 'sign' => '-'],
        ] as $r): ?>
        <div class="d-flex justify-between align-center" style="padding:12px 0;border-bottom:1px solid var(--border)">
            <div class="d-flex align-center gap-8">
                <span style="font-weight:700;color:var(--<?= $r['color'] ?>);font-size:16px;width:14px"><?= $r['sign'] ?></span>
                <span style="font-size:13.5px"><?= $r['label'] ?></span>
            </div>
            <span class="fw-600 text-<?= $r['color'] ?>"><?= formatRupiah($r['value']) ?></span>
        </div>
        <?php endforeach; ?>
        <div class="d-flex justify-between align-center" style="padding:16px 0">
            <span class="fw-700" style="font-size:15px">💰 SALDO KAS BERSIH</span>
            <span class="fw-800 text-<?= $saldo >= 0 ? 'success' : 'danger' ?>" style="font-size:22px">
                <?= ($saldo < 0 ? '- ' : '') . formatRupiah(abs($saldo)) ?>
            </span>
        </div>
    </div>
</div>

<!-- Daftar Kas Tambahan -->
<div class="card fade-in">
    <div class="card-header">
        <h3><i class="fa-solid fa-piggy-bank" style="color:var(--info)"></i>
            Kas Tambahan — <?= namaBulan($filterBulan) . ' ' . $filterTahun ?>
        </h3>
        <span class="badge badge-info"><?= count($kasList) ?> entri</span>
    </div>
    <?php if (empty($kasList)): ?>
    <div class="empty-state">
        <i class="fa-solid fa-piggy-bank"></i>
        <p>Belum ada kas tambahan bulan ini.<br>Klik <strong>"Tambah Saldo Kas"</strong> untuk menambahkan.</p>
    </div>
    <?php else: ?>
    <div class="table-wrapper">
        <table class="table">
            <thead>
                <tr>
                    <th>Keterangan</th>
                    <th>Tanggal</th>
                    <th>Dicatat Oleh</th>
                    <th class="text-right">Jumlah</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($kasList as $k): ?>
                <tr>
                    <td>
                        <div class="fw-600"><?= htmlspecialchars($k['judul']) ?></div>
                        <?php if ($k['keterangan']): ?>
                        <div class="text-muted fs-12"><?= htmlspecialchars($k['keterangan']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="text-muted"><?= date('d M Y', strtotime($k['tanggal'])) ?></td>
                    <td><span class="chip"><?= htmlspecialchars($k['input_nama'] ?? 'Admin') ?></span></td>
                    <td class="text-right fw-600 text-info"><?= formatRupiah($k['jumlah']) ?></td>
                    <td>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="action" value="delete_kas">
                            <input type="hidden" name="id" value="<?= $k['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger"
                                data-confirm="Hapus entri kas '<?= htmlspecialchars($k['judul']) ?>'?">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3" class="fw-700" style="padding:14px 16px;border-top:2px solid var(--border-accent)">TOTAL</td>
                    <td class="text-right fw-700 text-info" style="padding:14px 16px;border-top:2px solid var(--border-accent);font-size:16px"><?= formatRupiah($totalKasBulan) ?></td>
                    <td style="border-top:2px solid var(--border-accent)"></td>
                </tr>
            </tfoot>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Modal Tambah Kas -->
<div class="modal-overlay" id="modalKas">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fa-solid fa-piggy-bank" style="color:var(--info)"></i> Tambah Saldo Kas</h3>
            <button class="modal-close"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add_kas">
            <div class="form-group">
                <label>Keterangan / Sumber Dana *</label>
                <input type="text" name="judul" class="form-control"
                    placeholder="Contoh: Iuran kebersihan RT, Donasi, Tabungan bersama" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Jumlah (Rp) *</label>
                    <input type="number" name="jumlah" class="form-control" placeholder="0" min="1" required>
                </div>
                <div class="form-group">
                    <label>Tanggal *</label>
                    <input type="date" name="tanggal" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
            </div>
            <div class="form-group">
                <label>Catatan Tambahan (Opsional)</label>
                <input type="text" name="keterangan" class="form-control" placeholder="Detail tambahan...">
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-plus"></i> Tambah ke Kas
                </button>
                <button type="button" class="btn btn-secondary modal-close">Batal</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
