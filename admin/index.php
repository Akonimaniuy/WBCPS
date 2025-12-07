<?php
  session_start();

  include_once("lib/config.php");

  $conn = new mysqli($host, $user, $pass, $db);
  if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
  }

  // Check for user ID and admin role
  if(!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin'){
    header("Location: ../index.php"); // Redirect non-admins to the main site
    exit();
  }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Dashboard</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" type="text/css" href="css/style.css">
</head>
<style type="text/css">
  .user-page button {
    background: rgb(36 49 66)!important;
  }
</style>
<body>
  <!-- Sidebar -->
  <?php include "php-include/navigation.php"; ?>

  <!-- Main -->
  <div class="main">
    <?php include "php-include/topbar.php"; ?>

    <!-- Cards -->
    <div class="main-content">
      <div class="cards">
        <div class="card">
          <div class="info">
            <h3>Total Users</h3>
            <p>1,234</p>
          </div>
          <i class="fa fa-users icon-users"></i>
        </div>
        <div class="card">
          <div class="info">
            <h3>Assessments Taken</h3>
            <p>876</p>
          </div>
          <i class="fa fa-chart-bar icon-assess"></i>
        </div>
        <div class="card">
          <div class="info">
            <h3>New Registrations Today</h3>
            <p>42</p>
          </div>
          <i class="fa fa-user-plus icon-new"></i>
        </div>
      </div>
      <!-- Content -->
      <div class="content">
        <div class="user-table-container">
          <div class="user-cont">
            <h2>Registered Users</h2>
            <input type="text" id="searchInput" class="user-search" placeholder="Search users...">
          </div>
          <?php
            $sql = "SELECT id, name, email FROM users ORDER BY id ASC";
            $result = $conn->query($sql);

            if ($result && $result->num_rows > 0) {
              echo "<table id='usersTable'>";
                echo "<thead>
                  <tr>
                    <th onclick='sortTable(0)'>ID ⬍</th>
                    <th onclick='sortTable(1)'>Name ⬍</th>
                    <th onclick='sortTable(2)'>Email ⬍</th>
                  </tr>
                </thead>
                <tbody>";

                  while ($row = $result->fetch_assoc()) {
                    echo "<tr>
                      <td>".$row['id']."</td>
                      <td>".$row['name']."</td>
                      <td>".$row['email']."</td>
                    </tr>";
                  }

                echo "</tbody>
              </table>";
              echo "<div id='pagination' class='user-page'></div>";
            } else {
              echo "<p>No users found.</p>";
            }
          ?>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
<script type="text/javascript">
  const rowsPerPage = 5; 
  let currentPage = 1;

  function paginateTable() {
    const table = document.getElementById("usersTable");
    const rows = table.getElementsByTagName("tbody")[0].getElementsByTagName("tr");
    const totalRows = rows.length;
    const totalPages = Math.ceil(totalRows / rowsPerPage);

    for (let i = 0; i < totalRows; i++) rows[i].style.display = "none";

    const start = (currentPage - 1) * rowsPerPage;
    const end = start + rowsPerPage;
    for (let i = start; i < end && i < totalRows; i++) rows[i].style.display = "";

    const pagination = document.getElementById("pagination");
    pagination.innerHTML = "";

    for (let i = 1; i <= totalPages; i++) {
      const btn = document.createElement("button");
      btn.innerText = i;
      btn.style.margin = "0 5px";
      btn.style.padding = "6px 12px";
      btn.style.border = "1px solid #ccc";
      btn.style.borderRadius = "6px";
      btn.style.cursor = "pointer";
      btn.style.background = (i === currentPage) ? "#4CAF50" : "#fff";
      btn.style.color = (i === currentPage) ? "#fff" : "#333";
      btn.onclick = function() { currentPage = i; paginateTable(); };
      pagination.appendChild(btn);
    }
  }

  function searchTable() {
    const input = document.getElementById("searchInput").value.toLowerCase();
    const rows = document.getElementById("usersTable").getElementsByTagName("tbody")[0].getElementsByTagName("tr");

    for (let i = 0; i < rows.length; i++) {
      const cells = rows[i].getElementsByTagName("td");
      let match = false;
      for (let j = 0; j < cells.length; j++) {
        if (cells[j].innerText.toLowerCase().includes(input)) { match = true; break; }
      }
      rows[i].style.display = match ? "" : "none";
    }
  }

  function sortTable(colIndex) {
    const table = document.getElementById("usersTable");
    let rows = Array.from(table.getElementsByTagName("tbody")[0].rows);
    let asc = table.getAttribute("data-sort") !== colIndex.toString();

    rows.sort((a, b) => {
      let x = a.cells[colIndex].innerText.toLowerCase();
      let y = b.cells[colIndex].innerText.toLowerCase();
      return asc ? (x > y ? 1 : -1) : (x < y ? 1 : -1);
    });

    rows.forEach(row => table.getElementsByTagName("tbody")[0].appendChild(row));
    table.setAttribute("data-sort", asc ? colIndex : "");
  }

  document.getElementById("searchInput").addEventListener("keyup", searchTable);
  paginateTable();
</script>
