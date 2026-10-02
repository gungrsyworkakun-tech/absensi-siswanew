<?php
// Biodata guru & wali kelas.
// - Guru / wali kelas : mengisi & mengubah biodata sendiri.
// - Admin             : melihat & mengubah biodata siapa pun (?user_id=).
// - Kepala sekolah    : hanya melihat.
date_default_timezone_set('Asia/Makassar');

require_once __DIR__ . '/../includes/auth.php';

const GP_PENGELOLA = ['admin'];                    // boleh mengubah biodata orang lain
const GP_PEMANTAU  = ['admin', 'kepala_sekolah'];  // boleh membuka biodata orang lain

$user = currentUser();
$pageTitle = 'Biodata Guru';
requireRole(['guru', 'wali_kelas', 'admin', 'kepala_sekolah']);

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

function gpCsrfToken() {
    if (empty($_SESSION['gp_csrf'])) { $_SESSION['gp_csrf'] = bin2hex(random_bytes(16)); }
    return $_SESSION['gp_csrf'];
}
function gpCsrfValid($t) {
    return !empty($_SESSION['gp_csrf']) && is_string($t) && hash_equals($_SESSION['gp_csrf'], $t);
}
function gpTanggal($s) {
    $d = DateTime::createFromFormat('Y-m-d', (string)$s);
    return ($d && $d->format('Y-m-d') === $s) ? $s : null;
}
function gpTeks($v, $maks) { return mb_substr(trim((string)$v), 0, $maks); }
function gpNull($v) { return $v === '' ? null : $v; }

/* ================= Cek migrasi ================= */
try {
    $pdo->query("SELECT 1 FROM guru_profil LIMIT 1");
    $pdo->query("SELECT semester, tahun_ajaran, berlaku_mulai, berlaku_sampai FROM jadwal_mengajar LIMIT 1");
} catch (PDOException $e) {
    include __DIR__ . '/../includes/header.php';
    echo "<div class='card p-4'><h5 class='fw-bold'><i class='bi bi-exclamation-triangle text-warning me-2'></i>Fitur Biodata Guru belum aktif</h5>"
       . "<p class='mb-0'>Import file <b>guru_profil_jadwal.sql</b> ke database <b>db_absensi_sekolah</b> melalui phpMyAdmin, lalu refresh halaman ini.</p></div>";
    include __DIR__ . '/../includes/footer.php';
    exit;
}

/* ================= Tentukan akun yang dibuka ================= */
$pemantau = in_array($user['role'], GP_PEMANTAU, true);
if ($pemantau) {
    $targetId = (int)($_GET['user_id'] ?? $_POST['user_id'] ?? 0);
} else {
    $targetId = (int)$user['id'];   // guru / wali kelas hanya bisa membuka miliknya sendiri
}

$stmt = $pdo->prepare("SELECT id, username, nama, role FROM users WHERE id = ? AND role IN ('guru','wali_kelas')");
$stmt->execute([$targetId]);
$target = $stmt->fetch();
if (!$target) {
    if ($pemantau) { redirect('guru/list.php'); }
    include __DIR__ . '/../includes/header.php';
    echo "<div class='card p-4 text-center'><p class='mb-0'>Data tidak ditemukan.</p></div>";
    include __DIR__ . '/../includes/footer.php';
    exit;
}

$milikSendiri = ((int)$target['id'] === (int)$user['id']);
$bisaEdit = $milikSendiri || in_array($user['role'], GP_PENGELOLA, true);

$errors = [];
$statusList = ['PNS', 'PPPK', 'GTY', 'Honorer', 'Lainnya'];
$agamaList = ['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Konghucu', 'Lainnya'];

$stmt = $pdo->prepare("SELECT * FROM guru_profil WHERE user_id = ?");
$stmt->execute([$target['id']]);
$profil = $stmt->fetch() ?: [];
$form = $profil;

/* ================= Simpan ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$bisaEdit) {
        $errors[] = 'Anda tidak berhak mengubah biodata ini.';
    } elseif (!gpCsrfValid($_POST['csrf'] ?? '')) {
        $errors[] = 'Sesi formulir tidak valid. Muat ulang halaman lalu coba lagi.';
    } else {
        $f = [
            'nip'                 => gpTeks($_POST['nip'] ?? '', 30),
            'nuptk'               => gpTeks($_POST['nuptk'] ?? '', 30),
            'jenis_kelamin'       => gpTeks($_POST['jenis_kelamin'] ?? '', 1),
            'tempat_lahir'        => gpTeks($_POST['tempat_lahir'] ?? '', 60),
            'tanggal_lahir'       => gpTeks($_POST['tanggal_lahir'] ?? '', 10),
            'agama'               => gpTeks($_POST['agama'] ?? '', 20),
            'alamat'              => gpTeks($_POST['alamat'] ?? '', 255),
            'no_hp'               => gpTeks($_POST['no_hp'] ?? '', 20),
            'email'               => gpTeks($_POST['email'] ?? '', 100),
            'pendidikan_terakhir' => gpTeks($_POST['pendidikan_terakhir'] ?? '', 50),
            'bidang_keahlian'     => gpTeks($_POST['bidang_keahlian'] ?? '', 100),
            'jabatan'             => gpTeks($_POST['jabatan'] ?? '', 100),
            'status_kepegawaian'  => gpTeks($_POST['status_kepegawaian'] ?? '', 20),
            'tanggal_masuk'       => gpTeks($_POST['tanggal_masuk'] ?? '', 10),
        ];
        $form = $f;

        if ($f['nip'] !== '' && !preg_match('/^[0-9A-Za-z .\-]{3,30}$/', $f['nip']))   { $errors[] = 'NIP hanya boleh huruf, angka, spasi, titik, atau strip.'; }
        if ($f['nuptk'] !== '' && !preg_match('/^[0-9A-Za-z .\-]{3,30}$/', $f['nuptk'])) { $errors[] = 'NUPTK hanya boleh huruf, angka, spasi, titik, atau strip.'; }
        if ($f['jenis_kelamin'] !== '' && !in_array($f['jenis_kelamin'], ['L', 'P'], true)) { $errors[] = 'Jenis kelamin tidak valid.'; }
        if ($f['agama'] !== '' && !in_array($f['agama'], $agamaList, true))             { $errors[] = 'Agama tidak valid.'; }
        if ($f['status_kepegawaian'] !== '' && !in_array($f['status_kepegawaian'], $statusList, true)) { $errors[] = 'Status kepegawaian tidak valid.'; }
        if ($f['no_hp'] !== '' && !preg_match('/^[0-9+\-\s]{6,20}$/', $f['no_hp']))     { $errors[] = 'Nomor HP hanya boleh angka, spasi, + atau strip (6–20 karakter).'; }
        if ($f['email'] !== '' && !filter_var($f['email'], FILTER_VALIDATE_EMAIL))      { $errors[] = 'Format email tidak valid.'; }
        if ($f['tanggal_lahir'] !== '') {
            if (!gpTanggal($f['tanggal_lahir']) || $f['tanggal_lahir'] >= date('Y-m-d')) { $errors[] = 'Tanggal lahir tidak valid.'; }
        }
        if ($f['tanggal_masuk'] !== '') {
            if (!gpTanggal($f['tanggal_masuk']) || $f['tanggal_masuk'] > date('Y-m-d'))  { $errors[] = 'Tanggal mulai bertugas tidak valid.'; }
        }

        if (!$errors) {
            $nilai = array_map('gpNull', $f);
            try {
                $sql = "INSERT INTO guru_profil
                          (user_id, nip, nuptk, jenis_kelamin, tempat_lahir, tanggal_lahir, agama, alamat, no_hp, email,
                           pendidikan_terakhir, bidang_keahlian, jabatan, status_kepegawaian, tanggal_masuk)
                        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                        ON DUPLICATE KEY UPDATE
                          nip=VALUES(nip), nuptk=VALUES(nuptk), jenis_kelamin=VALUES(jenis_kelamin), tempat_lahir=VALUES(tempat_lahir),
                          tanggal_lahir=VALUES(tanggal_lahir), agama=VALUES(agama), alamat=VALUES(alamat), no_hp=VALUES(no_hp),
                          email=VALUES(email), pendidikan_terakhir=VALUES(pendidikan_terakhir), bidang_keahlian=VALUES(bidang_keahlian),
                          jabatan=VALUES(jabatan), status_kepegawaian=VALUES(status_kepegawaian), tanggal_masuk=VALUES(tanggal_masuk)";
                $pdo->prepare($sql)->execute([
                    $target['id'], $nilai['nip'], $nilai['nuptk'], $nilai['jenis_kelamin'], $nilai['tempat_lahir'], $nilai['tanggal_lahir'],
                    $nilai['agama'], $nilai['alamat'], $nilai['no_hp'], $nilai['email'], $nilai['pendidikan_terakhir'],
                    $nilai['bidang_keahlian'], $nilai['jabatan'], $nilai['status_kepegawaian'], $nilai['tanggal_masuk'],
                ]);
                setFlash('success', 'Biodata berhasil disimpan.');
                redirect('guru/profil.php' . ($pemantau ? '?user_id=' . (int)$target['id'] : ''));
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    $errors[] = 'NIP tersebut sudah dipakai guru lain.';
                } else {
                    $errors[] = 'Gagal menyimpan biodata. Coba lagi.';
                }
            }
        }
    }
}

/* ================= Jadwal ringkas guru ini ================= */
$namaHari = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat'];
$stmt = $pdo->prepare("
    SELECT j.hari, j.jam_mulai, j.jam_selesai, j.berlaku_mulai, j.berlaku_sampai, m.nama_mapel, k.nama_kelas
    FROM jadwal_mengajar j
    JOIN mata_pelajaran m ON j.mapel_id = m.id
    JOIN kelas k ON j.kelas_id = k.id
    WHERE j.guru_id = ? AND j.semester = ? AND j.tahun_ajaran = ?
    ORDER BY j.hari, j.jam_mulai
");
$awalPeriode = (int)date('n') >= 7 ? (int)date('Y') : (int)date('Y') - 1;
$stmt->execute([$target['id'], (int)date('n') >= 7 ? 'Ganjil' : 'Genap', $awalPeriode . '/' . ($awalPeriode + 1)]);
$jadwal = $stmt->fetchAll();
$mapelDiampu = [];
foreach ($jadwal as $j) { $mapelDiampu[$j['nama_mapel']] = true; }

$p = function ($k) use ($form) { return $form[$k] ?? ''; };
$csrf = gpCsrfToken();
$labelRole = ['guru' => 'Guru', 'wali_kelas' => 'Wali Kelas'][$target['role']] ?? $target['role'];

include __DIR__ . '/../includes/header.php';
?>

<style>
.gp-avatar{ width:64px; height:64px; border-radius:50%; background:#131A2E; color:#F4B740; display:flex; align-items:center; justify-content:center; font-size:1.6rem; font-weight:800; flex-shrink:0; }
.gp-chip{ display:inline-block; background:#EDEFF5; color:#131A2E; border-radius:6px; font-size:.74rem; font-weight:700; padding:3px 9px; margin:0 4px 4px 0; }
.gp-jadwal-row{ display:flex; gap:10px; font-size:.82rem; padding:6px 0; border-top:1px solid #E5E7EE; }
.gp-jadwal-row:first-of-type{ border-top:none; }
.gp-jadwal-row .h{ width:58px; font-weight:700; color:#6B7280; flex-shrink:0; }
</style>

<div class="d-flex align-items-center gap-3 mb-3 flex-wrap">
  <div class="gp-avatar"><?= strtoupper(mb_substr($target['nama'], 0, 1)) ?></div>
  <div>
    <h4 class="fw-bold mb-0"><?= clean($target['nama']) ?></h4>
    <div class="text-muted small"><?= clean($labelRole) ?> · akun <b><?= clean($target['username']) ?></b></div>
  </div>
  <?php if ($pemantau): ?>
    <a href="list.php" class="btn btn-outline-secondary btn-sm ms-auto"><i class="bi bi-arrow-left"></i> Daftar Guru</a>
  <?php endif; ?>
</div>

<?php if ($errors): ?>
  <div class="alert alert-danger">
    <?php foreach ($errors as $er): ?><div><i class="bi bi-exclamation-circle-fill me-1"></i><?= clean($er) ?></div><?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if (empty($profil) && $bisaEdit && $milikSendiri): ?>
  <div class="alert alert-info small"><i class="bi bi-info-circle-fill me-1"></i>Biodata Anda belum dilengkapi. Silakan isi formulir di bawah.</div>
<?php endif; ?>
<?php if (!$bisaEdit): ?>
  <div class="alert alert-light border small"><i class="bi bi-eye-fill me-1"></i>Mode lihat saja.</div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-lg-8">
    <form method="POST" class="card p-3">
      <input type="hidden" name="csrf" value="<?= clean($csrf) ?>">
      <input type="hidden" name="user_id" value="<?= (int)$target['id'] ?>">
      <?php $dis = $bisaEdit ? '' : 'disabled'; ?>
      <h6 class="fw-bold mb-3"><i class="bi bi-person-vcard-fill me-1"></i>Biodata</h6>
      <div class="row g-3">
        <div class="col-md-6"><label class="form-label small">NIP</label><input type="text" name="nip" class="form-control" maxlength="30" value="<?= clean($p('nip')) ?>" <?= $dis ?>></div>
        <div class="col-md-6"><label class="form-label small">NUPTK</label><input type="text" name="nuptk" class="form-control" maxlength="30" value="<?= clean($p('nuptk')) ?>" <?= $dis ?>></div>

        <div class="col-md-4">
          <label class="form-label small">Jenis Kelamin</label>
          <select name="jenis_kelamin" class="form-select" <?= $dis ?>>
            <option value="">-</option>
            <option value="L" <?= $p('jenis_kelamin') === 'L' ? 'selected' : '' ?>>Laki-laki</option>
            <option value="P" <?= $p('jenis_kelamin') === 'P' ? 'selected' : '' ?>>Perempuan</option>
          </select>
        </div>
        <div class="col-md-4"><label class="form-label small">Tempat Lahir</label><input type="text" name="tempat_lahir" class="form-control" maxlength="60" value="<?= clean($p('tempat_lahir')) ?>" <?= $dis ?>></div>
        <div class="col-md-4"><label class="form-label small">Tanggal Lahir</label><input type="date" name="tanggal_lahir" class="form-control" max="<?= date('Y-m-d', strtotime('-1 day')) ?>" value="<?= clean($p('tanggal_lahir')) ?>" <?= $dis ?>></div>

        <div class="col-md-4">
          <label class="form-label small">Agama</label>
          <select name="agama" class="form-select" <?= $dis ?>>
            <option value="">-</option>
            <?php foreach ($agamaList as $a): ?><option value="<?= $a ?>" <?= $p('agama') === $a ? 'selected' : '' ?>><?= $a ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4"><label class="form-label small">No. HP</label><input type="text" name="no_hp" class="form-control" maxlength="20" value="<?= clean($p('no_hp')) ?>" <?= $dis ?>></div>
        <div class="col-md-4"><label class="form-label small">Email</label><input type="email" name="email" class="form-control" maxlength="100" value="<?= clean($p('email')) ?>" <?= $dis ?>></div>

        <div class="col-12"><label class="form-label small">Alamat</label><input type="text" name="alamat" class="form-control" maxlength="255" value="<?= clean($p('alamat')) ?>" <?= $dis ?>></div>

        <div class="col-md-4"><label class="form-label small">Pendidikan Terakhir</label><input type="text" name="pendidikan_terakhir" class="form-control" maxlength="50" placeholder="Contoh: S1 Pendidikan Matematika" value="<?= clean($p('pendidikan_terakhir')) ?>" <?= $dis ?>></div>
        <div class="col-md-4"><label class="form-label small">Bidang Keahlian</label><input type="text" name="bidang_keahlian" class="form-control" maxlength="100" value="<?= clean($p('bidang_keahlian')) ?>" <?= $dis ?>></div>
        <div class="col-md-4"><label class="form-label small">Jabatan</label><input type="text" name="jabatan" class="form-control" maxlength="100" placeholder="Contoh: Guru Mapel" value="<?= clean($p('jabatan')) ?>" <?= $dis ?>></div>

        <div class="col-md-6">
          <label class="form-label small">Status Kepegawaian</label>
          <select name="status_kepegawaian" class="form-select" <?= $dis ?>>
            <option value="">-</option>
            <?php foreach ($statusList as $s): ?><option value="<?= $s ?>" <?= $p('status_kepegawaian') === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6"><label class="form-label small">Mulai Bertugas</label><input type="date" name="tanggal_masuk" class="form-control" max="<?= date('Y-m-d') ?>" value="<?= clean($p('tanggal_masuk')) ?>" <?= $dis ?>></div>
      </div>
      <div class="form-text mt-2">Nama tampil diubah lewat menu Kelola Akun.</div>
      <?php if ($bisaEdit): ?><button class="btn btn-primary mt-3 align-self-start"><i class="bi bi-save"></i> Simpan Biodata</button><?php endif; ?>
    </form>
  </div>

  <div class="col-lg-4">
    <div class="card p-3 mb-3">
      <h6 class="fw-bold mb-2"><i class="bi bi-journal-bookmark-fill me-1"></i>Mata Pelajaran Diampu</h6>
      <?php if (empty($mapelDiampu)): ?>
        <div class="text-muted small">Belum ada jadwal mengajar.</div>
      <?php else: foreach (array_keys($mapelDiampu) as $mp): ?><span class="gp-chip"><?= clean($mp) ?></span><?php endforeach; endif; ?>
    </div>
    <div class="card p-3">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h6 class="fw-bold mb-0"><i class="bi bi-calendar-week-fill me-1"></i>Jadwal Mengajar <span class="text-muted fw-normal small">(periode berjalan)</span></h6>
        <a href="jadwal.php<?= $pemantau ? '?guru_id=' . (int)$target['id'] : '' ?>" class="small">Detail</a>
      </div>
      <?php if (empty($jadwal)): ?><div class="text-muted small">Belum ada jadwal.</div><?php endif; ?>
      <?php foreach ($jadwal as $j): ?>
        <div class="gp-jadwal-row">
          <div class="h"><?= $namaHari[$j['hari']] ?? '-' ?></div>
          <div><?= substr($j['jam_mulai'], 0, 5) ?>–<?= substr($j['jam_selesai'], 0, 5) ?><br>
            <span class="text-muted"><?= clean($j['nama_mapel']) ?> · <?= clean($j['nama_kelas']) ?></span>
            <?php if ($j['berlaku_mulai'] || $j['berlaku_sampai']): ?>
              <br><span class="badge bg-info-subtle text-info-emphasis border">
                <?= ($j['berlaku_mulai'] && $j['berlaku_mulai'] === $j['berlaku_sampai'])
                    ? 'Sekali, ' . date('d/m/Y', strtotime($j['berlaku_mulai']))
                    : ($j['berlaku_mulai'] ? date('d/m/Y', strtotime($j['berlaku_mulai'])) : 'awal periode') . ' – ' . ($j['berlaku_sampai'] ? date('d/m/Y', strtotime($j['berlaku_sampai'])) : 'akhir periode') ?>
              </span>
            <?php endif; ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>