<?php
session_start();
if (!isset($_SESSION['user_email'])) {
    header("Location: login.php");
    exit();
}
include '../config/database.php';
include '../core/functions.php';
include '../includes/header.php';
include '../includes/sidebar.php';

// Handle category creation, update, and deletion
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['add_category'])) {
        $name = sanitizeInput($_POST['name']);
        $sql = "INSERT INTO categories (name) VALUES ('$name')";
        if ($conn->query($sql) === TRUE) {
            $message = "Category added successfully";
        } else {
            $error = "Error adding category: " . $conn->error;
        }
    } elseif (isset($_POST['update_category'])) {
        $id = sanitizeInput($_POST['id']);
        $name = sanitizeInput($_POST['name']);
        $sql = "UPDATE categories SET name='$name' WHERE id=$id";
        if ($conn->query($sql) === TRUE) {
            $message = "Category updated successfully";
        } else {
            $error = "Error updating category: " . $conn->error;
        }
    } elseif (isset($_POST['delete_category'])) {
        $id = sanitizeInput($_POST['id']);
        $sql = "DELETE FROM categories WHERE id=$id";
        if ($conn->query($sql) === TRUE) {
            $message = "Category deleted successfully";
        } else {
            $error = "Error deleting category: " . $conn->error;
        }
    }
}

// Fetch categories for display
$categorySql = "SELECT * FROM categories";
$categoryResult = $conn->query($categorySql);
?>

<h2>Manage Categories</h2>

<?php if (isset($message)) {
    echo "<p class='success'>$message</p>";
} ?>
<?php if (isset($error)) {
    echo "<p class='error'>$error</p>";
} ?>

<h3>Add Category</h3>
<form method="post">
    <input type="text" name="name" placeholder="Category Name" required><br>
    <button type="submit" name="add_category">Add Category</button>
</form>

<h3>Category List</h3>
<table>
    <thead>
        <tr>
            <th>Category Name</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <?php
        if ($categoryResult->num_rows > 0) {
            while ($categoryRow = $categoryResult->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . $categoryRow['name'] . "</td>";
                echo "<td>";
                echo "<button onclick=\"editCategory(" . $categoryRow['id'] . ", '" . $categoryRow['name'] . "')\">Edit</button> | ";
                echo "<form method='post' style='display:inline;'><input type='hidden' name='id' value='" . $categoryRow['id'] . "'><button type='submit' name='delete_category'>Delete</button></form>";
                echo "</td>";
                echo "</tr>";
            }
        } else {
            echo "<tr><td colspan='2'>No categories found</td></tr>";
        }
        ?>
    </tbody>
</table>

<div id="edit_category_form" style="display:none;">
    <h3>Edit Category</h3>
    <form method="post">
        <input type="hidden" name="id" id="edit_id">
        <input type="text" name="name" id="edit_name" placeholder="Category Name" required><br>
        <button type="submit" name="update_category">Update Category</button>
        <button type="button" onclick="document.getElementById('edit_category_form').style.display='none';">Cancel</button>
    </form>
</div>

<script>
    function editCategory(id, name) {
        document.getElementById('edit_category_form').style.display = 'block';
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_name').value = name;
    }
</script>

<?php include '../includes/footer.php'; ?>
