<?php
// Set timezone eksplisit — INI PENYEBAB PALING UMUM absen GPS gagal padahal
// jarak sudah dekat: kalau timezone server tidak diset, PHP default ke UTC,
// sehingga jam yang dicek meleset dari jam asli di Indonesia.
// Sesuaikan jika sekolah Anda di zona waktu lain:
//   WIB (Jakarta/Sumatera/Jawa/Kalbar-Kalteng) -> 'Asia/Jakarta'
//   WITA (Bali/NTB/NTT/Kalimantan lainnya/Sulawesi) -> 'Asia/Makassar'
//   WIT (Maluku/Papua) -> 'Asia/Jayapura'
date_default_timezone_set('Asia/Makassar');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/libur.php';
requireRole(['siswa']);
$pageTitle = 'Absen GPS';
$user = currentUser();

// Ubah total menit menjadi teks: 45 -> "45 menit", 75 -> "1 jam 15 menit"
function teksKeterlambatan(int $menit): string {
    if ($menit < 60) return $menit . ' menit';
    $j = intdiv($menit, 60);
    $m = $menit % 60;
    return $j . ' jam' . ($m > 0 ? ' ' . $m . ' menit' : '');
}

$stmtSiswa = $pdo->prepare("SELECT s.*, k.nama_kelas FROM siswa s LEFT JOIN kelas k ON s.kelas_id = k.id WHERE s.id = ?");
$stmtSiswa->execute([$user['siswa_id']]);
$siswa = $stmtSiswa->fetch();

$lokasi = $pdo->query("SELECT * FROM lokasi_sekolah ORDER BY id DESC LIMIT 1")->fetch();

// Absen masuk setelah jam_selesai TETAP DITERIMA (dicatat terlambat) sampai jam pulang dibuka.
// Setelah itu absen masuk ditutup (siswa harus diinput manual oleh guru/admin).
$batasAkhirMasuk = null;
if ($lokasi) {
    $batasAkhirMasuk = ($lokasi['jam_pulang_mulai'] > $lokasi['jam_selesai']) ? $lokasi['jam_pulang_mulai'] : '23:59:59';
}

$hariIni = date('Y-m-d');
$stmtAbsenHariIni = $pdo->prepare("SELECT * FROM absensi WHERE siswa_id = ? AND tanggal = ?");
$stmtAbsenHariIni->execute([$user['siswa_id'], $hariIni]);
$absenHariIni = $stmtAbsenHariIni->fetch();

$sudahMasuk  = $absenHariIni && $absenHariIni['status'] === 'Hadir';
$sudahPulang = $absenHariIni && !empty($absenHariIni['jam_pulang']);

// Menit terlambat yang sudah tercatat hari ini (kolom terlambat_menit; cadangan: baca dari keterangan)
$terlambatSaya = 0;
if ($absenHariIni) {
    if (isset($absenHariIni['terlambat_menit'])) {
        $terlambatSaya = (int)$absenHariIni['terlambat_menit'];
    } elseif (preg_match('/terlambat (\d+) menit/i', (string)($absenHariIni['keterangan'] ?? ''), $mm)) {
        $terlambatSaya = (int)$mm[1];
    }
}

$infoLibur = cekHariLibur($pdo, $hariIni);

// Tahap absen yang berlaku sekarang untuk siswa ini: 'masuk', 'pulang', 'selesai', atau 'libur'
if ($infoLibur['libur'] && !$sudahMasuk) {
    $tahap = 'libur';
} elseif (!$sudahMasuk) {
    $tahap = 'masuk';
} elseif (!$sudahPulang) {
    $tahap = 'pulang';
} else {
    $tahap = 'selesai';
}

// Peringatan di halaman bila jam masuk sudah lewat (masih bisa absen, tetapi terlambat) atau sudah ditutup
$infoTelatSekarang = null;
if ($tahap === 'masuk' && $lokasi) {
    $sekarang = date('H:i:s');
    if ($sekarang > $lokasi['jam_selesai']) {
        if ($sekarang <= $batasAkhirMasuk) {
            $infoTelatSekarang = ['status' => 'telat', 'menit' => (int)ceil((strtotime($sekarang) - strtotime($lokasi['jam_selesai'])) / 60)];
        } else {
            $infoTelatSekarang = ['status' => 'tutup'];
        }
    }
}

// ==== Proses absen (dipanggil via fetch AJAX) ====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'absen') {
    header('Content-Type: application/json');

    if (!$siswa || !$siswa['kelas_id']) {
        echo json_encode(['ok' => false, 'pesan' => 'Akun Anda belum terhubung ke data kelas. Hubungi admin sekolah.']);
        exit;
    }
    if (!$lokasi) {
        echo json_encode(['ok' => false, 'pesan' => 'Lokasi absensi GPS belum diatur oleh admin sekolah.']);
        exit;
    }

    // Ambil ulang status absen hari ini secara real-time (hindari race condition
    // / status basi dari saat halaman pertama kali dimuat).
    $stmtCek = $pdo->prepare("SELECT * FROM absensi WHERE siswa_id = ? AND tanggal = ?");
    $stmtCek->execute([$user['siswa_id'], $hariIni]);
    $absenSaatIni = $stmtCek->fetch();
    $sudahMasukSaatIni  = $absenSaatIni && $absenSaatIni['status'] === 'Hadir';
    $sudahPulangSaatIni = $absenSaatIni && !empty($absenSaatIni['jam_pulang']);

    if ($sudahMasukSaatIni && $sudahPulangSaatIni) {
        echo json_encode(['ok' => false, 'pesan' => 'Anda sudah absen masuk dan pulang hari ini.']);
        exit;
    }

    // Blokir absen MASUK baru di hari libur (dicek ulang di server, bukan cuma
    // dari tampilan halaman, supaya tidak bisa diakali). Kalau siswa sudah
    // absen masuk sebelumnya (kasus jarang, misalnya libur ditambahkan admin
    // belakangan), absen pulang tetap diperbolehkan.
    if (!$sudahMasukSaatIni && $infoLibur['libur']) {
        echo json_encode(['ok' => false, 'pesan' => "Hari ini libur ({$infoLibur['keterangan']}), absen tidak dibuka."]);
        exit;
    }

    // Tentukan tipe absen di server (jangan percaya input klien) berdasarkan status saat ini
    $tipe = !$sudahMasukSaatIni ? 'Masuk' : 'Pulang';

    $lat = (float)($_POST['lat'] ?? 0);
    $lng = (float)($_POST['lng'] ?? 0);
    $akurasi = (float)($_POST['akurasi'] ?? 0);

    if (!$lat || !$lng) {
        echo json_encode(['ok' => false, 'pesan' => 'Gagal membaca lokasi GPS.']);
        exit;
    }

    $jarak = round(hitungJarakMeter($lokasi['latitude'], $lokasi['longitude'], $lat, $lng), 2);
    $jamSekarang = date('H:i:s');

    if ($tipe === 'Masuk') {
        $jamBuka       = $lokasi['jam_mulai'];
        $jamTutup      = $lokasi['jam_selesai'];      // batas tepat waktu
        $jamBatasAkhir = $batasAkhirMasuk;            // batas terlambat yang masih diterima
    } else {
        $jamBuka       = $lokasi['jam_pulang_mulai'];
        $jamTutup      = $lokasi['jam_pulang_selesai'];
        $jamBatasAkhir = $jamTutup;
    }

    $dalamRadius = $jarak <= $lokasi['radius_meter'];
    $dalamJam = ($jamSekarang >= $jamBuka) && ($jamSekarang <= $jamBatasAkhir);

    // Hitung keterlambatan (hanya absen masuk, hanya jika lewat batas tepat waktu)
    $terlambatMenit = 0;
    if ($tipe === 'Masuk' && $jamSekarang > $jamTutup) {
        $terlambatMenit = (int)ceil((strtotime($jamSekarang) - strtotime($jamTutup)) / 60);
    }

    $status = 'Ditolak';
    $alasan = null;
    $jamTampil = substr($jamSekarang, 0, 5);
    if (!$dalamJam) {
        if ($tipe === 'Masuk') {
            if ($jamSekarang < $jamBuka) {
                $alasan = "Absen masuk belum dibuka. Sekarang pukul {$jamTampil}, dibuka mulai " . substr($jamBuka, 0, 5) . '.';
            } else {
                $alasan = "Absen masuk sudah ditutup (batas pukul " . substr($jamBatasAkhir, 0, 5) . "). Sekarang pukul {$jamTampil}. Hubungi guru/wali kelas untuk diinput manual.";
            }
        } else {
            $alasan = "Di luar jam absen pulang. Sekarang pukul {$jamTampil}, jam dibuka " . substr($jamBuka,0,5) . '–' . substr($jamTutup,0,5);
        }
    } elseif (!$dalamRadius) {
        $alasan = "Di luar radius sekolah (jarak {$jarak}m, maksimal {$lokasi['radius_meter']}m)";
    } else {
        $status = 'Berhasil';
    }

    // Catat jejak percobaan (selalu, berhasil atau ditolak)
    $stmt = $pdo->prepare("
        INSERT INTO absensi_gps (siswa_id, kelas_id, tipe, tanggal, waktu, latitude, longitude, jarak_meter, akurasi_meter, status, alasan_ditolak)
        VALUES (?,?,?,?,?,?,?,?,?,?,?)
    ");
    $stmt->execute([$user['siswa_id'], $siswa['kelas_id'], $tipe, $hariIni, $jamSekarang, $lat, $lng, $jarak, $akurasi, $status, $alasan]);
    $gpsLogId = (int)$pdo->lastInsertId();

    if ($status === 'Berhasil' && $tipe === 'Masuk') {
        $keterangan = 'Absen mandiri via GPS' . ($terlambatMenit > 0 ? " — terlambat {$terlambatMenit} menit" : '');
        $stmt = $pdo->prepare("
            INSERT INTO absensi (siswa_id, kelas_id, tanggal, status, keterangan, input_oleh)
            VALUES (?,?,?, 'Hadir', ?, 'Sistem GPS')
            ON DUPLICATE KEY UPDATE status='Hadir', keterangan=VALUES(keterangan), input_oleh='Sistem GPS'
        ");
        $stmt->execute([$user['siswa_id'], $siswa['kelas_id'], $hariIni, $keterangan]);

        // Simpan menit terlambat ke kolom khusus. Dibungkus try-catch supaya absen tetap berhasil
        // walaupun migrasi_terlambat.sql belum dijalankan (info tetap ada di kolom keterangan).
        try {
            $pdo->prepare("UPDATE absensi SET terlambat_menit = ? WHERE siswa_id = ? AND tanggal = ?")
                ->execute([$terlambatMenit, $user['siswa_id'], $hariIni]);
            $pdo->prepare("UPDATE absensi_gps SET terlambat_menit = ? WHERE id = ?")
                ->execute([$terlambatMenit, $gpsLogId]);
        } catch (PDOException $e) {
            // kolom terlambat_menit belum ada
        }

        $pesan = $terlambatMenit > 0
            ? "Absen masuk berhasil, tetapi Anda terlambat " . teksKeterlambatan($terlambatMenit) . ". Jarak Anda {$jarak} meter dari sekolah."
            : "Absen masuk berhasil! Jarak Anda {$jarak} meter dari sekolah.";
        echo json_encode([
            'ok' => true, 'tipe' => 'Masuk', 'pesan' => $pesan, 'jam' => date('H:i'), 'jarak' => $jarak,
            'terlambat' => $terlambatMenit, 'terlambat_teks' => $terlambatMenit > 0 ? teksKeterlambatan($terlambatMenit) : '',
        ]);
    } elseif ($status === 'Berhasil' && $tipe === 'Pulang') {
        // Jaga-jaga kalau baris absensi hari ini belum ada (harusnya sudah ada karena tipe Pulang
        // hanya tercapai setelah absen masuk), pakai INSERT ... ON DUPLICATE KEY agar tidak fatal error.
        $stmt = $pdo->prepare("
            INSERT INTO absensi (siswa_id, kelas_id, tanggal, status, keterangan, jam_pulang, latitude_pulang, longitude_pulang, jarak_pulang_meter, input_oleh)
            VALUES (?,?,?, 'Hadir', 'Absen mandiri via GPS', ?, ?, ?, ?, 'Sistem GPS')
            ON DUPLICATE KEY UPDATE jam_pulang=VALUES(jam_pulang), latitude_pulang=VALUES(latitude_pulang), longitude_pulang=VALUES(longitude_pulang), jarak_pulang_meter=VALUES(jarak_pulang_meter), input_oleh='Sistem GPS'
        ");
        $stmt->execute([$user['siswa_id'], $siswa['kelas_id'], $hariIni, $jamSekarang, $lat, $lng, $jarak]);
        echo json_encode(['ok' => true, 'tipe' => 'Pulang', 'pesan' => "Absen pulang berhasil! Jarak Anda {$jarak} meter dari sekolah.", 'jam' => date('H:i'), 'jarak' => $jarak]);
    } else {
        echo json_encode(['ok' => false, 'pesan' => $alasan, 'jarak' => $jarak]);
    }
    exit;
}

// ==== Catatan absen hari ini (lokasi + jam) untuk ditampilkan di kartu & peta ====
// Pulang hanya ditampilkan jika benar-benar tercatat di tabel absensi (jam_pulang terisi).
$logMasuk = $logPulang = null;
if ($siswa && $siswa['kelas_id']) {
    $stmtLog = $pdo->prepare("
        SELECT waktu, latitude, longitude, jarak_meter FROM absensi_gps
        WHERE siswa_id = ? AND tanggal = ? AND tipe = ? AND status = 'Berhasil'
        ORDER BY id DESC LIMIT 1
    ");
    if ($sudahMasuk) {
        $stmtLog->execute([$user['siswa_id'], $hariIni, 'Masuk']);
        $logMasuk = $stmtLog->fetch() ?: null;
    }
    if ($sudahPulang) {
        $stmtLog->execute([$user['siswa_id'], $hariIni, 'Pulang']);
        $logPulang = $stmtLog->fetch() ?: null;
    }
}
$masukManual = $sudahMasuk && !$logMasuk; // dicatat guru/admin, tanpa data GPS
$bentukLog = function ($r) {
    return $r ? [
        'lat'   => (float)$r['latitude'],
        'lng'   => (float)$r['longitude'],
        'jam'   => substr($r['waktu'], 0, 5),
        'jarak' => (int)round($r['jarak_meter']),
    ] : null;
};
$dataLog = ['masuk' => $bentukLog($logMasuk), 'pulang' => $bentukLog($logPulang)];
if ($dataLog['masuk']) { $dataLog['masuk']['terlambat'] = $terlambatSaya; }

include __DIR__ . '/../includes/header.php';
?>

<style>
.absen-dot{ width:30px; height:30px; border-radius:50%; color:#fff; font-weight:800; font-size:.8rem; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.absen-dot.masuk{ background:#16A34A; }
.absen-dot.telat{ background:#D97706; }
.absen-dot.pulang{ background:#EA7A1B; }
.absen-dot.kosong{ background:#CBD5E1; }
.telat-teks{ color:#B45309; font-weight:700; }
</style>

<h4 class="fw-bold mb-3"><i class="bi bi-geo-alt-fill me-2"></i>Absen Kehadiran (GPS)</h4>

<?php if (!$siswa || !$siswa['kelas_id']): ?>
  <div class="card p-4 text-center">
    <i class="bi bi-exclamation-triangle text-warning fs-1 mb-2"></i>
    <p class="mb-0">Akun Anda belum terhubung ke data kelas. Silakan hubungi admin sekolah.</p>
  </div>
<?php elseif (!$lokasi): ?>
  <div class="card p-4 text-center">
    <i class="bi bi-geo-alt text-warning fs-1 mb-2"></i>
    <p class="mb-0">Lokasi absensi GPS belum diatur oleh admin sekolah. Silakan tunggu atau hubungi admin.</p>
  </div>
<?php elseif ($tahap === 'libur'): ?>
  <div class="card p-4 text-center">
    <i class="bi bi-calendar-x-fill text-info fs-1 mb-2"></i>
    <h5 class="fw-bold">Hari Ini Libur</h5>
    <p class="mb-0 text-muted"><?= clean($infoLibur['keterangan']) ?> — tidak ada absen hari ini. Selamat beristirahat!</p>
  </div>
<?php else: ?>

<?php if ($infoTelatSekarang && $infoTelatSekarang['status'] === 'telat'): ?>
  <div id="alertTelat" class="alert alert-warning d-flex align-items-start gap-2 small">
    <i class="bi bi-alarm-fill mt-1"></i>
    <div>
      Batas absen masuk (<strong><?= substr($lokasi['jam_selesai'], 0, 5) ?></strong>) sudah lewat.
      Anda <strong>masih bisa absen sampai pukul <?= substr($batasAkhirMasuk, 0, 5) ?></strong>, tetapi akan tercatat
      <strong>terlambat</strong> (saat ini <?= teksKeterlambatan($infoTelatSekarang['menit']) ?>).
    </div>
  </div>
<?php elseif ($infoTelatSekarang && $infoTelatSekarang['status'] === 'tutup'): ?>
  <div id="alertTelat" class="alert alert-danger d-flex align-items-start gap-2 small">
    <i class="bi bi-x-octagon-fill mt-1"></i>
    <div>Absen masuk sudah ditutup (batas pukul <strong><?= substr($batasAkhirMasuk, 0, 5) ?></strong>). Hubungi guru atau wali kelas untuk diinput manual.</div>
  </div>
<?php endif; ?>

<div class="row g-4">
  <div class="col-lg-5">
    <div class="gps-hero mb-3">
      <div class="d-flex justify-content-between align-items-start mb-2 position-relative">
        <div>
          <div class="small text-white-50">Halo,</div>
          <div class="fw-bold fs-5"><?= clean($siswa['nama_lengkap']) ?></div>
          <div class="small text-white-50"><?= clean($siswa['nama_kelas'] ?? '-') ?> &bull; <?= formatTanggalIndo($hariIni) ?></div>
        </div>
      </div>

      <div class="text-center mb-2">
        <span class="badge-soft <?= $sudahMasuk ? 'good' : '' ?>" style="margin-right:6px;">
          <i class="bi <?= $sudahMasuk ? 'bi-check-circle-fill' : 'bi-box-arrow-in-right' ?>"></i>
          Masuk <?= $sudahMasuk ? '&bull; selesai' : '&bull; belum' ?>
        </span>
        <span class="badge-soft <?= $sudahPulang ? 'good' : '' ?>">
          <i class="bi <?= $sudahPulang ? 'bi-check-circle-fill' : 'bi-box-arrow-right' ?>"></i>
          Pulang <?= $sudahPulang ? '&bull; selesai' : '&bull; belum' ?>
        </span>
      </div>

      <div class="gps-btn-wrap position-relative">
        <?php if ($tahap !== 'selesai'): ?>
          <div class="gps-ring"></div>
          <div class="gps-ring r2"></div>
          <div class="gps-ring r3"></div>
        <?php endif; ?>
        <button type="button" id="btnAbsen" class="gps-btn <?= $tahap === 'selesai' ? 'done' : '' ?>" data-tahap="<?= $tahap ?>" <?= $tahap === 'selesai' ? 'disabled' : '' ?>>
          <i class="bi <?= $tahap === 'selesai' ? 'bi-check-lg' : ($tahap === 'pulang' ? 'bi-box-arrow-right' : 'bi-geo-alt-fill') ?>"></i>
          <span id="btnAbsenLabel"><?php
            if ($tahap === 'masuk') echo 'Absen Masuk';
            elseif ($tahap === 'pulang') echo 'Absen Pulang';
            else echo 'Sudah Absen';
          ?></span>
        </button>
      </div>

      <div class="text-center mt-2 small text-white-75" id="statusText">
        <?php if ($tahap === 'selesai'): ?>
          Tercatat hadir masuk & pulang hari ini
        <?php elseif ($tahap === 'pulang'): ?>
          Sudah absen masuk. Pastikan GPS aktif, lalu tekan tombol untuk absen pulang.
        <?php else: ?>
          Pastikan GPS/lokasi HP Anda aktif, lalu tekan tombol
        <?php endif; ?>
      </div>
    </div>

    <div class="card p-3 mb-3" id="catatanAbsen">
      <h6 class="fw-bold small mb-2"><i class="bi bi-clock-history me-1"></i>Catatan Absen Hari Ini</h6>

      <div class="d-flex align-items-center gap-3 py-2 border-bottom">
        <span class="absen-dot <?= $sudahMasuk ? ($terlambatSaya > 0 ? 'telat' : 'masuk') : 'kosong' ?>" id="dotMasuk">M</span>
        <div class="flex-grow-1">
          <div class="fw-semibold small">Absen Masuk</div>
          <div class="small text-muted" id="infoMasuk">Belum absen</div>
        </div>
        <button type="button" class="btn btn-outline-secondary btn-sm d-none" id="btnPetaMasuk"><i class="bi bi-geo-alt"></i> Peta</button>
      </div>

      <div class="d-flex align-items-center gap-3 pt-2">
        <span class="absen-dot <?= $sudahPulang ? 'pulang' : 'kosong' ?>" id="dotPulang">P</span>
        <div class="flex-grow-1">
          <div class="fw-semibold small">Absen Pulang</div>
          <div class="small text-muted" id="infoPulang">Belum absen</div>
        </div>
        <button type="button" class="btn btn-outline-secondary btn-sm d-none" id="btnPetaPulang"><i class="bi bi-geo-alt"></i> Peta</button>
      </div>
    </div>

    <div class="card p-3">
      <h6 class="fw-bold small mb-2"><i class="bi bi-info-circle me-1"></i>Ketentuan Absen GPS</h6>
      <ul class="small text-muted mb-0 ps-3">
        <li>Absen hanya berhasil jika Anda berada dalam radius <strong><?= (int)$lokasi['radius_meter'] ?> meter</strong> dari sekolah.</li>
        <li>Jam absen masuk pukul <strong><?= substr($lokasi['jam_mulai'],0,5) ?></strong> s/d <strong><?= substr($lokasi['jam_selesai'],0,5) ?></strong>. Lewat dari jam itu Anda <strong>masih bisa absen sampai pukul <?= substr($batasAkhirMasuk,0,5) ?></strong>, tetapi tercatat <strong>terlambat</strong> beserta lama keterlambatannya.</li>
        <li>Jam absen pulang pukul <strong><?= substr($lokasi['jam_pulang_mulai'],0,5) ?></strong> s/d <strong><?= substr($lokasi['jam_pulang_selesai'],0,5) ?></strong> (waktu server: <strong><?= date('H:i') ?></strong> sekarang).</li>
        <li>Absen pulang hanya bisa dilakukan setelah absen masuk berhasil.</li>
        <li>Aktifkan izin lokasi (GPS) pada browser HP Anda.</li>
        <li>Masing-masing (masuk & pulang) hanya bisa dilakukan satu kali per hari.</li>
      </ul>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="card p-3">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h6 class="fw-bold mb-0">Posisi Anda</h6>
        <span class="badge-soft gps" id="jarakBadge">Menunggu lokasi...</span>
      </div>
      <div id="map" style="height:360px; border-radius:12px;"></div>
      <div class="small text-muted mt-2 d-flex gap-3 flex-wrap">
        <span><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#16A34A;"></span> Lokasi absen masuk</span>
        <span><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#EA7A1B;"></span> Lokasi absen pulang</span>
        <span><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#4338CA;"></span> Posisi Anda sekarang</span>
      </div>
    </div>
  </div>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const schoolLat = <?= (float)$lokasi['latitude'] ?>;
const schoolLng = <?= (float)$lokasi['longitude'] ?>;
const radius = <?= (int)$lokasi['radius_meter'] ?>;
let tahapSaatIni = <?= json_encode($tahap) ?>; // 'masuk' | 'pulang' | 'selesai'

const map = L.map('map').setView([schoolLat, schoolLng], 16);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap' }).addTo(map);
L.marker([schoolLat, schoolLng]).addTo(map).bindPopup('Lokasi Sekolah');
L.circle([schoolLat, schoolLng], { radius: radius, color: '#0D9488', fillOpacity: 0.12 }).addTo(map);

// ---- Catatan absen hari ini: data dari server ----
const logAbsen = <?= json_encode($dataLog) ?>;
const masukManual = <?= json_encode($masukManual) ?>;
const terlambatManual = <?= (int)$terlambatSaya ?>;
let posisiSaya = null;
const markerAbsen = {};
const namaAbsen = { masuk: 'Absen Masuk', pulang: 'Absen Pulang' };

// 75 -> "1 jam 15 menit", 45 -> "45 menit"
function teksTelat(m) {
  if (m < 60) return m + ' menit';
  const j = Math.floor(m / 60), s = m % 60;
  return j + ' jam' + (s > 0 ? ' ' + s + ' menit' : '');
}

function ikonAbsen(huruf, warna) {
  return L.divIcon({
    className: '',
    html: '<div style="width:28px;height:28px;border-radius:50%;background:' + warna + ';color:#fff;font-weight:800;font-size:.8rem;display:flex;align-items:center;justify-content:center;border:3px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,.4);">' + huruf + '</div>',
    iconSize: [28, 28], iconAnchor: [14, 14]
  });
}

function pasangMarkerAbsen(kunci) {
  const d = logAbsen[kunci];
  if (!d) return;
  if (markerAbsen[kunci]) map.removeLayer(markerAbsen[kunci]);
  const telat = (kunci === 'masuk' && d.terlambat > 0) ? '<br><strong style="color:#B45309;">Terlambat ' + teksTelat(d.terlambat) + '</strong>' : '';
  markerAbsen[kunci] = L.marker([d.lat, d.lng], {
    icon: ikonAbsen(kunci === 'masuk' ? 'M' : 'P', kunci === 'masuk' ? (d.terlambat > 0 ? '#D97706' : '#16A34A') : '#EA7A1B')
  }).addTo(map).bindPopup('<strong>' + namaAbsen[kunci] + '</strong><br>Pukul ' + d.jam + '<br>' + d.jarak + ' m dari sekolah' + telat);
}

function tampilkanInfoAbsen(kunci) {
  const cap = kunci === 'masuk' ? 'Masuk' : 'Pulang';
  const info = document.getElementById('info' + cap);
  const dot  = document.getElementById('dot' + cap);
  const btn  = document.getElementById('btnPeta' + cap);
  const d = logAbsen[kunci];
  if (d) {
    info.textContent = 'Pukul ' + d.jam + ' \u00B7 ' + d.jarak + ' m dari sekolah';
    if (kunci === 'masuk' && d.terlambat > 0) {
      const t = document.createElement('div');
      t.className = 'telat-teks';
      t.textContent = 'Terlambat ' + teksTelat(d.terlambat);
      info.appendChild(t);
      dot.className = 'absen-dot telat';
    } else {
      dot.className = 'absen-dot ' + kunci;
    }
    btn.classList.remove('d-none');
  } else if (kunci === 'masuk' && masukManual) {
    info.textContent = 'Tercatat hadir (diinput guru/admin, tanpa data lokasi)';
    if (terlambatManual > 0) {
      const t = document.createElement('div');
      t.className = 'telat-teks';
      t.textContent = 'Terlambat ' + teksTelat(terlambatManual);
      info.appendChild(t);
      dot.className = 'absen-dot telat';
    } else {
      dot.className = 'absen-dot masuk';
    }
  }
}

function fitSemua() {
  const titik = [[schoolLat, schoolLng]];
  if (posisiSaya) titik.push(posisiSaya);
  ['masuk', 'pulang'].forEach(function (k) { if (logAbsen[k]) titik.push([logAbsen[k].lat, logAbsen[k].lng]); });
  map.fitBounds(L.latLngBounds(titik), { padding: [40, 40], maxZoom: 18 });
}

// Dipanggil setelah absen berhasil: tambahkan marker & perbarui kartu tanpa reload halaman
function catatAbsenBerhasil(data, pos) {
  const kunci = data.tipe === 'Masuk' ? 'masuk' : 'pulang';
  const jarak = (data.jarak !== undefined) ? data.jarak
    : haversine(schoolLat, schoolLng, pos.coords.latitude, pos.coords.longitude);
  logAbsen[kunci] = { lat: pos.coords.latitude, lng: pos.coords.longitude, jam: data.jam, jarak: Math.round(jarak), terlambat: data.terlambat || 0 };
  pasangMarkerAbsen(kunci);
  tampilkanInfoAbsen(kunci);
  fitSemua();
}

['masuk', 'pulang'].forEach(function (k) {
  const cap = k === 'masuk' ? 'Masuk' : 'Pulang';
  document.getElementById('btnPeta' + cap).addEventListener('click', function () {
    const d = logAbsen[k];
    if (!d) return;
    map.setView([d.lat, d.lng], 18);
    if (markerAbsen[k]) markerAbsen[k].openPopup();
  });
});

let myMarker = null;
function haversine(lat1, lng1, lat2, lng2) {
  const R = 6371000;
  const dLat = (lat2-lat1) * Math.PI/180;
  const dLng = (lng2-lng1) * Math.PI/180;
  const a = Math.sin(dLat/2)**2 + Math.cos(lat1*Math.PI/180)*Math.cos(lat2*Math.PI/180)*Math.sin(dLng/2)**2;
  return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
}

function tampilkanPosisi(lat, lng) {
  if (myMarker) map.removeLayer(myMarker);
  myMarker = L.marker([lat, lng], {
    icon: L.divIcon({ className: '', html: '<div style="width:16px;height:16px;background:#4338CA;border:3px solid #fff;border-radius:50%;box-shadow:0 2px 6px rgba(0,0,0,.4);"></div>' })
  }).addTo(map).bindPopup('Posisi Anda');
  const jarak = Math.round(haversine(schoolLat, schoolLng, lat, lng));
  const badge = document.getElementById('jarakBadge');
  badge.textContent = jarak + ' meter dari sekolah';
  badge.className = 'badge-soft ' + (jarak <= radius ? 'good' : 'bad');
  posisiSaya = [lat, lng];
  fitSemua();
}

// Tampilkan catatan absen yang sudah ada hari ini
['masuk', 'pulang'].forEach(function (k) { pasangMarkerAbsen(k); tampilkanInfoAbsen(k); });
fitSemua();

if (navigator.geolocation) {
  navigator.geolocation.getCurrentPosition(
    (pos) => tampilkanPosisi(pos.coords.latitude, pos.coords.longitude),
    () => { document.getElementById('jarakBadge').textContent = 'Gagal ambil lokasi'; },
    { enableHighAccuracy: true, timeout: 10000 }
  );
}

document.getElementById('btnAbsen')?.addEventListener('click', function () {
  if (tahapSaatIni === 'selesai') return;
  const btn = this;
  const label = document.getElementById('btnAbsenLabel');
  const statusText = document.getElementById('statusText');
  const labelAksi = tahapSaatIni === 'pulang' ? 'Absen Pulang' : 'Absen Masuk';
  btn.disabled = true;
  label.textContent = 'Mencari lokasi...';

  if (!navigator.geolocation) {
    statusText.textContent = 'Browser tidak mendukung GPS.';
    btn.disabled = false;
    label.textContent = labelAksi;
    return;
  }

  navigator.geolocation.getCurrentPosition(function (pos) {
    label.textContent = 'Mengirim...';
    const fd = new FormData();
    fd.append('aksi', 'absen');
    fd.append('lat', pos.coords.latitude);
    fd.append('lng', pos.coords.longitude);
    fd.append('akurasi', pos.coords.accuracy || 0);

    fetch(window.location.href, { method: 'POST', body: fd })
      .then(r => r.json())
      .then(data => {
        if (data.ok) {
          catatAbsenBerhasil(data, pos);
          if (data.tipe === 'Masuk') {
            // Absen masuk selesai -> lanjut ke tahap pulang (tetap aktif, tunggu jam pulang)
            tahapSaatIni = 'pulang';
            btn.disabled = false;
            btn.querySelector('i').className = 'bi bi-box-arrow-right';
            label.textContent = 'Absen Pulang';
            statusText.textContent = data.terlambat > 0
              ? 'Absen masuk tercatat pukul ' + data.jam + ' (terlambat ' + data.terlambat_teks + '). Nanti tekan tombol lagi untuk absen pulang.'
              : 'Absen masuk tercatat pukul ' + data.jam + '. Nanti tekan tombol lagi untuk absen pulang.';
            document.getElementById('alertTelat')?.remove();
          } else {
            // Absen pulang selesai -> semua tahap tuntas
            tahapSaatIni = 'selesai';
            btn.disabled = true;
            btn.classList.add('done');
            btn.querySelector('i').className = 'bi bi-check-lg';
            label.textContent = 'Sudah Absen';
            statusText.textContent = 'Absen pulang tercatat pukul ' + data.jam + '. Sampai jumpa besok!';
            document.querySelectorAll('.gps-ring').forEach(el => el.remove());
          }
        } else {
          btn.classList.add('rejected');
          btn.querySelector('i').className = 'bi bi-x-lg';
          label.textContent = 'Absen Gagal';
          statusText.textContent = data.pesan;
          setTimeout(() => {
            btn.disabled = false;
            btn.classList.remove('rejected');
            btn.querySelector('i').className = tahapSaatIni === 'pulang' ? 'bi bi-box-arrow-right' : 'bi bi-geo-alt-fill';
            label.textContent = tahapSaatIni === 'pulang' ? 'Absen Pulang' : 'Coba Lagi';
          }, 3500);
        }
      })
      .catch(() => {
        statusText.textContent = 'Terjadi kesalahan jaringan. Coba lagi.';
        btn.disabled = false;
        label.textContent = labelAksi;
      });

    tampilkanPosisi(pos.coords.latitude, pos.coords.longitude);
  }, function () {
    statusText.textContent = 'Gagal mengambil lokasi. Aktifkan GPS & izin lokasi.';
    btn.disabled = false;
    label.textContent = labelAksi;
  }, { enableHighAccuracy: true, timeout: 15000 });
});
</script>

<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>