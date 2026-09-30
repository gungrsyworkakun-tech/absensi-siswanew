<?php
/**
 * Fungsi bantu fitur Rapor Sekelas.
 * Di-require oleh rapor/kelas.php dan rapor/cetak_kelas.php (setelah auth.php).
 */

function raporSemesterSekarang() {
    return (int)date('n') >= 7 ? 'Ganjil' : 'Genap';
}

function raporTahunAjaranSekarang() {
    $bulan = (int)date('n');
    $tahun = (int)date('Y');
    $awal  = $bulan >= 7 ? $tahun : $tahun - 1;
    return $awal . '/' . ($awal + 1);
}

function raporOpsiTahunAjaran($rentang = 3) {
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

/**
 * Ambil seluruh data rapor satu siswa untuk satu semester + tahun ajaran:
 * biodata, daftar nilai per mapel, rata-rata, rekap kehadiran periode
 * semester itu saja, dan rincian tugas e-learning yang sudah dinilai.
 * Mengembalikan null jika siswa tidak ditemukan.
 */
function raporDataSiswa($pdo, $siswaId, $semester, $tahunAjaran) {
    $stmt = $pdo->prepare("SELECT s.*, k.nama_kelas, k.wali_kelas FROM siswa s LEFT JOIN kelas k ON s.kelas_id = k.id WHERE s.id = ?");
    $stmt->execute([$siswaId]);
    $siswa = $stmt->fetch();
    if (!$siswa) return null;

    $stmt = $pdo->prepare("
        SELECT n.*, m.nama_mapel, m.kkm FROM nilai n
        JOIN mata_pelajaran m ON n.mapel_id = m.id
        WHERE n.siswa_id = ? AND n.semester = ? AND n.tahun_ajaran = ?
        ORDER BY m.nama_mapel
    ");
    $stmt->execute([$siswaId, $semester, $tahunAjaran]);
    $nilaiList = $stmt->fetchAll();
    $rataRata = count($nilaiList) > 0 ? round(array_sum(array_column($nilaiList, 'nilai_akhir')) / count($nilaiList), 1) : 0;

    // Rekap kehadiran dibatasi ke rentang tanggal semester ini saja (bukan
    // sepanjang waktu), supaya rapor Genap tidak ikut menghitung absensi Ganjil.
    $bagian = explode('/', $tahunAjaran);
    $thAwal  = (int)($bagian[0] ?? date('Y'));
    $thAkhir = (int)($bagian[1] ?? ($thAwal + 1));
    if ($semester === 'Ganjil') { $mulai = "{$thAwal}-07-01"; $selesai = "{$thAwal}-12-31"; }
    else                        { $mulai = "{$thAkhir}-01-01"; $selesai = "{$thAkhir}-06-30"; }

    $stmt = $pdo->prepare("SELECT status, COUNT(*) c FROM absensi WHERE siswa_id = ? AND tanggal BETWEEN ? AND ? GROUP BY status");
    $stmt->execute([$siswaId, $mulai, $selesai]);
    $rekapAbsen = ['Hadir' => 0, 'Izin' => 0, 'Sakit' => 0, 'Alpa' => 0];
    foreach ($stmt->fetchAll() as $r) { $rekapAbsen[$r['status']] = $r['c']; }

    $stmt = $pdo->prepare("
        SELECT m.nama_mapel, et.judul, et.deadline, ep.nilai, ep.tanggal_kumpul
        FROM elearning_pengumpulan ep
        JOIN elearning_tugas et ON ep.tugas_id = et.id
        JOIN mata_pelajaran m ON et.mapel_id = m.id
        WHERE ep.siswa_id = ? AND ep.nilai IS NOT NULL AND et.semester = ? AND et.tahun_ajaran = ?
        ORDER BY m.nama_mapel, et.deadline
    ");
    $stmt->execute([$siswaId, $semester, $tahunAjaran]);
    $tugasList = $stmt->fetchAll();

    return compact('siswa', 'nilaiList', 'rataRata', 'rekapAbsen', 'tugasList');
}