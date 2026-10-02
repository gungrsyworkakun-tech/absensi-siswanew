<?php
// Jadwal mengajar guru, DIATUR PER TANGGAL.
// Jam pelajaran pertama & terakhir pada tanggal itu dipakai sebagai acuan absen guru.
// - Admin           : menambah, menyalin, dan menghapus jadwal semua guru.
// - Kepala sekolah  : melihat semua jadwal.
// - Guru/wali kelas : melihat jadwal sendiri.
//
// Cara kerja: pilih Semester + Tahun Ajaran, lalu tiap jadwal cukup diberi TANGGAL dan BULAN.
// Tahun mengikuti semester (Ganjil = Jul–Des tahun awal, Genap = Jan–Jun tahun akhir) dan nama hari
// (Senin, Selasa, ...) terisi otomatis dari tanggalnya, jadi jadwal Senin dan Selasa bisa berbeda.
date_default_timezone_set('Asia/Makassar');

require_once __DIR__ . '/../includes/auth.php';

const GJ_PENGELOLA = ['admin'];
const GJ_PEMANTAU  = ['admin', 'kepala_sekolah'];

$user = currentUser();
$pageTitle = 'Jadwal Mengajar';
requireRole(['guru', 'wali_kelas', 'admin', 'kepala_sekolah']);

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

/* ================= Helper ================= */
function gjCsrfToken() {
    if (empty($_SESSION['gj_csrf'])) { $_SESSION['gj_csrf'] = bin2hex(random_bytes(16)); }
    return $_SESSION['gj_csrf'];
}
function gjCsrfValid($t) {
    return !empty($_SESSION['gj_csrf']) && is_string($t) && hash_equals($_SESSION['gj_csrf'], $t);
}
function gjWaktu($s) { return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string)$s) ? $s . ':00' : null; }

function gjSemesterSekarang() { return (int)date('n') >= 7 ? 'Ganjil' : 'Genap'; }
function gjTahunAjaranSekarang() {
    $bulan = (int)date('n');
    $tahun = (int)date('Y');
    $awal  = $bulan >= 7 ? $tahun : $tahun - 1;
    return $awal . '/' . ($awal + 1);
}
function gjOpsiTahun($rentang = 3) {
    $bulan = (int)date('n');
    $tahun = (int)date('Y');
    $awalSekarang = $bulan >= 7 ? $tahun : $tahun - 1;
    $opsi = [];
    for ($i = -$rentang; $i <= $rentang; $i++) {
        $a = $awalSekarang + $i;
        $opsi[] = $a . '/' . ($a + 1);
    }
    return $opsi;
}
// Rentang tanggal sebuah periode: Ganjil = 1 Jul – 31 Des (tahun awal), Genap = 1 Jan – 30 Jun (tahun akhir)
function gjRentangPeriode($semester, $tahun) {
    $a = (int)substr($tahun, 0, 4);
    return $semester === 'Ganjil' ? [$a . '-07-01', $a . '-12-31'] : [($a + 1) . '-01-01', ($a + 1) . '-06-30'];
}
function gjNamaBulan() {
    return [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
}
function gjNamaHariLengkap() {
    return [1 => 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
}
function gjTglIndo($d) {
    $b = gjNamaBulan();
    $t = strtotime($d);
    return date('j', $t) . ' ' . substr($b[(int)date('n', $t)], 0, 3) . ' ' . date('Y', $t);
}
// "Senin, 5 Oktober 2026"
function gjTglPanjang($d) {
    $b = gjNamaBulan();
    $h = gjNamaHariLengkap();
    $t = strtotime($d);
    return $h[(int)date('N', $t)] . ', ' . date('j', $t) . ' ' . $b[(int)date('n', $t)] . ' ' . date('Y', $t);
}
function gjGabungTanggal($y, $m, $d) {
    $y = (int)$y; $m = (int)$m; $d = (int)$d;
    return checkdate($m, $d, $y) ? sprintf('%04d-%02d-%02d', $y, $m, $d) : null;
}
// Teks "berlaku" untuk jadwal berulang versi lama (yang tidak terikat satu tanggal)
function gjRentangTeks($mulai, $sampai) {
    if (!$mulai && !$sampai) { return 'Tiap minggu, sepanjang semester'; }
    if ($mulai && $mulai === $sampai) { return gjTglIndo($mulai); }
    return 'Tiap minggu, ' . ($mulai ? gjTglIndo($mulai) : 'awal semester') . ' – ' . ($sampai ? gjTglIndo($sampai) : 'akhir semester');
}
// Apakah ada tanggal bergaris hari ke-$hari (1=Senin..7=Minggu) di dalam rentang [$dari, $sampai]?
function gjAdaHari($hari, $dari, $sampai) {
    if ($dari > $sampai) { return false; }
    $d = new DateTime($dari);
    $e = new DateTime($sampai);
    for ($i = 0; $i < 7 && $d <= $e; $i++) {
        if ((int)$d->format('N') === (int)$hari) { return true; }
        $d->modify('+1 day');
    }
    return false;
}
// Cari jadwal lain (periode sama) yang bentrok pada tanggal tertentu: jam beririsan untuk guru / kelas yang sama.
// Jadwal berulang versi lama ikut diperhitungkan.
function gjCariBentrok(PDO $pdo, $kolom, $id, $semester, $tahun, $hari, $jamMulai, $jamSelesai, $berMulai, $berSampai) {
    if (!in_array($kolom, ['guru_id', 'kelas_id'], true)) { return null; }
    list($pAwal, $pAkhir) = gjRentangPeriode($semester, $tahun);
    $stmt = $pdo->prepare("
        SELECT j.*, m.nama_mapel
        FROM jadwal_mengajar j JOIN mata_pelajaran m ON j.mapel_id = m.id
        WHERE j.$kolom = ? AND j.semester = ? AND j.tahun_ajaran = ? AND j.hari = ? AND j.jam_mulai < ? AND j.jam_selesai > ?");
    $stmt->execute([$id, $semester, $tahun, $hari, $jamSelesai, $jamMulai]);
    $a1 = $berMulai ?: $pAwal;
    $a2 = $berSampai ?: $pAkhir;
    foreach ($stmt->fetchAll() as $r) {
        $b1 = $r['berlaku_mulai'] ?: $pAwal;
        $b2 = $r['berlaku_sampai'] ?: $pAkhir;
        $awal = max($a1, $b1);
        $akhir = min($a2, $b2);
        if ($awal <= $akhir && gjAdaHari($hari, $awal, $akhir)) { return $r; }
    }
    return null;
}
// Query string periode (+ guru, + bulan) untuk link/redirect
function gjQuery($semester, $tahun_ajaran, $guruId = null, $bulan = null) {
    $q = ['semester' => $semester, 'tahun_ajaran' => $tahun_ajaran];
    if ($guruId) { $q['guru_id'] = $guruId; }
    if ($bulan)  { $q['bulan'] = $bulan; }
    return http_build_query($q);
}
// Dua dropdown: Tanggal (1–31) dan Bulan (hanya bulan di dalam semester). Tahun mengikuti semester.
function gjSelectTgl($prefix, $semester, $tahun, $default) {
    $namaBulan = gjNamaBulan();
    list($pAwal, $pAkhir) = gjRentangPeriode($semester, $tahun);
    $bulanAwal = (int)substr($pAwal, 5, 2);
    $bulanAkhir = (int)substr($pAkhir, 5, 2);
    $pilih = ($default >= $pAwal && $default <= $pAkhir) ? $default : $pAwal;
    $m0 = (int)substr($pilih, 5, 2);
    $d0 = (int)substr($pilih, 8, 2);

    $h  = '<div class="d-flex gap-2">';
    $h .= '<select name="' . $prefix . '_d" id="' . $prefix . '_d" class="form-select" style="max-width:90px" aria-label="Tanggal">';
    for ($d = 1; $d <= 31; $d++) { $h .= '<option value="' . $d . '"' . ($d === $d0 ? ' selected' : '') . '>' . $d . '</option>'; }
    $h .= '</select>';
    $h .= '<select name="' . $prefix . '_m" id="' . $prefix . '_m" class="form-select" aria-label="Bulan">';
    for ($m = $bulanAwal; $m <= $bulanAkhir; $m++) { $h .= '<option value="' . $m . '"' . ($m === $m0 ? ' selected' : '') . '>' . $namaBulan[$m] . '</option>'; }
    $h .= '</select></div>';
    return $h;
}

/* ================= Cek migrasi ================= */
try {
    $pdo->query("SELECT 1 FROM jadwal_mengajar LIMIT 1");
    $pdo->query("SELECT toleransi_menit FROM pengaturan_absen_guru LIMIT 1");
    $pdo->query("SELECT semester, tahun_ajaran FROM jadwal_mengajar LIMIT 1");
    $pdo->query("SELECT berlaku_mulai, berlaku_sampai FROM jadwal_mengajar LIMIT 1");
} catch (PDOException $e) {
    include __DIR__ . '/../includes/header.php';
    echo "<div class='card p-4'><h5 class='fw-bold'><i class='bi bi-exclamation-triangle text-warning me-2'></i>Fitur Jadwal Mengajar belum siap</h5>"
       . "<p class='mb-1'>Import file berikut ke database <b>db_absensi_sekolah</b> melalui phpMyAdmin (aman dijalankan ulang, urut dari atas): "
       . "<b>guru_tambah.sql</b>, <b>guru_profil_jadwal.sql</b>, <b>jadwal_tambah_periode.sql</b>, <b>jadwal_tambah_tanggal.sql</b>.</p>"
       . "<p class='mb-0'>Lalu refresh halaman ini.</p></div>";
    include __DIR__ . '/../includes/footer.php';
    exit;
}

$pemantau = in_array($user['role'], GJ_PEMANTAU, true);
$bisaKelola = in_array($user['role'], GJ_PENGELOLA, true);
$namaBulan = gjNamaBulan();

// ==== Periode yang sedang dilihat/dikelola ====
$semester     = in_array($_GET['semester'] ?? '', ['Ganjil', 'Genap'], true) ? $_GET['semester'] : gjSemesterSekarang();
$tahun_ajaran = $_GET['tahun_ajaran'] ?? gjTahunAjaranSekarang();
if (!preg_match('#^\d{4}/\d{4}$#', $tahun_ajaran)) { $tahun_ajaran = gjTahunAjaranSekarang(); }
$periodeAktif = ($semester === gjSemesterSekarang() && $tahun_ajaran === gjTahunAjaranSekarang());
list($pAwal, $pAkhir) = gjRentangPeriode($semester, $tahun_ajaran);
$tahunPeriode = (int)substr($pAwal, 0, 4);

// ==== Bulan yang ditampilkan: bulan di dalam semester, atau "semua" ====
$opsiBulan = [];
for ($t = strtotime($pAwal); $t <= strtotime($pAkhir); $t = strtotime('+1 month', $t)) {
    $opsiBulan[date('Y-m', $t)] = $namaBulan[(int)date('n', $t)] . ' ' . date('Y', $t);
}
$bulanFilter = $_GET['bulan'] ?? '';
if ($bulanFilter !== 'semua' && !isset($opsiBulan[$bulanFilter])) {
    // Bawaan: bulan berjalan jika masuk semester ini, kalau tidak bulan pertama semester
    reset($opsiBulan);
    $bulanFilter = isset($opsiBulan[date('Y-m')]) ? date('Y-m') : (string)key($opsiBulan);   // kunci pertama (bulan awal semester)
}

$guruList = $pdo->query("SELECT id, nama, role FROM users WHERE role IN ('guru','wali_kelas') ORDER BY nama")->fetchAll();
$guruIdValid = array_map('intval', array_column($guruList, 'id'));

// Guru biasa: otomatis dikunci ke dirinya. Admin / kepala sekolah: boleh memilih (kosong = semua).
$filterGuru = $pemantau ? (int)($_GET['guru_id'] ?? 0) : (int)$user['id'];
if ($pemantau && $filterGuru && !in_array($filterGuru, $guruIdValid, true)) { $filterGuru = 0; }

$mapelList = $pdo->query("SELECT id, nama_mapel FROM mata_pelajaran ORDER BY nama_mapel")->fetchAll();
$kelasList = $pdo->query("SELECT id, nama_kelas FROM kelas ORDER BY tingkat, nama_kelas")->fetchAll();

$hariIni = date('Y-m-d');
$defaultTgl = ($hariIni >= $pAwal && $hariIni <= $pAkhir) ? $hariIni : $pAwal;

$errors = [];
$old = [
    'guru_id' => $filterGuru ?: '', 'mapel_id' => '', 'kelas_id' => '',
    'jam_mulai' => '07:00', 'jam_selesai' => '08:30', 'tanggal' => $defaultTgl,
];

/* ================= POST (admin) ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';
    $kembali = 'guru/jadwal.php?' . gjQuery($semester, $tahun_ajaran, $filterGuru && $pemantau ? $filterGuru : null, $bulanFilter);

    if (!$bisaKelola) {
        $errors[] = 'Anda tidak berhak mengubah jadwal.';
    } elseif (!gjCsrfValid($_POST['csrf'] ?? '')) {
        $errors[] = 'Sesi formulir tidak valid. Muat ulang halaman lalu coba lagi.';

    // ---------- Tambah jadwal pada satu tanggal ----------
    } elseif ($aksi === 'tambah') {
        $gid = (int)($_POST['guru_id'] ?? 0);
        $mid = (int)($_POST['mapel_id'] ?? 0);
        $kid = (int)($_POST['kelas_id'] ?? 0);
        $mulaiIn   = trim($_POST['jam_mulai'] ?? '');
        $selesaiIn = trim($_POST['jam_selesai'] ?? '');
        $semesterIn = in_array($_POST['semester'] ?? '', ['Ganjil', 'Genap'], true) ? $_POST['semester'] : $semester;
        $tahunIn    = trim($_POST['tahun_ajaran'] ?? '') ?: $tahun_ajaran;

        $tanggal = null;
        if (preg_match('#^\d{4}/\d{4}$#', $tahunIn)) {
            list($pAwalIn, $pAkhirIn) = gjRentangPeriode($semesterIn, $tahunIn);
            $tanggal = gjGabungTanggal((int)substr($pAwalIn, 0, 4), $_POST['tgl_m'] ?? 0, $_POST['tgl_d'] ?? 0);
        }
        $old = [
            'guru_id' => $gid ?: '', 'mapel_id' => $mid ?: '', 'kelas_id' => $kid ?: '',
            'jam_mulai' => $mulaiIn, 'jam_selesai' => $selesaiIn, 'tanggal' => $tanggal ?: $defaultTgl,
        ];

        $mulai = gjWaktu($mulaiIn);
        $selesai = gjWaktu($selesaiIn);

        if (!in_array($gid, $guruIdValid, true)) { $errors[] = 'Pilih guru yang valid.'; }
        if (!in_array($mid, array_map('intval', array_column($mapelList, 'id')), true)) { $errors[] = 'Pilih mata pelajaran yang valid.'; }
        if (!in_array($kid, array_map('intval', array_column($kelasList, 'id')), true)) { $errors[] = 'Pilih kelas yang valid.'; }
        if (!$mulai || !$selesai) { $errors[] = 'Format jam tidak valid.'; }
        elseif ($mulai >= $selesai) { $errors[] = 'Jam selesai harus lebih besar dari jam mulai.'; }
        if (!preg_match('#^\d{4}/\d{4}$#', $tahunIn)) { $errors[] = 'Tahun ajaran tidak valid.'; }

        $hari = 0;
        if (!$errors) {
            if (!$tanggal) {
                $errors[] = 'Tanggal tidak valid (misalnya 31 Februari tidak ada).';
            } else {
                $hari = (int)date('N', strtotime($tanggal));
                if ($hari > 5) { $errors[] = gjTglPanjang($tanggal) . ' jatuh pada akhir pekan. Pilih tanggal hari Senin sampai Jumat.'; }
            }
        }

        // ---- Bentrok pada tanggal itu ----
        if (!$errors) {
            $b = gjCariBentrok($pdo, 'guru_id', $gid, $semesterIn, $tahunIn, $hari, $mulai, $selesai, $tanggal, $tanggal);
            if ($b) {
                $errors[] = 'Guru ini sudah mengajar ' . $b['nama_mapel'] . ' pada ' . gjTglPanjang($tanggal) . ' pukul '
                          . substr($b['jam_mulai'], 0, 5) . '–' . substr($b['jam_selesai'], 0, 5) . ', bentrok dengan jadwal ini.';
            }
            $b = gjCariBentrok($pdo, 'kelas_id', $kid, $semesterIn, $tahunIn, $hari, $mulai, $selesai, $tanggal, $tanggal);
            if ($b) {
                $errors[] = 'Kelas tersebut sudah punya ' . $b['nama_mapel'] . ' pada ' . gjTglPanjang($tanggal) . ' pukul '
                          . substr($b['jam_mulai'], 0, 5) . '–' . substr($b['jam_selesai'], 0, 5) . ', bentrok dengan jadwal ini.';
            }
        }

        if (!$errors) {
            // Satu tanggal = berlaku_mulai sama dengan berlaku_sampai
            $stmt = $pdo->prepare("INSERT INTO jadwal_mengajar (guru_id, mapel_id, kelas_id, semester, tahun_ajaran, hari, jam_mulai, jam_selesai, berlaku_mulai, berlaku_sampai) VALUES (?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$gid, $mid, $kid, $semesterIn, $tahunIn, $hari, $mulai, $selesai, $tanggal, $tanggal]);
            setFlash('success', 'Jadwal ditambahkan untuk ' . gjTglPanjang($tanggal) . '.');
            redirect('guru/jadwal.php?' . gjQuery($semesterIn, $tahunIn, $gid, substr($tanggal, 0, 7)));
        }

    // ---------- Hapus ----------
    } elseif ($aksi === 'hapus') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM jadwal_mengajar WHERE id = ?");
        $stmt->execute([$id]);
        setFlash('success', $stmt->rowCount() ? 'Jadwal dihapus.' : 'Jadwal tidak ditemukan.');
        redirect($kembali);

    // ---------- Salin semua jadwal dari satu tanggal ke tanggal lain ----------
    } elseif ($aksi === 'salin_tanggal') {
        $dari = gjGabungTanggal($tahunPeriode, $_POST['dari_m'] ?? 0, $_POST['dari_d'] ?? 0);
        $ke   = gjGabungTanggal($tahunPeriode, $_POST['ke_m'] ?? 0, $_POST['ke_d'] ?? 0);

        if (!$dari || !$ke) {
            $errors[] = 'Tanggal sumber dan tujuan harus valid (misalnya 31 Februari tidak ada).';
        } elseif ($dari === $ke) {
            $errors[] = 'Tanggal sumber dan tujuan tidak boleh sama.';
        } elseif ($dari < $pAwal || $dari > $pAkhir || $ke < $pAwal || $ke > $pAkhir) {
            $errors[] = 'Kedua tanggal harus berada di Semester ' . $semester . ' ' . $tahun_ajaran . '.';
        } elseif ((int)date('N', strtotime($ke)) > 5) {
            $errors[] = gjTglPanjang($ke) . ' jatuh pada akhir pekan. Pilih tanggal tujuan hari Senin sampai Jumat.';
        } else {
            $sql = "SELECT guru_id, mapel_id, kelas_id, jam_mulai, jam_selesai FROM jadwal_mengajar
                    WHERE semester = ? AND tahun_ajaran = ? AND berlaku_mulai = ? AND berlaku_sampai = ?";
            $params = [$semester, $tahun_ajaran, $dari, $dari];
            if ($filterGuru && $pemantau) { $sql .= " AND guru_id = ?"; $params[] = $filterGuru; }
            $sql .= " ORDER BY jam_mulai";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $sumber = $stmt->fetchAll();

            if (empty($sumber)) {
                $errors[] = 'Tidak ada jadwal pada ' . gjTglPanjang($dari) . ' untuk disalin.';
            } else {
                $hariKe = (int)date('N', strtotime($ke));
                $ins = $pdo->prepare("INSERT INTO jadwal_mengajar (guru_id, mapel_id, kelas_id, semester, tahun_ajaran, hari, jam_mulai, jam_selesai, berlaku_mulai, berlaku_sampai) VALUES (?,?,?,?,?,?,?,?,?,?)");
                $disalin = 0; $dilewati = 0;
                foreach ($sumber as $j) {
                    $bg = gjCariBentrok($pdo, 'guru_id', $j['guru_id'], $semester, $tahun_ajaran, $hariKe, $j['jam_mulai'], $j['jam_selesai'], $ke, $ke);
                    $bk = gjCariBentrok($pdo, 'kelas_id', $j['kelas_id'], $semester, $tahun_ajaran, $hariKe, $j['jam_mulai'], $j['jam_selesai'], $ke, $ke);
                    if ($bg || $bk) { $dilewati++; continue; }
                    $ins->execute([$j['guru_id'], $j['mapel_id'], $j['kelas_id'], $semester, $tahun_ajaran, $hariKe, $j['jam_mulai'], $j['jam_selesai'], $ke, $ke]);
                    $disalin++;
                }
                $pesan = "{$disalin} jadwal disalin dari " . gjTglPanjang($dari) . ' ke ' . gjTglPanjang($ke) . '.';
                if ($dilewati) { $pesan .= " {$dilewati} dilewati karena bentrok dengan jadwal yang sudah ada."; }
                setFlash($disalin ? 'success' : 'error', $pesan);
                redirect('guru/jadwal.php?' . gjQuery($semester, $tahun_ajaran, $filterGuru && $pemantau ? $filterGuru : null, substr($ke, 0, 7)));
            }
        }
    } else {
        $errors[] = 'Aksi tidak dikenal.';
    }
}

/* ================= Data jadwal (periode terpilih) ================= */
$sql = "SELECT j.*, m.nama_mapel, k.nama_kelas, u.nama AS nama_guru
        FROM jadwal_mengajar j
        JOIN mata_pelajaran m ON j.mapel_id = m.id
        JOIN kelas k ON j.kelas_id = k.id
        JOIN users u ON j.guru_id = u.id
        WHERE j.semester = ? AND j.tahun_ajaran = ?";
$params = [$semester, $tahun_ajaran];
if ($filterGuru) { $sql .= " AND j.guru_id = ?"; $params[] = $filterGuru; }
$sql .= " ORDER BY j.berlaku_mulai, j.jam_mulai, u.nama";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$perTanggal = [];   // jadwal terikat satu tanggal
$berulang = [];     // jadwal versi lama (tiap minggu), kalau ada
foreach ($stmt->fetchAll() as $r) {
    if ($r['berlaku_mulai'] && $r['berlaku_mulai'] === $r['berlaku_sampai']) {
        if ($bulanFilter !== 'semua' && substr($r['berlaku_mulai'], 0, 7) !== $bulanFilter) { continue; }
        $perTanggal[$r['berlaku_mulai']][] = $r;
    } else {
        $berulang[] = $r;
    }
}
ksort($perTanggal);

$tol = (int)($pdo->query("SELECT toleransi_menit FROM pengaturan_absen_guru ORDER BY id LIMIT 1")->fetchColumn() ?: 0);
$csrf = gjCsrfToken();

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <h4 class="fw-bold mb-0"><i class="bi bi-calendar-week-fill me-2"></i>Jadwal Mengajar</h4>
  <?php if (!$periodeAktif): ?>
    <span class="badge-soft warn">Melihat periode lain, bukan yang sedang berjalan</span>
  <?php endif; ?>
</div>

<?php if ($errors): ?>
  <div class="alert alert-danger">
    <?php foreach ($errors as $er): ?><div><i class="bi bi-exclamation-circle-fill me-1"></i><?= clean($er) ?></div><?php endforeach; ?>
  </div>
<?php endif; ?>

<div class="alert alert-light border small">
  <i class="bi bi-alarm-fill me-1"></i>
  Jadwal diatur <b>per tanggal</b>, jadi hari Senin dan Selasa bisa punya jadwal berbeda. Pada tanggal itu, jam <b>pelajaran pertama</b>
  menjadi acuan absen masuk guru (tepat waktu sampai <b><?= $tol ?> menit</b> setelah pelajaran pertama dimulai), dan absen pulang dibuka
  setelah <b>pelajaran terakhir</b> selesai. Pada tanggal tanpa jadwal berlaku jam standar di menu Kehadiran Guru.
</div>

<div class="card p-3 mb-3">
  <form method="GET" class="row g-2 align-items-end">
    <div class="col-6 col-md-2">
      <label class="form-label small">Semester</label>
      <select name="semester" class="form-select" onchange="this.form.submit()">
        <option value="Ganjil" <?= $semester === 'Ganjil' ? 'selected' : '' ?>>Ganjil</option>
        <option value="Genap" <?= $semester === 'Genap' ? 'selected' : '' ?>>Genap</option>
      </select>
    </div>
    <div class="col-6 col-md-3">
      <label class="form-label small">Tahun Ajaran</label>
      <select name="tahun_ajaran" class="form-select" onchange="this.form.submit()">
        <?php foreach (gjOpsiTahun() as $opt): ?>
          <option value="<?= $opt ?>" <?= $tahun_ajaran === $opt ? 'selected' : '' ?>><?= $opt ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-12 col-md-3">
      <label class="form-label small">Bulan</label>
      <select name="bulan" class="form-select" onchange="this.form.submit()">
        <?php foreach ($opsiBulan as $val => $lbl): ?>
          <option value="<?= $val ?>" <?= $bulanFilter === $val ? 'selected' : '' ?>><?= clean($lbl) ?></option>
        <?php endforeach; ?>
        <option value="semua" <?= $bulanFilter === 'semua' ? 'selected' : '' ?>>Semua bulan</option>
      </select>
    </div>
    <?php if ($pemantau): ?>
    <div class="col-12 col-md-4">
      <label class="form-label small">Tampilkan jadwal guru</label>
      <select name="guru_id" class="form-select" onchange="this.form.submit()">
        <option value="">Semua guru</option>
        <?php foreach ($guruList as $g): ?>
          <option value="<?= (int)$g['id'] ?>" <?= $filterGuru === (int)$g['id'] ? 'selected' : '' ?>><?= clean($g['nama']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>
  </form>
  <div class="small text-muted mt-2">
    <i class="bi bi-info-circle me-1"></i>Semester <?= clean($semester) ?> <?= clean($tahun_ajaran) ?>: <?= clean(gjTglIndo($pAwal)) ?> – <?= clean(gjTglIndo($pAkhir)) ?>
  </div>
</div>

<?php if ($bisaKelola): ?>
<div class="card p-3 mb-4">
  <h6 class="fw-bold mb-3"><i class="bi bi-plus-circle-fill me-1"></i>Tambah Jadwal — Semester <?= clean($semester) ?> <?= clean($tahun_ajaran) ?></h6>
  <form method="POST" id="formTambahJadwal">
    <input type="hidden" name="csrf" value="<?= clean($csrf) ?>">
    <input type="hidden" name="aksi" value="tambah">
    <input type="hidden" name="semester" value="<?= clean($semester) ?>">
    <input type="hidden" name="tahun_ajaran" value="<?= clean($tahun_ajaran) ?>">
    <div class="row g-3">
      <div class="col-md-4">
        <label class="form-label small">Guru</label>
        <select name="guru_id" class="form-select" required>
          <option value="">- pilih guru -</option>
          <?php foreach ($guruList as $g): ?>
            <option value="<?= (int)$g['id'] ?>" <?= (string)$old['guru_id'] === (string)$g['id'] ? 'selected' : '' ?>><?= clean($g['nama']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label small">Mata Pelajaran</label>
        <select name="mapel_id" class="form-select" required>
          <option value="">- pilih mapel -</option>
          <?php foreach ($mapelList as $m): ?>
            <option value="<?= (int)$m['id'] ?>" <?= (string)$old['mapel_id'] === (string)$m['id'] ? 'selected' : '' ?>><?= clean($m['nama_mapel']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label small">Kelas</label>
        <select name="kelas_id" class="form-select" required>
          <option value="">- pilih kelas -</option>
          <?php foreach ($kelasList as $k): ?>
            <option value="<?= (int)$k['id'] ?>" <?= (string)$old['kelas_id'] === (string)$k['id'] ? 'selected' : '' ?>><?= clean($k['nama_kelas']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-6 col-md-3"><label class="form-label small">Jam Mulai</label><input type="time" name="jam_mulai" class="form-control" value="<?= clean($old['jam_mulai']) ?>" required></div>
      <div class="col-6 col-md-3"><label class="form-label small">Jam Selesai</label><input type="time" name="jam_selesai" class="form-control" value="<?= clean($old['jam_selesai']) ?>" required></div>
      <div class="col-md-4">
        <label class="form-label small">Tanggal &amp; Bulan</label>
        <?= gjSelectTgl('tgl', $semester, $tahun_ajaran, $old['tanggal']) ?>
        <div class="form-text fw-semibold" id="info_tgl"></div>
      </div>
      <div class="col-md-2 d-flex align-items-start" style="padding-top:1.7rem;"><button class="btn btn-primary w-100"><i class="bi bi-plus-lg"></i> Tambah</button></div>
    </div>
  </form>

  <hr>
  <div class="small fw-semibold text-muted mb-2">
    <i class="bi bi-copy me-1"></i>Salin semua jadwal dari satu tanggal ke tanggal lain
    <?= $filterGuru ? '(hanya milik guru yang sedang dipilih)' : '' ?>
  </div>
  <form method="POST" class="row g-2 align-items-start">
    <input type="hidden" name="csrf" value="<?= clean($csrf) ?>">
    <input type="hidden" name="aksi" value="salin_tanggal">
    <div class="col-md-4">
      <label class="form-label small mb-1">Dari tanggal</label>
      <?= gjSelectTgl('dari', $semester, $tahun_ajaran, $defaultTgl) ?>
      <div class="form-text" id="info_dari"></div>
    </div>
    <div class="col-md-4">
      <label class="form-label small mb-1">Ke tanggal</label>
      <?= gjSelectTgl('ke', $semester, $tahun_ajaran, $defaultTgl) ?>
      <div class="form-text" id="info_ke"></div>
    </div>
    <div class="col-md-4" style="padding-top:1.7rem;">
      <button class="btn btn-outline-secondary w-100" onclick="return confirm('Salin semua jadwal pada tanggal sumber ke tanggal tujuan? Jadwal yang bentrok akan dilewati.');">
        <i class="bi bi-copy"></i> Salin Jadwal
      </button>
    </div>
  </form>
</div>

<script>
(function () {
  const $ = function (id) { return document.getElementById(id); };
  const TAHUN = <?= (int)$tahunPeriode ?>;
  const NAMA_HARI = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
  const NAMA_BULAN = <?= json_encode(array_values(gjNamaBulan()), JSON_UNESCAPED_UNICODE) ?>;

  function hariDalamBulan(m) { return new Date(TAHUN, m, 0).getDate(); }

  // Nama hari terisi otomatis dari tanggal & bulan yang dipilih
  function tampil(p) {
    const info = $('info_' + p);
    if (!info) return;
    const d = parseInt($(p + '_d').value, 10), m = parseInt($(p + '_m').value, 10);
    const w = new Date(TAHUN, m - 1, d).getDay();
    const libur = (w === 0 || w === 6);
    info.textContent = NAMA_HARI[w] + ', ' + d + ' ' + NAMA_BULAN[m - 1] + ' ' + TAHUN + (libur ? ' — akhir pekan, tidak bisa dijadwalkan' : '');
    info.className = 'form-text fw-semibold ' + (libur ? 'text-danger' : 'text-success');
  }

  // Jumlah tanggal menyesuaikan bulan (Februari 28/29, bulan lain 30/31)
  function sesuaikan(p) {
    const d = $(p + '_d'), m = $(p + '_m');
    if (!d || !m) return;
    const maks = hariDalamBulan(parseInt(m.value, 10));
    const dipilih = Math.min(parseInt(d.value, 10) || 1, maks);
    d.innerHTML = '';
    for (let i = 1; i <= maks; i++) {
      const o = document.createElement('option');
      o.value = i; o.textContent = i;
      if (i === dipilih) o.selected = true;
      d.appendChild(o);
    }
    tampil(p);
  }

  ['tgl', 'dari', 'ke'].forEach(function (p) {
    const d = $(p + '_d'), m = $(p + '_m');
    if (!d || !m) return;
    m.addEventListener('change', function () { sesuaikan(p); });
    d.addEventListener('change', function () { tampil(p); });
    sesuaikan(p);
  });
})();
</script>
<?php endif; ?>

<?php if (empty($perTanggal) && empty($berulang)): ?>
  <div class="card p-4 text-center text-muted">
    Belum ada jadwal mengajar<?= $filterGuru && $pemantau ? ' untuk guru ini' : '' ?>
    <?= $bulanFilter === 'semua' ? ' di Semester ' . clean($semester) . ' ' . clean($tahun_ajaran) : ' pada bulan ' . clean($opsiBulan[$bulanFilter] ?? '') ?>.
    <?php if ($bisaKelola): ?><br><span class="small">Tambahkan lewat formulir di atas, atau salin dari tanggal lain.</span><?php endif; ?>
  </div>
<?php endif; ?>

<?php foreach ($perTanggal as $tgl => $baris):
    $pertama = $baris[0]['jam_mulai'];
    $terakhir = '00:00:00';
    foreach ($baris as $b) { if ($b['jam_selesai'] > $terakhir) { $terakhir = $b['jam_selesai']; } }
    $batasTepat = date('H:i', strtotime("2000-01-01 {$pertama}") + $tol * 60);
    $adalahHariIni = ($tgl === $hariIni);
?>
  <div class="card p-3 mb-3" <?= $adalahHariIni ? 'style="border-color:#131A2E;"' : '' ?>>
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
      <h6 class="fw-bold mb-0">
        <?= clean(gjTglPanjang($tgl)) ?>
        <?php if ($adalahHariIni): ?><span class="badge bg-dark ms-1">Hari ini</span><?php endif; ?>
      </h6>
      <?php if ($filterGuru): ?>
        <span class="small text-muted">
          Absen tepat waktu s/d <b class="text-dark"><?= $batasTepat ?></b> · pulang mulai <b class="text-dark"><?= substr($terakhir, 0, 5) ?></b>
        </span>
      <?php endif; ?>
    </div>
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead class="table-light"><tr><th>Jam</th><th>Mata Pelajaran</th><th>Kelas</th><?php if (!$filterGuru): ?><th>Guru</th><?php endif; ?><?php if ($bisaKelola): ?><th></th><?php endif; ?></tr></thead>
        <tbody>
          <?php foreach ($baris as $b): ?>
            <tr>
              <td class="text-nowrap fw-semibold"><?= substr($b['jam_mulai'], 0, 5) ?>–<?= substr($b['jam_selesai'], 0, 5) ?></td>
              <td><?= clean($b['nama_mapel']) ?></td>
              <td><?= clean($b['nama_kelas']) ?></td>
              <?php if (!$filterGuru): ?><td><?= clean($b['nama_guru']) ?></td><?php endif; ?>
              <?php if ($bisaKelola): ?>
                <td class="text-end">
                  <form method="POST" class="d-inline" onsubmit="return confirm('Hapus jadwal ini?');">
                    <input type="hidden" name="csrf" value="<?= clean($csrf) ?>">
                    <input type="hidden" name="aksi" value="hapus">
                    <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash3"></i></button>
                  </form>
                </td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endforeach; ?>

<?php if (!empty($berulang)):
    $namaHariLengkap = gjNamaHariLengkap();
?>
  <div class="card p-3 mb-3 border-warning">
    <h6 class="fw-bold mb-1"><i class="bi bi-arrow-repeat me-1"></i>Jadwal Berulang (versi lama)</h6>
    <div class="small text-muted mb-2">Jadwal ini dibuat dengan cara lama (berlaku tiap minggu), bukan per tanggal. Masih dipakai untuk acuan absen. Hapus bila sudah diganti jadwal per tanggal.</div>
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead class="table-light"><tr><th>Hari</th><th>Jam</th><th>Mata Pelajaran</th><th>Kelas</th><?php if (!$filterGuru): ?><th>Guru</th><?php endif; ?><th>Berlaku</th><?php if ($bisaKelola): ?><th></th><?php endif; ?></tr></thead>
        <tbody>
          <?php foreach ($berulang as $b): ?>
            <tr>
              <td><?= clean($namaHariLengkap[(int)$b['hari']] ?? '-') ?></td>
              <td class="text-nowrap fw-semibold"><?= substr($b['jam_mulai'], 0, 5) ?>–<?= substr($b['jam_selesai'], 0, 5) ?></td>
              <td><?= clean($b['nama_mapel']) ?></td>
              <td><?= clean($b['nama_kelas']) ?></td>
              <?php if (!$filterGuru): ?><td><?= clean($b['nama_guru']) ?></td><?php endif; ?>
              <td class="small text-muted"><?= clean(gjRentangTeks($b['berlaku_mulai'], $b['berlaku_sampai'])) ?></td>
              <?php if ($bisaKelola): ?>
                <td class="text-end">
                  <form method="POST" class="d-inline" onsubmit="return confirm('Hapus jadwal berulang ini? Jadwal akan hilang dari semua minggu yang dicakupnya.');">
                    <input type="hidden" name="csrf" value="<?= clean($csrf) ?>">
                    <input type="hidden" name="aksi" value="hapus">
                    <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash3"></i></button>
                  </form>
                </td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>