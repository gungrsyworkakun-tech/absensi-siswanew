<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole(['admin','guru','wali_kelas']);
$pageTitle = 'Rekap Absensi';
$user = currentUser();

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
$bulan    = $_GET['bulan'] ?? date('Y-m');

$rekap = [];
if ($kelas_id) {
    $stmt = $pdo->prepare("
        SELECT s.id, s.nis, s.nama_lengkap,
            SUM(CASE WHEN a.status='Hadir' THEN 1 ELSE 0 END) AS hadir,
            SUM(CASE WHEN a.status='Izin' THEN 1 ELSE 0 END) AS izin,
            SUM(CASE WHEN a.status='Sakit' THEN 1 ELSE 0 END) AS sakit,
            SUM(CASE WHEN a.status='Alpa' THEN 1 ELSE 0 END) AS alpa,
            SUM(CASE WHEN a.status='Hadir' AND a.jam_pulang IS NOT NULL THEN 1 ELSE 0 END) AS pulang_tercatat
        FROM siswa s
        LEFT JOIN absensi a ON a.siswa_id = s.id AND DATE_FORMAT(a.tanggal, '%Y-%m') = ?
        WHERE s.kelas_id = ? AND s.status = 'Aktif'
        GROUP BY s.id, s.nis, s.nama_lengkap
        ORDER BY s.nama_lengkap
    ");
    $stmt->execute([$bulan, $kelas_id]);
    $rekap = $stmt->fetchAll();
}

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h4 class="fw-bold mb-0"><i class="bi bi-bar-chart-fill me-2"></i>Rekap Absensi Bulanan</h4>
  <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Input Absensi</a>
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
      <label class="form-label small">Bulan</label>
      <input type="month" name="bulan" class="form-control" value="<?= clean($bulan) ?>" onchange="this.form.submit()">
    </div>
  </form>
</div>

<div class="card p-3">
  <p class="text-muted small mb-3">Kolom <strong>Pulang Tercatat</strong> menghitung berapa hari dari total Hadir yang absen pulangnya juga tercatat (via GPS). Selisihnya berarti siswa hadir tapi tidak absen pulang.</p>
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead class="table-light">
        <tr><th>#</th><th>NIS</th><th>Nama Siswa</th><th class="text-center">Hadir</th><th class="text-center">Izin</th><th class="text-center">Sakit</th><th class="text-center">Alpa</th><th class="text-center">Pulang Tercatat</th><th class="text-center">% Kehadiran</th></tr>
      </thead>
      <tbody>
        <?php if (empty($rekap)): ?>
          <tr><td colspan="9" class="text-center text-muted py-4">Tidak ada data.</td></tr>
        <?php endif; ?>
        <?php foreach ($rekap as $i => $r):
          $total = $r['hadir'] + $r['izin'] + $r['sakit'] + $r['alpa'];
          $persen = $total > 0 ? round($r['hadir'] / $total * 100, 1) : 0;
          $belumPulang = $r['hadir'] - $r['pulang_tercatat'];
        ?>
        <tr>
          <td><?= $i + 1 ?></td>
          <td><?= clean($r['nis']) ?></td>
          <td class="fw-semibold"><?= clean($r['nama_lengkap']) ?></td>
          <td class="text-center"><?= $r['hadir'] ?></td>
          <td class="text-center"><?= $r['izin'] ?></td>
          <td class="text-center"><?= $r['sakit'] ?></td>
          <td class="text-center"><?= $r['alpa'] ?></td>
          <td class="text-center">
            <?= $r['pulang_tercatat'] ?> / <?= $r['hadir'] ?>
            <?php if ($r['hadir'] > 0 && $belumPulang > 0): ?>
              <span class="badge bg-warning text-dark ms-1" title="<?= $belumPulang ?> hari hadir tapi tidak absen pulang"><?= $belumPulang ?> blm pulang</span>
            <?php endif; ?>
          </td>
          <td class="text-center">
            <span class="badge bg-<?= $persen >= 90 ? 'success' : ($persen >= 75 ? 'warning text-dark' : 'danger') ?>"><?= $persen ?>%</span>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>