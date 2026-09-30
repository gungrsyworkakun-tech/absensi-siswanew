<?php
require_once __DIR__ . '/../includes/auth.php';
$user = currentUser();
$pageTitle = 'Rapor Siswa';

$siswa_id = (int)($_GET['siswa_id'] ?? 0);

// Siswa hanya boleh lihat rapor sendiri
if ($user['role'] === 'siswa' && (int)$user['siswa_id'] !== $siswa_id) {
    setFlash('error', 'Anda hanya dapat melihat rapor sendiri.');
    redirect('index.php');
}

$stmt = $pdo->prepare("SELECT s.*, k.nama_kelas, k.wali_kelas FROM siswa s LEFT JOIN kelas k ON s.kelas_id = k.id WHERE s.id = ?");
$stmt->execute([$siswa_id]);
$siswa = $stmt->fetch();

if (!$siswa) {
    setFlash('error', 'Data siswa tidak ditemukan.');
    redirect('index.php');
}

$semester     = $_GET['semester'] ?? 'Ganjil';
$tahun_ajaran = $_GET['tahun_ajaran'] ?? '2025/2026';

$stmt = $pdo->prepare("
    SELECT n.*, m.nama_mapel, m.kkm FROM nilai n
    JOIN mata_pelajaran m ON n.mapel_id = m.id
    WHERE n.siswa_id = ? AND n.semester = ? AND n.tahun_ajaran = ?
    ORDER BY m.nama_mapel
");
$stmt->execute([$siswa_id, $semester, $tahun_ajaran]);
$nilaiList = $stmt->fetchAll();

$rataRata = count($nilaiList) > 0 ? round(array_sum(array_column($nilaiList, 'nilai_akhir')) / count($nilaiList), 1) : 0;

// Rekap absensi selama semester (perkiraan dari tahun ajaran)
$stmt = $pdo->prepare("SELECT status, COUNT(*) c FROM absensi WHERE siswa_id = ? GROUP BY status");
$stmt->execute([$siswa_id]);
$rekapAbsen = ['Hadir'=>0,'Izin'=>0,'Sakit'=>0,'Alpa'=>0];
foreach ($stmt->fetchAll() as $r) { $rekapAbsen[$r['status']] = $r['c']; }

// ==== Rincian tugas E-Learning untuk semester/tahun ajaran ini ====
$stmt = $pdo->prepare("
    SELECT m.nama_mapel, et.judul, et.deadline, ep.nilai, ep.tanggal_kumpul
    FROM elearning_pengumpulan ep
    JOIN elearning_tugas et ON ep.tugas_id = et.id
    JOIN mata_pelajaran m ON et.mapel_id = m.id
    WHERE ep.siswa_id = ? AND ep.nilai IS NOT NULL AND et.semester = ? AND et.tahun_ajaran = ?
    ORDER BY m.nama_mapel, et.deadline
");
$stmt->execute([$siswa_id, $semester, $tahun_ajaran]);
$tugasSemesterIni = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2 no-print">
  <h4 class="fw-bold mb-0"><i class="bi bi-file-earmark-text-fill me-2"></i>Rapor Siswa</h4>
  <div class="d-flex gap-2">
    <form method="GET" class="d-flex gap-2">
      <input type="hidden" name="siswa_id" value="<?= $siswa_id ?>">
      <select name="semester" class="form-select form-select-sm" onchange="this.form.submit()">
        <option value="Ganjil" <?= $semester==='Ganjil'?'selected':'' ?>>Ganjil</option>
        <option value="Genap" <?= $semester==='Genap'?'selected':'' ?>>Genap</option>
      </select>
      <input type="text" name="tahun_ajaran" class="form-control form-control-sm" value="<?= clean($tahun_ajaran) ?>" style="width:120px;">
      <button class="btn btn-sm btn-outline-primary">Terapkan</button>
    </form>
    <button onclick="window.print()" class="btn btn-sm btn-primary"><i class="bi bi-printer"></i> Cetak</button>
  </div>
</div>

<div class="card p-4" id="areaRapor">
  <div class="text-center mb-4">
    <h5 class="fw-bold mb-0">LAPORAN HASIL BELAJAR SISWA</h5>
    <small class="text-muted">Semester <?= clean($semester) ?> - Tahun Ajaran <?= clean($tahun_ajaran) ?></small>
  </div>

  <div class="row mb-3">
    <div class="col-md-6">
      <table class="table table-borderless table-sm mb-0">
        <tr><td class="text-muted" style="width:140px;">Nama Siswa</td><td>: <strong><?= clean($siswa['nama_lengkap']) ?></strong></td></tr>
        <tr><td class="text-muted">NIS / NISN</td><td>: <?= clean($siswa['nis']) ?> / <?= clean($siswa['nisn'] ?: '-') ?></td></tr>
        <tr><td class="text-muted">Kelas</td><td>: <?= clean($siswa['nama_kelas'] ?? '-') ?></td></tr>
      </table>
    </div>
    <div class="col-md-6">
      <table class="table table-borderless table-sm mb-0">
        <tr><td class="text-muted" style="width:140px;">Wali Kelas</td><td>: <?= clean($siswa['wali_kelas'] ?? '-') ?></td></tr>
        <tr><td class="text-muted">Jenis Kelamin</td><td>: <?= $siswa['jenis_kelamin']==='L'?'Laki-laki':'Perempuan' ?></td></tr>
      </table>
    </div>
  </div>

  <table class="table table-bordered table-sm">
    <thead class="table-light">
      <tr><th>#</th><th>Mata Pelajaran</th><th class="text-center">KKM</th><th class="text-center">Tugas</th><th class="text-center">UTS</th><th class="text-center">UAS</th><th class="text-center">Nilai Akhir</th><th class="text-center">Predikat</th></tr>
    </thead>
    <tbody>
      <?php if (empty($nilaiList)): ?>
        <tr><td colspan="8" class="text-center text-muted py-4">Belum ada nilai untuk semester/tahun ajaran ini.</td></tr>
      <?php endif; ?>
      <?php foreach ($nilaiList as $i => $n): ?>
      <tr>
        <td><?= $i + 1 ?></td>
        <td><?= clean($n['nama_mapel']) ?></td>
        <td class="text-center"><?= (int)$n['kkm'] ?></td>
        <td class="text-center"><?= number_format($n['nilai_tugas'],1) ?></td>
        <td class="text-center"><?= number_format($n['nilai_uts'],1) ?></td>
        <td class="text-center"><?= number_format($n['nilai_uas'],1) ?></td>
        <td class="text-center fw-bold"><?= number_format($n['nilai_akhir'],1) ?></td>
        <td class="text-center"><?= clean($n['predikat']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
    <?php if (!empty($nilaiList)): ?>
    <tfoot>
      <tr class="table-light fw-bold"><td colspan="6" class="text-end">Rata-rata</td><td class="text-center"><?= $rataRata ?></td><td class="text-center"><?= hitungPredikat($rataRata) ?></td></tr>
    </tfoot>
    <?php endif; ?>
  </table>

  <h6 class="fw-bold mt-4">Rekapitulasi Kehadiran</h6>
  <div class="row g-2 text-center">
    <div class="col-3"><div class="p-2 border rounded"><div class="fw-bold fs-5"><?= $rekapAbsen['Hadir'] ?></div><small class="text-muted">Hadir</small></div></div>
    <div class="col-3"><div class="p-2 border rounded"><div class="fw-bold fs-5"><?= $rekapAbsen['Izin'] ?></div><small class="text-muted">Izin</small></div></div>
    <div class="col-3"><div class="p-2 border rounded"><div class="fw-bold fs-5"><?= $rekapAbsen['Sakit'] ?></div><small class="text-muted">Sakit</small></div></div>
    <div class="col-3"><div class="p-2 border rounded"><div class="fw-bold fs-5"><?= $rekapAbsen['Alpa'] ?></div><small class="text-muted">Alpa</small></div></div>
  </div>

  <?php if (!empty($tugasSemesterIni)): ?>
  <h6 class="fw-bold mt-4">Rincian Nilai Tugas E-Learning (Semester Ini)</h6>
  <table class="table table-bordered table-sm">
    <thead class="table-light">
      <tr><th>Mata Pelajaran</th><th>Judul Tugas</th><th>Tanggal Kumpul</th><th class="text-center">Nilai</th></tr>
    </thead>
    <tbody>
      <?php foreach ($tugasSemesterIni as $t): ?>
      <tr>
        <td><?= clean($t['nama_mapel']) ?></td>
        <td><?= clean($t['judul']) ?></td>
        <td><?= formatTanggalIndo(substr($t['tanggal_kumpul'],0,10)) ?></td>
        <td class="text-center fw-bold"><?= number_format($t['nilai'],1) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>

  <div class="row mt-5 text-center">
    <div class="col-4"></div>
    <div class="col-4">
      <p class="mb-5">Mengetahui,<br>Wali Kelas</p>
      <p class="fw-bold mb-0">(<?= clean($siswa['wali_kelas'] ?? '.......................') ?>)</p>
    </div>
    <div class="col-4"></div>
  </div>
</div>

<style>
@media print {
  .no-print, .topbar, .sidebar, footer { display: none !important; }
  #areaRapor { box-shadow: none !important; border: none !important; }
  main { padding: 0 !important; }
}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>