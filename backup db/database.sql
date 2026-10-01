-- =========================================================
-- DATABASE: db_absensi_sekolah
-- Sistem Dashboard Absensi & Akademik Sekolah SMA
-- =========================================================

CREATE DATABASE IF NOT EXISTS db_absensi_sekolah CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE db_absensi_sekolah;

-- =========================================================
-- TABEL KELAS
-- =========================================================
CREATE TABLE kelas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_kelas VARCHAR(50) NOT NULL,       -- contoh: X IPA 1
    tingkat VARCHAR(10) NOT NULL,          -- X, XI, XII
    jurusan VARCHAR(50) DEFAULT NULL,      -- IPA, IPS, Bahasa
    wali_kelas VARCHAR(100) DEFAULT NULL,  -- nama wali kelas (teks, untuk cetak rapor)
    wali_kelas_user_id INT DEFAULT NULL,   -- akun guru (users) yang menjadi wali kelas
    tahun_ajaran VARCHAR(20) NOT NULL,     -- 2025/2026
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =========================================================
-- TABEL SISWA (BIODATA LENGKAP)
-- =========================================================
CREATE TABLE siswa (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nis VARCHAR(20) NOT NULL UNIQUE,
    nisn VARCHAR(20) DEFAULT NULL,
    nama_lengkap VARCHAR(100) NOT NULL,
    jenis_kelamin ENUM('L','P') NOT NULL,
    tempat_lahir VARCHAR(50) DEFAULT NULL,
    tanggal_lahir DATE DEFAULT NULL,
    agama VARCHAR(30) DEFAULT NULL,
    alamat TEXT,
    no_hp VARCHAR(20) DEFAULT NULL,
    email VARCHAR(100) DEFAULT NULL,
    nama_ayah VARCHAR(100) DEFAULT NULL,
    nama_ibu VARCHAR(100) DEFAULT NULL,
    no_hp_ortu VARCHAR(20) DEFAULT NULL,
    pekerjaan_ortu VARCHAR(50) DEFAULT NULL,
    foto VARCHAR(255) DEFAULT NULL,
    kelas_id INT DEFAULT NULL,
    status ENUM('Aktif','Pindah','Lulus','Keluar') DEFAULT 'Aktif',
    tanggal_masuk DATE DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =========================================================
-- TABEL USERS (LOGIN: admin, guru, siswa)
-- =========================================================
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    nama VARCHAR(100) NOT NULL,
    role ENUM('admin','guru','wali_kelas','siswa') NOT NULL DEFAULT 'siswa',
    siswa_id INT DEFAULT NULL,          -- diisi jika role = siswa
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Hubungkan kelas ke akun guru sebagai wali kelas (FK ditambahkan di sini karena
-- tabel users baru saja dibuat di atas)
ALTER TABLE kelas
    ADD FOREIGN KEY (wali_kelas_user_id) REFERENCES users(id) ON DELETE SET NULL;

-- =========================================================
-- TABEL MATA PELAJARAN
-- =========================================================
CREATE TABLE mata_pelajaran (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kode_mapel VARCHAR(20) DEFAULT NULL,
    nama_mapel VARCHAR(100) NOT NULL,
    kkm INT DEFAULT 75
) ENGINE=InnoDB;

-- =========================================================
-- TABEL ABSENSI
-- =========================================================
CREATE TABLE absensi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    siswa_id INT NOT NULL,
    kelas_id INT NOT NULL,
    tanggal DATE NOT NULL,
    status ENUM('Hadir','Izin','Sakit','Alpa') NOT NULL DEFAULT 'Hadir',
    keterangan VARCHAR(255) DEFAULT NULL,
    input_oleh VARCHAR(100) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE,
    FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE CASCADE,
    UNIQUE KEY unique_absen (siswa_id, tanggal)
) ENGINE=InnoDB;

-- =========================================================
-- TABEL NILAI (RAPOR)
-- =========================================================
CREATE TABLE nilai (
    id INT AUTO_INCREMENT PRIMARY KEY,
    siswa_id INT NOT NULL,
    mapel_id INT NOT NULL,
    semester ENUM('Ganjil','Genap') NOT NULL,
    tahun_ajaran VARCHAR(20) NOT NULL,
    nilai_tugas DECIMAL(5,2) DEFAULT 0,
    nilai_uts DECIMAL(5,2) DEFAULT 0,
    nilai_uas DECIMAL(5,2) DEFAULT 0,
    nilai_akhir DECIMAL(5,2) DEFAULT 0,
    predikat VARCHAR(5) DEFAULT NULL,
    catatan VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE,
    FOREIGN KEY (mapel_id) REFERENCES mata_pelajaran(id) ON DELETE CASCADE,
    UNIQUE KEY unique_nilai (siswa_id, mapel_id, semester, tahun_ajaran)
) ENGINE=InnoDB;

-- =========================================================
-- TABEL PENGUMUMAN
-- =========================================================
CREATE TABLE pengumuman (
    id INT AUTO_INCREMENT PRIMARY KEY,
    judul VARCHAR(150) NOT NULL,
    isi TEXT NOT NULL,
    kategori ENUM('Umum','Akademik','Kegiatan','Penting') DEFAULT 'Umum',
    penulis VARCHAR(100) DEFAULT NULL,
    tanggal DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =========================================================
-- TABEL LOKASI SEKOLAH (titik & radius absen GPS, diatur Superadmin)
-- =========================================================
CREATE TABLE lokasi_sekolah (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_lokasi VARCHAR(100) NOT NULL DEFAULT 'Sekolah',
    latitude DECIMAL(10,7) NOT NULL,
    longitude DECIMAL(10,7) NOT NULL,
    radius_meter INT NOT NULL DEFAULT 150,
    jam_mulai TIME NOT NULL DEFAULT '06:00:00',
    jam_selesai TIME NOT NULL DEFAULT '08:00:00',
    aktif TINYINT(1) NOT NULL DEFAULT 1,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =========================================================
-- TABEL LOG ABSENSI GPS (jejak setiap percobaan absen mandiri siswa)
-- =========================================================
CREATE TABLE absensi_gps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    siswa_id INT NOT NULL,
    kelas_id INT NOT NULL,
    tanggal DATE NOT NULL,
    waktu TIME NOT NULL,
    latitude DECIMAL(10,7) NOT NULL,
    longitude DECIMAL(10,7) NOT NULL,
    jarak_meter DECIMAL(10,2) NOT NULL,
    akurasi_meter DECIMAL(10,2) DEFAULT NULL,
    status ENUM('Berhasil','Ditolak') NOT NULL,
    alasan_ditolak VARCHAR(150) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE,
    FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =========================================================
-- E-LEARNING: MATERI
-- =========================================================
CREATE TABLE elearning_materi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kelas_id INT NOT NULL,
    mapel_id INT NOT NULL,
    guru_id INT DEFAULT NULL,
    guru_nama VARCHAR(100) DEFAULT NULL,
    judul VARCHAR(150) NOT NULL,
    deskripsi TEXT,
    file_materi VARCHAR(255) DEFAULT NULL,
    file_nama_asli VARCHAR(255) DEFAULT NULL,
    tanggal DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE CASCADE,
    FOREIGN KEY (mapel_id) REFERENCES mata_pelajaran(id) ON DELETE CASCADE,
    FOREIGN KEY (guru_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =========================================================
-- E-LEARNING: TUGAS
-- =========================================================
CREATE TABLE elearning_tugas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kelas_id INT NOT NULL,
    mapel_id INT NOT NULL,
    guru_id INT DEFAULT NULL,
    guru_nama VARCHAR(100) DEFAULT NULL,
    judul VARCHAR(150) NOT NULL,
    deskripsi TEXT,
    file_lampiran VARCHAR(255) DEFAULT NULL,
    file_nama_asli VARCHAR(255) DEFAULT NULL,
    deadline DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE CASCADE,
    FOREIGN KEY (mapel_id) REFERENCES mata_pelajaran(id) ON DELETE CASCADE,
    FOREIGN KEY (guru_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =========================================================
-- E-LEARNING: PENGUMPULAN TUGAS SISWA
-- =========================================================
CREATE TABLE elearning_pengumpulan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tugas_id INT NOT NULL,
    siswa_id INT NOT NULL,
    file_jawaban VARCHAR(255) DEFAULT NULL,
    file_nama_asli VARCHAR(255) DEFAULT NULL,
    catatan TEXT,
    nilai DECIMAL(5,2) DEFAULT NULL,
    feedback_guru TEXT,
    status ENUM('Belum Dinilai','Sudah Dinilai') NOT NULL DEFAULT 'Belum Dinilai',
    tanggal_kumpul TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tugas_id) REFERENCES elearning_tugas(id) ON DELETE CASCADE,
    FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE,
    UNIQUE KEY unique_kumpul (tugas_id, siswa_id)
) ENGINE=InnoDB;

-- =========================================================
-- DATA AWAL (SEED)
-- =========================================================

-- Catatan: akun admin TIDAK di-seed di sini karena hash password harus dibuat
-- oleh PHP (bcrypt otomatis salted, beda tiap generate). Setelah import database ini,
-- buka file setup.php di browser SATU KALI untuk membuat akun admin awal
-- (username: admin, password: admin123). Setelah berhasil, hapus/rename file setup.php.

INSERT INTO kelas (nama_kelas, tingkat, jurusan, wali_kelas, tahun_ajaran) VALUES
('X IPA 1', 'X', 'IPA', 'Budi Santoso, S.Pd', '2025/2026'),
('X IPA 2', 'X', 'IPA', 'Siti Aminah, S.Pd', '2025/2026'),
('XI IPS 1', 'XI', 'IPS', 'Ahmad Fauzi, S.Pd', '2025/2026'),
('XII IPA 1', 'XII', 'IPA', 'Dewi Lestari, S.Pd', '2025/2026');

INSERT INTO mata_pelajaran (kode_mapel, nama_mapel, kkm) VALUES
('MTK', 'Matematika', 75),
('BIN', 'Bahasa Indonesia', 75),
('BIG', 'Bahasa Inggris', 75),
('FIS', 'Fisika', 75),
('KIM', 'Kimia', 75),
('BIO', 'Biologi', 75),
('PKN', 'PPKn', 75),
('SEJ', 'Sejarah Indonesia', 75);

-- Titik lokasi sekolah default untuk absen GPS (WAJIB diubah lewat menu
-- "Lokasi Absensi GPS" agar sesuai koordinat sekolah Anda yang sebenarnya)
INSERT INTO lokasi_sekolah (nama_lokasi, latitude, longitude, radius_meter, jam_mulai, jam_selesai) VALUES
('Lokasi Sekolah (Contoh - Harap Diubah)', -8.6500000, 115.2167000, 150, '06:00:00', '08:00:00');

INSERT INTO pengumuman (judul, isi, kategori, penulis, tanggal) VALUES
('Selamat Datang di Tahun Ajaran Baru 2025/2026', 'Kepada seluruh siswa-siswi, selamat datang di tahun ajaran baru. Mari kita sambut dengan semangat belajar yang tinggi.', 'Umum', 'Admin', CURDATE()),
('Jadwal Penilaian Tengah Semester', 'Penilaian Tengah Semester (PTS) akan dilaksanakan sesuai jadwal yang telah ditentukan oleh masing-masing guru mata pelajaran.', 'Akademik', 'Admin', CURDATE());

-- CATATAN PENTING:
-- Hash password di atas hanya contoh, silakan generate ulang hash bcrypt yang valid
-- menggunakan PHP: password_hash('admin123', PASSWORD_BCRYPT)
-- lalu update kolom password pada tabel users, atau gunakan file reset_password.php
-- yang disediakan pada folder proyek.
