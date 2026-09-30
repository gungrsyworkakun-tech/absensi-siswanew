<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole(['admin', 'guru', 'wali_kelas']);
require_once __DIR__ . '/_lib.php';
$user = currentUser();
$pageTitle = 'Rapor Sekelas';

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

$kelasList = $kelasSaya ? [$kelasSaya] : $pdo->query("SELECT * FROM kelas ORDER BY tingkat, nama_kelas")->fetchAll();
$kelas_id     = $kelasSaya ? $kelasSaya['id'] : ($_GET['kelas_id'] ?? ($kelasList[0]['id'] ?? ''));
$semester     = in_array($_GET['semester'] ?? '', ['Ganjil', 'Genap'], true) ? $_GET['semester'] : raporSemesterSekarang();
$tahun_ajaran = $_GET['tahun_ajaran'] ?? raporTahunAjaranSekarang();

$daftarSiswa = [];
if ($kelas_id) {
    $stmt = $pdo->prepare("
        SELECT s.id, s.nis, s.nama_lengkap,
               COUNT(n.id) AS jml_mapel, AVG(n.nilai_akhir) AS rata_rata
        FROM siswa s
        LEFT JOIN nilai n ON n.siswa_id = s.id AND n.semester = ? AND n.tahun_ajaran = ?
        WHERE s.kelas_id = ? AND s.status = 'Aktif'
        GROUP BY s.id, s.nis, s.nama_lengkap
        ORDER BY s.nama_lengkap
    ");
    $stmt->execute([$semester, $tahun_ajaran, $kelas_id]);
    $daftarSiswa = $stmt->fetchAll();
}
$totalBelumAdaNilai = count(array_filter($daftarSiswa, fn($s) => (int)$s['jml_mapel'] === 0));

include __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="fw-bold mb-0"><i class="bi bi-folder2-open me-2"></i>Rapor Sekelas</h4>
    <?php if ($kelasSaya): ?><div class="small text-muted">Kelas <?= clean($kelasSaya['nama_kelas']) ?></div><?php endif; ?>
  </div>
  <?php if ($kelas_id && !empty($daftarSiswa)): ?>
  <a href="cetak_kelas.php?kelas_id=<?= (int)$kelas_id ?>&semester=<?= urlencode($semester) ?>&tahun_ajaran=<?= urlencode($tahun_ajaran) ?>" target="_blank" class="btn btn-primary btn-sm">
    <i class="bi bi-printer-fill"></i> Cetak / Unduh Rapor Sekelas
  </a>
  <?php endif; ?>
</div>

<div class="alert alert-light border small">
  <i class="bi bi-info-circle me-1"></i> Pilih kelas dan periode, lalu tekan <strong>Cetak / Unduh Rapor Sekelas</strong> untuk membuka rapor seluruh siswa dalam satu halaman siap cetak. Gunakan tombol Cetak di sana, lalu pilih <strong>Simpan sebagai PDF</strong> pada dialog cetak browser untuk menyimpannya sebagai file.
</div>

<div class="card p-3 mb-3">
  <form method="GET" class="row g-2">
    <?php if (!$kelasSaya): ?>
    <div class="col-12 col-md-4">
      <label class="form-label small">Kelas</label>
      <select name="kelas_id" class="form-select" onchange="this.form.submit()">
        <?php foreach ($kelasList as $k): ?>
          <option value="<?= $k['id'] ?>" <?= (string)$kelas_id === (string)$k['id'] ? 'selected' : '' ?>><?= clean($k['nama_kelas']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>
    <div class="col-6 col-md-3">
      <label class="form-label small">Semester</label>
      <select name="semester" class="form-select" onchange="this.form.submit()">
        <option value="Ganjil" <?= $semester === 'Ganjil' ? 'selected' : '' ?>>Ganjil</option>
        <option value="Genap" <?= $semester === 'Genap' ? 'selected' : '' ?>>Genap</option>
      </select>
    </div>
    <div class="col-6 col-md-5">
      <label class="form-label small">Tahun Ajaran</label>
      <select name="tahun_ajaran" class="form-select" onchange="this.form.submit()">
        <?php foreach (raporOpsiTahunAjaran() as $opt): ?>
          <option value="<?= $opt ?>" <?= $tahun_ajaran === $opt ? 'selected' : '' ?>><?= $opt ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </form>
</div>

<?php if ($kelas_id && $totalBelumAdaNilai > 0): ?>
<div class="alert alert-warning small"><i class="bi bi-exclamation-triangle-fill me-1"></i><?= $totalBelumAdaNilai ?> dari <?= count($daftarSiswa) ?> siswa belum punya nilai untuk Semester <?= clean($semester) ?> <?= clean($tahun_ajaran) ?>. Rapor mereka akan tercetak tanpa data nilai.</div>
<?php endif; ?>

<div class="card p-3">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light"><tr><th>#</th><th>NIS</th><th>Nama Siswa</th><th class="text-center">Mapel Dinilai</th><th class="text-center">Rata-rata</th><th class="text-end">Aksi</th></tr></thead>
      <tbody>
        <?php if (empty($daftarSiswa)): ?>
          <tr><td colspan="6" class="text-center text-muted py-4">Tidak ada siswa aktif di kelas ini.</td></tr>
        <?php endif; ?>
        <?php foreach ($daftarSiswa as $i => $s): ?>
        <tr>
          <td><?= $i + 1 ?></td>
          <td><?= clean($s['nis']) ?></td>
          <td class="fw-semibold"><?= clean($s['nama_lengkap']) ?></td>
          <td class="text-center"><?= (int)$s['jml_mapel'] ?></td>
          <td class="text-center"><?= $s['jml_mapel'] > 0 ? number_format($s['rata_rata'], 1) : '-' ?></td>
          <td class="text-end">
            <a href="rapor.php?siswa_id=<?= (int)$s['id'] ?>&semester=<?= urlencode($semester) ?>&tahun_ajaran=<?= urlencode($tahun_ajaran) ?>" target="_blank" class="btn btn-outline-primary btn-sm"><i class="bi bi-eye"></i> Lihat Rapor</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>