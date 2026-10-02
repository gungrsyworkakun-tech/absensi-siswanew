<?php
// Daftar guru & wali kelas beserta biodata ringkas dan mata pelajaran yang diampu.
// Untuk admin dan kepala sekolah.
date_default_timezone_set('Asia/Makassar');

require_once __DIR__ . '/../includes/auth.php';

$user = currentUser();
$pageTitle = 'Data Guru';
requireRole(['admin', 'kepala_sekolah']);

try {
    $pdo->query("SELECT 1 FROM guru_profil LIMIT 1");
    $pdo->query("SELECT semester, tahun_ajaran FROM jadwal_mengajar LIMIT 1");
} catch (PDOException $e) {
    include __DIR__ . '/../includes/header.php';
    echo "<div class='card p-4'><h5 class='fw-bold'><i class='bi bi-exclamation-triangle text-warning me-2'></i>Fitur Data Guru belum aktif</h5>"
       . "<p class='mb-0'>Import file <b>guru_profil_jadwal.sql</b> ke database <b>db_absensi_sekolah</b> melalui phpMyAdmin, lalu refresh halaman ini.</p></div>";
    include __DIR__ . '/../includes/footer.php';
    exit;
}

$q = trim($_GET['q'] ?? '');

$sql = "
    SELECT u.id, u.nama, u.role, p.nip, p.jabatan, p.no_hp, p.status_kepegawaian, p.id AS profil_id,
           GROUP_CONCAT(DISTINCT m.nama_mapel ORDER BY m.nama_mapel SEPARATOR ', ') AS mapel,
           COUNT(DISTINCT j.id) AS jml_jadwal
    FROM users u
    LEFT JOIN guru_profil p ON p.user_id = u.id
    LEFT JOIN jadwal_mengajar j ON j.guru_id = u.id AND j.semester = ? AND j.tahun_ajaran = ?
    LEFT JOIN mata_pelajaran m ON m.id = j.mapel_id
    WHERE u.role IN ('guru', 'wali_kelas')";
$awalPeriode = (int)date('n') >= 7 ? (int)date('Y') : (int)date('Y') - 1;
$params = [(int)date('n') >= 7 ? 'Ganjil' : 'Genap', $awalPeriode . '/' . ($awalPeriode + 1)];   // periode berjalan
if ($q !== '') {
    $sql .= " AND (u.nama LIKE ? OR p.nip LIKE ?)";
    $like = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
}
$sql .= " GROUP BY u.id, u.nama, u.role, p.nip, p.jabatan, p.no_hp, p.status_kepegawaian, p.id ORDER BY u.nama";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$daftar = $stmt->fetchAll();

$labelRole = ['guru' => 'Guru', 'wali_kelas' => 'Wali Kelas'];
$jmlBelumLengkap = 0;
foreach ($daftar as $g) { if (!$g['profil_id']) { $jmlBelumLengkap++; } }

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <h4 class="fw-bold mb-0"><i class="bi bi-person-vcard-fill me-2"></i>Data Guru</h4>
  <a href="jadwal.php" class="btn btn-outline-dark btn-sm"><i class="bi bi-calendar-week-fill"></i> Jadwal Mengajar</a>
</div>

<?php if ($jmlBelumLengkap > 0): ?>
  <div class="alert alert-warning small"><i class="bi bi-exclamation-triangle-fill me-1"></i><b><?= $jmlBelumLengkap ?></b> guru belum melengkapi biodata.</div>
<?php endif; ?>

<div class="card p-3 mb-3">
  <form method="GET" class="row g-2">
    <div class="col-md-6">
      <input type="text" name="q" class="form-control" placeholder="Cari nama atau NIP..." value="<?= clean($q) ?>">
    </div>
    <div class="col-auto"><button class="btn btn-primary"><i class="bi bi-search"></i> Cari</button></div>
    <?php if ($q !== ''): ?><div class="col-auto"><a href="list.php" class="btn btn-outline-secondary">Reset</a></div><?php endif; ?>
  </form>
</div>

<div class="card p-3">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr><th>#</th><th>Nama</th><th>NIP</th><th>Jabatan</th><th>Mapel Diampu</th><th class="text-center">Jadwal (periode ini)</th><th>No. HP</th><th></th></tr>
      </thead>
      <tbody>
        <?php if (empty($daftar)): ?>
          <tr><td colspan="8" class="text-center text-muted py-4">Tidak ada data guru.</td></tr>
        <?php endif; ?>
        <?php foreach ($daftar as $i => $g): ?>
          <tr>
            <td><?= $i + 1 ?></td>
            <td>
              <div class="fw-semibold"><?= clean($g['nama']) ?></div>
              <div class="small text-muted"><?= clean($labelRole[$g['role']] ?? $g['role']) ?><?= $g['status_kepegawaian'] ? ' · ' . clean($g['status_kepegawaian']) : '' ?></div>
            </td>
            <td><?= $g['nip'] ? clean($g['nip']) : '<span class="text-muted">-</span>' ?></td>
            <td><?= $g['jabatan'] ? clean($g['jabatan']) : '<span class="text-muted">-</span>' ?></td>
            <td class="small"><?= $g['mapel'] ? clean($g['mapel']) : '<span class="text-muted">Belum ada jadwal</span>' ?></td>
            <td class="text-center"><?= (int)$g['jml_jadwal'] ?></td>
            <td class="small"><?= $g['no_hp'] ? clean($g['no_hp']) : '<span class="text-muted">-</span>' ?></td>
            <td class="text-nowrap">
              <?php if (!$g['profil_id']): ?><span class="badge bg-warning text-dark me-1">Belum diisi</span><?php endif; ?>
              <a href="profil.php?user_id=<?= (int)$g['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-person-vcard"></i> Biodata</a>
              <a href="jadwal.php?guru_id=<?= (int)$g['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-calendar-week"></i> Jadwal</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>