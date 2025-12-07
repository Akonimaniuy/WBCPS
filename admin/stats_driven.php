<?php
session_start();
include_once("lib/config.php");
  
// Check for user ID and admin role
if(!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin'){
  header("Location: ../index.php"); // Redirect non-admins to the main site
  exit();
}

// Fetch stats
$totalUsers = $conn->query("SELECT COUNT(*) AS total FROM users")->fetch_assoc()['total'];
$totalAssessments = $conn->query("SELECT COUNT(*) AS total FROM user_assessments")->fetch_assoc()['total'];
$avgScore = $conn->query("SELECT AVG(score) AS avg_score FROM user_assessments")->fetch_assoc()['avg_score'];

// Grouped by Major
$majorStats = $conn->query("
  SELECT c.name as category_name, m.major, COUNT(ua.id) AS taken, AVG(ua.score) AS avg_score
  FROM majors m
  JOIN categories c ON m.category_id = c.id
  LEFT JOIN user_assessments ua ON m.id = ua.major_id
  GROUP BY m.id
  ORDER BY c.name, m.major
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Dashboard - Assessment Stats</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" type="text/css" href="css/style.css">
<style>
.stats-cards {
  display: flex;
  gap: 20px;
  margin-bottom: 20px;
}
.stats-card {
  flex: 1;
  background: #fff;
  padding: 20px;
  border-radius: 12px;
  box-shadow: 0 4px 10px rgba(0,0,0,0.1);
  text-align: center;
}
.stats-card h3 {
  font-size: 18px;
  color: #555;
}
.stats-card p {
  font-size: 22px;
  font-weight: bold;
  color: #2a3442;
}
.table-container {
  background: #fff;
  padding: 20px;
  border-radius: 12px;
  box-shadow: 0 4px 10px rgba(0,0,0,0.1);
}
.table-container table {
  width: 100%;
  border-collapse: collapse;
}
.table-container th, .table-container td {
  padding: 12px;
  border-bottom: 1px solid #eee;
  text-align: center;
}
.table-container th {
  background: #2a3442;
  color: white;
}
</style>
</head>
<body>
  <!-- Sidebar -->
  <?php include "php-include/navigation.php"; ?>

  <!-- Main -->
  <div class="main">
    <?php include "php-include/topbar.php"; ?>

    <div class="main-content">
      <div class="content">

        <!-- Stats Cards -->
        <div class="stats-cards">
          <div class="stats-card">
            <h3>Total Users</h3>
            <p><?= $totalUsers ?></p>
          </div>
          <div class="stats-card">
            <h3>Total Assessments Taken</h3>
            <p><?= $totalAssessments ?></p>
          </div>
          <div class="stats-card">
            <h3>Average Score</h3>
            <p><?= $avgScore ? round($avgScore, 2) : 0 ?></p>
          </div>
        </div>

        <!-- Major/Track Stats -->
        <div class="table-container">
          <h3>Assessment Stats by Major/Track</h3>
          <table>
            <thead>
              <tr>
                <th>Category/Track</th>
                <th>Major</th>
                <th>Assessments Taken</th>
                <th>Average Score</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($majorStats->num_rows > 0) {
                while ($row = $majorStats->fetch_assoc()) { ?>
                  <tr>
                    <td><?= htmlspecialchars($row['category_name']) ?></td>
                    <td><?= htmlspecialchars($row['major']) ?></td>
                    <td><?= $row['taken'] ?></td>
                    <td><?= $row['avg_score'] ? round($row['avg_score'], 2) : 0 ?></td>
                  </tr>
              <?php } } else { ?>
                  <tr><td colspan="4">No assessment data found.</td></tr>
              <?php } ?>
            </tbody>
          </table>
        </div>

      </div>
    </div>
  </div>
</body>
</html>
