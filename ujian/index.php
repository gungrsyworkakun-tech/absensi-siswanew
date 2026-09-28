<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole(['admin', 'guru', 'siswa']);
require_once __DIR__ . '/_lib.php';
$user = currentUser();
$pageTitle = 'Ujian UTS / UAS';
$now = time();

/* ==================== MODE SISWA ==================== */
if ($user['role'] === 'siswa') {
    $stmt = $pdo->prepare("SELECT kelas_id FROM siswa WHERE id = ?");
    $stmt->execute([$user['siswa_id']]);
    $kelasId = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT u.*, m.nama_mapel, p.status AS status_peserta, p.nilai_akhir, p.dinilai,
               (SELECT COUNT(*) FROM ujian_soal WHERE ujian_id = u.id) AS jml_soal
        FROM ujian u
        JOIN mata_pelajaran m ON m.id = u.mapel_id
        LEFT JOIN ujian_peserta p ON p.ujian_id = u.id AND p.siswa_id = ?
        WHERE u.kelas_id = ? AND u.status = 'Terbit'
        ORDER BY u.waktu_mulai DESC
    ");
    $stmt->execute([$user['siswa_id'], $kelasId]);
    $daftar = $stmt->fetchAll();

    include __DIR__ . '/../includes/header.php';
    ?>
    <h4 class="fw-bold mb-3"><i class="bi bi-pencil-square me-2"></i>Ujian Saya</h4>

    <?php if (empty($daftar)): ?>
      <div class="card p-4 text-center text-muted">Belum ada ujian yang dijadwalkan untuk kelas Anda.</div>
    <?php endif; ?>

    <div class="row g-3">
    <?php foreach ($daftar as $u):
        $mulai   = strtotime($u['waktu_mulai']);
        $selesai = strtotime($u['waktu_selesai']);
    ?>
      <div class="col-12 col-lg-6">
        <div class="card p-3 h-100">
          <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
            <div>
              <div class="fw-bold"><?= clean($u['judul']) ?></div>
              <div class="small text-muted"><?= clean($u['nama_mapel']) ?> &middot; <?= (int)$u['jml_soal'] ?> soal &middot; <?= (int)$u['durasi_menit'] ?> menit</div>
            </div>
            <?= ujianBadgeJenis($u['jenis']) ?>
          </div>
          <div class="small text-muted mb-3">
            Dibuka <?= ujianFormatWaktu($u['waktu_mulai']) ?> &ndash; ditutup <?= ujianFormatWaktu($u['waktu_selesai']) ?>
          </div>

          <div class="mt-auto">
          <?php if ($u['status_peserta'] === 'Selesai'): ?>
            <?php if ($u['dinilai']): ?>
              <div class="d-flex align-items-center justify-content-between">
                <span class="badge-soft good">Selesai dinilai</span>
                <span class="fs-4 fw-bold"><?= number_format($u['nilai_akhir'], 1) ?></span>
              </div>
            <?php else: ?>
              <span class="badge-soft warn">Selesai &mdash; menunggu penilaian esai oleh guru</span>
            <?php endif; ?>
          <?php elseif ($u['jml_soal'] < 1): ?>
            <span class="badge-soft">Soal belum tersedia</span>
          <?php elseif ($now < $mulai): ?>
            <span class="badge-soft info">Belum dibuka</span>
          <?php elseif ($now >= $selesai): ?>
            <span class="badge-soft bad">Ditutup, Anda tidak mengikuti ujian ini</span>
          <?php else: ?>
            <a href="kerjakan.php?id=<?= (int)$u['id'] ?>" class="btn btn-primary w-100">
              <i class="bi bi-play-fill"></i> <?= $u['status_peserta'] === 'Berlangsung' ? 'Lanjutkan Ujian' : 'Mulai Ujian' ?>
            </a>
          <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
    </div>
    <?php
    include __DIR__ . '/../includes/footer.php';
    exit;
}

/* ==================== MODE GURU / ADMIN ==================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'hapus') {
    $ujian = ujianAmbil($pdo, (int)($_POST['id'] ?? 0));
    if (ujianBolehKelola($user, $ujian)) {
        $g = $pdo->prepare("SELECT gambar FROM ujian_soal WHERE ujian_id = ? AND gambar IS NOT NULL");
        $g->execute([$ujian['id']]);
        foreach ($g->fetchAll(PDO::FETCH_COLUMN) as $namaGambar) ujianHapusGambar($namaGambar);
        $pdo->prepare("DELETE FROM ujian WHERE id = ?")->execute([$ujian['id']]);
        setFlash('success', 'Ujian beserta soal dan hasilnya sudah dihapus.');
    } else {
        setFlash('error', 'Ujian tidak ditemukan atau bukan milik Anda.');
    }
    redirect('ujian/index.php');
}

$sql = "
    SELECT u.*, k.nama_kelas, m.nama_mapel,
           (SELECT COUNT(*) FROM ujian_soal WHERE ujian_id = u.id) AS jml_soal,
           (SELECT COUNT(*) FROM ujian_peserta WHERE ujian_id = u.id AND status = 'Selesai') AS jml_selesai
    FROM ujian u
    JOIN kelas k ON k.id = u.kelas_id
    JOIN mata_pelajaran m ON m.id = u.mapel_id
";
if ($user['role'] === 'admin') {
    $stmt = $pdo->query($sql . " ORDER BY u.waktu_mulai DESC");
} else {
    $stmt = $pdo->prepare($sql . " WHERE u.guru_id = ? ORDER BY u.waktu_mulai DESC");
    $stmt->execute([$user['id']]);
}
$daftar = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h4 class="fw-bold mb-0"><i class="bi bi-pencil-square me-2"></i>Ujian UTS / UAS</h4>
  <a href="form.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Buat Ujian</a>
</div>

<div class="card p-3">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr><th>Ujian</th><th>Kelas</th><th>Jadwal</th><th class="text-center">Soal</th><th class="text-center">Selesai</th><th>Status</th><th class="text-end">Aksi</th></tr>
      </thead>
      <tbody>
        <?php if (empty($daftar)): ?>
          <tr><td colspan="7" class="text-center text-muted py-4">Belum ada ujian. Klik "Buat Ujian" untuk memulai.</td></tr>
        <?php endif; ?>
        <?php foreach ($daftar as $u): ?>
        <tr>
          <td>
            <div class="fw-semibold"><?= clean($u['judul']) ?> <?= ujianBadgeJenis($u['jenis']) ?></div>
            <div class="small text-muted"><?= clean($u['nama_mapel']) ?> &middot; <?= clean($u['semester']) ?> <?= clean($u['tahun_ajaran']) ?></div>
          </td>
          <td><?= clean($u['nama_kelas']) ?></td>
          <td class="small text-nowrap"><?= ujianFormatWaktu($u['waktu_mulai']) ?><br><span class="text-muted">s/d <?= ujianFormatWaktu($u['waktu_selesai']) ?></span></td>
          <td class="text-center"><?= (int)$u['jml_soal'] ?></td>
          <td class="text-center"><?= (int)$u['jml_selesai'] ?></td>
          <td><span class="badge-soft <?= $u['status'] === 'Terbit' ? 'good' : 'warn' ?>"><?= clean($u['status']) ?></span></td>
          <td class="text-end text-nowrap">
            <?php if (ujianBolehKelola($user, $u)): ?>
              <a href="soal.php?id=<?= (int)$u['id'] ?>" class="btn btn-outline-primary btn-sm">Soal</a>
              <a href="hasil.php?id=<?= (int)$u['id'] ?>" class="btn btn-outline-secondary btn-sm">Hasil</a>
              <a href="form.php?id=<?= (int)$u['id'] ?>" class="btn btn-outline-secondary btn-sm" title="Edit info ujian"><i class="bi bi-gear"></i></a>
              <form method="POST" class="d-inline" onsubmit="return confirm('Hapus ujian ini beserta semua soal dan hasil siswa?');">
                <input type="hidden" name="aksi" value="hapus">
                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
