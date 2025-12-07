<?php
require_once("lib/config.php");

// The header.php file now handles session starting and authentication.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- Handle Actions ---
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
  $user_id = (int)$_GET['id'];

  if ($_GET['action'] === 'activate') {
      $stmt = $conn->prepare("UPDATE users SET status = 'active' WHERE id = ?");
      $stmt->bind_param("i", $user_id);
      $stmt->execute();
      $stmt->close();
      header("Location: user_driven.php");
      exit();
  }

  if ($_GET['action'] === 'deactivate') {
      $stmt = $conn->prepare("UPDATE users SET status = 'inactive' WHERE id = ?");
      $stmt->bind_param("i", $user_id);
      $stmt->execute();
      $stmt->close();
      header("Location: user_driven.php");
      exit();
  }

  if ($_GET['action'] === 'promote') {
      $stmt = $conn->prepare("UPDATE users SET role = 'admin' WHERE id = ?");
      $stmt->bind_param("i", $user_id);
      $stmt->execute();
      $stmt->close();
      header("Location: user_driven.php");
      exit();
  }

  if ($_GET['action'] === 'demote') {
      // Server-side check to prevent self-demotion
      if (isset($_SESSION['user_id']) && $user_id === (int)$_SESSION['user_id']) {
          // Redirect without making changes if a user tries to demote themselves via URL
          header("Location: user_driven.php");
          exit();
      }
      $stmt = $conn->prepare("UPDATE users SET role = 'user' WHERE id = ?");
      $stmt->bind_param("i", $user_id);
      $stmt->execute();
      $stmt->close();
      header("Location: user_driven.php");
      exit();
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $user_id = (int)$_POST['user_id'];
    $new_password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->bind_param("si", $new_password, $user_id);
    $stmt->execute();
    $stmt->close();
    header("Location: user_driven.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    header('Content-Type: application/json');
    $user_id = (int)$_POST['user_id'];

    // Prevent an admin from deleting their own account
    if (isset($_SESSION['user_id']) && $user_id === (int)$_SESSION['user_id']) {
        echo json_encode(['success' => false, 'message' => 'Error: You cannot delete your own account.']);
        exit();
    }

    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'User deleted successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete user.']);
    }
    $stmt->close();
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        h1, h2, h3, .font-serif { font-family: 'Playfair Display', serif; }
        /* Modal styles */
        .modal { display: none; }
        .modal.active { display: flex; }
    </style>
</head>
<body class="bg-gray-100 flex">

<?php include 'header.php'; ?>

<!-- Main content area -->
<div class="flex-1 md:ml-64">
    <main class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:px-8 pt-20 md:pt-12">
        <h1 class="text-3xl font-bold text-gray-900 mb-8">Manage Users</h1>

        <!-- Message Area -->
        <div id="message-area" class="mb-6"></div>

        <!-- Admins Table -->
        <div class="bg-white rounded-lg shadow-lg overflow-hidden mb-12">
            <div class="p-8">
                <h2 class="text-2xl font-bold text-gray-800 mb-6">Administrators</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-800">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">#</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Name</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Email</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Status</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-white uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php
                            
                            $sql = "SELECT id, name, email, status, role FROM users ORDER BY role DESC, id ASC";
                            $result = $conn->query($sql);
                            $admins = [];
                            $users = [];
                            if ($result && $result->num_rows > 0) {
                                while ($row = $result->fetch_assoc()) {
                                    if ($row['role'] === 'admin') {
                                        $admins[] = $row;
                                    } else {
                                        $users[] = $row;
                                    }
                                }
                            }

                            // Populate Admins Table
                            if (!empty($admins)) {
                                $adminCounter = 1;
                                foreach ($admins as $admin) { ?>
                                    <tr>
                                        <td class="px-6 py-4 text-sm font-medium text-gray-900"><?php echo $adminCounter++; ?></td>
                                        <td class="px-6 py-4 text-sm font-semibold text-gray-700"><?php echo htmlspecialchars($admin['name']); ?></td>
                                        <td class="px-6 py-4 text-sm text-gray-500"><?php echo htmlspecialchars($admin['email']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">active</span>
                                        </td>
                                        <td class="px-6 py-4 text-right text-sm font-medium">
                                            <?php if ($admin['id'] !== (int)$_SESSION['user_id']): ?>
                                                <div class="flex flex-col sm:flex-row sm:justify-end sm:space-x-2 space-y-2 sm:space-y-0">
                                                    <button onclick='openPasswordModal(<?php echo $admin['id']; ?>, "<?php echo htmlspecialchars(addslashes($admin['name'])); ?>")' class="w-full sm:w-auto bg-blue-500 hover:bg-blue-600 text-white font-bold py-1 px-3 rounded-md text-xs">Password</button>
                                                    <button onclick='openRoleChangeModal(<?php echo $admin['id']; ?>, "<?php echo htmlspecialchars(addslashes($admin['name'])); ?>", "demote")' class="w-full sm:w-auto bg-purple-500 hover:bg-purple-600 text-white font-bold py-1 px-3 rounded-md text-xs">Demote</button>
                                                    <button onclick='openDeleteModal(<?php echo $admin['id']; ?>, "<?php echo htmlspecialchars(addslashes($admin['name'])); ?>")' class="w-full sm:w-auto bg-red-500 hover:bg-red-600 text-white font-bold py-1 px-3 rounded-md text-xs">Delete</button>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-xs text-gray-400 font-semibold">ADMIN (YOU)</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php } // end foreach
                            } else {
                                echo "<tr><td colspan='5' class='px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center'>No administrators found.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            <div class="p-8">
                <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
                    <h2 class="text-2xl font-bold text-gray-800">Registered Users</h2>
                    <div class="flex items-center gap-4">
                        <input type="text" id="searchInput" class="w-full md:w-64 px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-yellow-500 focus:border-yellow-500" placeholder="Search users...">
                        <a href="export_users.php" class="inline-block bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-md text-sm whitespace-nowrap">Export CSV</a>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table id="usersTable" class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-800">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">#</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider cursor-pointer" onclick="sortTable(0)">Name ⬍</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider cursor-pointer" onclick="sortTable(1)">Email ⬍</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Status</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-white uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php
                            // Populate Users Table
                            if (!empty($users)) {
                                $userCounter = 1;
                                foreach ($users as $user) {
                                    // The existing logic for rendering a user row is perfect, so we'll reuse it here.
                                    echo "<tr class='user-row'>
                                        <td class='px-6 py-4 text-sm font-medium text-gray-900'>".($userCounter++)."</td>
                                        <td class='px-6 py-4 text-sm font-semibold text-gray-700'>" . htmlspecialchars($user['name']) . "</td>
                                        <td class='px-6 py-4 text-sm text-gray-500'>" . htmlspecialchars($user['email']) . "</td>
                                        <td class='px-6 py-4 whitespace-nowrap text-sm'>
                                            <span class='px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full " . ($user['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800') . "'>" . htmlspecialchars($user['status']) . "</span>
                                        </td>
                                        <td class='px-6 py-4 text-right text-sm font-medium'>
                                            <div class='flex flex-col sm:flex-row sm:justify-end sm:space-x-2 space-y-2 sm:space-y-0'>
                                            " . ($user['status'] === 'active' ? "<a href='user_driven.php?action=deactivate&id={$user['id']}' class='w-full sm:w-auto text-center bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-1 px-3 rounded-md text-xs'>Deactivate</a>" : "<a href='user_driven.php?action=activate&id={$user['id']}' class='w-full sm:w-auto text-center bg-green-500 hover:bg-green-600 text-white font-bold py-1 px-3 rounded-md text-xs'>Activate</a>") . "
                                            <button onclick='openRoleChangeModal({$user['id']}, \"" . htmlspecialchars(addslashes($user['name'])) . "\", \"promote\")' class='w-full sm:w-auto bg-teal-500 hover:bg-teal-600 text-white font-bold py-1 px-3 rounded-md text-xs'>Promote</button>
                                            <button onclick='openPasswordModal({$user['id']}, \"" . htmlspecialchars(addslashes($user['name'])) . "\")' class='w-full sm:w-auto bg-blue-500 hover:bg-blue-600 text-white font-bold py-1 px-3 rounded-md text-xs'>Password</button>
                                            <button onclick='openDeleteModal({$user['id']}, \"" . htmlspecialchars(addslashes($user['name'])) . "\")' class='w-full sm:w-auto bg-red-500 hover:bg-red-600 text-white font-bold py-1 px-3 rounded-md text-xs'>Delete</button>
                                            </div>
                                        </td>
                                    </tr>";
                                }
                            } else {
                                echo "<tr><td colspan='5' class='px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center'>No users found.</td></tr>";
                            } ?>
                        </tbody>
                    </table>
                </div>
                <div id="pagination" class="px-8 py-4 flex justify-center items-center space-x-2"></div>
            </div>
        </div>
    </main>
</div>

  <!-- Change Password Modal -->
  <div id="passwordModal" class="modal fixed inset-0 bg-gray-600 bg-opacity-50 items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl p-8 w-full max-w-md">
      <div class="flex justify-between items-center mb-6">
        <h3 class="text-2xl font-bold text-gray-800">Change Password for <span id="modalUserName"></span></h3>
        <button class="text-gray-500 hover:text-gray-800" onclick="closePasswordModal()">&times;</button>
      </div>
      <form action="user_driven.php" method="POST" class="space-y-4">
        <input type="hidden" name="user_id" id="modalUserId">
        <div>
            <label for="new_password" class="block text-sm font-medium text-gray-700">New Password</label>
            <input type="password" id="new_password" name="new_password" required class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-yellow-500 focus:border-yellow-500">
        </div>
        <button type="submit" name="change_password" class="w-full bg-gray-800 text-white px-6 py-2 rounded-lg hover:bg-gray-700 transition-colors font-semibold">Update Password</button>
      </form>
    </div>
  </div>

  <!-- Delete Confirmation Modal -->
  <div id="deleteModal" class="modal fixed inset-0 bg-gray-600 bg-opacity-50 items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl p-8 w-full max-w-md">
      <div class="flex justify-between items-center mb-4">
        <h3 class="text-2xl font-bold text-gray-800">Confirm Deletion</h3>
        <button class="text-gray-500 hover:text-gray-800 text-3xl" onclick="closeDeleteModal()">&times;</button>
      </div>
      <p class="text-gray-600 mb-6">Are you sure you want to permanently delete the user <strong id="deleteUserName" class="font-bold"></strong>? This action cannot be undone.</p>
      <form id="deleteUserForm" method="POST">
        <input type="hidden" name="delete_user" value="1">
        <input type="hidden" name="user_id" id="deleteUserId">
        <div class="flex justify-end gap-4 mt-8">
          <button type="button" onclick="closeDeleteModal()" class="px-6 py-2 rounded-lg bg-gray-200 text-gray-800 hover:bg-gray-300 font-semibold">Cancel</button>
          <button type="submit" class="px-6 py-2 rounded-lg bg-red-600 text-white hover:bg-red-700 font-semibold">Delete</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Role Change Confirmation Modal -->
  <div id="roleChangeModal" class="modal fixed inset-0 bg-gray-600 bg-opacity-50 items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl p-8 w-full max-w-md">
      <div class="flex justify-between items-center mb-4">
        <h3 id="roleChangeModalTitle" class="text-2xl font-bold text-gray-800">Confirm Role Change</h3>
        <button class="text-gray-500 hover:text-gray-800 text-3xl" onclick="closeRoleChangeModal()">&times;</button>
      </div>
      <p id="roleChangeModalText" class="text-gray-600 mb-6"></p>
      <form id="roleChangeForm" method="GET" action="user_driven.php">
        <input type="hidden" name="action" id="roleChangeAction">
        <input type="hidden" name="id" id="roleChangeUserId">
        <div class="flex justify-end gap-4 mt-8">
          <button type="button" onclick="closeRoleChangeModal()" class="px-6 py-2 rounded-lg bg-gray-200 text-gray-800 hover:bg-gray-300 font-semibold">Cancel</button>
          <button id="roleChangeConfirmBtn" type="submit" class="px-6 py-2 rounded-lg text-white font-semibold">Confirm</button>
        </div>
      </form>
    </div>
  </div>


  <!-- Result/Success Modal -->
  <div id="resultModal" class="modal fixed inset-0 bg-gray-600 bg-opacity-50 items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl p-8 w-full max-w-sm text-center">
      <div class="flex justify-center mb-4">
        <!-- Success Icon -->
        <svg id="resultSuccessIcon" xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 text-green-500 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <!-- Error Icon -->
        <svg id="resultErrorIcon" xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 text-red-500 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
      </div>
      <h3 id="resultModalTitle" class="text-2xl font-bold text-gray-800 mb-2"></h3>
      <p id="resultModalMessage" class="text-gray-600 mb-8"></p>
      <button id="resultModalOkButton" class="w-full px-6 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700 font-semibold">OK</button>
    </div>
  </div>

  <script>
    const passwordModal = document.getElementById('passwordModal');
    const modalUserId = document.getElementById('modalUserId');
    const modalUserName = document.getElementById('modalUserName');
    const deleteModal = document.getElementById('deleteModal');
    const roleChangeModal = document.getElementById('roleChangeModal');
    const rowsPerPage = 10;
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
        prevBtn.innerHTML = "&laquo; Prev";
        prevBtn.className = "px-4 py-2 text-sm font-medium text-gray-700 bg-white rounded-md border border-gray-300 hover:bg-gray-50";
        prevBtn.onclick = function() { currentPage--; paginateTable(); };
        pagination.appendChild(prevBtn);
      }

      // Page number buttons
      let startPage = Math.max(1, currentPage - 2);
      let endPage = Math.min(totalPages, startPage + 4);
      if (endPage - startPage < 4) startPage = Math.max(1, endPage - 4);

      for (let i = startPage; i <= endPage; i++) {
        const btn = document.createElement("button");
        btn.innerText = i; 
        btn.className = `px-4 py-2 text-sm font-medium rounded-md border ${i === currentPage ? 'bg-yellow-400 text-black border-yellow-400 z-10' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'}`;
        btn.onclick = function() { currentPage = i; paginateTable(); };
        pagination.appendChild(btn);
      }

      // Next button
      if (currentPage < totalPages) {
        const nextBtn = document.createElement("button");
        nextBtn.innerHTML = "Next &raquo;";
        nextBtn.className = "px-4 py-2 text-sm font-medium text-gray-700 bg-white rounded-md border border-gray-300 hover:bg-gray-50";
        nextBtn.onclick = function() { currentPage++; paginateTable(); };
        pagination.appendChild(nextBtn);
      }
    }

    function searchTable() {
      const input = document.getElementById("searchInput").value.toLowerCase();
      const table = document.getElementById("usersTable");
      const tbody = table.getElementsByTagName("tbody")[0];
      const rows = Array.from(tbody.getElementsByTagName("tr"));
      const noResultsRow = tbody.querySelector('td[colspan="5"]');
      if (noResultsRow) return; // Don't filter if the table is empty

      const filteredRows = rows.filter(row => {
        return Array.from(row.getElementsByTagName("td")).some(td => td.innerText.toLowerCase().includes(input));
      });

      rows.forEach(row => row.style.display = 'none');
      filteredRows.forEach(row => tbody.appendChild(row));
      paginateTable();
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

    function openPasswordModal(userId, userName) {
      modalUserId.value = userId;
      modalUserName.textContent = userName;
      passwordModal.classList.add('active');
    }
    function closePasswordModal() {
      passwordModal.classList.remove('active');
    }

    function openDeleteModal(userId, userName) {
        document.getElementById('deleteUserId').value = userId;
        document.getElementById('deleteUserName').textContent = userName;
        deleteModal.classList.add('active');
    }

    function closeDeleteModal() {
        deleteModal.classList.remove('active');
    }

    function openRoleChangeModal(userId, userName, action) {
        document.getElementById('roleChangeUserId').value = userId;
        document.getElementById('roleChangeAction').value = action;

        const modalText = document.getElementById('roleChangeModalText');
        const confirmBtn = document.getElementById('roleChangeConfirmBtn');

        if (action === 'promote') {
            modalText.innerHTML = `Are you sure you want to promote <strong class="font-bold">${userName}</strong> to an Administrator?`;
            confirmBtn.className = 'px-6 py-2 rounded-lg text-white font-semibold bg-teal-500 hover:bg-teal-600';
            confirmBtn.textContent = 'Promote';
        } else {
            modalText.innerHTML = `Are you sure you want to demote <strong class="font-bold">${userName}</strong> to a regular User?`;
            confirmBtn.className = 'px-6 py-2 rounded-lg text-white font-semibold bg-purple-500 hover:bg-purple-600';
            confirmBtn.textContent = 'Demote';
        }

        roleChangeModal.classList.add('active');
    }

    function closeRoleChangeModal() {
        roleChangeModal.classList.remove('active');
    }

    window.onclick = function(event) {
      if (event.target == passwordModal) {
        closePasswordModal();
      }
      if (event.target == deleteModal) {
        closeDeleteModal();
      }
      if (event.target == roleChangeModal) {
        closeRoleChangeModal();
      }
    }
    // Make functions globally accessible for inline onclick handlers
    window.openPasswordModal = openPasswordModal;
    window.closePasswordModal = closePasswordModal;
    window.openDeleteModal = openDeleteModal;
    window.closeDeleteModal = closeDeleteModal;
    window.sortTable = sortTable;
    window.openRoleChangeModal = openRoleChangeModal;
    window.closeRoleChangeModal = closeRoleChangeModal;
    
    const resultModal = document.getElementById('resultModal');
    const resultSuccessIcon = document.getElementById('resultSuccessIcon');
    const resultErrorIcon = document.getElementById('resultErrorIcon');
    const resultModalTitle = document.getElementById('resultModalTitle');
    const resultModalMessage = document.getElementById('resultModalMessage');
    const resultModalOkButton = document.getElementById('resultModalOkButton');

    document.addEventListener('DOMContentLoaded', function() { // This should wrap all DOM-dependent code
        document.getElementById('deleteUserForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);

            fetch('user_driven.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                closeDeleteModal(); // Close the confirmation modal

                // Populate and show the result modal
                resultModalTitle.textContent = data.success ? 'Success' : 'Error';
                resultModalMessage.textContent = data.message;
                resultSuccessIcon.classList.toggle('hidden', !data.success);
                resultErrorIcon.classList.toggle('hidden', data.success);
                resultModal.classList.add('active');

                // Define what the OK button does
                resultModalOkButton.onclick = () => {
                    resultModal.classList.remove('active');
                    if (data.success) {
                        window.location.reload();
                    }
                };
            })
            .catch(error => console.error('Error:', error));
        });

        document.getElementById("searchInput").addEventListener("keyup", function() {
            currentPage = 1; // Reset to first page on search
            searchTable();
        });
        paginateTable();
    });
  </script>
</body>
</html>
