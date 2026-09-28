<?php
$role = currentUser()['role'];
$currentPage = basename($_SERVER['PHP_SELF']);
$currentDir  = basename(dirname($_SERVER['PHP_SELF']));

function navActive($dirOrFile, $currentDir, $currentPage) {
    return ($dirOrFile === $currentPage || $dirOrFile === $currentDir) ? 'active' : '';
}
?>
<aside class="sidebar" id="sidebar">
  <div class="p-3">
    <ul class="nav nav-pills flex-column gap-1">

      <li class="nav-item">
        <a class="nav-link <?= navActive('index.php', $currentDir, $currentPage) ?>" href="<?= BASE_URL ?>/index.php">
          <i class="bi bi-speedometer2"></i>Dashboard
        </a>
      </li>

      <?php if (in_array($role, ['admin','guru'])): ?>
      <li class="nav-item">
        <a class="nav-link <?= navActive('siswa', $currentDir, $currentPage) ?>" href="<?= BASE_URL ?>/siswa/list.php">
          <i class="bi bi-people-fill"></i>Data Siswa
        </a>
      </li>
      <?php elseif ($role === 'wali_kelas'): ?>
      <li class="nav-item">
        <a class="nav-link <?= navActive('siswa', $currentDir, $currentPage) ?>" href="<?= BASE_URL ?>/siswa/list.php">
          <i class="bi bi-people-fill"></i>Siswa Kelas Saya
        </a>
      </li>
      <?php endif; ?>

      <?php if ($role === 'admin'): ?>
      <li class="nav-item">
        <a class="nav-link <?= navActive('kelas', $currentDir, $currentPage) ?>" href="<?= BASE_URL ?>/kelas/list.php">
          <i class="bi bi-door-open-fill"></i>Data Kelas
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= navActive('mapel', $currentDir, $currentPage) ?>" href="<?= BASE_URL ?>/mapel/list.php">
          <i class="bi bi-journal-bookmark-fill"></i>Mata Pelajaran
        </a>
      </li>
      <?php endif; ?>

      <li class="nav-section-label">ABSENSI</li>

      <?php if ($role === 'siswa'): ?>
        <li class="nav-item">
          <a class="nav-link gps-link <?= navActive('gps.php', $currentDir, $currentPage) ?>" href="<?= BASE_URL ?>/absensi/gps.php">
            <i class="bi bi-geo-alt-fill"></i>Absen GPS
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= ($currentDir==='absensi' && $currentPage==='index.php') ? 'active' : '' ?>" href="<?= BASE_URL ?>/absensi/index.php">
            <i class="bi bi-clock-history"></i>Riwayat Absensi
          </a>
        </li>
      <?php endif; ?>

      <?php if (in_array($role, ['admin','guru'])): ?>
        <li class="nav-item">
          <a class="nav-link <?= ($currentDir==='absensi' && $currentPage==='index.php') ? 'active' : '' ?>" href="<?= BASE_URL ?>/absensi/index.php">
            <i class="bi bi-pencil-square"></i>Input Manual
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= navActive('rekap.php', $currentDir, $currentPage) ?>" href="<?= BASE_URL ?>/absensi/rekap.php">
            <i class="bi bi-bar-chart-fill"></i>Rekap Bulanan
          </a>
        </li>
      <?php endif; ?>

      <?php if (in_array($role, ['admin','guru','wali_kelas'])): ?>
        <li class="nav-item">
          <a class="nav-link gps-link <?= navActive('monitor.php', $currentDir, $currentPage) ?>" href="<?= BASE_URL ?>/absensi/monitor.php">
            <i class="bi bi-geo-alt-fill"></i>Monitor Absen GPS
          </a>
        </li>
      <?php endif; ?>

      <?php if ($role === 'admin'): ?>
        <li class="nav-item">
          <a class="nav-link gps-link <?= navActive('lokasi', $currentDir, $currentPage) ?>" href="<?= BASE_URL ?>/lokasi/index.php">
            <i class="bi bi-crosshair"></i>Lokasi &amp; Radius GPS
          </a>
        </li>
      <?php endif; ?>

      <li class="nav-section-label">AKADEMIK</li>

      <li class="nav-item">
        <a class="nav-link <?= navActive('nilai', $currentDir, $currentPage) ?>" href="<?= BASE_URL ?>/nilai/index.php">
          <i class="bi bi-clipboard-data-fill"></i>Nilai / Rapor
        </a>
      </li>

      <li class="nav-item">
        <a class="nav-link <?= navActive('materi.php', $currentDir, $currentPage) ?>" href="<?= BASE_URL ?>/elearning/materi.php">
          <i class="bi bi-journal-richtext"></i>Materi Belajar
        </a>
      </li>

      <li class="nav-item">
        <a class="nav-link <?= navActive('tugas.php', $currentDir, $currentPage) ?>" href="<?= BASE_URL ?>/elearning/tugas.php">
          <i class="bi bi-card-checklist"></i>Tugas
        </a>
      </li>

      <?php if (in_array($role, ['admin','guru','siswa'])): ?>
      <li class="nav-item">
        <a class="nav-link <?= navActive('ujian', $currentDir, $currentPage) ?>" href="<?= BASE_URL ?>/ujian/index.php">
          <i class="bi bi-pencil-square"></i>Ujian UTS / UAS
        </a>
      </li>
      <?php endif; ?>

      <li class="nav-item">
        <a class="nav-link <?= navActive('pengumuman', $currentDir, $currentPage) ?>" href="<?= BASE_URL ?>/pengumuman/list.php">
          <i class="bi bi-megaphone-fill"></i>Pengumuman
        </a>
      </li>

      <?php if ($role === 'admin'): ?>
      <li class="nav-section-label">ADMINISTRASI</li>
      <li class="nav-item">
        <a class="nav-link <?= navActive('users', $currentDir, $currentPage) ?>" href="<?= BASE_URL ?>/users/list.php">
          <i class="bi bi-person-gear"></i>Kelola Akun
        </a>
      </li>
      <?php endif; ?>

    </ul>
  </div>
</aside>