<?php
session_start();
include_once("lib/config.php");

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
  die("Database connection failed: " . $conn->connect_error);
}

if(!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin'){
  header("Location: ../index.php");
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
<style>
  .user-page button {
    background: rgb(36 49 66)!important;
    color: #fff;
    margin: 0 3px;
    border: none;
    border-radius: 4px;
    padding: 6px 12px;
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
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>";
                  while ($row = $result->fetch_assoc()) {
                    echo "<tr>
                      <td>".$row['id']."</td>
                      <td>".$row['name']."</td>
                      <td>".$row['email']."</td>
                      <td>
                        <button class='bots' onclick='openEditModal(".$row['id'].", \"".$row['name']."\", \"".$row['email']."\")'> Edit </button>
                        <button class='bots1' onclick='deleteUser(".$row['id'].")'> Delete </button>
                      </td>
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
  <!-- Edit Modal -->
  <div id="editModal" class="modal">
    <div class="modal-content">
      <span class="close" onclick="closeEditModal()">&times;</span>
      <h3 style="margin-bottom: 15px;">Edit User</h3>
      <form id="editForm" method="POST" action="php-include/update_user.php">
        <input type="hidden" name="id" id="editUserId">
        <div class="form-group">
          <label for="editUserName">Name</label>
          <input type="text" name="name" id="editUserName" required>
        </div>
        <div class="form-group">
          <label for="editUserEmail">Email</label>
          <input type="email" name="email" id="editUserEmail" required>
        </div>
        <button type="submit" class="save-btn">💾 Save Changes</button>
      </form>
    </div>
  </div>

  <script>
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

      // Previous button
      if (currentPage > 1) {
        const prevBtn = document.createElement("button");
        prevBtn.innerText = "Prev";
        prevBtn.onclick = function() { currentPage--; paginateTable(); };
        pagination.appendChild(prevBtn);
      }

      // Show only 5 page numbers
      let startPage = Math.max(1, currentPage - 2);
      let endPage = Math.min(totalPages, startPage + 4);
      if (endPage - startPage < 4) startPage = Math.max(1, endPage - 4);

      for (let i = startPage; i <= endPage; i++) {
        const btn = document.createElement("button");
        btn.innerText = i;
        btn.style.background = (i === currentPage) ? "#4CAF50" : "#243142";
        btn.onclick = function() { currentPage = i; paginateTable(); };
        pagination.appendChild(btn);
      }

      // Next button
      if (currentPage < totalPages) {
        const nextBtn = document.createElement("button");
        nextBtn.innerText = "Next";
        nextBtn.onclick = function() { currentPage++; paginateTable(); };
        pagination.appendChild(nextBtn);
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

    function openEditModal(id, name, email) {
      document.getElementById("editUserId").value = id;
      document.getElementById("editUserName").value = name;
      document.getElementById("editUserEmail").value = email;
      document.getElementById("editModal").style.display = "block";
    }

    function closeEditModal() {
      document.getElementById("editModal").style.display = "none";
    }

    function deleteUser(id) {
      if (confirm("Are you sure you want to delete this user?")) {
        window.location.href = "php-include/delete_user.php?id=" + id;
      }
    }

    document.getElementById("searchInput").addEventListener("keyup", searchTable);
    paginateTable();
  </script>
</body>
</html>
