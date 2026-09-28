<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole(['admin','guru']);
$pageTitle = 'Edit Siswa';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM siswa WHERE id = ?");
$stmt->execute([$id]);
$siswa = $stmt->fetch();

if (!$siswa) {
    setFlash('error', 'Data siswa tidak ditemukan.');
    redirect('siswa/list.php');
}

$kelasList = $pdo->query("SELECT * FROM kelas ORDER BY tingkat, nama_kelas")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nis            = trim($_POST['nis']);
    $nisn           = trim($_POST['nisn']);
    $nama_lengkap   = trim($_POST['nama_lengkap']);
    $jenis_kelamin  = $_POST['jenis_kelamin'];
    $tempat_lahir   = trim($_POST['tempat_lahir']);
    $tanggal_lahir  = $_POST['tanggal_lahir'] ?: null;
    $agama          = trim($_POST['agama']);
    $alamat         = trim($_POST['alamat']);
    $no_hp          = trim($_POST['no_hp']);
    $email          = trim($_POST['email']);
    $nama_ayah      = trim($_POST['nama_ayah']);
    $nama_ibu       = trim($_POST['nama_ibu']);
    $no_hp_ortu     = trim($_POST['no_hp_ortu']);
    $pekerjaan_ortu = trim($_POST['pekerjaan_ortu']);
    $kelas_id       = $_POST['kelas_id'] ?: null;
    $status         = $_POST['status'];
    $tanggal_masuk  = $_POST['tanggal_masuk'] ?: null;

    $namaFoto = $siswa['foto'];
    if (!empty($_FILES['foto']['name'])) {
        $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png'])) {
            if ($namaFoto && file_exists(__DIR__ . '/../uploads/foto_siswa/' . $namaFoto)) {
                unlink(__DIR__ . '/../uploads/foto_siswa/' . $namaFoto);
            }
            $namaFoto = 'siswa_' . time() . '_' . rand(100,999) . '.' . $ext;
            move_uploaded_file($_FILES['foto']['tmp_name'], __DIR__ . '/../uploads/foto_siswa/' . $namaFoto);
        }
    }

    $stmt = $pdo->prepare("UPDATE siswa SET
        nis=?, nisn=?, nama_lengkap=?, jenis_kelamin=?, tempat_lahir=?, tanggal_lahir=?, agama=?, alamat=?,
        no_hp=?, email=?, nama_ayah=?, nama_ibu=?, no_hp_ortu=?, pekerjaan_ortu=?, foto=?, kelas_id=?, status=?, tanggal_masuk=?
        WHERE id=?");
    $stmt->execute([
        $nis, $nisn ?: null, $nama_lengkap, $jenis_kelamin, $tempat_lahir ?: null, $tanggal_lahir,
        $agama ?: null, $alamat ?: null, $no_hp ?: null, $email ?: null,
        $nama_ayah ?: null, $nama_ibu ?: null, $no_hp_ortu ?: null, $pekerjaan_ortu ?: null,
        $namaFoto, $kelas_id, $status, $tanggal_masuk, $id
    ]);
    setFlash('success', 'Data siswa berhasil diperbarui.');
    redirect('siswa/list.php');
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/theme_gov.php';
?>

<style>
/* ====== Responsive tambahan untuk form Edit Siswa ====== */
@media (max-width: 767.98px) {

  .gv-topline {
    flex-direction: column;
    align-items: flex-start;
    gap: 6px;
  }
  .gv-crumb {
    font-size: 0.8rem;
  }

  .gv-card {
    padding: 14px;
    border-radius: 10px;
  }

  .gv-section-label {
    font-size: 0.95rem;
    margin-top: 18px;
  }

  /* Foto profil di tengah supaya rapi di layar sempit */
  .gv-card form > .gv-profile-photo {
    display: block;
    margin-left: auto;
    margin-right: auto;
  }

  /* Semua kolom form jadi full width di HP */
  form .row > [class*="col-"] {
    width: 100%;
  }

  /* Beri jarak antar field lebih lega untuk sentuhan jari */
  .form-label {
    margin-bottom: 4px;
    font-size: 0.85rem;
  }
  .form-control,
  .form-select {
    font-size: 16px; /* mencegah auto-zoom Safari saat fokus input */
    padding: 10px 12px;
  }

  /* Tombol Update & Batal ditumpuk, full width, nempel di bawah layar */
  .gv-card form > .d-flex.gap-2 {
    position: sticky;
    bottom: 0;
    background: #fff;
    padding: 10px 0 4px;
    margin: 16px -14px -14px;
    padding-left: 14px;
    padding-right: 14px;
    border-top: 1px solid #eee;
    flex-direction: column;
  }
  .gv-card form > .d-flex.gap-2 .gv-btn,
  .gv-card form > .d-flex.gap-2 .gv-btn-outline {
    width: 100%;
    text-align: center;
  }

  input[type="file"] {
    padding: 8px;
  }
}
</style>

<div class="gv-topline">
  <div class="gv-title">
    <small>Manajemen Siswa</small>
    Edit Data Siswa
  </div>
  <span class="gv-crumb"><a href="<?= BASE_URL ?>/index.php">Laman</a> <i class="bi bi-chevron-right small"></i> <a href="list.php">Data Siswa</a> <i class="bi bi-chevron-right small"></i> <b>Edit</b></span>
</div>

<div class="gv-card">
  <form method="POST" enctype="multipart/form-data">
    <?php if (!empty($siswa['foto'])): ?>
      <img src="<?= BASE_URL ?>/uploads/foto_siswa/<?= clean($siswa['foto']) ?>" class="gv-profile-photo mb-3" style="width:90px;height:90px;">
    <?php endif; ?>

    <div class="gv-section-label">Data Identitas</div>
    <div class="row">
      <div class="col-md-3 mb-3"><label class="form-label">NIS *</label><input type="text" name="nis" class="form-control" value="<?= clean($siswa['nis']) ?>" required></div>
      <div class="col-md-3 mb-3"><label class="form-label">NISN</label><input type="text" name="nisn" class="form-control" value="<?= clean($siswa['nisn']) ?>"></div>
      <div class="col-md-6 mb-3"><label class="form-label">Nama Lengkap *</label><input type="text" name="nama_lengkap" class="form-control" value="<?= clean($siswa['nama_lengkap']) ?>" required></div>
    </div>
    <div class="row">
      <div class="col-md-3 mb-3">
        <label class="form-label">Jenis Kelamin *</label>
        <select name="jenis_kelamin" class="form-select" required>
          <option value="L" <?= $siswa['jenis_kelamin']==='L'?'selected':'' ?>>Laki-laki</option>
          <option value="P" <?= $siswa['jenis_kelamin']==='P'?'selected':'' ?>>Perempuan</option>
        </select>
      </div>
      <div class="col-md-3 mb-3"><label class="form-label">Tempat Lahir</label><input type="text" name="tempat_lahir" class="form-control" value="<?= clean($siswa['tempat_lahir']) ?>"></div>
      <div class="col-md-3 mb-3"><label class="form-label">Tanggal Lahir</label><input type="date" name="tanggal_lahir" class="form-control" value="<?= clean($siswa['tanggal_lahir']) ?>"></div>
      <div class="col-md-3 mb-3">
        <label class="form-label">Agama</label>
        <select name="agama" class="form-select">
          <option value="">-</option>
          <?php foreach (['Islam','Kristen','Katolik','Hindu','Buddha','Konghucu'] as $a): ?>
            <option value="<?= $a ?>" <?= $siswa['agama']===$a?'selected':'' ?>><?= $a ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="mb-3"><label class="form-label">Alamat</label><textarea name="alamat" class="form-control" rows="2"><?= clean($siswa['alamat']) ?></textarea></div>
    <div class="row">
      <div class="col-md-6 mb-3"><label class="form-label">No. HP Siswa</label><input type="text" name="no_hp" class="form-control" value="<?= clean($siswa['no_hp']) ?>"></div>
      <div class="col-md-6 mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="<?= clean($siswa['email']) ?>"></div>
    </div>

    <div class="gv-section-label">Data Orang Tua / Wali</div>
    <div class="row">
      <div class="col-md-6 mb-3"><label class="form-label">Nama Ayah</label><input type="text" name="nama_ayah" class="form-control" value="<?= clean($siswa['nama_ayah']) ?>"></div>
      <div class="col-md-6 mb-3"><label class="form-label">Nama Ibu</label><input type="text" name="nama_ibu" class="form-control" value="<?= clean($siswa['nama_ibu']) ?>"></div>
    </div>
    <div class="row">
      <div class="col-md-6 mb-3"><label class="form-label">No. HP Orang Tua</label><input type="text" name="no_hp_ortu" class="form-control" value="<?= clean($siswa['no_hp_ortu']) ?>"></div>
      <div class="col-md-6 mb-3"><label class="form-label">Pekerjaan Orang Tua</label><input type="text" name="pekerjaan_ortu" class="form-control" value="<?= clean($siswa['pekerjaan_ortu']) ?>"></div>
    </div>

    <div class="gv-section-label">Data Akademik</div>
    <div class="row">
      <div class="col-md-4 mb-3">
        <label class="form-label">Kelas</label>
        <select name="kelas_id" class="form-select">
          <option value="">- Belum Ada Kelas -</option>
          <?php foreach ($kelasList as $k): ?>
            <option value="<?= $k['id'] ?>" <?= (string)$siswa['kelas_id']===(string)$k['id']?'selected':'' ?>><?= clean($k['nama_kelas']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4 mb-3">
        <label class="form-label">Status</label>
        <select name="status" class="form-select">
          <?php foreach (['Aktif','Pindah','Lulus','Keluar'] as $s): ?>
            <option value="<?= $s ?>" <?= $siswa['status']===$s?'selected':'' ?>><?= $s ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4 mb-3"><label class="form-label">Tanggal Masuk</label><input type="date" name="tanggal_masuk" class="form-control" value="<?= clean($siswa['tanggal_masuk']) ?>"></div>
    </div>
    <div class="mb-3">
      <label class="form-label">Ganti Foto (opsional)</label>
      <input type="file" name="foto" class="form-control" accept=".jpg,.jpeg,.png">
    </div>

    <div class="d-flex gap-2 mt-2">
      <button class="gv-btn"><i class="bi bi-save"></i> Update</button>
      <a href="list.php" class="gv-btn-outline">Batal</a>
    </div>
  </form>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>