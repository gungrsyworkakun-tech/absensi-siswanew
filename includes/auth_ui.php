<?php
/**
 * includes/auth_ui.php — tampilan bersama halaman login, lupa & reset kata sandi.
 * Responsif untuk HP, tablet, laptop, dan layar lebar.
 */

function authHead(string $title): void { ?>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="theme-color" content="#0B0F1D">
<title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?> - SIM Sekolah</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700;800&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
<?php include __DIR__ . '/theme_gov.php'; authStyle(); ?>
</head>
<?php }

function authStyle(): void { ?>
<style>
html, body { height:100%; }
body{ overflow-x:hidden; -webkit-text-size-adjust:100%; }

.lg-wrap{ min-height:100vh; min-height:100dvh; display:flex; }

/* ---- Panel kiri (laptop/desktop) ---- */
.lg-side{
  flex:1 1 50%; position:relative; overflow:hidden;
  background:radial-gradient(120% 140% at 15% 0%, #1C2540 0%, var(--navy) 45%, var(--navy-2) 100%);
  color:#fff; padding:56px 52px; display:flex; flex-direction:column; justify-content:space-between;
}
.lg-side::before, .lg-side::after{ content:""; position:absolute; border-radius:50%; background:rgba(244,183,64,.08); }
.lg-side::before{ width:380px; height:380px; right:-140px; top:-120px; }
.lg-side::after{ width:260px; height:260px; left:-100px; bottom:-80px; background:rgba(37,99,235,.10); }
.lg-logo-badge{
  width:52px; height:52px; border-radius:14px; background:linear-gradient(135deg, var(--gold), #D89A1F);
  display:flex; align-items:center; justify-content:center; color:var(--navy-2); font-size:1.5rem;
  box-shadow:0 8px 20px -6px rgba(244,183,64,.5); position:relative; z-index:1;
}
.lg-headline{ font-family:'Sora',sans-serif; font-weight:800; font-size:2.1rem; line-height:1.2; margin:26px 0 12px; position:relative; z-index:1; }
.lg-sub{ color:rgba(255,255,255,.6); font-size:.92rem; max-width:380px; position:relative; z-index:1; }
.lg-feature{ display:flex; align-items:center; gap:14px; margin-top:16px; position:relative; z-index:1; }
.lg-feature-icon{ width:40px; height:40px; border-radius:10px; background:rgba(255,255,255,.07); border:1px solid rgba(255,255,255,.1);
  display:flex; align-items:center; justify-content:center; flex-shrink:0; font-size:1.05rem; }
.lg-feature-text b{ display:block; font-size:.86rem; font-weight:700; }
.lg-feature-text span{ font-size:.76rem; color:rgba(255,255,255,.5); }
.lg-side-footer{ font-size:.72rem; color:rgba(255,255,255,.35); position:relative; z-index:1; }

/* ---- Panel form ---- */
.lg-form-side{
  flex:1 1 50%; display:flex; background:var(--bg);
  padding:max(32px, env(safe-area-inset-top)) max(32px, env(safe-area-inset-right)) max(32px, env(safe-area-inset-bottom)) max(32px, env(safe-area-inset-left));
}
.lg-form-box{ width:100%; max-width:390px; margin:auto; animation:gvFadeUp .4s var(--ease) both; }
.lg-form-title{ font-family:'Sora',sans-serif; font-weight:800; font-size:1.5rem; color:var(--ink); margin-bottom:4px; }
.lg-form-hint{ color:var(--ink-soft); font-size:.86rem; margin-bottom:28px; line-height:1.55; }

.lg-input-group{ position:relative; margin-bottom:16px; }
.lg-input-group i.lg-ic{ position:absolute; left:14px; top:50%; transform:translateY(-50%); color:var(--ink-soft); font-size:1rem; pointer-events:none; }
.lg-input-group .form-control{ padding:11px 44px 11px 40px; border-radius:10px; font-size:.9rem; }
.lg-input-group .form-control:focus{ box-shadow:0 0 0 3px var(--brand-soft); border-color:var(--navy); }
.lg-toggle-pass{
  position:absolute; right:6px; top:50%; transform:translateY(-50%); border:none; background:transparent; color:var(--ink-soft);
  width:32px; height:32px; border-radius:8px; display:flex; align-items:center; justify-content:center; transition:background .15s, color .15s;
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
.lg-submit:disabled{ opacity:.75; transform:none; }

.lg-row{ display:flex; justify-content:space-between; align-items:center; gap:10px; margin:-4px 0 18px; font-size:.84rem; }
.lg-link{ color:var(--navy); font-weight:600; text-decoration:none; padding:6px 0; display:inline-block; }
.lg-link:hover{ text-decoration:underline; }

.lg-error, .lg-notice{
  display:flex; align-items:flex-start; gap:9px; border-radius:10px; padding:11px 14px; font-size:.85rem; font-weight:600; margin-bottom:18px; line-height:1.5;
  animation:gvFadeUp .3s var(--ease) both;
}
.lg-error{ background:var(--bad-soft); color:var(--bad); }
.lg-notice{ background:var(--ok-soft, #dcfce7); color:var(--ok, #15803d); }
.lg-error i, .lg-notice i{ margin-top:2px; }

.lg-state{ text-align:center; }
.lg-state-icon{ width:68px; height:68px; border-radius:50%; margin:0 auto 14px; display:flex; align-items:center; justify-content:center; font-size:2rem; }
.lg-state-icon.ok{ background:var(--ok-soft, #dcfce7); color:var(--ok, #15803d); }
.lg-state-icon.bad{ background:var(--bad-soft); color:var(--bad); }

.lg-meter{ height:6px; border-radius:6px; background:#e5e7eb; overflow:hidden; margin:-6px 0 6px; }
.lg-meter > span{ display:block; height:100%; width:0; border-radius:6px; transition:width .25s, background .25s; }
.lg-meter-label{ font-size:.76rem; color:var(--ink-soft); margin-bottom:16px; min-height:1.1em; }

.lg-mobile-brand{ display:none; }
.hp{ position:absolute; left:-9999px; }

/* ---- Laptop kecil ---- */
@media (max-width:1199px){
  .lg-side{ padding:44px 34px; }
  .lg-headline{ font-size:1.75rem; }
}

/* ---- Tablet & HP: panel kiri disembunyikan ---- */
@media (max-width:900px){
  .lg-side{ display:none; }
  .lg-form-side{ flex-basis:100%; }
  /* 16px agar iOS Safari tidak zoom otomatis; target sentuh lebih besar */
  .lg-input-group .form-control{ font-size:16px; min-height:48px; }
  .lg-toggle-pass{ width:40px; height:40px; right:4px; }
  .lg-submit{ min-height:48px; }
  .lg-mobile-brand{ display:flex; flex-direction:column; align-items:center; text-align:center; margin-bottom:24px; }
  .lg-mobile-brand .lg-logo-badge{ margin-bottom:10px; }
}
/* Tablet: form tampil sebagai kartu */
@media (min-width:577px) and (max-width:900px){
  .lg-form-box{ background:#fff; padding:34px 32px; border-radius:20px; box-shadow:var(--sh-md); max-width:460px; }
}
/* HP */
@media (max-width:576px){
  .lg-form-side{ padding-left:max(20px, env(safe-area-inset-left)); padding-right:max(20px, env(safe-area-inset-right)); }
  .lg-form-title{ font-size:1.35rem; }
}
/* HP landscape / layar pendek */
@media (max-height:520px){
  .lg-form-side{ padding-top:16px; padding-bottom:16px; }
  .lg-mobile-brand{ flex-direction:row; gap:12px; margin-bottom:14px; }
  .lg-mobile-brand .lg-logo-badge{ margin:0; width:40px; height:40px; font-size:1.15rem; }
  .lg-form-hint{ margin-bottom:16px; }
}
@media (prefers-reduced-motion:reduce){ *{ animation:none !important; transition:none !important; } }
</style>
<?php }

function authSide(): void { ?>
  <div class="lg-side">
    <div>
      <div class="lg-logo-badge"><i class="bi bi-mortarboard-fill"></i></div>
      <div class="lg-headline">Kelola sekolah lebih mudah, dari mana saja.</div>
      <div class="lg-sub">Absensi berbasis GPS, e-learning, dan rapor digital dalam satu sistem terpadu.</div>
    </div>
    <div>
      <div class="lg-feature">
        <div class="lg-feature-icon"><i class="bi bi-geo-alt-fill"></i></div>
        <div class="lg-feature-text"><b>Absensi GPS</b><span>Presensi masuk &amp; pulang tervalidasi lokasi</span></div>
      </div>
      <div class="lg-feature">
        <div class="lg-feature-icon"><i class="bi bi-journal-richtext"></i></div>
        <div class="lg-feature-text"><b>E-Learning</b><span>Materi &amp; tugas dalam satu tempat</span></div>
      </div>
      <div class="lg-feature">
        <div class="lg-feature-icon"><i class="bi bi-clipboard-data-fill"></i></div>
        <div class="lg-feature-text"><b>Rapor Digital</b><span>Nilai &amp; laporan otomatis tersusun rapi</span></div>
      </div>
    </div>
    <div class="lg-side-footer">&copy; <?= date('Y') ?> SIM Sekolah — Sistem Informasi Manajemen Absensi &amp; Akademik</div>
  </div>
<?php }

function authMobileBrand(): void { ?>
      <div class="lg-mobile-brand">
        <div class="lg-logo-badge"><i class="bi bi-mortarboard-fill"></i></div>
        <div class="fw-bold mt-2" style="font-family:'Sora',sans-serif;">SIM Sekolah</div>
      </div>
<?php }

function authScripts(): void { ?>
<script>
// Tombol tampil/sembunyi password
document.querySelectorAll('[data-toggle-pass]').forEach(function (b) {
  b.addEventListener('click', function () {
    var i = document.getElementById(b.dataset.togglePass), ic = b.querySelector('i');
    var show = i.type === 'password';
    i.type = show ? 'text' : 'password';
    ic.className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
  });
});
// Cegah klik ganda saat mengirim form
document.querySelectorAll('form[data-lock]').forEach(function (f) {
  f.addEventListener('submit', function () {
    var b = f.querySelector('button[type=submit]');
    if (b) { b.disabled = true; b.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Memproses...'; }
  });
});
// Tombol "Kembali" di browser: muat ulang agar tombol tidak macet
window.addEventListener('pageshow', function (e) { if (e.persisted) location.reload(); });
</script>
<?php }