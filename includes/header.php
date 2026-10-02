<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($pageTitle) ? clean($pageTitle) . ' - ' : '' ?>SIM Sekolah</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700;800&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
<?php include __DIR__ . '/theme_gov.php'; ?>
<style>
/* ---- Topbar modern (khusus layout header) ---- */
.gv-topbar{
  background:linear-gradient(120deg, rgba(19,26,46,.98), rgba(11,15,29,.98));
  backdrop-filter:blur(10px);
  box-shadow:0 1px 0 rgba(255,255,255,.05), 0 6px 20px -8px rgba(0,0,0,.35);
  border-bottom:1px solid rgba(255,255,255,.06);
  padding:.55rem 0;
}
.gv-toggle-btn{
  width:38px; height:38px; border-radius:10px; border:1px solid rgba(255,255,255,.12);
  background:rgba(255,255,255,.05); color:#fff; display:flex; align-items:center; justify-content:center;
  font-size:1.35rem; padding:0; flex-shrink:0;
  transition:background .18s ease, transform .15s ease;
}
.gv-toggle-btn:hover{ background:rgba(255,255,255,.12); }
.gv-toggle-btn:active{ transform:scale(.94); }
.gv-toggle-btn:focus-visible{ outline:2px solid var(--gold,#F4B740); outline-offset:2px; }

.gv-brand{ display:flex; align-items:center; gap:10px; text-decoration:none; }
.gv-brand-badge{
  width:36px; height:36px; border-radius:10px;
  background:linear-gradient(135deg, var(--gold), #D89A1F);
  display:flex; align-items:center; justify-content:center; color:var(--navy-2);
  font-size:1.05rem; box-shadow:0 4px 12px -4px rgba(244,183,64,.55);
  flex-shrink:0;
}
.gv-brand-text{ font-family:'Sora',sans-serif; font-weight:800; color:#fff; font-size:1.02rem; letter-spacing:-.01em; line-height:1.1; }
.gv-brand-text small{ display:block; font-family:'Inter',sans-serif; font-size:.62rem; font-weight:600; letter-spacing:.09em; text-transform:uppercase; color:rgba(255,255,255,.45); }

/* ---- Chip identitas user (tampilan saja) ---- */
.gv-user-chip{
  display:flex; align-items:center; gap:9px; background:rgba(255,255,255,.05);
  border:1px solid rgba(255,255,255,.08); border-radius:30px; padding:5px 8px 5px 5px;
}
.gv-user-avatar{
  width:30px; height:30px; border-radius:50%; background:linear-gradient(135deg,var(--gold),#D89A1F);
  color:var(--navy-2); font-weight:800; font-size:.8rem; display:flex; align-items:center; justify-content:center;
  flex-shrink:0; overflow:hidden;
}
.gv-user-avatar img{ width:100%; height:100%; object-fit:cover; }
.gv-user-meta{ line-height:1.15; }
.gv-user-meta .name{ color:#fff; font-size:.8rem; font-weight:600; }
.gv-user-meta .role{ color:rgba(255,255,255,.5); font-size:.66rem; text-transform:uppercase; letter-spacing:.05em; font-weight:700; }

/* ---- Tombol tiga titik + dropdown menu ---- */
.gv-more-btn{
  width:36px; height:36px; border-radius:10px; border:1px solid rgba(255,255,255,.12);
  background:rgba(255,255,255,.05); color:#fff; display:flex; align-items:center; justify-content:center;
  transition:background .18s ease, transform .15s ease;
}
.gv-more-btn:hover, .gv-more-btn.show{ background:rgba(255,255,255,.14); color:#fff; }
.gv-more-btn:active{ transform:scale(.94); }

.gv-dropdown-menu{
  background:var(--navy); border:1px solid rgba(255,255,255,.1); border-radius:12px;
  box-shadow:0 16px 40px -10px rgba(0,0,0,.5); padding:8px; min-width:230px;
  position:absolute; right:0; top:calc(100% + 10px); z-index:3000;
  display:none;
}
.gv-dropdown-menu.show{ display:block; }
.gv-more-wrap{ position:relative; }
.gv-dropdown-header{
  display:flex; align-items:center; gap:10px; padding:8px 10px 12px; border-bottom:1px solid rgba(255,255,255,.08); margin-bottom:6px;
}
.gv-dropdown-header .gv-user-avatar{ width:36px; height:36px; font-size:.9rem; }
.gv-dropdown-header .name{ color:#fff; font-size:.85rem; font-weight:700; }
.gv-dropdown-header .role{ color:rgba(255,255,255,.45); font-size:.68rem; text-transform:uppercase; letter-spacing:.05em; font-weight:700; }
.gv-dropdown-menu .dropdown-item{
  color:rgba(255,255,255,.82); font-size:.85rem; font-weight:500; border-radius:8px; padding:9px 10px;
  display:flex; align-items:center; gap:10px; transition:background .15s ease, color .15s ease;
}
.gv-dropdown-menu .dropdown-item i{ font-size:1rem; width:18px; text-align:center; color:rgba(255,255,255,.5); }
.gv-dropdown-menu .dropdown-item:hover, .gv-dropdown-menu .dropdown-item:focus{ background:rgba(255,255,255,.08); color:#fff; }
.gv-dropdown-menu .dropdown-item:hover i{ color:var(--gold); }
.gv-dropdown-menu .dropdown-item.text-danger{ color:#FCA5A5 !important; }
.gv-dropdown-menu .dropdown-item.text-danger i{ color:#FCA5A5; }
.gv-dropdown-menu .dropdown-item.text-danger:hover{ background:rgba(220,38,38,.16); }
.gv-dropdown-menu .dropdown-divider{ border-color:rgba(255,255,255,.08); margin:6px 4px; }

@media (max-width:575px){ .gv-user-meta{ display:none; } .gv-user-chip{ padding:5px; } }

/* ---- Sidebar versi mobile / tablet (di bawah 992px): menu hamburger ----
   Di layar lebar sidebar tetap tampil seperti biasa. Di layar kecil sidebar
   disembunyikan di sisi kiri dan digeser masuk saat tombol hamburger ditekan.
   Aturan memakai !important supaya menang atas style sidebar di style.css / theme_gov.php. */
.gv-sidebar-head{ display:none; }
.gv-sidebar-overlay{ display:none; }

@media (max-width: 991.98px){
  .sidebar{
    display:block !important;
    position:fixed !important;
    top:0 !important; left:0 !important; bottom:0 !important;
    height:100% !important;
    width:280px !important; max-width:86vw !important;
    margin:0 !important;
    z-index:2050 !important;
    transform:translateX(-105%) !important;
    transition:transform .25s ease !important;
    overflow-y:auto !important;
    -webkit-overflow-scrolling:touch;
  }
  .sidebar.gv-open{
    transform:translateX(0) !important;
    box-shadow:0 0 44px rgba(0,0,0,.5);
  }

  /* Kepala sidebar (judul + tombol tutup) — disuntikkan lewat JS di bawah */
  .gv-sidebar-head{
    display:flex; align-items:center; justify-content:space-between;
    padding:14px 16px; position:sticky; top:0; z-index:1;
    background:var(--navy,#131A2E); border-bottom:1px solid rgba(255,255,255,.08);
  }
  .gv-sidebar-title{ color:#fff; font-family:'Sora',sans-serif; font-weight:800; font-size:.95rem; display:flex; align-items:center; gap:10px; }
  .gv-sidebar-title .gv-brand-badge{ width:30px; height:30px; font-size:.9rem; border-radius:8px; }
  .gv-sidebar-close{
    width:34px; height:34px; border-radius:9px; border:1px solid rgba(255,255,255,.14);
    background:rgba(255,255,255,.06); color:#fff; display:flex; align-items:center; justify-content:center; padding:0;
  }
  .gv-sidebar-close:hover{ background:rgba(255,255,255,.14); }

  /* Latar gelap di belakang sidebar; ketuk untuk menutup */
  .gv-sidebar-overlay{
    display:block; position:fixed; top:0; left:0; right:0; bottom:0;
    background:rgba(8,12,24,.55); z-index:2040;
    opacity:0; pointer-events:none; transition:opacity .25s ease;
  }
  .gv-sidebar-overlay.show{ opacity:1; pointer-events:auto; }

  body.gv-sidebar-open{ overflow:hidden; }

  /* Konten memakai seluruh lebar layar karena sidebar sudah melayang */
  .content-area{ margin-left:0 !important; width:100%; min-width:0; }
}
</style>
</head>
<body>
<?php
$roleNow = currentUser()['role'];
$roleLabel = ['admin'=>'Admin','guru'=>'Guru','wali_kelas'=>'Wali Kelas','siswa'=>'Siswa'][$roleNow] ?? $roleNow;
$namaNow = currentUser()['nama'];
$inisial = strtoupper(mb_substr($namaNow ?: '?', 0, 1));

// Kalau siswa punya foto profil, tampilkan di avatar topbar (bukan cuma inisial)
$fotoTopbar = null;
if ($roleNow === 'siswa' && !empty(currentUser()['siswa_id'])) {
    $fotoPath = __DIR__ . '/../uploads/foto_siswa/';
    $stmtFotoNow = $pdo->prepare("SELECT foto FROM siswa WHERE id = ?");
    $stmtFotoNow->execute([currentUser()['siswa_id']]);
    $fotoNow = $stmtFotoNow->fetchColumn();
    if ($fotoNow && is_file($fotoPath . $fotoNow)) {
        $fotoTopbar = BASE_URL . '/uploads/foto_siswa/' . $fotoNow;
    }
}

// Menu akses cepat di dropdown, disesuaikan per role — hanya link ke halaman yang benar-benar ada
$quickLinks = [];
if ($roleNow === 'siswa') {
    $quickLinks[] = ['url' => '/absensi/gps.php', 'icon' => 'bi-geo-alt-fill', 'label' => 'Absen GPS'];
    $quickLinks[] = ['url' => '/nilai/index.php', 'icon' => 'bi-clipboard-data-fill', 'label' => 'Nilai Saya'];
    $quickLinks[] = ['url' => '/ujian/index.php', 'icon' => 'bi-pencil-square', 'label' => 'Ujian UTS / UAS'];
} elseif ($roleNow === 'wali_kelas') {
    $quickLinks[] = ['url' => '/absensi/monitor.php', 'icon' => 'bi-geo-alt-fill', 'label' => 'Monitor Absen GPS'];
    $quickLinks[] = ['url' => '/siswa/list.php', 'icon' => 'bi-people-fill', 'label' => 'Siswa Kelas Saya'];
} elseif (in_array($roleNow, ['admin','guru'])) {
    $quickLinks[] = ['url' => '/ujian/index.php', 'icon' => 'bi-pencil-square', 'label' => 'Ujian UTS / UAS'];
    $quickLinks[] = ['url' => '/nilai/index.php', 'icon' => 'bi-clipboard-data-fill', 'label' => 'Nilai / Rapor'];
    $quickLinks[] = ['url' => '/pengumuman/list.php', 'icon' => 'bi-megaphone-fill', 'label' => 'Pengumuman'];
    if ($roleNow === 'admin') {
        $quickLinks[] = ['url' => '/users/list.php', 'icon' => 'bi-person-gear', 'label' => 'Kelola Akun'];
    }
}
?>
<nav class="navbar navbar-expand-lg gv-topbar sticky-top">
  <div class="container-fluid">
    <div class="d-flex align-items-center gap-2">
      <!-- Tombol hamburger: hanya tampil di layar kecil (di bawah 992px) -->
      <button class="gv-toggle-btn d-lg-none" type="button" id="btnSidebarToggle"
              aria-controls="sidebar" aria-expanded="false" aria-label="Buka menu navigasi">
        <i class="bi bi-list"></i>
      </button>

      <a class="gv-brand" href="<?= BASE_URL ?>/index.php">
        <span class="gv-brand-badge"><i class="bi bi-mortarboard-fill"></i></span>
        <span class="gv-brand-text d-none d-sm-block">
          SIM Sekolah
          <small>Absensi &amp; Akademik</small>
        </span>
      </a>
    </div>

    <div class="ms-auto d-flex align-items-center gap-2">
      <div class="gv-user-chip">
        <div class="gv-user-avatar">
          <?php if ($fotoTopbar): ?>
            <img src="<?= clean($fotoTopbar) ?>" alt="Foto profil">
          <?php else: ?>
            <?= clean($inisial) ?>
          <?php endif; ?>
        </div>
        <div class="gv-user-meta d-none d-sm-block">
          <div class="name"><?= clean($namaNow) ?></div>
          <div class="role"><?= clean($roleLabel) ?></div>
        </div>
      </div>

      <div class="gv-more-wrap">
        <button class="gv-more-btn" type="button" id="btnUserMenu" aria-expanded="false" title="Menu akun">
          <i class="bi bi-three-dots-vertical"></i>
        </button>
        <ul class="dropdown-menu gv-dropdown-menu" id="userDropdownMenu" aria-labelledby="btnUserMenu">
          <li>
            <div class="gv-dropdown-header">
              <div class="gv-user-avatar">
                <?php if ($fotoTopbar): ?>
                  <img src="<?= clean($fotoTopbar) ?>" alt="Foto profil">
                <?php else: ?>
                  <?= clean($inisial) ?>
                <?php endif; ?>
              </div>
              <div>
                <div class="name"><?= clean($namaNow) ?></div>
                <div class="role"><?= clean($roleLabel) ?></div>
              </div>
            </div>
          </li>
          <?php foreach ($quickLinks as $ql): ?>
          <li><a class="dropdown-item" href="<?= BASE_URL . $ql['url'] ?>"><i class="bi <?= $ql['icon'] ?>"></i> <?= clean($ql['label']) ?></a></li>
          <?php endforeach; ?>
          <?php if (!empty($quickLinks)): ?><li><hr class="dropdown-divider"></li><?php endif; ?>
          <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>/logout.php"><i class="bi bi-box-arrow-right"></i> Keluar</a></li>
        </ul>
      </div>
    </div>
  </div>
</nav>

<script>
// Dropdown menu akun — vanilla JS, TIDAK bergantung pada Bootstrap JS.
// Dipasang di sini (bukan menunggu Bootstrap dari CDN) supaya tetap berfungsi
// meskipun CDN diblokir oleh jaringan/firewall.
(function () {
  var btn  = document.getElementById('btnUserMenu');
  var menu = document.getElementById('userDropdownMenu');
  if (!btn || !menu) return;

  function closeMenu() {
    menu.classList.remove('show');
    btn.setAttribute('aria-expanded', 'false');
  }
  function toggleMenu(e) {
    e.stopPropagation();
    var willOpen = !menu.classList.contains('show');
    menu.classList.toggle('show', willOpen);
    btn.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
  }

  btn.addEventListener('click', toggleMenu);
  document.addEventListener('click', function (e) {
    if (!menu.contains(e.target) && e.target !== btn) closeMenu();
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeMenu();
  });
})();
</script>

<!-- Latar gelap di belakang sidebar saat dibuka di layar kecil -->
<div class="gv-sidebar-overlay" id="gvSidebarOverlay"></div>

<div class="d-flex">
<?php include __DIR__ . '/sidebar.php'; ?>

<script>
// Menu hamburger (layar kecil) — vanilla JS, tidak bergantung pada Bootstrap JS.
// Dipasang SETELAH sidebar di-render supaya elemen #sidebar sudah ada.
(function () {
  var sidebar = document.getElementById('sidebar');
  var btn     = document.getElementById('btnSidebarToggle');
  var overlay = document.getElementById('gvSidebarOverlay');
  if (!sidebar || !btn || !overlay) return;

  // Kepala sidebar dengan tombol tutup (tampil hanya di layar kecil lewat CSS)
  var head = document.createElement('div');
  head.className = 'gv-sidebar-head';
  head.innerHTML =
    '<span class="gv-sidebar-title"><span class="gv-brand-badge"><i class="bi bi-mortarboard-fill"></i></span>Menu</span>' +
    '<button type="button" class="gv-sidebar-close" aria-label="Tutup menu"><i class="bi bi-x-lg"></i></button>';
  sidebar.insertBefore(head, sidebar.firstChild);

  function buka() {
    sidebar.classList.add('gv-open');
    overlay.classList.add('show');
    document.body.classList.add('gv-sidebar-open');
    btn.setAttribute('aria-expanded', 'true');
  }
  function tutup() {
    sidebar.classList.remove('gv-open');
    overlay.classList.remove('show');
    document.body.classList.remove('gv-sidebar-open');
    btn.setAttribute('aria-expanded', 'false');
  }

  btn.addEventListener('click', function () {
    if (sidebar.classList.contains('gv-open')) tutup(); else buka();
  });
  overlay.addEventListener('click', tutup);
  head.querySelector('.gv-sidebar-close').addEventListener('click', tutup);

  // Menutup otomatis setelah memilih salah satu menu
  sidebar.addEventListener('click', function (e) {
    if (e.target.closest && e.target.closest('a.nav-link')) tutup();
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') tutup();
  });
  // Kalau layar diperlebar sampai mode desktop, pastikan keadaan terbuka-tertutupnya bersih
  window.addEventListener('resize', function () {
    if (window.innerWidth >= 992) tutup();
  });
})();
</script>

<main class="flex-grow-1 p-3 p-md-4 content-area">
<?php showFlash(); ?>