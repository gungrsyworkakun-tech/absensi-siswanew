<?php
/**
 * KONFIGURASI MODUL REGISTER
 * Ubah nilai di bawah sesuai server Anda.
 */
return [
    'app_name' => 'Absensi Sekolah',

    // Koneksi database (host "db" = sesuai dump phpMyAdmin Anda / Docker)
    'db' => [
        'host'    => 'db',
        'name'    => 'db_absensi_sekolah',
        'user'    => 'root',
        'pass'    => '',
        'charset' => 'utf8mb4',
    ],

    // Pengiriman email lewat Gmail SMTP.
    // 'pass' WAJIB berupa "App Password" Gmail (16 karakter), BUKAN password Gmail biasa.
    // Cara: Akun Google > Keamanan > Verifikasi 2 Langkah (aktifkan) > Sandi aplikasi.
    'mail' => [
        'host'      => 'smtp.gmail.com',
        'port'      => 587,
        'user'      => 'emailsekolah@gmail.com',
        'pass'      => 'xxxxxxxxxxxxxxxx',
        'from_name' => 'Admin Sekolah',
    ],

    // Link halaman login yang dicantumkan di email
    'login_url' => 'http://localhost/login.php',

    // Kode pendaftaran rahasia yang dibagikan sekolah ke siswa/guru.
    // Isi supaya orang luar tidak bisa mendaftar sembarangan. Kosongkan ('') untuk menonaktifkan.
    'kode_pendaftaran' => 'SEKOLAH2026',

    // true = hanya menerima email @gmail.com
    'hanya_gmail' => true,
];
