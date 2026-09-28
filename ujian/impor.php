<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole(['admin', 'guru']);
require_once __DIR__ . '/_lib.php';
require_once __DIR__ . '/_docx.php';
$user = currentUser();
$pageTitle = 'Impor Soal dari Word';

$ujianId = (int)($_GET['id'] ?? 0);
$ujian = ujianAmbil($pdo, $ujianId);
if (!ujianBolehKelola($user, $ujian)) {
    setFlash('error', 'Ujian tidak ditemukan atau bukan milik Anda.');
    redirect('ujian/index.php');
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM ujian_peserta WHERE ujian_id = ?");
$stmt->execute([$ujianId]);
if ((int)$stmt->fetchColumn() > 0) {
    setFlash('error', 'Soal terkunci karena sudah ada siswa yang mengerjakan ujian ini.');
    redirect("ujian/soal.php?id={$ujianId}");
}

$back = "ujian/impor.php?id={$ujianId}";
$sesi = $_SESSION['ujian_import'] ?? null;
if ($sesi && (int)$sesi['ujian_id'] !== $ujianId) $sesi = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        setFlash('error', 'Ukuran file melebihi batas upload server (post_max_size).');
        redirect($back);
    }
    $aksi = $_POST['aksi'] ?? '';

    /* ---- Unggah & urai file Word ---- */
    if ($aksi === 'unggah') {
        $f = $_FILES['berkas'] ?? null;
        if (!$f || $f['error'] !== UPLOAD_ERR_OK) {
            setFlash('error', 'Pilih file .docx terlebih dahulu (atau ukurannya melebihi batas upload server).');
            redirect($back);
        }
        if (strtolower(pathinfo($f['name'], PATHINFO_EXTENSION)) !== 'docx') {
            setFlash('error', 'Hanya file Word berformat .docx yang didukung. Simpan ulang file .doc lama sebagai .docx.');
            redirect($back);
        }
        if ($f['size'] > 10 * 1024 * 1024) {
            setFlash('error', 'Ukuran file Word maksimal 10 MB.');
            redirect($back);
        }

        ujianBersihkanImpor(); // buang hasil impor sebelumnya yang belum dikonfirmasi
        $hasil = ujianParseDocx($f['tmp_name']);
        if (isset($hasil['error'])) {
            setFlash('error', $hasil['error']);
            redirect($back);
        }
        if (empty($hasil['soal'])) {
            setFlash('error', 'Tidak ada soal yang terdeteksi. Pastikan setiap soal diawali nomor yang diketik manual, misalnya "1." atau "1)". Lihat contoh format di halaman ini.');
            redirect($back);
        }

        $_SESSION['ujian_import'] = [
            'ujian_id'   => $ujianId,
            'nama_file'  => $f['name'],
            'soal'       => $hasil['soal'],
            'peringatan' => $hasil['peringatan'],
        ];
        redirect($back);
    }

    /* ---- Konfirmasi: simpan soal ke database ---- */
    if ($aksi === 'konfirmasi') {
        if (!$sesi) {
            setFlash('error', 'Data impor sudah kedaluwarsa. Unggah ulang file Word Anda.');
            redirect($back);
        }
        $valid = array_values(array_filter($sesi['soal'], fn($s) => empty($s['error'])));
        if (empty($valid)) {
            setFlash('error', 'Tidak ada soal valid untuk diimpor.');
            redirect($back);
        }

        try {
            $pdo->beginTransaction();

            if (!empty($_POST['ganti'])) { // ganti semua soal yang sudah ada
                $g = $pdo->prepare("SELECT gambar FROM ujian_soal WHERE ujian_id = ? AND gambar IS NOT NULL");
                $g->execute([$ujianId]);
                $gambarLama = $g->fetchAll(PDO::FETCH_COLUMN);
                $pdo->prepare("DELETE FROM ujian_soal WHERE ujian_id = ?")->execute([$ujianId]);
            } else {
                $gambarLama = [];
            }

            $stmt = $pdo->prepare("SELECT COALESCE(MAX(urutan),0) FROM ujian_soal WHERE ujian_id = ?");
            $stmt->execute([$ujianId]);
            $urutan = (int)$stmt->fetchColumn();

            $ins = $pdo->prepare("
                INSERT INTO ujian_soal (ujian_id, tipe, pertanyaan, gambar, opsi_a, opsi_b, opsi_c, opsi_d, opsi_e, kunci, pedoman, bobot, urutan)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)
            ");
            foreach ($valid as $s) {
                $urutan++;
                $ins->execute([
                    $ujianId, $s['tipe'], $s['pertanyaan'], $s['gambar'],
                    $s['opsi']['a'], $s['opsi']['b'], $s['opsi']['c'], $s['opsi']['d'], $s['opsi']['e'],
                    $s['kunci'], $s['pedoman'] !== '' ? $s['pedoman'] : null, $s['bobot'], $urutan,
                ]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            setFlash('error', 'Gagal menyimpan soal: kolom "gambar" mungkin belum ada di tabel ujian_soal. Jalankan update_gambar.sql.');
            redirect($back);
        }

        foreach ($gambarLama as $g) ujianHapusGambar($g);
        unset($_SESSION['ujian_import']); // gambar sudah dipakai soal, jangan dihapus
        setFlash('success', count($valid) . ' soal berhasil diimpor dari Word.');
        redirect("ujian/soal.php?id={$ujianId}");
    }

    if ($aksi === 'batal') {
        ujianBersihkanImpor();
        setFlash('info', 'Impor dibatalkan.');
        redirect($back);
    }
}

include __DIR__ . '/../includes/header.php';
$hurufOpsi = ['a', 'b', 'c', 'd', 'e'];
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="fw-bold mb-0"><i class="bi bi-file-earmark-word me-2"></i>Impor Soal dari Word</h4>
    <div class="small text-muted"><?= clean($ujian['judul']) ?> &middot; <?= clean($ujian['nama_mapel']) ?> &middot; Kelas <?= clean($ujian['nama_kelas']) ?></div>
  </div>
  <a href="soal.php?id=<?= $ujianId ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Kembali ke Soal</a>
</div>

<?php if (!$sesi): ?>
<!-- ============ LANGKAH 1: unggah ============ -->
<div class="row g-3">
  <div class="col-12 col-lg-5">
    <div class="card p-3">
      <h6 class="fw-bold mb-3">1. Unggah file Word</h6>
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="aksi" value="unggah">
        <input type="file" name="berkas" accept=".docx" class="form-control mb-3" required>
        <button class="btn btn-primary w-100"><i class="bi bi-upload"></i> Unggah &amp; Periksa</button>
      </form>
      <hr>
      <a href="template_soal_ujian.docx" download class="btn btn-outline-secondary btn-sm w-100"><i class="bi bi-download"></i> Unduh Template Word (dengan contoh)</a>
      <div class="small text-muted mt-2">Soal belum disimpan sampai Anda mengonfirmasi di langkah berikutnya, jadi aman untuk mencoba.</div>
    </div>
  </div>

  <div class="col-12 col-lg-7">
    <div class="card p-3">
      <h6 class="fw-bold mb-2">Format penulisan di Word</h6>
<pre class="small bg-light border rounded p-3 mb-3" style="white-space:pre-wrap;">1. Ibu kota Indonesia adalah ...
A. Bandung
B. Jakarta
C. Surabaya
D. Medan
Kunci: B
Bobot: 2

2. [ESSAY] Jelaskan proses fotosintesis!
Pedoman: Cahaya, klorofil, CO2 dan air menjadi glukosa dan oksigen.
Bobot: 5</pre>
      <ul class="small mb-0">
        <li><strong>Ketik nomor soal secara manual</strong> ("1." atau "1)"). Penomoran otomatis Word tidak terbaca.</li>
        <li>Pilihan ganda: opsi A sampai E (minimal A dan B) dan baris <code>Kunci: B</code>.</li>
        <li>Essay: tulis <code>[ESSAY]</code> di soal, atau cukup tanpa opsi. <code>Pedoman:</code> dan <code>Bobot:</code> opsional (bobot bawaan 1).</li>
        <li><strong>Gambar:</strong> sisipkan di paragraf soal, sebelum opsi A. Satu gambar per soal (JPG/PNG/GIF/WebP, maks 3 MB). Gambar di opsi jawaban tidak dibaca.</li>
        <li>Rumus Word (equation) dibaca sebagai teks biasa. Untuk rumus rumit, tempel sebagai <strong>gambar</strong>.</li>
        <li>Teks sebelum soal nomor 1 (judul, petunjuk) diabaikan. Hanya format <code>.docx</code>.</li>
      </ul>
    </div>
  </div>
</div>

<?php else:
    $soalList   = $sesi['soal'];
    $jmlValid   = count(array_filter($soalList, fn($s) => empty($s['error'])));
    $jmlBermasalah = count($soalList) - $jmlValid;
?>
<!-- ============ LANGKAH 2: pratinjau ============ -->
<div class="card p-3 mb-3">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
      <div class="fw-bold">Pratinjau: <?= clean($sesi['nama_file']) ?></div>
      <div class="small text-muted">
        <span class="text-success fw-semibold"><?= $jmlValid ?> soal siap diimpor</span>
        <?php if ($jmlBermasalah): ?> &middot; <span class="text-danger fw-semibold"><?= $jmlBermasalah ?> soal bermasalah (akan dilewati)</span><?php endif; ?>
      </div>
    </div>
    <div class="d-flex gap-2 flex-wrap align-items-center">
      <form method="POST" class="d-flex gap-2 align-items-center flex-wrap">
        <input type="hidden" name="aksi" value="konfirmasi">
        <div class="form-check mb-0">
          <input class="form-check-input" type="checkbox" name="ganti" id="ganti" value="1">
          <label class="form-check-label small" for="ganti">Ganti semua soal yang sudah ada</label>
        </div>
        <button class="btn btn-success" <?= $jmlValid ? '' : 'disabled' ?>><i class="bi bi-check2-circle"></i> Impor <?= $jmlValid ?> Soal</button>
      </form>
      <form method="POST">
        <input type="hidden" name="aksi" value="batal">
        <button class="btn btn-outline-secondary">Batal</button>
      </form>
    </div>
  </div>

  <?php if (!empty($sesi['peringatan'])): ?>
    <div class="alert alert-warning small mt-3 mb-0">
      <strong>Perhatian:</strong>
      <ul class="mb-0">
        <?php foreach ($sesi['peringatan'] as $w): ?><li><?= clean($w) ?></li><?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>
</div>

<?php foreach ($soalList as $i => $s): ?>
<div class="card p-3 mb-2 <?= !empty($s['error']) ? 'border-danger' : '' ?>">
  <div class="d-flex justify-content-between gap-2 flex-wrap">
    <div class="fw-semibold"><?= (int)$s['no'] ?>. <?= nl2br(clean($s['pertanyaan'])) ?></div>
    <div class="text-nowrap">
      <?php if (!empty($s['error'])): ?>
        <span class="badge-soft bad">Dilewati</span>
      <?php else: ?>
        <span class="badge-soft <?= $s['tipe'] === 'essay' ? 'warn' : 'info' ?>"><?= $s['tipe'] === 'essay' ? 'Essay' : 'Pilihan ganda' ?></span>
        <span class="badge-soft brand">bobot <?= rtrim(rtrim(number_format($s['bobot'], 2), '0'), '.') ?></span>
      <?php endif; ?>
    </div>
  </div>

  <?php if (!empty($s['error'])): ?>
    <div class="small text-danger mt-1"><i class="bi bi-exclamation-circle-fill"></i> <?= clean($s['error']) ?></div>
  <?php endif; ?>

  <?php if (!empty($s['gambar'])): ?>
    <img src="<?= clean(ujianUrlGambar($s['gambar'])) ?>" alt="Gambar soal" class="img-fluid rounded border mt-2" style="max-height:180px;">
  <?php endif; ?>

  <?php if ($s['tipe'] === 'pilihan_ganda'): ?>
    <div class="small mt-2">
      <?php foreach ($hurufOpsi as $k): if ($s['opsi'][$k] === null) continue; ?>
        <div class="<?= $s['kunci'] === $k ? 'text-success fw-bold' : 'text-muted' ?>">
          <?= strtoupper($k) ?>. <?= clean($s['opsi'][$k]) ?><?= $s['kunci'] === $k ? ' &check;' : '' ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php elseif (!empty($s['pedoman'])): ?>
    <div class="small text-muted mt-2"><strong>Pedoman:</strong> <?= nl2br(clean($s['pedoman'])) ?></div>
  <?php endif; ?>
</div>
<?php endforeach; ?>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
