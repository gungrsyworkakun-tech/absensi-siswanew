<?php
require_once __DIR__ . '/../includes/auth.php';
$user = currentUser();
$pageTitle = 'Pengumuman';

if (isset($_GET['hapus']) && $user['role'] === 'admin') {
    $stmt = $pdo->prepare("DELETE FROM pengumuman WHERE id = ?");
    $stmt->execute([(int)$_GET['hapus']]);
    setFlash('success', 'Pengumuman berhasil dihapus.');
    redirect('pengumuman/list.php');
}

$data = $pdo->query("SELECT * FROM pengumuman ORDER BY tanggal DESC, id DESC")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h4 class="fw-bold mb-0"><i class="bi bi-megaphone-fill me-2"></i>Pengumuman &amp; Informasi</h4>
  <?php if (in_array($user['role'], ['admin','guru'])): ?>
    <a href="tambah.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Buat Pengumuman</a>
  <?php endif; ?>
</div>

<?php if (empty($data)): ?>
  <div class="card p-4 text-center text-muted">Belum ada pengumuman.</div>
<?php endif; ?>

<div class="row g-3">
  <?php foreach ($data as $p): ?>
  <div class="col-md-6">
    <div class="card p-3 h-100">
      <div class="d-flex justify-content-between align-items-start mb-2">
        <h6 class="fw-bold mb-0"><?= clean($p['judul']) ?></h6>
        <?= kategoriBadge($p['kategori']) ?>
      </div>
      <p class="text-muted small mb-2" style="white-space: pre-line;"><?= clean($p['isi']) ?></p>
      <div class="d-flex justify-content-between align-items-center mt-auto">
        <small class="text-muted"><i class="bi bi-calendar3 me-1"></i><?= formatTanggalIndo($p['tanggal']) ?> &bull; <?= clean($p['penulis'] ?: 'Admin') ?></small>
        <?php if (in_array($user['role'], ['admin','guru'])): ?>
          <div>
            <a href="edit.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-warning"><i class="bi bi-pencil-square"></i></a>
            <?php if ($user['role'] === 'admin'): ?>
              <a href="?hapus=<?= $p['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Hapus pengumuman ini?');"><i class="bi bi-trash"></i></a>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
