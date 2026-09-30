<?php
// Menyajikan lampiran izin (surat dokter / surat orang tua) dengan pengecekan hak akses.
// File disimpan di uploads/izin (tidak bisa dibuka langsung lewat URL), hanya lewat script ini.
require_once __DIR__ . '/../includes/auth.php';

$user = currentUser();
requireRole(['siswa', 'wali_kelas', 'guru', 'admin']);

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT siswa_id, kelas_id, lampiran FROM izin WHERE id = ?");
$stmt->execute([$id]);
$izin = $stmt->fetch();

function tolakLampiran($kode, $pesan) {
    http_response_code($kode);
    header('Content-Type: text/plain; charset=utf-8');
    echo $pesan;
    exit;
}

if (!$izin || empty($izin['lampiran'])) {
    tolakLampiran(404, 'Lampiran tidak ditemukan.');
}

// Hak akses: pemilik, wali kelas dari kelas tersebut, guru, dan admin
$boleh = false;
if ($user['role'] === 'siswa') {
    $boleh = ((int)$user['siswa_id'] === (int)$izin['siswa_id']);
} elseif ($user['role'] === 'wali_kelas') {
    $kelasSaya = kelasWaliSaya($pdo, $user['id']);
    $boleh = $kelasSaya && (int)$kelasSaya['id'] === (int)$izin['kelas_id'];
} else { // guru, admin
    $boleh = true;
}
if (!$boleh) {
    tolakLampiran(403, 'Anda tidak berhak melihat lampiran ini.');
}

$path = realpath(__DIR__ . '/../uploads/izin') . DIRECTORY_SEPARATOR . basename($izin['lampiran']);
if (!is_file($path)) {
    tolakLampiran(404, 'Berkas lampiran tidak ada di server.');
}

$mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
if (!in_array($mime, ['image/jpeg', 'image/png', 'application/pdf'], true)) {
    tolakLampiran(415, 'Tipe berkas tidak didukung.');
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="lampiran-izin-' . $id . '.' . pathinfo($path, PATHINFO_EXTENSION) . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=0, no-store');
readfile($path);
exit;