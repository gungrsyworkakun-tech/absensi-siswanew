<?php
/**
 * Proteksi halaman: pastikan sudah login sebelum mengakses halaman manapun
 * kecuali login.php. Panggil di baris paling atas setiap halaman terproteksi.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

if (empty($_SESSION['user_id'])) {
    redirect('login.php');
}

function currentUser() {
    return [
        'id'       => $_SESSION['user_id'] ?? null,
        'nama'     => $_SESSION['user_nama'] ?? '',
        'role'     => $_SESSION['user_role'] ?? '',
        'siswa_id' => $_SESSION['user_siswa_id'] ?? null,
    ];
}

/**
 * Batasi akses halaman hanya untuk role tertentu.
 * Contoh: requireRole(['admin','guru']);
 */
function requireRole(array $roles) {
    $user = currentUser();
    if (!in_array($user['role'], $roles, true)) {
        setFlash('error', 'Anda tidak memiliki akses ke halaman tersebut.');
        redirect('index.php');
    }
}
