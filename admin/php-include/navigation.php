<?php
  $currentPage = basename($_SERVER['PHP_SELF']);
?>
<div class="sidebar">
  <h2>Admin Panel</h2>
  <ul>
    <li class="<?= ($currentPage == 'index.php') ? 'active' : '' ?>">
      <a href="index.php"><i class="fa fa-home"></i> Dashboard</a>
    </li>
    <li>
      <a href="../dashboard.php"><i class="fa fa-user-circle"></i> User Dashboard</a>
    </li>
    <li class="<?= ($currentPage == 'user_driven.php') ? 'active' : '' ?>">
      <a href="user_driven.php"><i class="fa fa-users"></i> Manage Users</a>
    </li>
    <li class="<?= ($currentPage == 'major_tracks_driven.php') ? 'active' : '' ?>">
      <a href="major_tracks_driven.php"><i class="fa fa-graduation-cap"></i> Majors & Tracks</a>
    </li>
    <li class="<?= ($currentPage == 'manage_categories.php') ? 'active' : '' ?>">
      <a href="manage_categories.php"><i class="fa fa-tags"></i> Manage Categories</a>
    </li>
    <li class="<?= ($currentPage == 'assessments_driven.php') ? 'active' : '' ?>">
      <a href="assessments_driven.php"><i class="fa fa-file-alt"></i> Manage Assessment</a>
    </li>
    <li class="<?= ($currentPage == 'stats_driven.php') ? 'active' : '' ?>">
      <a href="stats_driven.php"><i class="fa fa-chart-line"></i> Assessment Stats</a>
    </li>
    <li>
      <a href="logout.php"><i class="fa fa-sign-out-alt"></i> Logout</a>
    </li>
  </ul>
</div>
<script type="text/javascript">
  document.addEventListener("DOMContentLoaded", function () {
    const current = window.location.pathname.split("/").pop();
    document.querySelectorAll(".sidebar ul li a").forEach(link => {
      if (link.getAttribute("href") === current) {
        link.parentElement.classList.add("active");
      }
    });
  });
</script>