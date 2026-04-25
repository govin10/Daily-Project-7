<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$db     = getDB();
$userId = currentUserId();

// Input pengeluaran (penghuni juga bisa input)
$msg = $msgType = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_pengeluaran') {
    $namaItem   = trim($_POST['nama_item']  ?? '');
    $jumlah     = (float)($_POST['jumlah']  ?? 0);
    $keterangan = trim($_POST['keterangan'] ?? '');
    $tanggal    = $_POST['tanggal'] ?? date('Y-m-d');
    if (empty($namaItem) || $jumlah <= 0) {
        $msg = 'Nama item dan jumlah wajib diisi dengan benar.';
        $msgType = 'danger';
    } else {
        $stmt = $db->prepare(
            "INSERT INTO pengeluaran (nama_item, jumlah, keterangan, tanggal, input_by) VALUES (?,?,?,?,?)"
        );
        $stmt->execute([$namaItem, $jumlah, $keterangan ?: null, $tanggal, $userId]);
        $msg = 'Pengeluaran berhasil ditambahkan!';
        $msgType = 'success';
    }
}

// Filter
$filterBulan = (int)($_GET['bulan'] ?? date('n'));
$filterTahun = (int)($_GET['tahun'] ?? date('Y'));

// Daftar pengeluaran
$stmtPng = $db->prepare(
    "SELECT p.*, u.nama as input_nama
     FROM pengeluaran p
     LEFT JOIN users u ON p.input_by = u.id
     WHERE MONTH(p.tanggal) = ? AND YEAR(p.tanggal) = ?
     ORDER BY p.tanggal DESC, p.created_at DESC"
);
$stmtPng->execute([$filterBulan, $filterTahun]);
$pengeluaranList = $stmtPng->fetchAll();

// Total
$stmtTotal = $db->prepare(
    "SELECT COALESCE(SUM(jumlah),0) FROM pengeluaran WHERE MONTH(tanggal) = ? AND YEAR(tanggal) = ?"
);
$stmtTotal->execute([$filterBulan, $filterTahun]);
$totalBulanIni = (float)$stmtTotal->fetchColumn();

$pageTitle    = 'Pengeluaran Bersama';
$pageSubtitle = 'Catatan pengeluaran kontrakan';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header fade-in">
    <div>
        <h1>Pengeluaran Bersama</h1>
        <p>Pencatatan semua pengeluaran kontrakan secara transparan</p>
    </div>
    <button class="btn btn-primary" data-modal-open="modalPengeluaran">
        <i class="fa-solid fa-plus"></i> Tambah Pengeluaran
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
        <div class="form-group" style="margin:0;flex:1;min-width:140px">
            <label>Bulan</label>
            <select name="bulan" class="form-control" onchange="this.form.submit()">
                <?php for ($m = 1; $m <= 12; $m++): ?>
                <option value="<?= $m ?>" <?= $m === $filterBulan ? 'selected' : '' ?>>
                    <?= namaBulan($m) ?>
                </option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="form-group" style="margin:0;flex:1;min-width:100px">
            <label>Tahun</label>
            <select name="tahun" class="form-control" onchange="this.form.submit()">
                <?php for ($y = date('Y'); $y >= date('Y') - 2; $y--): ?>
                <option value="<?= $y ?>" <?= $y === $filterTahun ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </div>
    </form>
</div>

<!-- Summary -->
<div class="stats-grid fade-in" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr));margin-bottom:20px">
    <div class="stat-card orange">
        <div class="stat-icon"><i class="fa-solid fa-receipt"></i></div>
        <div class="stat-label">Total Pengeluaran</div>
        <div class="stat-value"><?= formatRupiah($totalBulanIni) ?></div>
        <div class="stat-sub"><?= namaBulan($filterBulan) . ' ' . $filterTahun ?></div>
    </div>
    <div class="stat-card blue">
        <div class="stat-icon"><i class="fa-solid fa-list-check"></i></div>
        <div class="stat-label">Jumlah Item</div>
        <div class="stat-value"><?= count($pengeluaranList) ?></div>
        <div class="stat-sub">transaksi pengeluaran</div>
    </div>
</div>

<!-- Tabel Pengeluaran -->
<div class="card fade-in">
    <div class="card-header">
        <h3>Daftar Pengeluaran — <?= namaBulan($filterBulan) . ' ' . $filterTahun ?></h3>
    </div>
    <?php if (empty($pengeluaranList)): ?>
    <div class="empty-state">
        <i class="fa-solid fa-receipt"></i>
        <p>Belum ada pengeluaran di bulan ini</p>
    </div>
    <?php else: ?>
    <div class="table-wrapper">
        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Item Pengeluaran</th>
                    <th>Keterangan</th>
                    <th>Tanggal</th>
                    <th>Dicatat Oleh</th>
                    <th class="text-right">Jumlah</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pengeluaranList as $i => $p): ?>
                <tr>
                    <td class="text-muted"><?= $i + 1 ?></td>
                    <td>
                        <div class="fw-600"><?= htmlspecialchars($p['nama_item']) ?></div>
                    </td>
                    <td class="text-muted"><?= $p['keterangan'] ? htmlspecialchars($p['keterangan']) : '—' ?></td>
                    <td class="text-muted"><?= date('d M Y', strtotime($p['tanggal'])) ?></td>
                    <td>
                        <span class="chip"><?= htmlspecialchars($p['input_nama'] ?? 'Sistem') ?></span>
                    </td>
                    <td class="text-right fw-600 text-warning"><?= formatRupiah($p['jumlah']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5" class="fw-700" style="padding:14px 16px;border-top:2px solid var(--border-accent)">TOTAL</td>
                    <td class="text-right fw-700 text-accent" style="padding:14px 16px;border-top:2px solid var(--border-accent);font-size:16px"><?= formatRupiah($totalBulanIni) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Modal Tambah Pengeluaran -->
<div class="modal-overlay" id="modalPengeluaran">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fa-solid fa-receipt" style="color:var(--warning)"></i> Tambah Pengeluaran</h3>
            <button class="modal-close"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add_pengeluaran">
            <div class="form-group">
                <label>Nama Item *</label>
                <input type="text" name="nama_item" class="form-control" placeholder="Contoh: Galon air, Sapu, dll." required>
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
                <label>Keterangan (Opsional)</label>
                <input type="text" name="keterangan" class="form-control" placeholder="Keterangan tambahan...">
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-plus"></i> Tambah
                </button>
                <button type="button" class="btn btn-secondary modal-close">Batal</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
