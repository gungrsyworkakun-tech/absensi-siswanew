# Modul Register Siswa & Guru

## Pemasangan
1. Salin folder `register/` ke root web project Anda (sejajar dengan `login.php`).
2. Edit `config.php`:
   - `db` → sesuaikan user/password database
   - `mail` → isi Gmail pengirim + **App Password** (bukan password Gmail biasa)
   - `login_url` → alamat halaman login
   - `kode_pendaftaran` → kode rahasia untuk siswa/guru (kosongkan untuk menonaktifkan)
3. Buka `http://domain-anda/register/`

## Membuat App Password Gmail
Akun Google → Keamanan → aktifkan **Verifikasi 2 Langkah** → **Sandi aplikasi** → buat → salin 16 karakter ke `config.php`.

## Alur
- Siswa: isi data → masuk tabel `siswa` + `users` (role `siswa`, terhubung lewat `siswa_id`)
- Guru : isi data → masuk tabel `users` (role `guru`) + `guru_profil`
- Password acak 10 karakter → di-hash bcrypt (`$2y$12$`, sama seperti data lama) → dikirim ke email
- Jika email gagal terkirim, data otomatis dibatalkan (rollback) sehingga tidak ada akun tanpa password

## Catatan
- Role `wali_kelas` tetap diatur admin (guru mendaftar sebagai `guru`).
- Pastikan PHP ekstensi `pdo_mysql` dan `openssl` aktif.
- Error teknis dicatat di error log PHP, tidak ditampilkan ke pengguna.
