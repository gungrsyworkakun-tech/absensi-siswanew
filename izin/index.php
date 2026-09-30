<?php
// Zona waktu HARUS sama dengan dashboard & absensi/gps.php.
date_default_timezone_set('Asia/Makassar');

require_once __DIR__ . '/../includes/auth.php';

$user = currentUser();
$pageTitle = 'Izin Siswa';

/* ================= Pengaturan ================= */
// Role yang boleh MENYETUJUI / MENOLAK izin. Role lain (admin) hanya bisa melihat.
// Wali kelas hanya untuk kelasnya sendiri; guru untuk semua kelas.
const IZIN_PEMROSES     = ['wali_kelas', 'guru'];
// Role yang boleh MENGHAPUS pengajuan izin (hanya wali kelas, dan hanya untuk kelasnya sendiri).
const IZIN_PENGHAPUS    = ['wali_kelas'];
const IZIN_MAKS_HARI    = 14;       // maksimal lama satu pengajuan (hari kalender)
const IZIN_MAKS_MUNDUR  = 7;        // boleh mengajukan untuk tanggal maksimal 7 hari ke belakang
const IZIN_MAKS_MAJU    = 60;       // dan maksimal 60 hari ke depan
const IZIN_MAKS_UKURAN  = 2097152;  // lampiran maksimal 2 MB

requireRole(['siswa', 'wali_kelas', 'guru', 'admin']);

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

$bisaProses = in_array($user['role'], IZIN_PEMROSES, true);
$bisaHapus  = in_array($user['role'], IZIN_PENGHAPUS, true);

/* ================= Helper ================= */
function izinCsrfToken() {
    if (empty($_SESSION['izin_csrf'])) { $_SESSION['izin_csrf'] = bin2hex(random_bytes(16)); }
    return $_SESSION['izin_csrf'];
}
function izinCsrfValid($t) {
    return !empty($_SESSION['izin_csrf']) && is_string($t) && hash_equals($_SESSION['izin_csrf'], $t);
}
function izinTanggal($s) {
    $d = DateTime::createFromFormat('Y-m-d', (string)$s);
    return ($d && $d->format('Y-m-d') === $s) ? $d : null;
}
function izinJumlahHari($mulai, $selesai) {
    return (int)round((strtotime($selesai) - strtotime($mulai)) / 86400) + 1;
}
function izinDirUpload() {
    $dir = __DIR__ . '/../uploads/izin';
    if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
    $ht = $dir . '/.htaccess';
    if (is_dir($dir) && !file_exists($ht)) {
        @file_put_contents($ht, "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
    }
    return $dir;
}
function izinBadge($status) {
    $map = ['Menunggu' => 'bg-warning text-dark', 'Disetujui' => 'bg-success', 'Ditolak' => 'bg-danger'];
    return '<span class="badge ' . ($map[$status] ?? 'bg-secondary') . '">' . clean($status) . '</span>';
}

/**
 * Setelah izin DISETUJUI, tulis ke tabel absensi (status Izin/Sakit) untuk tiap hari sekolah
 * dalam rentang tanggal, supaya otomatis muncul di kalender dashboard & rekap absensi.
 * - Sabtu/Minggu dan tanggal di tabel hari_libur dilewati.
 * - Hari yang sudah tercatat "Hadir" tidak ditimpa.
 * Mengembalikan jumlah hari yang diterapkan.
 */
function izinTerapkanKeAbsensi(PDO $pdo, array $izin, $namaPemroses) {
    $stmt = $pdo->prepare("SELECT tanggal FROM hari_libur WHERE tanggal BETWEEN ? AND ?");
    $stmt->execute([$izin['tanggal_mulai'], $izin['tanggal_selesai']]);
    $libur = array_flip($stmt->fetchAll(PDO::FETCH_COLUMN));

    $cek = $pdo->prepare("SELECT id, status FROM absensi WHERE siswa_id = ? AND tanggal = ?");
    $ins = $pdo->prepare("INSERT INTO absensi (siswa_id, kelas_id, tanggal, status, keterangan, input_oleh) VALUES (?,?,?,?,?,?)");
    $upd = $pdo->prepare("UPDATE absensi SET status = ?, keterangan = ?, input_oleh = ? WHERE id = ?");

    $ket = mb_substr($izin['jenis'] . ' (disetujui): ' . $izin['alasan'], 0, 255);
    $oleh = mb_substr('Izin - ' . $namaPemroses, 0, 100);

    $terapkan = 0;
    $d = new DateTime($izin['tanggal_mulai']);
    $akhir = new DateTime($izin['tanggal_selesai']);
    while ($d <= $akhir) {
        $tgl = $d->format('Y-m-d');
        if ((int)$d->format('N') < 6 && !isset($libur[$tgl])) {
            $cek->execute([$izin['siswa_id'], $tgl]);
            $ada = $cek->fetch();
            if (!$ada) {
                $ins->execute([$izin['siswa_id'], $izin['kelas_id'], $tgl, $izin['jenis'], $ket, $oleh]);
                $terapkan++;
            } elseif ($ada['status'] !== 'Hadir') {
                $upd->execute([$izin['jenis'], $ket, $oleh, $ada['id']]);
                $terapkan++;
            }
        }
        $d->modify('+1 day');
    }
    return $terapkan;
}

/* ================= Cek tabel sudah dimigrasi ================= */
$tabelAda = true;
try {
    $pdo->query("SELECT 1 FROM izin LIMIT 1");
} catch (PDOException $e) {
    $tabelAda = false;
}
if (!$tabelAda) {
    include __DIR__ . '/../includes/header.php';
    echo "<div class='card p-4'><h5 class='fw-bold'><i class='bi bi-exclamation-triangle text-warning me-2'></i>Fitur Izin belum aktif</h5>"
       . "<p class='mb-0'>Import file <b>tambah_izin.sql</b> ke database <b>db_absensi_sekolah</b> melalui phpMyAdmin, lalu refresh halaman ini.</p></div>";
    include __DIR__ . '/../includes/footer.php';
    exit;
}

/* ================= Konteks per role ================= */
$kelasSaya = null;      // wali kelas
$siswaRow  = null;      // siswa

if ($user['role'] === 'wali_kelas') {
    $kelasSaya = kelasWaliSaya($pdo, $user['id']);
    if (!$kelasSaya) {
        include __DIR__ . '/../includes/header.php';
        echo "<div class='card p-4 text-center'><i class='bi bi-exclamation-triangle text-warning fs-1 mb-2'></i><p class='mb-0'>Akun Anda belum dihubungkan ke kelas manapun. Hubungi admin sekolah.</p></div>";
        include __DIR__ . '/../includes/footer.php';
        exit;
    }
}
if ($user['role'] === 'siswa') {
    $stmt = $pdo->prepare("SELECT id, nis, nama_lengkap, kelas_id FROM siswa WHERE id = ?");
    $stmt->execute([$user['siswa_id']]);
    $siswaRow = $stmt->fetch();
    if (!$siswaRow || !$siswaRow['kelas_id']) {
        include __DIR__ . '/../includes/header.php';
        echo "<div class='card p-4 text-center'><i class='bi bi-exclamation-triangle text-warning fs-1 mb-2'></i><p class='mb-0'>Akun Anda belum terhubung ke data siswa atau kelas. Hubungi admin sekolah.</p></div>";
        include __DIR__ . '/../includes/footer.php';
        exit;
    }
}

$hariIni = date('Y-m-d');
$minTanggal = date('Y-m-d', strtotime("-" . IZIN_MAKS_MUNDUR . " day"));
$maxTanggal = date('Y-m-d', strtotime("+" . IZIN_MAKS_MAJU . " day"));

$errors = [];
$old = ['jenis' => 'Izin', 'tanggal_mulai' => $hariIni, 'tanggal_selesai' => $hariIni, 'alasan' => ''];

/* ================= Proses POST ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';

    if (!izinCsrfValid($_POST['csrf'] ?? '')) {
        $errors[] = 'Sesi formulir tidak valid. Muat ulang halaman lalu coba lagi.';

    // ---------- Siswa mengajukan izin ----------
    } elseif ($aksi === 'ajukan' && $user['role'] === 'siswa') {
        $jenis   = ($_POST['jenis'] ?? '') === 'Sakit' ? 'Sakit' : 'Izin';
        $mulai   = trim($_POST['tanggal_mulai'] ?? '');
        $selesai = trim($_POST['tanggal_selesai'] ?? '');
        $alasan  = trim($_POST['alasan'] ?? '');
        $old = ['jenis' => $jenis, 'tanggal_mulai' => $mulai, 'tanggal_selesai' => $selesai, 'alasan' => $alasan];

        $dMulai = izinTanggal($mulai);
        $dSelesai = izinTanggal($selesai);
        if (!$dMulai || !$dSelesai) {
            $errors[] = 'Tanggal mulai dan tanggal selesai wajib diisi dengan benar.';
        } else {
            if ($selesai < $mulai)          { $errors[] = 'Tanggal selesai tidak boleh sebelum tanggal mulai.'; }
            if ($mulai < $minTanggal)       { $errors[] = 'Pengajuan hanya boleh untuk tanggal maksimal ' . IZIN_MAKS_MUNDUR . ' hari ke belakang.'; }
            if ($selesai > $maxTanggal)     { $errors[] = 'Tanggal terlalu jauh ke depan (maksimal ' . IZIN_MAKS_MAJU . ' hari).'; }
            if (!$errors && izinJumlahHari($mulai, $selesai) > IZIN_MAKS_HARI) {
                $errors[] = 'Satu pengajuan maksimal ' . IZIN_MAKS_HARI . ' hari. Pisahkan menjadi beberapa pengajuan jika lebih lama.';
            }
        }
        $lenAlasan = mb_strlen($alasan);
        if ($lenAlasan < 5)   { $errors[] = 'Alasan minimal 5 karakter.'; }
        if ($lenAlasan > 255) { $errors[] = 'Alasan maksimal 255 karakter.'; }

        // Tidak boleh bertumpuk dengan pengajuan lain yang masih menunggu / sudah disetujui
        if (!$errors) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM izin WHERE siswa_id = ? AND status IN ('Menunggu','Disetujui') AND tanggal_mulai <= ? AND tanggal_selesai >= ?");
            $stmt->execute([$siswaRow['id'], $selesai, $mulai]);
            if ((int)$stmt->fetchColumn() > 0) {
                $errors[] = 'Sudah ada pengajuan izin (menunggu / disetujui) yang tanggalnya bertumpuk dengan pengajuan ini.';
            }
        }

        // Validasi lampiran (opsional): JPG, PNG, atau PDF, maks 2 MB
        $ekstensi = null;
        $tmpLampiran = null;
        if (!$errors && !empty($_FILES['lampiran']['name'])) {
            $f = $_FILES['lampiran'];
            if ($f['error'] !== UPLOAD_ERR_OK) {
                $errors[] = 'Upload lampiran gagal (kode ' . (int)$f['error'] . ').';
            } elseif ($f['size'] > IZIN_MAKS_UKURAN) {
                $errors[] = 'Ukuran lampiran maksimal 2 MB.';
            } else {
                $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
                $ekstensi = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'application/pdf' => 'pdf'][$mime] ?? null;
                if (!$ekstensi) { $errors[] = 'Format lampiran harus JPG, PNG, atau PDF.'; }
                $tmpLampiran = $f['tmp_name'];
            }
        }

        if (!$errors) {
            $namaFile = null;
            if ($tmpLampiran && $ekstensi) {
                $dir = izinDirUpload();
                $namaFile = bin2hex(random_bytes(12)) . '.' . $ekstensi;
                if (!is_dir($dir) || !move_uploaded_file($tmpLampiran, $dir . '/' . $namaFile)) {
                    $errors[] = 'Lampiran tidak dapat disimpan di server. Hubungi admin.';
                    $namaFile = null;
                }
            }
            if (!$errors) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO izin (siswa_id, kelas_id, jenis, tanggal_mulai, tanggal_selesai, alasan, lampiran) VALUES (?,?,?,?,?,?,?)");
                    $stmt->execute([$siswaRow['id'], $siswaRow['kelas_id'], $jenis, $mulai, $selesai, $alasan, $namaFile]);
                    setFlash('success', 'Pengajuan ' . strtolower($jenis) . ' berhasil dikirim. Menunggu konfirmasi wali kelas / guru.');
                    redirect('izin/index.php');
                } catch (PDOException $e) {
                    if ($namaFile) { @unlink(izinDirUpload() . '/' . $namaFile); }
                    $errors[] = 'Gagal menyimpan pengajuan. Coba lagi.';
                }
            }
        }

    // ---------- Siswa membatalkan pengajuan (hanya yang masih Menunggu) ----------
    } elseif ($aksi === 'batal' && $user['role'] === 'siswa') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("SELECT lampiran FROM izin WHERE id = ? AND siswa_id = ? AND status = 'Menunggu'");
        $stmt->execute([$id, $siswaRow['id']]);
        $row = $stmt->fetch();
        if ($row) {
            $pdo->prepare("DELETE FROM izin WHERE id = ? AND siswa_id = ? AND status = 'Menunggu'")->execute([$id, $siswaRow['id']]);
            if (!empty($row['lampiran'])) { @unlink(izinDirUpload() . '/' . basename($row['lampiran'])); }
            setFlash('success', 'Pengajuan dibatalkan.');
            redirect('izin/index.php');
        } else {
            $errors[] = 'Pengajuan tidak ditemukan atau sudah diproses, sehingga tidak bisa dibatalkan.';
        }

    // ---------- Wali kelas / guru menyetujui atau menolak ----------
    } elseif ($aksi === 'proses' && $bisaProses) {
        $id = (int)($_POST['id'] ?? 0);
        $keputusan = ($_POST['keputusan'] ?? '') === 'setuju' ? 'Disetujui' : 'Ditolak';
        $catatan = mb_substr(trim($_POST['catatan'] ?? ''), 0, 255);

        if ($keputusan === 'Ditolak' && $catatan === '') {
            $errors[] = 'Catatan wajib diisi saat menolak izin, supaya siswa tahu alasannya.';
        } else {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("SELECT * FROM izin WHERE id = ? FOR UPDATE");
                $stmt->execute([$id]);
                $izin = $stmt->fetch();

                if (!$izin) {
                    throw new RuntimeException('Data izin tidak ditemukan.');
                }
                if ($user['role'] === 'wali_kelas' && (int)$izin['kelas_id'] !== (int)$kelasSaya['id']) {
                    throw new RuntimeException('Anda hanya bisa memproses izin siswa di kelas Anda.');
                }
                if ($izin['status'] !== 'Menunggu') {
                    throw new RuntimeException('Izin ini sudah diproses oleh ' . ($izin['diproses_nama'] ?: 'pengguna lain') . '.');
                }

                $stmt = $pdo->prepare("UPDATE izin SET status = ?, diproses_oleh = ?, diproses_nama = ?, diproses_role = ?, catatan_pemroses = ?, diproses_pada = NOW() WHERE id = ? AND status = 'Menunggu'");
                $stmt->execute([$keputusan, $user['id'], $user['nama'], $user['role'], $catatan !== '' ? $catatan : null, $id]);

                $jumlahDiterapkan = 0;
                if ($keputusan === 'Disetujui') {
                    $jumlahDiterapkan = izinTerapkanKeAbsensi($pdo, $izin, $user['nama']);
                }
                $pdo->commit();

                setFlash('success', $keputusan === 'Disetujui'
                    ? 'Izin disetujui' . ($jumlahDiterapkan ? " dan {$jumlahDiterapkan} hari tercatat di absensi siswa." : '.')
                    : 'Izin ditolak.');
                redirect('izin/index.php');
            } catch (RuntimeException $e) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                $errors[] = $e->getMessage();
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                $errors[] = 'Terjadi kesalahan saat menyimpan keputusan. Coba lagi.';
            }
        }
    // ---------- Wali kelas menghapus pengajuan izin (kelasnya sendiri) ----------
    } elseif ($aksi === 'hapus' && $bisaHapus) {
        $id = (int)($_POST['id'] ?? 0);
        $hapusAbsensi = ($_POST['hapus_absensi'] ?? '') === '1';
        $fileLampiran = null;

        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT * FROM izin WHERE id = ? FOR UPDATE");
            $stmt->execute([$id]);
            $izin = $stmt->fetch();

            if (!$izin) {
                throw new RuntimeException('Data izin tidak ditemukan (mungkin sudah dihapus).');
            }
            if ($user['role'] === 'wali_kelas' && (int)$izin['kelas_id'] !== (int)$kelasSaya['id']) {
                throw new RuntimeException('Anda hanya bisa menghapus izin siswa di kelas Anda.');
            }

            // Izin yang sudah disetujui sudah ditulis ke tabel absensi. Jika dipilih, hapus juga
            // catatan absensi hasil izin ini saja (dikenali dari jenis, keterangan, dan input_oleh
            // yang sama persis dengan yang ditulis saat persetujuan). Catatan lain tidak disentuh.
            $absensiTerhapus = 0;
            if ($izin['status'] === 'Disetujui' && $hapusAbsensi) {
                $ket = mb_substr($izin['jenis'] . ' (disetujui): ' . $izin['alasan'], 0, 255);
                $stmt = $pdo->prepare("DELETE FROM absensi WHERE siswa_id = ? AND tanggal BETWEEN ? AND ? AND status = ? AND keterangan = ? AND input_oleh LIKE 'Izin - %'");
                $stmt->execute([$izin['siswa_id'], $izin['tanggal_mulai'], $izin['tanggal_selesai'], $izin['jenis'], $ket]);
                $absensiTerhapus = $stmt->rowCount();
            }

            $pdo->prepare("DELETE FROM izin WHERE id = ?")->execute([$id]);
            $fileLampiran = $izin['lampiran'];
            $pdo->commit();

            if (!empty($fileLampiran)) { @unlink(izinDirUpload() . '/' . basename($fileLampiran)); }

            setFlash('success', 'Pengajuan izin dihapus' . ($absensiTerhapus ? " beserta {$absensiTerhapus} catatan absensi hasil izin tersebut." : '.'));
            redirect('izin/index.php');
        } catch (RuntimeException $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            $errors[] = $e->getMessage();
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            $errors[] = 'Terjadi kesalahan saat menghapus izin. Coba lagi.';
        }
    } else {
        $errors[] = 'Aksi tidak diizinkan.';
    }
}

/* ================= Ambil data untuk tampilan ================= */
$riwayat = [];
$daftar = [];
$hitung = ['Menunggu' => 0, 'Disetujui' => 0, 'Ditolak' => 0];
$kelasList = [];
$filterStatus = 'semua';
$filterKelas = '';

if ($user['role'] === 'siswa') {
    $stmt = $pdo->prepare("SELECT * FROM izin WHERE siswa_id = ? ORDER BY created_at DESC, id DESC LIMIT 50");
    $stmt->execute([$siswaRow['id']]);
    $riwayat = $stmt->fetchAll();
} else {
    // Wali kelas dikunci ke kelasnya; guru & admin bisa memilih kelas.
    if ($user['role'] === 'wali_kelas') {
        $filterKelas = (string)$kelasSaya['id'];
    } else {
        $kelasList = $pdo->query("SELECT id, nama_kelas FROM kelas ORDER BY tingkat, nama_kelas")->fetchAll();
        $filterKelas = (string)($_GET['kelas_id'] ?? '');
    }
    // Default: yang bisa memproses langsung melihat antrean "Menunggu"; admin melihat semua.
    $statusDefault = $bisaProses ? 'Menunggu' : 'semua';
    $filterStatus = $_GET['status'] ?? $statusDefault;
    if (!in_array($filterStatus, ['Menunggu', 'Disetujui', 'Ditolak', 'semua'], true)) { $filterStatus = $statusDefault; }

    // Hitungan per status (mengikuti filter kelas)
    $sqlHitung = "SELECT status, COUNT(*) c FROM izin" . ($filterKelas !== '' ? " WHERE kelas_id = ?" : "") . " GROUP BY status";
    $stmt = $pdo->prepare($sqlHitung);
    $stmt->execute($filterKelas !== '' ? [$filterKelas] : []);
    foreach ($stmt->fetchAll() as $r) { $hitung[$r['status']] = (int)$r['c']; }

    $sql = "SELECT i.*, s.nis, s.nama_lengkap, k.nama_kelas
            FROM izin i
            JOIN siswa s ON i.siswa_id = s.id
            JOIN kelas k ON i.kelas_id = k.id
            WHERE 1=1";
    $params = [];
    if ($filterStatus !== 'semua') { $sql .= " AND i.status = ?"; $params[] = $filterStatus; }
    if ($filterKelas !== '')       { $sql .= " AND i.kelas_id = ?"; $params[] = $filterKelas; }
    $sql .= " ORDER BY (i.status = 'Menunggu') DESC, i.created_at DESC, i.id DESC LIMIT 200";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $daftar = $stmt->fetchAll();
}

$csrf = izinCsrfToken();

include __DIR__ . '/../includes/header.php';
?>

<style>
.izin-card{ background:#fff; border:1px solid #E5E7EE; border-left:4px solid #E5E7EE; border-radius:10px; padding:14px 16px; margin-bottom:12px; }
.izin-card.st-Menunggu{ border-left-color:#EA7A1B; }
.izin-card.st-Disetujui{ border-left-color:#16A34A; }
.izin-card.st-Ditolak{ border-left-color:#DC2626; }
.izin-card .iz-nama{ font-weight:700; }
.izin-card .iz-meta{ font-size:.78rem; color:#6B7280; }
.izin-card .iz-alasan{ font-size:.86rem; margin:8px 0; white-space:pre-line; word-break:break-word; }
.izin-card .iz-proses{ font-size:.76rem; color:#6B7280; border-top:1px dashed #E5E7EE; padding-top:8px; margin-top:8px; }
.izin-tabs .nav-link{ font-size:.82rem; font-weight:600; }
.izin-tabs .nav-link .badge{ margin-left:4px; }
@media (max-width: 575.98px){
  .izin-card .iz-actions .btn{ flex:1 1 auto; }
}
</style>

<h4 class="fw-bold mb-3"><i class="bi bi-envelope-paper-fill me-2"></i>Izin Siswa</h4>

<?php if ($errors): ?>
  <div class="alert alert-danger">
    <?php foreach ($errors as $er): ?><div><i class="bi bi-exclamation-circle-fill me-1"></i><?= clean($er) ?></div><?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if ($user['role'] === 'siswa'): ?>
<!-- ================= TAMPILAN SISWA ================= -->
<div class="card p-3 mb-4">
  <h6 class="fw-bold mb-3"><i class="bi bi-pencil-square me-1"></i>Ajukan Izin / Sakit</h6>
  <form method="POST" enctype="multipart/form-data" id="formIzin">
    <input type="hidden" name="csrf" value="<?= clean($csrf) ?>">
    <input type="hidden" name="aksi" value="ajukan">
    <div class="row g-3">
      <div class="col-md-3">
        <label class="form-label small">Jenis</label>
        <select name="jenis" class="form-select">
          <option value="Izin"  <?= $old['jenis'] === 'Izin'  ? 'selected' : '' ?>>Izin</option>
          <option value="Sakit" <?= $old['jenis'] === 'Sakit' ? 'selected' : '' ?>>Sakit</option>
        </select>
      </div>
      <div class="col-md-3 col-6">
        <label class="form-label small">Tanggal Mulai</label>
        <input type="date" name="tanggal_mulai" id="izMulai" class="form-control" required
               min="<?= $minTanggal ?>" max="<?= $maxTanggal ?>" value="<?= clean($old['tanggal_mulai']) ?>">
      </div>
      <div class="col-md-3 col-6">
        <label class="form-label small">Tanggal Selesai</label>
        <input type="date" name="tanggal_selesai" id="izSelesai" class="form-control" required
               min="<?= $minTanggal ?>" max="<?= $maxTanggal ?>" value="<?= clean($old['tanggal_selesai']) ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label small">Lampiran <span class="text-muted">(opsional)</span></label>
        <input type="file" name="lampiran" class="form-control" accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf">
      </div>
      <div class="col-12">
        <label class="form-label small">Alasan</label>
        <textarea name="alasan" class="form-control" rows="3" maxlength="255" required
                  placeholder="Contoh: Demam dan disarankan istirahat oleh dokter."><?= clean($old['alasan']) ?></textarea>
        <div class="form-text">Lampiran berupa surat dokter / surat orang tua (JPG, PNG, atau PDF, maks. 2 MB). Satu pengajuan maksimal <?= IZIN_MAKS_HARI ?> hari, dan hanya untuk tanggal <?= IZIN_MAKS_MUNDUR ?> hari ke belakang sampai <?= IZIN_MAKS_MAJU ?> hari ke depan.</div>
      </div>
    </div>
    <button class="btn btn-primary mt-3"><i class="bi bi-send-fill"></i> Kirim Pengajuan</button>
  </form>
</div>

<h5 class="fw-bold mb-3"><i class="bi bi-clock-history me-2"></i>Riwayat Pengajuan Saya</h5>
<?php if (empty($riwayat)): ?>
  <div class="card p-4 text-center text-muted">Belum ada pengajuan izin.</div>
<?php endif; ?>
<?php foreach ($riwayat as $r): $hari = izinJumlahHari($r['tanggal_mulai'], $r['tanggal_selesai']); ?>
  <div class="izin-card st-<?= clean($r['status']) ?>">
    <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
      <div>
        <span class="fw-bold"><?= clean($r['jenis']) ?></span>
        <span class="iz-meta ms-1">· <?= $r['tanggal_mulai'] === $r['tanggal_selesai']
            ? formatTanggalIndo($r['tanggal_mulai'])
            : formatTanggalIndo($r['tanggal_mulai']) . ' – ' . formatTanggalIndo($r['tanggal_selesai']) ?> (<?= $hari ?> hari)</span>
      </div>
      <?= izinBadge($r['status']) ?>
    </div>
    <div class="iz-alasan"><?= clean($r['alasan']) ?></div>
    <?php if (!empty($r['lampiran'])): ?>
      <a href="lampiran.php?id=<?= (int)$r['id'] ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary"><i class="bi bi-paperclip"></i> Lihat lampiran</a>
    <?php endif; ?>
    <?php if ($r['status'] === 'Menunggu'): ?>
      <form method="POST" class="d-inline" onsubmit="return confirm('Batalkan pengajuan ini?');">
        <input type="hidden" name="csrf" value="<?= clean($csrf) ?>">
        <input type="hidden" name="aksi" value="batal">
        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-x-lg"></i> Batalkan</button>
      </form>
      <div class="iz-proses">Menunggu konfirmasi wali kelas / guru. Diajukan <?= clean(date('d/m/Y H:i', strtotime($r['created_at']))) ?>.</div>
    <?php else: ?>
      <div class="iz-proses">
        <?= clean($r['status']) ?> oleh <b><?= clean($r['diproses_nama'] ?: '-') ?></b>
        <?= $r['diproses_role'] ? '(' . clean(['wali_kelas' => 'Wali Kelas', 'guru' => 'Guru'][$r['diproses_role']] ?? $r['diproses_role']) . ')' : '' ?>
        <?= $r['diproses_pada'] ? '· ' . clean(date('d/m/Y H:i', strtotime($r['diproses_pada']))) : '' ?>
        <?php if (!empty($r['catatan_pemroses'])): ?><br>Catatan: <?= clean($r['catatan_pemroses']) ?><?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
<?php endforeach; ?>

<script>
(function () {
  const mulai = document.getElementById('izMulai');
  const selesai = document.getElementById('izSelesai');
  if (!mulai || !selesai) return;
  mulai.addEventListener('change', function () {
    if (!mulai.value) return;
    selesai.min = mulai.value;
    if (!selesai.value || selesai.value < mulai.value) selesai.value = mulai.value;
  });
})();
</script>

<?php else: ?>
<!-- ================= TAMPILAN WALI KELAS / GURU / ADMIN ================= -->
<?php if ($user['role'] === 'wali_kelas'): ?>
  <div class="text-muted small mb-3">Izin siswa Kelas <b><?= clean($kelasSaya['nama_kelas']) ?></b>. Anda bisa menyetujui, menolak, atau menghapus pengajuan.</div>
<?php elseif ($bisaProses): ?>
  <div class="text-muted small mb-3">Daftar izin semua kelas. Anda bisa menyetujui atau menolak pengajuan.</div>
<?php else: ?>
  <div class="alert alert-light border small">
    <i class="bi bi-eye-fill me-1"></i> Mode <b>lihat saja</b>. Persetujuan izin dilakukan oleh wali kelas / guru.
  </div>
<?php endif; ?>

<div class="card p-3 mb-3">
  <form method="GET" class="row g-2 align-items-end" id="formFilterIzin">
    <input type="hidden" name="status" value="<?= clean($filterStatus) ?>">
    <?php if ($user['role'] !== 'wali_kelas'): ?>
    <div class="col-md-4">
      <label class="form-label small">Kelas</label>
      <select name="kelas_id" class="form-select" onchange="this.form.submit()">
        <option value="">Semua kelas</option>
        <?php foreach ($kelasList as $k): ?>
          <option value="<?= (int)$k['id'] ?>" <?= (string)$filterKelas === (string)$k['id'] ? 'selected' : '' ?>><?= clean($k['nama_kelas']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>
    <div class="col">
      <ul class="nav nav-pills izin-tabs">
        <?php
        $tabs = ['Menunggu' => 'Menunggu', 'Disetujui' => 'Disetujui', 'Ditolak' => 'Ditolak', 'semua' => 'Semua'];
        foreach ($tabs as $nilai => $label):
            $jml = $nilai === 'semua' ? array_sum($hitung) : $hitung[$nilai];
            $q = http_build_query(array_filter(['status' => $nilai, 'kelas_id' => $user['role'] === 'wali_kelas' ? '' : $filterKelas], function ($v) { return $v !== ''; }));
        ?>
          <li class="nav-item">
            <a class="nav-link <?= $filterStatus === $nilai ? 'active' : '' ?>" href="?<?= clean($q) ?>">
              <?= clean($label) ?><span class="badge <?= $filterStatus === $nilai ? 'bg-light text-dark' : 'bg-secondary' ?>"><?= $jml ?></span>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </form>
</div>

<?php if (empty($daftar)): ?>
  <div class="card p-4 text-center text-muted">
    <?= $filterStatus === 'Menunggu' ? 'Tidak ada izin yang menunggu konfirmasi.' : 'Tidak ada data izin untuk filter ini.' ?>
  </div>
<?php endif; ?>

<?php foreach ($daftar as $r): $hari = izinJumlahHari($r['tanggal_mulai'], $r['tanggal_selesai']); ?>
  <div class="izin-card st-<?= clean($r['status']) ?>">
    <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
      <div>
        <div class="iz-nama"><?= clean($r['nama_lengkap']) ?> <span class="iz-meta fw-normal">· NIS <?= clean($r['nis']) ?> · Kelas <?= clean($r['nama_kelas']) ?></span></div>
        <div class="iz-meta">
          <span class="fw-semibold text-dark"><?= clean($r['jenis']) ?></span> ·
          <?= $r['tanggal_mulai'] === $r['tanggal_selesai']
              ? formatTanggalIndo($r['tanggal_mulai'])
              : formatTanggalIndo($r['tanggal_mulai']) . ' – ' . formatTanggalIndo($r['tanggal_selesai']) ?>
          (<?= $hari ?> hari) · diajukan <?= clean(date('d/m/Y H:i', strtotime($r['created_at']))) ?>
        </div>
      </div>
      <?= izinBadge($r['status']) ?>
    </div>

    <div class="iz-alasan"><?= clean($r['alasan']) ?></div>

    <div class="d-flex flex-wrap gap-2 iz-actions">
      <?php if (!empty($r['lampiran'])): ?>
        <a href="lampiran.php?id=<?= (int)$r['id'] ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary"><i class="bi bi-paperclip"></i> Lihat lampiran</a>
      <?php endif; ?>
      <?php if ($bisaProses && $r['status'] === 'Menunggu'): ?>
        <button type="button" class="btn btn-sm btn-success btn-proses"
                data-id="<?= (int)$r['id'] ?>" data-keputusan="setuju"
                data-nama="<?= clean($r['nama_lengkap']) ?>"><i class="bi bi-check-lg"></i> Setujui</button>
        <button type="button" class="btn btn-sm btn-outline-danger btn-proses"
                data-id="<?= (int)$r['id'] ?>" data-keputusan="tolak"
                data-nama="<?= clean($r['nama_lengkap']) ?>"><i class="bi bi-x-lg"></i> Tolak</button>
      <?php endif; ?>
      <?php if ($bisaHapus): ?>
        <button type="button" class="btn btn-sm btn-outline-secondary btn-hapus ms-sm-auto"
                data-id="<?= (int)$r['id'] ?>" data-status="<?= clean($r['status']) ?>"
                data-jenis="<?= clean($r['jenis']) ?>" data-nama="<?= clean($r['nama_lengkap']) ?>"><i class="bi bi-trash3"></i> Hapus</button>
      <?php endif; ?>
    </div>

    <?php if ($r['status'] !== 'Menunggu'): ?>
      <div class="iz-proses">
        <?= clean($r['status']) ?> oleh <b><?= clean($r['diproses_nama'] ?: '-') ?></b>
        <?= $r['diproses_role'] ? '(' . clean(['wali_kelas' => 'Wali Kelas', 'guru' => 'Guru'][$r['diproses_role']] ?? $r['diproses_role']) . ')' : '' ?>
        <?= $r['diproses_pada'] ? '· ' . clean(date('d/m/Y H:i', strtotime($r['diproses_pada']))) : '' ?>
        <?php if (!empty($r['catatan_pemroses'])): ?><br>Catatan: <?= clean($r['catatan_pemroses']) ?><?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
<?php endforeach; ?>
<?php if (count($daftar) >= 200): ?>
  <div class="text-muted small">Menampilkan 200 pengajuan terbaru. Gunakan filter kelas / status untuk mempersempit.</div>
<?php endif; ?>

<?php if ($bisaProses): ?>
<!-- Modal konfirmasi setuju / tolak -->
<div class="modal fade" id="modalProses" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <input type="hidden" name="csrf" value="<?= clean($csrf) ?>">
      <input type="hidden" name="aksi" value="proses">
      <input type="hidden" name="id" id="prosesId">
      <input type="hidden" name="keputusan" id="prosesKeputusan">
      <div class="modal-header">
        <h6 class="modal-title mb-0" id="prosesJudul">Konfirmasi</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="small text-muted mb-2" id="prosesInfo"></p>
        <label class="form-label small" id="prosesLabel">Catatan</label>
        <textarea name="catatan" id="prosesCatatan" class="form-control" rows="3" maxlength="255"></textarea>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn" id="prosesSubmit">Simpan</button>
      </div>
    </form>
  </div>
</div>
<script>
(function () {
  const modalEl = document.getElementById('modalProses');
  if (!modalEl) return;
  document.querySelectorAll('.btn-proses').forEach(function (b) {
    b.addEventListener('click', function () {
      const setuju = this.dataset.keputusan === 'setuju';
      document.getElementById('prosesId').value = this.dataset.id;
      document.getElementById('prosesKeputusan').value = this.dataset.keputusan;
      document.getElementById('prosesJudul').textContent = setuju ? 'Setujui izin' : 'Tolak izin';
      document.getElementById('prosesInfo').textContent = setuju
        ? 'Izin ' + this.dataset.nama + ' akan disetujui dan otomatis dicatat di absensi pada hari sekolah dalam rentang tanggal tersebut.'
        : 'Izin ' + this.dataset.nama + ' akan ditolak.';
      const catatan = document.getElementById('prosesCatatan');
      catatan.value = '';
      catatan.required = !setuju;
      document.getElementById('prosesLabel').textContent = setuju ? 'Catatan (opsional)' : 'Alasan penolakan (wajib)';
      const submit = document.getElementById('prosesSubmit');
      submit.className = 'btn ' + (setuju ? 'btn-success' : 'btn-danger');
      submit.textContent = setuju ? 'Setujui' : 'Tolak';
      new bootstrap.Modal(modalEl).show();
    });
  });
})();
</script>
<?php endif; ?>

<?php if ($bisaHapus): ?>
<!-- Modal konfirmasi hapus -->
<div class="modal fade" id="modalHapus" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <input type="hidden" name="csrf" value="<?= clean($csrf) ?>">
      <input type="hidden" name="aksi" value="hapus">
      <input type="hidden" name="id" id="hapusId">
      <div class="modal-header">
        <h6 class="modal-title mb-0"><i class="bi bi-trash3 me-1"></i> Hapus pengajuan izin</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="mb-2" id="hapusInfo"></p>
        <p class="small text-muted mb-2">Tindakan ini tidak bisa dibatalkan, dan lampiran (jika ada) ikut terhapus.</p>
        <div class="form-check d-none" id="hapusAbsensiBox">
          <input class="form-check-input" type="checkbox" name="hapus_absensi" value="1" id="hapusAbsensi" checked>
          <label class="form-check-label small" for="hapusAbsensi">
            Hapus juga catatan absensi (Izin/Sakit) yang dibuat otomatis dari izin ini.
            <span class="d-block text-muted">Jika tidak dicentang, catatan di absensi tetap ada dan bisa diubah lewat Input Manual.</span>
          </label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-danger"><i class="bi bi-trash3"></i> Hapus</button>
      </div>
    </form>
  </div>
</div>
<script>
(function () {
  const modalEl = document.getElementById('modalHapus');
  if (!modalEl) return;
  document.querySelectorAll('.btn-hapus').forEach(function (b) {
    b.addEventListener('click', function () {
      const disetujui = this.dataset.status === 'Disetujui';
      document.getElementById('hapusId').value = this.dataset.id;
      document.getElementById('hapusInfo').textContent =
        'Pengajuan ' + this.dataset.jenis.toLowerCase() + ' milik ' + this.dataset.nama +
        ' (status: ' + this.dataset.status + ') akan dihapus.';
      const box = document.getElementById('hapusAbsensiBox');
      const cb = document.getElementById('hapusAbsensi');
      box.classList.toggle('d-none', !disetujui);
      cb.checked = disetujui;
      cb.disabled = !disetujui; // checkbox nonaktif tidak ikut terkirim
      new bootstrap.Modal(modalEl).show();
    });
  });
})();
</script>
<?php endif; ?>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>