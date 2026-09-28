<?php
// Zona waktu HARUS sama dengan absensi/gps.php, kalau tidak sapaan ("Selamat Pagi/Siang")
// dan tanggal hari ini bisa meleset (server default-nya UTC).
// WIB -> 'Asia/Jakarta' | WITA -> 'Asia/Makassar' | WIT -> 'Asia/Jayapura'
date_default_timezone_set('Asia/Makassar');

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/libur.php';

$user = currentUser();
$pageTitle = 'Dashboard';
$hariIni = date('Y-m-d');

// ==== Data siswa (jika login sebagai siswa) ====
$dataSiswaSaya = null;
if ($user['role'] === 'siswa' && $user['siswa_id']) {
    $stmt = $pdo->prepare("SELECT s.*, k.nama_kelas, k.tahun_ajaran FROM siswa s LEFT JOIN kelas k ON s.kelas_id = k.id WHERE s.id = ?");
    $stmt->execute([$user['siswa_id']]);
    $dataSiswaSaya = $stmt->fetch();
}

// ==== Kelas wali (jika login sebagai wali_kelas) ====
$kelasSaya = null;
if ($user['role'] === 'wali_kelas') {
    $kelasSaya = kelasWaliSaya($pdo, $user['id']);
}

// ==== Statistik umum (admin/guru) ====
$totalSiswa = $totalKelas = 0;
if (in_array($user['role'], ['admin','guru'])) {
    $totalSiswa = $pdo->query("SELECT COUNT(*) c FROM siswa WHERE status='Aktif'")->fetch()['c'];
    $totalKelas = $pdo->query("SELECT COUNT(*) c FROM kelas")->fetch()['c'];
}

// ==== Info hari libur hari ini (Sabtu/Minggu otomatis + tanggal merah admin) ====
$infoLiburHariIni = cekHariLibur($pdo, $hariIni);

// ==== Ringkasan sistem, panel "Perlu Perhatian", & menu cepat — KHUSUS ADMIN ====
$statSistem = [];
$menuCepat = [];
if ($user['role'] === 'admin') {
    $statSistem['guru']              = (int)$pdo->query("SELECT COUNT(*) c FROM users WHERE role='guru'")->fetch()['c'];
    $statSistem['wali_kelas']        = (int)$pdo->query("SELECT COUNT(*) c FROM users WHERE role='wali_kelas'")->fetch()['c'];
    $statSistem['mapel']             = (int)$pdo->query("SELECT COUNT(*) c FROM mata_pelajaran")->fetch()['c'];
    $statSistem['akun']              = (int)$pdo->query("SELECT COUNT(*) c FROM users")->fetch()['c'];
    $statSistem['tugas_belum_dinilai'] = (int)$pdo->query("SELECT COUNT(*) c FROM elearning_pengumpulan WHERE status='Belum Dinilai'")->fetch()['c'];
    $statSistem['siswa_tanpa_kelas'] = (int)$pdo->query("SELECT COUNT(*) c FROM siswa WHERE kelas_id IS NULL AND status='Aktif'")->fetch()['c'];

    // CATATAN: sesuaikan URL di bawah kalau nama file di project Anda berbeda
    // (khususnya Data Kelas, Mata Pelajaran, Kelola Akun — saya tebak dari
    // pola penamaan modul lain seperti siswa/list.php).
    $menuCepat = [
        ['label' => 'Data Siswa',          'icon' => 'bi-people-fill',            'url' => '/siswa/list.php'],
        ['label' => 'Data Kelas',          'icon' => 'bi-diagram-3-fill',         'url' => '/kelas/list.php'],
        ['label' => 'Mata Pelajaran',      'icon' => 'bi-journal-bookmark-fill',  'url' => '/mapel/list.php'],
        ['label' => 'Kelola Akun',         'icon' => 'bi-person-badge-fill',      'url' => '/akun/list.php'],
        ['label' => 'Input Absensi',       'icon' => 'bi-calendar2-check-fill',   'url' => '/absensi/index.php'],
        ['label' => 'Absensi Per Mapel',   'icon' => 'bi-journal-check',          'url' => '/absensi/mapel.php'],
        ['label' => 'Monitor GPS',         'icon' => 'bi-geo-alt-fill',           'url' => '/absensi/monitor.php'],
        ['label' => 'Lokasi & Radius GPS', 'icon' => 'bi-geo-fill',               'url' => '/lokasi/index.php'],
        ['label' => 'Hari Libur',          'icon' => 'bi-calendar-x-fill',        'url' => '/libur/index.php'],
        ['label' => 'Rekap Absensi',       'icon' => 'bi-bar-chart-fill',         'url' => '/absensi/rekap.php'],
        ['label' => 'Nilai / Rapor',       'icon' => 'bi-clipboard-data-fill',    'url' => '/nilai/index.php'],
        ['label' => 'Materi Belajar',      'icon' => 'bi-journal-richtext',       'url' => '/elearning/materi.php'],
        ['label' => 'Tugas',               'icon' => 'bi-card-checklist',         'url' => '/elearning/tugas.php'],
        ['label' => 'Pengumuman',          'icon' => 'bi-megaphone-fill',         'url' => '/pengumuman/list.php'],
    ];
}

// ==== Absensi hari ini (dibatasi ke kelas wali jika role wali_kelas) ====
$absenHariIni = ['Hadir'=>0,'Izin'=>0,'Sakit'=>0,'Alpa'=>0];
$gpsHariIni = ['Berhasil'=>0,'Ditolak'=>0];
if (in_array($user['role'], ['admin','guru','wali_kelas'])) {
    if ($kelasSaya) {
        $stmt = $pdo->prepare("SELECT status, COUNT(*) c FROM absensi WHERE tanggal = ? AND kelas_id = ? GROUP BY status");
        $stmt->execute([$hariIni, $kelasSaya['id']]);
        $stmtG = $pdo->prepare("SELECT status, COUNT(*) c FROM absensi_gps WHERE tanggal = ? AND kelas_id = ? GROUP BY status");
        $stmtG->execute([$hariIni, $kelasSaya['id']]);
    } else {
        $stmt = $pdo->prepare("SELECT status, COUNT(*) c FROM absensi WHERE tanggal = ? GROUP BY status");
        $stmt->execute([$hariIni]);
        $stmtG = $pdo->prepare("SELECT status, COUNT(*) c FROM absensi_gps WHERE tanggal = ? GROUP BY status");
        $stmtG->execute([$hariIni]);
    }
    foreach ($stmt->fetchAll() as $row) { $absenHariIni[$row['status']] = $row['c']; }
    foreach ($stmtG->fetchAll() as $row) { $gpsHariIni[$row['status']] = $row['c']; }
}

// ==== Rekap 7 hari terakhir (untuk chart admin/guru/wali_kelas) ====
$labels7 = $dataHadir = $dataIzin = $dataSakit = $dataAlpa = [];
if (in_array($user['role'], ['admin','guru','wali_kelas'])) {
    if ($kelasSaya) {
        $stmt = $pdo->prepare("SELECT tanggal, status, COUNT(*) c FROM absensi WHERE tanggal >= DATE_SUB(?, INTERVAL 6 DAY) AND kelas_id = ? GROUP BY tanggal, status");
        $stmt->execute([$hariIni, $kelasSaya['id']]);
    } else {
        $stmt = $pdo->prepare("SELECT tanggal, status, COUNT(*) c FROM absensi WHERE tanggal >= DATE_SUB(?, INTERVAL 6 DAY) GROUP BY tanggal, status");
        $stmt->execute([$hariIni]);
    }
    $rekap7hari = [];
    foreach ($stmt->fetchAll() as $r) { $rekap7hari[$r['tanggal']][$r['status']] = $r['c']; }
    for ($i = 6; $i >= 0; $i--) {
        $tgl = date('Y-m-d', strtotime("-$i day"));
        $labels7[] = date('d/m', strtotime($tgl));
        $dataHadir[] = $rekap7hari[$tgl]['Hadir'] ?? 0;
        $dataIzin[]  = $rekap7hari[$tgl]['Izin'] ?? 0;
        $dataSakit[] = $rekap7hari[$tgl]['Sakit'] ?? 0;
        $dataAlpa[]  = $rekap7hari[$tgl]['Alpa'] ?? 0;
    }
}

// ==== Data presensi hari ini untuk siswa (masuk & pulang) + jadwal lokasi ====
$absenSayaHariIni = null;
$lokasi = null;
$tugasBelumDikumpul = [];
if ($user['role'] === 'siswa' && $user['siswa_id']) {
    $stmt = $pdo->prepare("SELECT * FROM absensi WHERE siswa_id = ? AND tanggal = ?");
    $stmt->execute([$user['siswa_id'], $hariIni]);
    $absenSayaHariIni = $stmt->fetch();

    $lokasi = $pdo->query("SELECT * FROM lokasi_sekolah ORDER BY id DESC LIMIT 1")->fetch();

    if ($dataSiswaSaya && $dataSiswaSaya['kelas_id']) {
        $stmt = $pdo->prepare("
            SELECT t.id, t.judul, t.deadline, mp.nama_mapel FROM elearning_tugas t
            JOIN mata_pelajaran mp ON t.mapel_id = mp.id
            WHERE t.kelas_id = ? AND t.deadline >= NOW()
              AND t.id NOT IN (SELECT tugas_id FROM elearning_pengumpulan WHERE siswa_id = ?)
            ORDER BY t.deadline ASC LIMIT 4
        ");
        $stmt->execute([$dataSiswaSaya['kelas_id'], $user['siswa_id']]);
        $tugasBelumDikumpul = $stmt->fetchAll();
    }
}
$sudahMasuk  = $absenSayaHariIni && $absenSayaHariIni['status'] === 'Hadir';
$sudahPulang = $absenSayaHariIni && !empty($absenSayaHariIni['jam_pulang'] ?? null);

// Ambil jam masuk yang akurat dari log absensi_gps (bukan created_at, karena
// timezone default MySQL server bisa berbeda dari timezone PHP aplikasi).
// Dibungkus try-catch: jika migrasi kolom "tipe"/"jam_pulang" belum dijalankan
// di database, dashboard tetap tampil (fallback ke status biasa) alih-alih fatal error.
$jamMasukSaya = null;
$migrasiPulangBelumJalan = false;
if ($sudahMasuk && $user['role'] === 'siswa' && $user['siswa_id']) {
    try {
        $stmt = $pdo->prepare("SELECT waktu FROM absensi_gps WHERE siswa_id = ? AND tanggal = ? AND tipe = 'Masuk' AND status = 'Berhasil' ORDER BY id DESC LIMIT 1");
        $stmt->execute([$user['siswa_id'], $hariIni]);
        $jamMasukSaya = $stmt->fetchColumn() ?: null;
    } catch (PDOException $e) {
        // Kolom "tipe" belum ada — berarti migrasi tambah_absen_pulang.sql belum dijalankan
        $migrasiPulangBelumJalan = true;
    }
}

// ==== Pengumuman terbaru ====
$pengumuman = $pdo->query("SELECT * FROM pengumuman ORDER BY tanggal DESC, id DESC LIMIT 4")->fetchAll();

// ==== Sapaan berdasar jam ====
$jamNow = (int)date('H');
if ($jamNow < 11)      { $sapaan = 'Selamat Pagi'; $sapaanIcon = 'bi-sunrise-fill'; }
elseif ($jamNow < 15)  { $sapaan = 'Selamat Siang'; $sapaanIcon = 'bi-sun-fill'; }
elseif ($jamNow < 18)  { $sapaan = 'Selamat Sore'; $sapaanIcon = 'bi-cloud-sun-fill'; }
else                   { $sapaan = 'Selamat Malam'; $sapaanIcon = 'bi-moon-stars-fill'; }

// ==== Susun mini-stat untuk kartu sapaan (beda per role) ====
$heroStats = [];
if ($user['role'] === 'siswa' && $dataSiswaSaya) {
    $heroStats = [
        ['label' => 'Kelas', 'value' => $dataSiswaSaya['nama_kelas'] ?? '-'],
        ['label' => 'Status', 'value' => $sudahMasuk ? 'Sudah presensi' : 'Belum presensi'],
        ['label' => 'Tahun Ajaran', 'value' => $dataSiswaSaya['tahun_ajaran'] ?? '-'],
    ];
} elseif ($user['role'] === 'admin') {
    $heroStats = [
        ['label' => 'Total Siswa', 'value' => $totalSiswa],
        ['label' => 'Total Kelas', 'value' => $totalKelas],
        ['label' => 'Total Akun', 'value' => $statSistem['akun'] ?? 0],
    ];
} elseif ($user['role'] === 'guru') {
    $heroStats = [
        ['label' => 'Total Siswa', 'value' => $totalSiswa],
        ['label' => 'Total Kelas', 'value' => $totalKelas],
        ['label' => 'Hadir Hari Ini', 'value' => $absenHariIni['Hadir']],
    ];
} elseif ($user['role'] === 'wali_kelas') {
    $heroStats = [
        ['label' => 'Kelas', 'value' => $kelasSaya['nama_kelas'] ?? '-'],
        ['label' => 'Hadir Hari Ini', 'value' => $absenHariIni['Hadir']],
        ['label' => 'Alpa Hari Ini', 'value' => $absenHariIni['Alpa']],
    ];
}

$roleLabelHero = ['admin'=>'Administrator','guru'=>'Guru','wali_kelas'=>'Wali Kelas','siswa'=>'Siswa'][$user['role']] ?? '';

include __DIR__ . '/includes/header.php';
?>

<style>
/* =========================================================
   Reskin dashboard — gaya portal institusional (navy + emas),
   label uppercase kecil, kartu presensi masuk/pulang terpisah,
   jam real-time WIB/WITA/WIT.
   ========================================================= */
:root{
  --navy:#131A2E; --navy-2:#0B0F1D; --gold:#F4B740;
  --bg:#F2F3F6; --surface:#FFFFFF; --line:#E5E7EE;
  --ink:#1C2233; --ink-soft:#6B7280;
  --brand:#131A2E; --brand-2:#0B0F1D; --brand-soft:#EDEFF5;
  --gps:#2563EB; --gps-2:#1D4ED8; --gps-soft:#EAF2FE;
  --good:#16A34A; --good-soft:#E7F6EC;
  --warn:#EA7A1B; --warn-soft:#FDEEE0;
  --bad:#DC2626;  --bad-soft:#FCEAEA;
  --info:#2563EB; --info-soft:#EAF2FE;
}
body{ background:var(--bg); color:var(--ink); font-family:'Inter',system-ui,sans-serif; }
.topbar{ background:var(--navy) !important; }
.sidebar{ background:var(--navy) !important; border-right:none !important; }
.sidebar .nav-section-label{ color:rgba(255,255,255,.35) !important; }
.sidebar .nav-link{ color:rgba(255,255,255,.78) !important; }
.sidebar .nav-link i{ color:rgba(255,255,255,.5) !important; }
.sidebar .nav-link:hover{ background:rgba(255,255,255,.06) !important; }
.sidebar .nav-link.active{ background:rgba(244,183,64,.12) !important; color:var(--gold) !important; font-weight:700; }
.sidebar .nav-link.active i{ color:var(--gold) !important; }
.sidebar .nav-link.gps-link.active,
.sidebar .nav-link.gps-link:hover{ background:rgba(37,99,235,.14) !important; color:#93B8F5 !important; }

.gv-topline{ display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; flex-wrap:wrap; gap:8px; }
.gv-title{ font-size:.76rem; font-weight:800; letter-spacing:.08em; text-transform:uppercase; color:var(--ink-soft); }
.gv-crumb{ font-size:.78rem; color:var(--ink-soft); }
.gv-crumb b{ color:var(--ink); font-weight:600; }

.gv-section-label{ font-size:.72rem; font-weight:800; letter-spacing:.07em; text-transform:uppercase; color:var(--ink-soft); margin:26px 0 12px; }
.gv-section-label:first-of-type{ margin-top:0; }

/* ---- Hero sapaan ---- */
.gv-hero{
  background:linear-gradient(135deg,var(--navy),var(--navy-2));
  border-radius:10px; color:#fff; padding:20px 24px; height:100%;
  position:relative; overflow:hidden;
}
.gv-hero::after{ content:""; position:absolute; right:-40px; top:-60px; width:200px; height:200px; border-radius:50%; background:rgba(255,255,255,.04); }
.gv-hero .gv-tag{ font-size:.68rem; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:rgba(255,255,255,.55); display:flex; align-items:center; gap:6px; }
.gv-hero .gv-avatar{ width:46px; height:46px; border-radius:50%; background:#fff; display:flex; align-items:center; justify-content:center; color:var(--navy); font-weight:800; font-size:1.1rem; flex-shrink:0; }
.gv-hero .gv-name{ font-size:1.25rem; font-weight:800; }
.gv-hero .gv-sub{ font-size:.78rem; color:rgba(255,255,255,.6); }
.gv-hero .gv-stats{ display:flex; gap:28px; margin-top:16px; padding-top:14px; border-top:1px solid rgba(255,255,255,.12); flex-wrap:wrap; }
.gv-hero .gv-stats .lbl{ font-size:.65rem; text-transform:uppercase; letter-spacing:.06em; color:rgba(255,255,255,.45); display:block; }
.gv-hero .gv-stats .val{ font-size:1rem; font-weight:700; }

/* ---- Kartu jam WIB/WITA/WIT ---- */
.gv-clock-card{ background:var(--surface); border:1px solid var(--line); border-radius:10px; padding:18px 20px; height:100%; }
.gv-clock-card .gv-tag{ font-size:.68rem; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:var(--ink-soft); display:flex; align-items:center; gap:6px; margin-bottom:14px; }
.gv-clock-row{ display:flex; justify-content:space-between; text-align:center; }
.gv-clock-row .zone .dot{ width:5px; height:5px; border-radius:50%; background:var(--gold); display:inline-block; margin-right:4px; }
.gv-clock-row .zone .zname{ font-size:.68rem; font-weight:700; color:var(--ink-soft); text-transform:uppercase; letter-spacing:.04em; }
.gv-clock-row .zone .ztime{ font-family:'Courier New',monospace; font-size:1.5rem; font-weight:800; color:var(--navy); margin:4px 0 0; }
.gv-clock-row .zone .zcity{ font-size:.7rem; color:var(--ink-soft); }

/* ---- Kartu jadwal & presensi ---- */
.gv-card{ background:var(--surface); border:1px solid var(--line); border-radius:10px; padding:16px 18px; }
.gv-jadwal{ display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; }
.gv-jadwal .gv-tag{ font-size:.68rem; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:var(--ink-soft); margin-bottom:6px; }
.gv-chip{ display:inline-flex; align-items:center; gap:6px; background:var(--info-soft); color:var(--info); font-size:.78rem; font-weight:700; padding:5px 12px; border-radius:6px; }

.gv-presensi{ display:flex; align-items:center; gap:14px; background:var(--surface); border:1px solid var(--line); border-radius:10px; padding:16px 18px; height:100%; }
.gv-presensi.pending{ border-left:4px solid var(--warn); background:linear-gradient(90deg,var(--warn-soft),var(--surface) 40%); }
.gv-presensi.done{ border-left:4px solid var(--good); }
.gv-presensi .icon-box{ width:42px; height:42px; border-radius:8px; background:var(--brand-soft); display:flex; align-items:center; justify-content:center; font-size:1.15rem; color:var(--navy); flex-shrink:0; }
.gv-presensi.pending .icon-box{ background:var(--warn-soft); color:var(--warn); }
.gv-presensi .gv-tag{ font-size:.68rem; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:var(--ink-soft); }
.gv-presensi .gv-time{ font-size:1.3rem; font-weight:800; color:var(--ink); }
.gv-presensi .gv-note{ font-size:.74rem; color:var(--ink-soft); }
.gv-presensi .gv-note.link{ color:var(--warn); font-weight:600; }

/* Kartu presensi yang bisa diklik untuk absen langsung */
.gv-presensi.klik{ cursor:pointer; transition:transform .15s ease, box-shadow .2s ease; }
.gv-presensi.klik:hover{ transform:translateY(-2px); box-shadow:0 10px 24px -12px rgba(234,122,27,.55); }
.gv-presensi.klik:active{ transform:scale(.99); }
.gv-presensi.klik:focus-visible{ outline:3px solid rgba(37,99,235,.45); outline-offset:2px; }
.gv-presensi.loading{ pointer-events:none; opacity:.8; }
.gv-presensi.loading .icon-box i{ animation:gvSpin 1s linear infinite; }
@keyframes gvSpin{ to{ transform:rotate(360deg); } }

.gv-info-bar{ background:var(--info-soft); color:#1E3A6E; border-radius:8px; padding:10px 16px; font-size:.8rem; display:flex; align-items:center; gap:8px; margin-top:12px; }
.gv-info-bar b{ font-weight:700; }
.gv-info-bar.ok{ background:var(--good-soft); color:#0F5A2A; }
.gv-info-bar.err{ background:var(--bad-soft); color:#8A1B1B; }

/* ---- Kartu bernomor (tugas / pengumuman / perlu perhatian) ---- */
.gv-num-card{ display:flex; align-items:center; gap:16px; background:var(--surface); border:1px solid var(--line); border-radius:10px; padding:16px 18px; height:100%; text-decoration:none; color:inherit; }
.gv-num-card .num{ font-size:1.7rem; font-weight:800; font-family:'Courier New',monospace; width:36px; flex-shrink:0; }
.gv-num-card .gv-tag{ font-size:.66rem; font-weight:700; letter-spacing:.06em; text-transform:uppercase; }
.gv-num-card .title{ font-weight:700; font-size:.92rem; color:var(--ink); }
.gv-num-card .desc{ font-size:.76rem; color:var(--ink-soft); }
.gv-num-card .arrow{ width:34px; height:34px; border-radius:50%; background:var(--brand-soft); display:flex; align-items:center; justify-content:center; flex-shrink:0; color:var(--ink); margin-left:auto; }

/* ---- Panel chart ---- */
.gv-panel{ background:var(--surface); border:1px solid var(--line); border-radius:10px; padding:18px 20px; }
.gv-panel-title{ font-size:.72rem; font-weight:800; letter-spacing:.06em; text-transform:uppercase; color:var(--ink-soft); margin-bottom:14px; }

/* ---- Menu cepat (shortcut tiles, khusus admin) ---- */
.gv-shortcut{
  display:flex; flex-direction:column; align-items:center; justify-content:center; gap:8px;
  background:var(--surface); border:1px solid var(--line); border-radius:10px;
  padding:18px 10px; text-decoration:none; color:var(--ink); height:100%; text-align:center;
  transition:transform .15s ease, box-shadow .15s ease, border-color .15s ease;
}
.gv-shortcut:hover{ transform:translateY(-3px); box-shadow:0 10px 24px -10px rgba(19,26,46,.2); border-color:var(--gold); color:var(--ink); }
.gv-shortcut .icon{ width:42px; height:42px; border-radius:10px; background:var(--brand-soft); display:flex; align-items:center; justify-content:center; font-size:1.15rem; color:var(--navy); }
.gv-shortcut .label{ font-size:.76rem; font-weight:700; line-height:1.2; }
</style>

<div class="gv-topline">
  <span class="gv-title">Dashboard</span>
  <span class="gv-crumb">Laman <i class="bi bi-chevron-right small"></i> <b>Dashboard</b></span>
</div>

<?php if (!empty($migrasiPulangBelumJalan)): ?>
<div class="gv-info-bar mb-3" style="background:var(--warn-soft);color:#7A3E0B;">
  <i class="bi bi-exclamation-triangle-fill"></i>
  Fitur <b>Absen Pulang</b> belum aktif karena migrasi database belum dijalankan. Import file
  <b>tambah_absen_pulang.sql</b> ke database <b>db_absensi_sekolah</b> melalui phpMyAdmin, lalu refresh halaman ini.
</div>
<?php endif; ?>

<?php if ($infoLiburHariIni['libur']): ?>
<div class="gv-info-bar mb-3">
  <i class="bi bi-calendar-x-fill"></i>
  <b>Hari ini libur</b> — <?= clean($infoLiburHariIni['keterangan']) ?>.
</div>
<?php endif; ?>

<!-- ===== Hero sapaan + Jam Indonesia ===== -->
<div class="row g-3 mb-1">
  <div class="col-lg-8">
    <div class="gv-hero">
      <div class="gv-tag"><i class="bi <?= $sapaanIcon ?>"></i> <?= $sapaan ?></div>
      <div class="d-flex align-items-center gap-3 mt-2 mb-1">
        <div class="gv-avatar"><?= strtoupper(substr($user['nama'],0,1)) ?></div>
        <div>
          <div class="gv-name"><?= strtoupper(clean($user['nama'])) ?></div>
          <div class="gv-sub"><?= formatTanggalIndo($hariIni) ?> · <?= clean($roleLabelHero) ?><?= $kelasSaya ? ' Kelas '.clean($kelasSaya['nama_kelas']) : '' ?></div>
        </div>
      </div>
      <?php if (!empty($heroStats)): ?>
      <div class="gv-stats">
        <?php foreach ($heroStats as $hs): ?>
        <div>
          <span class="lbl"><?= clean($hs['label']) ?></span>
          <span class="val"<?= $hs['label'] === 'Status' ? ' id="heroStatus"' : '' ?>><?= clean((string)$hs['value']) ?></span>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="gv-clock-card">
      <div class="gv-tag"><i class="bi bi-clock-history"></i> Waktu Indonesia</div>
      <div class="gv-clock-row">
        <div class="zone"><span class="dot"></span><span class="zname">WIB</span><div class="ztime" id="clockWIB">--:--:--</div><div class="zcity">Jakarta</div></div>
        <div class="zone"><span class="dot"></span><span class="zname">WITA</span><div class="ztime" id="clockWITA">--:--:--</div><div class="zcity">Makassar</div></div>
        <div class="zone"><span class="dot"></span><span class="zname">WIT</span><div class="ztime" id="clockWIT">--:--:--</div><div class="zcity">Jayapura</div></div>
      </div>
    </div>
  </div>
</div>

<?php if ($user['role'] === 'siswa'): ?>
<!-- ===== Presensi hari ini (khusus siswa) ===== -->
<div class="gv-section-label">Presensi Hari Ini</div>

<?php if ($lokasi): ?>
<div class="gv-card mb-3">
  <div class="gv-jadwal">
    <div>
      <div class="gv-tag">Jadwal Absen Hari Ini</div>
      <span class="gv-chip"><i class="bi bi-clock"></i> Masuk <?= substr($lokasi['jam_mulai'],0,5) ?>–<?= substr($lokasi['jam_selesai'],0,5) ?></span>
      <span class="gv-chip" style="background:var(--warn-soft);color:var(--warn);margin-left:6px;"><i class="bi bi-clock"></i> Pulang <?= substr($lokasi['jam_pulang_mulai'],0,5) ?>–<?= substr($lokasi['jam_pulang_selesai'],0,5) ?></span>
    </div>
    <a href="<?= BASE_URL ?>/absensi/gps.php" class="btn btn-dark btn-sm" style="background:var(--navy);border:none;"><i class="bi bi-geo-alt-fill"></i> Absen GPS</a>
  </div>
</div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-md-6">
    <div id="cardMasuk" class="gv-presensi <?= $sudahMasuk ? 'done' : 'pending klik' ?>"<?= $sudahMasuk ? '' : ' role="button" tabindex="0"' ?>>
      <div class="icon-box"><i class="bi bi-box-arrow-in-right"></i></div>
      <div>
        <div class="gv-tag">Presensi Masuk</div>
        <div class="gv-time" id="timeMasuk"><?= $sudahMasuk ? ($jamMasukSaya ? substr($jamMasukSaya, 0, 5) : 'Hadir') : '— Belum presensi' ?></div>
        <div class="gv-note <?= $sudahMasuk ? '' : 'link' ?>" id="noteMasuk"><i class="bi <?= $sudahMasuk ? 'bi-check-circle' : 'bi-cursor-fill' ?>"></i> <span><?= $sudahMasuk ? 'Sudah presensi' : 'Klik untuk presensi' ?></span></div>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div id="cardPulang" class="gv-presensi <?= $sudahPulang ? 'done' : ($sudahMasuk ? 'pending klik' : 'pending') ?>"<?= (!$sudahPulang && $sudahMasuk) ? ' role="button" tabindex="0"' : '' ?>>
      <div class="icon-box"><i class="bi bi-box-arrow-right"></i></div>
      <div>
        <div class="gv-tag">Presensi Pulang</div>
        <div class="gv-time" id="timePulang"><?= $sudahPulang ? substr($absenSayaHariIni['jam_pulang'], 0, 5) : '— Belum presensi' ?></div>
        <div class="gv-note <?= $sudahPulang ? '' : 'link' ?>" id="notePulang"><i class="bi <?= $sudahPulang ? 'bi-check-circle' : 'bi-cursor-fill' ?>"></i> <span><?= $sudahPulang ? 'Sudah presensi' : ($sudahMasuk ? 'Klik untuk presensi' : 'Presensi masuk dulu') ?></span></div>
      </div>
    </div>
  </div>
</div>

<div id="presensiPesan" class="gv-info-bar d-none" role="status" aria-live="polite"></div>

<div class="gv-info-bar">
  <i class="bi bi-info-circle-fill"></i>
  Metode presensi hari ini <b>GPS</b><?php if ($lokasi): ?> — presensi hanya berhasil dalam radius <b><?= (int)$lokasi['radius_meter'] ?> meter</b> dari sekolah.<?php endif; ?>
</div>
<?php endif; ?>

<!-- ===== Ringkasan Sistem — khusus admin ===== -->
<?php if ($user['role'] === 'admin'): ?>
<div class="gv-section-label">Ringkasan Sistem</div>
<div class="row g-3">
  <div class="col-6 col-lg-3">
    <div class="gv-card d-flex align-items-center gap-3">
      <div class="icon-box"><i class="bi bi-person-workspace"></i></div>
      <div><div class="gv-tag">Total Guru</div><div class="gv-time"><?= $statSistem['guru'] ?></div></div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="gv-card d-flex align-items-center gap-3">
      <div class="icon-box"><i class="bi bi-person-lines-fill"></i></div>
      <div><div class="gv-tag">Total Wali Kelas</div><div class="gv-time"><?= $statSistem['wali_kelas'] ?></div></div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="gv-card d-flex align-items-center gap-3">
      <div class="icon-box"><i class="bi bi-journal-bookmark-fill"></i></div>
      <div><div class="gv-tag">Mata Pelajaran</div><div class="gv-time"><?= $statSistem['mapel'] ?></div></div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="gv-card d-flex align-items-center gap-3">
      <div class="icon-box"><i class="bi bi-shield-lock-fill"></i></div>
      <div><div class="gv-tag">Total Akun</div><div class="gv-time"><?= $statSistem['akun'] ?></div></div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ===== Perlu Perhatian — khusus admin ===== -->
<?php if ($user['role'] === 'admin' && ($statSistem['tugas_belum_dinilai'] > 0 || $statSistem['siswa_tanpa_kelas'] > 0)): ?>
<div class="gv-section-label">Perlu Perhatian</div>
<div class="row g-3">
  <?php if ($statSistem['tugas_belum_dinilai'] > 0): ?>
  <div class="col-md-6">
    <a href="<?= BASE_URL ?>/elearning/tugas.php" class="gv-num-card">
      <div class="num" style="color:#EA7A1B;"><?= $statSistem['tugas_belum_dinilai'] ?></div>
      <div>
        <div class="gv-tag" style="color:#EA7A1B;">Menunggu Penilaian</div>
        <div class="title">Pengumpulan tugas siswa yang belum dinilai guru</div>
      </div>
      <div class="arrow"><i class="bi bi-arrow-right"></i></div>
    </a>
  </div>
  <?php endif; ?>
  <?php if ($statSistem['siswa_tanpa_kelas'] > 0): ?>
  <div class="col-md-6">
    <a href="<?= BASE_URL ?>/siswa/list.php" class="gv-num-card">
      <div class="num" style="color:#DC2626;"><?= $statSistem['siswa_tanpa_kelas'] ?></div>
      <div>
        <div class="gv-tag" style="color:#DC2626;">Siswa Belum Ada Kelas</div>
        <div class="title">Siswa aktif yang belum dihubungkan ke kelas manapun</div>
      </div>
      <div class="arrow"><i class="bi bi-arrow-right"></i></div>
    </a>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- ===== Statistik ringkas untuk admin/guru/wali_kelas ===== -->
<?php if (in_array($user['role'], ['admin','guru','wali_kelas'])): ?>
<div class="gv-section-label">Statistik Absensi Hari Ini</div>
<div class="row g-3">
  <div class="col-6 col-lg-3">
    <div class="gv-presensi done"><div class="icon-box"><i class="bi bi-people-fill"></i></div>
      <div><div class="gv-tag">Hadir</div><div class="gv-time"><?= $absenHariIni['Hadir'] ?></div></div></div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="gv-presensi pending"><div class="icon-box"><i class="bi bi-exclamation-triangle-fill"></i></div>
      <div><div class="gv-tag">Alpa</div><div class="gv-time"><?= $absenHariIni['Alpa'] ?></div></div></div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="gv-card d-flex align-items-center gap-3">
      <div class="icon-box" style="background:var(--gps-soft);color:var(--gps);"><i class="bi bi-geo-alt-fill"></i></div>
      <div><div class="gv-tag">Absen via GPS</div><div class="gv-time"><?= $gpsHariIni['Berhasil'] ?></div></div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="gv-card d-flex align-items-center gap-3">
      <div class="icon-box" style="background:var(--warn-soft);color:var(--warn);"><i class="bi bi-shield-exclamation"></i></div>
      <div><div class="gv-tag">GPS Ditolak</div><div class="gv-time"><?= $gpsHariIni['Ditolak'] ?></div></div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ===== Grafik (admin/guru/wali_kelas) ===== -->
<?php if (in_array($user['role'], ['admin','guru','wali_kelas'])): ?>
<div class="gv-section-label">Rekap 7 Hari Terakhir<?= $kelasSaya ? ' — Kelas '.clean($kelasSaya['nama_kelas']) : '' ?></div>
<div class="gv-panel mb-1">
  <canvas id="chartAbsensi" height="120"></canvas>
</div>
<?php endif; ?>

<!-- ===== Menu Cepat — khusus admin ===== -->
<?php if (!empty($menuCepat)): ?>
<div class="gv-section-label">Menu Cepat</div>
<div class="row g-3 mb-1">
  <?php foreach ($menuCepat as $m): ?>
  <div class="col-6 col-md-3 col-lg-2">
    <a href="<?= BASE_URL . $m['url'] ?>" class="gv-shortcut">
      <div class="icon"><i class="bi <?= $m['icon'] ?>"></i></div>
      <div class="label"><?= clean($m['label']) ?></div>
    </a>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ===== Layanan: tugas (siswa) / pengumuman (semua) ===== -->
<div class="gv-section-label">
  <?= $user['role'] === 'siswa' ? 'Tugas & Pengumuman' : 'Pengumuman Terbaru' ?>
</div>
<div class="row g-3">
  <?php
  $palet = ['#2563EB','#16A34A','#EA7A1B','#7C3AED'];
  $i = 0;
  if ($user['role'] === 'siswa'):
    foreach ($tugasBelumDikumpul as $t):
      $sw = sisaWaktu($t['deadline']); $warna = $palet[$i % count($palet)]; $i++;
  ?>
    <div class="col-md-6">
      <a href="<?= BASE_URL ?>/elearning/tugas_detail.php?id=<?= $t['id'] ?>" class="gv-num-card">
        <div class="num" style="color:<?= $warna ?>;"><?= str_pad($i,2,'0',STR_PAD_LEFT) ?></div>
        <div>
          <div class="gv-tag" style="color:<?= $warna ?>;">Tugas Belajar</div>
          <div class="title"><?= clean($t['judul']) ?></div>
          <div class="desc"><?= clean($t['nama_mapel']) ?> · tenggat <?= $sw['label'] ?></div>
        </div>
        <div class="arrow"><i class="bi bi-arrow-right"></i></div>
      </a>
    </div>
  <?php endforeach; endif; ?>

  <?php foreach ($pengumuman as $p): $warna = $palet[$i % count($palet)]; $i++; ?>
    <div class="col-md-6">
      <a href="<?= BASE_URL ?>/pengumuman/list.php" class="gv-num-card">
        <div class="num" style="color:<?= $warna ?>;"><?= str_pad($i,2,'0',STR_PAD_LEFT) ?></div>
        <div>
          <div class="gv-tag" style="color:<?= $warna ?>;"><?= clean($p['kategori']) ?></div>
          <div class="title"><?= clean($p['judul']) ?></div>
          <div class="desc"><?= clean(mb_strimwidth($p['isi'], 0, 70, '...')) ?></div>
        </div>
        <div class="arrow"><i class="bi bi-box-arrow-up-right"></i></div>
      </a>
    </div>
  <?php endforeach; ?>

  <?php if ($user['role'] === 'siswa' && empty($tugasBelumDikumpul) && empty($pengumuman)): ?>
    <div class="col-12"><div class="gv-card text-muted small text-center">Tidak ada tugas atau pengumuman terbaru.</div></div>
  <?php elseif ($user['role'] !== 'siswa' && empty($pengumuman)): ?>
    <div class="col-12"><div class="gv-card text-muted small text-center">Belum ada pengumuman.</div></div>
  <?php endif; ?>
</div>

<script>
function pad(n){ return n.toString().padStart(2,'0'); }
function updateClocks(){
  const now = new Date();
  const utc = now.getTime() + (now.getTimezoneOffset()*60000);
  const zones = { WIB:7, WITA:8, WIT:9 };
  for (const [zone, offset] of Object.entries(zones)) {
    const t = new Date(utc + (3600000*offset));
    const el = document.getElementById('clock'+zone);
    if (el) el.textContent = pad(t.getHours())+':'+pad(t.getMinutes())+':'+pad(t.getSeconds());
  }
}
updateClocks();
setInterval(updateClocks, 1000);
</script>

<?php if ($user['role'] === 'siswa'): ?>
<script>
/* ===== Absen langsung dari kartu presensi =====
   Mengirim koordinat GPS ke absensi/gps.php (endpoint yang sama dengan halaman Absen GPS).
   Server yang menentukan apakah ini absen MASUK atau PULANG, lalu memvalidasi jam & radius. */
(function () {
  const URL_ABSEN = <?= json_encode(BASE_URL . '/absensi/gps.php') ?>;
  const cardMasuk  = document.getElementById('cardMasuk');
  const cardPulang = document.getElementById('cardPulang');
  const pesanEl    = document.getElementById('presensiPesan');
  let sibuk = false;

  function tampilPesan(teks, jenis) {
    pesanEl.className = 'gv-info-bar ' + (jenis || '');
    pesanEl.innerHTML = '';
    const ikon = document.createElement('i');
    ikon.className = 'bi ' + (jenis === 'ok' ? 'bi-check-circle-fill' : jenis === 'err' ? 'bi-exclamation-circle-fill' : 'bi-info-circle-fill');
    const span = document.createElement('span');
    span.textContent = teks; // textContent: pesan dari server tidak diperlakukan sebagai HTML
    pesanEl.appendChild(ikon);
    pesanEl.appendChild(span);
  }

  function noteEl(card)  { return card.querySelector('.gv-note'); }
  function noteText(card, teks, ikon, link) {
    const n = noteEl(card);
    n.className = 'gv-note' + (link ? ' link' : '');
    n.querySelector('i').className = 'bi ' + ikon;
    n.querySelector('span').textContent = teks;
  }

  function jadikanSelesai(card, timeId, jam) {
    card.classList.remove('pending', 'klik', 'loading');
    card.classList.add('done');
    card.removeAttribute('role');
    card.removeAttribute('tabindex');
    document.getElementById(timeId).textContent = jam;
    noteText(card, 'Sudah presensi', 'bi-check-circle', false);
  }

  function aktifkan(card) {
    card.classList.add('klik');
    card.setAttribute('role', 'button');
    card.setAttribute('tabindex', '0');
    noteText(card, 'Klik untuk presensi', 'bi-cursor-fill', true);
  }

  function pulihkan(card, teksAsal) {
    card.classList.remove('loading');
    card.querySelector('.icon-box i').className = card.dataset.ikon;
    noteText(card, teksAsal, 'bi-cursor-fill', true);
    sibuk = false;
  }

  function absen(card) {
    if (sibuk || !card.classList.contains('klik')) return;
    if (!navigator.geolocation) {
      tampilPesan('Browser Anda tidak mendukung GPS.', 'err');
      return;
    }
    sibuk = true;
    const teksAsal = noteEl(card).querySelector('span').textContent;
    card.dataset.ikon = card.querySelector('.icon-box i').className;
    card.classList.add('loading');
    card.querySelector('.icon-box i').className = 'bi bi-arrow-repeat';
    noteText(card, 'Mencari lokasi...', 'bi-geo-alt-fill', true);
    tampilPesan('Mengambil lokasi GPS Anda...', '');

    navigator.geolocation.getCurrentPosition(function (pos) {
      noteText(card, 'Mengirim...', 'bi-send-fill', true);
      const fd = new FormData();
      fd.append('aksi', 'absen');
      fd.append('lat', pos.coords.latitude);
      fd.append('lng', pos.coords.longitude);
      fd.append('akurasi', pos.coords.accuracy || 0);

      fetch(URL_ABSEN, { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function (r) { return r.text(); })
        .then(function (text) {
          let data;
          try { data = JSON.parse(text); }
          catch (e) { throw new Error('Respons server tidak valid. Coba muat ulang halaman, atau gunakan menu Absen GPS.'); }

          if (data.ok) {
            if (data.tipe === 'Masuk') {
              jadikanSelesai(cardMasuk, 'timeMasuk', data.jam);
              aktifkan(cardPulang);
              const hs = document.getElementById('heroStatus');
              if (hs) hs.textContent = 'Sudah presensi';
            } else {
              jadikanSelesai(cardPulang, 'timePulang', data.jam);
            }
            sibuk = false;
            tampilPesan(data.pesan, 'ok');
          } else {
            pulihkan(card, teksAsal);
            tampilPesan(data.pesan || 'Absen gagal.', 'err');
          }
        })
        .catch(function (err) {
          pulihkan(card, teksAsal);
          tampilPesan(err.message || 'Terjadi kesalahan jaringan. Coba lagi.', 'err');
        });
    }, function () {
      pulihkan(card, teksAsal);
      tampilPesan('Gagal mengambil lokasi. Aktifkan GPS dan izinkan akses lokasi untuk situs ini.', 'err');
    }, { enableHighAccuracy: true, timeout: 15000 });
  }

  [cardMasuk, cardPulang].forEach(function (card) {
    if (!card) return;
    card.addEventListener('click', function () { absen(card); });
    card.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); absen(card); }
    });
  });
})();
</script>
<?php endif; ?>

<?php
$extraScripts = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>';
if (in_array($user['role'], ['admin','guru','wali_kelas'])) {
    $extraScripts .= "
    <script>
    const ctx = document.getElementById('chartAbsensi');
    Chart.defaults.font.family = 'Inter';
    new Chart(ctx, {
      type: 'bar',
      data: {
        labels: " . json_encode($labels7) . ",
        datasets: [
          { label: 'Hadir', data: " . json_encode($dataHadir) . ", backgroundColor: '#16A34A' },
          { label: 'Izin',  data: " . json_encode($dataIzin)  . ", backgroundColor: '#EA7A1B' },
          { label: 'Sakit', data: " . json_encode($dataSakit) . ", backgroundColor: '#2563EB' },
          { label: 'Alpa',  data: " . json_encode($dataAlpa)  . ", backgroundColor: '#DC2626' }
        ]
      },
      options: {
        responsive: true,
        plugins: { legend: { labels: { boxWidth: 10, font: { size: 11 } } } },
        scales: {
          x: { stacked: true, grid: { display: false } },
          y: { stacked: true, beginAtZero: true, grid: { color: '#E5E7EE' } }
        }
      }
    });
    </script>";
}
include __DIR__ . '/includes/footer.php';
?>