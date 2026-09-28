<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole(['admin','guru']);
$pageTitle = 'Edit Pengumuman';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM pengumuman WHERE id = ?");
$stmt->execute([$id]);
$data = $stmt->fetch();

if (!$data) {
    setFlash('error', 'Pengumuman tidak ditemukan.');
    redirect('pengumuman/list.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $judul    = trim($_POST['judul']);
    $isi      = trim($_POST['isi']);
    $kategori = $_POST['kategori'];
    $tanggal  = $_POST['tanggal'];

    $stmt = $pdo->prepare("UPDATE pengumuman SET judul=?, isi=?, kategori=?, tanggal=? WHERE id=?");
    $stmt->execute([$judul, $isi, $kategori, $tanggal, $id]);
    setFlash('success', 'Pengumuman berhasil diperbarui.');
    redirect('pengumuman/list.php');
}

include __DIR__ . '/../includes/header.php';
?>
<h4 class="fw-bold mb-3"><i class="bi bi-pencil-square me-2"></i>Edit Pengumuman</h4>
<div class="card p-4" style="max-width:700px;">
  <form method="POST">
    <div class="mb-3">
      <label class="form-label">Judul</label>
      <input type="text" name="judul" class="form-control" value="<?= clean($data['judul']) ?>" required>
    </div>
    <div class="row">
      <div class="col-md-6 mb-3">
        <label class="form-label">Kategori</label>
        <select name="kategori" class="form-select">
          <?php foreach (['Umum','Akademik','Kegiatan','Penting'] as $k): ?>
            <option value="<?= $k ?>" <?= $data['kategori']===$k?'selected':'' ?>><?= $k ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-6 mb-3">
        <label class="form-label">Tanggal</label>
        <input type="date" name="tanggal" class="form-control" value="<?= clean($data['tanggal']) ?>">
      </div>
    </div>
    <div class="mb-3">
      <label class="form-label">Isi Pengumuman</label>
      <textarea name="isi" class="form-control" rows="6" required><?= clean($data['isi']) ?></textarea>
    </div>
    <button class="btn btn-primary"><i class="bi bi-save"></i> Update</button>
    <a href="list.php" class="btn btn-outline-secondary">Batal</a>
  </form>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
