<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/libur.php';
requireRole(['admin','guru','wali_kelas']);
$pageTitle = 'Monitor Absen GPS';
$user = currentUser();

// Wali kelas hanya boleh lihat kelasnya sendiri
$kelasSaya = null;
if ($user['role'] === 'wali_kelas') {
    $kelasSaya = kelasWaliSaya($pdo, $user['id']);
    if (!$kelasSaya) {
        include __DIR__ . '/../includes/header.php';
        echo "<div class='card p-4 text-center'><i class='bi bi-exclamation-triangle text-warning fs-1 mb-2'></i><p class='mb-0'>Akun Anda belum dihubungkan ke kelas manapun. Hubungi admin sekolah.</p></div>";
        include __DIR__ . '/../includes/footer.php';
        exit;
    }
}

$kelasList = $pdo->query("SELECT * FROM kelas ORDER BY tingkat, nama_kelas")->fetchAll();
$kelas_id = $kelasSaya ? $kelasSaya['id'] : ($_GET['kelas_id'] ?? ($kelasList[0]['id'] ?? ''));
$tanggal  = $_GET['tanggal'] ?? date('Y-m-d');

$dataSiswa = [];
if ($kelas_id) {
    // Catatan: absensi_gps bisa punya 2 baris "Berhasil" per siswa per hari
    // (satu Masuk, satu Pulang) — makanya join ke GPS masuk difilter tipe='Masuk'
    // secara eksplisit. Info pulang diambil dari tabel absensi sendiri
    // (jam_pulang/jarak_pulang_meter) karena itu yang jadi catatan resminya.
    $stmt = $pdo->prepare("
        SELECT s.id, s.nis, s.nama_lengkap,
               a.status, a.keterangan, a.jam_pulang, a.jarak_pulang_meter,
               gm.waktu AS jam_masuk, gm.jarak_meter AS jarak_masuk
        FROM siswa s
        LEFT JOIN absensi a ON a.siswa_id = s.id AND a.tanggal = ?
        LEFT JOIN absensi_gps gm ON gm.siswa_id = s.id AND gm.tanggal = ? AND gm.tipe = 'Masuk' AND gm.status = 'Berhasil'
        WHERE s.kelas_id = ? AND s.status = 'Aktif'
        ORDER BY s.nama_lengkap
    ");
    $stmt->execute([$tanggal, $tanggal, $kelas_id]);
    $dataSiswa = $stmt->fetchAll();
}

$totalSiswa      = count($dataSiswa);
$totalHadir      = count(array_filter($dataSiswa, fn($s) => $s['status'] === 'Hadir'));
$totalBelumAbsen = $totalSiswa - $totalHadir;
$totalSudahPulang = count(array_filter($dataSiswa, fn($s) => !empty($s['jam_pulang'])));
$totalBelumPulang = $totalHadir - $totalSudahPulang;

$infoLibur = cekHariLibur($pdo, $tanggal);

// Log percobaan ditolak hari ini (masuk maupun pulang, untuk transparansi ke wali kelas/guru)
$logDitolak = [];
if ($kelas_id) {
    $stmt = $pdo->prepare("
        SELECT g.*, s.nama_lengkap FROM absensi_gps g
        JOIN siswa s ON s.id = g.siswa_id
        WHERE g.kelas_id = ? AND g.tanggal = ? AND g.status = 'Ditolak'
        ORDER BY g.created_at DESC LIMIT 15
    ");
    $stmt->execute([$kelas_id, $tanggal]);
    $logDitolak = $stmt->fetchAll();
}

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h4 class="fw-bold mb-0"><i class="bi bi-geo-alt-fill me-2"></i>Monitor Absen GPS<?= $kelasSaya ? ' — Kelas ' . clean($kelasSaya['nama_kelas']) : '' ?></h4>
  <div class="d-flex gap-2 flex-wrap">
    <?php if (in_array($user['role'], ['admin','guru'])): ?>
      <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-calendar2-check-fill"></i> Input Absensi</a>
    <?php endif; ?>
    <?php if ($user['role'] === 'admin'): ?>
      <a href="<?= BASE_URL ?>/libur/index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-calendar-x-fill"></i> Kelola Libur</a>
    <?php endif; ?>
  </div>
</div>

<div class="card p-3 mb-3">
  <form method="GET" class="row g-2">
    <?php if (!$kelasSaya): ?>
    <div class="col-md-5">
      <label class="form-label small">Kelas</label>
      <select name="kelas_id" class="form-select" onchange="this.form.submit()">
        <?php foreach ($kelasList as $k): ?>
          <option value="<?= $k['id'] ?>" <?= (string)$kelas_id === (string)$k['id'] ? 'selected' : '' ?>><?= clean($k['nama_kelas']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>
    <div class="col-md-4">
      <label class="form-label small">Tanggal</label>
      <input type="date" name="tanggal" class="form-control" value="<?= clean($tanggal) ?>" onchange="this.form.submit()">
    </div>
  </form>
</div>

<?php if ($infoLibur['libur']): ?>
<div class="alert alert-info d-flex align-items-center gap-2 mb-3">
  <i class="bi bi-calendar-x-fill fs-5"></i>
  <div>
    <strong><?= formatTanggalIndo($tanggal) ?> adalah hari libur</strong> — <?= clean($infoLibur['keterangan']) ?>.
    Data di bawah tetap ditampilkan sebagai referensi, tapi status "Belum Absen" pada tanggal ini bukan pelanggaran kehadiran.
  </div>
</div>
<?php endif; ?>

<div class="row g-3 mb-3">
  <div class="col-6 col-md-3">
    <div class="card stat-card v-good p-3">
      <i class="bi bi-box-arrow-in-right"></i>
      <div class="small">Sudah Absen Masuk</div>
      <div class="fs-3 fw-bold"><?= $totalHadir ?></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card stat-card <?= $infoLibur['libur'] ? '' : 'v-bad' ?> p-3">
      <i class="bi bi-x-circle-fill"></i>
      <div class="small">Belum Absen<?= $infoLibur['libur'] ? ' (Libur)' : '' ?></div>
      <div class="fs-3 fw-bold"><?= $totalBelumAbsen ?></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card stat-card v-good p-3">
      <i class="bi bi-box-arrow-right"></i>
      <div class="small">Sudah Absen Pulang</div>
      <div class="fs-3 fw-bold"><?= $totalSudahPulang ?></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card stat-card p-3" style="border-left:4px solid #F4B740;">
      <i class="bi bi-clock-history" style="color:#D89A1F;"></i>
      <div class="small">Hadir, Belum Pulang</div>
      <div class="fs-3 fw-bold"><?= $totalBelumPulang ?></div>
    </div>
  </div>
</div>

<div class="card p-3 mb-3">
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead class="table-light">
        <tr>
          <th>#</th><th>NIS</th><th>Nama Siswa</th><th>Status</th>
          <th>Jam Masuk</th><th>Jarak Masuk</th>
          <th>Jam Pulang</th><th>Jarak Pulang</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($dataSiswa)): ?>
          <tr><td colspan="8" class="text-center text-muted py-4">Tidak ada data siswa di kelas ini.</td></tr>
        <?php endif; ?>
        <?php foreach ($dataSiswa as $i => $s): ?>
        <tr>
          <td><?= $i + 1 ?></td>
          <td><?= clean($s['nis']) ?></td>
          <td class="fw-semibold"><?= clean($s['nama_lengkap']) ?></td>
          <td><?= $s['status'] ? badgeStatusAbsen($s['status']) : "<span class='badge-soft bad'>Belum Absen</span>" ?></td>
          <td>
            <?php if ($s['jam_masuk']): ?>
              <span class="badge-soft gps"><i class="bi bi-geo-alt-fill me-1"></i><?= substr($s['jam_masuk'],0,5) ?></span>
            <?php elseif ($s['status']): ?>
              <span class="badge-soft brand">Manual</span>
            <?php else: ?>
              -
            <?php endif; ?>
          </td>
          <td><?= $s['jarak_masuk'] !== null ? round($s['jarak_masuk']) . ' m' : '-' ?></td>
          <td>
            <?php if ($s['jam_pulang']): ?>
              <span class="badge-soft gps"><i class="bi bi-geo-alt-fill me-1"></i><?= substr($s['jam_pulang'],0,5) ?></span>
            <?php elseif ($s['status'] === 'Hadir'): ?>
              <span class="badge-soft warn">Belum Pulang</span>
            <?php else: ?>
              -
            <?php endif; ?>
          </td>
          <td><?= $s['jarak_pulang_meter'] !== null ? round($s['jarak_pulang_meter']) . ' m' : '-' ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if (!empty($logDitolak)): ?>
<div class="card p-3">
  <h6 class="fw-bold small mb-3"><i class="bi bi-shield-exclamation me-1"></i>Percobaan Absen GPS yang Ditolak Hari Ini</h6>
  <div class="table-responsive">
    <table class="table table-sm">
      <thead class="table-light"><tr><th>Waktu</th><th>Jenis</th><th>Nama Siswa</th><th>Jarak</th><th>Alasan Ditolak</th></tr></thead>
      <tbody>
        <?php foreach ($logDitolak as $l): ?>
        <tr>
          <td><?= substr($l['waktu'],0,5) ?></td>
          <td><span class="badge-soft <?= $l['tipe'] === 'Pulang' ? 'brand' : 'info' ?>"><?= clean($l['tipe']) ?></span></td>
          <td><?= clean($l['nama_lengkap']) ?></td>
          <td><?= round($l['jarak_meter']) ?> m</td>
          <td class="text-danger small"><?= clean($l['alasan_ditolak']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>