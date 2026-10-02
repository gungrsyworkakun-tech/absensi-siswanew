<?php
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth_ui.php';

if (!empty($_SESSION['user_id'])) {
    redirect('index.php');
}

$error  = '';
$notice = $_SESSION['login_notice'] ?? '';   // mis. "Kata sandi berhasil diubah"
unset($_SESSION['login_notice']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Username dan password wajib diisi.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']       = $user['id'];
            $_SESSION['user_nama']     = $user['nama'];
            $_SESSION['user_role']     = $user['role'];
            $_SESSION['user_siswa_id'] = $user['siswa_id'];
            redirect('index.php');
        } else {
            $error = 'Username atau password salah.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<?php authHead('Masuk'); ?>
<body>

<div class="lg-wrap">
  <?php authSide(); ?>

  <div class="lg-form-side">
    <div class="lg-form-box">

      <?php authMobileBrand(); ?>

      <div class="lg-form-title">Selamat Datang</div>
      <div class="lg-form-hint">Masuk menggunakan akun yang sudah terdaftar.</div>

      <?php if ($notice): ?>
        <div class="lg-notice"><i class="bi bi-check-circle-fill"></i> <span><?= clean($notice) ?></span></div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="lg-error"><i class="bi bi-exclamation-circle-fill"></i> <span><?= clean($error) ?></span></div>
      <?php endif; ?>

      <form method="POST" novalidate data-lock>
        <div class="lg-input-group">
          <i class="bi bi-person-fill lg-ic"></i>
          <input type="text" name="username" class="form-control" placeholder="Username" required autofocus
                 autocomplete="username" autocapitalize="none" autocorrect="off" spellcheck="false" enterkeyhint="next"
                 value="<?= clean($_POST['username'] ?? '') ?>">
        </div>
        <div class="lg-input-group">
          <i class="bi bi-lock-fill lg-ic"></i>
          <input type="password" name="password" id="lgPassword" class="form-control" placeholder="Password" required
                 autocomplete="current-password" enterkeyhint="go">
          <button type="button" class="lg-toggle-pass" data-toggle-pass="lgPassword" tabindex="-1" aria-label="Tampilkan password">
            <i class="bi bi-eye"></i>
          </button>
        </div>

        <div class="lg-row">
          <span></span>
          <a class="lg-link" href="<?= BASE_URL ?>/lupa_password.php">Lupa kata sandi?</a>
        </div>

        <button type="submit" class="lg-submit">
          <i class="bi bi-box-arrow-in-right"></i> Masuk
        </button>
      </form>

      <p class="text-center text-muted small mt-4 mb-0">
        Lupa akses? Hubungi administrator sekolah.
      </p>
    </div>
  </div>
</div>

<?php authScripts(); ?>
</body>
</html>