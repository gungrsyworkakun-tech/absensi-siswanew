<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole(['admin']);
$pageTitle = 'Edit Kelas';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM kelas WHERE id = ?");
$stmt->execute([$id]);
$kelas = $stmt->fetch();

if (!$kelas) {
    setFlash('error', 'Data kelas tidak ditemukan.');
    redirect('kelas/list.php');
}

// Akun yang bisa dijadikan wali kelas: role guru/wali_kelas, belum jadi wali kelas lain (kecuali kelas ini sendiri)
$calonWali = $pdo->prepare("
    SELECT u.id, u.nama FROM users u
    WHERE u.role IN ('guru','wali_kelas')
      AND (u.id NOT IN (SELECT wali_kelas_user_id FROM kelas WHERE wali_kelas_user_id IS NOT NULL AND id != ?) )
    ORDER BY u.nama
");
$calonWali->execute([$id]);
$calonWali = $calonWali->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_kelas   = trim($_POST['nama_kelas']);
    $tingkat      = trim($_POST['tingkat']);
    $jurusan      = trim($_POST['jurusan']);
    $wali_kelas   = trim($_POST['wali_kelas']);
    $wali_kelas_user_id = $_POST['wali_kelas_user_id'] ?: null;
    // Validasi server-side: pastikan user id yang dikirim benar berperan guru/wali_kelas & belum ditugaskan ke kelas lain
    if ($wali_kelas_user_id !== null) {
        $validId = array_column($calonWali, 'id');
        if (!in_array((int)$wali_kelas_user_id, $validId, true)) {
            $wali_kelas_user_id = null;
        }
    }
    $tahun_ajaran = trim($_POST['tahun_ajaran']);

    $stmt = $pdo->prepare("UPDATE kelas SET nama_kelas=?, tingkat=?, jurusan=?, wali_kelas=?, wali_kelas_user_id=?, tahun_ajaran=? WHERE id=?");
    $stmt->execute([$nama_kelas, $tingkat, $jurusan ?: null, $wali_kelas ?: null, $wali_kelas_user_id, $tahun_ajaran, $id]);
    setFlash('success', 'Data kelas berhasil diperbarui.');
    redirect('kelas/list.php');
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/theme_gov.php';
?>

<div class="gv-topline">
  <div class="gv-title">
    <small>Manajemen Kelas</small>
    Edit Kelas
  </div>
  <span class="gv-crumb"><a href="<?= BASE_URL ?>/index.php">Laman</a> <i class="bi bi-chevron-right small"></i> <a href="list.php">Data Kelas</a> <i class="bi bi-chevron-right small"></i> <b>Edit</b></span>
</div>

<div class="gv-card" style="max-width:640px;">
  <form method="POST">
    <div class="mb-3">
      <label class="form-label">Nama Kelas</label>
      <input type="text" name="nama_kelas" class="form-control" value="<?= clean($kelas['nama_kelas']) ?>" required>
    </div>
    <div class="row">
      <div class="col-md-6 mb-3">
        <label class="form-label">Tingkat</label>
        <select name="tingkat" class="form-select" required>
          <?php foreach (['X','XI','XII'] as $t): ?>
            <option value="<?= $t ?>" <?= $kelas['tingkat']===$t?'selected':'' ?>><?= $t ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-6 mb-3">
        <label class="form-label">Jurusan</label>
        <select name="jurusan" class="form-select">
          <option value="">- Tidak Ada -</option>
          <?php foreach (['IPA','IPS','Bahasa'] as $j): ?>
            <option value="<?= $j ?>" <?= $kelas['jurusan']===$j?'selected':'' ?>><?= $j ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="mb-3">
      <label class="form-label">Nama Wali Kelas (untuk cetak rapor)</label>
      <input type="text" name="wali_kelas" class="form-control" value="<?= clean($kelas['wali_kelas']) ?>">
    </div>
    <div class="mb-3">
      <label class="form-label">Hubungkan ke Akun Login Wali Kelas</label>
      <select name="wali_kelas_user_id" class="form-select">
        <option value="">- Belum Dihubungkan -</option>
        <?php foreach ($calonWali as $c): ?>
          <option value="<?= $c['id'] ?>" <?= (string)$kelas['wali_kelas_user_id']===(string)$c['id']?'selected':'' ?>><?= clean($c['nama']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="mb-3">
      <label class="form-label">Tahun Ajaran</label>
      <input type="text" name="tahun_ajaran" class="form-control" value="<?= clean($kelas['tahun_ajaran']) ?>" required>
    </div>
    <div class="d-flex gap-2">
      <button class="gv-btn"><i class="bi bi-save"></i> Update</button>
      <a href="list.php" class="gv-btn-outline">Batal</a>
    </div>
  </form>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>