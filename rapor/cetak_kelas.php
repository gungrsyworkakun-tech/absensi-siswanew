<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole(['admin', 'guru', 'wali_kelas']);
require_once __DIR__ . '/_lib.php';
$user = currentUser();
$pageTitle = 'Cetak Rapor Sekelas';

$kelas_id     = (int)($_GET['kelas_id'] ?? 0);
$semester     = in_array($_GET['semester'] ?? '', ['Ganjil', 'Genap'], true) ? $_GET['semester'] : raporSemesterSekarang();
$tahun_ajaran = $_GET['tahun_ajaran'] ?? raporTahunAjaranSekarang();

// Wali kelas hanya boleh mencetak kelasnya sendiri (dicek ulang di server,
// bukan cuma mengandalkan link yang ditampilkan di kelas.php)
if ($user['role'] === 'wali_kelas') {
    $kelasSaya = kelasWaliSaya($pdo, $user['id']);
    if (!$kelasSaya || (int)$kelasSaya['id'] !== $kelas_id) {
        setFlash('error', 'Anda hanya dapat mencetak rapor kelas Anda sendiri.');
        redirect('rapor/kelas.php');
    }
}

$stmt = $pdo->prepare("SELECT * FROM kelas WHERE id = ?");
$stmt->execute([$kelas_id]);
$kelas = $stmt->fetch();
if (!$kelas) {
    setFlash('error', 'Kelas tidak ditemukan.');
    redirect('rapor/kelas.php');
}

$stmt = $pdo->prepare("SELECT id FROM siswa WHERE kelas_id = ? AND status = 'Aktif' ORDER BY nama_lengkap");
$stmt->execute([$kelas_id]);
$idSiswa = $stmt->fetchAll(PDO::FETCH_COLUMN);

if (empty($idSiswa)) {
    setFlash('error', 'Tidak ada siswa aktif di kelas ini.');
    redirect('rapor/kelas.php');
}

include __DIR__ . '/../includes/header.php';
?>
<style>
@media print {
  .no-print, .topbar, .sidebar, footer, .bottom-nav { display: none !important; }
  main { padding: 0 !important; }
  .rapor-halaman { box-shadow: none !important; border: none !important; page-break-after: always; }
  .rapor-halaman:last-child { page-break-after: auto; }
}
</style>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2 no-print">
  <div>
    <h4 class="fw-bold mb-0"><i class="bi bi-printer-fill me-2"></i>Cetak Rapor Sekelas</h4>
    <div class="small text-muted">Kelas <?= clean($kelas['nama_kelas']) ?> &middot; Semester <?= clean($semester) ?> <?= clean($tahun_ajaran) ?> &middot; <?= count($idSiswa) ?> siswa</div>
  </div>
  <div class="d-flex gap-2">
    <a href="kelas.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Kembali</a>
    <button onclick="window.print()" class="btn btn-primary btn-sm"><i class="bi bi-printer"></i> Cetak Sekarang</button>
  </div>
</div>

<?php foreach ($idSiswa as $sid):
    $d = raporDataSiswa($pdo, $sid, $semester, $tahun_ajaran);
    if (!$d) continue;
    extract($d); // $siswa, $nilaiList, $rataRata, $rekapAbsen, $tugasList
?>
<div class="card p-4 mb-4 rapor-halaman">
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
        <tr><td class="text-muted">Jenis Kelamin</td><td>: <?= $siswa['jenis_kelamin'] === 'L' ? 'Laki-laki' : 'Perempuan' ?></td></tr>
      </table>
    </div>
  </div>

  <table class="table table-bordered table-sm">
    <thead class="table-light">
      <tr><th>#</th><th>Mata Pelajaran</th><th class="text-center">KKM</th><th class="text-center">Tugas</th><th class="text-center">UTS</th><th class="text-center">UAS</th><th class="text-center">Nilai Akhir</th><th class="text-center">Predikat</th></tr>
    </thead>
    <tbody>
      <?php if (empty($nilaiList)): ?>
        <tr><td colspan="8" class="text-center text-muted py-3">Belum ada nilai untuk semester/tahun ajaran ini.</td></tr>
      <?php endif; ?>
      <?php foreach ($nilaiList as $i => $n): ?>
      <tr>
        <td><?= $i + 1 ?></td>
        <td><?= clean($n['nama_mapel']) ?></td>
        <td class="text-center"><?= (int)$n['kkm'] ?></td>
        <td class="text-center"><?= number_format($n['nilai_tugas'], 1) ?></td>
        <td class="text-center"><?= number_format($n['nilai_uts'], 1) ?></td>
        <td class="text-center"><?= number_format($n['nilai_uas'], 1) ?></td>
        <td class="text-center fw-bold"><?= number_format($n['nilai_akhir'], 1) ?></td>
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

  <h6 class="fw-bold mt-4">Rekapitulasi Kehadiran (Semester Ini)</h6>
  <div class="row g-2 text-center">
    <div class="col-3"><div class="p-2 border rounded"><div class="fw-bold fs-5"><?= $rekapAbsen['Hadir'] ?></div><small class="text-muted">Hadir</small></div></div>
    <div class="col-3"><div class="p-2 border rounded"><div class="fw-bold fs-5"><?= $rekapAbsen['Izin'] ?></div><small class="text-muted">Izin</small></div></div>
    <div class="col-3"><div class="p-2 border rounded"><div class="fw-bold fs-5"><?= $rekapAbsen['Sakit'] ?></div><small class="text-muted">Sakit</small></div></div>
    <div class="col-3"><div class="p-2 border rounded"><div class="fw-bold fs-5"><?= $rekapAbsen['Alpa'] ?></div><small class="text-muted">Alpa</small></div></div>
  </div>

  <?php if (!empty($tugasList)): ?>
  <h6 class="fw-bold mt-4">Rincian Nilai Tugas E-Learning (Semester Ini)</h6>
  <table class="table table-bordered table-sm">
    <thead class="table-light"><tr><th>Mata Pelajaran</th><th>Judul Tugas</th><th>Tanggal Kumpul</th><th class="text-center">Nilai</th></tr></thead>
    <tbody>
      <?php foreach ($tugasList as $t): ?>
      <tr>
        <td><?= clean($t['nama_mapel']) ?></td>
        <td><?= clean($t['judul']) ?></td>
        <td><?= formatTanggalIndo(substr($t['tanggal_kumpul'], 0, 10)) ?></td>
        <td class="text-center fw-bold"><?= number_format($t['nilai'], 1) ?></td>
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
<?php endforeach; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>