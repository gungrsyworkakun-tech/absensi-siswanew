<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole(['admin']);
$pageTitle = 'Mata Pelajaran';

// Tambah
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'tambah') {
    $stmt = $pdo->prepare("INSERT INTO mata_pelajaran (kode_mapel, nama_mapel, kkm) VALUES (?,?,?)");
    $stmt->execute([trim($_POST['kode_mapel']), trim($_POST['nama_mapel']), (int)$_POST['kkm']]);
    setFlash('success', 'Mata pelajaran berhasil ditambahkan.');
    redirect('mapel/list.php');
}

// Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'edit') {
    $stmt = $pdo->prepare("UPDATE mata_pelajaran SET kode_mapel=?, nama_mapel=?, kkm=? WHERE id=?");
    $stmt->execute([trim($_POST['kode_mapel']), trim($_POST['nama_mapel']), (int)$_POST['kkm'], (int)$_POST['id']]);
    setFlash('success', 'Mata pelajaran berhasil diperbarui.');
    redirect('mapel/list.php');
}

// Hapus
if (isset($_GET['hapus'])) {
    $stmt = $pdo->prepare("DELETE FROM mata_pelajaran WHERE id = ?");
    $stmt->execute([(int)$_GET['hapus']]);
    setFlash('success', 'Mata pelajaran berhasil dihapus.');
    redirect('mapel/list.php');
}

$data = $pdo->query("SELECT * FROM mata_pelajaran ORDER BY nama_mapel")->fetchAll();
include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="fw-bold mb-0"><i class="bi bi-journal-bookmark-fill me-2"></i>Mata Pelajaran</h4>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambah"><i class="bi bi-plus-lg"></i> Tambah</button>
</div>

<div class="card p-3">
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead class="table-light">
        <tr><th>#</th><th>Kode</th><th>Nama Mapel</th><th>KKM</th><th class="text-end">Aksi</th></tr>
      </thead>
      <tbody>
        <?php if (empty($data)): ?>
          <tr><td colspan="5" class="text-center text-muted py-4">Belum ada data.</td></tr>
        <?php endif; ?>
        <?php foreach ($data as $i => $row): ?>
        <tr>
          <td><?= $i+1 ?></td>
          <td><?= clean($row['kode_mapel']) ?></td>
          <td class="fw-semibold"><?= clean($row['nama_mapel']) ?></td>
          <td><?= (int)$row['kkm'] ?></td>
          <td class="text-end">
            <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#modalEdit<?= $row['id'] ?>"><i class="bi bi-pencil-square"></i></button>
            <a href="?hapus=<?= $row['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Hapus mapel ini?');"><i class="bi bi-trash"></i></a>
          </td>
        </tr>

        <!-- Modal Edit -->
        <div class="modal fade" id="modalEdit<?= $row['id'] ?>" tabindex="-1">
          <div class="modal-dialog">
            <div class="modal-content">
              <form method="POST">
                <input type="hidden" name="aksi" value="edit">
                <input type="hidden" name="id" value="<?= $row['id'] ?>">
                <div class="modal-header"><h6 class="modal-title">Edit Mata Pelajaran</h6><button class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                  <div class="mb-2"><label class="form-label">Kode</label><input type="text" name="kode_mapel" class="form-control" value="<?= clean($row['kode_mapel']) ?>"></div>
                  <div class="mb-2"><label class="form-label">Nama Mapel</label><input type="text" name="nama_mapel" class="form-control" value="<?= clean($row['nama_mapel']) ?>" required></div>
                  <div class="mb-2"><label class="form-label">KKM</label><input type="number" name="kkm" class="form-control" value="<?= (int)$row['kkm'] ?>"></div>
                </div>
                <div class="modal-footer"><button class="btn btn-primary">Simpan</button></div>
              </form>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal Tambah -->
<div class="modal fade" id="modalTambah" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="aksi" value="tambah">
        <div class="modal-header"><h6 class="modal-title">Tambah Mata Pelajaran</h6><button class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="mb-2"><label class="form-label">Kode</label><input type="text" name="kode_mapel" class="form-control"></div>
          <div class="mb-2"><label class="form-label">Nama Mapel</label><input type="text" name="nama_mapel" class="form-control" required></div>
          <div class="mb-2"><label class="form-label">KKM</label><input type="number" name="kkm" class="form-control" value="75"></div>
        </div>
        <div class="modal-footer"><button class="btn btn-primary">Simpan</button></div>
      </form>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
