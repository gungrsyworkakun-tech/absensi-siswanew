-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: db
-- Waktu pembuatan: 02 Okt 2026 pada 07.26
-- Versi server: 8.0.46
-- Versi PHP: 8.3.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Basis data: `db_absensi_sekolah`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `absensi`
--

CREATE TABLE `absensi` (
  `id` int NOT NULL,
  `siswa_id` int NOT NULL,
  `kelas_id` int NOT NULL,
  `tanggal` date NOT NULL,
  `status` enum('Hadir','Izin','Sakit','Alpa') NOT NULL DEFAULT 'Hadir',
  `jam_pulang` time DEFAULT NULL,
  `latitude_pulang` decimal(10,7) DEFAULT NULL,
  `longitude_pulang` decimal(10,7) DEFAULT NULL,
  `keterangan` varchar(255) DEFAULT NULL,
  `input_oleh` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `jarak_pulang_meter` decimal(10,2) DEFAULT NULL,
  `terlambat_menit` int NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data untuk tabel `absensi`
--

INSERT INTO `absensi` (`id`, `siswa_id`, `kelas_id`, `tanggal`, `status`, `jam_pulang`, `latitude_pulang`, `longitude_pulang`, `keterangan`, `input_oleh`, `created_at`, `jarak_pulang_meter`, `terlambat_menit`) VALUES
(1, 1, 1, '2026-09-28', 'Hadir', '14:11:41', -8.7845599, 115.1942623, 'Absen mandiri via GPS', 'Sistem GPS', '2026-09-28 06:01:41', 0.33, 0),
(3, 1, 1, '2026-09-29', 'Hadir', NULL, NULL, NULL, 'Absen mandiri via GPS', 'Sistem GPS', '2026-09-29 00:00:41', NULL, 0),
(4, 1, 1, '2026-09-30', 'Hadir', NULL, NULL, NULL, 'Absen mandiri via GPS', 'Sistem GPS', '2026-09-30 00:32:30', NULL, 0),
(5, 1, 1, '2026-10-01', 'Izin', NULL, NULL, NULL, 'Izin (disetujui): acara agama', 'Izin - yoga', '2026-09-30 03:23:25', NULL, 0),
(6, 6, 1, '2026-10-06', 'Sakit', NULL, NULL, NULL, 'Sakit (disetujui): sakit', 'Izin - yoga', '2026-10-02 06:22:24', NULL, 0);

-- --------------------------------------------------------

--
-- Struktur dari tabel `absensi_gps`
--

CREATE TABLE `absensi_gps` (
  `id` int NOT NULL,
  `siswa_id` int NOT NULL,
  `kelas_id` int NOT NULL,
  `tanggal` date NOT NULL,
  `waktu` time NOT NULL,
  `tipe` enum('Masuk','Pulang') NOT NULL DEFAULT 'Masuk',
  `latitude` decimal(10,7) NOT NULL,
  `longitude` decimal(10,7) NOT NULL,
  `jarak_meter` decimal(10,2) NOT NULL,
  `akurasi_meter` decimal(10,2) DEFAULT NULL,
  `status` enum('Berhasil','Ditolak') NOT NULL,
  `alasan_ditolak` varchar(150) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `terlambat_menit` int NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data untuk tabel `absensi_gps`
--

INSERT INTO `absensi_gps` (`id`, `siswa_id`, `kelas_id`, `tanggal`, `waktu`, `tipe`, `latitude`, `longitude`, `jarak_meter`, `akurasi_meter`, `status`, `alasan_ditolak`, `created_at`, `terlambat_menit`) VALUES
(1, 1, 1, '2026-09-28', '09:33:35', 'Masuk', -8.7845460, 115.1942552, 15162.86, 81.00, 'Ditolak', 'Di luar jam absen masuk. Sekarang pukul 09:33, jam dibuka 06:00–08:00', '2026-09-28 01:33:35', 0),
(2, 1, 1, '2026-09-28', '09:55:59', 'Masuk', -8.7845465, 115.1942552, 15162.91, 85.00, 'Ditolak', 'Di luar jam absen masuk. Sekarang pukul 09:55, jam dibuka 06:00–08:00', '2026-09-28 01:55:59', 0),
(3, 1, 1, '2026-09-28', '13:57:32', 'Masuk', -8.7845605, 115.1942585, 0.10, 85.00, 'Ditolak', 'Di luar jam absen masuk. Sekarang pukul 13:57, jam dibuka 14:00–15:00', '2026-09-28 05:57:32', 0),
(4, 1, 1, '2026-09-28', '14:01:41', 'Masuk', -8.7845730, 115.1942671, 1.67, 89.00, 'Berhasil', NULL, '2026-09-28 06:01:41', 0),
(5, 1, 1, '2026-09-28', '14:08:55', 'Pulang', -8.7845299, 115.1942487, 3.56, 77.00, 'Ditolak', 'Di luar jam absen pulang. Sekarang pukul 14:08, jam dibuka 17:00–20:00', '2026-09-28 06:08:55', 0),
(6, 1, 1, '2026-09-28', '14:09:00', 'Pulang', -8.7845299, 115.1942487, 3.56, 77.00, 'Ditolak', 'Di luar jam absen pulang. Sekarang pukul 14:09, jam dibuka 17:00–20:00', '2026-09-28 06:09:00', 0),
(7, 1, 1, '2026-09-28', '14:09:01', 'Pulang', -8.7845299, 115.1942487, 3.56, 77.00, 'Ditolak', 'Di luar jam absen pulang. Sekarang pukul 14:09, jam dibuka 17:00–20:00', '2026-09-28 06:09:01', 0),
(8, 1, 1, '2026-09-28', '14:09:02', 'Pulang', -8.7845299, 115.1942487, 3.56, 77.00, 'Ditolak', 'Di luar jam absen pulang. Sekarang pukul 14:09, jam dibuka 17:00–20:00', '2026-09-28 06:09:02', 0),
(9, 1, 1, '2026-09-28', '14:09:02', 'Pulang', -8.7845299, 115.1942487, 3.56, 77.00, 'Ditolak', 'Di luar jam absen pulang. Sekarang pukul 14:09, jam dibuka 17:00–20:00', '2026-09-28 06:09:02', 0),
(10, 1, 1, '2026-09-28', '14:09:04', 'Pulang', -8.7845299, 115.1942487, 3.56, 77.00, 'Ditolak', 'Di luar jam absen pulang. Sekarang pukul 14:09, jam dibuka 17:00–20:00', '2026-09-28 06:09:04', 0),
(11, 1, 1, '2026-09-28', '14:09:05', 'Pulang', -8.7845493, 115.1942564, 1.24, 81.00, 'Ditolak', 'Di luar jam absen pulang. Sekarang pukul 14:09, jam dibuka 17:00–20:00', '2026-09-28 06:09:05', 0),
(12, 1, 1, '2026-09-28', '14:09:06', 'Pulang', -8.7845493, 115.1942564, 1.24, 81.00, 'Ditolak', 'Di luar jam absen pulang. Sekarang pukul 14:09, jam dibuka 17:00–20:00', '2026-09-28 06:09:06', 0),
(13, 1, 1, '2026-09-28', '14:09:28', 'Pulang', -8.7845530, 115.1942609, 0.81, 78.00, 'Berhasil', NULL, '2026-09-28 06:09:28', 0),
(14, 1, 1, '2026-09-28', '14:09:34', 'Pulang', -8.7845530, 115.1942609, 0.81, 78.00, 'Berhasil', NULL, '2026-09-28 06:09:34', 0),
(15, 1, 1, '2026-09-28', '14:10:08', 'Pulang', -8.7845495, 115.1942559, 1.24, 78.00, 'Berhasil', NULL, '2026-09-28 06:10:08', 0),
(16, 1, 1, '2026-09-28', '14:10:18', 'Pulang', -8.7845595, 115.1942583, 0.13, 85.00, 'Berhasil', NULL, '2026-09-28 06:10:18', 0),
(17, 1, 1, '2026-09-28', '14:10:19', 'Pulang', -8.7845595, 115.1942583, 0.13, 85.00, 'Berhasil', NULL, '2026-09-28 06:10:19', 0),
(18, 1, 1, '2026-09-28', '14:10:49', 'Pulang', -8.7845668, 115.1942650, 0.98, 85.00, 'Berhasil', NULL, '2026-09-28 06:10:49', 0),
(19, 1, 1, '2026-09-28', '14:10:50', 'Pulang', -8.7845668, 115.1942650, 0.98, 85.00, 'Berhasil', NULL, '2026-09-28 06:10:50', 0),
(20, 1, 1, '2026-09-28', '14:10:50', 'Pulang', -8.7845668, 115.1942650, 0.98, 85.00, 'Berhasil', NULL, '2026-09-28 06:10:50', 0),
(21, 1, 1, '2026-09-28', '14:10:51', 'Pulang', -8.7845668, 115.1942650, 0.98, 85.00, 'Berhasil', NULL, '2026-09-28 06:10:51', 0),
(22, 1, 1, '2026-09-28', '14:10:51', 'Pulang', -8.7845668, 115.1942650, 0.98, 85.00, 'Berhasil', NULL, '2026-09-28 06:10:51', 0),
(23, 1, 1, '2026-09-28', '14:10:52', 'Pulang', -8.7845668, 115.1942650, 0.98, 85.00, 'Berhasil', NULL, '2026-09-28 06:10:52', 0),
(24, 1, 1, '2026-09-28', '14:10:53', 'Pulang', -8.7845668, 115.1942650, 0.98, 85.00, 'Berhasil', NULL, '2026-09-28 06:10:53', 0),
(25, 1, 1, '2026-09-28', '14:10:53', 'Pulang', -8.7845668, 115.1942650, 0.98, 85.00, 'Berhasil', NULL, '2026-09-28 06:10:53', 0),
(26, 1, 1, '2026-09-28', '14:10:55', 'Pulang', -8.7845668, 115.1942650, 0.98, 85.00, 'Berhasil', NULL, '2026-09-28 06:10:55', 0),
(27, 1, 1, '2026-09-28', '14:10:56', 'Pulang', -8.7845668, 115.1942650, 0.98, 85.00, 'Berhasil', NULL, '2026-09-28 06:10:56', 0),
(28, 1, 1, '2026-09-28', '14:10:57', 'Pulang', -8.7845668, 115.1942650, 0.98, 85.00, 'Berhasil', NULL, '2026-09-28 06:10:57', 0),
(29, 1, 1, '2026-09-28', '14:10:57', 'Pulang', -8.7845493, 115.1942557, 1.26, 81.00, 'Berhasil', NULL, '2026-09-28 06:10:57', 0),
(30, 1, 1, '2026-09-28', '14:10:58', 'Pulang', -8.7845493, 115.1942557, 1.26, 81.00, 'Berhasil', NULL, '2026-09-28 06:10:58', 0),
(31, 1, 1, '2026-09-28', '14:10:58', 'Pulang', -8.7845493, 115.1942557, 1.26, 81.00, 'Berhasil', NULL, '2026-09-28 06:10:58', 0),
(32, 1, 1, '2026-09-28', '14:10:59', 'Pulang', -8.7845493, 115.1942557, 1.26, 81.00, 'Berhasil', NULL, '2026-09-28 06:10:59', 0),
(33, 1, 1, '2026-09-28', '14:11:00', 'Pulang', -8.7845493, 115.1942557, 1.26, 81.00, 'Berhasil', NULL, '2026-09-28 06:11:00', 0),
(34, 1, 1, '2026-09-28', '14:11:01', 'Pulang', -8.7845493, 115.1942557, 1.26, 81.00, 'Berhasil', NULL, '2026-09-28 06:11:01', 0),
(35, 1, 1, '2026-09-28', '14:11:01', 'Pulang', -8.7845493, 115.1942557, 1.26, 81.00, 'Berhasil', NULL, '2026-09-28 06:11:01', 0),
(36, 1, 1, '2026-09-28', '14:11:02', 'Pulang', -8.7845493, 115.1942557, 1.26, 81.00, 'Berhasil', NULL, '2026-09-28 06:11:02', 0),
(37, 1, 1, '2026-09-28', '14:11:02', 'Pulang', -8.7845493, 115.1942557, 1.26, 81.00, 'Berhasil', NULL, '2026-09-28 06:11:02', 0),
(38, 1, 1, '2026-09-28', '14:11:03', 'Pulang', -8.7845493, 115.1942557, 1.26, 81.00, 'Berhasil', NULL, '2026-09-28 06:11:03', 0),
(39, 1, 1, '2026-09-28', '14:11:04', 'Pulang', -8.7845493, 115.1942557, 1.26, 81.00, 'Berhasil', NULL, '2026-09-28 06:11:04', 0),
(40, 1, 1, '2026-09-28', '14:11:04', 'Pulang', -8.7845493, 115.1942557, 1.26, 81.00, 'Berhasil', NULL, '2026-09-28 06:11:04', 0),
(41, 1, 1, '2026-09-28', '14:11:05', 'Pulang', -8.7845493, 115.1942557, 1.26, 81.00, 'Berhasil', NULL, '2026-09-28 06:11:05', 0),
(42, 1, 1, '2026-09-28', '14:11:05', 'Pulang', -8.7845493, 115.1942557, 1.26, 81.00, 'Berhasil', NULL, '2026-09-28 06:11:05', 0),
(43, 1, 1, '2026-09-28', '14:11:06', 'Pulang', -8.7845493, 115.1942557, 1.26, 81.00, 'Berhasil', NULL, '2026-09-28 06:11:06', 0),
(44, 1, 1, '2026-09-28', '14:11:07', 'Pulang', -8.7845493, 115.1942557, 1.26, 81.00, 'Berhasil', NULL, '2026-09-28 06:11:07', 0),
(45, 1, 1, '2026-09-28', '14:11:08', 'Pulang', -8.7845441, 115.1942570, 1.80, 74.00, 'Berhasil', NULL, '2026-09-28 06:11:08', 0),
(46, 1, 1, '2026-09-28', '14:11:09', 'Pulang', -8.7845441, 115.1942570, 1.80, 74.00, 'Berhasil', NULL, '2026-09-28 06:11:09', 0),
(47, 1, 1, '2026-09-28', '14:11:10', 'Pulang', -8.7845441, 115.1942570, 1.80, 74.00, 'Berhasil', NULL, '2026-09-28 06:11:10', 0),
(48, 1, 1, '2026-09-28', '14:11:41', 'Pulang', -8.7845599, 115.1942623, 0.33, 81.00, 'Berhasil', NULL, '2026-09-28 06:11:41', 0),
(49, 1, 1, '2026-09-29', '07:04:11', 'Masuk', -8.7845237, 115.1942457, 4.31, 74.00, 'Ditolak', 'Di luar jam absen masuk. Sekarang pukul 07:04, jam dibuka 14:00–15:00', '2026-09-28 23:04:11', 0),
(50, 1, 1, '2026-09-29', '07:04:16', 'Masuk', -8.7845237, 115.1942457, 4.31, 74.00, 'Ditolak', 'Di luar jam absen masuk. Sekarang pukul 07:04, jam dibuka 14:00–15:00', '2026-09-28 23:04:16', 0),
(51, 1, 1, '2026-09-29', '07:04:38', 'Masuk', -8.7845533, 115.1942917, 3.64, 74.00, 'Ditolak', 'Di luar jam absen masuk. Sekarang pukul 07:04, jam dibuka 14:00–15:00', '2026-09-28 23:04:38', 0),
(52, 1, 1, '2026-09-29', '07:35:59', 'Masuk', -8.7845733, 115.1943235, 7.21, 81.00, 'Ditolak', 'Di luar jam absen masuk. Sekarang pukul 07:35, jam dibuka 08:00–09:00', '2026-09-28 23:35:59', 0),
(53, 1, 1, '2026-09-29', '07:36:09', 'Masuk', -8.7845733, 115.1943235, 7.21, 81.00, 'Ditolak', 'Di luar jam absen masuk. Sekarang pukul 07:36, jam dibuka 08:00–09:00', '2026-09-28 23:36:09', 0),
(54, 1, 1, '2026-09-29', '08:00:41', 'Masuk', -8.7845385, 115.1942504, 2.59, 85.00, 'Berhasil', NULL, '2026-09-29 00:00:41', 0),
(55, 1, 1, '2026-09-29', '08:00:53', 'Pulang', -8.7845219, 115.1942486, 4.41, 72.00, 'Ditolak', 'Di luar jam absen pulang. Sekarang pukul 08:00, jam dibuka 14:00–20:00', '2026-09-29 00:00:53', 0),
(56, 1, 1, '2026-09-29', '08:00:55', 'Pulang', -8.7845219, 115.1942486, 4.41, 72.00, 'Ditolak', 'Di luar jam absen pulang. Sekarang pukul 08:00, jam dibuka 14:00–20:00', '2026-09-29 00:00:55', 0),
(57, 1, 1, '2026-09-29', '11:37:15', 'Pulang', -8.7845373, 115.1942517, 2.67, 81.00, 'Ditolak', 'Di luar jam absen pulang. Sekarang pukul 11:37, jam dibuka 14:00–20:00', '2026-09-29 03:37:15', 0),
(58, 1, 1, '2026-09-29', '11:37:15', 'Pulang', -8.7845373, 115.1942517, 2.67, 81.00, 'Ditolak', 'Di luar jam absen pulang. Sekarang pukul 11:37, jam dibuka 14:00–20:00', '2026-09-29 03:37:15', 0),
(59, 1, 1, '2026-09-29', '12:39:11', 'Pulang', -8.7845544, 115.1942937, 3.83, 73.00, 'Ditolak', 'Di luar jam absen pulang. Sekarang pukul 12:39, jam dibuka 14:00–20:00', '2026-09-29 04:39:11', 0),
(60, 1, 1, '2026-09-30', '08:32:30', 'Masuk', -8.7845455, 115.1942556, 1.68, 78.00, 'Berhasil', NULL, '2026-09-30 00:32:30', 0),
(61, 1, 1, '2026-09-30', '11:16:28', 'Pulang', -8.7845350, 115.1942528, 2.88, 74.00, 'Ditolak', 'Di luar jam absen pulang. Sekarang pukul 11:16, jam dibuka 14:00–20:00', '2026-09-30 03:16:28', 0),
(62, 1, 1, '2026-09-30', '11:42:42', 'Pulang', -8.7845593, 115.1942581, 0.16, 90.00, 'Ditolak', 'Di luar jam absen pulang. Sekarang pukul 11:42, jam dibuka 14:00–20:00', '2026-09-30 03:42:42', 0),
(63, 1, 1, '2026-10-01', '09:58:12', 'Masuk', -8.7845492, 115.1942571, 1.23, 85.00, 'Ditolak', 'Di luar jam absen masuk. Sekarang pukul 09:58, jam dibuka 08:00–09:00', '2026-10-01 01:58:12', 0),
(64, 1, 1, '2026-10-01', '09:59:27', 'Masuk', -8.7845586, 115.1942595, 0.17, 85.00, 'Ditolak', 'Di luar jam absen masuk. Sekarang pukul 09:59, jam dibuka 08:00–09:00', '2026-10-01 01:59:27', 0),
(65, 1, 1, '2026-10-01', '10:00:21', 'Masuk', -8.7845586, 115.1942595, 0.17, 85.00, 'Ditolak', 'Di luar jam absen masuk. Sekarang pukul 10:00, jam dibuka 08:00–09:00', '2026-10-01 02:00:21', 0),
(66, 1, 1, '2026-10-01', '10:46:11', 'Masuk', -8.7845454, 115.1942560, 1.67, 81.00, 'Ditolak', 'Di luar jam absen masuk. Sekarang pukul 10:46, jam dibuka 08:00–09:00', '2026-10-01 02:46:11', 0),
(69, 6, 1, '2026-10-02', '14:19:17', 'Masuk', -8.7845493, 115.1942564, 1.24, 81.00, 'Ditolak', 'Di luar jam absen masuk. Sekarang pukul 14:19, jam dibuka 08:00–09:00', '2026-10-02 06:19:17', 0),
(70, 6, 1, '2026-10-02', '14:21:07', 'Masuk', -8.7845549, 115.1942569, 0.63, 85.00, 'Ditolak', 'Absen masuk sudah ditutup (batas pukul 14:00). Sekarang pukul 14:21. Hubungi guru/wali kelas untuk diinput manual.', '2026-10-02 06:21:07', 0),
(71, 6, 1, '2026-10-02', '14:21:09', 'Masuk', -8.7845549, 115.1942569, 0.63, 85.00, 'Ditolak', 'Absen masuk sudah ditutup (batas pukul 14:00). Sekarang pukul 14:21. Hubungi guru/wali kelas untuk diinput manual.', '2026-10-02 06:21:09', 0),
(72, 6, 1, '2026-10-02', '14:21:12', 'Masuk', -8.7845549, 115.1942569, 0.63, 85.00, 'Ditolak', 'Absen masuk sudah ditutup (batas pukul 14:00). Sekarang pukul 14:21. Hubungi guru/wali kelas untuk diinput manual.', '2026-10-02 06:21:12', 0),
(73, 6, 1, '2026-10-02', '14:21:13', 'Masuk', -8.7845549, 115.1942569, 0.63, 85.00, 'Ditolak', 'Absen masuk sudah ditutup (batas pukul 14:00). Sekarang pukul 14:21. Hubungi guru/wali kelas untuk diinput manual.', '2026-10-02 06:21:13', 0),
(74, 6, 1, '2026-10-02', '14:21:20', 'Masuk', -8.7845602, 115.1942592, 0.02, 85.00, 'Ditolak', 'Absen masuk sudah ditutup (batas pukul 14:00). Sekarang pukul 14:21. Hubungi guru/wali kelas untuk diinput manual.', '2026-10-02 06:21:20', 0),
(75, 6, 1, '2026-10-02', '14:21:20', 'Masuk', -8.7845602, 115.1942592, 0.02, 85.00, 'Ditolak', 'Absen masuk sudah ditutup (batas pukul 14:00). Sekarang pukul 14:21. Hubungi guru/wali kelas untuk diinput manual.', '2026-10-02 06:21:20', 0),
(76, 6, 1, '2026-10-02', '14:21:20', 'Masuk', -8.7845602, 115.1942592, 0.02, 85.00, 'Ditolak', 'Absen masuk sudah ditutup (batas pukul 14:00). Sekarang pukul 14:21. Hubungi guru/wali kelas untuk diinput manual.', '2026-10-02 06:21:20', 0),
(77, 6, 1, '2026-10-02', '14:21:21', 'Masuk', -8.7845602, 115.1942592, 0.02, 85.00, 'Ditolak', 'Absen masuk sudah ditutup (batas pukul 14:00). Sekarang pukul 14:21. Hubungi guru/wali kelas untuk diinput manual.', '2026-10-02 06:21:21', 0),
(78, 6, 1, '2026-10-02', '14:21:22', 'Masuk', -8.7845602, 115.1942592, 0.02, 85.00, 'Ditolak', 'Absen masuk sudah ditutup (batas pukul 14:00). Sekarang pukul 14:21. Hubungi guru/wali kelas untuk diinput manual.', '2026-10-02 06:21:22', 0),
(79, 6, 1, '2026-10-02', '14:21:22', 'Masuk', -8.7845602, 115.1942592, 0.02, 85.00, 'Ditolak', 'Absen masuk sudah ditutup (batas pukul 14:00). Sekarang pukul 14:21. Hubungi guru/wali kelas untuk diinput manual.', '2026-10-02 06:21:22', 0),
(80, 6, 1, '2026-10-02', '14:21:24', 'Masuk', -8.7845602, 115.1942592, 0.02, 85.00, 'Ditolak', 'Absen masuk sudah ditutup (batas pukul 14:00). Sekarang pukul 14:21. Hubungi guru/wali kelas untuk diinput manual.', '2026-10-02 06:21:24', 0),
(81, 6, 1, '2026-10-02', '14:21:24', 'Masuk', -8.7845602, 115.1942592, 0.02, 85.00, 'Ditolak', 'Absen masuk sudah ditutup (batas pukul 14:00). Sekarang pukul 14:21. Hubungi guru/wali kelas untuk diinput manual.', '2026-10-02 06:21:24', 0),
(82, 6, 1, '2026-10-02', '14:21:25', 'Masuk', -8.7845602, 115.1942592, 0.02, 85.00, 'Ditolak', 'Absen masuk sudah ditutup (batas pukul 14:00). Sekarang pukul 14:21. Hubungi guru/wali kelas untuk diinput manual.', '2026-10-02 06:21:25', 0);

-- --------------------------------------------------------

--
-- Struktur dari tabel `absensi_guru`
--

CREATE TABLE `absensi_guru` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `tanggal` date NOT NULL,
  `jam_masuk` time DEFAULT NULL,
  `lat_masuk` decimal(10,7) DEFAULT NULL,
  `lng_masuk` decimal(10,7) DEFAULT NULL,
  `jarak_masuk` int DEFAULT NULL,
  `akurasi_masuk` int DEFAULT NULL,
  `terlambat` tinyint(1) NOT NULL DEFAULT '0',
  `jam_jadwal` time DEFAULT NULL,
  `terlambat_menit` int NOT NULL DEFAULT '0',
  `jam_pulang` time DEFAULT NULL,
  `lat_pulang` decimal(10,7) DEFAULT NULL,
  `lng_pulang` decimal(10,7) DEFAULT NULL,
  `jarak_pulang` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data untuk tabel `absensi_guru`
--

INSERT INTO `absensi_guru` (`id`, `user_id`, `tanggal`, `jam_masuk`, `lat_masuk`, `lng_masuk`, `jarak_masuk`, `akurasi_masuk`, `terlambat`, `jam_jadwal`, `terlambat_menit`, `jam_pulang`, `lat_pulang`, `lng_pulang`, `jarak_pulang`, `created_at`) VALUES
(1, 4, '2026-10-01', '09:57:25', -8.7845304, 115.1942532, 3, 77, 1, NULL, 0, '14:56:35', -8.7845404, 115.1942525, 2, '2026-10-01 01:57:25');

-- --------------------------------------------------------

--
-- Struktur dari tabel `absensi_mapel`
--

CREATE TABLE `absensi_mapel` (
  `id` int NOT NULL,
  `siswa_id` int NOT NULL,
  `kelas_id` int NOT NULL,
  `mapel_id` int NOT NULL,
  `guru_id` int DEFAULT NULL,
  `guru_nama` varchar(100) DEFAULT NULL,
  `tanggal` date NOT NULL,
  `status` enum('Hadir','Izin','Sakit','Alpa') NOT NULL DEFAULT 'Hadir',
  `keterangan` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `elearning_materi`
--

CREATE TABLE `elearning_materi` (
  `id` int NOT NULL,
  `kelas_id` int NOT NULL,
  `mapel_id` int NOT NULL,
  `guru_id` int DEFAULT NULL,
  `guru_nama` varchar(100) DEFAULT NULL,
  `judul` varchar(150) NOT NULL,
  `deskripsi` text,
  `file_materi` varchar(255) DEFAULT NULL,
  `file_nama_asli` varchar(255) DEFAULT NULL,
  `tanggal` date NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data untuk tabel `elearning_materi`
--

INSERT INTO `elearning_materi` (`id`, `kelas_id`, `mapel_id`, `guru_id`, `guru_nama`, `judul`, `deskripsi`, `file_materi`, `file_nama_asli`, `tanggal`, `created_at`) VALUES
(1, 1, 2, 1, 'superadmin', 'MATERI IPA', 'Pelajari Dan Pahami', 'materi_1790739283_303.pdf', 'TUGAS KELOMPOK IV SUBKEL II - DATA DESIL.pdf', '2026-09-30', '2026-09-30 03:34:43');

-- --------------------------------------------------------

--
-- Struktur dari tabel `elearning_pengumpulan`
--

CREATE TABLE `elearning_pengumpulan` (
  `id` int NOT NULL,
  `tugas_id` int NOT NULL,
  `siswa_id` int NOT NULL,
  `file_jawaban` varchar(255) DEFAULT NULL,
  `file_nama_asli` varchar(255) DEFAULT NULL,
  `catatan` text,
  `nilai` decimal(5,2) DEFAULT NULL,
  `feedback_guru` text,
  `status` enum('Belum Dinilai','Sudah Dinilai') NOT NULL DEFAULT 'Belum Dinilai',
  `tanggal_kumpul` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data untuk tabel `elearning_pengumpulan`
--

INSERT INTO `elearning_pengumpulan` (`id`, `tugas_id`, `siswa_id`, `file_jawaban`, `file_nama_asli`, `catatan`, `nilai`, `feedback_guru`, `status`, `tanggal_kumpul`) VALUES
(1, 1, 1, 'kumpul_1790559322_953.pdf', 'T.pdf', 'done', 90.00, NULL, 'Sudah Dinilai', '2026-09-28 01:35:22'),
(2, 2, 1, 'kumpul_1790559451_767.pdf', 'Citilink-QR-eBoardingPass-FH2IPW.pdf', NULL, 75.00, NULL, 'Sudah Dinilai', '2026-09-28 01:37:31');

-- --------------------------------------------------------

--
-- Struktur dari tabel `elearning_tugas`
--

CREATE TABLE `elearning_tugas` (
  `id` int NOT NULL,
  `kelas_id` int NOT NULL,
  `mapel_id` int NOT NULL,
  `guru_id` int DEFAULT NULL,
  `guru_nama` varchar(100) DEFAULT NULL,
  `judul` varchar(150) NOT NULL,
  `deskripsi` text,
  `file_lampiran` varchar(255) DEFAULT NULL,
  `file_nama_asli` varchar(255) DEFAULT NULL,
  `deadline` datetime NOT NULL,
  `semester` enum('Ganjil','Genap') NOT NULL DEFAULT 'Ganjil',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `tahun_ajaran` varchar(20) NOT NULL DEFAULT '2025/2026'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data untuk tabel `elearning_tugas`
--

INSERT INTO `elearning_tugas` (`id`, `kelas_id`, `mapel_id`, `guru_id`, `guru_nama`, `judul`, `deskripsi`, `file_lampiran`, `file_nama_asli`, `deadline`, `semester`, `created_at`, `tahun_ajaran`) VALUES
(1, 1, 2, 1, 'superadmin', 'UPDATE !', 'edededed', 'tugas_1790559270_974.pdf', '37_Saiful_Basyir_Bagian-lanjutan.pdf', '2026-09-29 09:36:00', 'Ganjil', '2026-09-28 01:34:30', '2026/2027'),
(2, 1, 2, 1, 'superadmin', 'arsip', 'aaaa', 'tugas_1790559433_570.pdf', 'I GEDE A RSY Y PUTRA DPS BTH PP 21 24 SEP 2026 (2).pdf', '2026-09-29 09:36:00', 'Ganjil', '2026-09-28 01:37:13', '2026/2027');

-- --------------------------------------------------------

--
-- Struktur dari tabel `guru_profil`
--

CREATE TABLE `guru_profil` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `nip` varchar(30) DEFAULT NULL,
  `nuptk` varchar(30) DEFAULT NULL,
  `jenis_kelamin` enum('L','P') DEFAULT NULL,
  `tempat_lahir` varchar(60) DEFAULT NULL,
  `tanggal_lahir` date DEFAULT NULL,
  `agama` varchar(20) DEFAULT NULL,
  `alamat` varchar(255) DEFAULT NULL,
  `no_hp` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `pendidikan_terakhir` varchar(50) DEFAULT NULL,
  `bidang_keahlian` varchar(100) DEFAULT NULL,
  `jabatan` varchar(100) DEFAULT NULL,
  `status_kepegawaian` enum('PNS','PPPK','GTY','Honorer','Lainnya') DEFAULT NULL,
  `tanggal_masuk` date DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data untuk tabel `guru_profil`
--

INSERT INTO `guru_profil` (`id`, `user_id`, `nip`, `nuptk`, `jenis_kelamin`, `tempat_lahir`, `tanggal_lahir`, `agama`, `alamat`, `no_hp`, `email`, `pendidikan_terakhir`, `bidang_keahlian`, `jabatan`, `status_kepegawaian`, `tanggal_masuk`, `updated_at`) VALUES
(1, 4, '200405252025081004', '200405252025081004', 'L', 'DENPASAR', '2005-01-01', 'Hindu', 'Jl kenangan indah 27', '081283827382', 'yoga@gmail.com', 'S1 Pendidikan', 'Komputer', 'Guru Muda', 'GTY', '2026-09-10', '2026-10-01 06:54:40');

-- --------------------------------------------------------

--
-- Struktur dari tabel `hari_libur`
--

CREATE TABLE `hari_libur` (
  `id` int NOT NULL,
  `tanggal` date NOT NULL,
  `keterangan` varchar(150) NOT NULL,
  `jenis` enum('Libur Nasional','Cuti Bersama','Libur Sekolah','Lainnya') NOT NULL DEFAULT 'Libur Nasional',
  `dibuat_oleh` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `izin`
--

CREATE TABLE `izin` (
  `id` int NOT NULL,
  `siswa_id` int NOT NULL,
  `kelas_id` int NOT NULL,
  `jenis` enum('Izin','Sakit') NOT NULL DEFAULT 'Izin',
  `tanggal_mulai` date NOT NULL,
  `tanggal_selesai` date NOT NULL,
  `alasan` varchar(255) NOT NULL,
  `lampiran` varchar(100) DEFAULT NULL,
  `status` enum('Menunggu','Disetujui','Ditolak') NOT NULL DEFAULT 'Menunggu',
  `diproses_oleh` int DEFAULT NULL,
  `diproses_nama` varchar(100) DEFAULT NULL,
  `diproses_role` varchar(20) DEFAULT NULL,
  `catatan_pemroses` varchar(255) DEFAULT NULL,
  `diproses_pada` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data untuk tabel `izin`
--

INSERT INTO `izin` (`id`, `siswa_id`, `kelas_id`, `jenis`, `tanggal_mulai`, `tanggal_selesai`, `alasan`, `lampiran`, `status`, `diproses_oleh`, `diproses_nama`, `diproses_role`, `catatan_pemroses`, `diproses_pada`, `created_at`) VALUES
(1, 1, 1, 'Izin', '2026-10-01', '2026-10-01', 'acara agama', '80bf7cba94dcb990649b53ce.jpg', 'Disetujui', 4, 'yoga', 'wali_kelas', NULL, '2026-09-30 03:23:25', '2026-09-30 03:21:54'),
(4, 6, 1, 'Sakit', '2026-10-06', '2026-10-06', 'sakit', NULL, 'Disetujui', 4, 'yoga', 'wali_kelas', NULL, '2026-10-02 06:22:24', '2026-10-02 06:22:18');

-- --------------------------------------------------------

--
-- Struktur dari tabel `jadwal_mengajar`
--

CREATE TABLE `jadwal_mengajar` (
  `id` int NOT NULL,
  `guru_id` int NOT NULL,
  `mapel_id` int NOT NULL,
  `kelas_id` int NOT NULL,
  `semester` enum('Ganjil','Genap') NOT NULL DEFAULT 'Ganjil',
  `tahun_ajaran` varchar(20) NOT NULL DEFAULT '2026/2027',
  `hari` tinyint NOT NULL,
  `jam_mulai` time NOT NULL,
  `jam_selesai` time NOT NULL,
  `berlaku_mulai` date DEFAULT NULL,
  `berlaku_sampai` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `kelas`
--

CREATE TABLE `kelas` (
  `id` int NOT NULL,
  `nama_kelas` varchar(50) NOT NULL,
  `tingkat` varchar(10) NOT NULL,
  `jurusan` varchar(50) DEFAULT NULL,
  `wali_kelas` varchar(100) DEFAULT NULL,
  `wali_kelas_user_id` int DEFAULT NULL,
  `tahun_ajaran` varchar(20) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data untuk tabel `kelas`
--

INSERT INTO `kelas` (`id`, `nama_kelas`, `tingkat`, `jurusan`, `wali_kelas`, `wali_kelas_user_id`, `tahun_ajaran`, `created_at`) VALUES
(1, 'X IPA 1', 'X', 'IPA', 'Yogaa', 4, '2025/2026', '2026-09-28 00:45:09'),
(2, 'X IPA 2', 'X', 'IPA', 'Siti Aminah, S.Pd', NULL, '2025/2026', '2026-09-28 00:45:09'),
(3, 'XI IPS 1', 'XI', 'IPS', 'Ahmad Fauzi, S.Pd', NULL, '2025/2026', '2026-09-28 00:45:09'),
(4, 'XII IPA 1', 'XII', 'IPA', 'Dewi Lestari, S.Pd', NULL, '2025/2026', '2026-09-28 00:45:09');

-- --------------------------------------------------------

--
-- Struktur dari tabel `lokasi_sekolah`
--

CREATE TABLE `lokasi_sekolah` (
  `id` int NOT NULL,
  `nama_lokasi` varchar(100) NOT NULL DEFAULT 'Sekolah',
  `latitude` decimal(10,7) NOT NULL,
  `longitude` decimal(10,7) NOT NULL,
  `radius_meter` int NOT NULL DEFAULT '150',
  `jam_mulai` time NOT NULL DEFAULT '06:00:00',
  `jam_selesai` time NOT NULL DEFAULT '08:00:00',
  `jam_pulang_mulai` time NOT NULL DEFAULT '14:00:00',
  `jam_pulang_selesai` time NOT NULL DEFAULT '17:00:00',
  `aktif` tinyint(1) NOT NULL DEFAULT '1',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data untuk tabel `lokasi_sekolah`
--

INSERT INTO `lokasi_sekolah` (`id`, `nama_lokasi`, `latitude`, `longitude`, `radius_meter`, `jam_mulai`, `jam_selesai`, `jam_pulang_mulai`, `jam_pulang_selesai`, `aktif`, `updated_at`) VALUES
(1, 'Lokasi Sekolah (Contoh - Harap Diubah)', -8.7845601, 115.1942593, 150, '08:00:00', '09:00:00', '14:00:00', '20:00:00', 1, '2026-09-28 23:35:02');

-- --------------------------------------------------------

--
-- Struktur dari tabel `mata_pelajaran`
--

CREATE TABLE `mata_pelajaran` (
  `id` int NOT NULL,
  `kode_mapel` varchar(20) DEFAULT NULL,
  `nama_mapel` varchar(100) NOT NULL,
  `kkm` int DEFAULT '75'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data untuk tabel `mata_pelajaran`
--

INSERT INTO `mata_pelajaran` (`id`, `kode_mapel`, `nama_mapel`, `kkm`) VALUES
(1, 'MTK', 'Matematika', 75),
(2, 'BIN', 'Bahasa Indonesia', 75),
(3, 'BIG', 'Bahasa Inggris', 75),
(4, 'FIS', 'Fisika', 75),
(5, 'KIM', 'Kimia', 75),
(6, 'BIO', 'Biologi', 75),
(7, 'PKN', 'PPKn', 75),
(8, 'SEJ', 'Sejarah Indonesia', 75);

-- --------------------------------------------------------

--
-- Struktur dari tabel `nilai`
--

CREATE TABLE `nilai` (
  `id` int NOT NULL,
  `siswa_id` int NOT NULL,
  `mapel_id` int NOT NULL,
  `semester` enum('Ganjil','Genap') NOT NULL,
  `tahun_ajaran` varchar(20) NOT NULL,
  `nilai_tugas` decimal(5,2) DEFAULT '0.00',
  `nilai_uts` decimal(5,2) DEFAULT '0.00',
  `nilai_uas` decimal(5,2) DEFAULT '0.00',
  `nilai_akhir` decimal(5,2) DEFAULT '0.00',
  `predikat` varchar(5) DEFAULT NULL,
  `catatan` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data untuk tabel `nilai`
--

INSERT INTO `nilai` (`id`, `siswa_id`, `mapel_id`, `semester`, `tahun_ajaran`, `nilai_tugas`, `nilai_uts`, `nilai_uas`, `nilai_akhir`, `predikat`, `catatan`, `created_at`) VALUES
(1, 1, 2, 'Ganjil', '2026/2027', 91.30, 77.80, 55.60, 73.00, 'C', NULL, '2026-09-28 01:37:54'),
(54, 1, 6, 'Genap', '2026/2027', 0.00, 77.80, 0.00, 23.30, 'D', NULL, '2026-09-29 07:53:48');

-- --------------------------------------------------------

--
-- Struktur dari tabel `nilai_tugas_manual`
--

CREATE TABLE `nilai_tugas_manual` (
  `id` int NOT NULL,
  `siswa_id` int NOT NULL,
  `kelas_id` int NOT NULL,
  `mapel_id` int NOT NULL,
  `semester` enum('Ganjil','Genap') NOT NULL,
  `tahun_ajaran` varchar(20) NOT NULL,
  `nilai` decimal(5,2) NOT NULL,
  `urutan` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data untuk tabel `nilai_tugas_manual`
--

INSERT INTO `nilai_tugas_manual` (`id`, `siswa_id`, `kelas_id`, `mapel_id`, `semester`, `tahun_ajaran`, `nilai`, `urutan`, `created_at`) VALUES
(7, 1, 1, 2, 'Ganjil', '2026/2027', 100.00, 0, '2026-09-30 00:48:41'),
(8, 1, 1, 2, 'Ganjil', '2026/2027', 100.00, 1, '2026-09-30 00:48:41');

-- --------------------------------------------------------

--
-- Struktur dari tabel `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data untuk tabel `password_resets`
--

INSERT INTO `password_resets` (`id`, `user_id`, `token_hash`, `expires_at`, `used_at`, `ip`, `created_at`) VALUES
(1, 9, '932034ea4157d29cc9bc04740c59914318edd5ed5d66ea1b78131b51c166c114', '2026-10-02 06:37:37', '2026-10-02 06:08:18', '172.17.0.1', '2026-10-02 06:07:37'),
(2, 9, '4ddf47d612c8c00fda232ff98af9f82dcc227b6c7daa6321a0fdd0580d8c8d21', '2026-10-02 06:26:39', '2026-10-02 06:12:40', '172.17.0.1', '2026-10-02 06:11:39');

-- --------------------------------------------------------

--
-- Struktur dari tabel `pengaturan_absen_guru`
--

CREATE TABLE `pengaturan_absen_guru` (
  `id` int NOT NULL,
  `jam_masuk_mulai` time NOT NULL DEFAULT '05:30:00',
  `jam_masuk_batas` time NOT NULL DEFAULT '07:30:00',
  `toleransi_menit` int NOT NULL DEFAULT '10',
  `jam_masuk_selesai` time NOT NULL DEFAULT '10:00:00',
  `jam_pulang_mulai` time NOT NULL DEFAULT '14:00:00',
  `jam_pulang_selesai` time NOT NULL DEFAULT '20:00:00',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data untuk tabel `pengaturan_absen_guru`
--

INSERT INTO `pengaturan_absen_guru` (`id`, `jam_masuk_mulai`, `jam_masuk_batas`, `toleransi_menit`, `jam_masuk_selesai`, `jam_pulang_mulai`, `jam_pulang_selesai`, `updated_at`) VALUES
(1, '05:30:00', '07:30:00', 10, '10:00:00', '14:00:00', '20:00:00', '2026-10-01 01:51:58');

-- --------------------------------------------------------

--
-- Struktur dari tabel `pengumuman`
--

CREATE TABLE `pengumuman` (
  `id` int NOT NULL,
  `judul` varchar(150) NOT NULL,
  `isi` text NOT NULL,
  `kategori` enum('Umum','Akademik','Kegiatan','Penting') DEFAULT 'Umum',
  `penulis` varchar(100) DEFAULT NULL,
  `tanggal` date NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data untuk tabel `pengumuman`
--

INSERT INTO `pengumuman` (`id`, `judul`, `isi`, `kategori`, `penulis`, `tanggal`, `created_at`) VALUES
(1, 'Selamat Datang di Tahun Ajaran Baru 2025/2026', 'Kepada seluruh siswa-siswi, selamat datang di tahun ajaran baru. Mari kita sambut dengan semangat belajar yang tinggi.', 'Umum', 'Admin', '2026-09-28', '2026-09-28 00:45:09'),
(2, 'Jadwal Penilaian Tengah Semester', 'Penilaian Tengah Semester (PTS) akan dilaksanakan sesuai jadwal yang telah ditentukan oleh masing-masing guru mata pelajaran.', 'Akademik', 'Admin', '2026-09-28', '2026-09-28 00:45:09');

-- --------------------------------------------------------

--
-- Struktur dari tabel `siswa`
--

CREATE TABLE `siswa` (
  `id` int NOT NULL,
  `nis` varchar(20) NOT NULL,
  `nisn` varchar(20) DEFAULT NULL,
  `nama_lengkap` varchar(100) NOT NULL,
  `jenis_kelamin` enum('L','P') NOT NULL,
  `tempat_lahir` varchar(50) DEFAULT NULL,
  `tanggal_lahir` date DEFAULT NULL,
  `agama` varchar(30) DEFAULT NULL,
  `alamat` text,
  `no_hp` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `nama_ayah` varchar(100) DEFAULT NULL,
  `nama_ibu` varchar(100) DEFAULT NULL,
  `no_hp_ortu` varchar(20) DEFAULT NULL,
  `pekerjaan_ortu` varchar(50) DEFAULT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `kelas_id` int DEFAULT NULL,
  `status` enum('Aktif','Pindah','Lulus','Keluar') DEFAULT 'Aktif',
  `tanggal_masuk` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data untuk tabel `siswa`
--

INSERT INTO `siswa` (`id`, `nis`, `nisn`, `nama_lengkap`, `jenis_kelamin`, `tempat_lahir`, `tanggal_lahir`, `agama`, `alamat`, `no_hp`, `email`, `nama_ayah`, `nama_ibu`, `no_hp_ortu`, `pekerjaan_ortu`, `foto`, `kelas_id`, `status`, `tanggal_masuk`, `created_at`) VALUES
(1, '100', '100', 'WAYAN KUPROK', 'L', 'DENPASAR', '2006-04-04', 'Hindu', 'JL KENAANGAN', '081238384828', 'wayankurprok@gmail.com', 'wayan tola', 'ketut ola', '081283838428', '-', 'siswa_1790559097_580.png', 1, 'Aktif', '2026-09-30', '2026-09-28 01:31:37'),
(6, '12312313', NULL, 'Gung Rsy Yoga', 'L', NULL, NULL, 'Hindu', 'jalan made bina denpasar utara', NULL, 'gungrsyyoga@gmail.com', NULL, NULL, NULL, NULL, NULL, 1, 'Aktif', '2026-10-02', '2026-10-02 02:04:11');

-- --------------------------------------------------------

--
-- Struktur dari tabel `token_pemakaian`
--

CREATE TABLE `token_pemakaian` (
  `id` int NOT NULL,
  `token_id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `nama` varchar(100) NOT NULL,
  `role` enum('siswa','guru') NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data untuk tabel `token_pemakaian`
--

INSERT INTO `token_pemakaian` (`id`, `token_id`, `user_id`, `nama`, `role`, `created_at`) VALUES
(1, 101, NULL, 'Gung Rsy Yoga', 'siswa', '2026-10-02 01:57:11'),
(2, 101, 9, 'Gung Rsy Yoga', 'siswa', '2026-10-02 02:04:11');

-- --------------------------------------------------------

--
-- Struktur dari tabel `token_registrasi`
--

CREATE TABLE `token_registrasi` (
  `id` int NOT NULL,
  `token` varchar(30) NOT NULL,
  `role` enum('siswa','guru') NOT NULL,
  `kelas_id` int DEFAULT NULL,
  `catatan` varchar(100) DEFAULT NULL,
  `maks_pakai` int NOT NULL DEFAULT '1',
  `jumlah_terpakai` int NOT NULL DEFAULT '0',
  `kadaluarsa` date DEFAULT NULL,
  `aktif` tinyint(1) NOT NULL DEFAULT '1',
  `dibuat_oleh` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data untuk tabel `token_registrasi`
--

INSERT INTO `token_registrasi` (`id`, `token`, `role`, `kelas_id`, `catatan`, `maks_pakai`, `jumlah_terpakai`, `kadaluarsa`, `aktif`, `dibuat_oleh`, `created_at`) VALUES
(101, 'SISWA-SKGD-4NCS', 'siswa', NULL, 'SISWA2026', 100, 2, NULL, 1, 1, '2026-10-02 01:56:22'),
(102, 'GURU-2VV9-7M6H', 'guru', NULL, 'GURU', 100, 0, NULL, 1, 1, '2026-10-02 06:00:52');

-- --------------------------------------------------------

--
-- Struktur dari tabel `ujian`
--

CREATE TABLE `ujian` (
  `id` int NOT NULL,
  `judul` varchar(150) NOT NULL,
  `jenis` enum('UTS','UAS') NOT NULL,
  `kelas_id` int NOT NULL,
  `mapel_id` int NOT NULL,
  `guru_id` int DEFAULT NULL,
  `guru_nama` varchar(100) DEFAULT NULL,
  `semester` enum('Ganjil','Genap') NOT NULL,
  `tahun_ajaran` varchar(20) NOT NULL,
  `petunjuk` text,
  `durasi_menit` int NOT NULL DEFAULT '60',
  `waktu_mulai` datetime NOT NULL,
  `waktu_selesai` datetime NOT NULL,
  `acak_soal` tinyint(1) NOT NULL DEFAULT '0',
  `status` enum('Draft','Terbit') NOT NULL DEFAULT 'Draft',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data untuk tabel `ujian`
--

INSERT INTO `ujian` (`id`, `judul`, `jenis`, `kelas_id`, `mapel_id`, `guru_id`, `guru_nama`, `semester`, `tahun_ajaran`, `petunjuk`, `durasi_menit`, `waktu_mulai`, `waktu_selesai`, `acak_soal`, `status`, `created_at`) VALUES
(3, 'matetmatika', 'UAS', 1, 2, 1, 'superadmin', 'Ganjil', '2026/2027', '', 90, '2026-09-28 15:15:00', '2026-09-29 12:00:00', 0, 'Terbit', '2026-09-28 06:04:24'),
(4, 'IPA', 'UTS', 1, 6, 1, 'superadmin', 'Genap', '2026/2027', 'BUAT DENGAN JUJUR BLOK', 10, '2026-09-29 15:42:00', '2026-09-30 20:00:00', 1, 'Terbit', '2026-09-29 07:40:25');

-- --------------------------------------------------------

--
-- Struktur dari tabel `ujian_jawaban`
--

CREATE TABLE `ujian_jawaban` (
  `id` int NOT NULL,
  `peserta_id` int NOT NULL,
  `soal_id` int NOT NULL,
  `jawaban` mediumtext,
  `skor` decimal(5,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data untuk tabel `ujian_jawaban`
--

INSERT INTO `ujian_jawaban` (`id`, `peserta_id`, `soal_id`, `jawaban`, `skor`) VALUES
(1, 1, 5, 'b', 2.00),
(2, 1, 6, 'a', 0.00),
(3, 1, 7, 'fotosintesis terjadi di karenakan sinar matahari masuk melalui daun tanaman', 3.00),
(15, 2, 8, 'b', 2.00),
(16, 2, 9, 'a', 0.00),
(17, 2, 10, 'test', 5.00);

-- --------------------------------------------------------

--
-- Struktur dari tabel `ujian_peserta`
--

CREATE TABLE `ujian_peserta` (
  `id` int NOT NULL,
  `ujian_id` int NOT NULL,
  `siswa_id` int NOT NULL,
  `waktu_mulai` datetime NOT NULL,
  `waktu_selesai` datetime DEFAULT NULL,
  `status` enum('Berlangsung','Selesai') NOT NULL DEFAULT 'Berlangsung',
  `nilai_pg` decimal(6,2) NOT NULL DEFAULT '0.00',
  `nilai_essay` decimal(6,2) NOT NULL DEFAULT '0.00',
  `nilai_akhir` decimal(6,2) NOT NULL DEFAULT '0.00',
  `dinilai` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data untuk tabel `ujian_peserta`
--

INSERT INTO `ujian_peserta` (`id`, `ujian_id`, `siswa_id`, `waktu_mulai`, `waktu_selesai`, `status`, `nilai_pg`, `nilai_essay`, `nilai_akhir`, `dinilai`) VALUES
(1, 3, 1, '2026-09-28 23:05:01', '2026-09-28 23:05:46', 'Selesai', 22.22, 33.33, 55.55, 1),
(2, 4, 1, '2026-09-29 15:47:33', '2026-09-29 15:47:45', 'Selesai', 22.22, 55.56, 77.78, 1);

-- --------------------------------------------------------

--
-- Struktur dari tabel `ujian_soal`
--

CREATE TABLE `ujian_soal` (
  `id` int NOT NULL,
  `ujian_id` int NOT NULL,
  `tipe` enum('pilihan_ganda','essay') NOT NULL,
  `pertanyaan` text NOT NULL,
  `gambar` varchar(255) DEFAULT NULL,
  `opsi_a` varchar(500) DEFAULT NULL,
  `opsi_b` varchar(500) DEFAULT NULL,
  `opsi_c` varchar(500) DEFAULT NULL,
  `opsi_d` varchar(500) DEFAULT NULL,
  `opsi_e` varchar(500) DEFAULT NULL,
  `kunci` char(1) DEFAULT NULL,
  `pedoman` text,
  `bobot` decimal(5,2) NOT NULL DEFAULT '1.00',
  `urutan` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data untuk tabel `ujian_soal`
--

INSERT INTO `ujian_soal` (`id`, `ujian_id`, `tipe`, `pertanyaan`, `gambar`, `opsi_a`, `opsi_b`, `opsi_c`, `opsi_d`, `opsi_e`, `kunci`, `pedoman`, `bobot`, `urutan`, `created_at`) VALUES
(5, 3, 'pilihan_ganda', 'Ibu kota Indonesia adalah ...', NULL, 'Bandung', 'Jakarta', 'Surabaya', 'Medan', NULL, 'b', NULL, 2.00, 1, '2026-09-28 06:06:35'),
(6, 3, 'pilihan_ganda', 'Perhatikan gambar segitiga siku-siku berikut. Berapa panjang sisi miring segitiga tersebut?', '40b32dff1c4791d1a6ee225e.png', '8 cm', '9 cm', '10 cm', '12 cm', NULL, 'c', NULL, 2.00, 2, '2026-09-28 06:06:35'),
(7, 3, 'essay', 'Jelaskan proses terjadinya fotosintesis pada tumbuhan!', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Cahaya matahari, klorofil, air dan CO2 menjadi glukosa dan oksigen.', 5.00, 3, '2026-09-28 06:06:35'),
(8, 4, 'pilihan_ganda', 'Ibu kota Indonesia adalah ...', NULL, 'Bandung', 'Jakarta', 'Surabaya', 'Medan', NULL, 'b', NULL, 2.00, 1, '2026-09-29 07:40:36'),
(9, 4, 'pilihan_ganda', 'Perhatikan gambar segitiga siku-siku berikut. Berapa panjang sisi miring segitiga tersebut?', '118a708728087eb47a7d5aab.png', '8 cm', '9 cm', '10 cm', '12 cm', NULL, 'c', NULL, 2.00, 2, '2026-09-29 07:40:36'),
(10, 4, 'essay', 'Jelaskan proses terjadinya fotosintesis pada tumbuhan!', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Cahaya matahari, klorofil, air dan CO2 menjadi glukosa dan oksigen.', 5.00, 3, '2026-09-29 07:40:36');

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `role` enum('superadmin','admin','guru','wali_kelas','siswa') NOT NULL DEFAULT 'siswa',
  `siswa_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data untuk tabel `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `nama`, `role`, `siswa_id`, `created_at`) VALUES
(1, 'superadmin', '$2y$12$fStFdYD/26kGNXULEvYLbezP7OrgW2QkruAPQpw9dtirN7VuGss3C', 'superadmin', 'admin', NULL, '2026-09-28 01:16:34'),
(3, 'dede', '$2y$12$VW81zLrC0TxpTYJX.Q7Y3ezSiso5SiS69mlXWHth2uUJwkwmMauly', 'dede', 'siswa', 1, '2026-09-28 01:32:00'),
(4, 'yoga', '$2y$12$XeUQtDpwgY4fwHa7OMFPVeyNsxkgRl9AkurBP94fPwIBN10Pksp7K', 'yoga', 'wali_kelas', NULL, '2026-09-28 07:25:45'),
(9, '2312313', '$2y$12$7c4tBvAbHgFn/7pznOxG/efXaOpSBM9nIpQnK0SGV3oxZ7uhbxLiG', 'Gung Rsy Yoga', 'siswa', 6, '2026-10-02 02:04:11');

--
-- Indeks untuk tabel yang dibuang
--

--
-- Indeks untuk tabel `absensi`
--
ALTER TABLE `absensi`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_absen` (`siswa_id`,`tanggal`),
  ADD KEY `kelas_id` (`kelas_id`);

--
-- Indeks untuk tabel `absensi_gps`
--
ALTER TABLE `absensi_gps`
  ADD PRIMARY KEY (`id`),
  ADD KEY `siswa_id` (`siswa_id`),
  ADD KEY `kelas_id` (`kelas_id`);

--
-- Indeks untuk tabel `absensi_guru`
--
ALTER TABLE `absensi_guru`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_guru_hari` (`user_id`,`tanggal`),
  ADD KEY `idx_absensi_guru_tanggal` (`tanggal`);

--
-- Indeks untuk tabel `absensi_mapel`
--
ALTER TABLE `absensi_mapel`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_absen_mapel` (`siswa_id`,`mapel_id`,`tanggal`),
  ADD KEY `kelas_id` (`kelas_id`),
  ADD KEY `mapel_id` (`mapel_id`),
  ADD KEY `guru_id` (`guru_id`);

--
-- Indeks untuk tabel `elearning_materi`
--
ALTER TABLE `elearning_materi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `kelas_id` (`kelas_id`),
  ADD KEY `mapel_id` (`mapel_id`),
  ADD KEY `guru_id` (`guru_id`);

--
-- Indeks untuk tabel `elearning_pengumpulan`
--
ALTER TABLE `elearning_pengumpulan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_kumpul` (`tugas_id`,`siswa_id`),
  ADD KEY `siswa_id` (`siswa_id`);

--
-- Indeks untuk tabel `elearning_tugas`
--
ALTER TABLE `elearning_tugas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `kelas_id` (`kelas_id`),
  ADD KEY `mapel_id` (`mapel_id`),
  ADD KEY `guru_id` (`guru_id`);

--
-- Indeks untuk tabel `guru_profil`
--
ALTER TABLE `guru_profil`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_profil_user` (`user_id`),
  ADD UNIQUE KEY `uniq_profil_nip` (`nip`);

--
-- Indeks untuk tabel `hari_libur`
--
ALTER TABLE `hari_libur`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_libur` (`tanggal`);

--
-- Indeks untuk tabel `izin`
--
ALTER TABLE `izin`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_izin_siswa` (`siswa_id`,`tanggal_mulai`),
  ADD KEY `idx_izin_kelas_status` (`kelas_id`,`status`);

--
-- Indeks untuk tabel `jadwal_mengajar`
--
ALTER TABLE `jadwal_mengajar`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_jadwal_guru_hari` (`guru_id`,`hari`),
  ADD KEY `idx_jadwal_kelas_hari` (`kelas_id`,`hari`),
  ADD KEY `jadwal_ibfk_2` (`mapel_id`),
  ADD KEY `idx_jadwal_periode` (`guru_id`,`semester`,`tahun_ajaran`,`hari`);

--
-- Indeks untuk tabel `kelas`
--
ALTER TABLE `kelas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `wali_kelas_user_id` (`wali_kelas_user_id`);

--
-- Indeks untuk tabel `lokasi_sekolah`
--
ALTER TABLE `lokasi_sekolah`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `mata_pelajaran`
--
ALTER TABLE `mata_pelajaran`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `nilai`
--
ALTER TABLE `nilai`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_nilai` (`siswa_id`,`mapel_id`,`semester`,`tahun_ajaran`),
  ADD KEY `mapel_id` (`mapel_id`);

--
-- Indeks untuk tabel `nilai_tugas_manual`
--
ALTER TABLE `nilai_tugas_manual`
  ADD PRIMARY KEY (`id`),
  ADD KEY `kelas_id` (`kelas_id`),
  ADD KEY `mapel_id` (`mapel_id`),
  ADD KEY `idx_manual` (`siswa_id`,`mapel_id`,`semester`,`tahun_ajaran`);

--
-- Indeks untuk tabel `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_token_hash` (`token_hash`),
  ADD KEY `user_id` (`user_id`);

--
-- Indeks untuk tabel `pengaturan_absen_guru`
--
ALTER TABLE `pengaturan_absen_guru`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `pengumuman`
--
ALTER TABLE `pengumuman`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `siswa`
--
ALTER TABLE `siswa`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nis` (`nis`),
  ADD KEY `kelas_id` (`kelas_id`);

--
-- Indeks untuk tabel `token_pemakaian`
--
ALTER TABLE `token_pemakaian`
  ADD PRIMARY KEY (`id`),
  ADD KEY `token_id` (`token_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indeks untuk tabel `token_registrasi`
--
ALTER TABLE `token_registrasi`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_token` (`token`),
  ADD KEY `kelas_id` (`kelas_id`),
  ADD KEY `dibuat_oleh` (`dibuat_oleh`);

--
-- Indeks untuk tabel `ujian`
--
ALTER TABLE `ujian`
  ADD PRIMARY KEY (`id`),
  ADD KEY `kelas_id` (`kelas_id`),
  ADD KEY `mapel_id` (`mapel_id`),
  ADD KEY `guru_id` (`guru_id`);

--
-- Indeks untuk tabel `ujian_jawaban`
--
ALTER TABLE `ujian_jawaban`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_jawaban` (`peserta_id`,`soal_id`),
  ADD KEY `soal_id` (`soal_id`);

--
-- Indeks untuk tabel `ujian_peserta`
--
ALTER TABLE `ujian_peserta`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_peserta` (`ujian_id`,`siswa_id`),
  ADD KEY `siswa_id` (`siswa_id`);

--
-- Indeks untuk tabel `ujian_soal`
--
ALTER TABLE `ujian_soal`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ujian_id` (`ujian_id`);

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `siswa_id` (`siswa_id`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `absensi`
--
ALTER TABLE `absensi`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT untuk tabel `absensi_gps`
--
ALTER TABLE `absensi_gps`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=83;

--
-- AUTO_INCREMENT untuk tabel `absensi_guru`
--
ALTER TABLE `absensi_guru`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `absensi_mapel`
--
ALTER TABLE `absensi_mapel`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `elearning_materi`
--
ALTER TABLE `elearning_materi`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `elearning_pengumpulan`
--
ALTER TABLE `elearning_pengumpulan`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT untuk tabel `elearning_tugas`
--
ALTER TABLE `elearning_tugas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT untuk tabel `guru_profil`
--
ALTER TABLE `guru_profil`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `hari_libur`
--
ALTER TABLE `hari_libur`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `izin`
--
ALTER TABLE `izin`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT untuk tabel `jadwal_mengajar`
--
ALTER TABLE `jadwal_mengajar`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `kelas`
--
ALTER TABLE `kelas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT untuk tabel `lokasi_sekolah`
--
ALTER TABLE `lokasi_sekolah`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `mata_pelajaran`
--
ALTER TABLE `mata_pelajaran`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT untuk tabel `nilai`
--
ALTER TABLE `nilai`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=80;

--
-- AUTO_INCREMENT untuk tabel `nilai_tugas_manual`
--
ALTER TABLE `nilai_tugas_manual`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT untuk tabel `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT untuk tabel `pengaturan_absen_guru`
--
ALTER TABLE `pengaturan_absen_guru`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `pengumuman`
--
ALTER TABLE `pengumuman`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT untuk tabel `siswa`
--
ALTER TABLE `siswa`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT untuk tabel `token_pemakaian`
--
ALTER TABLE `token_pemakaian`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT untuk tabel `token_registrasi`
--
ALTER TABLE `token_registrasi`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=103;

--
-- AUTO_INCREMENT untuk tabel `ujian`
--
ALTER TABLE `ujian`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT untuk tabel `ujian_jawaban`
--
ALTER TABLE `ujian_jawaban`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT untuk tabel `ujian_peserta`
--
ALTER TABLE `ujian_peserta`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT untuk tabel `ujian_soal`
--
ALTER TABLE `ujian_soal`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `absensi`
--
ALTER TABLE `absensi`
  ADD CONSTRAINT `absensi_ibfk_1` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `absensi_ibfk_2` FOREIGN KEY (`kelas_id`) REFERENCES `kelas` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `absensi_gps`
--
ALTER TABLE `absensi_gps`
  ADD CONSTRAINT `absensi_gps_ibfk_1` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `absensi_gps_ibfk_2` FOREIGN KEY (`kelas_id`) REFERENCES `kelas` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `absensi_guru`
--
ALTER TABLE `absensi_guru`
  ADD CONSTRAINT `absensi_guru_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `absensi_mapel`
--
ALTER TABLE `absensi_mapel`
  ADD CONSTRAINT `absensi_mapel_ibfk_1` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `absensi_mapel_ibfk_2` FOREIGN KEY (`kelas_id`) REFERENCES `kelas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `absensi_mapel_ibfk_3` FOREIGN KEY (`mapel_id`) REFERENCES `mata_pelajaran` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `absensi_mapel_ibfk_4` FOREIGN KEY (`guru_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `elearning_materi`
--
ALTER TABLE `elearning_materi`
  ADD CONSTRAINT `elearning_materi_ibfk_1` FOREIGN KEY (`kelas_id`) REFERENCES `kelas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `elearning_materi_ibfk_2` FOREIGN KEY (`mapel_id`) REFERENCES `mata_pelajaran` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `elearning_materi_ibfk_3` FOREIGN KEY (`guru_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `elearning_pengumpulan`
--
ALTER TABLE `elearning_pengumpulan`
  ADD CONSTRAINT `elearning_pengumpulan_ibfk_1` FOREIGN KEY (`tugas_id`) REFERENCES `elearning_tugas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `elearning_pengumpulan_ibfk_2` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `elearning_tugas`
--
ALTER TABLE `elearning_tugas`
  ADD CONSTRAINT `elearning_tugas_ibfk_1` FOREIGN KEY (`kelas_id`) REFERENCES `kelas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `elearning_tugas_ibfk_2` FOREIGN KEY (`mapel_id`) REFERENCES `mata_pelajaran` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `elearning_tugas_ibfk_3` FOREIGN KEY (`guru_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `guru_profil`
--
ALTER TABLE `guru_profil`
  ADD CONSTRAINT `guru_profil_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `izin`
--
ALTER TABLE `izin`
  ADD CONSTRAINT `izin_ibfk_1` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `izin_ibfk_2` FOREIGN KEY (`kelas_id`) REFERENCES `kelas` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `jadwal_mengajar`
--
ALTER TABLE `jadwal_mengajar`
  ADD CONSTRAINT `jadwal_ibfk_1` FOREIGN KEY (`guru_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `jadwal_ibfk_2` FOREIGN KEY (`mapel_id`) REFERENCES `mata_pelajaran` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `jadwal_ibfk_3` FOREIGN KEY (`kelas_id`) REFERENCES `kelas` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `kelas`
--
ALTER TABLE `kelas`
  ADD CONSTRAINT `kelas_ibfk_1` FOREIGN KEY (`wali_kelas_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `nilai`
--
ALTER TABLE `nilai`
  ADD CONSTRAINT `nilai_ibfk_1` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `nilai_ibfk_2` FOREIGN KEY (`mapel_id`) REFERENCES `mata_pelajaran` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `nilai_tugas_manual`
--
ALTER TABLE `nilai_tugas_manual`
  ADD CONSTRAINT `nilai_tugas_manual_ibfk_1` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `nilai_tugas_manual_ibfk_2` FOREIGN KEY (`kelas_id`) REFERENCES `kelas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `nilai_tugas_manual_ibfk_3` FOREIGN KEY (`mapel_id`) REFERENCES `mata_pelajaran` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `password_resets`
--
ALTER TABLE `password_resets`
  ADD CONSTRAINT `pr_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `siswa`
--
ALTER TABLE `siswa`
  ADD CONSTRAINT `siswa_ibfk_1` FOREIGN KEY (`kelas_id`) REFERENCES `kelas` (`id`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `token_pemakaian`
--
ALTER TABLE `token_pemakaian`
  ADD CONSTRAINT `tp_token_fk` FOREIGN KEY (`token_id`) REFERENCES `token_registrasi` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tp_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `token_registrasi`
--
ALTER TABLE `token_registrasi`
  ADD CONSTRAINT `token_kelas_fk` FOREIGN KEY (`kelas_id`) REFERENCES `kelas` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `token_user_fk` FOREIGN KEY (`dibuat_oleh`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `ujian`
--
ALTER TABLE `ujian`
  ADD CONSTRAINT `ujian_ibfk_1` FOREIGN KEY (`kelas_id`) REFERENCES `kelas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ujian_ibfk_2` FOREIGN KEY (`mapel_id`) REFERENCES `mata_pelajaran` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ujian_ibfk_3` FOREIGN KEY (`guru_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `ujian_jawaban`
--
ALTER TABLE `ujian_jawaban`
  ADD CONSTRAINT `ujian_jawaban_ibfk_1` FOREIGN KEY (`peserta_id`) REFERENCES `ujian_peserta` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ujian_jawaban_ibfk_2` FOREIGN KEY (`soal_id`) REFERENCES `ujian_soal` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `ujian_peserta`
--
ALTER TABLE `ujian_peserta`
  ADD CONSTRAINT `ujian_peserta_ibfk_1` FOREIGN KEY (`ujian_id`) REFERENCES `ujian` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ujian_peserta_ibfk_2` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `ujian_soal`
--
ALTER TABLE `ujian_soal`
  ADD CONSTRAINT `ujian_soal_ibfk_1` FOREIGN KEY (`ujian_id`) REFERENCES `ujian` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
