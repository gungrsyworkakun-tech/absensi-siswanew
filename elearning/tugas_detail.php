<?php
require_once __DIR__ . '/../includes/auth.php';
$user = currentUser();
$pageTitle = 'Detail Tugas';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("
    SELECT t.*, k.nama_kelas, k.id AS kelas_id, mp.nama_mapel FROM elearning_tugas t
    JOIN kelas k ON t.kelas_id = k.id
    JOIN mata_pelajaran mp ON t.mapel_id = mp.id
    WHERE t.id = ?
");
$stmt->execute([$id]);
$tugas = $stmt->fetch();

if (!$tugas) {
    setFlash('error', 'Tugas tidak ditemukan.');
    redirect('elearning/tugas.php');
}

// Batasi akses wali kelas hanya ke kelasnya sendiri
if ($user['role'] === 'wali_kelas') {
    $kelasSaya = kelasWaliSaya($pdo, $user['id']);
    if (!$kelasSaya || (int)$kelasSaya['id'] !== (int)$tugas['kelas_id']) {
        setFlash('error', 'Anda tidak memiliki akses ke tugas kelas lain.');
        redirect('elearning/tugas.php');
    }
}

$sw = sisaWaktu($tugas['deadline']);
$sudahLewatDeadline = strtotime($tugas['deadline']) < time();

// ================= MODE SISWA =================
if ($user['role'] === 'siswa') {
    $stmt = $pdo->prepare("SELECT * FROM elearning_pengumpulan WHERE tugas_id = ? AND siswa_id = ?");
    $stmt->execute([$id, $user['siswa_id']]);
    $pengumpulanSaya = $stmt->fetch();

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'kumpul') {
        $catatan = trim($_POST['catatan']);
        $fileNama = $pengumpulanSaya['file_jawaban'] ?? null;
        $fileAsli = $pengumpulanSaya['file_nama_asli'] ?? null;

        if (!empty($_FILES['file_jawaban']['name'])) {
            $ext = strtolower(pathinfo($_FILES['file_jawaban']['name'], PATHINFO_EXTENSION));
            $allowed = ['pdf','doc','docx','ppt','pptx','xls','xlsx','jpg','jpeg','png','zip'];
            if (in_array($ext, $allowed)) {
                if ($fileNama && file_exists(__DIR__ . '/../uploads/pengumpulan/' . $fileNama)) {
                    unlink(__DIR__ . '/../uploads/pengumpulan/' . $fileNama);
                }
                $fileNama = 'kumpul_' . time() . '_' . rand(100,999) . '.' . $ext;
                $fileAsli = $_FILES['file_jawaban']['name'];
                move_uploaded_file($_FILES['file_jawaban']['tmp_name'], __DIR__ . '/../uploads/pengumpulan/' . $fileNama);
            }
        }

        if (!$fileNama && $catatan === '') {
            setFlash('error', 'Unggah file jawaban atau tulis catatan sebagai jawaban.');
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO elearning_pengumpulan (tugas_id, siswa_id, file_jawaban, file_nama_asli, catatan, status)
                VALUES (?,?,?,?,?, 'Belum Dinilai')
                ON DUPLICATE KEY UPDATE file_jawaban=VALUES(file_jawaban), file_nama_asli=VALUES(file_nama_asli),
                    catatan=VALUES(catatan), tanggal_kumpul=CURRENT_TIMESTAMP
            ");
            $stmt->execute([$id, $user['siswa_id'], $fileNama, $fileAsli, $catatan ?: null]);
            setFlash('success', 'Tugas berhasil dikumpulkan.');
            redirect('elearning/tugas_detail.php?id=' . $id);
        }
    }

    include __DIR__ . '/../includes/header.php';
    ?>
    <a href="tugas.php" class="btn btn-sm btn-outline-secondary mb-3"><i class="bi bi-arrow-left"></i> Kembali</a>

    <div class="card p-4 mb-3">
      <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
          <h5 class="fw-bold mb-1"><?= clean($tugas['judul']) ?></h5>
          <div class="text-muted small mb-2"><?= clean($tugas['nama_mapel']) ?> &bull; <?= clean($tugas['nama_kelas']) ?> &bull; oleh <?= clean($tugas['guru_nama'] ?: 'Guru') ?></div>
        </div>
        <span class="deadline-chip badge-soft <?= $sw['class'] ?>"><i class="bi bi-clock me-1"></i><?= $sw['label'] ?></span>
      </div>
      <p style="white-space:pre-line;" class="mb-2"><?= clean($tugas['deskripsi'] ?: 'Tidak ada instruksi tambahan.') ?></p>
      <div class="small text-muted mb-2">Tenggat: <strong><?= formatWaktuIndo($tugas['deadline']) ?></strong></div>
      <?php if ($tugas['file_lampiran']): ?>
        <a href="<?= BASE_URL ?>/uploads/tugas/<?= clean($tugas['file_lampiran']) ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-paperclip"></i> Lampiran Soal</a>
      <?php endif; ?>
    </div>

    <div class="card p-4">
      <h6 class="fw-bold mb-3">Pengumpulan Anda</h6>

      <?php if ($pengumpulanSaya && $pengumpulanSaya['status'] === 'Sudah Dinilai'): ?>
        <div class="alert alert-light border">
          <div class="d-flex justify-content-between align-items-center mb-1">
            <strong>Nilai: <?= number_format($pengumpulanSaya['nilai'],1) ?></strong>
            <span class="badge-soft good">Sudah Dinilai</span>
          </div>
          <?php if ($pengumpulanSaya['feedback_guru']): ?>
            <div class="small text-muted mt-1">Catatan guru: <?= nl2br(clean($pengumpulanSaya['feedback_guru'])) ?></div>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <?php if ($pengumpulanSaya): ?>
        <div class="mb-3 p-3 rounded" style="background:var(--brand-soft);">
          <div class="small text-muted mb-1">Dikumpulkan pada <?= formatWaktuIndo($pengumpulanSaya['tanggal_kumpul']) ?>
            <?php if (strtotime($pengumpulanSaya['tanggal_kumpul']) > strtotime($tugas['deadline'])): ?>
              <span class="badge-soft warn ms-1">Terlambat</span>
            <?php endif; ?>
          </div>
          <?php if ($pengumpulanSaya['file_nama_asli']): ?>
            <a href="<?= BASE_URL ?>/uploads/pengumpulan/<?= clean($pengumpulanSaya['file_jawaban']) ?>" target="_blank"><i class="bi bi-file-earmark-check-fill me-1"></i><?= clean($pengumpulanSaya['file_nama_asli']) ?></a>
          <?php endif; ?>
          <?php if ($pengumpulanSaya['catatan']): ?><p class="small mb-0 mt-2" style="white-space:pre-line;"><?= clean($pengumpulanSaya['catatan']) ?></p><?php endif; ?>
        </div>
      <?php endif; ?>

      <?php if (!$pengumpulanSaya || $pengumpulanSaya['status'] !== 'Sudah Dinilai'): ?>
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="aksi" value="kumpul">
        <div class="mb-3">
          <label class="form-label">File Jawaban</label>
          <input type="file" name="file_jawaban" class="form-control" accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.jpg,.jpeg,.png,.zip">
        </div>
        <div class="mb-3">
          <label class="form-label">Catatan (opsional)</label>
          <textarea name="catatan" class="form-control" rows="3"><?= clean($pengumpulanSaya['catatan'] ?? '') ?></textarea>
        </div>
        <button class="btn btn-primary"><i class="bi bi-upload"></i> <?= $pengumpulanSaya ? 'Perbarui Pengumpulan' : 'Kumpulkan Tugas' ?></button>
        <?php if ($sudahLewatDeadline): ?><span class="text-danger small ms-2">Tenggat sudah lewat, pengumpulan akan ditandai terlambat.</span><?php endif; ?>
      </form>
      <?php endif; ?>
    </div>
    <?php
    include __DIR__ . '/../includes/footer.php';
    exit;
}

// ================= MODE GURU/ADMIN/WALI KELAS: lihat & nilai pengumpulan =================
requireRole(['admin','guru','wali_kelas']);

if (in_array($user['role'], ['admin','guru']) && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'nilai') {
    $siswa_id = $_POST['siswa_id'];
    $nilai = (float)$_POST['nilai'];
    $feedback = trim($_POST['feedback']);

    $stmt = $pdo->prepare("UPDATE elearning_pengumpulan SET nilai = ?, feedback_guru = ?, status = 'Sudah Dinilai' WHERE tugas_id = ? AND siswa_id = ?");
    $stmt->execute([$nilai, $feedback ?: null, $id, $siswa_id]);
    setFlash('success', 'Nilai berhasil disimpan.');
    redirect('elearning/tugas_detail.php?id=' . $id);
}

$stmt = $pdo->prepare("
    SELECT s.id, s.nis, s.nama_lengkap, p.file_jawaban, p.file_nama_asli, p.catatan, p.nilai, p.feedback_guru, p.status, p.tanggal_kumpul
    FROM siswa s
    LEFT JOIN elearning_pengumpulan p ON p.siswa_id = s.id AND p.tugas_id = ?
    WHERE s.kelas_id = ? AND s.status = 'Aktif'
    ORDER BY s.nama_lengkap
");
$stmt->execute([$id, $tugas['kelas_id']]);
$daftarSiswa = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<a href="tugas.php" class="btn btn-sm btn-outline-secondary mb-3"><i class="bi bi-arrow-left"></i> Kembali</a>

<div class="card p-4 mb-3">
  <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
    <div>
      <h5 class="fw-bold mb-1"><?= clean($tugas['judul']) ?></h5>
      <div class="text-muted small"><?= clean($tugas['nama_mapel']) ?> &bull; <?= clean($tugas['nama_kelas']) ?></div>
    </div>
    <span class="deadline-chip badge-soft <?= $sw['class'] ?>"><i class="bi bi-clock me-1"></i><?= $sw['label'] ?></span>
  </div>
  <p style="white-space:pre-line;" class="mt-2 mb-0"><?= clean($tugas['deskripsi'] ?: '-') ?></p>
</div>

<div class="card p-3">
  <h6 class="fw-bold mb-3">Pengumpulan Siswa</h6>
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead class="table-light">
        <tr><th>#</th><th>Nama</th><th>Status</th><th>Waktu Kumpul</th><th>File</th><th>Nilai</th><?php if (in_array($user['role'],['admin','guru'])): ?><th class="text-end">Aksi</th><?php endif; ?></tr>
      </thead>
      <tbody>
        <?php foreach ($daftarSiswa as $i => $s):
          $terlambat = $s['tanggal_kumpul'] && strtotime($s['tanggal_kumpul']) > strtotime($tugas['deadline']);
        ?>
        <tr>
          <td><?= $i+1 ?></td>
          <td class="fw-semibold"><?= clean($s['nama_lengkap']) ?></td>
          <td>
            <?php if (!$s['tanggal_kumpul']): ?>
              <span class="badge-soft bad">Belum Kumpul</span>
            <?php elseif ($terlambat): ?>
              <span class="badge-soft warn">Terlambat</span>
            <?php elseif ($s['status'] === 'Sudah Dinilai'): ?>
              <span class="badge-soft good">Dinilai</span>
            <?php else: ?>
              <span class="badge-soft info">Terkumpul</span>
            <?php endif; ?>
          </td>
          <td class="small"><?= $s['tanggal_kumpul'] ? formatWaktuIndo($s['tanggal_kumpul']) : '-' ?></td>
          <td>
            <?php if ($s['file_nama_asli']): ?>
              <a href="<?= BASE_URL ?>/uploads/pengumpulan/<?= clean($s['file_jawaban']) ?>" target="_blank"><i class="bi bi-file-earmark-check"></i></a>
            <?php else: ?>-<?php endif; ?>
          </td>
          <td><?= $s['nilai'] !== null ? number_format($s['nilai'],1) : '-' ?></td>
          <?php if (in_array($user['role'],['admin','guru'])): ?>
          <td class="text-end">
            <?php if ($s['tanggal_kumpul']): ?>
              <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalNilai<?= $s['id'] ?>"><i class="bi bi-pencil-square"></i> Nilai</button>
            <?php endif; ?>
          </td>
          <?php endif; ?>
        </tr>

        <?php if (in_array($user['role'],['admin','guru']) && $s['tanggal_kumpul']): ?>
        <div class="modal fade" id="modalNilai<?= $s['id'] ?>" tabindex="-1">
          <div class="modal-dialog">
            <div class="modal-content">
              <form method="POST">
                <input type="hidden" name="aksi" value="nilai">
                <input type="hidden" name="siswa_id" value="<?= $s['id'] ?>">
                <div class="modal-header"><h6 class="modal-title">Nilai — <?= clean($s['nama_lengkap']) ?></h6><button class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                  <?php if ($s['catatan']): ?><p class="small text-muted" style="white-space:pre-line;"><?= clean($s['catatan']) ?></p><?php endif; ?>
                  <div class="mb-2"><label class="form-label">Nilai (0-100)</label><input type="number" step="0.1" min="0" max="100" name="nilai" class="form-control" value="<?= $s['nilai'] ?? '' ?>" required></div>
                  <div class="mb-2"><label class="form-label">Catatan/Feedback</label><textarea name="feedback" class="form-control" rows="2"><?= clean($s['feedback_guru'] ?? '') ?></textarea></div>
                </div>
                <div class="modal-footer"><button class="btn btn-primary">Simpan Nilai</button></div>
              </form>
            </div>
          </div>
        </div>
        <?php endif; ?>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
