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
        'pass'    => 'dede',
        'charset' => 'utf8mb4',
    ],

    // Pengiriman email lewat Gmail SMTP.
    // 'pass' WAJIB berupa "App Password" Gmail (16 karakter), BUKAN password Gmail biasa.
    // Cara: Akun Google > Keamanan > Verifikasi 2 Langkah (aktifkan) > Sandi aplikasi.
    'mail' => [
        'host'      => 'smtp.gmail.com',
        'port'      => 587,
        'user'      => 'gungrsyworkakun@gmail.com',
        'pass'      => 'nszy zivc jnji opdo',
        'from_name' => 'Admin Sekolah',
    ],

      // Link halaman login yang dicantumkan di email
   'login_url' => '/login.php',
 
    // Kode pendaftaran RAHASIA, dibedakan per peran.
    // - kode_siswa : dibagikan ke siswa. Kosongkan ('') jika siswa boleh daftar tanpa kode.
    // - kode_guru  : dibagikan HANYA ke guru. WAJIB diisi; jika kosong, pendaftaran guru ditutup.
    // Gunakan kode guru yang panjang & sulit ditebak (mis. 10+ karakter) dan jangan sama dengan kode siswa.
    'kode_siswa' => 'SISWA2026',
    'kode_guru'  => 'GantiKodeGuruIni-8f3K',
 
    // true = hanya menerima email @gmail.com
    'hanya_gmail' => true,
];
