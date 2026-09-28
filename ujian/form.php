<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole(['admin', 'guru']);
require_once __DIR__ . '/_lib.php';
$user = currentUser();
$pageTitle = 'Form Ujian';

$id = (int)($_GET['id'] ?? 0);
$ujian = null;
if ($id) {
    $ujian = ujianAmbil($pdo, $id);
    if (!ujianBolehKelola($user, $ujian)) {
        setFlash('error', 'Ujian tidak ditemukan atau bukan milik Anda.');
        redirect('ujian/index.php');
    }
}

$kelasList = $pdo->query("SELECT * FROM kelas ORDER BY tingkat, nama_kelas")->fetchAll();
$mapelList = $pdo->query("SELECT * FROM mata_pelajaran ORDER BY nama_mapel")->fetchAll();

// Nilai awal form: dari data ujian (edit), atau default (baru)
$besok8 = date('Y-m-d\T08:00', strtotime('+1 day'));
$besok12 = date('Y-m-d\T12:00', strtotime('+1 day'));
$f = [
    'judul'        => $ujian['judul'] ?? '',
    'jenis'        => $ujian['jenis'] ?? 'UTS',
    'kelas_id'     => $ujian['kelas_id'] ?? ($kelasList[0]['id'] ?? ''),
    'mapel_id'     => $ujian['mapel_id'] ?? ($mapelList[0]['id'] ?? ''),
    'semester'     => $ujian['semester'] ?? ((int)date('n') >= 7 ? 'Ganjil' : 'Genap'),
    'tahun_ajaran' => $ujian['tahun_ajaran'] ?? ujianTahunAjaran(),
    'durasi_menit' => $ujian['durasi_menit'] ?? 90,
    'waktu_mulai'  => $ujian ? date('Y-m-d\TH:i', strtotime($ujian['waktu_mulai'])) : $besok8,
    'waktu_selesai'=> $ujian ? date('Y-m-d\TH:i', strtotime($ujian['waktu_selesai'])) : $besok12,
    'acak_soal'    => $ujian['acak_soal'] ?? 0,
    'petunjuk'     => $ujian['petunjuk'] ?? '',
];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($f as $k => $v) {
        if (isset($_POST[$k])) $f[$k] = $_POST[$k];
    }
    $f['acak_soal'] = isset($_POST['acak_soal']) ? 1 : 0;

    $mulaiTs   = strtotime(str_replace('T', ' ', $f['waktu_mulai']));
    $selesaiTs = strtotime(str_replace('T', ' ', $f['waktu_selesai']));
    $durasi    = (int)$f['durasi_menit'];

    if (trim($f['judul']) === '') {
        $error = 'Judul ujian wajib diisi.';
    } elseif (!in_array($f['jenis'], ['UTS', 'UAS'], true) || !in_array($f['semester'], ['Ganjil', 'Genap'], true)) {
        $error = 'Jenis ujian atau semester tidak valid.';
    } elseif (!$mulaiTs || !$selesaiTs || $selesaiTs <= $mulaiTs) {
        $error = 'Waktu penutupan harus setelah waktu pembukaan.';
    } elseif ($durasi < 1 || $durasi > 600) {
        $error = 'Durasi harus antara 1 dan 600 menit.';
    } else {
        $mulai   = date('Y-m-d H:i:s', $mulaiTs);
        $selesai = date('Y-m-d H:i:s', $selesaiTs);

        if ($ujian) {
            $stmt = $pdo->prepare("
                UPDATE ujian SET judul=?, jenis=?, kelas_id=?, mapel_id=?, semester=?, tahun_ajaran=?,
                    petunjuk=?, durasi_menit=?, waktu_mulai=?, waktu_selesai=?, acak_soal=?
                WHERE id=?
            ");
            $stmt->execute([trim($f['judul']), $f['jenis'], (int)$f['kelas_id'], (int)$f['mapel_id'], $f['semester'],
                $f['tahun_ajaran'], trim($f['petunjuk']), $durasi, $mulai, $selesai, $f['acak_soal'], $ujian['id']]);
            $newId = $ujian['id'];
            setFlash('success', 'Info ujian diperbarui.');
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO ujian (judul, jenis, kelas_id, mapel_id, guru_id, guru_nama, semester, tahun_ajaran,
                    petunjuk, durasi_menit, waktu_mulai, waktu_selesai, acak_soal)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)
            ");
            $stmt->execute([trim($f['judul']), $f['jenis'], (int)$f['kelas_id'], (int)$f['mapel_id'], $user['id'], $user['nama'],
                $f['semester'], $f['tahun_ajaran'], trim($f['petunjuk']), $durasi, $mulai, $selesai, $f['acak_soal']]);
            $newId = $pdo->lastInsertId();
            setFlash('success', 'Ujian dibuat. Sekarang tambahkan soalnya.');
        }
        redirect("ujian/soal.php?id={$newId}");
    }
}

include __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h4 class="fw-bold mb-0"><i class="bi bi-pencil-square me-2"></i><?= $ujian ? 'Edit Info Ujian' : 'Buat Ujian Baru' ?></h4>
  <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Kembali</a>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?= clean($error) ?></div><?php endif; ?>

<div class="card p-3">
  <form method="POST" class="row g-3">
    <div class="col-12 col-md-8">
      <label class="form-label">Judul Ujian</label>
      <input type="text" name="judul" class="form-control" value="<?= clean($f['judul']) ?>" placeholder="Contoh: UTS Matematika Semester Ganjil" required>
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label">Jenis</label>
      <select name="jenis" class="form-select">
        <option value="UTS" <?= $f['jenis'] === 'UTS' ? 'selected' : '' ?>>UTS</option>
        <option value="UAS" <?= $f['jenis'] === 'UAS' ? 'selected' : '' ?>>UAS</option>
      </select>
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label">Durasi (menit)</label>
      <input type="number" name="durasi_menit" min="1" max="600" class="form-control" value="<?= (int)$f['durasi_menit'] ?>">
    </div>

    <div class="col-12 col-md-4">
      <label class="form-label">Kelas</label>
      <select name="kelas_id" class="form-select">
        <?php foreach ($kelasList as $k): ?>
          <option value="<?= $k['id'] ?>" <?= (string)$f['kelas_id'] === (string)$k['id'] ? 'selected' : '' ?>><?= clean($k['nama_kelas']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-12 col-md-4">
      <label class="form-label">Mata Pelajaran</label>
      <select name="mapel_id" class="form-select">
        <?php foreach ($mapelList as $m): ?>
          <option value="<?= $m['id'] ?>" <?= (string)$f['mapel_id'] === (string)$m['id'] ? 'selected' : '' ?>><?= clean($m['nama_mapel']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label">Semester</label>
      <select name="semester" class="form-select">
        <option value="Ganjil" <?= $f['semester'] === 'Ganjil' ? 'selected' : '' ?>>Ganjil</option>
        <option value="Genap" <?= $f['semester'] === 'Genap' ? 'selected' : '' ?>>Genap</option>
      </select>
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label">Tahun Ajaran</label>
      <select name="tahun_ajaran" class="form-select">
        <?php foreach (ujianOpsiTahun() as $t): ?>
          <option value="<?= $t ?>" <?= $f['tahun_ajaran'] === $t ? 'selected' : '' ?>><?= $t ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-12 col-md-6">
      <label class="form-label">Dibuka pada</label>
      <input type="datetime-local" name="waktu_mulai" class="form-control" value="<?= clean($f['waktu_mulai']) ?>" required>
    </div>
    <div class="col-12 col-md-6">
      <label class="form-label">Ditutup pada</label>
      <input type="datetime-local" name="waktu_selesai" class="form-control" value="<?= clean($f['waktu_selesai']) ?>" required>
      <div class="form-text">Siswa yang mulai mengerjakan mendapat waktu sesuai durasi, tapi tidak melewati waktu penutupan ini.</div>
    </div>

    <div class="col-12">
      <label class="form-label">Petunjuk untuk siswa <span class="text-muted">(opsional)</span></label>
      <textarea name="petunjuk" rows="3" class="form-control" placeholder="Contoh: Kerjakan sendiri. Jawaban tersimpan otomatis."><?= clean($f['petunjuk']) ?></textarea>
    </div>
    <div class="col-12">
      <div class="form-check">
        <input class="form-check-input" type="checkbox" name="acak_soal" id="acak" <?= $f['acak_soal'] ? 'checked' : '' ?>>
        <label class="form-check-label" for="acak">Acak urutan soal untuk tiap siswa</label>
      </div>
    </div>

    <div class="col-12">
      <button class="btn btn-primary"><i class="bi bi-save"></i> <?= $ujian ? 'Simpan Perubahan' : 'Simpan &amp; Lanjut ke Soal' ?></button>
    </div>
  </form>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
