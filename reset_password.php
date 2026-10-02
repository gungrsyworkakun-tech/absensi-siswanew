<?php
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth_ui.php';
require_once __DIR__ . '/includes/mailer.php';

if (empty($_SESSION['auth_csrf'])) $_SESSION['auth_csrf'] = bin2hex(random_bytes(32));

// ---- Validasi token dari link email ----
$token = (string)($_GET['token'] ?? $_POST['token'] ?? '');
$row   = null;
if (preg_match('/^[a-f0-9]{64}$/', $token)) {
    $q = $pdo->prepare('SELECT r.id, r.user_id, u.username, u.nama
                        FROM password_resets r JOIN users u ON u.id = r.user_id
                        WHERE r.token_hash = ? AND r.used_at IS NULL AND r.expires_at > NOW() LIMIT 1');
    $q->execute([hash('sha256', $token)]);
    $row = $q->fetch() ?: null;
}

function validasiPassword(string $p): ?string {
    if (strlen($p) < 8)               return 'Kata sandi minimal 8 karakter.';
    if (strlen($p) > 72)              return 'Kata sandi maksimal 72 karakter.';
    if (!preg_match('/[A-Za-z]/', $p) || !preg_match('/\d/', $p)) return 'Kata sandi harus mengandung huruf dan angka.';
    return null;
}

$error = '';

if ($row && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $p1 = (string)($_POST['password'] ?? '');
    $p2 = (string)($_POST['password2'] ?? '');

    if (!hash_equals($_SESSION['auth_csrf'], (string)($_POST['csrf'] ?? ''))) {
        $error = 'Sesi tidak valid. Muat ulang halaman lalu coba lagi.';
    } elseif ($err = validasiPassword($p1)) {
        $error = $err;
    } elseif ($p1 !== $p2) {
        $error = 'Konfirmasi kata sandi tidak sama.';
    } else {
        try {
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')
                ->execute([password_hash($p1, PASSWORD_BCRYPT, ['cost' => 12]), $row['user_id']]);
            $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL')
                ->execute([$row['user_id']]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Reset password error: ' . $e->getMessage());
            $error = 'Terjadi kesalahan. Silakan coba lagi.';
        }

        if ($error === '') {
            // Email konfirmasi "kata sandi diubah" — kegagalan kirim email tidak membatalkan reset
            try { kirimNotifPasswordBerubah($pdo, (int)$row['user_id']); }
            catch (Throwable $e) { error_log('Notif ganti sandi gagal: ' . $e->getMessage()); }

            $_SESSION['login_notice'] = 'Kata sandi berhasil diubah. Silakan masuk dengan kata sandi baru.';
            $_SESSION['auth_csrf'] = bin2hex(random_bytes(32));
            redirect('login.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<?php authHead('Atur Ulang Kata Sandi'); ?>
<body>

<div class="lg-wrap">
  <?php authSide(); ?>

  <div class="lg-form-side">
    <div class="lg-form-box">

      <?php authMobileBrand(); ?>

<?php if (!$row): ?>
      <div class="lg-state">
        <div class="lg-state-icon bad"><i class="bi bi-link-45deg"></i></div>
        <div class="lg-form-title">Tautan Tidak Berlaku</div>
        <div class="lg-form-hint">Tautan ini sudah kedaluwarsa, sudah dipakai, atau tidak valid. Silakan minta tautan baru.</div>
        <a class="lg-submit text-decoration-none" href="<?= BASE_URL ?>/lupa_password.php"><i class="bi bi-envelope"></i> Minta Tautan Baru</a>
        <p class="mt-3 mb-0"><a class="lg-link small" href="<?= BASE_URL ?>/login.php">Kembali ke Login</a></p>
      </div>

<?php else: ?>
      <div class="lg-form-title">Buat Kata Sandi Baru</div>
      <div class="lg-form-hint">Akun <b><?= clean($row['username']) ?></b>. Minimal 8 karakter, kombinasi huruf dan angka.</div>

      <?php if ($error): ?>
        <div class="lg-error"><i class="bi bi-exclamation-circle-fill"></i> <span><?= clean($error) ?></span></div>
      <?php endif; ?>

      <form method="POST" novalidate data-lock>
        <input type="hidden" name="csrf" value="<?= clean($_SESSION['auth_csrf']) ?>">
        <input type="hidden" name="token" value="<?= clean($token) ?>">

        <div class="lg-input-group">
          <i class="bi bi-lock-fill lg-ic"></i>
          <input type="password" name="password" id="pw1" class="form-control" placeholder="Kata sandi baru" required autofocus
                 autocomplete="new-password" minlength="8" maxlength="72" enterkeyhint="next">
          <button type="button" class="lg-toggle-pass" data-toggle-pass="pw1" tabindex="-1" aria-label="Tampilkan kata sandi"><i class="bi bi-eye"></i></button>
        </div>
        <div class="lg-meter"><span id="pwBar"></span></div>
        <div class="lg-meter-label" id="pwLabel"></div>

        <div class="lg-input-group">
          <i class="bi bi-shield-lock-fill lg-ic"></i>
          <input type="password" name="password2" id="pw2" class="form-control" placeholder="Ulangi kata sandi baru" required
                 autocomplete="new-password" maxlength="72" enterkeyhint="go">
          <button type="button" class="lg-toggle-pass" data-toggle-pass="pw2" tabindex="-1" aria-label="Tampilkan kata sandi"><i class="bi bi-eye"></i></button>
        </div>

        <button type="submit" class="lg-submit mt-2"><i class="bi bi-check2-circle"></i> Simpan Kata Sandi Baru</button>
      </form>
<?php endif; ?>

    </div>
  </div>
</div>

<?php authScripts(); ?>
<script>
(function () {
  var pw = document.getElementById('pw1'); if (!pw) return;
  var bar = document.getElementById('pwBar'), lbl = document.getElementById('pwLabel');
  pw.addEventListener('input', function () {
    var v = pw.value, s = 0;
    if (v.length >= 8) s++;
    if (v.length >= 12) s++;
    if (/[a-z]/.test(v) && /[A-Z]/.test(v)) s++;
    if (/\d/.test(v)) s++;
    if (/[^A-Za-z0-9]/.test(v)) s++;
    var lv = v.length === 0 ? 0 : Math.min(4, Math.max(1, s - (v.length < 8 ? 1 : 0)));
    var warna = ['transparent', '#dc2626', '#f59e0b', '#84cc16', '#16a34a'];
    var nama  = ['', 'Lemah', 'Cukup', 'Kuat', 'Sangat kuat'];
    bar.style.width = (lv * 25) + '%';
    bar.style.background = warna[lv];
    lbl.textContent = nama[lv] ? 'Kekuatan: ' + nama[lv] : '';
  });
})();
</script>
</body>
</html>