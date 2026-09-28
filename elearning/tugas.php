<?php
require_once __DIR__ . '/../includes/auth.php';
$user = currentUser();
$pageTitle = 'Tugas';

$isPengajar = in_array($user['role'], ['admin','guru']);

$kelasSaya = null;
if ($user['role'] === 'wali_kelas') {
    $kelasSaya = kelasWaliSaya($pdo, $user['id']);
}
$kelasList = $pdo->query("SELECT * FROM kelas ORDER BY tingkat, nama_kelas")->fetchAll();
$mapelList = $pdo->query("SELECT * FROM mata_pelajaran ORDER BY nama_mapel")->fetchAll();

// Perkiraan semester & tahun ajaran BERJALAN, dipakai sebagai nilai default
// form "Buat Tugas" (guru tetap bisa mengubahnya sebelum publikasi).
// Asumsi kalender akademik umum: Juli-Desember = Ganjil, Januari-Juni = Genap.
$bulanSkrg = (int)date('n');
$tahunSkrg = (int)date('Y');
if ($bulanSkrg >= 7) {
    $tahunAjaranDefault = $tahunSkrg . '/' . ($tahunSkrg + 1);
    $semesterDefault = 'Ganjil';
} else {
    $tahunAjaranDefault = ($tahunSkrg - 1) . '/' . $tahunSkrg;
    $semesterDefault = 'Genap';
}

// ==== Tambah tugas (guru/admin) ====
if ($isPengajar && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'tambah') {
    $kelas_id     = $_POST['kelas_id'];
    $mapel_id     = $_POST['mapel_id'];
    $semester     = $_POST['semester'] ?: $semesterDefault;
    $tahun_ajaran = trim($_POST['tahun_ajaran']) ?: $tahunAjaranDefault;
    $judul        = trim($_POST['judul']);
    $deskripsi    = trim($_POST['deskripsi']);
    $deadline     = $_POST['deadline'];

    $fileNama = null; $fileAsli = null;
    if (!empty($_FILES['file_lampiran']['name'])) {
        $ext = strtolower(pathinfo($_FILES['file_lampiran']['name'], PATHINFO_EXTENSION));
        $allowed = ['pdf','doc','docx','ppt','pptx','xls','xlsx','jpg','jpeg','png','zip'];
        if (in_array($ext, $allowed)) {
            $fileNama = 'tugas_' . time() . '_' . rand(100,999) . '.' . $ext;
            $fileAsli = $_FILES['file_lampiran']['name'];
            move_uploaded_file($_FILES['file_lampiran']['tmp_name'], __DIR__ . '/../uploads/tugas/' . $fileNama);
        }
    }

    if ($judul === '' || !$deadline) {
        setFlash('error', 'Judul dan deadline tugas wajib diisi.');
    } elseif (!in_array($semester, ['Ganjil','Genap'], true) || !preg_match('/^\d{4}\/\d{4}$/', $tahun_ajaran)) {
        setFlash('error', 'Semester atau format Tahun Ajaran tidak valid (contoh: 2025/2026).');
    } else {
        $stmt = $pdo->prepare("INSERT INTO elearning_tugas (kelas_id, mapel_id, semester, tahun_ajaran, guru_id, guru_nama, judul, deskripsi, file_lampiran, file_nama_asli, deadline) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$kelas_id, $mapel_id, $semester, $tahun_ajaran, $user['id'], $user['nama'], $judul, $deskripsi ?: null, $fileNama, $fileAsli, $deadline]);
        setFlash('success', 'Tugas berhasil dipublikasikan.');
    }
    redirect('elearning/tugas.php');
}

// ==== Hapus tugas ====
if ($isPengajar && isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    $stmt = $pdo->prepare("SELECT file_lampiran FROM elearning_tugas WHERE id = ?");
    $stmt->execute([$id]);
    $t = $stmt->fetch();
    if ($t) {
        if (!empty($t['file_lampiran']) && file_exists(__DIR__ . '/../uploads/tugas/' . $t['file_lampiran'])) {
            unlink(__DIR__ . '/../uploads/tugas/' . $t['file_lampiran']);
        }
        $pdo->prepare("DELETE FROM elearning_tugas WHERE id = ?")->execute([$id]);
        setFlash('success', 'Tugas berhasil dihapus.');
    }
    redirect('elearning/tugas.php');
}

$filterKelas = $kelasSaya ? $kelasSaya['id'] : ($_GET['kelas_id'] ?? '');
$filterMapel = $_GET['mapel_id'] ?? '';
$filterTahunAjaran = $_GET['tahun_ajaran'] ?? '';

if ($user['role'] === 'siswa') {
    $stmtS = $pdo->prepare("SELECT kelas_id FROM siswa WHERE id = ?");
    $stmtS->execute([$user['siswa_id']]);
    $filterKelas = $stmtS->fetchColumn() ?: 0;
}

$sql = "SELECT t.*, k.nama_kelas, mp.nama_mapel,
        (SELECT COUNT(*) FROM elearning_pengumpulan p WHERE p.tugas_id = t.id) AS jml_kumpul,
        (SELECT COUNT(*) FROM siswa s WHERE s.kelas_id = t.kelas_id AND s.status='Aktif') AS jml_siswa
        FROM elearning_tugas t
        JOIN kelas k ON t.kelas_id = k.id
        JOIN mata_pelajaran mp ON t.mapel_id = mp.id
        WHERE 1=1";
$params = [];
if ($filterKelas !== '') { $sql .= " AND t.kelas_id = ?"; $params[] = $filterKelas; }
if ($filterMapel !== '') { $sql .= " AND t.mapel_id = ?"; $params[] = $filterMapel; }
if ($filterTahunAjaran !== '') { $sql .= " AND t.tahun_ajaran = ?"; $params[] = $filterTahunAjaran; }
$sql .= " ORDER BY t.deadline DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$data = $stmt->fetchAll();

// Status pengumpulan siswa (untuk role siswa)
$statusKumpulSaya = [];
if ($user['role'] === 'siswa') {
    $stmt = $pdo->prepare("SELECT tugas_id, status, nilai FROM elearning_pengumpulan WHERE siswa_id = ?");
    $stmt->execute([$user['siswa_id']]);
    foreach ($stmt->fetchAll() as $r) { $statusKumpulSaya[$r['tugas_id']] = $r; }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h4 class="fw-bold mb-0"><i class="bi bi-card-checklist me-2"></i>Tugas</h4>
  <?php if ($isPengajar): ?>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambah"><i class="bi bi-plus-lg"></i> Buat Tugas</button>
  <?php endif; ?>
</div>

<div class="card p-3 mb-3">
  <form method="GET" class="row g-2">
    <?php if (!$kelasSaya && $user['role'] !== 'siswa'): ?>
    <div class="col-md-3">
      <select name="kelas_id" class="form-select" onchange="this.form.submit()">
        <option value="">Semua Kelas</option>
        <?php foreach ($kelasList as $k): ?>
          <option value="<?= $k['id'] ?>" <?= (string)$filterKelas===(string)$k['id']?'selected':'' ?>><?= clean($k['nama_kelas']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>
    <div class="col-md-3">
      <select name="mapel_id" class="form-select" onchange="this.form.submit()">
        <option value="">Semua Mapel</option>
        <?php foreach ($mapelList as $m): ?>
          <option value="<?= $m['id'] ?>" <?= (string)$filterMapel===(string)$m['id']?'selected':'' ?>><?= clean($m['nama_mapel']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
      <input type="text" name="tahun_ajaran" class="form-control" placeholder="Tahun Ajaran (mis. 2025/2026)" value="<?= clean($filterTahunAjaran) ?>" onchange="this.form.submit()">
    </div>
  </form>
</div>

<?php if (empty($data)): ?>
  <div class="card p-4 text-center text-muted">Belum ada tugas.</div>
<?php endif; ?>

<div class="row g-3">
  <?php foreach ($data as $t):
    $sw = sisaWaktu($t['deadline']);
    $statusSaya = $statusKumpulSaya[$t['id']] ?? null;
  ?>
  <div class="col-md-6">
    <div class="elearn-card d-flex gap-3">
      <div class="elearn-icon tugas"><i class="bi bi-card-checklist"></i></div>
      <div class="flex-grow-1">
        <div class="d-flex justify-content-between align-items-start">
          <h6 class="fw-bold mb-1"><?= clean($t['judul']) ?></h6>
          <?php if ($isPengajar): ?>
            <a href="?hapus=<?= $t['id'] ?>" class="text-danger small" onclick="return confirm('Hapus tugas ini beserta seluruh pengumpulan siswa?');"><i class="bi bi-trash"></i></a>
          <?php endif; ?>
        </div>
        <div class="small text-muted mb-2">
          <?= clean($t['nama_mapel']) ?> &bull; <?= clean($t['nama_kelas']) ?>
          &bull; <span class="badge-soft brand"><?= clean($t['semester']) ?> <?= clean($t['tahun_ajaran']) ?></span>
        </div>
        <div class="mb-2 d-flex align-items-center gap-2 flex-wrap">
          <span class="deadline-chip badge-soft <?= $sw['class'] ?>"><i class="bi bi-clock me-1"></i><?= $sw['label'] ?></span>
          <small class="text-muted">Tenggat: <?= formatWaktuIndo($t['deadline']) ?></small>
        </div>

        <?php if ($user['role'] === 'siswa'): ?>
          <?php if ($statusSaya): ?>
            <span class="badge-soft <?= $statusSaya['status']==='Sudah Dinilai' ? 'good' : 'info' ?>">
              <?= $statusSaya['status']==='Sudah Dinilai' ? 'Dinilai: ' . number_format($statusSaya['nilai'],1) : 'Sudah Dikumpulkan' ?>
            </span>
          <?php else: ?>
            <span class="badge-soft bad">Belum Dikumpulkan</span>
          <?php endif; ?>
        <?php else: ?>
          <span class="badge-soft brand"><?= $t['jml_kumpul'] ?> / <?= $t['jml_siswa'] ?> siswa mengumpulkan</span>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mt-2">
          <small class="text-muted"><?= clean($t['guru_nama'] ?: 'Guru') ?></small>
          <a href="tugas_detail.php?id=<?= $t['id'] ?>" class="btn btn-sm btn-outline-primary">
            <?= $user['role']==='siswa' ? 'Lihat / Kumpulkan' : 'Lihat Pengumpulan' ?>
          </a>
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
        <div class="modal-header"><h6 class="modal-title">Buat Tugas Baru</h6><button class="btn-close" data-bs-dismiss="modal"></button></div>
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
          <div class="row">
            <div class="col-md-6 mb-2">
              <label class="form-label">Semester</label>
              <select name="semester" class="form-select" required>
                <option value="Ganjil" <?= $semesterDefault==='Ganjil'?'selected':'' ?>>Ganjil</option>
                <option value="Genap" <?= $semesterDefault==='Genap'?'selected':'' ?>>Genap</option>
              </select>
            </div>
            <div class="col-md-6 mb-2">
              <label class="form-label">Tahun Ajaran</label>
              <input type="text" name="tahun_ajaran" class="form-control" value="<?= clean($tahunAjaranDefault) ?>" placeholder="2025/2026" pattern="\d{4}/\d{4}" required>
            </div>
          </div>
          <small class="text-muted d-block mb-2">Semester & tahun ajaran ini yang menentukan nilai tugas masuk ke rapor semester mana. Sesuaikan kalau tugas ini untuk semester lain.</small>
          <div class="mb-2"><label class="form-label">Judul Tugas</label><input type="text" name="judul" class="form-control" required></div>
          <div class="mb-2"><label class="form-label">Instruksi / Deskripsi</label><textarea name="deskripsi" class="form-control" rows="3"></textarea></div>
          <div class="mb-2"><label class="form-label">Tenggat Waktu (Deadline)</label><input type="datetime-local" name="deadline" class="form-control" required></div>
          <div class="mb-2">
            <label class="form-label">Lampiran Soal (opsional)</label>
            <input type="file" name="file_lampiran" class="form-control" accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.jpg,.jpeg,.png,.zip">
          </div>
        </div>
        <div class="modal-footer"><button class="btn btn-primary"><i class="bi bi-send-fill"></i> Publikasikan</button></div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>