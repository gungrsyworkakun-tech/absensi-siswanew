<?php
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth_ui.php';
require_once __DIR__ . '/includes/mailer.php';

if (!empty($_SESSION['user_id'])) {
    redirect('index.php');
}

const LUPA_MAKS_SALAH   = 3;    // kesempatan salah memasukkan username
const LUPA_JENDELA_DTK  = 600;  // setelah habis, tunggu 10 menit

if (empty($_SESSION['auth_csrf'])) $_SESSION['auth_csrf'] = bin2hex(random_bytes(32));

$now = time();
$_SESSION['lupa_gagal'] = array_values(array_filter($_SESSION['lupa_gagal'] ?? [], fn($t) => $t > $now - LUPA_JENDELA_DTK));

function menitTunggu(int $now): int {
    $tertua = min($_SESSION['lupa_gagal']);
    return max(1, (int)ceil(($tertua + LUPA_JENDELA_DTK - $now) / 60));
}

$error       = '';
$sent        = false;
$emailMasked = '';
$terkunci    = count($_SESSION['lupa_gagal']) >= LUPA_MAKS_SALAH;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$terkunci) {
    $username = trim($_POST['username'] ?? '');

    if (!empty($_POST['website'])) {
        exit; // honeypot
    }

    if (!hash_equals($_SESSION['auth_csrf'], (string)($_POST['csrf'] ?? ''))) {
        $error = 'Sesi tidak valid. Muat ulang halaman lalu coba lagi.';
    } elseif ($username === '') {
        $error = 'Username wajib diisi.';
    } else {
        try {
            $hasil = kirimTautanReset($pdo, $username);

            switch ($hasil['status']) {
                case 'terkirim':
                    $sent = true;
                    $emailMasked = $hasil['email'];
                    $_SESSION['lupa_gagal'] = [];
                    break;

                case 'tidak_ditemukan':
                    $_SESSION['lupa_gagal'][] = $now;
                    $sisa = LUPA_MAKS_SALAH - count($_SESSION['lupa_gagal']);
                    if ($sisa <= 0) {
                        $terkunci = true;
                    } else {
                        $error = 'Username salah atau tidak ditemukan. Sisa kesempatan: ' . $sisa . ' dari ' . LUPA_MAKS_SALAH . '.';
                    }
                    break;

                case 'tanpa_email':
                    $error = 'Akun ini belum memiliki email terdaftar, sehingga tidak bisa direset otomatis. Hubungi administrator sekolah.';
                    break;

                default: // dibatasi
                    $error = 'Permintaan reset untuk akun ini sudah terlalu sering. Coba lagi dalam 1 jam.';
            }
        } catch (Throwable $e) {
            error_log('Lupa password error: ' . $e->getMessage());
            $error = 'Email tidak dapat dikirim saat ini. Silakan coba lagi nanti atau hubungi administrator.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<?php authHead('Lupa Kata Sandi'); ?>
<body>

<div class="lg-wrap">
  <?php authSide(); ?>

  <div class="lg-form-side">
    <div class="lg-form-box">

      <?php authMobileBrand(); ?>

<?php if ($sent): ?>
      <div class="lg-state">
        <div class="lg-state-icon ok"><i class="bi bi-envelope-check-fill"></i></div>
        <div class="lg-form-title">Periksa Email Anda</div>
        <div class="lg-form-hint">
          Tautan untuk mengatur ulang kata sandi sudah dikirim ke <b><?= clean($emailMasked) ?></b>.
          Tautan berlaku <b><?= (int)RESET_MENIT ?> menit</b>. Cek juga folder <b>Spam</b>.
        </div>
        <a class="lg-submit text-decoration-none" href="<?= BASE_URL ?>/login.php"><i class="bi bi-arrow-left"></i> Kembali ke Login</a>
      </div>

<?php elseif ($terkunci): ?>
      <div class="lg-state">
        <div class="lg-state-icon bad"><i class="bi bi-lock-fill"></i></div>
        <div class="lg-form-title">Kesempatan Habis</div>
        <div class="lg-form-hint">
          Anda sudah <?= LUPA_MAKS_SALAH ?> kali salah memasukkan username.
          Coba lagi dalam <b><?= menitTunggu($now) ?> menit</b>, atau hubungi administrator sekolah.
        </div>
        <a class="lg-submit text-decoration-none" href="<?= BASE_URL ?>/login.php"><i class="bi bi-arrow-left"></i> Kembali ke Login</a>
      </div>

<?php else: ?>
      <div class="lg-form-title">Lupa Kata Sandi?</div>
      <div class="lg-form-hint">Masukkan username Anda. Tautan pengaturan ulang kata sandi akan dikirim ke email yang terdaftar pada akun tersebut. Anda punya <?= LUPA_MAKS_SALAH ?> kali kesempatan.</div>

      <?php if ($error): ?>
        <div class="lg-error"><i class="bi bi-exclamation-circle-fill"></i> <span><?= clean($error) ?></span></div>
      <?php endif; ?>

      <form method="POST" novalidate data-lock>
        <input type="hidden" name="csrf" value="<?= clean($_SESSION['auth_csrf']) ?>">
        <input class="hp" type="text" name="website" tabindex="-1" autocomplete="off">
        <div class="lg-input-group">
          <i class="bi bi-person-fill lg-ic"></i>
          <input type="text" name="username" class="form-control" placeholder="Username" required autofocus
                 autocomplete="username" autocapitalize="none" autocorrect="off" spellcheck="false" enterkeyhint="send"
                 value="<?= clean($_POST['username'] ?? '') ?>">
        </div>
        <button type="submit" class="lg-submit mt-2"><i class="bi bi-send-fill"></i> Kirim Tautan Reset</button>
      </form>

      <p class="text-center small mt-4 mb-0">
        <a class="lg-link" href="<?= BASE_URL ?>/login.php"><i class="bi bi-arrow-left"></i> Kembali ke Login</a>
      </p>
<?php endif; ?>

    </div>
  </div>
</div>

<?php authScripts(); ?>
</body>
</html>