<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole(['admin','guru']);
$pageTitle = 'Buat Pengumuman';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $judul    = trim($_POST['judul']);
    $isi      = trim($_POST['isi']);
    $kategori = $_POST['kategori'];
    $tanggal  = $_POST['tanggal'] ?: date('Y-m-d');

    if ($judul === '' || $isi === '') {
        setFlash('error', 'Judul dan isi pengumuman wajib diisi.');
    } else {
        $stmt = $pdo->prepare("INSERT INTO pengumuman (judul, isi, kategori, penulis, tanggal) VALUES (?,?,?,?,?)");
        $stmt->execute([$judul, $isi, $kategori, currentUser()['nama'], $tanggal]);
        setFlash('success', 'Pengumuman berhasil dipublikasikan.');
        redirect('pengumuman/list.php');
    }
}

include __DIR__ . '/../includes/header.php';
?>
<h4 class="fw-bold mb-3"><i class="bi bi-megaphone-fill me-2"></i>Buat Pengumuman</h4>
<div class="card p-4" style="max-width:700px;">
  <form method="POST">
    <div class="mb-3">
      <label class="form-label">Judul</label>
      <input type="text" name="judul" class="form-control" required>
    </div>
    <div class="row">
      <div class="col-md-6 mb-3">
        <label class="form-label">Kategori</label>
        <select name="kategori" class="form-select">
          <?php foreach (['Umum','Akademik','Kegiatan','Penting'] as $k): ?>
            <option value="<?= $k ?>"><?= $k ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-6 mb-3">
        <label class="form-label">Tanggal</label>
        <input type="date" name="tanggal" class="form-control" value="<?= date('Y-m-d') ?>">
      </div>
    </div>
    <div class="mb-3">
      <label class="form-label">Isi Pengumuman</label>
      <textarea name="isi" class="form-control" rows="6" required></textarea>
    </div>
    <button class="btn btn-primary"><i class="bi bi-send-fill"></i> Publikasikan</button>
    <a href="list.php" class="btn btn-outline-secondary">Batal</a>
  </form>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
