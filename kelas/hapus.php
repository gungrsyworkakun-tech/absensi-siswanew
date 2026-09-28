<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole(['admin']);

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("DELETE FROM kelas WHERE id = ?");
$stmt->execute([$id]);

setFlash('success', 'Kelas berhasil dihapus.');
redirect('kelas/list.php');
