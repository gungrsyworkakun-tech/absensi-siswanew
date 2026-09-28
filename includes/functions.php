<?php
/**
 * Kumpulan fungsi bantu (helper) yang dipakai di seluruh aplikasi
 */

function clean($str) {
    return htmlspecialchars(trim($str ?? ''), ENT_QUOTES, 'UTF-8');
}

function redirect($path) {
    header("Location: " . BASE_URL . "/" . ltrim($path, '/'));
    exit;
}

function setFlash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function showFlash() {
    if (!empty($_SESSION['flash'])) {
        $type = $_SESSION['flash']['type'] === 'error' ? 'danger' : $_SESSION['flash']['type'];
        $msg  = clean($_SESSION['flash']['message']);
        echo "<div class='alert alert-{$type} alert-dismissible fade show' role='alert'>
                {$msg}
                <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
              </div>";
        unset($_SESSION['flash']);
    }
}

function formatTanggalIndo($tanggal) {
    if (empty($tanggal)) return '-';
    $bulan = ['01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April','05'=>'Mei','06'=>'Juni',
              '07'=>'Juli','08'=>'Agustus','09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'];
    $d = date('d', strtotime($tanggal));
    $m = date('m', strtotime($tanggal));
    $y = date('Y', strtotime($tanggal));
    return "{$d} {$bulan[$m]} {$y}";
}

function hitungPredikat($nilai) {
    if ($nilai >= 90) return 'A';
    if ($nilai >= 80) return 'B';
    if ($nilai >= 70) return 'C';
    return 'D';
}

function badgeStatusAbsen($status) {
    $map = [
        'Hadir' => 'good',
        'Izin'  => 'warn',
        'Sakit' => 'info',
        'Alpa'  => 'bad',
    ];
    $color = $map[$status] ?? 'brand';
    return "<span class='badge-soft {$color}'>{$status}</span>";
}

/**
 * Ambil ID kelas yang diampu oleh akun wali_kelas yang sedang login.
 * Mengembalikan null jika belum dihubungkan ke kelas manapun.
 */
function kelasWaliSaya($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT id, nama_kelas FROM kelas WHERE wali_kelas_user_id = ? LIMIT 1");
    $stmt->execute([$userId]);
    return $stmt->fetch();
}

/**
 * Hitung jarak antara dua titik koordinat (meter) menggunakan formula Haversine
 */
function hitungJarakMeter($lat1, $lng1, $lat2, $lng2) {
    $earthRadius = 6371000; // meter
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) * sin($dLat / 2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLng / 2) * sin($dLng / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $earthRadius * $c;
}

function badgeStatusGps($status) {
    $color = $status === 'Berhasil' ? 'good' : 'bad';
    return "<span class='badge-soft {$color}'>{$status}</span>";
}

function kategoriBadge($kategori) {
    $map = [
        'Penting'  => 'bad',
        'Akademik' => 'info',
        'Kegiatan' => 'good',
        'Umum'     => 'brand',
    ];
    $color = $map[$kategori] ?? 'brand';
    return "<span class='badge-soft {$color}'>{$kategori}</span>";
}

function formatWaktuIndo($datetime) {
    if (empty($datetime)) return '-';
    return date('d/m/Y H:i', strtotime($datetime));
}

function sisaWaktu($deadline) {
    $now = time();
    $target = strtotime($deadline);
    $diff = $target - $now;
    if ($diff <= 0) return ['label' => 'Lewat tenggat', 'class' => 'bad'];
    $jam = floor($diff / 3600);
    if ($jam < 24) return ['label' => "{$jam} jam lagi", 'class' => 'warn'];
    $hari = floor($jam / 24);
    return ['label' => "{$hari} hari lagi", 'class' => 'good'];
}
