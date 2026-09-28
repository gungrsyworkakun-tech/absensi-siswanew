<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole(['admin']);
$pageTitle = 'Data Kelas';

$data = $pdo->query("
    SELECT k.*, (SELECT COUNT(*) FROM siswa s WHERE s.kelas_id = k.id AND s.status='Aktif') AS jumlah_siswa
    FROM kelas k ORDER BY k.tingkat, k.nama_kelas
")->fetchAll();

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/theme_gov.php';
?>

<div class="gv-topline">
  <div class="gv-title">
    <small>Manajemen Kelas</small>
    Data Kelas
  </div>
  <span class="gv-crumb"><a href="<?= BASE_URL ?>/index.php">Laman</a> <i class="bi bi-chevron-right small"></i> <b>Data Kelas</b></span>
</div>

<div class="d-flex justify-content-end mb-3">
  <a href="tambah.php" class="gv-btn"><i class="bi bi-plus-lg"></i> Tambah Kelas</a>
</div>

<div class="gv-table-wrap">
  <div class="table-responsive">
    <table class="table align-middle">
      <thead>
        <tr>
          <th style="width:40px;">#</th>
          <th>Nama Kelas</th>
          <th>Tingkat</th>
          <th>Jurusan</th>
          <th>Wali Kelas</th>
          <th>Tahun Ajaran</th>
          <th>Jml Siswa</th>
          <th class="text-end">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($data)): ?>
          <tr><td colspan="8" class="text-center text-muted py-4">Belum ada data kelas.</td></tr>
        <?php endif; ?>
        <?php foreach ($data as $i => $row): ?>
        <tr>
          <td class="text-muted"><?= $i + 1 ?></td>
          <td class="fw-semibold"><?= clean($row['nama_kelas']) ?></td>
          <td><?= clean($row['tingkat']) ?></td>
          <td><?= clean($row['jurusan'] ?? '-') ?></td>
          <td><?= clean($row['wali_kelas'] ?? '-') ?></td>
          <td><?= clean($row['tahun_ajaran']) ?></td>
          <td><span class="gv-pill info"><?= $row['jumlah_siswa'] ?> siswa</span></td>
          <td class="text-end">
            <a href="edit.php?id=<?= $row['id'] ?>" class="gv-icon-btn" title="Edit"><i class="bi bi-pencil-square"></i></a>
            <a href="hapus.php?id=<?= $row['id'] ?>" class="gv-icon-btn danger" title="Hapus" onclick="return confirm('Yakin hapus kelas ini? Siswa di kelas ini tidak akan terhapus.');"><i class="bi bi-trash"></i></a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>