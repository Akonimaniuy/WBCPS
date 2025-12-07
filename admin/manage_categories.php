<?php
require_once("lib/config.php");
  
// The header.php file now handles session starting and authentication.
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
  header("Location: ../index.php"); // Redirect non-admins to the main site
  exit();
}

// --- CRUD Operations ---

// Add Category
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_category'])) {
    $name = $_POST['name'];
    $description = $_POST['description'];
    $stmt = $conn->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
    $stmt->bind_param("ss", $name, $description);
    $stmt->execute();
    $stmt->close();
    header("Location: manage_categories.php");
    exit();
}

// Handle Update Category (from AJAX)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_category'])) {
    header('Content-Type: application/json');
    $id = $_POST['category_id'];
    $name = $_POST['name'];
    $description = $_POST['description'];

    if (empty($id) || empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Category ID and Name are required.']);
        exit();
    }

    $stmt = $conn->prepare("UPDATE categories SET name = ?, description = ? WHERE id = ?");
    $stmt->bind_param("ssi", $name, $description, $id);
    
    // No need to check execute result here, we'll rely on the response for the user
    $stmt->execute();
    echo json_encode(['success' => true, 'message' => 'Category updated successfully!']);
    exit();
}

// Handle Delete Category (from AJAX)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_category'])) {
    header('Content-Type: application/json');
    $category_id = (int)$_POST['category_id'];

    // Before deleting, check if any majors are using this category
    $check_stmt = $conn->prepare("SELECT COUNT(*) FROM majors WHERE category_id = ?");
    $check_stmt->bind_param("i", $category_id);
    $check_stmt->execute();
    $count = $check_stmt->get_result()->fetch_row()[0];
    $check_stmt->close();

    if ($count > 0) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete category. It is currently in use by ' . $count . ' major(s).']);
        exit();
    }

    $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
    $stmt->bind_param("i", $category_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Category deleted successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error: Could not delete the category.']);
    }
    $stmt->close();
    exit();
}

// Fetch all categories for display
$categories = [];
$result = $conn->query("SELECT * FROM categories ORDER BY name ASC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Categories - Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        h1, h2, h3, .font-serif { font-family: 'Playfair Display', serif; }
        .modal { display: none; }
        .modal.active { display: flex; }
    </style>
</head>
<body class="bg-gray-100 flex">

<?php include 'header.php'; ?>

<!-- Main content area -->
<div class="w-full md:pl-64">
    <main class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:px-8 pt-20 md:pt-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-8">Manage Categories</h1>

        <!-- Categories List -->
        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            <div class="p-8">
                <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
                    <h2 class="text-2xl font-bold text-gray-800">Existing Categories</h2>
                    <div class="flex items-center gap-4">
                        <input type="text" id="categorySearchInput" class="w-full md:w-64 px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-yellow-500 focus:border-yellow-500" placeholder="Search categories...">
                        <button id="openAddCategoryModal" class="bg-gray-800 text-white px-5 py-2 rounded-lg hover:bg-gray-700 font-semibold text-sm whitespace-nowrap">Add Category</button>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table id="categoriesTable" class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-800">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">#</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Name</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Description</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-white uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="categoriesTableBody" class="bg-white divide-y divide-gray-200">
                            <?php if (empty($categories)): ?>
                                <tr><td colspan="4" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">No categories found.</td></tr>
                            <?php else: ?>
                                <?php $categoryCounter = 1; ?>
                                <?php foreach ($categories as $category): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo $categoryCounter++; ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-700"><?php echo htmlspecialchars($category['name']); ?></td>
                                        <td class="px-6 py-4 text-sm text-gray-500"><?php echo htmlspecialchars($category['description']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                                            <button 
                                                class="edit-category-btn inline-block bg-blue-500 hover:bg-blue-600 text-white font-bold py-1 px-3 rounded-md text-xs"
                                                data-id="<?php echo $category['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($category['name']); ?>"
                                                data-description="<?php echo htmlspecialchars($category['description']); ?>"
                                            >
                                                Edit
                                            </button>
                                            <button 
                                                class="delete-category-btn inline-block bg-red-500 hover:bg-red-600 text-white font-bold py-1 px-3 rounded-md text-xs"
                                                data-id="<?php echo $category['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($category['name']); ?>"
                                            >Delete</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div id="pagination" class="px-8 py-4 flex justify-center items-center space-x-2"></div>
            </div>
        </div>
    </main>
</div>

<!-- Add Category Modal -->
<div id="addCategoryModal" class="modal fixed inset-0 bg-gray-600 bg-opacity-50 items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl p-8 w-full max-w-lg">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-2xl font-bold text-gray-800">Add New Category</h3>
            <button id="closeAddModal" class="text-gray-500 hover:text-gray-800 text-3xl">&times;</button>
        </div>
        <form action="manage_categories.php" method="POST" class="space-y-4">
            <div>
                <label for="name" class="block text-sm font-medium text-gray-700">Category Name</label>
                <input type="text" id="name" name="name" placeholder="e.g., Information Technology" required class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-yellow-500 focus:border-yellow-500">
            </div>
            <div>
                <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
                <textarea id="description" name="description" placeholder="A brief description of the category" rows="3" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-yellow-500 focus:border-yellow-500"></textarea>
            </div>
            <div>
                <button type="submit" name="add_category" class="w-full bg-gray-800 text-white px-6 py-3 rounded-lg hover:bg-gray-700 transition-colors font-semibold">Add Category</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Category Modal -->
<div id="editCategoryModal" class="modal fixed inset-0 bg-gray-600 bg-opacity-50 items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl p-8 w-full max-w-lg">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-2xl font-bold text-gray-800">Edit Category</h3>
            <button id="closeEditModal" class="text-gray-500 hover:text-gray-800 text-3xl">&times;</button>
        </div>
        <form id="editCategoryForm" method="POST" class="space-y-4">
            <input type="hidden" name="update_category" value="1">
            <input type="hidden" id="edit_category_id" name="category_id">
            
            <div>
                <label for="edit_name" class="block text-sm font-medium text-gray-700">Category Name</label>
                <input type="text" id="edit_name" name="name" required class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-yellow-500 focus:border-yellow-500">
            </div>
            <div>
                <label for="edit_description" class="block text-sm font-medium text-gray-700">Description</label>
                <textarea id="edit_description" name="description" rows="3" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-yellow-500 focus:border-yellow-500"></textarea>
            </div>
            <div>
                <button type="submit" class="w-full bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition-colors font-semibold">Update Category</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteCategoryModal" class="modal fixed inset-0 bg-gray-600 bg-opacity-50 items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl p-8 w-full max-w-md">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-2xl font-bold text-gray-800">Confirm Deletion</h3>
            <button id="closeDeleteModal" class="text-gray-500 hover:text-gray-800 text-3xl">&times;</button>
        </div>
        <p class="text-gray-600 mb-6">Are you sure you want to permanently delete the category <strong id="deleteCategoryName" class="font-bold"></strong>? This action cannot be undone.</p>
        <form id="deleteCategoryForm" method="POST">
            <input type="hidden" name="delete_category" value="1">
            <input type="hidden" name="category_id" id="delete_category_id">
            <div class="flex justify-end gap-4 mt-8">
                <button type="button" id="cancelDeleteBtn" class="px-6 py-2 rounded-lg bg-gray-200 text-gray-800 hover:bg-gray-300 font-semibold">Cancel</button>
                <button type="submit" class="px-6 py-2 rounded-lg bg-red-600 text-white hover:bg-red-700 font-semibold">Delete</button>
            </div>
        </form>
    </div>
</div>

<!-- Result/Success Modal -->
<div id="resultModal" class="modal fixed inset-0 bg-gray-600 bg-opacity-50 items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl p-8 w-full max-w-sm text-center">
        <div class="flex justify-center mb-4">
            <svg id="resultSuccessIcon" class="h-16 w-16 text-green-500 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            <svg id="resultErrorIcon" class="h-16 w-16 text-red-500 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
        </div>
        <h3 id="resultModalTitle" class="text-2xl font-bold text-gray-800 mb-2"></h3>
        <p id="resultModalMessage" class="text-gray-600 mb-8"></p>
        <button id="resultModalOkButton" class="w-full px-6 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700 font-semibold">OK</button>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const addModal = document.getElementById('addCategoryModal');
    const openAddModalBtn = document.getElementById('openAddCategoryModal');
    const closeAddModalBtn = document.getElementById('closeAddModal');
    const editModal = document.getElementById('editCategoryModal');
    const closeEditModalBtn = document.getElementById('closeEditModal');
    const deleteModal = document.getElementById('deleteCategoryModal');
    const resultModal = document.getElementById('resultModal');

    openAddModalBtn.addEventListener('click', () => addModal.classList.add('active'));
    closeAddModalBtn.addEventListener('click', () => addModal.classList.remove('active'));

    document.querySelectorAll('.edit-category-btn').forEach(button => {
        button.addEventListener('click', () => {
            document.getElementById('edit_category_id').value = button.dataset.id;
            document.getElementById('edit_name').value = button.dataset.name;
            document.getElementById('edit_description').value = button.dataset.description;
            editModal.classList.add('active');
        });
    });

    closeEditModalBtn.addEventListener('click', () => editModal.classList.remove('active'));
    window.addEventListener('click', (e) => { 
        if (e.target === editModal) editModal.classList.remove('active'); 
        if (e.target === addModal) addModal.classList.remove('active');
        if (e.target === deleteModal) deleteModal.classList.remove('active');
        if (e.target === resultModal) resultModal.classList.remove('active');
    });

    document.getElementById('editCategoryForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        fetch('manage_categories.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            alert(data.message);
            if (data.success) {
                window.location.reload();
            }
        })
        .catch(error => console.error('Error:', error));
    });

    // --- Delete Modal Logic ---
    document.querySelectorAll('.delete-category-btn').forEach(button => {
        button.addEventListener('click', () => {
            document.getElementById('delete_category_id').value = button.dataset.id;
            document.getElementById('deleteCategoryName').textContent = button.dataset.name;
            deleteModal.classList.add('active');
        });
    });

    document.getElementById('closeDeleteModal').addEventListener('click', () => deleteModal.classList.remove('active'));
    document.getElementById('cancelDeleteBtn').addEventListener('click', () => deleteModal.classList.remove('active'));

    document.getElementById('deleteCategoryForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        fetch('manage_categories.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            deleteModal.classList.remove('active');

            const resultModalTitle = document.getElementById('resultModalTitle');
            const resultModalMessage = document.getElementById('resultModalMessage');
            const resultSuccessIcon = document.getElementById('resultSuccessIcon');
            const resultErrorIcon = document.getElementById('resultErrorIcon');
            const resultModalOkButton = document.getElementById('resultModalOkButton');

            resultModalTitle.textContent = data.success ? 'Success' : 'Error';
            resultModalMessage.textContent = data.message;
            resultSuccessIcon.classList.toggle('hidden', !data.success);
            resultErrorIcon.classList.toggle('hidden', data.success);
            resultModal.classList.add('active');

            resultModalOkButton.onclick = () => {
                resultModal.classList.remove('active');
                if (data.success) {
                    window.location.reload();
                }
            };
        })
        .catch(error => console.error('Error:', error));
    });

    // --- Search and Pagination Functionality ---
    const searchInput = document.getElementById('categorySearchInput');
    const tableBody = document.getElementById('categoriesTableBody');
    const allRows = Array.from(tableBody.getElementsByTagName('tr'));
    const paginationContainer = document.getElementById('pagination');
    const rowsPerPage = 10;
    let currentPage = 1;

    function applyFiltersAndPagination() {
        const searchTerm = searchInput.value.toLowerCase();

        // 1. Filter rows based on search term
        const visibleRows = allRows.filter(row => {
            // Don't filter the 'No categories found' row
            if (row.getElementsByTagName('td').length <= 1) {
                return true;
            }
            const nameText = row.cells[1]?.textContent.toLowerCase() || '';
            const descriptionText = row.cells[2]?.textContent.toLowerCase() || '';
            const isVisible = nameText.includes(searchTerm) || descriptionText.includes(searchTerm);
            row.style.display = 'none'; // Hide all rows initially
            return isVisible;
        });

        // 2. Paginate the filtered (visible) rows
        const totalPages = Math.ceil(visibleRows.length / rowsPerPage);
        const start = (currentPage - 1) * rowsPerPage;
        const end = start + rowsPerPage;

        visibleRows.slice(start, end).forEach(row => {
            row.style.display = ''; // Show only the rows for the current page
        });

        // 3. Render pagination controls
        renderPagination(totalPages);
    }

    function renderPagination(totalPages) {
        paginationContainer.innerHTML = "";
        if (totalPages <= 1) return;

        if (currentPage > 1) {
            const prevBtn = document.createElement("button");
            prevBtn.innerHTML = "&laquo; Prev";
            prevBtn.className = "px-4 py-2 text-sm font-medium text-gray-700 bg-white rounded-md border border-gray-300 hover:bg-gray-50";
            prevBtn.onclick = () => { currentPage--; applyFiltersAndPagination(); };
            paginationContainer.appendChild(prevBtn);
        }

        const pageInfo = document.createElement('span');
        pageInfo.className = 'px-4 py-2 text-sm font-medium text-gray-700';
        pageInfo.textContent = `Page ${currentPage} of ${totalPages}`;
        paginationContainer.appendChild(pageInfo);

        if (currentPage < totalPages) {
            const nextBtn = document.createElement("button");
            nextBtn.innerHTML = "Next &raquo;";
            nextBtn.className = "px-4 py-2 text-sm font-medium text-gray-700 bg-white rounded-md border border-gray-300 hover:bg-gray-50";
            nextBtn.onclick = () => { currentPage++; applyFiltersAndPagination(); };
            paginationContainer.appendChild(nextBtn);
        }
    }

    searchInput.addEventListener('keyup', () => {
        currentPage = 1; // Reset to the first page on a new search
        applyFiltersAndPagination();
    });

    // Initial load
    applyFiltersAndPagination();
});
</script>

</body>
</html>