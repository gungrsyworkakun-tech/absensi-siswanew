<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole(['siswa']);
require_once __DIR__ . '/_lib.php';
$user = currentUser();
$pageTitle = 'Mengerjakan Ujian';
$now = time();

$ujianId = (int)($_GET['id'] ?? 0);
$ujian   = ujianAmbil($pdo, $ujianId);
$siswaId = (int)$user['siswa_id'];

$stmt = $pdo->prepare("SELECT kelas_id FROM siswa WHERE id = ?");
$stmt->execute([$siswaId]);
$kelasSiswa = (int)$stmt->fetchColumn();

if (!$ujian || $ujian['status'] !== 'Terbit' || (int)$ujian['kelas_id'] !== $kelasSiswa) {
    setFlash('error', 'Ujian tidak tersedia untuk Anda.');
    redirect('ujian/index.php');
}

$stmt = $pdo->prepare("SELECT * FROM ujian_peserta WHERE ujian_id = ? AND siswa_id = ?");
$stmt->execute([$ujianId, $siswaId]);
$peserta = $stmt->fetch();

if ($peserta && $peserta['status'] === 'Selesai') {
    setFlash('info', 'Anda sudah menyelesaikan ujian ini.');
    redirect('ujian/index.php');
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM ujian_soal WHERE ujian_id = ?");
$stmt->execute([$ujianId]);
$jumlahSoal = (int)$stmt->fetchColumn();

/* ---------- Belum mulai: layar petunjuk + tombol mulai ---------- */
if (!$peserta) {
    $dalamJendela = $now >= strtotime($ujian['waktu_mulai']) && $now < strtotime($ujian['waktu_selesai']);
    if (!$dalamJendela || $jumlahSoal < 1) {
        setFlash('error', 'Ujian ini belum dibuka atau sudah ditutup.');
        redirect('ujian/index.php');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'mulai') {
        $stmt = $pdo->prepare("INSERT IGNORE INTO ujian_peserta (ujian_id, siswa_id, waktu_mulai, status) VALUES (?,?,?, 'Berlangsung')");
        $stmt->execute([$ujianId, $siswaId, date('Y-m-d H:i:s')]);
        redirect("ujian/kerjakan.php?id={$ujianId}");
    }

    $durasiEfektif = min((int)$ujian['durasi_menit'], (int)ceil((strtotime($ujian['waktu_selesai']) - $now) / 60));
    include __DIR__ . '/../includes/header.php';
    ?>
    <div class="card p-4" style="max-width:640px;margin:0 auto;">
      <div class="mb-2"><?= ujianBadgeJenis($ujian['jenis']) ?></div>
      <h4 class="fw-bold"><?= clean($ujian['judul']) ?></h4>
      <div class="text-muted mb-3"><?= clean($ujian['nama_mapel']) ?> &middot; Kelas <?= clean($ujian['nama_kelas']) ?></div>

      <ul class="mb-3">
        <li><?= $jumlahSoal ?> soal</li>
        <li>Waktu mengerjakan <strong><?= $durasiEfektif ?> menit</strong> begitu Anda menekan Mulai. Waktu tidak bisa dijeda.</li>
        <li>Jawaban tersimpan otomatis. Bila waktu habis, ujian dikirim otomatis.</li>
        <li>Ujian hanya bisa dikerjakan satu kali.</li>
      </ul>
      <?php if (trim($ujian['petunjuk'] ?? '') !== ''): ?>
        <div class="alert alert-light border small"><strong>Petunjuk guru:</strong><br><?= nl2br(clean($ujian['petunjuk'])) ?></div>
      <?php endif; ?>

      <form method="POST">
        <input type="hidden" name="aksi" value="mulai">
        <div class="d-flex gap-2">
          <a href="index.php" class="btn btn-outline-secondary">Kembali</a>
          <button class="btn btn-primary flex-grow-1"><i class="bi bi-play-fill"></i> Mulai Ujian Sekarang</button>
        </div>
      </form>
    </div>
    <?php
    include __DIR__ . '/../includes/footer.php';
    exit;
}

/* ---------- Sedang berlangsung ---------- */
$deadline    = ujianDeadline($ujian, $peserta);
$batasSimpan = $deadline + 30; // toleransi 30 detik untuk kirim otomatis saat waktu habis

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'simpan') { // autosave via AJAX
        header('Content-Type: application/json');
        if ($now > $batasSimpan) {
            echo json_encode(['ok' => false, 'pesan' => 'Waktu habis, jawaban tidak disimpan.']);
            exit;
        }
        $ok = ujianSimpanJawaban($pdo, $peserta['id'], $ujianId, (int)($_POST['soal_id'] ?? 0), $_POST['jawaban'] ?? '');
        echo json_encode(['ok' => $ok]);
        exit;
    }

    if ($aksi === 'selesai') {
        if ($now <= $batasSimpan && !empty($_POST['jawaban']) && is_array($_POST['jawaban'])) {
            foreach ($_POST['jawaban'] as $soalId => $jaw) {
                ujianSimpanJawaban($pdo, $peserta['id'], $ujianId, (int)$soalId, $jaw);
            }
        }
        ujianSelesaikan($pdo, $peserta['id']);
        setFlash('success', 'Ujian selesai dan jawaban Anda terkirim.');
        redirect('ujian/index.php');
    }
}

if ($now >= $deadline) { // waktu habis saat membuka halaman
    ujianSelesaikan($pdo, $peserta['id'], date('Y-m-d H:i:s', $deadline));
    setFlash('info', 'Waktu ujian sudah habis. Jawaban Anda terkirim otomatis.');
    redirect('ujian/index.php');
}

$urut = $ujian['acak_soal'] ? "MD5(CONCAT(s.id, '-', " . (int)$peserta['id'] . "))" : "s.urutan, s.id";
$stmt = $pdo->prepare("
    SELECT s.id, s.tipe, s.pertanyaan, s.gambar, s.opsi_a, s.opsi_b, s.opsi_c, s.opsi_d, s.opsi_e, s.bobot, j.jawaban
    FROM ujian_soal s
    LEFT JOIN ujian_jawaban j ON j.soal_id = s.id AND j.peserta_id = ?
    WHERE s.ujian_id = ?
    ORDER BY {$urut}
");
$stmt->execute([$peserta['id'], $ujianId]);
$soalList = $stmt->fetchAll();
$terjawabAwal = count(array_filter($soalList, fn($s) => trim((string)$s['jawaban']) !== ''));

include __DIR__ . '/../includes/header.php';
?>
<style>
.ujian-bar{ position:sticky; top:64px; z-index:900; }
.ujian-timer{ font-variant-numeric:tabular-nums; font-size:1.5rem; font-weight:800; }
.ujian-timer.urgent{ color:#dc3545; }
.nav-soal{ display:flex; flex-wrap:wrap; gap:6px; }
.nav-soal a{ width:34px; height:34px; border-radius:8px; border:1px solid #d0d5dd; display:flex; align-items:center; justify-content:center; font-size:.8rem; font-weight:600; color:#475467; text-decoration:none; background:#fff; }
.nav-soal a.done{ background:#198754; border-color:#198754; color:#fff; }
.opsi-label{ display:flex; gap:10px; align-items:flex-start; padding:10px 12px; border:1px solid #e4e7ec; border-radius:10px; margin-bottom:8px; cursor:pointer; }
.opsi-label:hover{ background:#f8f9fb; }
.opsi-label input:checked + span{ font-weight:700; }
</style>

<div class="card p-3 mb-3 ujian-bar">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
      <div class="fw-bold"><?= clean($ujian['judul']) ?></div>
      <div class="small text-muted"><span id="jmlTerjawab"><?= $terjawabAwal ?></span> dari <?= count($soalList) ?> soal terjawab &middot; <span id="saveStatus">Jawaban tersimpan otomatis</span></div>
    </div>
    <div class="text-end">
      <div class="small text-muted">Sisa waktu</div>
      <div class="ujian-timer" id="timer">--:--</div>
    </div>
  </div>
  <div class="nav-soal mt-2">
    <?php foreach ($soalList as $i => $s): ?>
      <a href="#soal-<?= (int)$s['id'] ?>" data-nav="<?= (int)$s['id'] ?>" class="<?= trim((string)$s['jawaban']) !== '' ? 'done' : '' ?>"><?= $i + 1 ?></a>
    <?php endforeach; ?>
  </div>
</div>

<form method="POST" id="formUjian">
  <input type="hidden" name="aksi" value="selesai">

  <?php foreach ($soalList as $i => $s): ?>
  <div class="card p-3 mb-3" id="soal-<?= (int)$s['id'] ?>" style="scroll-margin-top:190px;">
    <div class="fw-semibold mb-2"><?= $i + 1 ?>. <?= nl2br(clean($s['pertanyaan'])) ?></div>
    <?php if (!empty($s['gambar'])): ?>
      <div class="mb-3"><img src="<?= clean(ujianUrlGambar($s['gambar'])) ?>" alt="Gambar soal nomor <?= $i + 1 ?>" class="img-fluid rounded border" style="max-height:360px;"></div>
    <?php endif; ?>

    <?php if ($s['tipe'] === 'pilihan_ganda'): ?>
      <?php foreach (['a', 'b', 'c', 'd', 'e'] as $k): if ($s["opsi_{$k}"] === null) continue; ?>
        <label class="opsi-label">
          <input type="radio" name="jawaban[<?= (int)$s['id'] ?>]" value="<?= $k ?>" data-soal="<?= (int)$s['id'] ?>" <?= $s['jawaban'] === $k ? 'checked' : '' ?>>
          <span><strong><?= strtoupper($k) ?>.</strong> <?= clean($s["opsi_{$k}"]) ?></span>
        </label>
      <?php endforeach; ?>
    <?php else: ?>
      <textarea name="jawaban[<?= (int)$s['id'] ?>]" data-soal="<?= (int)$s['id'] ?>" rows="5" class="form-control" placeholder="Tulis jawaban Anda di sini"><?= clean($s['jawaban'] ?? '') ?></textarea>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>

  <button type="submit" class="btn btn-primary w-100 mb-4"><i class="bi bi-send-check"></i> Selesai &amp; Kirim Jawaban</button>
</form>

<script>
(function () {
  var form = document.getElementById('formUjian');
  var statusEl = document.getElementById('saveStatus');
  var timerEl = document.getElementById('timer');
  var jmlEl = document.getElementById('jmlTerjawab');
  var total = <?= count($soalList) ?>;
  var akhir = Date.now() + <?= max(0, $deadline - $now) ?> * 1000;
  var otomatis = false;
  var debounce = {};

  function hitungTerjawab() {
    jmlEl.textContent = document.querySelectorAll('.nav-soal a.done').length;
  }
  function tandai(soalId, terisi) {
    var el = document.querySelector('[data-nav="' + soalId + '"]');
    if (el) el.classList.toggle('done', terisi);
    hitungTerjawab();
  }
  function simpan(soalId, nilai) {
    statusEl.textContent = 'Menyimpan...';
    var body = new URLSearchParams({ aksi: 'simpan', soal_id: soalId, jawaban: nilai });
    fetch(window.location.href, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
      body: body.toString()
    })
      .then(function (r) { return r.json(); })
      .then(function (res) { statusEl.textContent = res.ok ? 'Tersimpan' : (res.pesan || 'Gagal menyimpan'); })
      .catch(function () { statusEl.textContent = 'Gagal menyimpan, periksa koneksi internet'; });
  }

  form.querySelectorAll('input[type=radio]').forEach(function (r) {
    r.addEventListener('change', function () {
      tandai(r.dataset.soal, true);
      simpan(r.dataset.soal, r.value);
    });
  });
  form.querySelectorAll('textarea').forEach(function (t) {
    t.addEventListener('input', function () {
      var id = t.dataset.soal;
      tandai(id, t.value.trim() !== '');
      clearTimeout(debounce[id]);
      debounce[id] = setTimeout(function () { simpan(id, t.value); }, 800);
    });
  });

  form.addEventListener('submit', function (e) {
    if (otomatis) return;
    var kosong = total - document.querySelectorAll('.nav-soal a.done').length;
    var pesan = kosong > 0
      ? 'Masih ada ' + kosong + ' soal yang belum dijawab. Kirim jawaban sekarang?'
      : 'Kirim jawaban sekarang? Setelah dikirim tidak bisa diubah.';
    if (!confirm(pesan)) e.preventDefault();
  });

  function tick() {
    var sisa = Math.max(0, Math.round((akhir - Date.now()) / 1000));
    var m = Math.floor(sisa / 60), s = sisa % 60;
    timerEl.textContent = (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
    timerEl.classList.toggle('urgent', sisa <= 300);
    if (sisa <= 0 && !otomatis) {
      otomatis = true;
      timerEl.textContent = '00:00';
      statusEl.textContent = 'Waktu habis, mengirim jawaban...';
      form.submit();
    }
  }
  tick();
  setInterval(tick, 1000);
})();
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
