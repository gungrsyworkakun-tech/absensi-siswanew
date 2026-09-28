<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole(['admin','guru','wali_kelas']);
$pageTitle = 'Data Siswa';
$user = currentUser();

// Wali kelas: kunci ke kelasnya sendiri saja
$kelasSaya = null;
if ($user['role'] === 'wali_kelas') {
    $kelasSaya = kelasWaliSaya($pdo, $user['id']);
    if (!$kelasSaya) {
        include __DIR__ . '/../includes/header.php';
        include __DIR__ . '/../includes/theme_gov.php';
        echo "<div class='gv-card text-center'><i class='bi bi-exclamation-triangle text-warning fs-1 mb-2'></i><p class='mb-0'>Akun Anda belum dihubungkan ke kelas manapun. Hubungi admin sekolah.</p></div>";
        include __DIR__ . '/../includes/footer.php';
        exit;
    }
}

$filterKelas = $kelasSaya ? $kelasSaya['id'] : ($_GET['kelas_id'] ?? '');
$cari = trim($_GET['cari'] ?? '');

$sql = "SELECT s.*, k.nama_kelas FROM siswa s LEFT JOIN kelas k ON s.kelas_id = k.id WHERE 1=1";
$params = [];
if ($filterKelas !== '') { $sql .= " AND s.kelas_id = ?"; $params[] = $filterKelas; }
if ($cari !== '') { $sql .= " AND (s.nama_lengkap LIKE ? OR s.nis LIKE ?)"; $params[] = "%$cari%"; $params[] = "%$cari%"; }
$sql .= " ORDER BY s.nama_lengkap";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$data = $stmt->fetchAll();

$kelasList = $pdo->query("SELECT * FROM kelas ORDER BY tingkat, nama_kelas")->fetchAll();

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/theme_gov.php';
?>

<style>
/* ====== Responsive tambahan untuk halaman Data Siswa ====== */
@media (max-width: 767.98px) {

  .gv-topline {
    flex-direction: column;
    align-items: flex-start;
    gap: 6px;
  }
  .gv-crumb {
    font-size: 0.8rem;
  }

  /* Form filter jadi full width & rapi ditumpuk */
  .gv-card form.row {
    row-gap: 10px;
  }
  .gv-card form .col-md-5,
  .gv-card form .col-md-4,
  .gv-card form .col-md-3 {
    width: 100%;
  }
  .gv-card form .col-md-3 {
    flex-direction: row;
  }

  /* Sembunyikan tabel asli, tampilkan versi kartu */
  .gv-table-wrap .table-responsive table.gv-table-desktop {
    display: none;
  }

  .gv-card-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
  }

  .gv-siswa-card {
    background: #fff;
    border: 1px solid #e6e6e6;
    border-radius: 12px;
    padding: 12px;
    display: flex;
    gap: 12px;
    align-items: flex-start;
  }

  .gv-siswa-card .gv-avatar-sm {
    width: 52px;
    height: 52px;
    border-radius: 10px;
    object-fit: cover;
    flex-shrink: 0;
  }

  .gv-siswa-card .gv-siswa-info {
    flex: 1;
    min-width: 0;
  }

  .gv-siswa-card .gv-siswa-nama {
    font-weight: 600;
    font-size: 0.95rem;
    line-height: 1.3;
    margin-bottom: 2px;
    word-break: break-word;
  }

  .gv-siswa-card .gv-siswa-meta {
    font-size: 0.8rem;
    color: #6c757d;
    display: flex;
    flex-wrap: wrap;
    gap: 4px 10px;
    margin-bottom: 6px;
  }

  .gv-siswa-card .gv-siswa-aksi {
    display: flex;
    gap: 6px;
    margin-top: 6px;
  }

  .gv-siswa-card .gv-siswa-aksi .gv-icon-btn {
    width: 34px;
    height: 34px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    border: 1px solid #e2e2e2;
  }
}

/* Sembunyikan versi kartu di layar desktop */
@media (min-width: 768px) {
  .gv-card-list { display: none; }
}
</style>

<div class="gv-topline">
  <div class="gv-title">
    <small>Manajemen Siswa</small>
    <?= $kelasSaya ? 'Siswa Kelas ' . clean($kelasSaya['nama_kelas']) : 'Data Siswa' ?>
  </div>
  <span class="gv-crumb"><a href="<?= BASE_URL ?>/index.php">Laman</a> <i class="bi bi-chevron-right small"></i> <b>Data Siswa</b></span>
</div>

<div class="gv-card mb-3">
  <form class="row g-2 align-items-end" method="GET">
    <div class="col-md-5">
      <label class="form-label">Cari</label>
      <input type="text" name="cari" class="form-control" placeholder="Nama atau NIS..." value="<?= clean($cari) ?>">
    </div>
    <?php if (!$kelasSaya): ?>
    <div class="col-md-4">
      <label class="form-label">Kelas</label>
      <select name="kelas_id" class="form-select">
        <option value="">Semua Kelas</option>
        <?php foreach ($kelasList as $k): ?>
          <option value="<?= $k['id'] ?>" <?= (string)$filterKelas === (string)$k['id'] ? 'selected' : '' ?>><?= clean($k['nama_kelas']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>
    <div class="col-md-3 d-flex gap-2">
      <button class="gv-btn-outline flex-grow-1"><i class="bi bi-search"></i> Cari</button>
      <?php if ($user['role'] !== 'wali_kelas'): ?>
        <a href="tambah.php" class="gv-btn flex-grow-1 text-center"><i class="bi bi-plus-lg"></i> Tambah</a>
      <?php endif; ?>
    </div>
  </form>
</div>

<!-- ====== VERSI TABEL (tampil di desktop/tablet) ====== -->
<div class="gv-table-wrap">
  <div class="table-responsive">
    <table class="table align-middle gv-table-desktop">
      <thead>
        <tr>
          <th style="width:40px;">#</th><th>Foto</th><th>NIS</th><th>Nama Lengkap</th><th>L/P</th><th>Kelas</th><th>Status</th><th class="text-end">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($data)): ?>
          <tr><td colspan="8" class="text-center text-muted py-4">Tidak ada data siswa.</td></tr>
        <?php endif; ?>
        <?php foreach ($data as $i => $row): ?>
        <tr>
          <td class="text-muted"><?= $i + 1 ?></td>
          <td>
            <img src="<?= !empty($row['foto']) ? BASE_URL.'/uploads/foto_siswa/'.clean($row['foto']) : 'https://ui-avatars.com/api/?name='.urlencode($row['nama_lengkap']) ?>" class="gv-avatar-sm">
          </td>
          <td><?= clean($row['nis']) ?></td>
          <td class="fw-semibold"><?= clean($row['nama_lengkap']) ?></td>
          <td><?= clean($row['jenis_kelamin']) ?></td>
          <td><?= clean($row['nama_kelas'] ?? '-') ?></td>
          <td><span class="gv-pill <?= $row['status']==='Aktif' ? 'good' : 'neutral' ?>"><?= clean($row['status']) ?></span></td>
          <td class="text-end">
            <a href="detail.php?id=<?= $row['id'] ?>" class="gv-icon-btn" title="Detail"><i class="bi bi-eye"></i></a>
            <?php if ($user['role'] !== 'wali_kelas'): ?>
              <a href="edit.php?id=<?= $row['id'] ?>" class="gv-icon-btn" title="Edit"><i class="bi bi-pencil-square"></i></a>
              <?php if ($user['role'] === 'admin'): ?>
              <a href="hapus.php?id=<?= $row['id'] ?>" class="gv-icon-btn danger" title="Hapus" onclick="return confirm('Yakin hapus data siswa ini beserta seluruh riwayat absensi & nilainya?');"><i class="bi bi-trash"></i></a>
              <?php endif; ?>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- ====== VERSI KARTU (tampil di mobile) ====== -->
  <div class="gv-card-list">
    <?php if (empty($data)): ?>
      <div class="text-center text-muted py-4">Tidak ada data siswa.</div>
    <?php endif; ?>
    <?php foreach ($data as $i => $row): ?>
      <div class="gv-siswa-card">
        <img src="<?= !empty($row['foto']) ? BASE_URL.'/uploads/foto_siswa/'.clean($row['foto']) : 'https://ui-avatars.com/api/?name='.urlencode($row['nama_lengkap']) ?>" class="gv-avatar-sm">
        <div class="gv-siswa-info">
          <div class="gv-siswa-nama"><?= clean($row['nama_lengkap']) ?></div>
          <div class="gv-siswa-meta">
            <span>NIS: <?= clean($row['nis']) ?></span>
            <span><?= clean($row['jenis_kelamin']) ?></span>
            <span><?= clean($row['nama_kelas'] ?? '-') ?></span>
          </div>
          <span class="gv-pill <?= $row['status']==='Aktif' ? 'good' : 'neutral' ?>"><?= clean($row['status']) ?></span>
          <div class="gv-siswa-aksi">
            <a href="detail.php?id=<?= $row['id'] ?>" class="gv-icon-btn" title="Detail"><i class="bi bi-eye"></i></a>
            <?php if ($user['role'] !== 'wali_kelas'): ?>
              <a href="edit.php?id=<?= $row['id'] ?>" class="gv-icon-btn" title="Edit"><i class="bi bi-pencil-square"></i></a>
              <?php if ($user['role'] === 'admin'): ?>
              <a href="hapus.php?id=<?= $row['id'] ?>" class="gv-icon-btn danger" title="Hapus" onclick="return confirm('Yakin hapus data siswa ini beserta seluruh riwayat absensi & nilainya?');"><i class="bi bi-trash"></i></a>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>