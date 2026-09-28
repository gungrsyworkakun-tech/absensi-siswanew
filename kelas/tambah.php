<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole(['admin']);
$pageTitle = 'Tambah Kelas';

// Akun yang bisa dijadikan wali kelas: role guru atau wali_kelas, dan belum menjadi wali kelas manapun
$calonWali = $pdo->query("
    SELECT u.id, u.nama FROM users u
    WHERE u.role IN ('guru','wali_kelas') AND u.id NOT IN (SELECT wali_kelas_user_id FROM kelas WHERE wali_kelas_user_id IS NOT NULL)
    ORDER BY u.nama
")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_kelas   = trim($_POST['nama_kelas']);
    $tingkat      = trim($_POST['tingkat']);
    $jurusan      = trim($_POST['jurusan']);
    $wali_kelas   = trim($_POST['wali_kelas']);
    $wali_kelas_user_id = $_POST['wali_kelas_user_id'] ?: null;
    // Validasi server-side: pastikan user id yang dikirim benar berperan guru/wali_kelas & belum ditugaskan
    if ($wali_kelas_user_id !== null) {
        $validId = array_column($calonWali, 'id');
        if (!in_array((int)$wali_kelas_user_id, $validId, true)) {
            $wali_kelas_user_id = null;
        }
    }
    $tahun_ajaran = trim($_POST['tahun_ajaran']);

    if ($nama_kelas === '' || $tingkat === '' || $tahun_ajaran === '') {
        setFlash('error', 'Nama kelas, tingkat, dan tahun ajaran wajib diisi.');
    } else {
        $stmt = $pdo->prepare("INSERT INTO kelas (nama_kelas, tingkat, jurusan, wali_kelas, wali_kelas_user_id, tahun_ajaran) VALUES (?,?,?,?,?,?)");
        $stmt->execute([$nama_kelas, $tingkat, $jurusan ?: null, $wali_kelas ?: null, $wali_kelas_user_id, $tahun_ajaran]);
        setFlash('success', 'Kelas berhasil ditambahkan.');
        redirect('kelas/list.php');
    }
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/theme_gov.php';
?>

<div class="gv-topline">
  <div class="gv-title">
    <small>Manajemen Kelas</small>
    Tambah Kelas
  </div>
  <span class="gv-crumb"><a href="<?= BASE_URL ?>/index.php">Laman</a> <i class="bi bi-chevron-right small"></i> <a href="list.php">Data Kelas</a> <i class="bi bi-chevron-right small"></i> <b>Tambah</b></span>
</div>

<div class="gv-card" style="max-width:640px;">
  <form method="POST">
    <div class="mb-3">
      <label class="form-label">Nama Kelas</label>
      <input type="text" name="nama_kelas" class="form-control" placeholder="contoh: X IPA 1" required>
    </div>
    <div class="row">
      <div class="col-md-6 mb-3">
        <label class="form-label">Tingkat</label>
        <select name="tingkat" class="form-select" required>
          <option value="">Pilih Tingkat</option>
          <option value="X">X</option>
          <option value="XI">XI</option>
          <option value="XII">XII</option>
        </select>
      </div>
      <div class="col-md-6 mb-3">
        <label class="form-label">Jurusan</label>
        <select name="jurusan" class="form-select">
          <option value="">- Tidak Ada -</option>
          <option value="IPA">IPA</option>
          <option value="IPS">IPS</option>
          <option value="Bahasa">Bahasa</option>
        </select>
      </div>
    </div>
    <div class="mb-3">
      <label class="form-label">Nama Wali Kelas (untuk cetak rapor)</label>
      <input type="text" name="wali_kelas" class="form-control" placeholder="contoh: Budi Santoso, S.Pd">
    </div>
    <div class="mb-3">
      <label class="form-label">Hubungkan ke Akun Login Wali Kelas</label>
      <select name="wali_kelas_user_id" class="form-select">
        <option value="">- Belum Dihubungkan -</option>
        <?php foreach ($calonWali as $c): ?>
          <option value="<?= $c['id'] ?>"><?= clean($c['nama']) ?></option>
        <?php endforeach; ?>
      </select>
      <small class="text-muted">Hanya menampilkan akun guru/wali kelas yang belum ditugaskan ke kelas lain. Buat akunnya dulu di menu Kelola Akun bila belum ada.</small>
    </div>
    <div class="mb-3">
      <label class="form-label">Tahun Ajaran</label>
      <input type="text" name="tahun_ajaran" class="form-control" placeholder="2025/2026" required>
    </div>
    <div class="d-flex gap-2">
      <button class="gv-btn"><i class="bi bi-save"></i> Simpan</button>
      <a href="list.php" class="gv-btn-outline">Batal</a>
    </div>
  </form>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>