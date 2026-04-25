<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$db  = getDB();
$msg = $msgType = '';

// Handle: kirim notifikasi manual / pengingat
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Kirim pengingat ke semua yang belum bayar bulan ini
    if ($action === 'reminder_belum_bayar') {
        $bulan = (int)date('n');
        $tahun = (int)date('Y');

        $stmtBiaya = $db->prepare("SELECT id, biaya_per_orang FROM biaya_bulanan WHERE bulan=? AND tahun=? LIMIT 1");
        $stmtBiaya->execute([$bulan, $tahun]);
        $biaya = $stmtBiaya->fetch();

        if (!$biaya) {
            $msg = 'Biaya bulan ini belum diinput. Input dulu sebelum kirim pengingat.';
            $msgType = 'warning';
        } else {
            $stmtBelum = $db->prepare(
                "SELECT u.id FROM users u
                 LEFT JOIN pembayaran p ON p.user_id=u.id AND p.biaya_id=?
                 WHERE u.role='penghuni' AND u.status='aktif'
                 AND (p.status IS NULL OR p.status='belum')"
            );
            $stmtBelum->execute([$biaya['id']]);
            $belumBayar = $stmtBelum->fetchAll();

            if (empty($belumBayar)) {
                $msg = 'Semua penghuni sudah membayar bulan ini! 🎉';
                $msgType = 'success';
            } else {
                $insNotif = $db->prepare(
                    "INSERT INTO notifikasi (user_id, judul, pesan) VALUES (?,?,?)"
                );
                $judul = "🔔 Pengingat Iuran " . namaBulan($bulan) . " $tahun";
                $pesan = "Halo! Ini adalah pengingat bahwa iuran kontrakan bulan " . namaBulan($bulan) . " $tahun sebesar " . formatRupiah($biaya['biaya_per_orang']) . " belum dibayarkan.\n\nMohon segera lakukan pembayaran. Terima kasih!";
                foreach ($belumBayar as $u) {
                    $insNotif->execute([$u['id'], $judul, $pesan]);
                }
                $msg = 'Pengingat berhasil dikirim ke ' . count($belumBayar) . ' penghuni!';
                $msgType = 'success';
            }
        }
    }

    // Kirim notifikasi custom
    if ($action === 'send_custom') {
        $judul     = trim($_POST['judul'] ?? '');
        $pesan     = trim($_POST['pesan'] ?? '');
        $targetIds = $_POST['target'] ?? 'all';

        if (empty($judul) || empty($pesan)) {
            $msg = 'Judul dan pesan wajib diisi.'; $msgType = 'danger';
        } else {
            $insNotif = $db->prepare("INSERT INTO notifikasi (user_id, judul, pesan) VALUES (?,?,?)");

            if ($targetIds === 'all') {
                $penghuni = $db->query(
                    "SELECT id FROM users WHERE role='penghuni' AND status='aktif'"
                )->fetchAll();
                foreach ($penghuni as $u) $insNotif->execute([$u['id'], $judul, $pesan]);
                $msg = 'Notifikasi berhasil dikirim ke semua penghuni aktif!';
            } else {
                $ids = array_filter(array_map('intval', (array)$targetIds));
                foreach ($ids as $uid) $insNotif->execute([$uid, $judul, $pesan]);
                $msg = 'Notifikasi berhasil dikirim ke ' . count($ids) . ' penghuni!';
            }
            $msgType = 'success';
        }
    }
}

// Daftar penghuni aktif
$penghuniAktif = $db->query(
    "SELECT id, nama, nomor_kamar FROM users WHERE role='penghuni' AND status='aktif' ORDER BY nomor_kamar"
)->fetchAll();

// Riwayat notifikasi yang dikirim admin (20 terbaru)
$riwayat = $db->query(
    "SELECT n.*, u.nama as ke_penghuni, u.nomor_kamar
     FROM notifikasi n
     JOIN users u ON n.user_id = u.id
     ORDER BY n.created_at DESC
     LIMIT 30"
)->fetchAll();

// Status pembayaran bulan ini (untuk quick info)
$bulan = (int)date('n'); $tahun = (int)date('Y');
$stmtBiaya = $db->prepare("SELECT id, biaya_per_orang FROM biaya_bulanan WHERE bulan=? AND tahun=? LIMIT 1");
$stmtBiaya->execute([$bulan, $tahun]);
$biayaIni = $stmtBiaya->fetch();

$jmlBelumBayar = 0;
if ($biayaIni) {
    $sB = $db->prepare(
        "SELECT COUNT(*) FROM users u
         LEFT JOIN pembayaran p ON p.user_id=u.id AND p.biaya_id=?
         WHERE u.role='penghuni' AND u.status='aktif'
         AND (p.status IS NULL OR p.status='belum')"
    );
    $sB->execute([$biayaIni['id']]);
    $jmlBelumBayar = (int)$sB->fetchColumn();
}

$pageTitle    = 'Kirim Notifikasi';
$pageSubtitle = 'Pengingat iuran & pesan ke penghuni';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header fade-in">
    <div>
        <h1>Manajemen Notifikasi</h1>
        <p>Kirim pengingat iuran dan pesan ke penghuni</p>
    </div>
</div>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType ?> fade-in" data-dismiss="5000">
    <i class="fa-solid <?= $msgType === 'success' ? 'fa-circle-check' : ($msgType === 'warning' ? 'fa-triangle-exclamation' : 'fa-circle-xmark') ?>"></i>
    <?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<!-- Quick Action: Pengingat Otomatis -->
<div class="card fade-in mb-20" style="background:rgba(245,158,11,0.07);border-color:rgba(245,158,11,0.3)">
    <div class="card-header">
        <div>
            <h3><i class="fa-solid fa-bell" style="color:var(--warning)"></i> Pengingat Iuran Cepat</h3>
            <p class="text-muted fs-12" style="margin-top:4px">Kirim pengingat otomatis ke semua penghuni yang belum bayar bulan ini</p>
        </div>
    </div>
    <div class="d-flex align-center justify-between flex-wrap gap-12" style="margin-top:12px">
        <div>
            <?php if ($biayaIni): ?>
            <p class="fs-13">
                Bulan <strong><?= namaBulan($bulan) . ' ' . $tahun ?></strong> —
                <?php if ($jmlBelumBayar > 0): ?>
                <span class="text-danger fw-600"><?= $jmlBelumBayar ?> penghuni belum bayar</span>
                <?php else: ?>
                <span class="text-success fw-600">Semua sudah lunas! 🎉</span>
                <?php endif; ?>
            </p>
            <?php else: ?>
            <p class="text-muted fs-13">Biaya bulan ini belum diinput. <a href="biaya.php" class="text-accent">Input biaya dulu →</a></p>
            <?php endif; ?>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="reminder_belum_bayar">
            <button type="submit" class="btn btn-warning"
                <?= (!$biayaIni || $jmlBelumBayar === 0) ? 'disabled' : '' ?>>
                <i class="fa-solid fa-paper-plane"></i>
                Kirim Pengingat ke <?= $jmlBelumBayar ?> Penghuni
            </button>
        </form>
    </div>
</div>

<!-- Form Notifikasi Custom -->
<div class="grid-2 fade-in">
    <div class="card">
        <div class="card-header">
            <h3><i class="fa-solid fa-message" style="color:var(--accent-primary)"></i> Kirim Pesan Custom</h3>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="send_custom">
            <div class="form-group">
                <label>Tujuan Penerima</label>
                <select name="target" id="targetSelect" class="form-control" onchange="toggleTargetList(this.value)">
                    <option value="all">📢 Semua Penghuni Aktif</option>
                    <option value="specific">👤 Penghuni Tertentu</option>
                </select>
            </div>
            <div id="targetList" style="display:none">
                <div class="form-group">
                    <label>Pilih Penghuni (bisa lebih dari satu)</label>
                    <div style="background:var(--bg-input);border:1px solid var(--border);border-radius:var(--radius-sm);padding:12px;max-height:200px;overflow-y:auto">
                        <?php foreach ($penghuniAktif as $p): ?>
                        <label style="display:flex;align-items:center;gap:10px;padding:6px 0;cursor:pointer;font-size:13px">
                            <input type="checkbox" name="target[]" value="<?= $p['id'] ?>" style="accent-color:var(--accent-primary)">
                            <span><?= htmlspecialchars($p['nama']) ?> <span class="text-muted">(Kamar <?= htmlspecialchars($p['nomor_kamar']) ?>)</span></span>
                        </label>
                        <?php endforeach; ?>
                        <?php if (empty($penghuniAktif)): ?>
                        <p class="text-muted fs-12">Tidak ada penghuni aktif</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="form-group">
                <label>Judul Notifikasi *</label>
                <input type="text" name="judul" class="form-control" placeholder="Contoh: Pengumuman Penting" required>
            </div>
            <div class="form-group">
                <label>Isi Pesan *</label>
                <textarea name="pesan" class="form-control" rows="5" placeholder="Tulis pesan Anda di sini..." required
                    style="resize:vertical;line-height:1.6"></textarea>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-paper-plane"></i> Kirim Notifikasi
            </button>
        </form>
    </div>

    <!-- Riwayat Notifikasi -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fa-solid fa-clock-rotate-left" style="color:var(--info)"></i> Riwayat Terkirim</h3>
        </div>
        <?php if (empty($riwayat)): ?>
        <div class="empty-state">
            <i class="fa-regular fa-paper-plane"></i>
            <p>Belum ada notifikasi yang dikirim</p>
        </div>
        <?php else: ?>
        <div style="max-height:480px;overflow-y:auto">
            <?php foreach ($riwayat as $n): ?>
            <div style="padding:12px 0;border-bottom:1px solid var(--border)">
                <div class="d-flex justify-between align-center" style="margin-bottom:4px">
                    <span class="fw-600 fs-13"><?= htmlspecialchars($n['judul']) ?></span>
                    <?php if ($n['is_read']): ?>
                    <span class="badge badge-muted" style="font-size:10px">Dibaca</span>
                    <?php else: ?>
                    <span class="badge badge-info" style="font-size:10px">Belum dibaca</span>
                    <?php endif; ?>
                </div>
                <p class="text-muted fs-12" style="margin-bottom:6px">
                    <?= htmlspecialchars(mb_strimwidth($n['pesan'], 0, 80, '...')) ?>
                </p>
                <div class="d-flex justify-between align-center">
                    <span class="chip" style="font-size:11px">
                        <?= htmlspecialchars($n['ke_penghuni']) ?> — Kamar <?= htmlspecialchars($n['nomor_kamar']) ?>
                    </span>
                    <span class="text-muted fs-12"><?= date('d M, H:i', strtotime($n['created_at'])) ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleTargetList(val) {
    const el = document.getElementById('targetList');
    const sel = document.getElementById('targetSelect');
    if (val === 'specific') {
        el.style.display = 'block';
        // Override the form's target name
        document.querySelector('select[name="target"]').name = '_target_type';
    } else {
        el.style.display = 'none';
        document.querySelector('select[name="_target_type"], select[name="target"]').name = 'target';
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
