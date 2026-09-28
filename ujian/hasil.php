<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole(['admin', 'guru']);
require_once __DIR__ . '/_lib.php';
$user = currentUser();
$pageTitle = 'Hasil Ujian';

$ujianId = (int)($_GET['id'] ?? 0);
$ujian = ujianAmbil($pdo, $ujianId);
if (!ujianBolehKelola($user, $ujian)) {
    setFlash('error', 'Ujian tidak ditemukan atau bukan milik Anda.');
    redirect('ujian/index.php');
}

// Akhiri otomatis siswa yang waktunya sudah habis tapi belum menekan "Selesai"
ujianFinalisasiKedaluwarsa($pdo, $ujian);

$pesertaId = (int)($_GET['peserta'] ?? 0);
$back = "ujian/hasil.php?id={$ujianId}";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';

    /* ---- Simpan skor essay seorang siswa ---- */
    if ($aksi === 'nilai_essay') {
        $pid = (int)($_POST['peserta_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT id FROM ujian_peserta WHERE id = ? AND ujian_id = ? AND status = 'Selesai'");
        $stmt->execute([$pid, $ujianId]);
        if ($stmt->fetchColumn()) {
            $stmt = $pdo->prepare("SELECT id, bobot FROM ujian_soal WHERE ujian_id = ? AND tipe = 'essay'");
            $stmt->execute([$ujianId]);
            $bobotMap = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

            $upd = $pdo->prepare("UPDATE ujian_jawaban SET skor = ? WHERE peserta_id = ? AND soal_id = ?");
            foreach (($_POST['skor'] ?? []) as $soalId => $val) {
                if (!isset($bobotMap[$soalId])) continue;
                $val = trim((string)$val);
                $skor = $val === '' ? null : max(0, min((float)$bobotMap[$soalId], (float)$val));
                $upd->execute([$skor, $pid, (int)$soalId]);
            }
            ujianHitungUlang($pdo, $pid);
            setFlash('success', 'Penilaian essay disimpan.');
        } else {
            setFlash('error', 'Data peserta tidak valid.');
        }
        redirect($back . "&peserta={$pid}");
    }

    /* ---- Kirim nilai ke tabel nilai (rapor) ---- */
    if ($aksi === 'kirim_rapor') {
        $kolom = $ujian['jenis'] === 'UTS' ? 'nilai_uts' : 'nilai_uas'; // whitelist, aman untuk disisipkan ke SQL
        $stmt = $pdo->prepare("SELECT siswa_id, nilai_akhir FROM ujian_peserta WHERE ujian_id = ? AND status = 'Selesai' AND dinilai = 1");
        $stmt->execute([$ujianId]);
        $baris = $stmt->fetchAll();

        $ins = $pdo->prepare("
            INSERT INTO nilai (siswa_id, mapel_id, semester, tahun_ajaran, {$kolom}) VALUES (?,?,?,?,?)
            ON DUPLICATE KEY UPDATE {$kolom} = VALUES({$kolom})
        ");
        $ambil = $pdo->prepare("SELECT nilai_tugas, nilai_uts, nilai_uas FROM nilai WHERE siswa_id = ? AND mapel_id = ? AND semester = ? AND tahun_ajaran = ?");
        $upAkhir = $pdo->prepare("UPDATE nilai SET nilai_akhir = ?, predikat = ? WHERE siswa_id = ? AND mapel_id = ? AND semester = ? AND tahun_ajaran = ?");

        foreach ($baris as $b) {
            $key = [$b['siswa_id'], $ujian['mapel_id'], $ujian['semester'], $ujian['tahun_ajaran']];
            $ins->execute(array_merge($key, [round((float)$b['nilai_akhir'], 1)]));
            $ambil->execute($key);
            $n = $ambil->fetch();
            // Bobot sama seperti halaman Input Nilai: tugas 30%, UTS 30%, UAS 40%
            $akhir = round(($n['nilai_tugas'] * 0.3) + ($n['nilai_uts'] * 0.3) + ($n['nilai_uas'] * 0.4), 1);
            $upAkhir->execute(array_merge([$akhir, hitungPredikat($akhir)], $key));
        }
        setFlash('success', count($baris) . " nilai {$ujian['jenis']} dikirim ke Nilai / Rapor.");
        redirect($back);
    }
}

include __DIR__ . '/../includes/header.php';

/* ==================== DETAIL: periksa jawaban satu siswa ==================== */
if ($pesertaId) {
    $stmt = $pdo->prepare("
        SELECT p.*, s.nama_lengkap, s.nis FROM ujian_peserta p
        JOIN siswa s ON s.id = p.siswa_id
        WHERE p.id = ? AND p.ujian_id = ?
    ");
    $stmt->execute([$pesertaId, $ujianId]);
    $p = $stmt->fetch();

    if (!$p || $p['status'] !== 'Selesai') {
        echo "<div class='card p-4 text-center text-muted'>Data peserta tidak ditemukan atau belum selesai mengerjakan.</div>";
        echo "<a href='hasil.php?id={$ujianId}' class='btn btn-outline-secondary btn-sm mt-3'>Kembali</a>";
        include __DIR__ . '/../includes/footer.php';
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT s.*, j.jawaban, j.skor FROM ujian_soal s
        LEFT JOIN ujian_jawaban j ON j.soal_id = s.id AND j.peserta_id = ?
        WHERE s.ujian_id = ? ORDER BY s.urutan, s.id
    ");
    $stmt->execute([$pesertaId, $ujianId]);
    $soalList = $stmt->fetchAll();
    ?>
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
      <div>
        <h4 class="fw-bold mb-0"><?= clean($p['nama_lengkap']) ?></h4>
        <div class="small text-muted">NIS <?= clean($p['nis']) ?> &middot; <?= clean($ujian['judul']) ?></div>
      </div>
      <a href="hasil.php?id=<?= $ujianId ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Rekap</a>
    </div>

    <div class="row g-3 mb-3">
      <div class="col-4"><div class="card p-3 text-center"><div class="small text-muted">Pilihan ganda</div><div class="fs-4 fw-bold"><?= number_format($p['nilai_pg'], 1) ?></div></div></div>
      <div class="col-4"><div class="card p-3 text-center"><div class="small text-muted">Essay</div><div class="fs-4 fw-bold"><?= number_format($p['nilai_essay'], 1) ?></div></div></div>
      <div class="col-4"><div class="card p-3 text-center"><div class="small text-muted">Nilai akhir<?= $p['dinilai'] ? '' : ' (sementara)' ?></div><div class="fs-4 fw-bold"><?= number_format($p['nilai_akhir'], 1) ?></div></div></div>
    </div>

    <form method="POST">
      <input type="hidden" name="aksi" value="nilai_essay">
      <input type="hidden" name="peserta_id" value="<?= (int)$p['id'] ?>">

      <?php foreach ($soalList as $i => $s): ?>
      <div class="card p-3 mb-3">
        <div class="d-flex justify-content-between gap-2">
          <div class="fw-semibold"><?= $i + 1 ?>. <?= nl2br(clean($s['pertanyaan'])) ?></div>
          <span class="badge-soft brand text-nowrap">bobot <?= rtrim(rtrim(number_format($s['bobot'], 2), '0'), '.') ?></span>
        </div>
        <?php if (!empty($s['gambar'])): ?>
          <img src="<?= clean(ujianUrlGambar($s['gambar'])) ?>" alt="Gambar soal" class="img-fluid rounded border mt-2" style="max-height:200px;">
        <?php endif; ?>

        <?php if ($s['tipe'] === 'pilihan_ganda'): ?>
          <div class="mt-2 small">
            Jawaban siswa: <strong><?= $s['jawaban'] !== '' && $s['jawaban'] !== null ? strtoupper(clean($s['jawaban'])) : '(kosong)' ?></strong>
            &middot; Kunci: <strong><?= strtoupper(clean($s['kunci'])) ?></strong>
            &middot; Skor: <strong><?= number_format((float)$s['skor'], 1) ?></strong>
            <?= ((float)$s['skor'] > 0) ? '<span class="text-success">benar</span>' : '<span class="text-danger">salah</span>' ?>
          </div>
        <?php else: ?>
          <div class="border rounded p-2 mt-2 bg-light" style="white-space:pre-wrap;"><?= trim((string)$s['jawaban']) !== '' ? clean($s['jawaban']) : '<span class="text-muted">(tidak dijawab)</span>' ?></div>
          <?php if (!empty($s['pedoman'])): ?>
            <div class="small text-muted mt-2"><strong>Pedoman:</strong> <?= nl2br(clean($s['pedoman'])) ?></div>
          <?php endif; ?>
          <div class="d-flex align-items-center gap-2 mt-2">
            <label class="small mb-0">Skor (0&ndash;<?= rtrim(rtrim(number_format($s['bobot'], 2), '0'), '.') ?>):</label>
            <input type="number" name="skor[<?= (int)$s['id'] ?>]" step="0.5" min="0" max="<?= clean($s['bobot']) ?>" value="<?= $s['skor'] !== null ? clean($s['skor']) : '' ?>" class="form-control form-control-sm" style="width:100px;">
            <?php if ($s['skor'] === null): ?><span class="badge-soft warn">belum dinilai</span><?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>

      <button class="btn btn-primary mb-4"><i class="bi bi-save"></i> Simpan Penilaian Essay</button>
    </form>
    <?php
    include __DIR__ . '/../includes/footer.php';
    exit;
}

/* ==================== REKAP: seluruh siswa di kelas ==================== */
$stmt = $pdo->prepare("
    SELECT s.nis, s.nama_lengkap, p.id AS peserta_id, p.status, p.waktu_mulai, p.waktu_selesai,
           p.nilai_pg, p.nilai_essay, p.nilai_akhir, p.dinilai
    FROM siswa s
    LEFT JOIN ujian_peserta p ON p.siswa_id = s.id AND p.ujian_id = ?
    WHERE s.kelas_id = ? AND s.status = 'Aktif'
    ORDER BY s.nama_lengkap
");
$stmt->execute([$ujianId, $ujian['kelas_id']]);
$baris = $stmt->fetchAll();

$belumMulai = 0; $berlangsung = 0; $selesai = 0; $perluDinilai = 0; $jumlahNilai = 0; $totalNilai = 0;
foreach ($baris as $b) {
    if (!$b['peserta_id']) $belumMulai++;
    elseif ($b['status'] === 'Berlangsung') $berlangsung++;
    else {
        $selesai++;
        if (!$b['dinilai']) $perluDinilai++;
        else { $jumlahNilai++; $totalNilai += (float)$b['nilai_akhir']; }
    }
}
$rata = $jumlahNilai ? $totalNilai / $jumlahNilai : 0;
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="fw-bold mb-0"><i class="bi bi-bar-chart-fill me-2"></i>Hasil: <?= clean($ujian['judul']) ?></h4>
    <div class="small text-muted"><?= ujianBadgeJenis($ujian['jenis']) ?> <?= clean($ujian['nama_mapel']) ?> &middot; Kelas <?= clean($ujian['nama_kelas']) ?></div>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <a href="soal.php?id=<?= $ujianId ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-list-check"></i> Soal</a>
    <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Daftar Ujian</a>
    <form method="POST" onsubmit="return confirm('Kirim nilai <?= clean($ujian['jenis']) ?> semua siswa yang sudah selesai dinilai ke Nilai / Rapor? Nilai <?= clean($ujian['jenis']) ?> lama di rapor akan ditimpa.');">
      <input type="hidden" name="aksi" value="kirim_rapor">
      <button class="btn btn-success btn-sm"><i class="bi bi-box-arrow-up-right"></i> Kirim Nilai ke Rapor</button>
    </form>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-6 col-md-3"><div class="card p-3"><div class="small text-muted">Belum mulai</div><div class="fs-3 fw-bold"><?= $belumMulai ?></div></div></div>
  <div class="col-6 col-md-3"><div class="card p-3"><div class="small text-muted">Sedang mengerjakan</div><div class="fs-3 fw-bold"><?= $berlangsung ?></div></div></div>
  <div class="col-6 col-md-3"><div class="card p-3"><div class="small text-muted">Perlu dinilai (essay)</div><div class="fs-3 fw-bold"><?= $perluDinilai ?></div></div></div>
  <div class="col-6 col-md-3"><div class="card p-3"><div class="small text-muted">Rata-rata nilai</div><div class="fs-3 fw-bold"><?= $jumlahNilai ? number_format($rata, 1) : '-' ?></div></div></div>
</div>

<div class="card p-3">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr><th>#</th><th>NIS</th><th>Nama Siswa</th><th>Status</th><th class="text-center">PG</th><th class="text-center">Essay</th><th class="text-center">Nilai Akhir</th><th class="text-end">Aksi</th></tr>
      </thead>
      <tbody>
        <?php if (empty($baris)): ?><tr><td colspan="8" class="text-center text-muted py-4">Tidak ada siswa aktif di kelas ini.</td></tr><?php endif; ?>
        <?php foreach ($baris as $i => $b): ?>
        <tr>
          <td><?= $i + 1 ?></td>
          <td><?= clean($b['nis']) ?></td>
          <td class="fw-semibold"><?= clean($b['nama_lengkap']) ?></td>
          <td>
            <?php if (!$b['peserta_id']): ?><span class="badge-soft bad">Belum mulai</span>
            <?php elseif ($b['status'] === 'Berlangsung'): ?><span class="badge-soft info">Mengerjakan</span>
            <?php elseif (!$b['dinilai']): ?><span class="badge-soft warn">Perlu dinilai</span>
            <?php else: ?><span class="badge-soft good">Dinilai</span><?php endif; ?>
          </td>
          <?php if ($b['peserta_id'] && $b['status'] === 'Selesai'): ?>
            <td class="text-center"><?= number_format($b['nilai_pg'], 1) ?></td>
            <td class="text-center"><?= number_format($b['nilai_essay'], 1) ?></td>
            <td class="text-center fw-bold"><?= number_format($b['nilai_akhir'], 1) ?><?= $b['dinilai'] ? '' : '*' ?></td>
            <td class="text-end"><a href="hasil.php?id=<?= $ujianId ?>&peserta=<?= (int)$b['peserta_id'] ?>" class="btn btn-outline-primary btn-sm">Periksa</a></td>
          <?php else: ?>
            <td class="text-center text-muted">-</td><td class="text-center text-muted">-</td><td class="text-center text-muted">-</td><td></td>
          <?php endif; ?>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="small text-muted mt-2">* Nilai sementara: masih ada essay yang belum diberi skor.</div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
