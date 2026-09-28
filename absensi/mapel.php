<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/libur.php';
requireRole(['admin','guru']);
$pageTitle = 'Absensi Per Mapel';
$user = currentUser();

$kelasList = $pdo->query("SELECT * FROM kelas ORDER BY tingkat, nama_kelas")->fetchAll();
$mapelList = $pdo->query("SELECT * FROM mata_pelajaran ORDER BY nama_mapel")->fetchAll();

$kelas_id = $_GET['kelas_id'] ?? ($kelasList[0]['id'] ?? '');
$mapel_id = $_GET['mapel_id'] ?? ($mapelList[0]['id'] ?? '');
$tanggal  = $_GET['tanggal'] ?? date('Y-m-d');

// Simpan absensi mapel
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kelas_id_post = $_POST['kelas_id'];
    $mapel_id_post = $_POST['mapel_id'];
    $tanggal_post  = $_POST['tanggal'];
    $statusArr     = $_POST['status'] ?? [];
    $ketArr        = $_POST['keterangan'] ?? [];

    $stmt = $pdo->prepare("
        INSERT INTO absensi_mapel (siswa_id, kelas_id, mapel_id, guru_id, guru_nama, tanggal, status, keterangan)
        VALUES (?,?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE status = VALUES(status), keterangan = VALUES(keterangan), guru_id = VALUES(guru_id), guru_nama = VALUES(guru_nama)
    ");
    foreach ($statusArr as $siswa_id => $status) {
        $stmt->execute([$siswa_id, $kelas_id_post, $mapel_id_post, $user['id'], $user['nama'], $tanggal_post, $status, $ketArr[$siswa_id] ?? null]);
    }
    setFlash('success', 'Absensi mapel berhasil disimpan.');
    redirect("absensi/mapel.php?kelas_id={$kelas_id_post}&mapel_id={$mapel_id_post}&tanggal={$tanggal_post}");
}

// Ambil siswa + status absensi mapel (jika sudah ada) + status kehadiran harian (referensi, read-only)
$siswaKelas = [];
if ($kelas_id && $mapel_id) {
    $stmt = $pdo->prepare("
        SELECT s.id, s.nis, s.nama_lengkap,
               am.status AS status_mapel, am.keterangan AS keterangan_mapel,
               a.status AS status_harian
        FROM siswa s
        LEFT JOIN absensi_mapel am ON am.siswa_id = s.id AND am.mapel_id = ? AND am.tanggal = ?
        LEFT JOIN absensi a ON a.siswa_id = s.id AND a.tanggal = ?
        WHERE s.kelas_id = ? AND s.status = 'Aktif'
        ORDER BY s.nama_lengkap
    ");
    $stmt->execute([$mapel_id, $tanggal, $tanggal, $kelas_id]);
    $siswaKelas = $stmt->fetchAll();
}

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h4 class="fw-bold mb-0"><i class="bi bi-journal-check me-2"></i>Absensi Per Mata Pelajaran</h4>
  <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Absensi Harian</a>
</div>

<div class="alert alert-light border small">
  Halaman ini untuk mencatat kehadiran siswa <strong>khusus di jam pelajaran Anda</strong> — terpisah dari absensi harian keseluruhan (yang sumbernya dari GPS/wali kelas). Contoh: siswa hadir di sekolah tapi tidak masuk kelas Anda, bisa ditandai Alpa di sini tanpa mengubah status kehadiran hariannya.
</div>

<div class="card p-3 mb-3">
  <form method="GET" class="row g-2">
    <div class="col-md-4">
      <label class="form-label small">Kelas</label>
      <select name="kelas_id" class="form-select" onchange="this.form.submit()">
        <?php foreach ($kelasList as $k): ?>
          <option value="<?= $k['id'] ?>" <?= (string)$kelas_id === (string)$k['id'] ? 'selected' : '' ?>><?= clean($k['nama_kelas']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-4">
      <label class="form-label small">Mata Pelajaran</label>
      <select name="mapel_id" class="form-select" onchange="this.form.submit()">
        <?php foreach ($mapelList as $m): ?>
          <option value="<?= $m['id'] ?>" <?= (string)$mapel_id === (string)$m['id'] ? 'selected' : '' ?>><?= clean($m['nama_mapel']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-4">
      <label class="form-label small">Tanggal</label>
      <input type="date" name="tanggal" class="form-control" value="<?= clean($tanggal) ?>" onchange="this.form.submit()">
    </div>
  </form>
</div>

<?php $infoLibur = cekHariLibur($pdo, $tanggal); ?>
<?php if ($infoLibur['libur']): ?>
<div class="alert alert-info small d-flex align-items-center gap-2">
  <i class="bi bi-calendar-x-fill fs-5"></i>
  <div><strong><?= formatTanggalIndo($tanggal) ?> adalah hari libur</strong> (<?= clean($infoLibur['keterangan']) ?>). Kalau memang ada kelas pengganti hari ini, silakan lanjutkan mengisi seperti biasa.</div>
</div>
<?php endif; ?>

<?php if ($kelas_id && $mapel_id): ?>
<div class="card p-3">
  <form method="POST">
    <input type="hidden" name="kelas_id" value="<?= clean($kelas_id) ?>">
    <input type="hidden" name="mapel_id" value="<?= clean($mapel_id) ?>">
    <input type="hidden" name="tanggal" value="<?= clean($tanggal) ?>">

    <div class="mb-2 d-flex gap-2">
      <button type="button" class="btn btn-sm btn-outline-success" onclick="setAllStatus('Hadir')">Tandai Semua Hadir</button>
    </div>

    <div class="table-responsive">
      <table class="table table-hover align-middle">
        <thead class="table-light">
          <tr><th>#</th><th>NIS</th><th>Nama Siswa</th><th>Kehadiran Hari Ini</th><th style="min-width:260px;">Status di Mapel Ini</th><th>Keterangan</th></tr>
        </thead>
        <tbody>
          <?php if (empty($siswaKelas)): ?>
            <tr><td colspan="6" class="text-center text-muted py-4">Tidak ada siswa aktif di kelas ini.</td></tr>
          <?php endif; ?>
          <?php foreach ($siswaKelas as $i => $s): ?>
          <tr>
            <td><?= $i + 1 ?></td>
            <td><?= clean($s['nis']) ?></td>
            <td class="fw-semibold"><?= clean($s['nama_lengkap']) ?></td>
            <td class="small">
              <?= $s['status_harian'] ? badgeStatusAbsen($s['status_harian']) : "<span class='badge-soft'>Belum absen</span>" ?>
            </td>
            <td>
              <?php $statusNow = $s['status_mapel'] ?? 'Hadir'; ?>
              <?php foreach (['Hadir','Izin','Sakit','Alpa'] as $opt): ?>
                <div class="form-check form-check-inline">
                  <input class="form-check-input status-radio" type="radio" name="status[<?= $s['id'] ?>]" value="<?= $opt ?>" <?= $statusNow === $opt ? 'checked' : '' ?>>
                  <label class="form-check-label small"><?= $opt ?></label>
                </div>
              <?php endforeach; ?>
            </td>
            <td><input type="text" name="keterangan[<?= $s['id'] ?>]" class="form-control form-control-sm" value="<?= clean($s['keterangan_mapel'] ?? '') ?>" placeholder="opsional"></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if (!empty($siswaKelas)): ?>
      <button class="btn btn-primary"><i class="bi bi-save"></i> Simpan Absensi Mapel</button>
    <?php endif; ?>
  </form>
</div>
<?php endif; ?>

<script>
function setAllStatus(status) {
  document.querySelectorAll('.status-radio').forEach(function (r) {
    if (r.value === status) r.checked = true;
  });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>