-- =========================================================
-- MIGRASI V2: Absensi GPS, Wali Kelas, E-Learning
-- HANYA jalankan file ini jika Anda sudah punya database dari versi
-- sebelumnya (tanpa fitur GPS/E-Learning). Instalasi baru cukup pakai
-- database.sql saja (sudah termasuk semua tabel di bawah ini).
-- =========================================================
USE db_absensi_sekolah;

-- 1. Tambah role 'wali_kelas'
ALTER TABLE users MODIFY role ENUM('admin','guru','wali_kelas','siswa') NOT NULL DEFAULT 'siswa';

-- 2. Hubungkan kelas ke akun wali kelas
ALTER TABLE kelas ADD COLUMN wali_kelas_user_id INT DEFAULT NULL AFTER wali_kelas;
ALTER TABLE kelas ADD CONSTRAINT fk_kelas_wali FOREIGN KEY (wali_kelas_user_id) REFERENCES users(id) ON DELETE SET NULL;

-- 3. Lokasi sekolah untuk absensi GPS
CREATE TABLE IF NOT EXISTS lokasi_sekolah (
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

INSERT INTO lokasi_sekolah (nama_lokasi, latitude, longitude, radius_meter, jam_mulai, jam_selesai)
VALUES ('Lokasi Sekolah (Contoh - Harap Diubah)', -8.6500000, 115.2167000, 150, '06:00:00', '08:00:00');

-- 4. Log absensi GPS
CREATE TABLE IF NOT EXISTS absensi_gps (
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

-- 5. E-Learning: Materi
CREATE TABLE IF NOT EXISTS elearning_materi (
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

-- 6. E-Learning: Tugas
CREATE TABLE IF NOT EXISTS elearning_tugas (
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

-- 7. E-Learning: Pengumpulan Tugas
CREATE TABLE IF NOT EXISTS elearning_pengumpulan (
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
-- SETELAH MIGRASI:
-- 1. Buka menu "Lokasi Absensi GPS" (superadmin) → set titik & radius sekolah
-- 2. Buat akun role "wali_kelas" di menu Kelola Akun
-- 3. Hubungkan wali kelas ke kelasnya lewat menu Data Kelas
-- =========================================================
