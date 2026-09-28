<?php
require_once __DIR__ . '/../includes/auth.php';
$user = currentUser();
$pageTitle = 'Materi Belajar';

$isPengajar = in_array($user['role'], ['admin','guru']);

// Kelas yang relevan untuk ditampilkan/filter
$kelasSaya = null;
if ($user['role'] === 'wali_kelas') {
    $kelasSaya = kelasWaliSaya($pdo, $user['id']);
}
$kelasList = $pdo->query("SELECT * FROM kelas ORDER BY tingkat, nama_kelas")->fetchAll();
$mapelList = $pdo->query("SELECT * FROM mata_pelajaran ORDER BY nama_mapel")->fetchAll();

// ==== Tambah materi (guru/admin) ====
if ($isPengajar && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'tambah') {
    $kelas_id  = $_POST['kelas_id'];
    $mapel_id  = $_POST['mapel_id'];
    $judul     = trim($_POST['judul']);
    $deskripsi = trim($_POST['deskripsi']);
    $tanggal   = $_POST['tanggal'] ?: date('Y-m-d');

    $fileNama = null; $fileAsli = null;
    if (!empty($_FILES['file_materi']['name'])) {
        $ext = strtolower(pathinfo($_FILES['file_materi']['name'], PATHINFO_EXTENSION));
        $allowed = ['pdf','doc','docx','ppt','pptx','xls','xlsx','jpg','jpeg','png','zip'];
        if (in_array($ext, $allowed)) {
            $fileNama = 'materi_' . time() . '_' . rand(100,999) . '.' . $ext;
            $fileAsli = $_FILES['file_materi']['name'];
            move_uploaded_file($_FILES['file_materi']['tmp_name'], __DIR__ . '/../uploads/materi/' . $fileNama);
        }
    }

    if ($judul === '') {
        setFlash('error', 'Judul materi wajib diisi.');
    } else {
        $stmt = $pdo->prepare("INSERT INTO elearning_materi (kelas_id, mapel_id, guru_id, guru_nama, judul, deskripsi, file_materi, file_nama_asli, tanggal) VALUES (?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$kelas_id, $mapel_id, $user['id'], $user['nama'], $judul, $deskripsi ?: null, $fileNama, $fileAsli, $tanggal]);
        setFlash('success', 'Materi berhasil dipublikasikan.');
    }
    redirect('elearning/materi.php');
}

// ==== Hapus materi ====
if ($isPengajar && isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    $stmt = $pdo->prepare("SELECT file_materi FROM elearning_materi WHERE id = ?");
    $stmt->execute([$id]);
    $m = $stmt->fetch();
    if ($m) {
        if (!empty($m['file_materi']) && file_exists(__DIR__ . '/../uploads/materi/' . $m['file_materi'])) {
            unlink(__DIR__ . '/../uploads/materi/' . $m['file_materi']);
        }
        $pdo->prepare("DELETE FROM elearning_materi WHERE id = ?")->execute([$id]);
        setFlash('success', 'Materi berhasil dihapus.');
    }
    redirect('elearning/materi.php');
}

// ==== Ambil daftar materi sesuai role ====
$filterKelas = $kelasSaya ? $kelasSaya['id'] : ($_GET['kelas_id'] ?? '');
$filterMapel = $_GET['mapel_id'] ?? '';

if ($user['role'] === 'siswa') {
    $stmtS = $pdo->prepare("SELECT kelas_id FROM siswa WHERE id = ?");
    $stmtS->execute([$user['siswa_id']]);
    $filterKelas = $stmtS->fetchColumn() ?: 0;
}

$sql = "SELECT mt.*, k.nama_kelas, mp.nama_mapel FROM elearning_materi mt
        JOIN kelas k ON mt.kelas_id = k.id
        JOIN mata_pelajaran mp ON mt.mapel_id = mp.id
        WHERE 1=1";
$params = [];
if ($filterKelas !== '') { $sql .= " AND mt.kelas_id = ?"; $params[] = $filterKelas; }
if ($filterMapel !== '') { $sql .= " AND mt.mapel_id = ?"; $params[] = $filterMapel; }
$sql .= " ORDER BY mt.tanggal DESC, mt.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$data = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h4 class="fw-bold mb-0"><i class="bi bi-journal-richtext me-2"></i>Materi Belajar</h4>
  <?php if ($isPengajar): ?>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambah"><i class="bi bi-plus-lg"></i> Unggah Materi</button>
  <?php endif; ?>
</div>

<div class="card p-3 mb-3">
  <form method="GET" class="row g-2">
    <?php if (!$kelasSaya && $user['role'] !== 'siswa'): ?>
    <div class="col-md-4">
      <select name="kelas_id" class="form-select" onchange="this.form.submit()">
        <option value="">Semua Kelas</option>
        <?php foreach ($kelasList as $k): ?>
          <option value="<?= $k['id'] ?>" <?= (string)$filterKelas===(string)$k['id']?'selected':'' ?>><?= clean($k['nama_kelas']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>
    <div class="col-md-4">
      <select name="mapel_id" class="form-select" onchange="this.form.submit()">
        <option value="">Semua Mapel</option>
        <?php foreach ($mapelList as $m): ?>
          <option value="<?= $m['id'] ?>" <?= (string)$filterMapel===(string)$m['id']?'selected':'' ?>><?= clean($m['nama_mapel']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </form>
</div>

<?php if (empty($data)): ?>
  <div class="card p-4 text-center text-muted">Belum ada materi yang dipublikasikan.</div>
<?php endif; ?>

<div class="row g-3">
  <?php foreach ($data as $m): ?>
  <div class="col-md-6">
    <div class="elearn-card d-flex gap-3">
      <div class="elearn-icon materi"><i class="bi bi-file-earmark-text-fill"></i></div>
      <div class="flex-grow-1">
        <div class="d-flex justify-content-between align-items-start">
          <h6 class="fw-bold mb-1"><?= clean($m['judul']) ?></h6>
          <?php if ($isPengajar): ?>
            <a href="?hapus=<?= $m['id'] ?>" class="text-danger small" onclick="return confirm('Hapus materi ini?');"><i class="bi bi-trash"></i></a>
          <?php endif; ?>
        </div>
        <div class="small text-muted mb-2"><?= clean($m['nama_mapel']) ?> &bull; <?= clean($m['nama_kelas']) ?></div>
        <?php if ($m['deskripsi']): ?><p class="small mb-2" style="white-space:pre-line;"><?= clean($m['deskripsi']) ?></p><?php endif; ?>
        <div class="d-flex justify-content-between align-items-center">
          <small class="text-muted"><?= formatTanggalIndo($m['tanggal']) ?> &bull; <?= clean($m['guru_nama'] ?: 'Guru') ?></small>
          <?php if ($m['file_materi']): ?>
            <a href="<?= BASE_URL ?>/uploads/materi/<?= clean($m['file_materi']) ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-download"></i> Unduh</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<?php if ($isPengajar): ?>
<div class="modal fade" id="modalTambah" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="aksi" value="tambah">
        <div class="modal-header"><h6 class="modal-title">Unggah Materi Belajar</h6><button class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="row">
            <div class="col-md-6 mb-2">
              <label class="form-label">Kelas</label>
              <select name="kelas_id" class="form-select" required>
                <?php foreach ($kelasList as $k): ?><option value="<?= $k['id'] ?>"><?= clean($k['nama_kelas']) ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6 mb-2">
              <label class="form-label">Mata Pelajaran</label>
              <select name="mapel_id" class="form-select" required>
                <?php foreach ($mapelList as $m): ?><option value="<?= $m['id'] ?>"><?= clean($m['nama_mapel']) ?></option><?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="mb-2"><label class="form-label">Judul Materi</label><input type="text" name="judul" class="form-control" required></div>
          <div class="mb-2"><label class="form-label">Deskripsi</label><textarea name="deskripsi" class="form-control" rows="3"></textarea></div>
          <div class="mb-2"><label class="form-label">Tanggal</label><input type="date" name="tanggal" class="form-control" value="<?= date('Y-m-d') ?>"></div>
          <div class="mb-2">
            <label class="form-label">File Materi (opsional)</label>
            <input type="file" name="file_materi" class="form-control" accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.jpg,.jpeg,.png,.zip">
          </div>
        </div>
        <div class="modal-footer"><button class="btn btn-primary"><i class="bi bi-send-fill"></i> Publikasikan</button></div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
