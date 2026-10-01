<?php
// Monitor kehadiran guru & wali kelas — untuk superadmin/admin dan kepala sekolah.
// Hanya melihat; pengaturan jam hanya bisa diubah oleh admin.
date_default_timezone_set('Asia/Makassar');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/libur.php';

const GM_PEMANTAU = ['admin', 'kepala_sekolah'];   // boleh melihat
const GM_PENGATUR = ['admin'];                      // boleh mengubah jam absen

$user = currentUser();
$pageTitle = 'Kehadiran Guru';
requireRole(GM_PEMANTAU);

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

$bisaAtur = in_array($user['role'], GM_PENGATUR, true);

function gmCsrfToken() {
    if (empty($_SESSION['gm_csrf'])) { $_SESSION['gm_csrf'] = bin2hex(random_bytes(16)); }
    return $_SESSION['gm_csrf'];
}
function gmCsrfValid($t) {
    return !empty($_SESSION['gm_csrf']) && is_string($t) && hash_equals($_SESSION['gm_csrf'], $t);
}
function gmWaktu($s) { return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string)$s) ? $s . ':00' : null; }
function gmTanggal($s) {
    $d = DateTime::createFromFormat('Y-m-d', (string)$s);
    return ($d && $d->format('Y-m-d') === $s) ? $s : null;
}

$hariIni = date('Y-m-d');

/* ================= Cek migrasi ================= */
try {
    $pdo->query("SELECT jam_jadwal, terlambat_menit FROM absensi_guru LIMIT 1");
    $pdo->query("SELECT toleransi_menit FROM pengaturan_absen_guru LIMIT 1");
} catch (PDOException $e) {
    include __DIR__ . '/../includes/header.php';
    echo "<div class='card p-4'><h5 class='fw-bold'><i class='bi bi-exclamation-triangle text-warning me-2'></i>Absen Guru belum aktif</h5>"
       . "<p class='mb-0'>Import file <b>guru_tambah.sql</b> lalu <b>guru_profil_jadwal.sql</b> ke database <b>db_absensi_sekolah</b> melalui phpMyAdmin, lalu refresh halaman ini.</p></div>";
    include __DIR__ . '/../includes/footer.php';
    exit;
}

$errors = [];

/* ================= Simpan pengaturan jam (admin) ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'simpan_pengaturan') {
    if (!$bisaAtur) {
        $errors[] = 'Anda tidak berhak mengubah pengaturan.';
    } elseif (!gmCsrfValid($_POST['csrf'] ?? '')) {
        $errors[] = 'Sesi formulir tidak valid. Muat ulang halaman lalu coba lagi.';
    } else {
        $f = [];
        $tolIn = $_POST['toleransi_menit'] ?? '';
        if (!ctype_digit((string)$tolIn) || (int)$tolIn > 120) { $errors[] = 'Toleransi harus berupa angka 0–120 menit.'; }
        $tolMenit = (int)$tolIn;
        foreach (['jam_masuk_mulai', 'jam_masuk_batas', 'jam_masuk_selesai', 'jam_pulang_mulai', 'jam_pulang_selesai'] as $k) {
            $f[$k] = gmWaktu($_POST[$k] ?? '');
            if ($f[$k] === null) { $errors[] = 'Format jam tidak valid.'; break; }
        }
        if (!$errors) {
            if (!($f['jam_masuk_mulai'] < $f['jam_masuk_batas'] && $f['jam_masuk_batas'] <= $f['jam_masuk_selesai'])) {
                $errors[] = 'Urutan jam masuk harus: dibuka < batas tepat waktu ≤ ditutup.';
            }
            if (!($f['jam_pulang_mulai'] < $f['jam_pulang_selesai'])) {
                $errors[] = 'Jam pulang dibuka harus lebih awal dari jam pulang ditutup.';
            }
            if (!$errors && !($f['jam_masuk_selesai'] <= $f['jam_pulang_mulai'])) {
                $errors[] = 'Jam masuk ditutup tidak boleh melewati jam pulang dibuka.';
            }
        }
        if (!$errors) {
            $row = $pdo->query("SELECT id FROM pengaturan_absen_guru ORDER BY id LIMIT 1")->fetch();
            if ($row) {
                $stmt = $pdo->prepare("UPDATE pengaturan_absen_guru SET jam_masuk_mulai=?, jam_masuk_batas=?, toleransi_menit=?, jam_masuk_selesai=?, jam_pulang_mulai=?, jam_pulang_selesai=? WHERE id=?");
                $stmt->execute([$f['jam_masuk_mulai'], $f['jam_masuk_batas'], $tolMenit, $f['jam_masuk_selesai'], $f['jam_pulang_mulai'], $f['jam_pulang_selesai'], $row['id']]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO pengaturan_absen_guru (jam_masuk_mulai, jam_masuk_batas, toleransi_menit, jam_masuk_selesai, jam_pulang_mulai, jam_pulang_selesai) VALUES (?,?,?,?,?,?)");
                $stmt->execute([$f['jam_masuk_mulai'], $f['jam_masuk_batas'], $tolMenit, $f['jam_masuk_selesai'], $f['jam_pulang_mulai'], $f['jam_pulang_selesai']]);
            }
            setFlash('success', 'Pengaturan jam absen guru disimpan.');
            redirect('absensi/guru_monitor.php');
        }
    }
}

/* ================= Parameter tampilan ================= */
$tanggal = gmTanggal($_GET['tanggal'] ?? '') ?: $hariIni;
$bulan = $_GET['bulan'] ?? date('Y-m');
if (!preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $bulan, $mb) || (int)$mb[1] < 2000 || (int)$mb[1] > 2100) { $bulan = date('Y-m'); }

$set = $pdo->query("SELECT * FROM pengaturan_absen_guru ORDER BY id LIMIT 1")->fetch() ?: [
    'jam_masuk_mulai' => '05:30:00', 'jam_masuk_batas' => '07:30:00', 'jam_masuk_selesai' => '10:00:00',
    'jam_pulang_mulai' => '14:00:00', 'jam_pulang_selesai' => '20:00:00', 'toleransi_menit' => 10,
];

$pegawai = $pdo->query("SELECT id, nama, role FROM users WHERE role IN ('guru','wali_kelas') ORDER BY nama")->fetchAll();
$labelRole = ['guru' => 'Guru', 'wali_kelas' => 'Wali Kelas'];

/* ---------- Harian ---------- */
$stmt = $pdo->prepare("SELECT * FROM absensi_guru WHERE tanggal = ?");
$stmt->execute([$tanggal]);
$absenHarian = [];
foreach ($stmt->fetchAll() as $r) { $absenHarian[$r['user_id']] = $r; }

$infoLiburTgl = cekHariLibur($pdo, $tanggal);
$sum = ['total' => count($pegawai), 'hadir' => 0, 'tepat' => 0, 'terlambat' => 0, 'belum' => 0];
foreach ($pegawai as $p) {
    $a = $absenHarian[$p['id']] ?? null;
    if ($a && !empty($a['jam_masuk'])) {
        $sum['hadir']++;
        if (!empty($a['terlambat'])) { $sum['terlambat']++; } else { $sum['tepat']++; }
    }
}
$sum['belum'] = $sum['total'] - $sum['hadir'];

/* ---------- Rekap bulanan ---------- */
$awalBulan = $bulan . '-01';
$akhirBulan = date('Y-m-t', strtotime($awalBulan));
$batasHitung = min($akhirBulan, $hariIni);   // hari yang belum terjadi tidak dihitung

$stmt = $pdo->prepare("SELECT tanggal FROM hari_libur WHERE tanggal BETWEEN ? AND ?");
$stmt->execute([$awalBulan, $akhirBulan]);
$liburBulan = array_flip($stmt->fetchAll(PDO::FETCH_COLUMN));

$hariSekolah = [];
for ($d = new DateTime($awalBulan); $d->format('Y-m-d') <= $batasHitung; $d->modify('+1 day')) {
    $t = $d->format('Y-m-d');
    if ((int)$d->format('N') < 6 && !isset($liburBulan[$t])) { $hariSekolah[$t] = true; }
}
$jumlahHariSekolah = $bulan > date('Y-m') ? 0 : count($hariSekolah);

$stmt = $pdo->prepare("SELECT user_id, tanggal, terlambat, jam_masuk FROM absensi_guru WHERE tanggal BETWEEN ? AND ? AND jam_masuk IS NOT NULL");
$stmt->execute([$awalBulan, $akhirBulan]);
$rekap = [];
foreach ($stmt->fetchAll() as $r) {
    if (!isset($hariSekolah[$r['tanggal']])) { continue; }   // absen di hari non-sekolah tidak dihitung
    $rekap[$r['user_id']]['hadir'] = ($rekap[$r['user_id']]['hadir'] ?? 0) + 1;
    $rekap[$r['user_id']]['terlambat'] = ($rekap[$r['user_id']]['terlambat'] ?? 0) + (int)$r['terlambat'];
}

$namaBulanIndo = [1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
$judulBulan = $namaBulanIndo[(int)date('n', strtotime($awalBulan))] . ' ' . date('Y', strtotime($awalBulan));
$csrf = gmCsrfToken();

include __DIR__ . '/../includes/header.php';
?>

<style>
.gm-stat{ background:#fff; border:1px solid #E5E7EE; border-radius:12px; padding:14px 16px; height:100%; border-left:4px solid var(--c,#131A2E); }
.gm-stat .l{ font-size:.68rem; font-weight:800; letter-spacing:.06em; text-transform:uppercase; color:#6B7280; }
.gm-stat .n{ font-size:1.8rem; font-weight:800; line-height:1.15; }
.gm-pct{ min-width:120px; }
</style>

<h4 class="fw-bold mb-3"><i class="bi bi-person-check-fill me-2"></i>Kehadiran Guru</h4>

<?php if ($errors): ?>
  <div class="alert alert-danger">
    <?php foreach ($errors as $er): ?><div><i class="bi bi-exclamation-circle-fill me-1"></i><?= clean($er) ?></div><?php endforeach; ?>
  </div>
<?php endif; ?>

<!-- ===== Harian ===== -->
<div class="card p-3 mb-3">
  <form method="GET" class="row g-2 align-items-end">
    <input type="hidden" name="bulan" value="<?= clean($bulan) ?>">
    <div class="col-md-4">
      <label class="form-label small">Tanggal</label>
      <input type="date" name="tanggal" class="form-control" value="<?= clean($tanggal) ?>" max="<?= $hariIni ?>" onchange="this.form.submit()">
    </div>
    <div class="col-md-8 small text-muted">
      <?php if ($infoLiburTgl['libur']): ?><i class="bi bi-calendar-x-fill text-warning me-1"></i>Tanggal ini libur — <?= clean($infoLiburTgl['keterangan']) ?>.<?php endif; ?>
    </div>
  </form>
</div>

<div class="row g-3 mb-3">
  <div class="col-6 col-lg-3"><div class="gm-stat" style="--c:#131A2E"><div class="l">Total Guru &amp; Wali Kelas</div><div class="n"><?= $sum['total'] ?></div></div></div>
  <div class="col-6 col-lg-3"><div class="gm-stat" style="--c:#16A34A"><div class="l">Hadir Tepat Waktu</div><div class="n"><?= $sum['tepat'] ?></div></div></div>
  <div class="col-6 col-lg-3"><div class="gm-stat" style="--c:#EA7A1B"><div class="l">Terlambat</div><div class="n"><?= $sum['terlambat'] ?></div></div></div>
  <div class="col-6 col-lg-3"><div class="gm-stat" style="--c:#DC2626"><div class="l"><?= $infoLiburTgl['libur'] ? 'Libur' : 'Belum / Tidak Absen' ?></div><div class="n"><?= $infoLiburTgl['libur'] ? '-' : $sum['belum'] ?></div></div></div>
</div>

<div class="card p-3 mb-4">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light"><tr><th>#</th><th>Nama</th><th>Peran</th><th>Jadwal Pertama</th><th>Masuk</th><th>Pulang</th><th>Jarak</th><th>Status</th></tr></thead>
      <tbody>
        <?php if (empty($pegawai)): ?>
          <tr><td colspan="8" class="text-center text-muted py-4">Belum ada akun guru / wali kelas.</td></tr>
        <?php endif; ?>
        <?php foreach ($pegawai as $i => $p): $a = $absenHarian[$p['id']] ?? null; $hadir = $a && !empty($a['jam_masuk']); ?>
          <tr>
            <td><?= $i + 1 ?></td>
            <td class="fw-semibold"><?= clean($p['nama']) ?></td>
            <td class="text-muted small"><?= clean($labelRole[$p['role']] ?? $p['role']) ?></td>
            <td class="small text-muted"><?= ($a && !empty($a['jam_jadwal'])) ? substr($a['jam_jadwal'], 0, 5) : '-' ?></td>
            <td><?= $hadir ? substr($a['jam_masuk'], 0, 5) : '-' ?></td>
            <td><?= ($a && !empty($a['jam_pulang'])) ? substr($a['jam_pulang'], 0, 5) : '-' ?></td>
            <td class="small text-muted"><?= $hadir ? (int)$a['jarak_masuk'] . ' m' : '-' ?></td>
            <td>
              <?php if ($hadir): ?>
                <?= !empty($a['terlambat']) ? '<span class="badge bg-warning text-dark">Terlambat ' . (int)$a['terlambat_menit'] . ' mnt</span>' : '<span class="badge bg-success">Tepat waktu</span>' ?>
              <?php elseif ($infoLiburTgl['libur']): ?>
                <span class="badge bg-secondary">Libur</span>
              <?php elseif ($tanggal > $hariIni): ?>
                <span class="text-muted">-</span>
              <?php elseif ($tanggal === $hariIni): ?>
                <span class="badge bg-light text-dark border">Belum absen</span>
              <?php else: ?>
                <span class="badge bg-danger">Tidak absen</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ===== Rekap bulanan ===== -->
<div class="d-flex justify-content-between align-items-end flex-wrap gap-2 mb-2">
  <h5 class="fw-bold mb-0"><i class="bi bi-bar-chart-fill me-2"></i>Rekap <?= clean($judulBulan) ?></h5>
  <form method="GET" class="d-flex gap-2 align-items-center">
    <input type="hidden" name="tanggal" value="<?= clean($tanggal) ?>">
    <input type="month" name="bulan" class="form-control form-control-sm" value="<?= clean($bulan) ?>" max="<?= date('Y-m') ?>" onchange="this.form.submit()">
  </form>
</div>
<div class="text-muted small mb-2">Dihitung dari <b><?= $jumlahHariSekolah ?></b> hari sekolah (Senin–Jumat, tanpa tanggal libur) sampai hari ini.</div>
<div class="card p-3 mb-4">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light"><tr><th>#</th><th>Nama</th><th class="text-center">Hadir</th><th class="text-center">Terlambat</th><th class="text-center">Tidak Absen</th><th class="gm-pct">Kehadiran</th></tr></thead>
      <tbody>
        <?php foreach ($pegawai as $i => $p):
            $h = (int)($rekap[$p['id']]['hadir'] ?? 0);
            $tl = (int)($rekap[$p['id']]['terlambat'] ?? 0);
            $tidak = max(0, $jumlahHariSekolah - $h);
            $pct = $jumlahHariSekolah > 0 ? round($h / $jumlahHariSekolah * 100) : 0;
        ?>
          <tr>
            <td><?= $i + 1 ?></td>
            <td class="fw-semibold"><?= clean($p['nama']) ?></td>
            <td class="text-center"><?= $h ?></td>
            <td class="text-center"><?= $tl ?></td>
            <td class="text-center"><?= $tidak ?></td>
            <td>
              <div class="progress" style="height:8px;"><div class="progress-bar <?= $pct >= 90 ? 'bg-success' : ($pct >= 75 ? 'bg-warning' : 'bg-danger') ?>" style="width:<?= $pct ?>%"></div></div>
              <div class="small text-muted"><?= $pct ?>%</div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($pegawai)): ?><tr><td colspan="6" class="text-center text-muted py-4">Belum ada data.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ===== Pengaturan jam ===== -->
<h5 class="fw-bold mb-2"><i class="bi bi-gear-fill me-2"></i>Pengaturan Jam Absen Guru</h5>
<div class="card p-3">
  <?php if (!$bisaAtur): ?>
    <div class="small text-muted mb-2"><i class="bi bi-eye-fill me-1"></i>Mode lihat saja. Pengaturan hanya bisa diubah oleh admin.</div>
  <?php endif; ?>
  <form method="POST">
    <input type="hidden" name="csrf" value="<?= clean($csrf) ?>">
    <input type="hidden" name="aksi" value="simpan_pengaturan">
    <div class="row g-3">
      <div class="col-6 col-md"><label class="form-label small">Masuk dibuka</label><input type="time" name="jam_masuk_mulai" class="form-control" value="<?= substr($set['jam_masuk_mulai'], 0, 5) ?>" <?= $bisaAtur ? 'required' : 'disabled' ?>></div>
      <div class="col-6 col-md"><label class="form-label small">Batas tepat waktu <span class="text-muted">(tanpa jadwal)</span></label><input type="time" name="jam_masuk_batas" class="form-control" value="<?= substr($set['jam_masuk_batas'], 0, 5) ?>" <?= $bisaAtur ? 'required' : 'disabled' ?>></div>
      <div class="col-6 col-md"><label class="form-label small">Toleransi (menit)</label><input type="number" name="toleransi_menit" min="0" max="120" class="form-control" value="<?= (int)($set['toleransi_menit'] ?? 10) ?>" <?= $bisaAtur ? 'required' : 'disabled' ?>></div>
      <div class="col-6 col-md"><label class="form-label small">Masuk ditutup</label><input type="time" name="jam_masuk_selesai" class="form-control" value="<?= substr($set['jam_masuk_selesai'], 0, 5) ?>" <?= $bisaAtur ? 'required' : 'disabled' ?>></div>
      <div class="col-6 col-md"><label class="form-label small">Pulang dibuka</label><input type="time" name="jam_pulang_mulai" class="form-control" value="<?= substr($set['jam_pulang_mulai'], 0, 5) ?>" <?= $bisaAtur ? 'required' : 'disabled' ?>></div>
      <div class="col-6 col-md"><label class="form-label small">Pulang ditutup</label><input type="time" name="jam_pulang_selesai" class="form-control" value="<?= substr($set['jam_pulang_selesai'], 0, 5) ?>" <?= $bisaAtur ? 'required' : 'disabled' ?>></div>
    </div>
    <?php if ($bisaAtur): ?><button class="btn btn-primary mt-3"><i class="bi bi-save"></i> Simpan Pengaturan</button><?php endif; ?>
    <div class="form-text mt-2">Jam standar ini hanya dipakai pada hari <b>tanpa jadwal mengajar</b>. Bila guru punya jadwal, batas tepat waktu = jam pelajaran pertama + toleransi, dan absen pulang dibuka setelah pelajaran terakhir selesai. Lokasi dan radius memakai menu Lokasi &amp; Radius GPS.</div>
  </form>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>