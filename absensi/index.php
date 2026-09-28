<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/libur.php';
$user = currentUser();
$pageTitle = 'Absensi';

// ==== Mode Siswa: lihat riwayat absensi sendiri ====
if ($user['role'] === 'siswa') {
    $stmt = $pdo->prepare("SELECT * FROM absensi WHERE siswa_id = ? ORDER BY tanggal DESC LIMIT 30");
    $stmt->execute([$user['siswa_id']]);
    $riwayat = $stmt->fetchAll();

    include __DIR__ . '/../includes/header.php';
    ?>
    <h4 class="fw-bold mb-3"><i class="bi bi-calendar2-check-fill me-2"></i>Riwayat Absensi Saya</h4>
    <div class="card p-3">
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead class="table-light"><tr><th>Tanggal</th><th>Status</th><th>Jam Masuk / Pulang</th><th>Keterangan</th></tr></thead>
          <tbody>
            <?php if (empty($riwayat)): ?><tr><td colspan="4" class="text-center text-muted py-4">Belum ada riwayat absensi.</td></tr><?php endif; ?>
            <?php foreach ($riwayat as $r): ?>
            <tr>
              <td class="text-nowrap"><?= formatTanggalIndo($r['tanggal']) ?></td>
              <td><?= badgeStatusAbsen($r['status']) ?></td>
              <td class="small text-muted text-nowrap">
                <?= $r['jam_pulang'] ? 'Pulang: ' . substr($r['jam_pulang'],0,5) : ($r['status']==='Hadir' ? 'Belum absen pulang' : '-') ?>
              </td>
              <td><?= clean($r['keterangan'] ?: '-') ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php
    include __DIR__ . '/../includes/footer.php';
    exit;
}

// ==== Mode Admin/Guru: input absensi per kelas & tanggal ====
requireRole(['admin','guru']);

$kelasList = $pdo->query("SELECT * FROM kelas ORDER BY tingkat, nama_kelas")->fetchAll();
$kelas_id = $_GET['kelas_id'] ?? ($kelasList[0]['id'] ?? '');
$tanggal  = $_GET['tanggal'] ?? date('Y-m-d');

// Simpan absensi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kelas_id_post = $_POST['kelas_id'];
    $tanggal_post  = $_POST['tanggal'];
    $statusArr     = $_POST['status'] ?? [];
    $ketArr        = $_POST['keterangan'] ?? [];

    // Siswa yang absennya terkunci (tercatat via Sistem GPS) tidak boleh ditimpa
    // lewat form ini KECUALI oleh admin. Dicek ulang di server (bukan cuma
    // mengandalkan atribut disabled di HTML) supaya tidak bisa diakali.
    $idTerkunci = [];
    if ($user['role'] !== 'admin') {
        $stmtKunci = $pdo->prepare("SELECT siswa_id FROM absensi WHERE kelas_id = ? AND tanggal = ? AND input_oleh = 'Sistem GPS'");
        $stmtKunci->execute([$kelas_id_post, $tanggal_post]);
        $idTerkunci = $stmtKunci->fetchAll(PDO::FETCH_COLUMN);
    }

    $stmt = $pdo->prepare("
        INSERT INTO absensi (siswa_id, kelas_id, tanggal, status, keterangan, input_oleh)
        VALUES (?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE status = VALUES(status), keterangan = VALUES(keterangan), input_oleh = VALUES(input_oleh)
    ");
    $jumlahDilewati = 0;
    foreach ($statusArr as $siswa_id => $status) {
        if (in_array($siswa_id, $idTerkunci)) { $jumlahDilewati++; continue; }
        $stmt->execute([$siswa_id, $kelas_id_post, $tanggal_post, $status, $ketArr[$siswa_id] ?? null, $user['nama']]);
    }

    if ($jumlahDilewati > 0) {
        setFlash('success', "Absensi disimpan. {$jumlahDilewati} siswa dilewati karena sudah absen GPS dan terkunci.");
    } else {
        setFlash('success', 'Absensi berhasil disimpan.');
    }
    redirect("absensi/index.php?kelas_id={$kelas_id_post}&tanggal={$tanggal_post}");
}

// Ambil siswa di kelas terpilih + status absensi (jika sudah ada) + info GPS masuk/pulang
$siswaKelas = [];
if ($kelas_id) {
    $stmt = $pdo->prepare("
        SELECT s.id, s.nis, s.nama_lengkap,
               a.status AS status_absen, a.keterangan, a.jam_pulang, a.input_oleh,
               gm.waktu AS jam_masuk_gps
        FROM siswa s
        LEFT JOIN absensi a ON a.siswa_id = s.id AND a.tanggal = ?
        LEFT JOIN absensi_gps gm ON gm.siswa_id = s.id AND gm.tanggal = ? AND gm.tipe = 'Masuk' AND gm.status = 'Berhasil'
        WHERE s.kelas_id = ? AND s.status = 'Aktif'
        ORDER BY s.nama_lengkap
    ");
    $stmt->execute([$tanggal, $tanggal, $kelas_id]);
    $siswaKelas = $stmt->fetchAll();
}

$jumlahTerkunci = count(array_filter($siswaKelas, fn($s) => $s['input_oleh'] === 'Sistem GPS'));
$infoLibur = cekHariLibur($pdo, $tanggal);

include __DIR__ . '/../includes/header.php';
?>

<style>
/* Layout responsif: grid Bootstrap yang bertumpuk di layar kecil,
   sejajar seperti tabel di layar md ke atas. Menghindari tabel lebar
   yang harus di-scroll horizontal di HP. */
.siswa-row { padding: .85rem 0; border-bottom: 1px solid #eef0f4; }
.siswa-row:last-child { border-bottom: none; }
.siswa-row.terkunci { background: #f8f9fb; border-radius: 8px; padding-left: .5rem; padding-right: .5rem; }
.mini-label { font-size: .72rem; text-transform: uppercase; letter-spacing: .04em; color: #9aa1b1; font-weight: 600; }

/* Tombol status pakai btn-check supaya besar & enak disentuh di HP */
.status-toggle-wrap { display: flex; flex-wrap: wrap; gap: .35rem; }
.status-toggle-wrap .btn { padding: .3rem .65rem; font-size: .8rem; }
.btn-check:disabled + label.btn { opacity: .55; cursor: not-allowed; }

@media (max-width: 767.98px) {
  .siswa-row .row > div { margin-bottom: .5rem; }
  .siswa-row .row > div:last-child { margin-bottom: 0; }
}
</style>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h4 class="fw-bold mb-0"><i class="bi bi-calendar2-check-fill me-2"></i>Input Absensi Harian</h4>
  <div class="d-flex gap-2 flex-wrap">
    <a href="mapel.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-journal-check"></i> <span class="d-none d-sm-inline">Absensi</span> Per Mapel</a>
    <a href="monitor.php?kelas_id=<?= $kelas_id ?>&tanggal=<?= $tanggal ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-geo-alt-fill"></i> Monitor GPS</a>
    <a href="rekap.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-bar-chart-fill"></i> Rekap</a>
    <?php if ($user['role'] === 'admin'): ?>
      <a href="<?= BASE_URL ?>/libur/index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-calendar-x-fill"></i> Kelola Libur</a>
    <?php endif; ?>
  </div>
</div>

<div class="card p-3 mb-3">
  <form method="GET" class="row g-2">
    <div class="col-12 col-md-6">
      <label class="form-label small">Kelas</label>
      <select name="kelas_id" class="form-select" onchange="this.form.submit()">
        <?php foreach ($kelasList as $k): ?>
          <option value="<?= $k['id'] ?>" <?= (string)$kelas_id === (string)$k['id'] ? 'selected' : '' ?>><?= clean($k['nama_kelas']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-12 col-md-6">
      <label class="form-label small">Tanggal</label>
      <input type="date" name="tanggal" class="form-control" value="<?= clean($tanggal) ?>" onchange="this.form.submit()">
    </div>
  </form>
</div>

<?php if ($kelas_id): ?>

<?php if ($infoLibur['libur']): ?>
<div class="alert alert-info small d-flex align-items-center gap-2">
  <i class="bi bi-calendar-x-fill fs-5"></i>
  <div><strong><?= formatTanggalIndo($tanggal) ?> adalah hari libur</strong> (<?= clean($infoLibur['keterangan']) ?>). Biasanya tidak perlu diisi absensi. Kalau memang ada kegiatan/kelas pengganti hari ini, silakan lanjutkan mengisi seperti biasa.</div>
</div>
<?php endif; ?>

<?php if ($user['role'] !== 'admin' && $jumlahTerkunci > 0): ?>
<div class="alert alert-warning small d-flex align-items-center gap-2">
  <i class="bi bi-lock-fill fs-5"></i>
  <div><?= $jumlahTerkunci ?> siswa sudah absen mandiri via GPS dan statusnya terkunci — tidak bisa diubah dari sini. Kalau ada yang perlu dikoreksi, hubungi admin.</div>
</div>
<?php endif; ?>

<div class="card p-3">
  <form method="POST">
    <input type="hidden" name="kelas_id" value="<?= clean($kelas_id) ?>">
    <input type="hidden" name="tanggal" value="<?= clean($tanggal) ?>">

    <div class="mb-3 d-flex gap-2">
      <button type="button" class="btn btn-sm btn-outline-success" onclick="setAllStatus('Hadir')"><i class="bi bi-check2-all"></i> Tandai Semua Hadir</button>
    </div>

    <!-- Header kolom, hanya tampil di layar md ke atas -->
    <div class="row g-2 d-none d-md-flex mb-1 px-1">
      <div class="col-md-4 mini-label">Siswa</div>
      <div class="col-md-5 mini-label">Status Kehadiran</div>
      <div class="col-md-3 mini-label">Keterangan</div>
    </div>

    <?php if (empty($siswaKelas)): ?>
      <div class="text-center text-muted py-4">Tidak ada siswa aktif di kelas ini.</div>
    <?php endif; ?>

    <?php foreach ($siswaKelas as $i => $s):
        $terkunci = $s['input_oleh'] === 'Sistem GPS';
        $kuncikanUntukGuru = $terkunci && $user['role'] !== 'admin';
        $statusNow = $s['status_absen'] ?? 'Hadir';
        $warnaStatus = ['Hadir' => 'success', 'Izin' => 'warning', 'Sakit' => 'info', 'Alpa' => 'danger'];
    ?>
    <div class="siswa-row <?= $kuncikanUntukGuru ? 'terkunci' : '' ?>">
      <div class="row g-2 align-items-center">
        <!-- Kolom identitas siswa -->
        <div class="col-12 col-md-4">
          <div class="d-flex align-items-start gap-2">
            <span class="text-muted small" style="min-width:22px;"><?= $i + 1 ?>.</span>
            <div>
              <div class="fw-semibold"><?= clean($s['nama_lengkap']) ?></div>
              <div class="small text-muted">NIS <?= clean($s['nis']) ?></div>
              <?php if ($s['jam_masuk_gps'] || $s['jam_pulang']): ?>
                <div class="small mt-1">
                  <?php if ($s['jam_masuk_gps']): ?>
                    <span class="badge-soft gps"><i class="bi bi-geo-alt-fill me-1"></i>Masuk <?= substr($s['jam_masuk_gps'],0,5) ?></span>
                  <?php endif; ?>
                  <?php if ($s['jam_pulang']): ?>
                    <span class="badge-soft gps ms-1"><i class="bi bi-geo-alt-fill me-1"></i>Pulang <?= substr($s['jam_pulang'],0,5) ?></span>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Kolom status -->
        <div class="col-12 col-md-5">
          <div class="mini-label d-md-none mb-1">Status Kehadiran</div>
          <?php if ($kuncikanUntukGuru): ?>
            <span class="badge-soft gps"><i class="bi bi-lock-fill me-1"></i><?= clean($statusNow) ?> (terkunci - via GPS)</span>
          <?php else: ?>
            <?php if ($terkunci): // admin, boleh override tapi diberi peringatan ?>
              <div class="form-check mb-2">
                <input type="checkbox" class="form-check-input unlock-toggle" data-target="grp-<?= $s['id'] ?>" id="unlock-<?= $s['id'] ?>">
                <label class="form-check-label small text-warning" for="unlock-<?= $s['id'] ?>"><i class="bi bi-unlock-fill"></i> Izinkan override (data GPS)</label>
              </div>
            <?php endif; ?>
            <div id="grp-<?= $s['id'] ?>" class="status-toggle-wrap">
              <?php foreach (['Hadir','Izin','Sakit','Alpa'] as $opt): ?>
                <input type="radio" class="btn-check status-radio" name="status[<?= $s['id'] ?>]"
                       id="st-<?= $s['id'] ?>-<?= $opt ?>" value="<?= $opt ?>"
                       <?= $statusNow === $opt ? 'checked' : '' ?> <?= $terkunci ? 'disabled' : '' ?>>
                <label class="btn btn-outline-<?= $warnaStatus[$opt] ?>" for="st-<?= $s['id'] ?>-<?= $opt ?>"><?= $opt ?></label>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <!-- Kolom keterangan -->
        <div class="col-12 col-md-3">
          <div class="mini-label d-md-none mb-1">Keterangan</div>
          <?php if ($kuncikanUntukGuru): ?>
            <span class="text-muted small"><?= clean($s['keterangan'] ?: '-') ?></span>
          <?php else: ?>
            <input type="text" name="keterangan[<?= $s['id'] ?>]" class="form-control form-control-sm" value="<?= clean($s['keterangan'] ?? '') ?>" placeholder="opsional" <?= $terkunci ? 'disabled' : '' ?>>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>

    <?php if (!empty($siswaKelas)): ?>
      <button class="btn btn-primary mt-3 w-100 w-md-auto"><i class="bi bi-save"></i> Simpan Absensi</button>
    <?php endif; ?>
  </form>
</div>
<?php endif; ?>

<script>
function setAllStatus(status) {
  document.querySelectorAll('.status-radio:not(:disabled)').forEach(function (r) {
    if (r.value === status) r.checked = true;
  });
}

// Admin: toggle "izinkan override" untuk siswa yang datanya berasal dari GPS
document.querySelectorAll('.unlock-toggle').forEach(function (cb) {
  cb.addEventListener('change', function () {
    const group = document.getElementById(this.dataset.target);
    if (!group) return;
    group.querySelectorAll('input').forEach(function (input) {
      input.disabled = !cb.checked;
    });
  });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>