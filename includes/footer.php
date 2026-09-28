</main>
</div>

<footer class="text-center text-muted small py-3 border-top bg-white d-none d-lg-block">
  &copy; <?= date('Y') ?> SIM Sekolah — Sistem Informasi Manajemen Absensi &amp; Akademik
</footer>

<style>
/* ---- Bottom navigation mobile: glass navy + emas ---- */
@keyframes gvNavUp{ from{ transform:translateY(100%); } to{ transform:translateY(0); } }
.bottom-nav{
  background:linear-gradient(180deg, rgba(19,26,46,.97), rgba(11,15,29,.99)) !important;
  backdrop-filter:blur(14px);
  border-top:1px solid rgba(255,255,255,.08) !important;
  box-shadow:0 -8px 28px -10px rgba(0,0,0,.4);
  animation:gvNavUp .3s cubic-bezier(.4,0,.2,1) both;
}
.bottom-nav a{
  color:rgba(255,255,255,.55) !important;
  transition:color .18s ease, transform .15s ease;
}
.bottom-nav a i{ transition:transform .18s ease; }
.bottom-nav a:active{ transform:scale(.93); }
.bottom-nav a.active{ color:#fff !important; position:relative; }
.bottom-nav a.active i{ transform:translateY(-1px); }
.bottom-nav a.active::before{
  content:""; position:absolute; top:-1px; left:50%; transform:translateX(-50%);
  width:22px; height:3px; border-radius:0 0 4px 4px; background:var(--gold,#F4B740);
}
.bottom-nav a.fab .fab-circle{
  background:linear-gradient(135deg, var(--gold,#F4B740), #D89A1F) !important;
  color:#131A2E !important;
  box-shadow:0 10px 22px -6px rgba(244,183,64,.55), 0 0 0 5px rgba(19,26,46,1) !important;
  transition:transform .18s cubic-bezier(.4,0,.2,1);
}
.bottom-nav a.fab:active .fab-circle{ transform:scale(.92); }
.bottom-nav a.fab span:last-child{ color:rgba(244,183,64,.9) !important; font-weight:700; }
</style>

<?php
// ==== Bottom navigation (mobile) — berbeda tiap role ====
$role = currentUser()['role'];
$curDir = basename(dirname($_SERVER['PHP_SELF']));
$curFile = basename($_SERVER['PHP_SELF']);
function bnActive($match, $curDir, $curFile) {
    return ($match === $curDir || $match === $curFile) ? 'active' : '';
}
?>
<nav class="bottom-nav">
  <a href="<?= BASE_URL ?>/index.php" class="<?= bnActive('index.php', $curDir, $curFile) ?>">
    <i class="bi bi-house-door-fill"></i>Beranda
  </a>

  <?php if ($role === 'siswa'): ?>
    <a href="<?= BASE_URL ?>/nilai/index.php" class="<?= bnActive('nilai', $curDir, $curFile) ?>">
      <i class="bi bi-clipboard-data-fill"></i>Nilai
    </a>
    <a href="<?= BASE_URL ?>/absensi/gps.php" class="fab">
      <span class="fab-circle"><i class="bi bi-geo-alt-fill"></i></span>
      <span>Absen</span>
    </a>
    <a href="<?= BASE_URL ?>/elearning/materi.php" class="<?= bnActive('elearning', $curDir, $curFile) ?>">
      <i class="bi bi-journal-richtext"></i>Belajar
    </a>
    <a href="<?= BASE_URL ?>/ujian/index.php" class="<?= bnActive('ujian', $curDir, $curFile) ?>">
      <i class="bi bi-pencil-square"></i>Ujian
    </a>

  <?php elseif ($role === 'wali_kelas'): ?>
    <a href="<?= BASE_URL ?>/absensi/monitor.php" class="<?= bnActive('monitor.php', $curDir, $curFile) ?>">
      <i class="bi bi-geo-alt-fill"></i>Absen GPS
    </a>
    <a href="<?= BASE_URL ?>/siswa/list.php" class="fab">
      <span class="fab-circle"><i class="bi bi-people-fill"></i></span>
      <span>Siswa</span>
    </a>
    <a href="<?= BASE_URL ?>/nilai/index.php" class="<?= bnActive('nilai', $curDir, $curFile) ?>">
      <i class="bi bi-clipboard-data-fill"></i>Nilai
    </a>
    <a href="<?= BASE_URL ?>/pengumuman/list.php" class="<?= bnActive('pengumuman', $curDir, $curFile) ?>">
      <i class="bi bi-megaphone-fill"></i>Info
    </a>

  <?php elseif (in_array($role, ['admin','guru'])): ?>
    <a href="<?= BASE_URL ?>/siswa/list.php" class="<?= bnActive('siswa', $curDir, $curFile) ?>">
      <i class="bi bi-people-fill"></i>Siswa
    </a>
    <a href="<?= BASE_URL ?>/absensi/index.php" class="fab">
      <span class="fab-circle"><i class="bi bi-calendar2-check-fill"></i></span>
      <span>Absen</span>
    </a>
    <a href="<?= BASE_URL ?>/elearning/materi.php" class="<?= bnActive('elearning', $curDir, $curFile) ?>">
      <i class="bi bi-journal-richtext"></i>Belajar
    </a>
    <a href="<?= BASE_URL ?>/ujian/index.php" class="<?= bnActive('ujian', $curDir, $curFile) ?>">
      <i class="bi bi-pencil-square"></i>Ujian
    </a>
  <?php endif; ?>
</nav>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php if (!empty($extraScripts)) echo $extraScripts; ?>
</body>
</html>