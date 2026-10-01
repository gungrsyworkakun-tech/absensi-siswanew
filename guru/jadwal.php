<?php
// Jadwal mengajar guru. Jam pelajaran inilah yang dipakai sebagai acuan batas "tepat waktu" absen guru.
// - Admin           : menambah & menghapus jadwal semua guru.
// - Kepala sekolah  : melihat semua jadwal.
// - Guru/wali kelas : melihat jadwal sendiri.
date_default_timezone_set('Asia/Makassar');

require_once __DIR__ . '/../includes/auth.php';

const GJ_PENGELOLA = ['admin'];
const GJ_PEMANTAU  = ['admin', 'kepala_sekolah'];

$user = currentUser();
$pageTitle = 'Jadwal Mengajar';
requireRole(['guru', 'wali_kelas', 'admin', 'kepala_sekolah']);

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

function gjCsrfToken() {
    if (empty($_SESSION['gj_csrf'])) { $_SESSION['gj_csrf'] = bin2hex(random_bytes(16)); }
    return $_SESSION['gj_csrf'];
}
function gjCsrfValid($t) {
    return !empty($_SESSION['gj_csrf']) && is_string($t) && hash_equals($_SESSION['gj_csrf'], $t);
}
function gjWaktu($s) { return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string)$s) ? $s . ':00' : null; }

try {
    $pdo->query("SELECT 1 FROM jadwal_mengajar LIMIT 1");
    $pdo->query("SELECT toleransi_menit FROM pengaturan_absen_guru LIMIT 1");
} catch (PDOException $e) {
    include __DIR__ . '/../includes/header.php';
    echo "<div class='card p-4'><h5 class='fw-bold'><i class='bi bi-exclamation-triangle text-warning me-2'></i>Fitur Jadwal Mengajar belum aktif</h5>"
       . "<p class='mb-0'>Import file <b>guru_tambah.sql</b> lalu <b>guru_profil_jadwal.sql</b> ke database <b>db_absensi_sekolah</b> melalui phpMyAdmin, lalu refresh halaman ini.</p></div>";
    include __DIR__ . '/../includes/footer.php';
    exit;
}

$pemantau = in_array($user['role'], GJ_PEMANTAU, true);
$bisaKelola = in_array($user['role'], GJ_PENGELOLA, true);
$namaHari = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat'];

$guruList = $pdo->query("SELECT id, nama, role FROM users WHERE role IN ('guru','wali_kelas') ORDER BY nama")->fetchAll();
$guruIdValid = array_column($guruList, 'id');

// Guru biasa: otomatis dikunci ke dirinya. Admin / kepala sekolah: boleh memilih (kosong = semua).
$filterGuru = $pemantau ? (int)($_GET['guru_id'] ?? 0) : (int)$user['id'];
if ($pemantau && $filterGuru && !in_array($filterGuru, array_map('intval', $guruIdValid), true)) { $filterGuru = 0; }

$mapelList = $pdo->query("SELECT id, nama_mapel FROM mata_pelajaran ORDER BY nama_mapel")->fetchAll();
$kelasList = $pdo->query("SELECT id, nama_kelas FROM kelas ORDER BY tingkat, nama_kelas")->fetchAll();

$errors = [];
$old = ['guru_id' => $filterGuru ?: '', 'mapel_id' => '', 'kelas_id' => '', 'hari' => '1', 'jam_mulai' => '07:00', 'jam_selesai' => '08:30'];

/* ================= POST (admin) ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';
    $kembali = 'guru/jadwal.php' . ($filterGuru && $pemantau ? '?guru_id=' . $filterGuru : '');

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
        $old = ['guru_id' => $gid ?: '', 'mapel_id' => $mid ?: '', 'kelas_id' => $kid ?: '', 'hari' => (string)$hari, 'jam_mulai' => $mulaiIn, 'jam_selesai' => $selesaiIn];

        $mulai = gjWaktu($mulaiIn);
        $selesai = gjWaktu($selesaiIn);

        if (!in_array($gid, array_map('intval', $guruIdValid), true)) { $errors[] = 'Pilih guru yang valid.'; }
        if (!in_array($mid, array_map('intval', array_column($mapelList, 'id')), true)) { $errors[] = 'Pilih mata pelajaran yang valid.'; }
        if (!in_array($kid, array_map('intval', array_column($kelasList, 'id')), true)) { $errors[] = 'Pilih kelas yang valid.'; }
        if (!isset($namaHari[$hari])) { $errors[] = 'Pilih hari Senin sampai Jumat.'; }
        if (!$mulai || !$selesai) { $errors[] = 'Format jam tidak valid.'; }
        elseif ($mulai >= $selesai) { $errors[] = 'Jam selesai harus lebih besar dari jam mulai.'; }

        if (!$errors) {
            // Bentrok: guru yang sama / kelas yang sama pada hari & jam yang beririsan
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM jadwal_mengajar WHERE guru_id = ? AND hari = ? AND jam_mulai < ? AND jam_selesai > ?");
            $stmt->execute([$gid, $hari, $selesai, $mulai]);
            if ((int)$stmt->fetchColumn() > 0) { $errors[] = 'Guru ini sudah punya jadwal lain yang bentrok pada hari dan jam tersebut.'; }

            $stmt = $pdo->prepare("SELECT COUNT(*) FROM jadwal_mengajar WHERE kelas_id = ? AND hari = ? AND jam_mulai < ? AND jam_selesai > ?");
            $stmt->execute([$kid, $hari, $selesai, $mulai]);
            if ((int)$stmt->fetchColumn() > 0) { $errors[] = 'Kelas tersebut sudah punya pelajaran lain yang bentrok pada hari dan jam tersebut.'; }
        }

        if (!$errors) {
            $stmt = $pdo->prepare("INSERT INTO jadwal_mengajar (guru_id, mapel_id, kelas_id, hari, jam_mulai, jam_selesai) VALUES (?,?,?,?,?,?)");
            $stmt->execute([$gid, $mid, $kid, $hari, $mulai, $selesai]);
            setFlash('success', 'Jadwal mengajar ditambahkan.');
            redirect('guru/jadwal.php?guru_id=' . $gid);
        }

    } elseif ($aksi === 'hapus') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM jadwal_mengajar WHERE id = ?");
        $stmt->execute([$id]);
        setFlash('success', $stmt->rowCount() ? 'Jadwal dihapus.' : 'Jadwal tidak ditemukan.');
        redirect($kembali);
    } else {
        $errors[] = 'Aksi tidak dikenal.';
    }
}

/* ================= Data jadwal ================= */
$sql = "SELECT j.*, m.nama_mapel, k.nama_kelas, u.nama AS nama_guru
        FROM jadwal_mengajar j
        JOIN mata_pelajaran m ON j.mapel_id = m.id
        JOIN kelas k ON j.kelas_id = k.id
        JOIN users u ON j.guru_id = u.id";
$params = [];
if ($filterGuru) { $sql .= " WHERE j.guru_id = ?"; $params[] = $filterGuru; }
$sql .= " ORDER BY j.hari, j.jam_mulai, u.nama";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$perHari = [];
foreach ($stmt->fetchAll() as $r) { $perHari[$r['hari']][] = $r; }

$tol = (int)($pdo->query("SELECT toleransi_menit FROM pengaturan_absen_guru ORDER BY id LIMIT 1")->fetchColumn() ?: 0);
$csrf = gjCsrfToken();

include __DIR__ . '/../includes/header.php';
?>

<h4 class="fw-bold mb-3"><i class="bi bi-calendar-week-fill me-2"></i>Jadwal Mengajar</h4>

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
</div>

<?php if ($pemantau): ?>
<div class="card p-3 mb-3">
  <form method="GET" class="row g-2 align-items-end">
    <div class="col-md-5">
      <label class="form-label small">Tampilkan jadwal</label>
      <select name="guru_id" class="form-select" onchange="this.form.submit()">
        <option value="">Semua guru</option>
        <?php foreach ($guruList as $g): ?>
          <option value="<?= (int)$g['id'] ?>" <?= $filterGuru === (int)$g['id'] ? 'selected' : '' ?>><?= clean($g['nama']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </form>
</div>
<?php endif; ?>

<?php if ($bisaKelola): ?>
<div class="card p-3 mb-4">
  <h6 class="fw-bold mb-3"><i class="bi bi-plus-circle-fill me-1"></i>Tambah Jadwal</h6>
  <form method="POST">
    <input type="hidden" name="csrf" value="<?= clean($csrf) ?>">
    <input type="hidden" name="aksi" value="tambah">
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
      <div class="col-md-3">
        <label class="form-label small">Hari</label>
        <select name="hari" class="form-select">
          <?php foreach ($namaHari as $no => $nm): ?><option value="<?= $no ?>" <?= (string)$old['hari'] === (string)$no ? 'selected' : '' ?>><?= $nm ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-3"><label class="form-label small">Jam Mulai</label><input type="time" name="jam_mulai" class="form-control" value="<?= clean($old['jam_mulai']) ?>" required></div>
      <div class="col-6 col-md-3"><label class="form-label small">Jam Selesai</label><input type="time" name="jam_selesai" class="form-control" value="<?= clean($old['jam_selesai']) ?>" required></div>
      <div class="col-md-3 d-flex align-items-end"><button class="btn btn-primary w-100"><i class="bi bi-plus-lg"></i> Tambah</button></div>
    </div>
  </form>
</div>
<?php endif; ?>

<?php if (empty($perHari)): ?>
  <div class="card p-4 text-center text-muted">Belum ada jadwal mengajar<?= $filterGuru && $pemantau ? ' untuk guru ini' : '' ?>.</div>
<?php endif; ?>

<?php foreach ($namaHari as $no => $nm):
    if (empty($perHari[$no])) { continue; }
    $baris = $perHari[$no];
    // Acuan absen dihitung per guru, jadi hanya ditampilkan jika yang dilihat satu guru
    $pertama = $baris[0]['jam_mulai'];
    $terakhir = '00:00:00';
    foreach ($baris as $b) { if ($b['jam_selesai'] > $terakhir) { $terakhir = $b['jam_selesai']; } }
    $batasTepat = date('H:i', strtotime("2000-01-01 {$pertama}") + $tol * 60);
?>
  <div class="card p-3 mb-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
      <h6 class="fw-bold mb-0"><?= $nm ?></h6>
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

<?php include __DIR__ . '/../includes/footer.php'; ?>