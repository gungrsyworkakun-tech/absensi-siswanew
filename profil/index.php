<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole(['siswa']);
$pageTitle = 'Profil Saya';
$user = currentUser();

// Folder penyimpanan foto siswa — disamakan dengan siswa/edit.php & siswa/tambah.php.
define('FOTO_SISWA_DIR', __DIR__ . '/../uploads/foto_siswa/');
define('FOTO_SISWA_URL', BASE_URL . '/uploads/foto_siswa/');

$stmt = $pdo->prepare("SELECT s.*, k.nama_kelas, k.tahun_ajaran FROM siswa s LEFT JOIN kelas k ON s.kelas_id = k.id WHERE s.id = ?");
$stmt->execute([$user['siswa_id']]);
$siswa = $stmt->fetch();

if (!$siswa) {
    setFlash('error', 'Data siswa tidak ditemukan. Hubungi admin sekolah.');
    redirect('index.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_lengkap   = trim($_POST['nama_lengkap'] ?? '');
    $jenis_kelamin  = $_POST['jenis_kelamin'] ?? '';
    $tempat_lahir   = trim($_POST['tempat_lahir'] ?? '');
    $tanggal_lahir  = $_POST['tanggal_lahir'] ?? '';
    $agama          = trim($_POST['agama'] ?? '');
    $alamat         = trim($_POST['alamat'] ?? '');
    $no_hp          = trim($_POST['no_hp'] ?? '');
    $email          = trim($_POST['email'] ?? '');
    $nama_ayah      = trim($_POST['nama_ayah'] ?? '');
    $nama_ibu       = trim($_POST['nama_ibu'] ?? '');
    $no_hp_ortu     = trim($_POST['no_hp_ortu'] ?? '');
    $pekerjaan_ortu = trim($_POST['pekerjaan_ortu'] ?? '');

    // ==== Validasi dasar ====
    if ($nama_lengkap === '') {
        $errors[] = 'Nama lengkap tidak boleh kosong.';
    }
    if (!in_array($jenis_kelamin, ['L', 'P'], true)) {
        $errors[] = 'Jenis kelamin tidak valid.';
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Format email tidak valid.';
    }
    if ($tanggal_lahir !== '' && !DateTime::createFromFormat('Y-m-d', $tanggal_lahir)) {
        $errors[] = 'Format tanggal lahir tidak valid.';
    }

    // ==== Upload foto (opsional) ====
    $namaFotoBaru = null;
    if (!empty($_FILES['foto']['name'])) {
        $ekstensiDiizinkan = ['jpg', 'jpeg', 'png'];
        $maxUkuran = 2 * 1024 * 1024; // 2MB

        if ($_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Gagal mengunggah foto. Coba lagi.';
        } elseif ($_FILES['foto']['size'] > $maxUkuran) {
            $errors[] = 'Ukuran foto maksimal 2MB.';
        } else {
            $ekstensi = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
            if (!in_array($ekstensi, $ekstensiDiizinkan, true)) {
                $errors[] = 'Format foto harus JPG, PNG, atau WEBP.';
            } else {
                if (!is_dir(FOTO_SISWA_DIR)) {
                    @mkdir(FOTO_SISWA_DIR, 0755, true);
                }
                $namaFotoBaru = 'siswa_' . time() . '_' . random_int(100, 999) . '.' . $ekstensi;
                $tujuan = FOTO_SISWA_DIR . $namaFotoBaru;
                if (!move_uploaded_file($_FILES['foto']['tmp_name'], $tujuan)) {
                    $errors[] = 'Gagal menyimpan file foto ke server.';
                    $namaFotoBaru = null;
                }
            }
        }
    }

    if (empty($errors)) {
        if ($namaFotoBaru) {
            // Hapus foto lama supaya tidak menumpuk file sampah
            if (!empty($siswa['foto']) && is_file(FOTO_SISWA_DIR . $siswa['foto'])) {
                @unlink(FOTO_SISWA_DIR . $siswa['foto']);
            }
            $stmt = $pdo->prepare("
                UPDATE siswa SET nama_lengkap=?, jenis_kelamin=?, tempat_lahir=?, tanggal_lahir=?, agama=?,
                    alamat=?, no_hp=?, email=?, nama_ayah=?, nama_ibu=?, no_hp_ortu=?, pekerjaan_ortu=?, foto=?
                WHERE id=?
            ");
            $stmt->execute([
                $nama_lengkap, $jenis_kelamin, $tempat_lahir ?: null, $tanggal_lahir ?: null, $agama ?: null,
                $alamat ?: null, $no_hp ?: null, $email ?: null, $nama_ayah ?: null, $nama_ibu ?: null,
                $no_hp_ortu ?: null, $pekerjaan_ortu ?: null, $namaFotoBaru, $siswa['id']
            ]);
        } else {
            $stmt = $pdo->prepare("
                UPDATE siswa SET nama_lengkap=?, jenis_kelamin=?, tempat_lahir=?, tanggal_lahir=?, agama=?,
                    alamat=?, no_hp=?, email=?, nama_ayah=?, nama_ibu=?, no_hp_ortu=?, pekerjaan_ortu=?
                WHERE id=?
            ");
            $stmt->execute([
                $nama_lengkap, $jenis_kelamin, $tempat_lahir ?: null, $tanggal_lahir ?: null, $agama ?: null,
                $alamat ?: null, $no_hp ?: null, $email ?: null, $nama_ayah ?: null, $nama_ibu ?: null,
                $no_hp_ortu ?: null, $pekerjaan_ortu ?: null, $siswa['id']
            ]);
        }

        setFlash('success', 'Biodata berhasil diperbarui.');
        redirect('profil/index.php');
    }

    // Kalau ada error, isi ulang $siswa dengan input POST supaya form tidak kosong
    $siswa = array_merge($siswa, [
        'nama_lengkap' => $nama_lengkap, 'jenis_kelamin' => $jenis_kelamin, 'tempat_lahir' => $tempat_lahir,
        'tanggal_lahir' => $tanggal_lahir, 'agama' => $agama, 'alamat' => $alamat, 'no_hp' => $no_hp,
        'email' => $email, 'nama_ayah' => $nama_ayah, 'nama_ibu' => $nama_ibu,
        'no_hp_ortu' => $no_hp_ortu, 'pekerjaan_ortu' => $pekerjaan_ortu,
    ]);
}

$fotoUrl = (!empty($siswa['foto']) && is_file(FOTO_SISWA_DIR . $siswa['foto']))
    ? FOTO_SISWA_URL . $siswa['foto']
    : null;

include __DIR__ . '/../includes/header.php';
?>

<h4 class="fw-bold mb-3"><i class="bi bi-person-vcard-fill me-2"></i>Profil Saya</h4>
<p class="text-muted small">Lihat dan lengkapi data diri Anda. Data seperti NIS, kelas, dan status siswa hanya bisa diubah oleh admin sekolah.</p>

<?php if (!empty($errors)): ?>
  <div class="alert alert-danger">
    <ul class="mb-0 ps-3">
      <?php foreach ($errors as $e): ?><li><?= clean($e) ?></li><?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<div class="row g-4">
  <div class="col-lg-4">
    <div class="card p-4 text-center">
      <div class="mx-auto mb-3" style="width:130px;height:130px;border-radius:50%;overflow:hidden;background:#EDEFF5;display:flex;align-items:center;justify-content:center;">
        <?php if ($fotoUrl): ?>
          <img src="<?= clean($fotoUrl) ?>" alt="Foto profil" style="width:100%;height:100%;object-fit:cover;">
        <?php else: ?>
          <i class="bi bi-person-fill" style="font-size:3.5rem;color:#B7BCCB;"></i>
        <?php endif; ?>
      </div>
      <div class="fw-bold fs-5"><?= clean($siswa['nama_lengkap']) ?></div>
      <div class="text-muted small mb-1">NIS: <?= clean($siswa['nis']) ?><?= $siswa['nisn'] ? ' &bull; NISN: '.clean($siswa['nisn']) : '' ?></div>
      <div class="text-muted small">
        <?= clean($siswa['nama_kelas'] ?? '-') ?><?= $siswa['tahun_ajaran'] ? ' &bull; '.clean($siswa['tahun_ajaran']) : '' ?>
      </div>
      <span class="badge bg-<?= $siswa['status'] === 'Aktif' ? 'success' : 'secondary' ?> mt-2"><?= clean($siswa['status']) ?></span>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card p-4">
      <form method="POST" enctype="multipart/form-data">
        <div class="mb-3">
          <label class="form-label">Foto Profil</label>
          <input type="file" name="foto" class="form-control" accept=".jpg,.jpeg,.png">
          <small class="text-muted">Opsional. Maksimal 2MB (JPG/PNG). Biarkan kosong jika tidak ingin mengganti foto.</small>
        </div>

        <hr class="my-3">
        <div class="fw-semibold small text-muted mb-2">Data Pribadi</div>
        <div class="row">
          <div class="col-md-8 mb-3">
            <label class="form-label">Nama Lengkap</label>
            <input type="text" name="nama_lengkap" class="form-control" value="<?= clean($siswa['nama_lengkap']) ?>" required>
          </div>
          <div class="col-md-4 mb-3">
            <label class="form-label">Jenis Kelamin</label>
            <select name="jenis_kelamin" class="form-select" required>
              <option value="L" <?= $siswa['jenis_kelamin'] === 'L' ? 'selected' : '' ?>>Laki-laki</option>
              <option value="P" <?= $siswa['jenis_kelamin'] === 'P' ? 'selected' : '' ?>>Perempuan</option>
            </select>
          </div>
        </div>
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">Tempat Lahir</label>
            <input type="text" name="tempat_lahir" class="form-control" value="<?= clean($siswa['tempat_lahir'] ?? '') ?>">
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Tanggal Lahir</label>
            <input type="date" name="tanggal_lahir" class="form-control" value="<?= clean($siswa['tanggal_lahir'] ?? '') ?>">
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Agama</label>
          <input type="text" name="agama" class="form-control" value="<?= clean($siswa['agama'] ?? '') ?>">
        </div>
        <div class="mb-3">
          <label class="form-label">Alamat</label>
          <textarea name="alamat" class="form-control" rows="2"><?= clean($siswa['alamat'] ?? '') ?></textarea>
        </div>
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">No. HP</label>
            <input type="text" name="no_hp" class="form-control" value="<?= clean($siswa['no_hp'] ?? '') ?>">
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" value="<?= clean($siswa['email'] ?? '') ?>">
          </div>
        </div>

        <hr class="my-3">
        <div class="fw-semibold small text-muted mb-2">Data Orang Tua / Wali</div>
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">Nama Ayah</label>
            <input type="text" name="nama_ayah" class="form-control" value="<?= clean($siswa['nama_ayah'] ?? '') ?>">
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Nama Ibu</label>
            <input type="text" name="nama_ibu" class="form-control" value="<?= clean($siswa['nama_ibu'] ?? '') ?>">
          </div>
        </div>
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">No. HP Orang Tua</label>
            <input type="text" name="no_hp_ortu" class="form-control" value="<?= clean($siswa['no_hp_ortu'] ?? '') ?>">
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Pekerjaan Orang Tua</label>
            <input type="text" name="pekerjaan_ortu" class="form-control" value="<?= clean($siswa['pekerjaan_ortu'] ?? '') ?>">
          </div>
        </div>

        <hr class="my-3">
        <div class="fw-semibold small text-muted mb-2">Data Administratif <span class="text-muted fw-normal">(hanya bisa diubah admin)</span></div>
        <div class="row">
          <div class="col-md-4 mb-3">
            <label class="form-label">NIS</label>
            <input type="text" class="form-control" value="<?= clean($siswa['nis']) ?>" disabled>
          </div>
          <div class="col-md-4 mb-3">
            <label class="form-label">Kelas</label>
            <input type="text" class="form-control" value="<?= clean($siswa['nama_kelas'] ?? '-') ?>" disabled>
          </div>
          <div class="col-md-4 mb-3">
            <label class="form-label">Status</label>
            <input type="text" class="form-control" value="<?= clean($siswa['status']) ?>" disabled>
          </div>
        </div>

        <button class="btn btn-primary mt-2"><i class="bi bi-save me-1"></i> Simpan Perubahan</button>
      </form>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>