<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$db  = getDB();
$msg = $msgType = '';

// Hitung jumlah penghuni aktif
$totalPenghuni = (int)$db->query(
    "SELECT COUNT(*) FROM users WHERE role='penghuni' AND status='aktif'"
)->fetchColumn();

// Handle POST: input biaya baru
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_biaya') {
    $bulan   = (int)$_POST['bulan'];
    $tahun   = (int)$_POST['tahun'];
    $sewa    = (float)str_replace(['.', ','], ['', '.'], $_POST['total_sewa']    ?? 0);
    $listrik = (float)str_replace(['.', ','], ['', '.'], $_POST['total_listrik'] ?? 0);
    $air     = (float)str_replace(['.', ','], ['', '.'], $_POST['total_air']     ?? 0);
    $wifi    = (float)str_replace(['.', ','], ['', '.'], $_POST['total_wifi']    ?? 0);
    $lain    = (float)str_replace(['.', ','], ['', '.'], $_POST['total_lain']    ?? 0);
    $jmlPenghuni = $totalPenghuni > 0 ? $totalPenghuni : 1;

    if ($bulan < 1 || $bulan > 12 || $tahun < 2020) {
        $msg = 'Periode tidak valid.'; $msgType = 'danger';
    } else {
        $totalSemua   = $sewa + $listrik + $air + $wifi + $lain;
        $biayaPerOrang = $jmlPenghuni > 0 ? round($totalSemua / $jmlPenghuni) : 0;

        try {
            // Cek apakah sudah ada
            $cek = $db->prepare("SELECT id FROM biaya_bulanan WHERE bulan=? AND tahun=?");
            $cek->execute([$bulan, $tahun]);
            $existing = $cek->fetch();

            if ($existing) {
                // Update
                $db->prepare(
                    "UPDATE biaya_bulanan SET total_sewa=?,total_listrik=?,total_air=?,total_wifi=?,total_lain=?,
                     jumlah_penghuni=?,biaya_per_orang=?,created_by=? WHERE bulan=? AND tahun=?"
                )->execute([$sewa,$listrik,$air,$wifi,$lain,$jmlPenghuni,$biayaPerOrang,currentUserId(),$bulan,$tahun]);
                $biayaId = $existing['id'];
                $msg = 'Biaya berhasil diperbarui!';
            } else {
                // Insert
                $ins = $db->prepare(
                    "INSERT INTO biaya_bulanan (bulan,tahun,total_sewa,total_listrik,total_air,total_wifi,total_lain,jumlah_penghuni,biaya_per_orang,created_by)
                     VALUES (?,?,?,?,?,?,?,?,?,?)"
                );
                $ins->execute([$bulan,$tahun,$sewa,$listrik,$air,$wifi,$lain,$jmlPenghuni,$biayaPerOrang,currentUserId()]);
                $biayaId = (int)$db->lastInsertId();
                $msg = 'Biaya berhasil disimpan!';

                // Auto-buat record pembayaran untuk semua penghuni aktif
                $penghuni = $db->query("SELECT id FROM users WHERE role='penghuni' AND status='aktif'")->fetchAll();
                $insPay   = $db->prepare(
                    "INSERT IGNORE INTO pembayaran (user_id, biaya_id, status) VALUES (?,?,'belum')"
                );
                foreach ($penghuni as $p) $insPay->execute([$p['id'], $biayaId]);
            }
            $msgType = 'success';
        } catch (Exception $e) {
            $msg = 'Error: ' . $e->getMessage(); $msgType = 'danger';
        }
    }
}

// Daftar biaya
$biayaList = $db->query(
    "SELECT * FROM biaya_bulanan ORDER BY tahun DESC, bulan DESC"
)->fetchAll();

$pageTitle    = 'Input Biaya Bulanan';
$pageSubtitle = 'Hitung & simpan tagihan kontrakan';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header fade-in">
    <div>
        <h1>Input Biaya Bulanan</h1>
        <p>Masukkan total biaya dan sistem akan otomatis membagi per penghuni</p>
    </div>
    <button class="btn btn-primary" data-modal-open="modalBiaya">
        <i class="fa-solid fa-plus"></i> Input Biaya Baru
    </button>
</div>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType ?> fade-in" data-dismiss="5000">
    <i class="fa-solid <?= $msgType === 'success' ? 'fa-circle-check' : 'fa-circle-xmark' ?>"></i>
    <?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<!-- Info Penghuni -->
<div class="card fade-in mb-20" style="background:rgba(124,111,239,0.08);border-color:var(--border-accent)">
    <div class="d-flex align-center gap-12">
        <div style="font-size:32px">ℹ️</div>
        <div>
            <div class="fw-600">Penghuni Aktif Saat Ini: <span style="color:var(--accent-primary)"><?= $totalPenghuni ?> orang</span></div>
            <div class="text-muted fs-12">Biaya akan dibagi rata ke seluruh penghuni yang statusnya aktif saat biaya diinput.</div>
        </div>
    </div>
</div>

<!-- Riwayat Biaya -->
<div class="card fade-in">
    <div class="card-header">
        <h3>Riwayat Biaya Bulanan</h3>
    </div>
    <?php if (empty($biayaList)): ?>
    <div class="empty-state">
        <i class="fa-solid fa-file-invoice-dollar"></i>
        <p>Belum ada data biaya. Klik "Input Biaya Baru" untuk memulai.</p>
    </div>
    <?php else: ?>
    <div class="table-wrapper">
        <table class="table">
            <thead>
                <tr>
                    <th>Periode</th>
                    <th>Sewa</th>
                    <th>Listrik</th>
                    <th>Air</th>
                    <th>WiFi</th>
                    <th>Lainnya</th>
                    <th>Jml Penghuni</th>
                    <th class="text-right">Biaya/Orang</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($biayaList as $b):
                    $total = $b['total_sewa']+$b['total_listrik']+$b['total_air']+$b['total_wifi']+$b['total_lain'];
                ?>
                <tr>
                    <td class="fw-600"><?= namaBulan($b['bulan']) . ' ' . $b['tahun'] ?></td>
                    <td><?= formatRupiah($b['total_sewa']) ?></td>
                    <td><?= formatRupiah($b['total_listrik']) ?></td>
                    <td><?= formatRupiah($b['total_air']) ?></td>
                    <td><?= formatRupiah($b['total_wifi']) ?></td>
                    <td><?= formatRupiah($b['total_lain']) ?></td>
                    <td><?= $b['jumlah_penghuni'] ?> orang</td>
                    <td class="text-right fw-700 text-accent"><?= formatRupiah($b['biaya_per_orang']) ?></td>
                    <td>
                        <button class="btn btn-sm btn-secondary"
                            data-modal-open="modalBiaya"
                            onclick="fillEditForm(<?= htmlspecialchars(json_encode($b)) ?>)">
                            <i class="fa-solid fa-pen"></i> Edit
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Modal Input Biaya -->
<div class="modal-overlay" id="modalBiaya">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fa-solid fa-calculator" style="color:var(--accent-primary)"></i> Input Biaya Bulanan</h3>
            <button class="modal-close"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST" id="formBiaya">
            <input type="hidden" name="action" value="save_biaya">

            <div class="form-row">
                <div class="form-group">
                    <label>Bulan *</label>
                    <select name="bulan" id="inp_bulan" class="form-control" required>
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?= $m ?>" <?= $m == date('n') ? 'selected' : '' ?>><?= namaBulan($m) ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Tahun *</label>
                    <select name="tahun" id="inp_tahun" class="form-control" required>
                        <?php for ($y = date('Y'); $y >= 2020; $y--): ?>
                        <option value="<?= $y ?>"><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Total Sewa (Rp)</label>
                    <div class="input-group">
                        <i class="fa-solid fa-house input-icon"></i>
                        <input type="number" name="total_sewa" id="inp_sewa" class="form-control" placeholder="0" min="0" oninput="hitungTotal()">
                    </div>
                </div>
                <div class="form-group">
                    <label>Total Listrik (Rp)</label>
                    <div class="input-group">
                        <i class="fa-solid fa-bolt input-icon"></i>
                        <input type="number" name="total_listrik" id="inp_listrik" class="form-control" placeholder="0" min="0" oninput="hitungTotal()">
                    </div>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Total Air (Rp)</label>
                    <div class="input-group">
                        <i class="fa-solid fa-droplet input-icon"></i>
                        <input type="number" name="total_air" id="inp_air" class="form-control" placeholder="0" min="0" oninput="hitungTotal()">
                    </div>
                </div>
                <div class="form-group">
                    <label>Total WiFi (Rp)</label>
                    <div class="input-group">
                        <i class="fa-solid fa-wifi input-icon"></i>
                        <input type="number" name="total_wifi" id="inp_wifi" class="form-control" placeholder="0" min="0" oninput="hitungTotal()">
                    </div>
                </div>
            </div>
            <div class="form-group">
                <label>Biaya Lainnya (Rp)</label>
                <div class="input-group">
                    <i class="fa-solid fa-ellipsis input-icon"></i>
                    <input type="number" name="total_lain" id="inp_lain" class="form-control" placeholder="0" min="0" oninput="hitungTotal()">
                </div>
            </div>

            <!-- Preview Perhitungan -->
            <div id="previewCalc" style="background:rgba(124,111,239,0.1);border:1px solid var(--border-accent);border-radius:var(--radius-sm);padding:16px;margin-bottom:16px;display:none">
                <div class="d-flex justify-between align-center">
                    <span class="text-muted fs-13">Total Semua Biaya</span>
                    <span class="fw-600" id="totalSemua">Rp 0</span>
                </div>
                <div class="d-flex justify-between align-center" style="margin-top:8px">
                    <span class="text-muted fs-13">Dibagi <?= $totalPenghuni ?> penghuni</span>
                    <span></span>
                </div>
                <div class="divider" style="margin:10px 0"></div>
                <div class="d-flex justify-between align-center">
                    <span class="fw-700">Tagihan per Orang</span>
                    <span class="fw-800 text-accent" id="biayaPerOrang" style="font-size:20px">Rp 0</span>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-save"></i> Simpan Biaya
                </button>
                <button type="button" class="btn btn-secondary modal-close">Batal</button>
            </div>
        </form>
    </div>
</div>

<script>
const totalPenghuni = <?= $totalPenghuni ?: 1 ?>;

function hitungTotal() {
    const sewa    = parseFloat(document.getElementById('inp_sewa').value)    || 0;
    const listrik = parseFloat(document.getElementById('inp_listrik').value) || 0;
    const air     = parseFloat(document.getElementById('inp_air').value)     || 0;
    const wifi    = parseFloat(document.getElementById('inp_wifi').value)    || 0;
    const lain    = parseFloat(document.getElementById('inp_lain').value)    || 0;
    const total   = sewa + listrik + air + wifi + lain;
    const perOrang = Math.round(total / totalPenghuni);

    if (total > 0) {
        document.getElementById('previewCalc').style.display = 'block';
        document.getElementById('totalSemua').textContent   = formatRupiah(total);
        document.getElementById('biayaPerOrang').textContent = formatRupiah(perOrang);
    } else {
        document.getElementById('previewCalc').style.display = 'none';
    }
}

function fillEditForm(data) {
    document.getElementById('inp_bulan').value   = data.bulan;
    document.getElementById('inp_tahun').value   = data.tahun;
    document.getElementById('inp_sewa').value    = data.total_sewa;
    document.getElementById('inp_listrik').value = data.total_listrik;
    document.getElementById('inp_air').value     = data.total_air;
    document.getElementById('inp_wifi').value    = data.total_wifi;
    document.getElementById('inp_lain').value    = data.total_lain;
    hitungTotal();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
