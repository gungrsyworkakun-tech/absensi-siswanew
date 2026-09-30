<?php
// Set timezone eksplisit — INI PENYEBAB PALING UMUM ujian dianggap "Belum
// dibuka"/"Ditutup" padahal jamnya sudah lewat: tanpa ini PHP default ke UTC,
// sehingga waktu_mulai/waktu_selesai yang disimpan (angka jam apa adanya,
// misalnya "15:42") dibaca ulang seolah-olah UTC, meleset dari jam asli
// Indonesia. HARUS SAMA dengan timezone di absensi/gps.php dan index.php
// (dashboard), supaya jam yang dilihat siswa/guru konsisten di semua halaman.
//   WIB (Jakarta/Sumatera/Jawa/Kalbar-Kalteng) -> 'Asia/Jakarta'
//   WITA (Bali/NTB/NTT/Kalimantan lainnya/Sulawesi) -> 'Asia/Makassar'
//   WIT (Maluku/Papua) -> 'Asia/Jayapura'
date_default_timezone_set('Asia/Makassar');

/**
 * Fungsi bantu modul Ujian (UTS/UAS).
 * Di-require oleh semua halaman di folder ujian/ (setelah auth.php).
 */

function ujianTahunAjaran() {
    $bulan = (int)date('n');
    $tahun = (int)date('Y');
    $awal  = $bulan >= 7 ? $tahun : $tahun - 1;
    return $awal . '/' . ($awal + 1);
}

function ujianOpsiTahun($rentang = 2) {
    $bulan = (int)date('n');
    $tahun = (int)date('Y');
    $awalSekarang = $bulan >= 7 ? $tahun : $tahun - 1;
    $opsi = [];
    for ($i = -$rentang; $i <= $rentang; $i++) {
        $a = $awalSekarang + $i;
        $opsi[] = $a . '/' . ($a + 1);
    }
    return $opsi;
}

function ujianAmbil($pdo, $id) {
    $stmt = $pdo->prepare("
        SELECT u.*, k.nama_kelas, m.nama_mapel
        FROM ujian u
        JOIN kelas k ON k.id = u.kelas_id
        JOIN mata_pelajaran m ON m.id = u.mapel_id
        WHERE u.id = ?
    ");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

/** Admin boleh mengelola semua ujian; guru hanya ujian buatannya sendiri. */
function ujianBolehKelola($user, $ujian) {
    if (!$ujian) return false;
    if ($user['role'] === 'admin') return true;
    return (int)$ujian['guru_id'] === (int)$user['id'];
}

/** Batas akhir pengerjaan seorang peserta (timestamp): mana yang lebih dulu, durasi atau penutupan ujian. */
function ujianDeadline($ujian, $peserta) {
    $batasDurasi = strtotime($peserta['waktu_mulai']) + ((int)$ujian['durasi_menit'] * 60);
    return min($batasDurasi, strtotime($ujian['waktu_selesai']));
}

function ujianBadgeJenis($jenis) {
    $warna = $jenis === 'UTS' ? 'info' : 'brand';
    return "<span class='badge-soft {$warna}'>" . clean($jenis) . "</span>";
}

function ujianFormatWaktu($datetime) {
    if (empty($datetime)) return '-';
    return date('d/m/Y H:i', strtotime($datetime));
}

/** Simpan satu jawaban siswa (divalidasi di server: soal harus milik ujian ini). */
function ujianSimpanJawaban($pdo, $pesertaId, $ujianId, $soalId, $jawaban) {
    $stmt = $pdo->prepare("SELECT tipe FROM ujian_soal WHERE id = ? AND ujian_id = ?");
    $stmt->execute([$soalId, $ujianId]);
    $tipe = $stmt->fetchColumn();
    if (!$tipe) return false;

    $jawaban = is_string($jawaban) ? $jawaban : '';
    if ($tipe === 'pilihan_ganda') {
        $jawaban = strtolower(trim($jawaban));
        if (!in_array($jawaban, ['a', 'b', 'c', 'd', 'e'], true)) $jawaban = '';
    } else {
        $jawaban = mb_substr($jawaban, 0, 10000);
    }

    $stmt = $pdo->prepare("
        INSERT INTO ujian_jawaban (peserta_id, soal_id, jawaban) VALUES (?,?,?)
        ON DUPLICATE KEY UPDATE jawaban = VALUES(jawaban)
    ");
    $stmt->execute([$pesertaId, $soalId, $jawaban]);
    return true;
}

/**
 * Hitung ulang nilai satu peserta. Skala 0-100 berdasarkan proporsi bobot:
 * nilai = (total skor diperoleh / total bobot seluruh soal) x 100.
 * dinilai = 1 jika semua soal essay sudah punya skor.
 */
function ujianHitungUlang($pdo, $pesertaId) {
    $stmt = $pdo->prepare("SELECT ujian_id FROM ujian_peserta WHERE id = ?");
    $stmt->execute([$pesertaId]);
    $ujianId = $stmt->fetchColumn();
    if (!$ujianId) return;

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(bobot),0) FROM ujian_soal WHERE ujian_id = ?");
    $stmt->execute([$ujianId]);
    $totalBobot = (float)$stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT s.tipe,
               COALESCE(SUM(j.skor),0) AS skor,
               SUM(CASE WHEN j.skor IS NULL THEN 1 ELSE 0 END) AS belum
        FROM ujian_soal s
        LEFT JOIN ujian_jawaban j ON j.soal_id = s.id AND j.peserta_id = ?
        WHERE s.ujian_id = ?
        GROUP BY s.tipe
    ");
    $stmt->execute([$pesertaId, $ujianId]);

    $skorPg = 0; $skorEssay = 0; $essayBelum = 0;
    foreach ($stmt->fetchAll() as $r) {
        if ($r['tipe'] === 'pilihan_ganda') {
            $skorPg = (float)$r['skor'];
        } else {
            $skorEssay  = (float)$r['skor'];
            $essayBelum = (int)$r['belum'];
        }
    }

    $nilaiPg    = $totalBobot > 0 ? round($skorPg / $totalBobot * 100, 2) : 0;
    $nilaiEssay = $totalBobot > 0 ? round($skorEssay / $totalBobot * 100, 2) : 0;
    $nilaiAkhir = round($nilaiPg + $nilaiEssay, 2);
    $dinilai    = $essayBelum === 0 ? 1 : 0;

    $stmt = $pdo->prepare("UPDATE ujian_peserta SET nilai_pg = ?, nilai_essay = ?, nilai_akhir = ?, dinilai = ? WHERE id = ?");
    $stmt->execute([$nilaiPg, $nilaiEssay, $nilaiAkhir, $dinilai, $pesertaId]);
}

/**
 * Akhiri ujian seorang peserta: pilihan ganda dinilai otomatis dari kunci,
 * essay kosong otomatis 0, essay berisi menunggu penilaian guru (skor NULL).
 */
function ujianSelesaikan($pdo, $pesertaId, $waktuSelesai = null) {
    $stmt = $pdo->prepare("SELECT * FROM ujian_peserta WHERE id = ?");
    $stmt->execute([$pesertaId]);
    $p = $stmt->fetch();
    if (!$p || $p['status'] === 'Selesai') return;

    $stmt = $pdo->prepare("SELECT id, tipe, kunci, bobot FROM ujian_soal WHERE ujian_id = ?");
    $stmt->execute([$p['ujian_id']]);
    $soalList = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT soal_id, jawaban FROM ujian_jawaban WHERE peserta_id = ?");
    $stmt->execute([$pesertaId]);
    $jawabanMap = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $upsert = $pdo->prepare("
        INSERT INTO ujian_jawaban (peserta_id, soal_id, jawaban, skor) VALUES (?,?,?,?)
        ON DUPLICATE KEY UPDATE skor = VALUES(skor)
    ");
    foreach ($soalList as $s) {
        $jaw = (string)($jawabanMap[$s['id']] ?? '');
        if ($s['tipe'] === 'pilihan_ganda') {
            $skor = ($jaw !== '' && $jaw === $s['kunci']) ? (float)$s['bobot'] : 0;
        } else {
            $skor = trim($jaw) === '' ? 0 : null;
        }
        $upsert->execute([$pesertaId, $s['id'], $jaw, $skor]);
    }

    $stmt = $pdo->prepare("UPDATE ujian_peserta SET status = 'Selesai', waktu_selesai = ? WHERE id = ?");
    $stmt->execute([$waktuSelesai ?: date('Y-m-d H:i:s'), $pesertaId]);

    ujianHitungUlang($pdo, $pesertaId);
}

/** Akhiri otomatis peserta yang waktunya sudah habis tapi belum menekan "Selesai" (misal browser ditutup). */
function ujianFinalisasiKedaluwarsa($pdo, $ujian) {
    $stmt = $pdo->prepare("SELECT * FROM ujian_peserta WHERE ujian_id = ? AND status = 'Berlangsung'");
    $stmt->execute([$ujian['id']]);
    foreach ($stmt->fetchAll() as $p) {
        $deadline = ujianDeadline($ujian, $p);
        if (time() >= $deadline) {
            ujianSelesaikan($pdo, $p['id'], date('Y-m-d H:i:s', $deadline));
        }
    }
}

/* ==================== Gambar soal ==================== */

function ujianDirGambar() {
    $dir = __DIR__ . '/../uploads/ujian_soal/';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    return $dir;
}

function ujianUrlGambar($nama) {
    return BASE_URL . '/uploads/ujian_soal/' . rawurlencode($nama);
}

/** Simpan bytes gambar (sudah divalidasi isinya benar-benar gambar). Mengembalikan nama file, atau null jika ditolak. */
function ujianSimpanBytesGambar($bytes) {
    if (!is_string($bytes) || $bytes === '' || strlen($bytes) > 3 * 1024 * 1024) return null;
    $info = @getimagesizefromstring($bytes);
    if (!$info) return null;
    $ekstensi = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];
    if (!isset($ekstensi[$info[2]])) return null;

    $dir = ujianDirGambar();
    if (!is_dir($dir) || !is_writable($dir)) return null;

    $nama = bin2hex(random_bytes(12)) . '.' . $ekstensi[$info[2]];
    if (@file_put_contents($dir . $nama, $bytes) === false) return null;
    return $nama;
}

/** Proses file dari <input type=file>. Hasil: ['nama' => string|null] atau ['error' => pesan]. */
function ujianSimpanUploadGambar($file) {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return ['nama' => null];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['error' => 'Gambar gagal diunggah (kemungkinan melebihi batas ukuran upload server).'];
    }
    if ($file['size'] > 3 * 1024 * 1024) return ['error' => 'Ukuran gambar maksimal 3 MB.'];
    $nama = ujianSimpanBytesGambar(file_get_contents($file['tmp_name']));
    if (!$nama) {
        return ['error' => 'Gambar tidak valid. Gunakan JPG, PNG, GIF, atau WebP, dan pastikan folder uploads/ujian_soal bisa ditulis oleh server.'];
    }
    return ['nama' => $nama];
}

function ujianHapusGambar($nama) {
    if ($nama && preg_match('/^[a-f0-9]{24}\.(jpg|png|gif|webp)$/', $nama)) {
        @unlink(ujianDirGambar() . $nama);
    }
}

/** Buang sisa hasil impor Word yang belum dikonfirmasi (beserta gambarnya). */
function ujianBersihkanImpor() {
    if (!empty($_SESSION['ujian_import']['soal'])) {
        foreach ($_SESSION['ujian_import']['soal'] as $s) {
            if (!empty($s['gambar'])) ujianHapusGambar($s['gambar']);
        }
    }
    unset($_SESSION['ujian_import']);
}