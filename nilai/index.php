<?php
require_once __DIR__ . '/../includes/auth.php';
$user = currentUser();
$pageTitle = 'Nilai / Rapor';

// Tahun ajaran BERJALAN dihitung otomatis dari tanggal hari ini, dipakai
// sebagai default supaya tidak perlu diset manual saat pertama buka halaman.
// Asumsi kalender akademik umum: tahun ajaran baru mulai Juli.
function tahunAjaranSekarang() {
    $bulan = (int)date('n');
    $tahun = (int)date('Y');
    $tahunAwal = $bulan >= 7 ? $tahun : $tahun - 1;
    return $tahunAwal . '/' . ($tahunAwal + 1);
}

// Daftar pilihan tahun ajaran: 5 tahun ke belakang s/d 5 tahun ke depan dari
// tahun ajaran berjalan, dipakai untuk mengisi dropdown (bukan isian bebas)
// supaya nilai yang dipilih selalu format yang valid.
function opsiTahunAjaran($rentang = 5) {
    $bulan = (int)date('n');
    $tahun = (int)date('Y');
    $tahunAwalSekarang = $bulan >= 7 ? $tahun : $tahun - 1;
    $opsi = [];
    for ($i = -$rentang; $i <= $rentang; $i++) {
        $awal = $tahunAwalSekarang + $i;
        $opsi[] = $awal . '/' . ($awal + 1);
    }
    return $opsi;
}

/* ====== Style responsif bersama, dipakai di ketiga mode halaman ini ======
   Teknik: tabel tetap SATU markup (tidak diduplikasi), hanya direstyle jadi
   kartu di layar HP lewat CSS (thead disembunyikan, setiap <td> diberi label
   dari atribut data-label). Aman dipakai walau ada <input>/tombol di dalam
   tabel karena elemen JS-nya tidak berubah, cuma tampilannya. */
function styleResponsifNilai() {
    ?>
    <style>
    @media (max-width: 767.98px) {
      .gv-mobile-header {
        flex-direction: column;
        align-items: stretch !important;
        gap: 10px;
      }
      .gv-mobile-header .btn,
      .gv-mobile-header a.btn {
        width: 100%;
        text-align: center;
      }

      .table-responsive-mobile table thead {
        display: none;
      }
      .table-responsive-mobile table,
      .table-responsive-mobile table tbody,
      .table-responsive-mobile table tr,
      .table-responsive-mobile table td {
        display: block;
        width: 100%;
      }
      .table-responsive-mobile table tr {
        border: 1px solid #e6e6e6;
        border-radius: 10px;
        margin-bottom: 10px;
        padding: 4px 0;
        background: #fff;
      }
      .table-responsive-mobile table td {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 7px 12px !important;
        border: none !important;
        text-align: right;
      }
      .table-responsive-mobile table td:before {
        content: attr(data-label);
        font-weight: 600;
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: .02em;
        color: #6c757d;
        text-align: left;
        flex-shrink: 0;
      }
      .table-responsive-mobile table td.td-nowrap-mobile {
        white-space: normal;
      }
      .table-responsive-mobile table td input.form-control,
      .table-responsive-mobile table td select.form-select {
        max-width: 120px;
        margin-left: auto;
      }
      .table-responsive-mobile table td .d-flex.align-items-center.gap-1 {
        justify-content: flex-end;
        flex-wrap: wrap;
      }

      #toastTarik {
        left: 12px;
        right: 12px;
        min-width: 0;
        max-width: none;
      }

      .card.p-3 {
        padding: 14px !important;
      }
    }
    </style>
    <?php
}

// ==== Mode Siswa: lihat nilai sendiri ====
if ($user['role'] === 'siswa') {
    $stmt = $pdo->prepare("
        SELECT n.*, m.nama_mapel FROM nilai n
        JOIN mata_pelajaran m ON n.mapel_id = m.id
        WHERE n.siswa_id = ? ORDER BY n.tahun_ajaran DESC, n.semester, m.nama_mapel
    ");
    $stmt->execute([$user['siswa_id']]);
    $nilaiSaya = $stmt->fetchAll();

    // Rincian tugas e-learning yang sudah dinilai, per mapel
    $stmt = $pdo->prepare("
        SELECT m.nama_mapel, et.judul, et.deadline, et.semester, et.tahun_ajaran, ep.nilai, ep.status, ep.feedback_guru, ep.tanggal_kumpul
        FROM elearning_pengumpulan ep
        JOIN elearning_tugas et ON ep.tugas_id = et.id
        JOIN mata_pelajaran m ON et.mapel_id = m.id
        WHERE ep.siswa_id = ?
        ORDER BY ep.tanggal_kumpul DESC
    ");
    $stmt->execute([$user['siswa_id']]);
    $tugasSaya = $stmt->fetchAll();

    include __DIR__ . '/../includes/header.php';
    styleResponsifNilai();
    ?>
    <div class="d-flex justify-content-between align-items-center mb-3 gv-mobile-header">
      <h4 class="fw-bold mb-0"><i class="bi bi-clipboard-data-fill me-2"></i>Nilai Saya</h4>
      <a href="rapor.php?siswa_id=<?= $user['siswa_id'] ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-printer"></i> Lihat Rapor</a>
    </div>
    <div class="card p-3 mb-4">
      <div class="table-responsive table-responsive-mobile">
        <table class="table table-hover">
          <thead class="table-light"><tr><th>Mapel</th><th>Semester</th><th>Tahun Ajaran</th><th>Tugas</th><th>UTS</th><th>UAS</th><th>Nilai Akhir</th><th>Predikat</th></tr></thead>
          <tbody>
            <?php if (empty($nilaiSaya)): ?><tr><td colspan="8" class="text-center text-muted py-4">Belum ada nilai.</td></tr><?php endif; ?>
            <?php foreach ($nilaiSaya as $n): ?>
            <tr>
              <td data-label="Mapel" class="fw-semibold"><?= clean($n['nama_mapel']) ?></td>
              <td data-label="Semester"><?= clean($n['semester']) ?></td>
              <td data-label="Tahun Ajaran"><?= clean($n['tahun_ajaran']) ?></td>
              <td data-label="Tugas"><?= number_format($n['nilai_tugas'],1) ?></td>
              <td data-label="UTS"><?= number_format($n['nilai_uts'],1) ?></td>
              <td data-label="UAS"><?= number_format($n['nilai_uas'],1) ?></td>
              <td data-label="Nilai Akhir" class="fw-bold"><?= number_format($n['nilai_akhir'],1) ?></td>
              <td data-label="Predikat"><span class="badge-soft brand"><?= clean($n['predikat']) ?></span></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <h5 class="fw-bold mb-3"><i class="bi bi-journal-check me-2"></i>Rincian Nilai Tugas E-Learning</h5>
    <div class="card p-3">
      <p class="text-muted small mb-3">Daftar semua tugas yang sudah diberi nilai oleh guru. Rata-rata nilai di sini yang menjadi dasar kolom "Tugas" pada nilai akhir per mapel di atas.</p>
      <div class="table-responsive table-responsive-mobile">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light"><tr><th>Mapel</th><th>Judul Tugas</th><th>Semester</th><th>Tanggal Kumpul</th><th class="text-center">Nilai</th><th>Catatan Guru</th></tr></thead>
          <tbody>
            <?php if (empty($tugasSaya)): ?>
              <tr><td colspan="6" class="text-center text-muted py-4">Belum ada tugas yang dinilai.</td></tr>
            <?php endif; ?>
            <?php foreach ($tugasSaya as $t): ?>
            <tr>
              <td data-label="Mapel" class="fw-semibold"><?= clean($t['nama_mapel']) ?></td>
              <td data-label="Judul Tugas"><?= clean($t['judul']) ?></td>
              <td data-label="Semester" class="text-muted small"><?= clean($t['semester']) ?> <?= clean($t['tahun_ajaran']) ?></td>
              <td data-label="Tanggal Kumpul" class="text-muted small"><?= formatTanggalIndo(substr($t['tanggal_kumpul'],0,10)) ?></td>
              <td data-label="Nilai" class="text-center">
                <?php if ($t['status'] === 'Sudah Dinilai' && $t['nilai'] !== null): ?>
                  <span class="fw-bold"><?= number_format($t['nilai'],1) ?></span>
                <?php else: ?>
                  <span class="badge-soft">Belum dinilai</span>
                <?php endif; ?>
              </td>
              <td data-label="Catatan Guru" class="text-muted small"><?= clean($t['feedback_guru'] ?: '-') ?></td>
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

// ==== Mode Wali Kelas: lihat nilai kelasnya saja (read-only) ====
if ($user['role'] === 'wali_kelas') {
    $kelasSaya = kelasWaliSaya($pdo, $user['id']);
    if (!$kelasSaya) {
        include __DIR__ . '/../includes/header.php';
        echo "<div class='card p-4 text-center'><i class='bi bi-exclamation-triangle text-warning fs-1 mb-2'></i><p class='mb-0'>Akun Anda belum dihubungkan ke kelas manapun. Hubungi admin sekolah.</p></div>";
        include __DIR__ . '/../includes/footer.php';
        exit;
    }

    $mapelList = $pdo->query("SELECT * FROM mata_pelajaran ORDER BY nama_mapel")->fetchAll();
    $mapel_id     = $_GET['mapel_id'] ?? ($mapelList[0]['id'] ?? '');
    $semester     = $_GET['semester'] ?? 'Ganjil';
    $tahun_ajaran = $_GET['tahun_ajaran'] ?? tahunAjaranSekarang();

    $siswaNilai = [];
    if ($mapel_id) {
        $stmt = $pdo->prepare("
            SELECT s.id, s.nis, s.nama_lengkap, n.nilai_tugas, n.nilai_uts, n.nilai_uas, n.nilai_akhir, n.predikat
            FROM siswa s
            LEFT JOIN nilai n ON n.siswa_id = s.id AND n.mapel_id = ? AND n.semester = ? AND n.tahun_ajaran = ?
            WHERE s.kelas_id = ? AND s.status = 'Aktif'
            ORDER BY s.nama_lengkap
        ");
        $stmt->execute([$mapel_id, $semester, $tahun_ajaran, $kelasSaya['id']]);
        $siswaNilai = $stmt->fetchAll();
    }

    include __DIR__ . '/../includes/header.php';
    styleResponsifNilai();
    ?>
    <h4 class="fw-bold mb-3"><i class="bi bi-clipboard-data-fill me-2"></i>Nilai Kelas <?= clean($kelasSaya['nama_kelas']) ?></h4>

    <div class="card p-3 mb-3">
      <form method="GET" class="row g-2">
        <div class="col-md-4">
          <label class="form-label small">Mata Pelajaran</label>
          <select name="mapel_id" class="form-select" onchange="this.form.submit()">
            <?php foreach ($mapelList as $m): ?>
              <option value="<?= $m['id'] ?>" <?= (string)$mapel_id === (string)$m['id'] ? 'selected' : '' ?>><?= clean($m['nama_mapel']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label small">Semester</label>
          <select name="semester" class="form-select" onchange="this.form.submit()">
            <option value="Ganjil" <?= $semester==='Ganjil'?'selected':'' ?>>Ganjil</option>
            <option value="Genap" <?= $semester==='Genap'?'selected':'' ?>>Genap</option>
          </select>
        </div>
        <div class="col-md-5">
          <label class="form-label small">Tahun Ajaran</label>
          <select name="tahun_ajaran" class="form-select" onchange="this.form.submit()">
            <?php foreach (opsiTahunAjaran() as $opt): ?>
              <option value="<?= $opt ?>" <?= $tahun_ajaran === $opt ? 'selected' : '' ?>><?= $opt ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </form>
    </div>

    <div class="card p-3">
      <div class="table-responsive table-responsive-mobile">
        <table class="table table-hover align-middle">
          <thead class="table-light"><tr><th>#</th><th>NIS</th><th>Nama Siswa</th><th>Tugas</th><th>UTS</th><th>UAS</th><th>Nilai Akhir</th><th>Predikat</th></tr></thead>
          <tbody>
            <?php if (empty($siswaNilai)): ?>
              <tr><td colspan="8" class="text-center text-muted py-4">Belum ada nilai untuk mapel/semester ini.</td></tr>
            <?php endif; ?>
            <?php foreach ($siswaNilai as $i => $s): ?>
            <tr>
              <td data-label="#"><?= $i+1 ?></td>
              <td data-label="NIS"><?= clean($s['nis']) ?></td>
              <td data-label="Nama Siswa" class="fw-semibold"><?= clean($s['nama_lengkap']) ?></td>
              <td data-label="Tugas"><?= $s['nilai_tugas'] !== null ? number_format($s['nilai_tugas'],1) : '-' ?></td>
              <td data-label="UTS"><?= $s['nilai_uts'] !== null ? number_format($s['nilai_uts'],1) : '-' ?></td>
              <td data-label="UAS"><?= $s['nilai_uas'] !== null ? number_format($s['nilai_uas'],1) : '-' ?></td>
              <td data-label="Nilai Akhir" class="fw-bold"><?= $s['nilai_akhir'] !== null ? number_format($s['nilai_akhir'],1) : '-' ?></td>
              <td data-label="Predikat"><?= $s['predikat'] ? "<span class='badge-soft brand'>{$s['predikat']}</span>" : '-' ?></td>
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

// ==== Mode Admin/Guru: input nilai per kelas + mapel ====
requireRole(['admin','guru']);

$kelasList = $pdo->query("SELECT * FROM kelas ORDER BY tingkat, nama_kelas")->fetchAll();
$mapelList = $pdo->query("SELECT * FROM mata_pelajaran ORDER BY nama_mapel")->fetchAll();

$kelas_id     = $_GET['kelas_id'] ?? ($kelasList[0]['id'] ?? '');
$mapel_id     = $_GET['mapel_id'] ?? ($mapelList[0]['id'] ?? '');
$semester     = $_GET['semester'] ?? 'Ganjil';
$tahun_ajaran = $_GET['tahun_ajaran'] ?? tahunAjaranSekarang();

// ==== Endpoint AJAX: tarik rata-rata nilai tugas E-Learning (dipanggil dari tombol via fetch) ====
if (isset($_GET['ajax_tarik_tugas'])) {
    header('Content-Type: application/json');
    $hasil = [];
    if ($kelas_id && $mapel_id) {
        $stmt = $pdo->prepare("
            SELECT ep.siswa_id, ROUND(AVG(ep.nilai),1) rata, COUNT(*) jumlah
            FROM elearning_pengumpulan ep
            JOIN elearning_tugas et ON ep.tugas_id = et.id
            WHERE et.kelas_id = ? AND et.mapel_id = ? AND et.semester = ? AND et.tahun_ajaran = ? AND ep.nilai IS NOT NULL
            GROUP BY ep.siswa_id
        ");
        $stmt->execute([$kelas_id, $mapel_id, $semester, $tahun_ajaran]);
        foreach ($stmt->fetchAll() as $row) {
            $hasil[$row['siswa_id']] = ['rata' => (float)$row['rata'], 'jumlah' => (int)$row['jumlah']];
        }
    }
    echo json_encode(['ok' => true, 'data' => $hasil]);
    exit;
}

// ==== Endpoint AJAX: tarik nilai UTS/UAS dari Ujian Online ====
// Syarat data yang ditarik: ujian berstatus Terbit, peserta berstatus Selesai,
// dan sudah dinilai (dinilai = 1, artinya essay sudah dikoreksi guru).
// Jika ada lebih dari satu ujian dengan jenis yang sama, nilainya dirata-rata.
if (isset($_GET['ajax_tarik_ujian'])) {
    header('Content-Type: application/json');
    $hasil = [];
    if ($kelas_id && $mapel_id) {
        $stmt = $pdo->prepare("
            SELECT up.siswa_id, u.jenis, ROUND(AVG(up.nilai_akhir),1) nilai, COUNT(*) jumlah
            FROM ujian_peserta up
            JOIN ujian u ON up.ujian_id = u.id
            WHERE u.kelas_id = ? AND u.mapel_id = ? AND u.semester = ? AND u.tahun_ajaran = ?
              AND u.status = 'Terbit' AND up.status = 'Selesai' AND up.dinilai = 1
            GROUP BY up.siswa_id, u.jenis
        ");
        $stmt->execute([$kelas_id, $mapel_id, $semester, $tahun_ajaran]);
        foreach ($stmt->fetchAll() as $row) {
            $hasil[$row['siswa_id']][strtolower($row['jenis'])] = [
                'nilai'  => (float)$row['nilai'],
                'jumlah' => (int)$row['jumlah'],
            ];
        }
    }
    echo json_encode(['ok' => true, 'data' => $hasil]);
    exit;
}

// ==== Endpoint AJAX: ambil daftar nilai tugas kertas yang tersimpan untuk
// seorang siswa (dipanggil saat modal kalkulator dibuka, supaya angka yang
// pernah diinput sebelumnya muncul lagi, tidak kosong) ====
if (isset($_GET['ajax_ambil_tugas_manual'])) {
    header('Content-Type: application/json');
    $siswa_id_amb = (int)($_GET['siswa_id'] ?? 0);
    $daftarNilai = [];
    if ($siswa_id_amb && $mapel_id) {
        $stmt = $pdo->prepare("SELECT nilai FROM nilai_tugas_manual WHERE siswa_id=? AND mapel_id=? AND semester=? AND tahun_ajaran=? ORDER BY urutan ASC");
        $stmt->execute([$siswa_id_amb, $mapel_id, $semester, $tahun_ajaran]);
        $daftarNilai = array_map('floatval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }
    echo json_encode(['ok' => true, 'nilai' => $daftarNilai]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ==== Simpan dari Kalkulator Nilai Tugas: simpan daftar nilai kertas
    // (supaya bisa dilihat lagi nanti), lalu hitung ulang rata-rata gabungan
    // di SERVER (bukan percaya angka dari browser) dan simpan ke tabel nilai. ====
    if (isset($_POST['ajax_simpan_kalkulator'])) {
        header('Content-Type: application/json');
        $siswa_id_k = (int)($_POST['siswa_id'] ?? 0);
        $nilaiArrMentah = $_POST['nilai'] ?? [];
        $sertakanElearning = ($_POST['sertakan_elearning'] ?? '0') === '1';
        $uts_k = (float)($_POST['uts'] ?? 0);
        $uas_k = (float)($_POST['uas'] ?? 0);

        if (!$siswa_id_k || !$mapel_id) {
            echo json_encode(['ok' => false, 'pesan' => 'Data tidak lengkap.']);
            exit;
        }

        // Bersihkan: hanya ambil angka valid 0-100
        $nilaiBersih = [];
        foreach ($nilaiArrMentah as $n) {
            if ($n === '' || $n === null) continue;
            $f = (float)$n;
            if ($f < 0 || $f > 100) continue;
            $nilaiBersih[] = $f;
        }

        // Ganti seluruh daftar nilai kertas siswa ini untuk mapel/semester/tahun ajaran ini
        $pdo->prepare("DELETE FROM nilai_tugas_manual WHERE siswa_id=? AND mapel_id=? AND semester=? AND tahun_ajaran=?")
            ->execute([$siswa_id_k, $mapel_id, $semester, $tahun_ajaran]);
        if (!empty($nilaiBersih)) {
            $stmtIns = $pdo->prepare("INSERT INTO nilai_tugas_manual (siswa_id, kelas_id, mapel_id, semester, tahun_ajaran, nilai, urutan) VALUES (?,?,?,?,?,?,?)");
            foreach ($nilaiBersih as $idx => $nilaiKertas) {
                $stmtIns->execute([$siswa_id_k, $kelas_id, $mapel_id, $semester, $tahun_ajaran, $nilaiKertas, $idx]);
            }
        }

        // Hitung rata-rata gabungan (kertas + e-learning jika dicentang), tertimbang sesuai jumlah tugas
        $totalNilai = array_sum($nilaiBersih);
        $totalJumlah = count($nilaiBersih);

        if ($sertakanElearning) {
            $stmtEl = $pdo->prepare("
                SELECT ROUND(AVG(ep.nilai),1) rata, COUNT(*) jumlah
                FROM elearning_pengumpulan ep
                JOIN elearning_tugas et ON ep.tugas_id = et.id
                WHERE et.kelas_id = ? AND et.mapel_id = ? AND et.semester = ? AND et.tahun_ajaran = ? AND ep.nilai IS NOT NULL AND ep.siswa_id = ?
            ");
            $stmtEl->execute([$kelas_id, $mapel_id, $semester, $tahun_ajaran, $siswa_id_k]);
            $el = $stmtEl->fetch();
            if ($el && $el['jumlah'] > 0) {
                $totalNilai += $el['rata'] * $el['jumlah'];
                $totalJumlah += $el['jumlah'];
            }
        }

        $rataGabungan = $totalJumlah > 0 ? round($totalNilai / $totalJumlah, 1) : 0;
        $akhir_k = round(($rataGabungan * 0.3) + ($uts_k * 0.3) + ($uas_k * 0.4), 1);
        $predikat_k = hitungPredikat($akhir_k);

        $stmtNilai = $pdo->prepare("
            INSERT INTO nilai (siswa_id, mapel_id, semester, tahun_ajaran, nilai_tugas, nilai_uts, nilai_uas, nilai_akhir, predikat)
            VALUES (?,?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE nilai_tugas=VALUES(nilai_tugas), nilai_uts=VALUES(nilai_uts), nilai_uas=VALUES(nilai_uas),
                nilai_akhir=VALUES(nilai_akhir), predikat=VALUES(predikat)
        ");
        $stmtNilai->execute([$siswa_id_k, $mapel_id, $semester, $tahun_ajaran, $rataGabungan, $uts_k, $uas_k, $akhir_k, $predikat_k]);

        echo json_encode(['ok' => true, 'rata' => $rataGabungan, 'akhir' => $akhir_k, 'predikat' => $predikat_k]);
        exit;
    }

    // ==== Auto-simpan satu nilai siswa (dipanggil via AJAX dari kalkulator,
    // tarik-ulang E-Learning, atau tarik UTS/UAS dari Ujian, supaya nilai
    // langsung tersimpan ke database begitu diterapkan — guru tidak perlu
    // ingat klik "Simpan Nilai" lagi). ====
    if (isset($_POST['ajax_simpan_satu'])) {
        header('Content-Type: application/json');
        $siswa_id_satu = (int)($_POST['siswa_id'] ?? 0);
        $tugas_satu    = (float)($_POST['tugas'] ?? 0);
        $uts_satu      = (float)($_POST['uts'] ?? 0);
        $uas_satu      = (float)($_POST['uas'] ?? 0);

        if (!$siswa_id_satu || !$mapel_id) {
            echo json_encode(['ok' => false, 'pesan' => 'Data tidak lengkap.']);
            exit;
        }

        $akhir_satu = round(($tugas_satu * 0.3) + ($uts_satu * 0.3) + ($uas_satu * 0.4), 1);
        $predikat_satu = hitungPredikat($akhir_satu);

        $stmt = $pdo->prepare("
            INSERT INTO nilai (siswa_id, mapel_id, semester, tahun_ajaran, nilai_tugas, nilai_uts, nilai_uas, nilai_akhir, predikat)
            VALUES (?,?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE nilai_tugas=VALUES(nilai_tugas), nilai_uts=VALUES(nilai_uts), nilai_uas=VALUES(nilai_uas),
                nilai_akhir=VALUES(nilai_akhir), predikat=VALUES(predikat)
        ");
        $stmt->execute([$siswa_id_satu, $mapel_id, $semester, $tahun_ajaran, $tugas_satu, $uts_satu, $uas_satu, $akhir_satu, $predikat_satu]);

        echo json_encode(['ok' => true, 'akhir' => $akhir_satu, 'predikat' => $predikat_satu]);
        exit;
    }

    $kelas_id_p     = $_POST['kelas_id'];
    $mapel_id_p     = $_POST['mapel_id'];
    $semester_p     = $_POST['semester'];
    $tahun_ajaran_p = $_POST['tahun_ajaran'];
    $tugasArr = $_POST['tugas'] ?? [];
    $utsArr   = $_POST['uts'] ?? [];
    $uasArr   = $_POST['uas'] ?? [];

    $stmt = $pdo->prepare("
        INSERT INTO nilai (siswa_id, mapel_id, semester, tahun_ajaran, nilai_tugas, nilai_uts, nilai_uas, nilai_akhir, predikat)
        VALUES (?,?,?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE nilai_tugas=VALUES(nilai_tugas), nilai_uts=VALUES(nilai_uts), nilai_uas=VALUES(nilai_uas),
            nilai_akhir=VALUES(nilai_akhir), predikat=VALUES(predikat)
    ");
    foreach ($tugasArr as $siswa_id => $tugas) {
        $tugas = (float)$tugas;
        $uts   = (float)($utsArr[$siswa_id] ?? 0);
        $uas   = (float)($uasArr[$siswa_id] ?? 0);
        // Bobot: tugas 30%, UTS 30%, UAS 40%
        $akhir = round(($tugas * 0.3) + ($uts * 0.3) + ($uas * 0.4), 1);
        $predikat = hitungPredikat($akhir);
        $stmt->execute([$siswa_id, $mapel_id_p, $semester_p, $tahun_ajaran_p, $tugas, $uts, $uas, $akhir, $predikat]);
    }
    setFlash('success', 'Nilai berhasil disimpan.');
    redirect("nilai/index.php?kelas_id={$kelas_id_p}&mapel_id={$mapel_id_p}&semester={$semester_p}&tahun_ajaran=" . urlencode($tahun_ajaran_p));
}

$siswaNilai = [];
if ($kelas_id && $mapel_id) {
    $stmt = $pdo->prepare("
        SELECT s.id, s.nis, s.nama_lengkap, n.nilai_tugas, n.nilai_uts, n.nilai_uas, n.nilai_akhir, n.predikat
        FROM siswa s
        LEFT JOIN nilai n ON n.siswa_id = s.id AND n.mapel_id = ? AND n.semester = ? AND n.tahun_ajaran = ?
        WHERE s.kelas_id = ? AND s.status = 'Aktif'
        ORDER BY s.nama_lengkap
    ");
    $stmt->execute([$mapel_id, $semester, $tahun_ajaran, $kelas_id]);
    $siswaNilai = $stmt->fetchAll();
}

// ==== Ambil rata-rata nilai tugas E-Learning per siswa (kelas + mapel + semester + tahun ajaran) ====
$tugasOtomatis = [];
if ($kelas_id && $mapel_id) {
    $stmt = $pdo->prepare("
        SELECT ep.siswa_id, ROUND(AVG(ep.nilai),1) rata, COUNT(*) jumlah
        FROM elearning_pengumpulan ep
        JOIN elearning_tugas et ON ep.tugas_id = et.id
        WHERE et.kelas_id = ? AND et.mapel_id = ? AND et.semester = ? AND et.tahun_ajaran = ? AND ep.nilai IS NOT NULL
        GROUP BY ep.siswa_id
    ");
    $stmt->execute([$kelas_id, $mapel_id, $semester, $tahun_ajaran]);
    foreach ($stmt->fetchAll() as $row) {
        $tugasOtomatis[$row['siswa_id']] = $row;
    }
}

// ==== Ambil nilai UTS/UAS dari Ujian Online per siswa (kelas + mapel + semester + tahun ajaran) ====
$ujianOtomatis = [];
if ($kelas_id && $mapel_id) {
    $stmt = $pdo->prepare("
        SELECT up.siswa_id, u.jenis, ROUND(AVG(up.nilai_akhir),1) nilai, COUNT(*) jumlah
        FROM ujian_peserta up
        JOIN ujian u ON up.ujian_id = u.id
        WHERE u.kelas_id = ? AND u.mapel_id = ? AND u.semester = ? AND u.tahun_ajaran = ?
          AND u.status = 'Terbit' AND up.status = 'Selesai' AND up.dinilai = 1
        GROUP BY up.siswa_id, u.jenis
    ");
    $stmt->execute([$kelas_id, $mapel_id, $semester, $tahun_ajaran]);
    foreach ($stmt->fetchAll() as $row) {
        $ujianOtomatis[$row['siswa_id']][strtolower($row['jenis'])] = $row;
    }
}

include __DIR__ . '/../includes/header.php';
styleResponsifNilai();
?>

<h4 class="fw-bold mb-3"><i class="bi bi-clipboard-data-fill me-2"></i>Input Nilai</h4>

<div class="card p-3 mb-3">
  <form method="GET" class="row g-2">
    <div class="col-md-3">
      <label class="form-label small">Kelas</label>
      <select name="kelas_id" class="form-select" onchange="this.form.submit()">
        <?php foreach ($kelasList as $k): ?>
          <option value="<?= $k['id'] ?>" <?= (string)$kelas_id === (string)$k['id'] ? 'selected' : '' ?>><?= clean($k['nama_kelas']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label small">Mata Pelajaran</label>
      <select name="mapel_id" class="form-select" onchange="this.form.submit()">
        <?php foreach ($mapelList as $m): ?>
          <option value="<?= $m['id'] ?>" <?= (string)$mapel_id === (string)$m['id'] ? 'selected' : '' ?>><?= clean($m['nama_mapel']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label small">Semester</label>
      <select name="semester" class="form-select" onchange="this.form.submit()">
        <option value="Ganjil" <?= $semester==='Ganjil'?'selected':'' ?>>Ganjil</option>
        <option value="Genap" <?= $semester==='Genap'?'selected':'' ?>>Genap</option>
      </select>
    </div>
    <div class="col-md-4">
      <label class="form-label small">Tahun Ajaran</label>
      <select name="tahun_ajaran" class="form-select" onchange="this.form.submit()">
        <?php foreach (opsiTahunAjaran() as $opt): ?>
          <option value="<?= $opt ?>" <?= $tahun_ajaran === $opt ? 'selected' : '' ?>><?= $opt ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </form>
</div>

<?php if ($kelas_id && $mapel_id): ?>
<style>
@keyframes tarikGlow {
  0%   { box-shadow: 0 0 0 0 rgba(25,135,84,.45); background-color:#e9f9ef; }
  60%  { box-shadow: 0 0 0 6px rgba(25,135,84,0); background-color:#e9f9ef; }
  100% { box-shadow: 0 0 0 0 rgba(25,135,84,0); background-color:#fff; }
}
.tugas-glow { animation: tarikGlow 1.1s ease-out; }

.tugas-hint { transition: color .3s ease, opacity .3s ease; opacity:.85; }
.tugas-hint.updating { opacity:0; }

#toastTarik {
  position: fixed; top: 18px; right: 18px; z-index: 2000;
  min-width: 300px; max-width: 380px;
  background: #fff; border-left: 4px solid #198754; border-radius: 10px;
  box-shadow: 0 10px 30px -8px rgba(0,0,0,.25);
  padding: 12px 16px; display:flex; align-items:flex-start; gap:10px;
  transform: translateX(120%); opacity: 0; transition: transform .35s cubic-bezier(.34,1.56,.64,1), opacity .35s ease;
}
#toastTarik.show { transform: translateX(0); opacity: 1; }
#toastTarik .bi { color:#198754; font-size:1.2rem; margin-top:1px; }
#toastTarik .msg-title { font-weight:700; font-size:.85rem; }
#toastTarik .msg-sub { font-size:.78rem; color:#6c757d; }

.btn-tarik {
  display: inline-flex; align-items: center; justify-content: center;
  gap: 6px; white-space: nowrap; min-width: 250px;
}
@media (max-width: 767.98px) {
  .tarik-actions { width: 100%; }
  .tarik-actions .btn-tarik { min-width: 0; width: 100%; }
}
</style>

<div id="toastTarik"></div>

<div class="card p-3">
  <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
    <div class="alert alert-light border small mb-0">
      Nilai Akhir dihitung otomatis: Tugas 30% + UTS 30% + UAS 40%.<br>
      Kolom <strong>Tugas</strong> otomatis terisi dari rata-rata nilai tugas E-Learning yang semester &amp; tahun ajarannya sama persis, jika belum pernah diisi manual.<br>
      Kolom <strong>UTS</strong> &amp; <strong>UAS</strong> bisa ditarik dari <strong>Ujian Online</strong> (ujian berstatus Terbit dan sudah selesai dikoreksi).
    </div>
    <div class="d-flex flex-column gap-2 tarik-actions">
      <button type="button" id="btnTarikTugas" class="btn btn-sm btn-outline-secondary btn-tarik">
        <i class="bi bi-laptop"></i> Tarik Tugas dari E-Learning
      </button>
      <button type="button" id="btnTarikUjian" class="btn btn-sm btn-outline-secondary btn-tarik">
        <i class="bi bi-file-earmark-check"></i> Tarik UTS/UAS dari Ujian
      </button>
    </div>
  </div>

  <form method="POST" id="formNilai">
    <input type="hidden" name="kelas_id" value="<?= clean($kelas_id) ?>">
    <input type="hidden" name="mapel_id" value="<?= clean($mapel_id) ?>">
    <input type="hidden" name="semester" value="<?= clean($semester) ?>">
    <input type="hidden" name="tahun_ajaran" value="<?= clean($tahun_ajaran) ?>">

    <div class="table-responsive table-responsive-mobile">
      <table class="table table-hover align-middle">
        <thead class="table-light">
          <tr><th>#</th><th>NIS</th><th>Nama Siswa</th><th>Tugas</th><th>UTS</th><th>UAS</th><th class="text-center">Preview Akhir</th></tr>
        </thead>
        <tbody>
          <?php if (empty($siswaNilai)): ?>
            <tr><td colspan="7" class="text-center text-muted py-4">Tidak ada siswa aktif di kelas ini.</td></tr>
          <?php endif; ?>
          <?php foreach ($siswaNilai as $i => $s):
              $otomatis = $tugasOtomatis[$s['id']] ?? null;
              $pakaiOtomatis = $otomatis && $s['nilai_tugas'] === null;
              $nilaiTugasTampil = $pakaiOtomatis ? $otomatis['rata'] : $s['nilai_tugas'];

              // UTS/UAS dari Ujian Online: dipakai sebagai tampilan awal jika
              // nilai tersimpan masih kosong / 0 (default kolom database 0.00).
              $uj = $ujianOtomatis[$s['id']] ?? [];
              $pakaiUts = isset($uj['uts']) && ($s['nilai_uts'] === null || (float)$s['nilai_uts'] == 0);
              $pakaiUas = isset($uj['uas']) && ($s['nilai_uas'] === null || (float)$s['nilai_uas'] == 0);
              $utsTampil = $pakaiUts ? $uj['uts']['nilai'] : $s['nilai_uts'];
              $uasTampil = $pakaiUas ? $uj['uas']['nilai'] : $s['nilai_uas'];

              $previewAwal = round((($nilaiTugasTampil ?? 0) * 0.3) + (($utsTampil ?? 0) * 0.3) + (($uasTampil ?? 0) * 0.4), 1);
          ?>
          <tr data-siswa-row="<?= $s['id'] ?>">
            <td data-label="#"><?= $i + 1 ?></td>
            <td data-label="NIS"><?= clean($s['nis']) ?></td>
            <td data-label="Nama Siswa" class="fw-semibold"><?= clean($s['nama_lengkap']) ?></td>
            <td data-label="Tugas">
              <div class="d-flex align-items-center gap-1">
                <input type="number" step="0.1" min="0" max="100" name="tugas[<?= $s['id'] ?>]"
                       class="form-control form-control-sm td-tugas-input" style="width:80px;"
                       data-siswa-id="<?= $s['id'] ?>" data-manual="<?= $s['nilai_tugas'] !== null ? clean($s['nilai_tugas']) : '' ?>"
                       value="<?= $nilaiTugasTampil ?? '' ?>">
                <button type="button" class="btn btn-sm btn-outline-secondary btn-kalkulator" data-siswa-id="<?= $s['id'] ?>" data-nama="<?= clean($s['nama_lengkap']) ?>" data-elearning-rata="<?= $otomatis['rata'] ?? 0 ?>" data-elearning-jumlah="<?= $otomatis['jumlah'] ?? 0 ?>" title="Kalkulator nilai tugas kertas">
                  <i class="bi bi-calculator"></i>
                </button>
              </div>
              <div class="small tugas-hint <?= $pakaiOtomatis ? 'text-success' : 'text-muted' ?>" data-hint-for="<?= $s['id'] ?>">
                <?php if ($otomatis): ?>
                  <?= $pakaiOtomatis ? 'otomatis dari' : 'tersedia:' ?> <?= $otomatis['jumlah'] ?> tugas
                <?php endif; ?>
              </div>
            </td>
            <td data-label="UTS">
              <input type="number" step="0.1" min="0" max="100" name="uts[<?= $s['id'] ?>]" class="form-control form-control-sm td-uts-input" data-siswa-id="<?= $s['id'] ?>" value="<?= $utsTampil ?? '' ?>" style="width:90px;">
              <?php if (isset($uj['uts'])): ?>
                <div class="small <?= $pakaiUts ? 'text-success' : 'text-muted' ?>"><?= $pakaiUts ? 'otomatis dari ujian' : 'ujian: ' . number_format($uj['uts']['nilai'], 1) ?></div>
              <?php endif; ?>
            </td>
            <td data-label="UAS">
              <input type="number" step="0.1" min="0" max="100" name="uas[<?= $s['id'] ?>]" class="form-control form-control-sm td-uas-input" data-siswa-id="<?= $s['id'] ?>" value="<?= $uasTampil ?? '' ?>" style="width:90px;">
              <?php if (isset($uj['uas'])): ?>
                <div class="small <?= $pakaiUas ? 'text-success' : 'text-muted' ?>"><?= $pakaiUas ? 'otomatis dari ujian' : 'ujian: ' . number_format($uj['uas']['nilai'], 1) ?></div>
              <?php endif; ?>
            </td>
            <td data-label="Preview Akhir" class="text-center">
              <span class="badge bg-light text-dark border preview-akhir" id="preview-<?= $s['id'] ?>"><?= number_format($previewAwal,1) ?></span>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if (!empty($siswaNilai)): ?>
      <button class="btn btn-primary"><i class="bi bi-save"></i> Simpan Nilai</button>
    <?php endif; ?>
  </form>
</div>

<!-- Modal Kalkulator Nilai Tugas Kertas -->
<div class="modal fade" id="modalKalkulator" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title mb-0"><i class="bi bi-calculator me-1"></i> Kalkulator Nilai Tugas — <span id="kalkNamaSiswa"></span></h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted small mb-2">Masukkan nilai tugas kertas satu per satu (PR, ulangan harian, dll), lalu rata-ratanya otomatis dihitung dan bisa langsung diterapkan ke kolom Tugas.</p>

        <div id="kalkElearningInfo" class="alert alert-light border small d-none mb-3">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="kalkSertakanElearning" checked>
            <label class="form-check-label" for="kalkSertakanElearning">
              <i class="bi bi-laptop me-1"></i> Gabungkan dengan nilai E-Learning: rata-rata <strong id="kalkElearningRataTxt">0</strong> dari <strong id="kalkElearningJumlahTxt">0</strong> tugas
            </label>
          </div>
        </div>

        <div class="fw-semibold small text-muted mb-1">Nilai Tugas Kertas</div>
        <div id="kalkDaftarNilai"></div>
        <button type="button" class="btn btn-sm btn-outline-primary mt-1" id="btnTambahBarisKalk"><i class="bi bi-plus-lg"></i> Tambah Nilai</button>
        <hr>
        <div class="d-flex justify-content-between align-items-center">
          <span class="fw-semibold">Rata-rata Gabungan:</span>
          <span class="fs-4 fw-bold text-primary" id="kalkRataRata">0.0</span>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-primary" id="btnTerapkanKalk"><i class="bi bi-check-lg"></i> Terapkan ke Kolom Tugas</button>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  const btn = document.getElementById('btnTarikTugas');
  const toast = document.getElementById('toastTarik');
  if (!btn) return;

  function showToast(title, sub, isError) {
    toast.innerHTML =
      '<i class="bi ' + (isError ? 'bi-exclamation-circle-fill' : 'bi-check-circle-fill') + '"></i>' +
      '<div><div class="msg-title">' + title + '</div><div class="msg-sub">' + sub + '</div></div>';
    toast.style.borderLeftColor = isError ? '#dc3545' : '#198754';
    requestAnimationFrame(() => toast.classList.add('show'));
    clearTimeout(toast._t);
    toast._t = setTimeout(() => toast.classList.remove('show'), 3200);
  }

  function animateCount(el, from, to, duration) {
    const start = performance.now();
    from = isNaN(from) ? 0 : from;
    function step(now) {
      const p = Math.min(1, (now - start) / duration);
      const eased = 1 - Math.pow(1 - p, 3); // ease-out cubic
      const val = from + (to - from) * eased;
      el.value = val.toFixed(1);
      if (el.dataset.siswaId) updatePreview(el.dataset.siswaId);
      if (p < 1) requestAnimationFrame(step);
      else el.value = to.toFixed(1);
    }
    requestAnimationFrame(step);
  }

  function tarikOtomatis() {
    const labelAwal = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Menarik nilai...';

    const params = new URLSearchParams({
      ajax_tarik_tugas: 1,
      kelas_id: <?= json_encode($kelas_id) ?>,
      mapel_id: <?= json_encode($mapel_id) ?>,
      semester: <?= json_encode($semester) ?>,
      tahun_ajaran: <?= json_encode($tahun_ajaran) ?>
    });

    // Pakai path halaman saat ini secara eksplisit (bukan cuma "?...") supaya
    // tidak ambigu resolusinya di server dengan struktur URL/subfolder apapun.
    const url = window.location.pathname + '?' + params.toString();

    fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.text();
      })
      .then(function (text) {
        let res;
        try {
          res = JSON.parse(text);
        } catch (e) {
          console.error('Respons bukan JSON valid:', text);
          throw new Error('Respons server tidak valid (bukan JSON). Cek console (F12) untuk detail.');
        }
        if (!res.ok) throw new Error(res.pesan || 'Gagal mengambil data');

        const data = res.data;
        let jumlahSiswaTerpengaruh = 0;
        const promiseSimpan = [];

        document.querySelectorAll('.td-tugas-input').forEach(function (input) {
          const sid = input.dataset.siswaId;
          const info = data[sid];
          const hint = document.querySelector('[data-hint-for="' + sid + '"]');
          if (!info) return;

          jumlahSiswaTerpengaruh++;
          const from = parseFloat(input.value) || 0;
          animateCount(input, from, info.rata, 550);
          input.classList.remove('tugas-glow');
          void input.offsetWidth; // restart animasi
          input.classList.add('tugas-glow');

          if (hint) {
            hint.classList.add('updating');
            setTimeout(function () {
              hint.className = 'small tugas-hint text-success';
              hint.textContent = 'otomatis dari ' + info.jumlah + ' tugas';
              hint.classList.remove('updating');
            }, 220);
          }

          promiseSimpan.push(
            simpanSatuNilai(sid, info.rata)
              .then(function (r) {
                if (r.ok) tandaiTersimpan(sid);
                return r.ok;
              })
              .catch(function () { return false; })
          );
        });

        btn.disabled = false;
        btn.innerHTML = labelAwal;

        if (jumlahSiswaTerpengaruh === 0) {
          showToast('Tidak ada data', 'Belum ada tugas dinilai untuk kelas/mapel/semester ini.', true);
          return;
        }

        Promise.all(promiseSimpan).then(function (hasilArr) {
          const jumlahSukses = hasilArr.filter(Boolean).length;
          if (jumlahSukses === jumlahSiswaTerpengaruh) {
            showToast('Tugas berhasil ditarik!', jumlahSiswaTerpengaruh + ' siswa, langsung tersimpan ke database.', false);
          } else {
            showToast('Ditarik, sebagian gagal tersimpan', jumlahSukses + ' dari ' + jumlahSiswaTerpengaruh + ' tersimpan. Klik "Simpan Nilai" untuk sisanya.', true);
          }
        });
      })
      .catch(function (err) {
        console.error('Tarik Tugas dari E-Learning gagal:', err);
        btn.disabled = false;
        btn.innerHTML = labelAwal;
        showToast('Gagal menarik data', err.message || 'Terjadi kesalahan, coba lagi.', true);
      });
  }

  function simpanSatuNilai(siswaId, nilaiTugas) {
    const utsInput = document.querySelector('.td-uts-input[data-siswa-id="' + siswaId + '"]');
    const uasInput = document.querySelector('.td-uas-input[data-siswa-id="' + siswaId + '"]');
    const body = new URLSearchParams({
      ajax_simpan_satu: 1,
      siswa_id: siswaId,
      tugas: nilaiTugas,
      uts: (utsInput && utsInput.value) || 0,
      uas: (uasInput && uasInput.value) || 0
    });
    return fetch(window.location.pathname + window.location.search, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
      body: body.toString()
    }).then(function (r) { return r.json(); });
  }

  function tandaiTersimpan(siswaId) {
    const hint = document.querySelector('[data-hint-for="' + siswaId + '"]');
    if (!hint) return;
    const teksAsli = hint.textContent;
    const kelasAsli = hint.className;
    hint.innerHTML = '<i class="bi bi-check-circle-fill"></i> tersimpan ke database';
    hint.className = 'small tugas-hint text-success fw-semibold';
    setTimeout(function () {
      hint.className = kelasAsli;
      hint.textContent = teksAsli;
    }, 2500);
  }

  function updatePreview(siswaId) {
    const tugasInput = document.querySelector('.td-tugas-input[data-siswa-id="' + siswaId + '"]');
    const utsInput = document.querySelector('.td-uts-input[data-siswa-id="' + siswaId + '"]');
    const uasInput = document.querySelector('.td-uas-input[data-siswa-id="' + siswaId + '"]');
    const preview = document.getElementById('preview-' + siswaId);
    if (!preview) return;
    const tugas = parseFloat(tugasInput && tugasInput.value) || 0;
    const uts = parseFloat(utsInput && utsInput.value) || 0;
    const uas = parseFloat(uasInput && uasInput.value) || 0;
    const akhir = (tugas * 0.3) + (uts * 0.3) + (uas * 0.4);
    preview.textContent = akhir.toFixed(1);
  }

  document.querySelectorAll('.td-tugas-input, .td-uts-input, .td-uas-input').forEach(function (input) {
    input.addEventListener('input', function () {
      updatePreview(this.dataset.siswaId);
    });
  });

  // ==== Kalkulator nilai tugas kertas (bisa digabung dengan rata-rata E-Learning) ====
  let kalkTargetSiswaId = null;
  let kalkElearningRata = 0;
  let kalkElearningJumlah = 0;
  const kalkDaftar = document.getElementById('kalkDaftarNilai');

  function hitungRataKalk() {
    const rataEl = document.getElementById('kalkRataRata');
    if (!rataEl) return;

    const nilaiManual = Array.from(kalkDaftar.querySelectorAll('.kalk-input'))
      .map(function (i) { return parseFloat(i.value); })
      .filter(function (v) { return !isNaN(v); });

    const sertakanElearning = document.getElementById('kalkSertakanElearning').checked && kalkElearningJumlah > 0;

    // Rata-rata tertimbang: nilai e-learning dihitung sebagai "kalkElearningJumlah"
    // titik data dengan nilai rata-ratanya, supaya bobotnya proporsional
    // terhadap banyaknya tugas, bukan cuma dianggap 1 angka biasa.
    let totalNilai = nilaiManual.reduce(function (a, b) { return a + b; }, 0);
    let totalJumlah = nilaiManual.length;
    if (sertakanElearning) {
      totalNilai += kalkElearningRata * kalkElearningJumlah;
      totalJumlah += kalkElearningJumlah;
    }

    const rata = totalJumlah > 0 ? (totalNilai / totalJumlah) : 0;
    rataEl.textContent = rata.toFixed(1);
  }

  function tambahBarisKalk(nilaiAwal) {
    const div = document.createElement('div');
    div.className = 'input-group input-group-sm mb-2';
    div.innerHTML =
      '<span class="input-group-text"><i class="bi bi-file-earmark-text"></i></span>' +
      '<input type="number" step="0.1" min="0" max="100" class="form-control kalk-input" placeholder="Nilai tugas">' +
      '<button type="button" class="btn btn-outline-danger btn-hapus-baris-kalk"><i class="bi bi-x-lg"></i></button>';
    kalkDaftar.appendChild(div);
    const inputKalk = div.querySelector('.kalk-input');
    if (nilaiAwal !== undefined && nilaiAwal !== null) inputKalk.value = nilaiAwal;
    inputKalk.addEventListener('input', hitungRataKalk);
    div.querySelector('.btn-hapus-baris-kalk').addEventListener('click', function () {
      div.remove();
      hitungRataKalk();
    });
  }

  function ambilTugasManualTersimpan(siswaId) {
    const params = new URLSearchParams(window.location.search);
    params.set('ajax_ambil_tugas_manual', 1);
    params.set('siswa_id', siswaId);
    const url = window.location.pathname + '?' + params.toString();
    return fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } }).then(function (r) { return r.json(); });
  }

  const btnTambahKalk = document.getElementById('btnTambahBarisKalk');
  if (btnTambahKalk) btnTambahKalk.addEventListener('click', tambahBarisKalk);

  const checkboxElearning = document.getElementById('kalkSertakanElearning');
  if (checkboxElearning) checkboxElearning.addEventListener('change', hitungRataKalk);

  document.querySelectorAll('.btn-kalkulator').forEach(function (tombol) {
    tombol.addEventListener('click', function () {
      kalkTargetSiswaId = this.dataset.siswaId;
      kalkElearningRata = parseFloat(this.dataset.elearningRata) || 0;
      kalkElearningJumlah = parseInt(this.dataset.elearningJumlah) || 0;

      document.getElementById('kalkNamaSiswa').textContent = this.dataset.nama;

      const infoBox = document.getElementById('kalkElearningInfo');
      if (kalkElearningJumlah > 0) {
        infoBox.classList.remove('d-none');
        document.getElementById('kalkElearningRataTxt').textContent = kalkElearningRata.toFixed(1);
        document.getElementById('kalkElearningJumlahTxt').textContent = kalkElearningJumlah;
        checkboxElearning.checked = true;
      } else {
        infoBox.classList.add('d-none');
      }

      kalkDaftar.innerHTML = '<div class="text-muted small py-2"><span class="spinner-border spinner-border-sm me-1"></span>Memuat nilai tersimpan...</div>';
      const modalEl = document.getElementById('modalKalkulator');
      const modalKalk = new bootstrap.Modal(modalEl);
      modalKalk.show();

      ambilTugasManualTersimpan(kalkTargetSiswaId)
        .then(function (res) {
          kalkDaftar.innerHTML = '';
          const daftarTersimpan = (res.ok && res.nilai && res.nilai.length) ? res.nilai : [null, null];
          daftarTersimpan.forEach(function (n) { tambahBarisKalk(n); });
          hitungRataKalk();
        })
        .catch(function () {
          kalkDaftar.innerHTML = '';
          tambahBarisKalk();
          tambahBarisKalk();
          hitungRataKalk();
        });
    });
  });

  const btnTerapkanKalk = document.getElementById('btnTerapkanKalk');
  if (btnTerapkanKalk) {
    btnTerapkanKalk.addEventListener('click', function () {
      if (!kalkTargetSiswaId) return;

      const nilaiArr = Array.from(kalkDaftar.querySelectorAll('.kalk-input')).map(function (i) { return i.value; });
      const jumlahKertasTerisi = nilaiArr.filter(function (n) { return n !== '' && !isNaN(parseFloat(n)); }).length;
      const sertakanElearning = checkboxElearning.checked && kalkElearningJumlah > 0;

      const utsInput = document.querySelector('.td-uts-input[data-siswa-id="' + kalkTargetSiswaId + '"]');
      const uasInput = document.querySelector('.td-uas-input[data-siswa-id="' + kalkTargetSiswaId + '"]');

      const body = new URLSearchParams();
      body.append('ajax_simpan_kalkulator', 1);
      body.append('siswa_id', kalkTargetSiswaId);
      body.append('sertakan_elearning', sertakanElearning ? '1' : '0');
      body.append('uts', (utsInput && utsInput.value) || 0);
      body.append('uas', (uasInput && uasInput.value) || 0);
      nilaiArr.forEach(function (n) { body.append('nilai[]', n); });

      btnTerapkanKalk.disabled = true;
      btnTerapkanKalk.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> Menyimpan...';

      fetch(window.location.pathname + window.location.search, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
        body: body.toString()
      })
        .then(function (r) { return r.json(); })
        .then(function (res) {
          btnTerapkanKalk.disabled = false;
          btnTerapkanKalk.innerHTML = '<i class="bi bi-check-lg"></i> Terapkan ke Kolom Tugas';
          if (!res.ok) throw new Error(res.pesan || 'Gagal menyimpan');

          const rataFinal = parseFloat(res.rata) || 0;
          const target = document.querySelector('.td-tugas-input[data-siswa-id="' + kalkTargetSiswaId + '"]');
          if (target) {
            const from = parseFloat(target.value) || 0;
            animateCount(target, from, rataFinal, 500);
            target.classList.remove('tugas-glow');
            void target.offsetWidth;
            target.classList.add('tugas-glow');
          }

          const preview = document.getElementById('preview-' + kalkTargetSiswaId);
          if (preview) preview.textContent = parseFloat(res.akhir).toFixed(1);

          const hint = document.querySelector('[data-hint-for="' + kalkTargetSiswaId + '"]');
          if (hint) {
            hint.className = 'small tugas-hint text-primary';
            hint.textContent = sertakanElearning
              ? 'gabungan: ' + jumlahKertasTerisi + ' kertas + ' + kalkElearningJumlah + ' e-learning'
              : jumlahKertasTerisi + ' tugas kertas (manual)';
          }

          tandaiTersimpan(kalkTargetSiswaId);
          showToast(
            'Nilai gabungan tersimpan!',
            'Rata-rata ' + rataFinal.toFixed(1) + ' — daftar nilai kertas & hasilnya tersimpan ke database.',
            false
          );
          bootstrap.Modal.getInstance(document.getElementById('modalKalkulator'))?.hide();
        })
        .catch(function (err) {
          btnTerapkanKalk.disabled = false;
          btnTerapkanKalk.innerHTML = '<i class="bi bi-check-lg"></i> Terapkan ke Kolom Tugas';
          showToast('Gagal menyimpan', err.message || 'Terjadi kesalahan, coba lagi.', true);
        });
    });
  }

  // ==== Tarik UTS/UAS dari Ujian Online ====
  function simpanNilaiLengkap(siswaId, tugas, uts, uas) {
    const body = new URLSearchParams({
      ajax_simpan_satu: 1,
      siswa_id: siswaId,
      tugas: tugas,
      uts: uts,
      uas: uas
    });
    return fetch(window.location.pathname + window.location.search, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
      body: body.toString()
    }).then(function (r) { return r.json(); });
  }

  const btnUjian = document.getElementById('btnTarikUjian');
  if (btnUjian) {
    const labelAwalUjian = btnUjian.innerHTML;
    btnUjian.addEventListener('click', function () {
      btnUjian.disabled = true;
      btnUjian.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Menarik nilai...';

      const params = new URLSearchParams({
        ajax_tarik_ujian: 1,
        kelas_id: <?= json_encode($kelas_id) ?>,
        mapel_id: <?= json_encode($mapel_id) ?>,
        semester: <?= json_encode($semester) ?>,
        tahun_ajaran: <?= json_encode($tahun_ajaran) ?>
      });

      fetch(window.location.pathname + '?' + params.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) {
          if (!r.ok) throw new Error('HTTP ' + r.status);
          return r.text();
        })
        .then(function (text) {
          let res;
          try {
            res = JSON.parse(text);
          } catch (e) {
            console.error('Respons bukan JSON valid:', text);
            throw new Error('Respons server tidak valid (bukan JSON). Cek console (F12) untuk detail.');
          }
          if (!res.ok) throw new Error(res.pesan || 'Gagal mengambil data');

          let terpengaruh = 0;
          const saves = [];

          document.querySelectorAll('.td-tugas-input').forEach(function (tugasInput) {
            const sid = tugasInput.dataset.siswaId;
            const info = res.data[sid];
            if (!info || (!info.uts && !info.uas)) return;

            const utsInput = document.querySelector('.td-uts-input[data-siswa-id="' + sid + '"]');
            const uasInput = document.querySelector('.td-uas-input[data-siswa-id="' + sid + '"]');
            let uts = parseFloat(utsInput.value) || 0;
            let uas = parseFloat(uasInput.value) || 0;

            [[info.uts, utsInput], [info.uas, uasInput]].forEach(function (pasangan) {
              if (!pasangan[0]) return;
              animateCount(pasangan[1], parseFloat(pasangan[1].value) || 0, pasangan[0].nilai, 500);
              pasangan[1].classList.remove('tugas-glow');
              void pasangan[1].offsetWidth; // restart animasi
              pasangan[1].classList.add('tugas-glow');
            });
            if (info.uts) uts = info.uts.nilai;
            if (info.uas) uas = info.uas.nilai;

            terpengaruh++;
            saves.push(
              simpanNilaiLengkap(sid, parseFloat(tugasInput.value) || 0, uts, uas)
                .then(function (r) {
                  if (r.ok) tandaiTersimpan(sid);
                  return r.ok;
                })
                .catch(function () { return false; })
            );
          });

          btnUjian.disabled = false;
          btnUjian.innerHTML = labelAwalUjian;

          if (terpengaruh === 0) {
            showToast('Tidak ada data', 'Belum ada ujian terbit yang sudah dinilai untuk kelas/mapel/semester ini.', true);
            return;
          }

          Promise.all(saves).then(function (hasilArr) {
            const sukses = hasilArr.filter(Boolean).length;
            if (sukses === terpengaruh) {
              showToast('UTS/UAS berhasil ditarik!', terpengaruh + ' siswa, langsung tersimpan ke database.', false);
            } else {
              showToast('Ditarik, sebagian gagal tersimpan', sukses + ' dari ' + terpengaruh + ' tersimpan. Klik "Simpan Nilai" untuk sisanya.', true);
            }
          });
        })
        .catch(function (err) {
          console.error('Tarik UTS/UAS dari Ujian gagal:', err);
          btnUjian.disabled = false;
          btnUjian.innerHTML = labelAwalUjian;
          showToast('Gagal menarik data', err.message || 'Terjadi kesalahan, coba lagi.', true);
        });
    });
  }

  btn.addEventListener('click', tarikOtomatis);
})();
</script>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>