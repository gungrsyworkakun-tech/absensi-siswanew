<?php
/**
 * Koneksi Database - Sistem Dashboard Absensi Sekolah
 * Sesuaikan DB_HOST, DB_USER, DB_PASS, DB_NAME dengan konfigurasi server Anda
 */

// Pengaturan koneksi MySQL di lingkungan Docker
define('DB_HOST', 'db');            
define('DB_NAME', 'db_absensi_sekolah');
define('DB_USER', 'root');
define('DB_PASS', 'dede');  

// Base URL aplikasi (tanpa slash di akhir). Contoh: http://localhost/sekolah-dashboard
// UBAH MENJADI (BENAR):
define('BASE_URL', 'http://localhost:8085');

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}
