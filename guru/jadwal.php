<?php
// Jadwal mengajar guru. Jam pelajaran inilah yang dipakai sebagai acuan batas "tepat waktu" absen guru.
// - Admin           : menambah & menghapus jadwal semua guru.
// - Kepala sekolah  : melihat semua jadwal.
// - Guru/wali kelas : melihat jadwal sendiri.
//
// Jadwal dikelompokkan per PERIODE (Semester + Tahun Ajaran), jadi tiap semester bisa punya jadwal berbeda.
// Di dalam satu semester, jadwal diatur lewat dua pemilih tanggal (Mulai berlaku & Sampai),
// masing-masing Tanggal - Bulan - Tahun:
//   - Mulai = awal semester & Sampai = akhir semester : berlaku tiap minggu sepanjang semester.
//   - Mulai & Sampai berbeda (mis. 1 Agu – 30 Sep)    : berlaku tiap minggu hanya pada rentang itu.
//   - Mulai = Sampai                                  : hanya satu tanggal (jam pengganti / tukar jadwal).
// Dropdown "Pilih bulan" mengisi kedua tanggal sekaligus, sehingga jadwal per bulan mudah disiapkan jauh hari.
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
function gjTglIndo($d) {
    $b = gjNamaBulan();
    $t = strtotime($d);
    return date('j', $t) . ' ' . substr($b[(int)date('n', $t)], 0, 3) . ' ' . date('Y', $t);
}
function gjGabungTanggal($y, $m, $d) {
    $y = (int)$y; $m = (int)$m; $d = (int)$d;
    return checkdate($m, $d, $y) ? sprintf('%04d-%02d-%02d', $y, $m, $d) : null;
}
function gjRentangTeks($mulai, $sampai) {
    if (!$mulai && !$sampai) { return 'Sepanjang periode'; }
    if ($mulai && $mulai === $sampai) { return 'Sekali, ' . gjTglIndo($mulai); }
    return ($mulai ? gjTglIndo($mulai) : 'awal periode') . ' – ' . ($sampai ? gjTglIndo($sampai) : 'akhir periode');
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
// Cari jadwal lain (periode sama) yang bentrok: hari sama, jam beririsan, DAN tanggal berlakunya beririsan.
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
// Tiga dropdown Tanggal - Bulan - Tahun. Bulan & tahun dibatasi sesuai periode yang dipilih.
// $nilai = nilai yang dikirim sebelumnya (Y-m-d), $default = nilai awal bila belum ada.
function gjSelectTanggal($prefix, $semester, $tahun, $nilai, $default) {
    $namaBulan = gjNamaBulan();
    list($pAwal, $pAkhir) = gjRentangPeriode($semester, $tahun);
    $y = (int)substr($pAwal, 0, 4);
    $bulanAwal = (int)substr($pAwal, 5, 2);
    $bulanAkhir = (int)substr($pAkhir, 5, 2);
    $pilih = ($nilai && $nilai >= $pAwal && $nilai <= $pAkhir) ? $nilai : $default;
    $m0 = (int)substr($pilih, 5, 2);
    $d0 = (int)substr($pilih, 8, 2);

    $h  = '<div class="d-flex gap-2">';
    $h .= '<select name="' . $prefix . '_d" id="' . $prefix . '_d" class="form-select" style="max-width:84px" aria-label="Tanggal">';
    for ($d = 1; $d <= 31; $d++) { $h .= '<option value="' . $d . '"' . ($d === $d0 ? ' selected' : '') . '>' . $d . '</option>'; }
    $h .= '</select>';
    $h .= '<select name="' . $prefix . '_m" id="' . $prefix . '_m" class="form-select" aria-label="Bulan">';
    for ($m = $bulanAwal; $m <= $bulanAkhir; $m++) { $h .= '<option value="' . $m . '"' . ($m === $m0 ? ' selected' : '') . '>' . $namaBulan[$m] . '</option>'; }
    $h .= '</select>';
    $h .= '<select name="' . $prefix . '_y" id="' . $prefix . '_y" class="form-select" style="max-width:100px" aria-label="Tahun">';
    $h .= '<option value="' . $y . '" selected>' . $y . '</option>';
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
$namaHari = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat'];
$namaBulan = gjNamaBulan();

// ==== Periode yang sedang dilihat/dikelola ====
$semester     = in_array($_GET['semester'] ?? '', ['Ganjil', 'Genap'], true) ? $_GET['semester'] : gjSemesterSekarang();
$tahun_ajaran = $_GET['tahun_ajaran'] ?? gjTahunAjaranSekarang();
if (!preg_match('#^\d{4}/\d{4}$#', $tahun_ajaran)) { $tahun_ajaran = gjTahunAjaranSekarang(); }
$periodeAktif = ($semester === gjSemesterSekarang() && $tahun_ajaran === gjTahunAjaranSekarang());
list($pAwal, $pAkhir) = gjRentangPeriode($semester, $tahun_ajaran);

// ==== Filter bulan (opsional): hanya tampilkan jadwal yang berlaku di bulan itu ====
$opsiBulan = [];
for ($t = strtotime($pAwal); $t <= strtotime($pAkhir); $t = strtotime('+1 month', $t)) {
    $opsiBulan[date('Y-m', $t)] = $namaBulan[(int)date('n', $t)] . ' ' . date('Y', $t);
}
$bulanFilter = $_GET['bulan'] ?? '';
if (!isset($opsiBulan[$bulanFilter])) { $bulanFilter = ''; }

$guruList = $pdo->query("SELECT id, nama, role FROM users WHERE role IN ('guru','wali_kelas') ORDER BY nama")->fetchAll();
$guruIdValid = array_column($guruList, 'id');

// Guru biasa: otomatis dikunci ke dirinya. Admin / kepala sekolah: boleh memilih (kosong = semua).
$filterGuru = $pemantau ? (int)($_GET['guru_id'] ?? 0) : (int)$user['id'];
if ($pemantau && $filterGuru && !in_array($filterGuru, array_map('intval', $guruIdValid), true)) { $filterGuru = 0; }

$mapelList = $pdo->query("SELECT id, nama_mapel FROM mata_pelajaran ORDER BY nama_mapel")->fetchAll();
$kelasList = $pdo->query("SELECT id, nama_kelas FROM kelas ORDER BY tingkat, nama_kelas")->fetchAll();

$errors = [];
$old = [
    'guru_id' => $filterGuru ?: '', 'mapel_id' => '', 'kelas_id' => '', 'hari' => '1',
    'jam_mulai' => '07:00', 'jam_selesai' => '08:30',
    'mulai' => '', 'sampai' => '',
];

/* ================= POST (admin) ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';
    $kembali = 'guru/jadwal.php?' . gjQuery($semester, $tahun_ajaran, $filterGuru && $pemantau ? $filterGuru : null, $bulanFilter ?: null);

    if (!$bisaKelola) {
        $errors[] = 'Anda tidak berhak mengubah jadwal.';
    } elseif (!gjCsrfValid($_POST['csrf'] ?? '')) {
        $errors[] = 'Sesi formulir tidak valid. Muat ulang halaman lalu coba lagi.';

    } elseif ($aksi === 'tambah') {
        $gid   = (int)($_POST['guru_id'] ?? 0);
        $mid   = (int)($_POST['mapel_id'] ?? 0);
        $kid   = (int)($_POST['kelas_id'] ?? 0);
        $hari  = (int)($_POST['hari'] ?? 0);
        $mulaiIn   = trim($_POST['jam_mulai'] ?? '');
        $selesaiIn = trim($_POST['jam_selesai'] ?? '');
        $semesterIn = in_array($_POST['semester'] ?? '', ['Ganjil', 'Genap'], true) ? $_POST['semester'] : $semester;
        $tahunIn    = trim($_POST['tahun_ajaran'] ?? '') ?: $tahun_ajaran;

        $tglMulai  = gjGabungTanggal($_POST['mulai_y'] ?? 0, $_POST['mulai_m'] ?? 0, $_POST['mulai_d'] ?? 0);
        $tglSampai = gjGabungTanggal($_POST['sampai_y'] ?? 0, $_POST['sampai_m'] ?? 0, $_POST['sampai_d'] ?? 0);
        $old = [
            'guru_id' => $gid ?: '', 'mapel_id' => $mid ?: '', 'kelas_id' => $kid ?: '', 'hari' => (string)$hari,
            'jam_mulai' => $mulaiIn, 'jam_selesai' => $selesaiIn,
            'mulai' => $tglMulai ?: '', 'sampai' => $tglSampai ?: '',
        ];

        $mulai = gjWaktu($mulaiIn);
        $selesai = gjWaktu($selesaiIn);

        if (!in_array($gid, array_map('intval', $guruIdValid), true)) { $errors[] = 'Pilih guru yang valid.'; }
        if (!in_array($mid, array_map('intval', array_column($mapelList, 'id')), true)) { $errors[] = 'Pilih mata pelajaran yang valid.'; }
        if (!in_array($kid, array_map('intval', array_column($kelasList, 'id')), true)) { $errors[] = 'Pilih kelas yang valid.'; }
        if (!$mulai || !$selesai) { $errors[] = 'Format jam tidak valid.'; }
        elseif ($mulai >= $selesai) { $errors[] = 'Jam selesai harus lebih besar dari jam mulai.'; }
        if (!preg_match('#^\d{4}/\d{4}$#', $tahunIn)) { $errors[] = 'Tahun ajaran tidak valid.'; }

        // ---- Tanggal berlaku ----
        $berMulai = null;
        $berSampai = null;
        if (!$errors) {
            list($pAwalIn, $pAkhirIn) = gjRentangPeriode($semesterIn, $tahunIn);
            $teksPeriode = "Semester {$semesterIn} {$tahunIn} (" . gjTglIndo($pAwalIn) . ' – ' . gjTglIndo($pAkhirIn) . ')';

            if (!$tglMulai || !$tglSampai) {
                $errors[] = 'Tanggal "Mulai berlaku" dan "Sampai" harus valid (misalnya 31 Februari tidak ada).';
            } elseif ($tglSampai < $tglMulai) {
                $errors[] = 'Tanggal "Sampai" tidak boleh sebelum tanggal "Mulai berlaku".';
            } elseif ($tglMulai < $pAwalIn || $tglSampai > $pAkhirIn) {
                $errors[] = 'Tanggal harus berada di dalam ' . $teksPeriode . '. Ganti semester / tahun ajaran di atas bila ingin menjadwalkan periode lain.';
            } elseif ($tglMulai === $pAwalIn && $tglSampai === $pAkhirIn) {
                // Seluruh semester -> disimpan tanpa batas tanggal (berlaku sepanjang periode)
                if (!isset($namaHari[$hari])) { $errors[] = 'Pilih hari Senin sampai Jumat.'; }
            } elseif ($tglMulai === $tglSampai) {
                // Satu tanggal saja -> hari mengikuti tanggal yang dipilih
                $berMulai = $berSampai = $tglMulai;
                $hari = (int)date('N', strtotime($tglMulai));
                if ($hari > 5) { $errors[] = 'Tanggal tersebut jatuh pada akhir pekan. Pilih hari Senin sampai Jumat.'; }
            } else {
                // Rentang tanggal -> berlaku tiap minggu pada hari terpilih
                $berMulai = $tglMulai;
                $berSampai = $tglSampai;
                if (!isset($namaHari[$hari])) {
                    $errors[] = 'Pilih hari Senin sampai Jumat.';
                } elseif (!gjAdaHari($hari, $berMulai, $berSampai)) {
                    $errors[] = 'Tidak ada hari ' . $namaHari[$hari] . ' pada rentang tanggal tersebut.';
                }
            }
        }

        // ---- Bentrok (hanya terhadap jadwal di periode yang sama, memperhitungkan tanggal berlaku) ----
        if (!$errors) {
            $b = gjCariBentrok($pdo, 'guru_id', $gid, $semesterIn, $tahunIn, $hari, $mulai, $selesai, $berMulai, $berSampai);
            if ($b) {
                $errors[] = 'Guru ini sudah mengajar ' . $b['nama_mapel'] . ' pada ' . $namaHari[$hari] . ' ' . substr($b['jam_mulai'], 0, 5) . '–' . substr($b['jam_selesai'], 0, 5)
                          . ' (' . gjRentangTeks($b['berlaku_mulai'], $b['berlaku_sampai']) . '), bentrok dengan jadwal ini.';
            }
            $b = gjCariBentrok($pdo, 'kelas_id', $kid, $semesterIn, $tahunIn, $hari, $mulai, $selesai, $berMulai, $berSampai);
            if ($b) {
                $errors[] = 'Kelas tersebut sudah punya ' . $b['nama_mapel'] . ' pada ' . $namaHari[$hari] . ' ' . substr($b['jam_mulai'], 0, 5) . '–' . substr($b['jam_selesai'], 0, 5)
                          . ' (' . gjRentangTeks($b['berlaku_mulai'], $b['berlaku_sampai']) . '), bentrok dengan jadwal ini.';
            }
        }

        if (!$errors) {
            $stmt = $pdo->prepare("INSERT INTO jadwal_mengajar (guru_id, mapel_id, kelas_id, semester, tahun_ajaran, hari, jam_mulai, jam_selesai, berlaku_mulai, berlaku_sampai) VALUES (?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$gid, $mid, $kid, $semesterIn, $tahunIn, $hari, $mulai, $selesai, $berMulai, $berSampai]);
            setFlash('success', 'Jadwal mengajar ditambahkan (' . gjRentangTeks($berMulai, $berSampai) . ') untuk Semester ' . $semesterIn . ' ' . $tahunIn . '.');
            redirect('guru/jadwal.php?' . gjQuery($semesterIn, $tahunIn, $gid, $berMulai ? substr($berMulai, 0, 7) : null));
        }

    } elseif ($aksi === 'hapus') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM jadwal_mengajar WHERE id = ?");
        $stmt->execute([$id]);
        setFlash('success', $stmt->rowCount() ? 'Jadwal dihapus.' : 'Jadwal tidak ditemukan.');
        redirect($kembali);

    } elseif ($aksi === 'salin_periode') {
        // Salin jadwal "sepanjang periode" dari periode lain ke periode yang sedang dibuka.
        // Jadwal yang dibatasi tanggal tidak ikut disalin karena tanggalnya milik periode asal.
        $dariSemester = in_array($_POST['dari_semester'] ?? '', ['Ganjil', 'Genap'], true) ? $_POST['dari_semester'] : null;
        $dariTahun    = trim($_POST['dari_tahun_ajaran'] ?? '');

        if (!$dariSemester || !preg_match('#^\d{4}/\d{4}$#', $dariTahun)) {
            $errors[] = 'Pilih periode sumber yang valid untuk disalin.';
        } elseif ($dariSemester === $semester && $dariTahun === $tahun_ajaran) {
            $errors[] = 'Periode sumber tidak boleh sama dengan periode tujuan.';
        } else {
            $stmt = $pdo->prepare("SELECT guru_id, mapel_id, kelas_id, hari, jam_mulai, jam_selesai, berlaku_mulai, berlaku_sampai FROM jadwal_mengajar WHERE semester = ? AND tahun_ajaran = ?");
            $stmt->execute([$dariSemester, $dariTahun]);
            $semua = $stmt->fetchAll();
            $sumber = [];
            $dilewatiTanggal = 0;
            foreach ($semua as $j) {
                if ($j['berlaku_mulai'] || $j['berlaku_sampai']) { $dilewatiTanggal++; } else { $sumber[] = $j; }
            }

            if (empty($sumber)) {
                $errors[] = "Tidak ada jadwal \"sepanjang periode\" di Semester {$dariSemester} {$dariTahun} untuk disalin.";
            } else {
                $cekGuru = $pdo->prepare("SELECT COUNT(*) FROM jadwal_mengajar WHERE guru_id = ? AND semester = ? AND tahun_ajaran = ? AND hari = ? AND jam_mulai < ? AND jam_selesai > ?");
                $cekKelas = $pdo->prepare("SELECT COUNT(*) FROM jadwal_mengajar WHERE kelas_id = ? AND semester = ? AND tahun_ajaran = ? AND hari = ? AND jam_mulai < ? AND jam_selesai > ?");
                $ins = $pdo->prepare("INSERT INTO jadwal_mengajar (guru_id, mapel_id, kelas_id, semester, tahun_ajaran, hari, jam_mulai, jam_selesai) VALUES (?,?,?,?,?,?,?,?)");
                $disalin = 0; $dilewati = 0;
                foreach ($sumber as $j) {
                    $cekGuru->execute([$j['guru_id'], $semester, $tahun_ajaran, $j['hari'], $j['jam_selesai'], $j['jam_mulai']]);
                    $cekKelas->execute([$j['kelas_id'], $semester, $tahun_ajaran, $j['hari'], $j['jam_selesai'], $j['jam_mulai']]);
                    if ((int)$cekGuru->fetchColumn() > 0 || (int)$cekKelas->fetchColumn() > 0) { $dilewati++; continue; }
                    $ins->execute([$j['guru_id'], $j['mapel_id'], $j['kelas_id'], $semester, $tahun_ajaran, $j['hari'], $j['jam_mulai'], $j['jam_selesai']]);
                    $disalin++;
                }
                $pesan = "{$disalin} jadwal disalin ke Semester {$semester} {$tahun_ajaran}.";
                if ($dilewati) { $pesan .= " {$dilewati} dilewati karena bentrok dengan jadwal yang sudah ada."; }
                if ($dilewatiTanggal) { $pesan .= " {$dilewatiTanggal} jadwal berbatas tanggal tidak ikut disalin."; }
                setFlash($disalin ? 'success' : 'error', $pesan);
                redirect('guru/jadwal.php?' . gjQuery($semester, $tahun_ajaran, $filterGuru && $pemantau ? $filterGuru : null));
            }
        }
    } else {
        $errors[] = 'Aksi tidak dikenal.';
    }
}

/* ================= Data jadwal (periode terpilih saja) ================= */
$sql = "SELECT j.*, m.nama_mapel, k.nama_kelas, u.nama AS nama_guru
        FROM jadwal_mengajar j
        JOIN mata_pelajaran m ON j.mapel_id = m.id
        JOIN kelas k ON j.kelas_id = k.id
        JOIN users u ON j.guru_id = u.id
        WHERE j.semester = ? AND j.tahun_ajaran = ?";
$params = [$semester, $tahun_ajaran];
if ($filterGuru) { $sql .= " AND j.guru_id = ?"; $params[] = $filterGuru; }
$sql .= " ORDER BY j.hari, j.jam_mulai, u.nama";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$perHari = [];
foreach ($stmt->fetchAll() as $r) {
    // Filter bulan: tampilkan hanya jadwal yang benar-benar berlaku (ada hari yang cocok) di bulan itu
    if ($bulanFilter !== '') {
        $awalBulan  = $bulanFilter . '-01';
        $akhirBulan = date('Y-m-t', strtotime($awalBulan));
        $awal  = max($awalBulan, $r['berlaku_mulai'] ?: $pAwal);
        $akhir = min($akhirBulan, $r['berlaku_sampai'] ?: $pAkhir);
        if ($awal > $akhir || !gjAdaHari($r['hari'], $awal, $akhir)) { continue; }
    }
    $perHari[$r['hari']][] = $r;
}

$tol = (int)($pdo->query("SELECT toleransi_menit FROM pengaturan_absen_guru ORDER BY id LIMIT 1")->fetchColumn() ?: 0);
$csrf = gjCsrfToken();
$tahunPeriode = (int)substr($pAwal, 0, 4);

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
  Jam <b>pelajaran pertama</b> setiap hari menjadi acuan absen masuk guru: tepat waktu sampai
  <b><?= $tol ?> menit</b> setelah pelajaran pertama dimulai. Absen pulang dibuka setelah <b>pelajaran terakhir</b> selesai.
  Pada hari tanpa jadwal, berlaku jam standar di menu Kehadiran Guru.
  Acuan ini dihitung dari jadwal yang <b>berlaku pada tanggal hari itu</b> di periode berjalan (Semester <?= clean(gjSemesterSekarang()) ?> <?= clean(gjTahunAjaranSekarang()) ?>).
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
      <label class="form-label small">Lihat bulan</label>
      <select name="bulan" class="form-select" onchange="this.form.submit()">
        <option value="">Seluruh semester</option>
        <?php foreach ($opsiBulan as $val => $lbl): ?>
          <option value="<?= $val ?>" <?= $bulanFilter === $val ? 'selected' : '' ?>><?= clean($lbl) ?></option>
        <?php endforeach; ?>
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
      <div class="col-6 col-md-3">
        <label class="form-label small">Hari</label>
        <select name="hari" id="pilihHari" class="form-select">
          <?php foreach ($namaHari as $no => $nm): ?><option value="<?= $no ?>" <?= (string)$old['hari'] === (string)$no ? 'selected' : '' ?>><?= $nm ?></option><?php endforeach; ?>
        </select>
        <div class="form-text" id="infoHari"></div>
      </div>
      <div class="col-6 col-md-3">
        <label class="form-label small">Pilih bulan <span class="text-muted">(cepat)</span></label>
        <select id="pilihBulan" class="form-select">
          <option value="">Atur tanggal manual</option>
          <option value="semua">Seluruh semester</option>
          <?php foreach ($opsiBulan as $val => $lbl): ?><option value="<?= $val ?>"><?= clean($lbl) ?></option><?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-6">
        <label class="form-label small">Mulai berlaku</label>
        <?= gjSelectTanggal('mulai', $semester, $tahun_ajaran, $old['mulai'], $pAwal) ?>
      </div>
      <div class="col-md-6">
        <label class="form-label small">Sampai</label>
        <?= gjSelectTanggal('sampai', $semester, $tahun_ajaran, $old['sampai'], $pAkhir) ?>
      </div>

      <div class="col-12 small text-muted" id="petunjukTanggal"></div>
      <div class="col-md-3"><button class="btn btn-primary w-100"><i class="bi bi-plus-lg"></i> Tambah</button></div>
    </div>
  </form>

  <hr>
  <div class="small fw-semibold text-muted mb-2"><i class="bi bi-copy me-1"></i>Salin jadwal dari periode lain</div>
  <form method="POST" class="row g-2 align-items-end">
    <input type="hidden" name="csrf" value="<?= clean($csrf) ?>">
    <input type="hidden" name="aksi" value="salin_periode">
    <div class="col-6 col-md-3">
      <select name="dari_semester" class="form-select form-select-sm">
        <option value="Ganjil">Ganjil</option>
        <option value="Genap">Genap</option>
      </select>
    </div>
    <div class="col-6 col-md-3">
      <select name="dari_tahun_ajaran" class="form-select form-select-sm">
        <?php foreach (gjOpsiTahun() as $opt): ?><option value="<?= $opt ?>"><?= $opt ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="col-12 col-md-6">
      <button class="btn btn-outline-secondary btn-sm" onclick="return confirm('Salin semua jadwal \"sepanjang periode\" dari periode terpilih ke Semester <?= clean($semester) ?> <?= clean($tahun_ajaran) ?>? Jadwal yang bentrok akan dilewati.');">
        <i class="bi bi-copy"></i> Salin ke Semester <?= clean($semester) ?> <?= clean($tahun_ajaran) ?>
      </button>
    </div>
  </form>
</div>

<script>
(function () {
  const $ = function (id) { return document.getElementById(id); };
  const hari = $('pilihHari');
  if (!hari) return;
  const info = $('infoHari');
  const petunjuk = $('petunjukTanggal');
  const pilihBulan = $('pilihBulan');
  const NAMA_HARI = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
  const TAHUN = <?= (int)$tahunPeriode ?>;
  const BULAN_AWAL = <?= (int)substr($pAwal, 5, 2) ?>;
  const BULAN_AKHIR = <?= (int)substr($pAkhir, 5, 2) ?>;

  function ambil(p) {
    return { d: parseInt($(p + '_d').value, 10), m: parseInt($(p + '_m').value, 10), y: parseInt($(p + '_y').value, 10) };
  }
  function hariDalamBulan(y, m) { return new Date(y, m, 0).getDate(); }

  // Jumlah hari menyesuaikan bulan (Februari 28/29, bulan lain 30/31)
  function sesuaikanHari(p) {
    const d = $(p + '_d'), t = ambil(p);
    const maks = hariDalamBulan(t.y, t.m);
    const dipilih = Math.min(t.d || 1, maks);
    d.innerHTML = '';
    for (let i = 1; i <= maks; i++) {
      const o = document.createElement('option');
      o.value = i; o.textContent = i;
      if (i === dipilih) o.selected = true;
      d.appendChild(o);
    }
  }

  function atur(p, tgl, bln) {
    $(p + '_m').value = bln;
    sesuaikanHari(p);
    $(p + '_d').value = tgl;
  }

  function perbarui() {
    const a = ambil('mulai'), b = ambil('sampai');
    const awal = new Date(a.y, a.m - 1, a.d), akhir = new Date(b.y, b.m - 1, b.d);
    const sama = awal.getTime() === akhir.getTime();
    const penuh = a.m === BULAN_AWAL && a.d === 1 && b.m === BULAN_AKHIR && b.d === hariDalamBulan(b.y, BULAN_AKHIR);

    hari.disabled = sama;   // satu tanggal saja: hari otomatis mengikuti tanggal
    if (sama) {
      const w = awal.getDay();
      info.textContent = 'Hari otomatis: ' + NAMA_HARI[w] + (w === 0 || w === 6 ? ' (akhir pekan, tidak bisa dijadwalkan)' : '');
      petunjuk.textContent = 'Jadwal hanya berlaku pada satu tanggal ini (cocok untuk jam pengganti / tukar jadwal).';
    } else if (akhir < awal) {
      info.textContent = '';
      petunjuk.textContent = 'Tanggal "Sampai" tidak boleh sebelum tanggal "Mulai berlaku".';
    } else if (penuh) {
      info.textContent = '';
      petunjuk.textContent = 'Jadwal berlaku setiap hari ' + hari.options[hari.selectedIndex].text + ' selama satu semester penuh.';
    } else {
      info.textContent = '';
      petunjuk.textContent = 'Jadwal berlaku setiap hari ' + hari.options[hari.selectedIndex].text + ' hanya dari tanggal "Mulai berlaku" sampai "Sampai".';
    }
  }

  // Pilih bulan -> isi tanggal 1 s/d tanggal terakhir bulan itu (atau seluruh semester)
  pilihBulan.addEventListener('change', function () {
    const v = this.value;
    if (!v) return;
    if (v === 'semua') {
      atur('mulai', 1, BULAN_AWAL);
      atur('sampai', hariDalamBulan(TAHUN, BULAN_AKHIR), BULAN_AKHIR);
    } else {
      const bln = parseInt(v.slice(5, 7), 10);
      atur('mulai', 1, bln);
      atur('sampai', hariDalamBulan(TAHUN, bln), bln);
    }
    perbarui();
  });

  ['mulai', 'sampai'].forEach(function (p) {
    $(p + '_m').addEventListener('change', function () { sesuaikanHari(p); pilihBulan.value = ''; perbarui(); });
    $(p + '_d').addEventListener('change', function () { pilihBulan.value = ''; perbarui(); });
    sesuaikanHari(p);
  });
  hari.addEventListener('change', perbarui);
  perbarui();
})();
</script>
<?php endif; ?>

<?php if (empty($perHari)): ?>
  <div class="card p-4 text-center text-muted">
    Belum ada jadwal mengajar<?= $filterGuru && $pemantau ? ' untuk guru ini' : '' ?> di Semester <?= clean($semester) ?> <?= clean($tahun_ajaran) ?><?= $bulanFilter !== '' ? ' pada bulan ' . clean($opsiBulan[$bulanFilter]) : '' ?>.
    <?php if ($bisaKelola): ?><br><span class="small">Gunakan "Salin jadwal dari periode lain" di atas kalau jadwalnya mirip semester sebelumnya.</span><?php endif; ?>
  </div>
<?php endif; ?>

<?php foreach ($namaHari as $no => $nm):
    if (empty($perHari[$no])) { continue; }
    $baris = $perHari[$no];
    // Acuan absen hanya dihitung bila semua jadwal hari itu berlaku sepanjang periode.
    // Kalau ada jadwal berbatas tanggal, jam acuan berbeda-beda per tanggal.
    $adaBerbatas = false;
    $pertama = $baris[0]['jam_mulai'];
    $terakhir = '00:00:00';
    foreach ($baris as $b) {
        if ($b['berlaku_mulai'] || $b['berlaku_sampai']) { $adaBerbatas = true; }
        if ($b['jam_selesai'] > $terakhir) { $terakhir = $b['jam_selesai']; }
    }
    $batasTepat = date('H:i', strtotime("2000-01-01 {$pertama}") + $tol * 60);
?>
  <div class="card p-3 mb-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
      <h6 class="fw-bold mb-0"><?= $nm ?></h6>
      <?php if ($filterGuru && $periodeAktif && !$adaBerbatas): ?>
        <span class="small text-muted">
          Absen tepat waktu s/d <b class="text-dark"><?= $batasTepat ?></b> · pulang mulai <b class="text-dark"><?= substr($terakhir, 0, 5) ?></b>
        </span>
      <?php elseif ($filterGuru && $periodeAktif): ?>
        <span class="small text-muted fst-italic">Ada jadwal berbatas tanggal — jam acuan absen mengikuti jadwal yang berlaku pada tanggal tersebut.</span>
      <?php elseif ($filterGuru): ?>
        <span class="small text-muted fst-italic">Bukan periode berjalan — jam ini tidak dipakai sebagai acuan absen saat ini.</span>
      <?php endif; ?>
    </div>
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead class="table-light"><tr><th>Jam</th><th>Mata Pelajaran</th><th>Kelas</th><?php if (!$filterGuru): ?><th>Guru</th><?php endif; ?><th>Berlaku</th><?php if ($bisaKelola): ?><th></th><?php endif; ?></tr></thead>
        <tbody>
          <?php foreach ($baris as $b): $terbatas = ($b['berlaku_mulai'] || $b['berlaku_sampai']); ?>
            <tr>
              <td class="text-nowrap fw-semibold"><?= substr($b['jam_mulai'], 0, 5) ?>–<?= substr($b['jam_selesai'], 0, 5) ?></td>
              <td><?= clean($b['nama_mapel']) ?></td>
              <td><?= clean($b['nama_kelas']) ?></td>
              <?php if (!$filterGuru): ?><td><?= clean($b['nama_guru']) ?></td><?php endif; ?>
              <td class="small">
                <?php if ($terbatas): ?>
                  <span class="badge bg-info-subtle text-info-emphasis border"><?= clean(gjRentangTeks($b['berlaku_mulai'], $b['berlaku_sampai'])) ?></span>
                <?php else: ?>
                  <span class="text-muted">Sepanjang periode</span>
                <?php endif; ?>
              </td>
              <?php if ($bisaKelola): ?>
                <td class="text-end">
                  <form method="POST" class="d-inline" onsubmit="return confirm(<?= htmlspecialchars(json_encode($terbatas ? 'Hapus jadwal ini? (' . gjRentangTeks($b['berlaku_mulai'], $b['berlaku_sampai']) . ')' : 'Hapus jadwal ini? Jadwal ini berlaku sepanjang periode, jadi akan hilang dari semua minggu di semester ini.', JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>);">
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

<?php include __DIR__ . '/../includes/footer.php'; ?>