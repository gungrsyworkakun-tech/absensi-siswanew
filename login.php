<?php
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

if (!empty($_SESSION['user_id'])) {
    redirect('index.php');
}

$error = '';

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
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Masuk - SIM Sekolah</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700;800&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
<?php include __DIR__ . '/includes/theme_gov.php'; ?>
<style>
html, body { height:100%; }
body{ overflow-x:hidden; }

.lg-wrap{ min-height:100vh; display:flex; }

/* ---- Panel kiri: navy + highlight fitur ---- */
.lg-side{
  flex:1 1 50%; position:relative; overflow:hidden;
  background:radial-gradient(120% 140% at 15% 0%, #1C2540 0%, var(--navy) 45%, var(--navy-2) 100%);
  color:#fff; padding:56px 52px; display:flex; flex-direction:column; justify-content:space-between;
}
.lg-side::before, .lg-side::after{
  content:""; position:absolute; border-radius:50%; background:rgba(244,183,64,.08);
}
.lg-side::before{ width:380px; height:380px; right:-140px; top:-120px; }
.lg-side::after{ width:260px; height:260px; left:-100px; bottom:-80px; background:rgba(37,99,235,.10); }

.lg-logo-badge{
  width:52px; height:52px; border-radius:14px;
  background:linear-gradient(135deg, var(--gold), #D89A1F);
  display:flex; align-items:center; justify-content:center; color:var(--navy-2); font-size:1.5rem;
  box-shadow:0 8px 20px -6px rgba(244,183,64,.5); position:relative; z-index:1;
}
.lg-headline{ font-family:'Sora',sans-serif; font-weight:800; font-size:2.1rem; line-height:1.2; margin:26px 0 12px; position:relative; z-index:1; }
.lg-sub{ color:rgba(255,255,255,.6); font-size:.92rem; max-width:380px; position:relative; z-index:1; }

.lg-feature{ display:flex; align-items:center; gap:14px; margin-top:16px; position:relative; z-index:1; }
.lg-feature-icon{
  width:40px; height:40px; border-radius:10px; background:rgba(255,255,255,.07);
  border:1px solid rgba(255,255,255,.1); display:flex; align-items:center; justify-content:center; flex-shrink:0; font-size:1.05rem;
}
.lg-feature-text b{ display:block; font-size:.86rem; font-weight:700; }
.lg-feature-text span{ font-size:.76rem; color:rgba(255,255,255,.5); }

.lg-side-footer{ font-size:.72rem; color:rgba(255,255,255,.35); position:relative; z-index:1; }

/* ---- Panel kanan: form ---- */
.lg-form-side{ flex:1 1 50%; display:flex; align-items:center; justify-content:center; padding:32px; background:var(--bg); }
.lg-form-box{ width:100%; max-width:390px; animation:gvFadeUp .4s var(--ease) both; }
.lg-form-title{ font-family:'Sora',sans-serif; font-weight:800; font-size:1.5rem; color:var(--ink); margin-bottom:4px; }
.lg-form-hint{ color:var(--ink-soft); font-size:.86rem; margin-bottom:28px; }

.lg-input-group{ position:relative; margin-bottom:16px; }
.lg-input-group i.lg-ic{
  position:absolute; left:14px; top:50%; transform:translateY(-50%); color:var(--ink-soft); font-size:1rem; pointer-events:none;
}
.lg-input-group .form-control{ padding:11px 14px 11px 40px; border-radius:10px; font-size:.9rem; }
.lg-input-group .form-control:focus{ box-shadow:0 0 0 3px var(--brand-soft); border-color:var(--navy); }
.lg-toggle-pass{
  position:absolute; right:6px; top:50%; transform:translateY(-50%); border:none; background:transparent;
  color:var(--ink-soft); width:32px; height:32px; border-radius:8px; display:flex; align-items:center; justify-content:center;
  transition:background .15s, color .15s;
}
.lg-toggle-pass:hover{ background:var(--brand-soft); color:var(--navy); }

.lg-submit{
  width:100%; padding:12px; border-radius:10px; border:none; color:#fff; font-weight:700; font-size:.92rem;
  background:linear-gradient(135deg, var(--navy-3), var(--navy-2)); box-shadow:var(--sh-sm);
  transition:transform .15s var(--ease), box-shadow .2s var(--ease);
  display:flex; align-items:center; justify-content:center; gap:8px;
}
.lg-submit:hover{ transform:translateY(-1px); box-shadow:var(--sh-md); color:#fff; }
.lg-submit:active{ transform:translateY(0) scale(.98); }

.lg-error{
  display:flex; align-items:center; gap:9px; background:var(--bad-soft); color:var(--bad);
  border-radius:10px; padding:11px 14px; font-size:.85rem; font-weight:600; margin-bottom:18px;
  animation:gvFadeUp .3s var(--ease) both;
}

.lg-mobile-brand{ display:none; }

@media (max-width: 900px){
  .lg-side{ display:none; }
  .lg-form-side{ background:var(--bg); }
  .lg-mobile-brand{ display:flex; flex-direction:column; align-items:center; text-align:center; margin-bottom:26px; }
  .lg-mobile-brand .lg-logo-badge{ margin-bottom:10px; }
}
</style>
</head>
<body>

<div class="lg-wrap">
  <!-- Panel kiri: branding & fitur -->
  <div class="lg-side">
    <div>
      <div class="lg-logo-badge"><i class="bi bi-mortarboard-fill"></i></div>
      <div class="lg-headline">Kelola sekolah lebih mudah, dari mana saja.</div>
      <div class="lg-sub">Absensi berbasis GPS, e-learning, dan rapor digital dalam satu sistem terpadu.</div>
    </div>

    <div>
      <div class="lg-feature">
        <div class="lg-feature-icon"><i class="bi bi-geo-alt-fill"></i></div>
        <div class="lg-feature-text"><b>Absensi GPS</b><span>Presensi masuk & pulang tervalidasi lokasi</span></div>
      </div>
      <div class="lg-feature">
        <div class="lg-feature-icon"><i class="bi bi-journal-richtext"></i></div>
        <div class="lg-feature-text"><b>E-Learning</b><span>Materi & tugas dalam satu tempat</span></div>
      </div>
      <div class="lg-feature">
        <div class="lg-feature-icon"><i class="bi bi-clipboard-data-fill"></i></div>
        <div class="lg-feature-text"><b>Rapor Digital</b><span>Nilai & laporan otomatis tersusun rapi</span></div>
      </div>
    </div>

    <div class="lg-side-footer">&copy; <?= date('Y') ?> SIM Sekolah — Sistem Informasi Manajemen Absensi &amp; Akademik</div>
  </div>

  <!-- Panel kanan: form login -->
  <div class="lg-form-side">
    <div class="lg-form-box">

      <div class="lg-mobile-brand">
        <div class="lg-logo-badge"><i class="bi bi-mortarboard-fill"></i></div>
        <div class="fw-bold mt-2" style="font-family:'Sora',sans-serif;">SIM Sekolah</div>
      </div>

      <div class="lg-form-title">Selamat Datang</div>
      <div class="lg-form-hint">Masuk menggunakan akun yang sudah terdaftar.</div>

      <?php if ($error): ?>
        <div class="lg-error"><i class="bi bi-exclamation-circle-fill"></i> <?= clean($error) ?></div>
      <?php endif; ?>

      <form method="POST" novalidate>
        <div class="lg-input-group">
          <i class="bi bi-person-fill lg-ic"></i>
          <input type="text" name="username" class="form-control" placeholder="Username" required autofocus>
        </div>
        <div class="lg-input-group">
          <i class="bi bi-lock-fill lg-ic"></i>
          <input type="password" name="password" id="lgPassword" class="form-control" placeholder="Password" required>
          <button type="button" class="lg-toggle-pass" id="lgTogglePass" tabindex="-1">
            <i class="bi bi-eye" id="lgToggleIcon"></i>
          </button>
        </div>

        <button type="submit" class="lg-submit mt-2">
          <i class="bi bi-box-arrow-in-right"></i> Masuk
        </button>
      </form>

      <p class="text-center text-muted small mt-4 mb-0">
        Lupa akses? Hubungi administrator sekolah.
      </p>
    </div>
  </div>
</div>

<script>
document.getElementById('lgTogglePass')?.addEventListener('click', function () {
  const input = document.getElementById('lgPassword');
  const icon = document.getElementById('lgToggleIcon');
  const isPassword = input.type === 'password';
  input.type = isPassword ? 'text' : 'password';
  icon.className = isPassword ? 'bi bi-eye-slash' : 'bi bi-eye';
});
</script>
</body>
</html>