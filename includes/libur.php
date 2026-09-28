<?php
/**
 * Cek apakah tanggal tertentu adalah hari libur.
 * Sabtu & Minggu otomatis dianggap libur tanpa perlu diatur admin.
 * Tanggal merah/libur nasional lain dicek dari tabel hari_libur.
 *
 * @param PDO $pdo
 * @param string $tanggal format Y-m-d
 * @return array ['libur' => bool, 'keterangan' => string|null, 'jenis' => string|null]
 */
function cekHariLibur($pdo, $tanggal) {
    $timestamp = strtotime($tanggal);
    $hariAngka = (int)date('N', $timestamp); // 1=Senin ... 6=Sabtu, 7=Minggu

    if ($hariAngka >= 6) {
        return [
            'libur' => true,
            'keterangan' => $hariAngka === 6 ? 'Akhir pekan (Sabtu)' : 'Akhir pekan (Minggu)',
            'jenis' => 'Weekend',
        ];
    }

    $stmt = $pdo->prepare("SELECT keterangan, jenis FROM hari_libur WHERE tanggal = ?");
    $stmt->execute([$tanggal]);
    $row = $stmt->fetch();

    if ($row) {
        return ['libur' => true, 'keterangan' => $row['keterangan'], 'jenis' => $row['jenis']];
    }

    return ['libur' => false, 'keterangan' => null, 'jenis' => null];
}

/**
 * Hitung jumlah hari efektif (Senin-Jumat, dikurangi tanggal merah yang
 * terdaftar) dalam satu bulan. Dipakai untuk referensi di rekap absensi.
 *
 * @param PDO $pdo
 * @param string $bulan format Y-m
 * @return int
 */
function hitungHariEfektifBulan($pdo, $bulan) {
    $mulai = $bulan . '-01';
    $totalHari = (int)date('t', strtotime($mulai));

    // Ambil semua tanggal merah yang jatuh di bulan ini (Sen-Jum saja yang relevan,
    // karena Sabtu/Minggu sudah otomatis tidak dihitung di loop bawah)
    $stmt = $pdo->prepare("SELECT tanggal FROM hari_libur WHERE DATE_FORMAT(tanggal, '%Y-%m') = ?");
    $stmt->execute([$bulan]);
    $tanggalMerah = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $hariEfektif = 0;
    for ($tgl = 1; $tgl <= $totalHari; $tgl++) {
        $tanggalIni = sprintf('%s-%02d', $bulan, $tgl);
        $hariAngka = (int)date('N', strtotime($tanggalIni));
        if ($hariAngka >= 6) continue; // Sabtu/Minggu
        if (in_array($tanggalIni, $tanggalMerah)) continue; // tanggal merah
        $hariEfektif++;
    }
    return $hariEfektif;
}