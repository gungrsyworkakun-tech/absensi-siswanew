-- =========================================================
-- MODUL UJIAN (UTS / UAS)
-- Jalankan di tab SQL phpMyAdmin, database db_absensi_sekolah
-- =========================================================

CREATE TABLE ujian (
    id INT AUTO_INCREMENT PRIMARY KEY,
    judul VARCHAR(150) NOT NULL,
    jenis ENUM('UTS','UAS') NOT NULL,
    kelas_id INT NOT NULL,
    mapel_id INT NOT NULL,
    guru_id INT DEFAULT NULL,
    guru_nama VARCHAR(100) DEFAULT NULL,
    semester ENUM('Ganjil','Genap') NOT NULL,
    tahun_ajaran VARCHAR(20) NOT NULL,
    petunjuk TEXT,
    durasi_menit INT NOT NULL DEFAULT 60,
    waktu_mulai DATETIME NOT NULL,
    waktu_selesai DATETIME NOT NULL,
    acak_soal TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('Draft','Terbit') NOT NULL DEFAULT 'Draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE CASCADE,
    FOREIGN KEY (mapel_id) REFERENCES mata_pelajaran(id) ON DELETE CASCADE,
    FOREIGN KEY (guru_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE ujian_soal (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ujian_id INT NOT NULL,
    tipe ENUM('pilihan_ganda','essay') NOT NULL,
    pertanyaan TEXT NOT NULL,
    opsi_a VARCHAR(500) DEFAULT NULL,
    opsi_b VARCHAR(500) DEFAULT NULL,
    opsi_c VARCHAR(500) DEFAULT NULL,
    opsi_d VARCHAR(500) DEFAULT NULL,
    opsi_e VARCHAR(500) DEFAULT NULL,
    kunci CHAR(1) DEFAULT NULL,          -- a-e, hanya untuk pilihan ganda
    pedoman TEXT,                         -- pedoman penilaian, hanya untuk essay (dilihat guru)
    bobot DECIMAL(5,2) NOT NULL DEFAULT 1,
    urutan INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ujian_id) REFERENCES ujian(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE ujian_peserta (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ujian_id INT NOT NULL,
    siswa_id INT NOT NULL,
    waktu_mulai DATETIME NOT NULL,
    waktu_selesai DATETIME DEFAULT NULL,
    status ENUM('Berlangsung','Selesai') NOT NULL DEFAULT 'Berlangsung',
    nilai_pg DECIMAL(6,2) NOT NULL DEFAULT 0,
    nilai_essay DECIMAL(6,2) NOT NULL DEFAULT 0,
    nilai_akhir DECIMAL(6,2) NOT NULL DEFAULT 0,
    dinilai TINYINT(1) NOT NULL DEFAULT 0,   -- 1 jika semua essay sudah diberi skor
    FOREIGN KEY (ujian_id) REFERENCES ujian(id) ON DELETE CASCADE,
    FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE,
    UNIQUE KEY unique_peserta (ujian_id, siswa_id)
) ENGINE=InnoDB;

CREATE TABLE ujian_jawaban (
    id INT AUTO_INCREMENT PRIMARY KEY,
    peserta_id INT NOT NULL,
    soal_id INT NOT NULL,
    jawaban MEDIUMTEXT,
    skor DECIMAL(5,2) DEFAULT NULL,
    FOREIGN KEY (peserta_id) REFERENCES ujian_peserta(id) ON DELETE CASCADE,
    FOREIGN KEY (soal_id) REFERENCES ujian_soal(id) ON DELETE CASCADE,
    UNIQUE KEY unique_jawaban (peserta_id, soal_id)
) ENGINE=InnoDB;
