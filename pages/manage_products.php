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

// Handle product form submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['add_product'])) {
        $category_id = sanitizeInput($_POST['category_id']);
        $name = sanitizeInput($_POST['name']);
        $price = sanitizeInput($_POST['price']);
        $quantity = sanitizeInput($_POST['quantity']);

        $sql = "INSERT INTO products (category_id, name, price, quantity) VALUES ($category_id, '$name', $price, $quantity)";

        if ($conn->query($sql) === TRUE) {
            $message = "Product added successfully";
        } else {
            $error = "Error adding product: " . $conn->error;
        }
    } elseif (isset($_POST['update_product'])) {
        $id = sanitizeInput($_POST['id']);
        $category_id = sanitizeInput($_POST['category_id']);
        $name = sanitizeInput($_POST['name']);
        $price = sanitizeInput($_POST['price']);
        $quantity = sanitizeInput($_POST['quantity']);

        $sql = "UPDATE products SET category_id=$category_id, name='$name', price=$price, quantity=$quantity WHERE id=$id";

        if ($conn->query($sql) === TRUE) {
            $message = "Product updated successfully";
        } else {
            $error = "Error updating product: " . $conn->error;
        }
    } elseif (isset($_POST['delete_product'])) {
        $id = sanitizeInput($_POST['id']);
        $sql = "DELETE FROM products WHERE id=$id";

        if ($conn->query($sql) === TRUE) {
            $message = "Product deleted successfully";
        } else {
            $error = "Error deleting product: " . $conn->error;
        }
    }
}

// Fetch categories for dropdown
$categorySql = "SELECT * FROM categories";
$categoryResult = $conn->query($categorySql);

// Fetch products for listing
$productsSql = "SELECT products.*, categories.name as category_name FROM products LEFT JOIN categories ON products.category_id = categories.id";
$productsResult = $conn->query($productsSql);

?>

<h2>Manage Products</h2>

<?php if (isset($message)) {
    echo "<p class='success'>$message</p>";
} ?>
<?php if (isset($error)) {
    echo "<p class='error'>$error</p>";
} ?>

<form method="post">
    <select name="category_id" id="category_id" required>
        <option value="">Select Category</option>
        <?php
        if ($categoryResult->num_rows > 0) {
            while ($categoryRow = $categoryResult->fetch_assoc()) {
                echo "<option value='" . $categoryRow['id'] . "'>" . $categoryRow['name'] . "</option>";
            }
        }
        ?>
    </select><br>
    <input type="text" name="name" placeholder="Name" required><br>
    <input type="number" name="price" placeholder="Price" required><br>
    <input type="number" name="quantity" placeholder="Quantity" required><br>
    <button type="submit" name="add_product">Add Product</button>
</form>

<hr>

<h3>Product List</h3>
<table>
    <thead>
        <tr>
            <th>Category</th>
            <th>Name</th>
            <th>Price</th>
            <th>Quantity</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <?php
        if ($productsResult->num_rows > 0) {
            while ($productRow = $productsResult->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . $productRow['category_name'] . "</td>";
                echo "<td>" . $productRow['name'] . "</td>";
                echo "<td>" . $productRow['price'] . "</td>";
                echo "<td>" . $productRow['quantity'] . "</td>";
                echo "<td>";
                echo "<button onclick=\"editProduct(" . $productRow['id'] . ", " . $productRow['category_id'] . ", '" . $productRow['name'] . "', " . $productRow['price'] . ", " . $productRow['quantity'] . ")\">Edit</button> | ";
                echo "<form method='post' style='display:inline;'><input type='hidden' name='id' value='" . $productRow['id'] . "'><button type='submit' name='delete_product'>Delete</button></form>";
                echo "</td>";
                echo "</tr>";
            }
        } else {
            echo "<tr><td colspan='5'>No products found</td></tr>";
        }
        ?>
    </tbody>
</table>

<div id="edit_product_form" style="display:none;">
    <h3>Edit Product</h3>
    <form method="post">
        <input type="hidden" name="id" id="edit_product_id">
        <select name="category_id" id="edit_category_id" required>
            <option value="">Select Category</option>
            <?php
            // Fetch categories again for the edit form
            $editCategorySql = "SELECT * FROM categories";
            $editCategoryResult = $conn->query($editCategorySql);
            if ($editCategoryResult->num_rows > 0) {
                while ($editCategoryRow = $editCategoryResult->fetch_assoc()) {
                    echo "<option value='" . $editCategoryRow['id'] . "'>" . $editCategoryRow['name'] . "</option>";
                }
            }
            ?>
        </select><br>
        <input type="text" name="name" id="edit_name" placeholder="Name" required><br>
        <input type="number" name="price" id="edit_price" placeholder="Price" required><br>
        <input type="number" name="quantity" id="edit_quantity" placeholder="Quantity" required><br>
        <button type="submit" name="update_product">Update Product</button>
        <button type="button" onclick="document.getElementById('edit_product_form').style.display='none';">Cancel</button>
    </form>
</div>

<script>
    function editProduct(id, category_id, name, price, quantity) {
        document.getElementById('edit_product_form').style.display = 'block';
        document.getElementById('edit_product_id').value = id;
        document.getElementById('edit_category_id').value = category_id; // Set the selected category
        document.getElementById('edit_name').value = name;
        document.getElementById('edit_price').value = price;
        document.getElementById('edit_quantity').value = quantity;
    }
</script>

<?php include '../includes/footer.php'; ?>