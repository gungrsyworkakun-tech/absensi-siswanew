<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole(['admin', 'guru']);
require_once __DIR__ . '/_lib.php';
$user = currentUser();
$pageTitle = 'Soal Ujian';

$ujianId = (int)($_GET['id'] ?? 0);
$ujian = ujianAmbil($pdo, $ujianId);
if (!ujianBolehKelola($user, $ujian)) {
    setFlash('error', 'Ujian tidak ditemukan atau bukan milik Anda.');
    redirect('ujian/index.php');
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM ujian_peserta WHERE ujian_id = ?");
$stmt->execute([$ujianId]);
$adaPeserta = (int)$stmt->fetchColumn() > 0;

$back = "ujian/soal.php?id={$ujianId}";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        setFlash('error', 'Ukuran file melebihi batas upload server (post_max_size).');
        redirect($back);
    }
    $aksi = $_POST['aksi'] ?? '';

    if ($adaPeserta && in_array($aksi, ['simpan_soal', 'hapus_soal', 'draftkan'], true)) {
        setFlash('error', 'Soal terkunci karena sudah ada siswa yang mengerjakan ujian ini.');
        redirect($back);
    }

    if ($aksi === 'simpan_soal') {
        $soalId     = (int)($_POST['soal_id'] ?? 0);
        $tipe       = ($_POST['tipe'] ?? '') === 'essay' ? 'essay' : 'pilihan_ganda';
        $pertanyaan = trim($_POST['pertanyaan'] ?? '');
        $bobot      = (float)($_POST['bobot'] ?? 1);
        $bobot      = max(0.01, min(100, $bobot));
        $pedoman    = trim($_POST['pedoman'] ?? '');
        $opsi = [];
        foreach (['a', 'b', 'c', 'd', 'e'] as $k) {
            $v = trim($_POST["opsi_{$k}"] ?? '');
            $opsi[$k] = $v === '' ? null : mb_substr($v, 0, 500);
        }
        $kunci = strtolower($_POST['kunci'] ?? '');

        if ($pertanyaan === '') {
            setFlash('error', 'Pertanyaan wajib diisi.');
            redirect($back . ($soalId ? "&edit={$soalId}" : ''));
        }
        if ($tipe === 'pilihan_ganda') {
            if ($opsi['a'] === null || $opsi['b'] === null) {
                setFlash('error', 'Pilihan ganda minimal punya opsi A dan B.');
                redirect($back . ($soalId ? "&edit={$soalId}" : ''));
            }
            if (!in_array($kunci, ['a', 'b', 'c', 'd', 'e'], true) || $opsi[$kunci] === null) {
                setFlash('error', 'Kunci jawaban harus salah satu opsi yang terisi.');
                redirect($back . ($soalId ? "&edit={$soalId}" : ''));
            }
            $pedoman = null;
        } else {
            $opsi = ['a' => null, 'b' => null, 'c' => null, 'd' => null, 'e' => null];
            $kunci = null;
        }

        // Gambar soal (opsional): unggah baru, atau hapus yang lama
        $gambarLama = null;
        if ($soalId) {
            $stmt = $pdo->prepare("SELECT gambar FROM ujian_soal WHERE id = ? AND ujian_id = ?");
            $stmt->execute([$soalId, $ujianId]);
            $gambarLama = $stmt->fetchColumn() ?: null;
        }
        $up = ujianSimpanUploadGambar($_FILES['gambar'] ?? ['error' => UPLOAD_ERR_NO_FILE]);
        if (!empty($up['error'])) {
            setFlash('error', $up['error']);
            redirect($back . ($soalId ? "&edit={$soalId}" : ''));
        }
        $gambar = $gambarLama;
        if ($up['nama']) {
            $gambar = $up['nama'];
        } elseif (!empty($_POST['hapus_gambar'])) {
            $gambar = null;
        }
        if ($gambarLama && $gambar !== $gambarLama) ujianHapusGambar($gambarLama);

        if ($soalId) {
            $stmt = $pdo->prepare("
                UPDATE ujian_soal SET tipe=?, pertanyaan=?, gambar=?, opsi_a=?, opsi_b=?, opsi_c=?, opsi_d=?, opsi_e=?,
                    kunci=?, pedoman=?, bobot=? WHERE id=? AND ujian_id=?
            ");
            $stmt->execute([$tipe, $pertanyaan, $gambar, $opsi['a'], $opsi['b'], $opsi['c'], $opsi['d'], $opsi['e'], $kunci, $pedoman, $bobot, $soalId, $ujianId]);
            setFlash('success', 'Soal diperbarui.');
        } else {
            $stmt = $pdo->prepare("SELECT COALESCE(MAX(urutan),0) + 1 FROM ujian_soal WHERE ujian_id = ?");
            $stmt->execute([$ujianId]);
            $urutan = (int)$stmt->fetchColumn();
            $stmt = $pdo->prepare("
                INSERT INTO ujian_soal (ujian_id, tipe, pertanyaan, gambar, opsi_a, opsi_b, opsi_c, opsi_d, opsi_e, kunci, pedoman, bobot, urutan)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)
            ");
            $stmt->execute([$ujianId, $tipe, $pertanyaan, $gambar, $opsi['a'], $opsi['b'], $opsi['c'], $opsi['d'], $opsi['e'], $kunci, $pedoman, $bobot, $urutan]);
            setFlash('success', 'Soal ditambahkan.');
        }
        redirect($back);
    }

    if ($aksi === 'hapus_soal') {
        $hapusId = (int)($_POST['soal_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT gambar FROM ujian_soal WHERE id = ? AND ujian_id = ?");
        $stmt->execute([$hapusId, $ujianId]);
        ujianHapusGambar($stmt->fetchColumn() ?: null);
        $pdo->prepare("DELETE FROM ujian_soal WHERE id = ? AND ujian_id = ?")->execute([$hapusId, $ujianId]);
        setFlash('success', 'Soal dihapus.');
        redirect($back);
    }

    if ($aksi === 'terbitkan') {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM ujian_soal WHERE ujian_id = ?");
        $stmt->execute([$ujianId]);
        if ((int)$stmt->fetchColumn() < 1) {
            setFlash('error', 'Tambahkan minimal satu soal sebelum menerbitkan ujian.');
        } else {
            $pdo->prepare("UPDATE ujian SET status = 'Terbit' WHERE id = ?")->execute([$ujianId]);
            setFlash('success', 'Ujian diterbitkan. Siswa kelas ' . $ujian['nama_kelas'] . ' bisa melihatnya sesuai jadwal.');
        }
        redirect($back);
    }

    if ($aksi === 'draftkan') {
        $pdo->prepare("UPDATE ujian SET status = 'Draft' WHERE id = ?")->execute([$ujianId]);
        setFlash('success', 'Ujian dikembalikan ke draft dan disembunyikan dari siswa.');
        redirect($back);
    }
}

$stmt = $pdo->prepare("SELECT * FROM ujian_soal WHERE ujian_id = ? ORDER BY urutan, id");
$stmt->execute([$ujianId]);
$soalList = $stmt->fetchAll();
$totalBobot = array_sum(array_column($soalList, 'bobot'));

$edit = null;
if (!empty($_GET['edit']) && !$adaPeserta) {
    foreach ($soalList as $s) {
        if ((int)$s['id'] === (int)$_GET['edit']) $edit = $s;
    }
}
$e = $edit ?: [];
$tipeForm = $e['tipe'] ?? 'pilihan_ganda';

$ujian = ujianAmbil($pdo, $ujianId); // muat ulang (status bisa berubah)
include __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="fw-bold mb-0"><i class="bi bi-list-check me-2"></i><?= clean($ujian['judul']) ?></h4>
    <div class="small text-muted"><?= ujianBadgeJenis($ujian['jenis']) ?> <?= clean($ujian['nama_mapel']) ?> &middot; <?= clean($ujian['nama_kelas']) ?> &middot; <?= (int)$ujian['durasi_menit'] ?> menit &middot; <?= ujianFormatWaktu($ujian['waktu_mulai']) ?> s/d <?= ujianFormatWaktu($ujian['waktu_selesai']) ?></div>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Daftar Ujian</a>
    <a href="form.php?id=<?= $ujianId ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-gear"></i> Info Ujian</a>
    <a href="hasil.php?id=<?= $ujianId ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-bar-chart-fill"></i> Hasil</a>
    <?php if (!$adaPeserta): ?>
      <a href="impor.php?id=<?= $ujianId ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-file-earmark-word"></i> Impor dari Word</a>
    <?php endif; ?>
    <form method="POST" class="d-inline">
      <?php if ($ujian['status'] === 'Draft'): ?>
        <input type="hidden" name="aksi" value="terbitkan">
        <button class="btn btn-success btn-sm"><i class="bi bi-send-fill"></i> Terbitkan Ujian</button>
      <?php elseif (!$adaPeserta): ?>
        <input type="hidden" name="aksi" value="draftkan">
        <button class="btn btn-outline-warning btn-sm"><i class="bi bi-eye-slash"></i> Jadikan Draft</button>
      <?php endif; ?>
    </form>
  </div>
</div>

<div class="alert alert-<?= $ujian['status'] === 'Terbit' ? 'success' : 'warning' ?> small">
  Status: <strong><?= clean($ujian['status']) ?></strong>.
  <?= $ujian['status'] === 'Draft' ? 'Siswa belum bisa melihat ujian ini sampai Anda menerbitkannya.' : 'Ujian terlihat oleh siswa sesuai jadwal.' ?>
  <?php if ($adaPeserta): ?> Soal terkunci karena sudah ada siswa yang mengerjakan.<?php endif; ?>
</div>

<div class="row g-3">
  <div class="col-12 col-xl-7">
    <div class="card p-3">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h6 class="fw-bold mb-0">Daftar Soal (<?= count($soalList) ?>)</h6>
        <span class="small text-muted">Total bobot <?= rtrim(rtrim(number_format($totalBobot, 2), '0'), '.') ?> = nilai 100</span>
      </div>

      <?php if (empty($soalList)): ?>
        <div class="text-center text-muted py-4">Belum ada soal. Tambahkan lewat form di samping.</div>
      <?php endif; ?>

      <?php foreach ($soalList as $i => $s): ?>
      <div class="border rounded p-3 mb-2">
        <div class="d-flex justify-content-between gap-2">
          <div class="fw-semibold"><?= $i + 1 ?>. <?= nl2br(clean($s['pertanyaan'])) ?></div>
          <div class="text-nowrap">
            <span class="badge-soft <?= $s['tipe'] === 'essay' ? 'warn' : 'info' ?>"><?= $s['tipe'] === 'essay' ? 'Essay' : 'Pilihan ganda' ?></span>
            <span class="badge-soft brand">bobot <?= rtrim(rtrim(number_format($s['bobot'], 2), '0'), '.') ?></span>
          </div>
        </div>
        <?php if (!empty($s['gambar'])): ?>
          <img src="<?= clean(ujianUrlGambar($s['gambar'])) ?>" alt="Gambar soal" class="img-fluid rounded border mt-2" style="max-height:200px;">
        <?php endif; ?>
        <?php if ($s['tipe'] === 'pilihan_ganda'): ?>
          <div class="small mt-2">
            <?php foreach (['a', 'b', 'c', 'd', 'e'] as $k): if ($s["opsi_{$k}"] === null) continue; ?>
              <div class="<?= $s['kunci'] === $k ? 'text-success fw-bold' : 'text-muted' ?>">
                <?= strtoupper($k) ?>. <?= clean($s["opsi_{$k}"]) ?><?= $s['kunci'] === $k ? ' &check;' : '' ?>
              </div>
            <?php endforeach; ?>
          </div>
        <?php elseif (!empty($s['pedoman'])): ?>
          <div class="small text-muted mt-2"><strong>Pedoman penilaian:</strong> <?= nl2br(clean($s['pedoman'])) ?></div>
        <?php endif; ?>
        <?php if (!$adaPeserta): ?>
        <div class="mt-2 d-flex gap-2">
          <a href="soal.php?id=<?= $ujianId ?>&edit=<?= (int)$s['id'] ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil"></i> Edit</a>
          <form method="POST" onsubmit="return confirm('Hapus soal ini?');">
            <input type="hidden" name="aksi" value="hapus_soal">
            <input type="hidden" name="soal_id" value="<?= (int)$s['id'] ?>">
            <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i> Hapus</button>
          </form>
        </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="col-12 col-xl-5">
    <?php if ($adaPeserta): ?>
      <div class="card p-3 text-muted">Soal tidak bisa ditambah atau diubah karena ujian sudah dikerjakan siswa.</div>
    <?php else: ?>
    <div class="card p-3" id="formSoal">
      <h6 class="fw-bold mb-3"><?= $edit ? 'Edit Soal' : 'Tambah Soal' ?></h6>
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="aksi" value="simpan_soal">
        <input type="hidden" name="soal_id" value="<?= (int)($e['id'] ?? 0) ?>">

        <div class="row g-2 mb-3">
          <div class="col-7">
            <label class="form-label small">Tipe soal</label>
            <select name="tipe" id="tipeSoal" class="form-select">
              <option value="pilihan_ganda" <?= $tipeForm === 'pilihan_ganda' ? 'selected' : '' ?>>Pilihan ganda</option>
              <option value="essay" <?= $tipeForm === 'essay' ? 'selected' : '' ?>>Essay</option>
            </select>
          </div>
          <div class="col-5">
            <label class="form-label small">Bobot</label>
            <input type="number" name="bobot" step="0.5" min="0.5" max="100" class="form-control" value="<?= clean($e['bobot'] ?? 1) ?>">
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label small">Pertanyaan</label>
          <textarea name="pertanyaan" rows="4" class="form-control" required><?= clean($e['pertanyaan'] ?? '') ?></textarea>
        </div>

        <div class="mb-3">
          <label class="form-label small">Gambar soal <span class="text-muted">(opsional; JPG, PNG, GIF, WebP; maks 3 MB)</span></label>
          <?php if (!empty($e['gambar'])): ?>
            <div class="mb-2">
              <img src="<?= clean(ujianUrlGambar($e['gambar'])) ?>" alt="Gambar soal" class="img-fluid rounded border" style="max-height:140px;">
              <div class="form-check mt-1">
                <input type="checkbox" class="form-check-input" name="hapus_gambar" id="hapusGambar" value="1">
                <label class="form-check-label small" for="hapusGambar">Hapus gambar ini</label>
              </div>
            </div>
          <?php endif; ?>
          <input type="file" name="gambar" accept="image/*" class="form-control form-control-sm">
        </div>

        <div id="blokPg">
          <?php foreach (['a', 'b', 'c', 'd', 'e'] as $k): ?>
          <div class="input-group input-group-sm mb-2">
            <span class="input-group-text fw-bold"><?= strtoupper($k) ?></span>
            <input type="text" name="opsi_<?= $k ?>" class="form-control" maxlength="500" value="<?= clean($e["opsi_{$k}"] ?? '') ?>" placeholder="Opsi <?= strtoupper($k) ?><?= in_array($k, ['a', 'b']) ? '' : ' (opsional)' ?>">
          </div>
          <?php endforeach; ?>
          <div class="mb-3">
            <label class="form-label small">Kunci jawaban</label>
            <select name="kunci" class="form-select form-select-sm">
              <?php foreach (['a', 'b', 'c', 'd', 'e'] as $k): ?>
                <option value="<?= $k ?>" <?= ($e['kunci'] ?? 'a') === $k ? 'selected' : '' ?>><?= strtoupper($k) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div id="blokEssay" class="mb-3">
          <label class="form-label small">Pedoman penilaian <span class="text-muted">(opsional, hanya dilihat guru)</span></label>
          <textarea name="pedoman" rows="3" class="form-control" placeholder="Poin jawaban yang benar, untuk memudahkan penilaian."><?= clean($e['pedoman'] ?? '') ?></textarea>
        </div>

        <div class="d-flex gap-2">
          <button class="btn btn-primary"><i class="bi bi-save"></i> <?= $edit ? 'Simpan Perubahan' : 'Tambah Soal' ?></button>
          <?php if ($edit): ?><a href="soal.php?id=<?= $ujianId ?>" class="btn btn-outline-secondary">Batal</a><?php endif; ?>
        </div>
      </form>
    </div>
    <?php endif; ?>
  </div>
</div>

<script>
(function () {
  var tipe = document.getElementById('tipeSoal');
  if (!tipe) return;
  function sync() {
    var pg = tipe.value === 'pilihan_ganda';
    document.getElementById('blokPg').style.display = pg ? '' : 'none';
    document.getElementById('blokEssay').style.display = pg ? 'none' : '';
  }
  tipe.addEventListener('change', sync);
  sync();
})();
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
