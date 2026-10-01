<?php
// Absen GPS untuk guru & wali kelas (masuk + pulang).
// Lokasi & radius memakai tabel lokasi_sekolah (sama dengan absen siswa);
// jam buka/tutup & batas terlambat memakai tabel pengaturan_absen_guru.
date_default_timezone_set('Asia/Makassar');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/libur.php';

$user = currentUser();
$pageTitle = 'Absen Guru';
requireRole(['guru', 'wali_kelas']);

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

/* ================= Helper ================= */
function agCsrfToken() {
    if (empty($_SESSION['ag_csrf'])) { $_SESSION['ag_csrf'] = bin2hex(random_bytes(16)); }
    return $_SESSION['ag_csrf'];
}
function agCsrfValid($t) {
    return !empty($_SESSION['ag_csrf']) && is_string($t) && hash_equals($_SESSION['ag_csrf'], $t);
}
function agJarakMeter($lat1, $lon1, $lat2, $lon2) {
    $r = 6371000;
    $p1 = deg2rad($lat1); $p2 = deg2rad($lat2);
    $dp = deg2rad($lat2 - $lat1); $dl = deg2rad($lon2 - $lon1);
    $a = sin($dp / 2) ** 2 + cos($p1) * cos($p2) * sin($dl / 2) ** 2;
    return 2 * $r * asin(min(1, sqrt($a)));
}
function agJson($arr) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($arr);
    exit;
}

$hariIni = date('Y-m-d');
$sekarang = date('H:i:s');
$adaPost = ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'absen');

/* ================= Cek migrasi ================= */
$tabelAda = true;
try {
    $pdo->query("SELECT 1 FROM absensi_guru LIMIT 1");
    $pdo->query("SELECT 1 FROM pengaturan_absen_guru LIMIT 1");
} catch (PDOException $e) {
    $tabelAda = false;
}
if (!$tabelAda) {
    if ($adaPost) { agJson(['ok' => false, 'pesan' => 'Fitur absen guru belum aktif. Hubungi admin untuk menjalankan guru_tambah.sql.']); }
    include __DIR__ . '/../includes/header.php';
    echo "<div class='card p-4'><h5 class='fw-bold'><i class='bi bi-exclamation-triangle text-warning me-2'></i>Absen Guru belum aktif</h5>"
       . "<p class='mb-0'>Import file <b>guru_tambah.sql</b> ke database <b>db_absensi_sekolah</b> melalui phpMyAdmin, lalu refresh halaman ini.</p></div>";
    include __DIR__ . '/../includes/footer.php';
    exit;
}

/* ================= Pengaturan & lokasi ================= */
$set = $pdo->query("SELECT * FROM pengaturan_absen_guru ORDER BY id LIMIT 1")->fetch() ?: [
    'jam_masuk_mulai' => '05:30:00', 'jam_masuk_batas' => '07:30:00', 'jam_masuk_selesai' => '10:00:00',
    'jam_pulang_mulai' => '14:00:00', 'jam_pulang_selesai' => '20:00:00',
];
$lokasi = $pdo->query("SELECT * FROM lokasi_sekolah WHERE aktif = 1 ORDER BY id DESC LIMIT 1")->fetch();
$infoLibur = cekHariLibur($pdo, $hariIni);

$stmt = $pdo->prepare("SELECT * FROM absensi_guru WHERE user_id = ? AND tanggal = ?");
$stmt->execute([$user['id'], $hariIni]);
$hariIniRow = $stmt->fetch();

/* ================= Proses absen (AJAX) ================= */
if ($adaPost) {
    if (!agCsrfValid($_POST['csrf'] ?? '')) {
        agJson(['ok' => false, 'pesan' => 'Sesi tidak valid. Muat ulang halaman lalu coba lagi.']);
    }
    if ($infoLibur['libur']) {
        agJson(['ok' => false, 'pesan' => 'Hari ini libur (' . $infoLibur['keterangan'] . '), absen tidak diperlukan.']);
    }
    if (!$lokasi) {
        agJson(['ok' => false, 'pesan' => 'Lokasi sekolah belum diatur. Hubungi admin.']);
    }

    $lat = $_POST['lat'] ?? null;
    $lng = $_POST['lng'] ?? null;
    if (!is_numeric($lat) || !is_numeric($lng) || abs((float)$lat) > 90 || abs((float)$lng) > 180 || ((float)$lat == 0 && (float)$lng == 0)) {
        agJson(['ok' => false, 'pesan' => 'Koordinat GPS tidak valid. Aktifkan GPS lalu coba lagi.']);
    }
    $lat = (float)$lat;
    $lng = (float)$lng;
    $akurasi = (int)round((float)($_POST['akurasi'] ?? 0));

    $jarak = (int)round(agJarakMeter($lat, $lng, (float)$lokasi['latitude'], (float)$lokasi['longitude']));
    $radius = (int)$lokasi['radius_meter'];
    if ($jarak > $radius) {
        agJson(['ok' => false, 'pesan' => "Anda berada {$jarak} meter dari sekolah. Absen hanya bisa dalam radius {$radius} meter."]);
    }

    $jamHM = substr($sekarang, 0, 5);

    // ---- Absen MASUK ----
    if (!$hariIniRow) {
        if ($sekarang < $set['jam_masuk_mulai'] || $sekarang > $set['jam_masuk_selesai']) {
            agJson(['ok' => false, 'pesan' => 'Di luar jam absen masuk. Sekarang pukul ' . $jamHM . ', dibuka ' . substr($set['jam_masuk_mulai'], 0, 5) . '–' . substr($set['jam_masuk_selesai'], 0, 5) . '.']);
        }
        $terlambat = ($sekarang > $set['jam_masuk_batas']) ? 1 : 0;
        try {
            $stmt = $pdo->prepare("INSERT INTO absensi_guru (user_id, tanggal, jam_masuk, lat_masuk, lng_masuk, jarak_masuk, akurasi_masuk, terlambat) VALUES (?,?,?,?,?,?,?,?)");
            $stmt->execute([$user['id'], $hariIni, $sekarang, $lat, $lng, $jarak, $akurasi, $terlambat]);
        } catch (PDOException $e) {
            agJson(['ok' => false, 'pesan' => 'Absen masuk Anda sudah tercatat. Muat ulang halaman.']);
        }
        agJson([
            'ok' => true, 'tipe' => 'Masuk', 'jam' => $jamHM, 'terlambat' => (bool)$terlambat, 'jarak' => $jarak,
            'pesan' => 'Absen masuk berhasil pukul ' . $jamHM . ($terlambat ? ' (terlambat).' : ' (tepat waktu).'),
        ]);
    }

    // ---- Absen PULANG ----
    if (empty($hariIniRow['jam_pulang'])) {
        if ($sekarang < $set['jam_pulang_mulai'] || $sekarang > $set['jam_pulang_selesai']) {
            agJson(['ok' => false, 'pesan' => 'Di luar jam absen pulang. Sekarang pukul ' . $jamHM . ', dibuka ' . substr($set['jam_pulang_mulai'], 0, 5) . '–' . substr($set['jam_pulang_selesai'], 0, 5) . '.']);
        }
        $stmt = $pdo->prepare("UPDATE absensi_guru SET jam_pulang = ?, lat_pulang = ?, lng_pulang = ?, jarak_pulang = ? WHERE id = ? AND jam_pulang IS NULL");
        $stmt->execute([$sekarang, $lat, $lng, $jarak, $hariIniRow['id']]);
        agJson(['ok' => true, 'tipe' => 'Pulang', 'jam' => $jamHM, 'jarak' => $jarak, 'pesan' => 'Absen pulang berhasil pukul ' . $jamHM . '.']);
    }

    agJson(['ok' => false, 'pesan' => 'Anda sudah absen masuk dan pulang hari ini.']);
}

/* ================= Data tampilan ================= */
$bulanIni = date('Y-m');
$awalBulan = $bulanIni . '-01';
$akhirBulan = date('Y-m-t');
$stmt = $pdo->prepare("SELECT * FROM absensi_guru WHERE user_id = ? AND tanggal BETWEEN ? AND ? ORDER BY tanggal DESC");
$stmt->execute([$user['id'], $awalBulan, $akhirBulan]);
$riwayat = $stmt->fetchAll();

$jmlHadir = 0; $jmlTerlambat = 0;
foreach ($riwayat as $r) {
    if (!empty($r['jam_masuk'])) { $jmlHadir++; }
    if (!empty($r['terlambat'])) { $jmlTerlambat++; }
}

$sudahMasuk  = $hariIniRow && !empty($hariIniRow['jam_masuk']);
$sudahPulang = $hariIniRow && !empty($hariIniRow['jam_pulang']);

if ($infoLibur['libur'])      { $tombolTeks = 'Hari ini libur'; $tombolAktif = false; }
elseif ($sudahPulang)         { $tombolTeks = 'Absen hari ini selesai'; $tombolAktif = false; }
elseif ($sudahMasuk)          { $tombolTeks = 'Absen Pulang'; $tombolAktif = true; }
else                          { $tombolTeks = 'Absen Masuk'; $tombolAktif = true; }

$namaBulanIndo = [1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
$csrf = agCsrfToken();

include __DIR__ . '/../includes/header.php';
?>

<style>
.ag-card{ background:#fff; border:1px solid #E5E7EE; border-radius:12px; padding:16px 18px; height:100%; display:flex; align-items:center; gap:14px; }
.ag-card.done{ border-left:4px solid #16A34A; }
.ag-card.pending{ border-left:4px solid #EA7A1B; background:linear-gradient(90deg,#FDEEE0,#fff 40%); }
.ag-card .ag-icon{ width:44px; height:44px; border-radius:10px; background:#EDEFF5; color:#131A2E; display:flex; align-items:center; justify-content:center; font-size:1.2rem; flex-shrink:0; }
.ag-card.done .ag-icon{ background:#E7F6EC; color:#16A34A; }
.ag-card.pending .ag-icon{ background:#FDEEE0; color:#EA7A1B; }
.ag-label{ font-size:.68rem; font-weight:800; letter-spacing:.06em; text-transform:uppercase; color:#6B7280; }
.ag-time{ font-size:1.5rem; font-weight:800; color:#1C2233; line-height:1.15; }
.ag-note{ font-size:.76rem; color:#6B7280; }
.ag-btn{ width:100%; padding:14px 18px; font-weight:800; font-size:1rem; border-radius:12px; }
.ag-info{ background:#EAF2FE; color:#1E3A6E; border-radius:8px; padding:10px 16px; font-size:.8rem; }
.ag-info.ok{ background:#E7F6EC; color:#0F5A2A; }
.ag-info.err{ background:#FCEAEA; color:#8A1B1B; }
</style>

<h4 class="fw-bold mb-3"><i class="bi bi-geo-alt-fill me-2"></i>Absen Guru</h4>

<?php if ($infoLibur['libur']): ?>
  <div class="ag-info mb-3"><i class="bi bi-calendar-x-fill me-1"></i><b>Hari ini libur</b> — <?= clean($infoLibur['keterangan']) ?>.</div>
<?php endif; ?>

<div class="ag-info mb-3">
  <i class="bi bi-info-circle-fill me-1"></i>
  Masuk dibuka <b><?= substr($set['jam_masuk_mulai'], 0, 5) ?>–<?= substr($set['jam_masuk_selesai'], 0, 5) ?></b>
  (tepat waktu sampai <b><?= substr($set['jam_masuk_batas'], 0, 5) ?></b>) · Pulang dibuka <b><?= substr($set['jam_pulang_mulai'], 0, 5) ?>–<?= substr($set['jam_pulang_selesai'], 0, 5) ?></b>.
  <?php if ($lokasi): ?>Absen hanya berhasil dalam radius <b><?= (int)$lokasi['radius_meter'] ?> meter</b> dari sekolah.<?php else: ?><b>Lokasi sekolah belum diatur.</b><?php endif; ?>
</div>

<div class="row g-3 mb-3">
  <div class="col-md-6">
    <div class="ag-card <?= $sudahMasuk ? 'done' : 'pending' ?>">
      <div class="ag-icon"><i class="bi bi-box-arrow-in-right"></i></div>
      <div>
        <div class="ag-label">Absen Masuk</div>
        <div class="ag-time"><?= $sudahMasuk ? substr($hariIniRow['jam_masuk'], 0, 5) : '— Belum absen' ?></div>
        <div class="ag-note">
          <?php if ($sudahMasuk): ?>
            <?= !empty($hariIniRow['terlambat']) ? '<span class="text-warning fw-semibold">Terlambat</span>' : '<span class="text-success fw-semibold">Tepat waktu</span>' ?>
            · <?= (int)$hariIniRow['jarak_masuk'] ?> m dari sekolah
          <?php else: ?>Belum ada absen masuk hari ini<?php endif; ?>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="ag-card <?= $sudahPulang ? 'done' : 'pending' ?>">
      <div class="ag-icon"><i class="bi bi-box-arrow-right"></i></div>
      <div>
        <div class="ag-label">Absen Pulang</div>
        <div class="ag-time"><?= $sudahPulang ? substr($hariIniRow['jam_pulang'], 0, 5) : '— Belum absen' ?></div>
        <div class="ag-note"><?= $sudahPulang ? (int)$hariIniRow['jarak_pulang'] . ' m dari sekolah' : ($sudahMasuk ? 'Absen pulang saat jam pulang dibuka' : 'Absen masuk terlebih dahulu') ?></div>
      </div>
    </div>
  </div>
</div>

<button type="button" id="btnAbsenGuru" class="btn btn-dark ag-btn" style="background:#131A2E;border:none;" <?= $tombolAktif ? '' : 'disabled' ?>>
  <i class="bi bi-geo-alt-fill"></i> <span><?= clean($tombolTeks) ?></span>
</button>
<div id="agPesan" class="ag-info mt-3 d-none" role="status" aria-live="polite"></div>

<div class="d-flex gap-2 flex-wrap mt-4 mb-2">
  <span class="badge bg-success-subtle text-success-emphasis border">Hadir bulan ini: <b><?= $jmlHadir ?></b> hari</span>
  <span class="badge bg-warning-subtle text-warning-emphasis border">Terlambat: <b><?= $jmlTerlambat ?></b> hari</span>
</div>

<h5 class="fw-bold mt-3 mb-3"><i class="bi bi-clock-history me-2"></i>Riwayat <?= clean($namaBulanIndo[(int)date('n')] . ' ' . date('Y')) ?></h5>
<div class="card p-3">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light"><tr><th>Tanggal</th><th>Masuk</th><th>Pulang</th><th>Status</th></tr></thead>
      <tbody>
        <?php if (empty($riwayat)): ?>
          <tr><td colspan="4" class="text-center text-muted py-4">Belum ada data absen bulan ini.</td></tr>
        <?php endif; ?>
        <?php foreach ($riwayat as $r): ?>
          <tr>
            <td><?= clean(formatTanggalIndo($r['tanggal'])) ?></td>
            <td><?= $r['jam_masuk'] ? substr($r['jam_masuk'], 0, 5) : '-' ?></td>
            <td><?= $r['jam_pulang'] ? substr($r['jam_pulang'], 0, 5) : '-' ?></td>
            <td>
              <?php if (!empty($r['terlambat'])): ?><span class="badge bg-warning text-dark">Terlambat</span>
              <?php else: ?><span class="badge bg-success">Tepat waktu</span><?php endif; ?>
              <?php if (empty($r['jam_pulang']) && $r['tanggal'] < $hariIni): ?><span class="badge bg-secondary">Tanpa absen pulang</span><?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
(function () {
  const btn = document.getElementById('btnAbsenGuru');
  const pesanEl = document.getElementById('agPesan');
  if (!btn) return;
  const URL_ABSEN = window.location.pathname;
  const CSRF = <?= json_encode($csrf) ?>;
  let sibuk = false;

  function tampil(teks, jenis) {
    pesanEl.className = 'ag-info mt-3 ' + (jenis || '');
    pesanEl.textContent = teks; // textContent: pesan server tidak diperlakukan sebagai HTML
  }

  btn.addEventListener('click', function () {
    if (sibuk || btn.disabled) return;
    if (!navigator.geolocation) { tampil('Browser Anda tidak mendukung GPS.', 'err'); return; }
    sibuk = true;
    const label = btn.querySelector('span');
    const teksAsal = label.textContent;
    btn.disabled = true;
    label.textContent = 'Mencari lokasi...';
    tampil('Mengambil lokasi GPS Anda...', '');

    function pulih() { sibuk = false; btn.disabled = false; label.textContent = teksAsal; }

    navigator.geolocation.getCurrentPosition(function (pos) {
      label.textContent = 'Mengirim...';
      const fd = new FormData();
      fd.append('aksi', 'absen');
      fd.append('csrf', CSRF);
      fd.append('lat', pos.coords.latitude);
      fd.append('lng', pos.coords.longitude);
      fd.append('akurasi', pos.coords.accuracy || 0);

      fetch(URL_ABSEN, { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function (r) { return r.text(); })
        .then(function (text) {
          let data;
          try { data = JSON.parse(text); }
          catch (e) { throw new Error('Respons server tidak valid. Muat ulang halaman lalu coba lagi.'); }
          if (data.ok) {
            tampil(data.pesan, 'ok');
            label.textContent = 'Berhasil';
            setTimeout(function () { window.location.reload(); }, 1200);
          } else {
            pulih();
            tampil(data.pesan || 'Absen gagal.', 'err');
          }
        })
        .catch(function (err) {
          pulih();
          tampil(err.message || 'Terjadi kesalahan jaringan. Coba lagi.', 'err');
        });
    }, function () {
      pulih();
      tampil('Gagal mengambil lokasi. Aktifkan GPS dan izinkan akses lokasi untuk situs ini.', 'err');
    }, { enableHighAccuracy: true, timeout: 15000 });
  });
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>