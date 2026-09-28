<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole(['admin']);

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT foto FROM siswa WHERE id = ?");
$stmt->execute([$id]);
$siswa = $stmt->fetch();

if ($siswa) {
    if (!empty($siswa['foto']) && file_exists(__DIR__ . '/../uploads/foto_siswa/' . $siswa['foto'])) {
        unlink(__DIR__ . '/../uploads/foto_siswa/' . $siswa['foto']);
    }
    $stmt = $pdo->prepare("DELETE FROM siswa WHERE id = ?");
    $stmt->execute([$id]);
    setFlash('success', 'Data siswa berhasil dihapus.');
} else {
    setFlash('error', 'Data siswa tidak ditemukan.');
}

redirect('siswa/list.php');
