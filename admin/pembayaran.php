<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$db = getDB();

// Handle: update status pembayaran — ALWAYS UPSERT + PRG redirect
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
    $userId   = (int)$_POST['user_id'];
    $biayaId  = (int)$_POST['biaya_id'];
    $status   = ($_POST['status'] ?? '') === 'lunas' ? 'lunas' : 'belum';
    $tglBayar = ($status === 'lunas') ? (trim($_POST['tanggal_bayar'] ?? '') ?: date('Y-m-d')) : null;
    $ket      = trim($_POST['keterangan'] ?? '');
    $fBulan   = (int)($_POST['filter_bulan'] ?? date('n'));
    $fTahun   = (int)($_POST['filter_tahun'] ?? date('Y'));

    if ($userId > 0 && $biayaId > 0) {
        // Selalu gunakan UPSERT — jauh lebih aman & andal
        $db->prepare(
            "INSERT INTO pembayaran (user_id, biaya_id, status, tanggal_bayar, keterangan, updated_by)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                status        = VALUES(status),
                tanggal_bayar = VALUES(tanggal_bayar),
                keterangan    = VALUES(keterangan),
                updated_by    = VALUES(updated_by)"
        )->execute([$userId, $biayaId, $status, $tglBayar, $ket ?: null, currentUserId()]);
    }

    // PRG: redirect agar refresh browser tidak re-submit & penghuni langsung lihat update
    header("Location: pembayaran.php?bulan={$fBulan}&tahun={$fTahun}&updated=1");
    exit;
}

$msg = $msgType = '';
if (isset($_GET['updated'])) {
    $msg = 'Status pembayaran berhasil diperbarui!';
    $msgType = 'success';
}

// Filter
$filterBulan = (int)($_GET['bulan'] ?? date('n'));
$filterTahun = (int)($_GET['tahun'] ?? date('Y'));

// Cari biaya periode ini
$stmtBiaya = $db->prepare("SELECT * FROM biaya_bulanan WHERE bulan=? AND tahun=? LIMIT 1");
$stmtBiaya->execute([$filterBulan, $filterTahun]);
$biayaAktif = $stmtBiaya->fetch();

// Status pembayaran semua penghuni aktif
$statusList = [];
if ($biayaAktif) {
    $stmt = $db->prepare(
        "SELECT u.id as user_id, u.nama, u.nomor_kamar, u.email,
                p.id as pay_id, p.status, p.tanggal_bayar, p.keterangan
         FROM users u
         LEFT JOIN pembayaran p ON p.user_id = u.id AND p.biaya_id = ?
         WHERE u.role='penghuni' AND u.status='aktif'
         ORDER BY u.nomor_kamar, u.nama"
    );
    $stmt->execute([$biayaAktif['id']]);
    $statusList = $stmt->fetchAll();
}

// Summary
$jmlLunas = count(array_filter($statusList, fn($r) => ($r['status'] ?? '') === 'lunas'));
$jmlBelum = count($statusList) - $jmlLunas;

$pageTitle    = 'Update Pembayaran';
$pageSubtitle = 'Kelola status iuran penghuni';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header fade-in">
    <div>
        <h1>Tracking Pembayaran</h1>
        <p>Update dan pantau status pembayaran setiap penghuni</p>
    </div>
</div>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType ?> fade-in" data-dismiss="4000">
    <i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<!-- Filter Periode -->
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

<?php if (!$biayaAktif): ?>
<div class="card fade-in">
    <div class="empty-state">
        <i class="fa-solid fa-file-invoice-dollar"></i>
        <p>Biaya untuk <?= namaBulan($filterBulan) . ' ' . $filterTahun ?> belum diinput.</p>
        <a href="biaya.php" class="btn btn-primary" style="margin-top:16px">
            <i class="fa-solid fa-plus"></i> Input Biaya Dulu
        </a>
    </div>
</div>
<?php else: ?>

<!-- Stats -->
<div class="stats-grid fade-in" style="grid-template-columns:repeat(auto-fit,minmax(180px,1fr));margin-bottom:20px">
    <div class="stat-card purple">
        <div class="stat-icon"><i class="fa-solid fa-file-invoice-dollar"></i></div>
        <div class="stat-label">Tagihan Per Orang</div>
        <div class="stat-value"><?= formatRupiah($biayaAktif['biaya_per_orang']) ?></div>
    </div>
    <div class="stat-card green">
        <div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div>
        <div class="stat-label">Sudah Lunas</div>
        <div class="stat-value"><?= $jmlLunas ?> orang</div>
    </div>
    <div class="stat-card red">
        <div class="stat-icon"><i class="fa-solid fa-circle-xmark"></i></div>
        <div class="stat-label">Belum Bayar</div>
        <div class="stat-value"><?= $jmlBelum ?> orang</div>
    </div>
    <div class="stat-card orange">
        <div class="stat-icon"><i class="fa-solid fa-money-bill-wave"></i></div>
        <div class="stat-label">Total Terkumpul</div>
        <div class="stat-value"><?= formatRupiah($jmlLunas * $biayaAktif['biaya_per_orang']) ?></div>
    </div>
</div>

<!-- Progress Bar -->
<?php $pct = count($statusList) > 0 ? round($jmlLunas / count($statusList) * 100) : 0; ?>
<div class="card fade-in mb-20">
    <div class="d-flex justify-between align-center mb-16">
        <span class="fw-600">Progress Pembayaran <?= namaBulan($filterBulan) . ' ' . $filterTahun ?></span>
        <span class="fw-700 text-accent"><?= $pct ?>%</span>
    </div>
    <div class="progress-bar">
        <div class="progress-fill" style="width:<?= $pct ?>%"></div>
    </div>
    <div class="text-muted fs-12" style="margin-top:6px"><?= $jmlLunas ?> dari <?= count($statusList) ?> penghuni sudah membayar</div>
</div>

<!-- Tabel Status Pembayaran -->
<div class="card fade-in">
    <div class="card-header">
        <h3>Daftar Status Pembayaran — <?= namaBulan($filterBulan) . ' ' . $filterTahun ?></h3>
    </div>
    <?php if (empty($statusList)): ?>
    <div class="empty-state"><i class="fa-solid fa-users"></i><p>Tidak ada penghuni aktif.</p></div>
    <?php else: ?>
    <div class="table-wrapper">
        <table class="table">
            <thead>
                <tr>
                    <th>Penghuni</th>
                    <th>Kamar</th>
                    <th>Status</th>
                    <th>Tanggal Bayar</th>
                    <th>Keterangan</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($statusList as $s): ?>
                <tr>
                    <td>
                        <div class="d-flex align-center gap-10">
                            <div class="user-avatar" style="width:34px;height:34px;font-size:13px;flex-shrink:0">
                                <?= strtoupper(substr($s['nama'], 0, 1)) ?>
                            </div>
                            <div>
                                <div class="fw-600"><?= htmlspecialchars($s['nama']) ?></div>
                                <div class="text-muted fs-12"><?= htmlspecialchars($s['email']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td><span class="chip">Kamar <?= htmlspecialchars($s['nomor_kamar']) ?></span></td>
                    <td>
                        <?php if (($s['status'] ?? '') === 'lunas'): ?>
                        <span class="badge badge-success"><i class="fa-solid fa-check"></i> Lunas</span>
                        <?php else: ?>
                        <span class="badge badge-danger"><i class="fa-solid fa-xmark"></i> Belum Bayar</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-muted">
                        <?= ($s['tanggal_bayar']) ? date('d M Y', strtotime($s['tanggal_bayar'])) : '—' ?>
                    </td>
                    <td class="text-muted"><?= $s['keterangan'] ? htmlspecialchars($s['keterangan']) : '—' ?></td>
                    <td>
                        <!-- data-* attributes — aman dari JSON/HTML-encoding bug -->
                        <button
                            class="btn btn-sm btn-primary btn-update-bayar"
                            data-uid="<?= (int)$s['user_id'] ?>"
                            data-bid="<?= (int)$biayaAktif['id'] ?>"
                            data-nama="<?= htmlspecialchars($s['nama'], ENT_QUOTES) ?>"
                            data-kamar="<?= htmlspecialchars($s['nomor_kamar'], ENT_QUOTES) ?>"
                            data-status="<?= $s['status'] ?? 'belum' ?>"
                            data-tgl="<?= $s['tanggal_bayar'] ?? date('Y-m-d') ?>"
                            data-ket="<?= htmlspecialchars($s['keterangan'] ?? '', ENT_QUOTES) ?>"
                            data-modal-open="modalUpdate">
                            <i class="fa-solid fa-pen"></i> Update
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php endif; ?>

<!-- Modal Update Status -->
<div class="modal-overlay" id="modalUpdate">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fa-solid fa-money-check-dollar" style="color:var(--success)"></i> Update Status Pembayaran</h3>
            <button class="modal-close"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST" action="pembayaran.php">
            <input type="hidden" name="action"       value="update_status">
            <input type="hidden" name="user_id"      id="m_user_id">
            <input type="hidden" name="biaya_id"     id="m_biaya_id">
            <input type="hidden" name="filter_bulan" value="<?= $filterBulan ?>">
            <input type="hidden" name="filter_tahun" value="<?= $filterTahun ?>">

            <div class="form-group">
                <label>Penghuni</label>
                <input type="text" id="m_nama" class="form-control" disabled>
            </div>
            <div class="form-group">
                <label>Status Pembayaran</label>
                <select name="status" id="m_status" class="form-control" onchange="toggleTglBayar(this.value)">
                    <option value="lunas">✅ Lunas</option>
                    <option value="belum">❌ Belum Bayar</option>
                </select>
            </div>
            <div class="form-group" id="grpTglBayar">
                <label>Tanggal Bayar</label>
                <input type="date" name="tanggal_bayar" id="m_tgl_bayar" class="form-control" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="form-group">
                <label>Keterangan (Opsional)</label>
                <input type="text" name="keterangan" id="m_keterangan" class="form-control" placeholder="Transfer BCA, tunai, dll.">
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Simpan</button>
                <button type="button" class="btn btn-secondary modal-close">Batal</button>
            </div>
        </form>
    </div>
</div>

<script>
// Baca dari data-* attributes — aman dari bug HTML encoding JSON di onclick
document.querySelectorAll('.btn-update-bayar').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('m_user_id').value    = btn.dataset.uid;
        document.getElementById('m_biaya_id').value   = btn.dataset.bid;
        document.getElementById('m_nama').value       = btn.dataset.nama + ' — Kamar ' + btn.dataset.kamar;
        document.getElementById('m_status').value     = btn.dataset.status || 'belum';
        document.getElementById('m_tgl_bayar').value  = btn.dataset.tgl   || '<?= date('Y-m-d') ?>';
        document.getElementById('m_keterangan').value = btn.dataset.ket   || '';
        toggleTglBayar(btn.dataset.status || 'belum');
    });
});

function toggleTglBayar(val) {
    document.getElementById('grpTglBayar').style.display = val === 'lunas' ? 'block' : 'none';
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
