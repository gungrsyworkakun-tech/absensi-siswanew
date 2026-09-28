<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole(['admin','guru','wali_kelas']);
$pageTitle = 'Detail Siswa';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT s.*, k.nama_kelas FROM siswa s LEFT JOIN kelas k ON s.kelas_id = k.id WHERE s.id = ?");
$stmt->execute([$id]);
$siswa = $stmt->fetch();

if (!$siswa) {
    setFlash('error', 'Data siswa tidak ditemukan.');
    redirect('siswa/list.php');
}

// Rekap absensi
$stmt = $pdo->prepare("SELECT status, COUNT(*) c FROM absensi WHERE siswa_id = ? GROUP BY status");
$stmt->execute([$id]);
$rekapAbsen = ['Hadir'=>0,'Izin'=>0,'Sakit'=>0,'Alpa'=>0];
foreach ($stmt->fetchAll() as $r) { $rekapAbsen[$r['status']] = $r['c']; }

// Riwayat absensi terbaru
$stmt = $pdo->prepare("SELECT * FROM absensi WHERE siswa_id = ? ORDER BY tanggal DESC LIMIT 10");
$stmt->execute([$id]);
$riwayatAbsen = $stmt->fetchAll();

// Nilai / rapor
$stmt = $pdo->prepare("
    SELECT n.*, m.nama_mapel FROM nilai n
    JOIN mata_pelajaran m ON n.mapel_id = m.id
    WHERE n.siswa_id = ?
    ORDER BY n.tahun_ajaran DESC, n.semester, m.nama_mapel
");
$stmt->execute([$id]);
$nilaiList = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/theme_gov.php';
?>

<style>
/* ====== Responsive tambahan untuk halaman Detail Siswa ====== */
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

  .gv-profile-photo {
    width: 100px;
    height: 100px;
  }

  /* Baris info key/value: tumpuk supaya value panjang (alamat dll) tidak terpotong */
  .gv-info-row {
    flex-direction: column;
    align-items: flex-start !important;
    gap: 2px;
    padding: 8px 0;
  }
  .gv-info-row .k {
    font-size: 0.75rem;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: .02em;
  }
  .gv-info-row .v {
    font-size: 0.92rem;
    text-align: left !important;
  }

  /* Tombol Kembali & Edit ditumpuk full width */
  .col-lg-4 .d-flex.gap-2 {
    flex-direction: column;
  }

  /* Rekap absensi: 2 kolom x 2 baris supaya angka tidak terlalu kecil */
  .col-lg-8 .row.g-2 > .col-3 {
    width: 50%;
    margin-bottom: 8px;
  }

  /* Header seksi Nilai/Rapor: tumpuk judul & tombol cetak */
  .gv-card .d-flex.justify-content-between.align-items-center {
    flex-direction: column;
    align-items: flex-start !important;
    gap: 8px;
  }
  .gv-card .d-flex.justify-content-between.align-items-center .gv-btn-outline {
    width: 100%;
    text-align: center;
  }

  /* Sembunyikan tabel asli (absensi & nilai), tampilkan versi kartu */
  .gv-table-wrap .table-responsive table.gv-table-desktop {
    display: none;
  }

  .gv-mobile-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
  }

  .gv-mobile-list .gv-mobile-item {
    border: 1px solid #e6e6e6;
    border-radius: 10px;
    padding: 10px 12px;
  }

  .gv-mobile-list .gv-mobile-item .row-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
    margin-bottom: 4px;
  }

  .gv-mobile-list .gv-mobile-item .row-top .title {
    font-weight: 600;
    font-size: 0.9rem;
  }

  .gv-mobile-list .gv-mobile-item .sub {
    font-size: 0.8rem;
    color: #6c757d;
  }
}

/* Sembunyikan versi kartu mobile di layar desktop */
@media (min-width: 768px) {
  .gv-mobile-list { display: none; }
}

/* ====== FIX: agar halaman ini tidak pernah terkunci overflow:hidden
   dan konten selalu bisa discroll penuh sampai bawah ====== */
html, body {
  height: auto !important;
  min-height: 100%;
  overflow-x: hidden;
  overflow-y: auto !important;
}
</style>

<div class="gv-topline">
  <div class="gv-title">
    <small>Manajemen Siswa</small>
    Detail Siswa
  </div>
  <span class="gv-crumb"><a href="<?= BASE_URL ?>/index.php">Laman</a> <i class="bi bi-chevron-right small"></i> <a href="list.php">Data Siswa</a> <i class="bi bi-chevron-right small"></i> <b>Detail</b></span>
</div>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="gv-card text-center">
      <img src="<?= !empty($siswa['foto']) ? BASE_URL.'/uploads/foto_siswa/'.clean($siswa['foto']) : 'https://ui-avatars.com/api/?size=200&name='.urlencode($siswa['nama_lengkap']) ?>" class="gv-profile-photo mx-auto mb-3">
      <h5 class="fw-bold mb-0"><?= clean($siswa['nama_lengkap']) ?></h5>
      <div class="text-muted small mb-2">NIS: <?= clean($siswa['nis']) ?> <?= $siswa['nisn'] ? '&bull; NISN: '.clean($siswa['nisn']) : '' ?></div>
      <span class="gv-pill <?= $siswa['status']==='Aktif' ? 'good' : 'neutral' ?> mb-3"><?= clean($siswa['status']) ?></span>

      <div class="text-start mt-2">
        <div class="gv-info-row"><span class="k">Kelas</span><span class="v"><?= clean($siswa['nama_kelas'] ?? '-') ?></span></div>
        <div class="gv-info-row"><span class="k">Jenis Kelamin</span><span class="v"><?= $siswa['jenis_kelamin']==='L'?'Laki-laki':'Perempuan' ?></span></div>
        <div class="gv-info-row"><span class="k">TTL</span><span class="v"><?= clean($siswa['tempat_lahir'] ?: '-') ?>, <?= formatTanggalIndo($siswa['tanggal_lahir']) ?></span></div>
        <div class="gv-info-row"><span class="k">Agama</span><span class="v"><?= clean($siswa['agama'] ?: '-') ?></span></div>
        <div class="gv-info-row"><span class="k">Alamat</span><span class="v"><?= nl2br(clean($siswa['alamat'] ?: '-')) ?></span></div>
        <div class="gv-info-row"><span class="k">No. HP</span><span class="v"><?= clean($siswa['no_hp'] ?: '-') ?></span></div>
        <div class="gv-info-row"><span class="k">Email</span><span class="v"><?= clean($siswa['email'] ?: '-') ?></span></div>
        <div class="gv-info-row"><span class="k">Nama Ayah</span><span class="v"><?= clean($siswa['nama_ayah'] ?: '-') ?></span></div>
        <div class="gv-info-row"><span class="k">Nama Ibu</span><span class="v"><?= clean($siswa['nama_ibu'] ?: '-') ?></span></div>
        <div class="gv-info-row"><span class="k">No. HP Ortu</span><span class="v"><?= clean($siswa['no_hp_ortu'] ?: '-') ?></span></div>
        <div class="gv-info-row"><span class="k">Pekerjaan Ortu</span><span class="v"><?= clean($siswa['pekerjaan_ortu'] ?: '-') ?></span></div>
        <div class="gv-info-row"><span class="k">Tanggal Masuk</span><span class="v"><?= formatTanggalIndo($siswa['tanggal_masuk']) ?></span></div>
      </div>

      <div class="d-flex gap-2 mt-3">
        <a href="list.php" class="gv-btn-outline flex-grow-1"><i class="bi bi-arrow-left"></i> Kembali</a>
        <?php if (currentUser()['role'] !== 'wali_kelas'): ?>
        <a href="edit.php?id=<?= $siswa['id'] ?>" class="gv-btn flex-grow-1"><i class="bi bi-pencil-square"></i> Edit</a>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <!-- Rekap Absensi -->
    <div class="gv-card mb-3">
      <div class="gv-section-label">Rekap Absensi</div>
      <div class="row g-2 mb-3">
        <div class="col-3"><div class="gv-mini-stat"><div class="num" style="color:var(--good);"><?= $rekapAbsen['Hadir'] ?></div><div class="lbl">Hadir</div></div></div>
        <div class="col-3"><div class="gv-mini-stat"><div class="num" style="color:var(--warn);"><?= $rekapAbsen['Izin'] ?></div><div class="lbl">Izin</div></div></div>
        <div class="col-3"><div class="gv-mini-stat"><div class="num" style="color:var(--info);"><?= $rekapAbsen['Sakit'] ?></div><div class="lbl">Sakit</div></div></div>
        <div class="col-3"><div class="gv-mini-stat"><div class="num" style="color:var(--bad);"><?= $rekapAbsen['Alpa'] ?></div><div class="lbl">Alpa</div></div></div>
      </div>

      <!-- Versi tabel (desktop/tablet) -->
      <div class="gv-table-wrap">
        <div class="table-responsive">
          <table class="table mb-0 gv-table-desktop">
            <thead><tr><th>Tanggal</th><th>Status</th><th>Keterangan</th></tr></thead>
            <tbody>
              <?php if (empty($riwayatAbsen)): ?>
                <tr><td colspan="3" class="text-muted text-center py-3">Belum ada riwayat absensi.</td></tr>
              <?php endif; ?>
              <?php foreach ($riwayatAbsen as $a):
                $pillMap = ['Hadir'=>'good','Izin'=>'warn','Sakit'=>'info','Alpa'=>'bad'];
              ?>
              <tr>
                <td><?= formatTanggalIndo($a['tanggal']) ?></td>
                <td><span class="gv-pill <?= $pillMap[$a['status']] ?? 'neutral' ?>"><?= clean($a['status']) ?></span></td>
                <td class="text-muted"><?= clean($a['keterangan'] ?: '-') ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <!-- Versi kartu (mobile) -->
        <div class="gv-mobile-list">
          <?php if (empty($riwayatAbsen)): ?>
            <div class="text-muted text-center py-3">Belum ada riwayat absensi.</div>
          <?php endif; ?>
          <?php foreach ($riwayatAbsen as $a):
            $pillMap = ['Hadir'=>'good','Izin'=>'warn','Sakit'=>'info','Alpa'=>'bad'];
          ?>
          <div class="gv-mobile-item">
            <div class="row-top">
              <span class="title"><?= formatTanggalIndo($a['tanggal']) ?></span>
              <span class="gv-pill <?= $pillMap[$a['status']] ?? 'neutral' ?>"><?= clean($a['status']) ?></span>
            </div>
            <div class="sub"><?= clean($a['keterangan'] ?: '-') ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- Nilai / Rapor -->
    <div class="gv-card">
      <div class="d-flex justify-content-between align-items-center">
        <div class="gv-section-label mb-0" style="border-bottom:none; padding-bottom:0;">Nilai / Rapor</div>
        <a href="<?= BASE_URL ?>/nilai/rapor.php?siswa_id=<?= $siswa['id'] ?>" class="gv-btn-outline"><i class="bi bi-printer"></i> Cetak Rapor</a>
      </div>

      <!-- Versi tabel (desktop/tablet) -->
      <div class="gv-table-wrap mt-3">
        <div class="table-responsive">
          <table class="table mb-0 gv-table-desktop">
            <thead><tr><th>Mapel</th><th>Semester</th><th>Tahun Ajaran</th><th>Nilai Akhir</th><th>Predikat</th></tr></thead>
            <tbody>
              <?php if (empty($nilaiList)): ?>
                <tr><td colspan="5" class="text-muted text-center py-3">Belum ada nilai.</td></tr>
              <?php endif; ?>
              <?php foreach ($nilaiList as $n): ?>
              <tr>
                <td class="fw-semibold"><?= clean($n['nama_mapel']) ?></td>
                <td><?= clean($n['semester']) ?></td>
                <td><?= clean($n['tahun_ajaran']) ?></td>
                <td class="fw-bold"><?= number_format($n['nilai_akhir'],1) ?></td>
                <td><span class="gv-pill neutral"><?= clean($n['predikat']) ?></span></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <!-- Versi kartu (mobile) -->
        <div class="gv-mobile-list">
          <?php if (empty($nilaiList)): ?>
            <div class="text-muted text-center py-3">Belum ada nilai.</div>
          <?php endif; ?>
          <?php foreach ($nilaiList as $n): ?>
          <div class="gv-mobile-item">
            <div class="row-top">
              <span class="title"><?= clean($n['nama_mapel']) ?></span>
              <span class="gv-pill neutral"><?= clean($n['predikat']) ?></span>
            </div>
            <div class="sub">Semester <?= clean($n['semester']) ?> &bull; TA <?= clean($n['tahun_ajaran']) ?> &bull; Nilai: <b><?= number_format($n['nilai_akhir'],1) ?></b></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<style>
/* Jaga-jaga: sisakan ruang kosong di paling bawah halaman ini
   supaya bottom nav (fixed) tidak menutupi baris terakhir konten,
   berapapun tinggi nav tersebut. */
.row.g-3 { padding-bottom: 90px; }
</style>

<script>
(function () {
  // Deteksi otomatis elemen fixed/sticky yang menempel di bawah layar
  // (bottom nav) dan sesuaikan jarak kosong di atas supaya presisi,
  // tanpa perlu tahu nama class/id nav tersebut.
  function fixBottomNavOverlap() {
    var vh = window.innerHeight;
    var els = document.querySelectorAll('body *');
    var navHeight = 0;

    els.forEach(function (el) {
      var style = window.getComputedStyle(el);
      if (style.position !== 'fixed' && style.position !== 'sticky') return;

      var rect = el.getBoundingClientRect();
      var dekatBawah = Math.abs(rect.bottom - vh) < 4;
      var cukupLebar = rect.width > window.innerWidth * 0.6;

      if (dekatBawah && cukupLebar && rect.height > 0 && rect.height < 140) {
        if (rect.height > navHeight) navHeight = rect.height;
      }
    });

    if (navHeight > 0) {
      var wrapper = document.querySelector('.row.g-3');
      if (wrapper) {
        wrapper.style.paddingBottom =
          'calc(' + (navHeight + 24) + 'px + env(safe-area-inset-bottom))';
      }
    }
  }

  window.addEventListener('load', fixBottomNavOverlap);
  window.addEventListener('resize', fixBottomNavOverlap);
  window.addEventListener('orientationchange', fixBottomNavOverlap);
  document.addEventListener('DOMContentLoaded', fixBottomNavOverlap);
})();
</script>