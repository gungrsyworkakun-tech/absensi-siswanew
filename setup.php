<?php
/**
 * JALANKAN FILE INI SATU KALI SAJA lewat browser setelah import database.sql,
 * untuk membuat akun admin pertama. Setelah berhasil membuat akun,
 * SEGERA HAPUS atau RENAME file ini demi keamanan.
 */
require_once __DIR__ . '/config/database.php';

$pesan = '';
$sukses = false;

// Cegah dijalankan berulang kali jika sudah ada admin
$cekAdmin = $pdo->query("SELECT COUNT(*) AS jumlah FROM users WHERE role = 'admin'")->fetch();

if ($cekAdmin['jumlah'] > 0) {
    $pesan = 'Akun admin sudah ada. Demi keamanan, hapus file setup.php ini dari server.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? 'admin');
    $password = $_POST['password'] ?? '';
    $nama     = trim($_POST['nama'] ?? 'Administrator');

    if (strlen($password) < 6) {
        $pesan = 'Password minimal 6 karakter.';
    } else {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password, nama, role) VALUES (?, ?, ?, 'admin')");
        $stmt->execute([$username, $hash, $nama]);
        $sukses = true;
        $pesan = "Akun admin berhasil dibuat! Silakan login dengan username '{$username}'. JANGAN LUPA HAPUS FILE setup.php SEKARANG.";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Setup Awal - SIM Sekolah</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container" style="max-width:480px; margin-top:80px;">
  <div class="card shadow-sm">
    <div class="card-body p-4">
      <h5 class="fw-bold mb-3">Setup Akun Admin Awal</h5>

      <?php if ($pesan): ?>
        <div class="alert alert-<?= $sukses ? 'success' : 'warning' ?>"><?= htmlspecialchars($pesan) ?></div>
      <?php endif; ?>

      <?php if (!$sukses && $cekAdmin['jumlah'] == 0): ?>
      <form method="POST">
        <div class="mb-3">
          <label class="form-label">Username</label>
          <input type="text" name="username" class="form-control" value="admin" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Nama Lengkap</label>
          <input type="text" name="nama" class="form-control" value="Administrator" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Password</label>
          <input type="password" name="password" class="form-control" placeholder="Minimal 6 karakter" required>
        </div>
        <button class="btn btn-primary w-100">Buat Akun Admin</button>
      </form>
      <?php elseif ($sukses): ?>
        <a href="login.php" class="btn btn-primary w-100">Ke Halaman Login</a>
      <?php endif; ?>
    </div>
  </div>
</div>
</body>
</html>
