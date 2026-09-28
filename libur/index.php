<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/libur.php';
requireRole(['admin']);
$pageTitle = 'Hari Libur & Tanggal Merah';
$user = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'tambah') {
    $tanggal    = $_POST['tanggal'] ?? '';
    $keterangan = trim($_POST['keterangan'] ?? '');
    $jenis      = $_POST['jenis'] ?? 'Libur Nasional';

    if (!$tanggal || $keterangan === '') {
        setFlash('error', 'Tanggal dan keterangan wajib diisi.');
    } else {
        $hariAngka = (int)date('N', strtotime($tanggal));
        if ($hariAngka >= 6) {
            setFlash('error', 'Tanggal itu sudah Sabtu/Minggu, otomatis libur — tidak perlu ditambahkan manual.');
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO hari_libur (tanggal, keterangan, jenis, dibuat_oleh)
                VALUES (?,?,?,?)
                ON DUPLICATE KEY UPDATE keterangan = VALUES(keterangan), jenis = VALUES(jenis), dibuat_oleh = VALUES(dibuat_oleh)
            ");
            $stmt->execute([$tanggal, $keterangan, $jenis, $user['nama']]);
            setFlash('success', 'Hari libur berhasil disimpan.');
        }
    }
    redirect('libur/index.php');
}

if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    $pdo->prepare("DELETE FROM hari_libur WHERE id = ?")->execute([$id]);
    setFlash('success', 'Hari libur berhasil dihapus.');
    redirect('libur/index.php');
}

$tahunFilter = $_GET['tahun'] ?? date('Y');
$stmt = $pdo->prepare("SELECT * FROM hari_libur WHERE YEAR(tanggal) = ? ORDER BY tanggal ASC");
$stmt->execute([$tahunFilter]);
$daftarLibur = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h4 class="fw-bold mb-0"><i class="bi bi-calendar-x-fill me-2"></i>Hari Libur &amp; Tanggal Merah</h4>
  <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalTambahLibur"><i class="bi bi-plus-lg"></i> Tambah Tanggal Merah</button>
</div>

<div class="alert alert-light border small">
  <i class="bi bi-info-circle me-1"></i> <strong>Sabtu &amp; Minggu otomatis dianggap libur</strong> di seluruh sistem (Monitor GPS, Absen Mandiri Siswa, Input Absensi) — tidak perlu ditambahkan di sini. Halaman ini khusus untuk menandai tanggal merah/libur nasional yang jatuh di hari kerja (Senin–Jumat), misalnya hari besar keagamaan, cuti bersama, atau libur khusus sekolah.
</div>

<div class="card p-3 mb-3">
  <form method="GET" class="row g-2">
    <div class="col-md-3">
      <label class="form-label small">Tahun</label>
      <select name="tahun" class="form-select" onchange="this.form.submit()">
        <?php for ($t = (int)date('Y') - 2; $t <= (int)date('Y') + 2; $t++): ?>
          <option value="<?= $t ?>" <?= (string)$tahunFilter === (string)$t ? 'selected' : '' ?>><?= $t ?></option>
        <?php endfor; ?>
      </select>
    </div>
  </form>
</div>

<div class="card p-3">
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead class="table-light">
        <tr><th>#</th><th>Tanggal</th><th>Hari</th><th>Keterangan</th><th>Jenis</th><th>Ditambahkan Oleh</th><th class="text-end">Aksi</th></tr>
      </thead>
      <tbody>
        <?php if (empty($daftarLibur)): ?>
          <tr><td colspan="7" class="text-center text-muted py-4">Belum ada tanggal merah untuk tahun ini.</td></tr>
        <?php endif; ?>
        <?php foreach ($daftarLibur as $i => $l): ?>
        <tr>
          <td><?= $i + 1 ?></td>
          <td class="fw-semibold"><?= formatTanggalIndo($l['tanggal']) ?></td>
          <td class="text-muted small"><?= clean(date('l', strtotime($l['tanggal']))) ?></td>
          <td><?= clean($l['keterangan']) ?></td>
          <td><span class="badge-soft brand"><?= clean($l['jenis']) ?></span></td>
          <td class="text-muted small"><?= clean($l['dibuat_oleh'] ?: '-') ?></td>
          <td class="text-end">
            <a href="?hapus=<?= $l['id'] ?>&tahun=<?= clean($tahunFilter) ?>" class="text-danger" onclick="return confirm('Hapus tanggal merah ini?');"><i class="bi bi-trash"></i></a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="modal fade" id="modalTambahLibur" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="aksi" value="tambah">
        <div class="modal-header">
          <h6 class="modal-title">Tambah Tanggal Merah</h6>
          <button class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Tanggal</label>
            <input type="date" name="tanggal" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Keterangan</label>
            <input type="text" name="keterangan" class="form-control" placeholder="Contoh: Maulid Nabi Muhammad SAW" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Jenis</label>
            <select name="jenis" class="form-select">
              <option value="Libur Nasional">Libur Nasional</option>
              <option value="Cuti Bersama">Cuti Bersama</option>
              <option value="Libur Sekolah">Libur Sekolah</option>
              <option value="Lainnya">Lainnya</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
          <button class="btn btn-primary"><i class="bi bi-save"></i> Simpan</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>