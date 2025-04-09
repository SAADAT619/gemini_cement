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

// Handle category form submissions
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

    // Handle product form submissions
    if (isset($_POST['add_product'])) {
        $category_id = sanitizeInput($_POST['category_id']);
        $name = sanitizeInput($_POST['name']);
        $brand_name = sanitizeInput($_POST['brand_name']);
        $type = sanitizeInput($_POST['type']);
        $price = floatval(sanitizeInput($_POST['price']));
        $quantity = floatval(sanitizeInput($_POST['quantity']));
        $unit = sanitizeInput($_POST['unit']);

        // Check if a product with the same name, category, unit, price, brand_name, and type already exists
        $checkSql = "SELECT id, quantity FROM products 
                     WHERE name = '$name' 
                     AND category_id = $category_id 
                     AND unit = '$unit' 
                     AND price = $price 
                     AND brand_name = '$brand_name' 
                     AND (type = '$type' OR (type IS NULL AND '$type' = ''))";
        $checkResult = $conn->query($checkSql);

        if ($checkResult->num_rows > 0) {
            // Product exists, update the quantity
            $existingProduct = $checkResult->fetch_assoc();
            $newQuantity = $existingProduct['quantity'] + $quantity;
            $updateSql = "UPDATE products 
                          SET quantity = $newQuantity 
                          WHERE id = " . $existingProduct['id'];
            if ($conn->query($updateSql) === TRUE) {
                $message = "Product quantity updated successfully";
            } else {
                $error = "Error updating product quantity: " . $conn->error;
            }
        } else {
            // Product does not exist, insert a new product
            $sql = "INSERT INTO products (category_id, name, price, quantity, unit, brand_name, type)
                    VALUES ($category_id, '$name', $price, $quantity, '$unit', '$brand_name', '$type')";

            if ($conn->query($sql) === TRUE) {
                $message = "Product added successfully";
            } else {
                $error = "Error adding product: " . $conn->error;
            }
        }
    } elseif (isset($_POST['delete_product'])) {
        $id = sanitizeInput($_POST['id']);
        $sql = "DELETE FROM products WHERE id=$id";
        if ($conn->query($sql) === TRUE) {
            $message = "Product deleted successfully";
        } else {
            $error = "Error deleting product: " . $conn->error;
        }
    } elseif (isset($_POST['update_product'])) {
        $id = sanitizeInput($_POST['id']);
        $category_id = sanitizeInput($_POST['category_id']);
        $name = sanitizeInput($_POST['name']);
        $brand_name = sanitizeInput($_POST['brand_name']);
        $type = sanitizeInput($_POST['type']);
        $price = floatval(sanitizeInput($_POST['price']));
        $quantity = floatval(sanitizeInput($_POST['quantity']));
        $unit = sanitizeInput($_POST['unit']);

        $sql = "UPDATE products SET category_id=$category_id, name='$name', brand_name='$brand_name', type='$type', price=$price, quantity=$quantity, unit='$unit' WHERE id=$id";

        if ($conn->query($sql) === TRUE) {
            $message = "Product updated successfully";
        } else {
            $error = "Error updating product: " . $conn->error;
        }
    }
}

// Fetch categories for dropdown and display
$categorySql = "SELECT * FROM categories";
$categoryResult = $conn->query($categorySql);

// Fetch products for listing, ordered by created_at DESC to show newest first
$productSql = "SELECT products.*, categories.name as category_name 
               FROM products 
               LEFT JOIN categories ON products.category_id = categories.id 
               ORDER BY products.created_at DESC";
$productResult = $conn->query($productSql);

// Create a category mapping for JavaScript
$categoryNames = [];
if ($categoryResult->num_rows > 0) {
    $categoryResult->data_seek(0); // Reset pointer
    while ($row = $categoryResult->fetch_assoc()) {
        $categoryNames[$row['id']] = $row['name'];
    }
}
?>

<h2>Category & Products</h2>

<?php if (isset($message)) {
    echo "<p class='success'>$message</p>";
} ?>
<?php if (isset($error)) {
    echo "<p class='error'>$error</p>";
} ?>

<!-- Category Management Section -->
<div class="section">
    <h3>Add Category</h3>
    <form method="post" class="category-form">
        <input type="text" name="name" placeholder="Category Name (e.g., Cement, Rod)" required><br>
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
                    echo "<td>" . htmlspecialchars($categoryRow['name']) . "</td>";
                    echo "<td>";
                    echo "<button onclick=\"editCategory(" . $categoryRow['id'] . ", '" . htmlspecialchars($categoryRow['name']) . "')\">Edit</button> | ";
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
</div>

<hr>

<!-- Product Management Section -->
<div class="section">
    <h3>Add Product</h3>
    <form method="post" class="product-form">
        <select name="category_id" id="category_id" required onchange="updateUnitDropdown()">
            <option value="">Select Category</option>
            <?php
            $categoryResult->data_seek(0);
            if ($categoryResult->num_rows > 0) {
                while ($categoryRow = $categoryResult->fetch_assoc()) {
                    echo "<option value='" . $categoryRow['id'] . "'>" . htmlspecialchars($categoryRow['name']) . "</option>";
                }
            }
            ?>
        </select><br>
        <input type="text" name="name" placeholder="Product Name (e.g., Portland Cement)" required><br>
        <input type="text" name="brand_name" placeholder="Brand Name (e.g., BSRM)" required><br>
        <input type="text" name="type" placeholder="Type (e.g., 8mm for Rod, leave blank for Cement)"><br>
        <input type="number" name="price" placeholder="Price" step="0.01" required><br>
        <input type="number" name="quantity" placeholder="Quantity" step="0.01" required><br>
        <select name="unit" id="unit" required>
            <option value="">Select Unit</option>
        </select><br>
        <button type="submit" name="add_product">Add Product</button>
    </form>

    <h3>Product List</h3>
    <table>
        <thead>
            <tr>
                <th>Category</th>
                <th>Name</th>
                <th>Brand</th>
                <th>Type</th>
                <th>Price</th>
                <th>Quantity</th>
                <th>Unit</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if ($productResult->num_rows > 0) {
                while ($productRow = $productResult->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($productRow['category_name']) . "</td>";
                    echo "<td>" . htmlspecialchars($productRow['name']) . "</td>";
                    echo "<td>" . htmlspecialchars($productRow['brand_name'] ?? 'N/A') . "</td>";
                    echo "<td>" . htmlspecialchars($productRow['type'] ?? 'N/A') . "</td>";
                    echo "<td>" . number_format($productRow['price'], 2) . "</td>";
                    echo "<td>" . htmlspecialchars($productRow['quantity']) . "</td>";
                    echo "<td>" . htmlspecialchars($productRow['unit'] ?? 'N/A') . "</td>";
                    echo "<td>";
                    echo "<button onclick=\"editProduct(" . $productRow['id'] . ", " . $productRow['category_id'] . ", '" . htmlspecialchars($productRow['name']) . "', '" . htmlspecialchars($productRow['brand_name'] ?? '') . "', '" . htmlspecialchars($productRow['type'] ?? '') . "', " . $productRow['price'] . ", " . $productRow['quantity'] . ", '" . htmlspecialchars($productRow['unit'] ?? '') . "')\">Edit</button> | ";
                    echo "<form method='post' style='display:inline;'>";
                    echo "<input type='hidden' name='id' value='" . $productRow['id'] . "'>";
                    echo "<button type='submit' name='delete_product'>Delete</button>";
                    echo "</form>";
                    echo "</td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='8'>No products found</td></tr>";
            }
            ?>
        </tbody>
    </table>

    <div id="edit_product_form" style="display:none;">
        <h3>Edit Product</h3>
        <form method="post">
            <input type="hidden" name="id" id="edit_product_id">
            <select name="category_id" id="edit_category_id" required onchange="updateEditUnitDropdown()">
                <option value="">Select Category</option>
                <?php
                $categoryResult->data_seek(0);
                if ($categoryResult->num_rows > 0) {
                    while ($categoryRow = $categoryResult->fetch_assoc()) {
                        echo "<option value='" . $categoryRow['id'] . "'>" . htmlspecialchars($categoryRow['name']) . "</option>";
                    }
                }
                ?>
            </select><br>
            <input type="text" name="name" id="edit_name" placeholder="Product Name" required><br>
            <input type="text" name="brand_name" id="edit_brand_name" placeholder="Brand Name" required><br>
            <input type="text" name="type" id="edit_type" placeholder="Type"><br>
            <input type="number" name="price" id="edit_price" placeholder="Price" step="0.01" required><br>
            <input type="number" name="quantity" id="edit_quantity" placeholder="Quantity" step="0.01" required><br>
            <select name="unit" id="edit_unit" required>
                <option value="">Select Unit</option>
            </select><br>
            <button type="submit" name="update_product">Update Product</button>
            <button type="button" onclick="document.getElementById('edit_product_form').style.display='none';">Cancel</button>
        </form>
    </div>
</div>

<script>
    // Category mapping from PHP
    const categoryNames = <?php echo json_encode($categoryNames); ?>;

    console.log("Category Names Mapping:", categoryNames); // Debug: Check the mapping

    function updateUnitDropdown() {
        console.log("updateUnitDropdown called"); // Debug: Check if function is called
        var categoryId = document.getElementById('category_id').value;
        console.log("Selected Category ID:", categoryId); // Debug: Check selected category ID
        var unitDropdown = document.getElementById('unit');
        unitDropdown.innerHTML = '<option value="">Select Unit</option>';

        if (!categoryId || !categoryNames[categoryId]) {
            console.log("No category selected or category not found for category ID:", categoryId);
            return; // No category selected or category not found
        }

        var categoryName = categoryNames[categoryId].toLowerCase().trim();
        console.log("Category Name:", categoryName); // Debug: Check category name

        if (categoryName === 'cement') {
            var units = ['bags', 'kg', 'gram'];
        } else if (categoryName === 'rod') {
            var units = ['ton', 'piece', 'inches'];
        } else {
            console.log("Unrecognized category:", categoryName);
            return; // No units if category is not recognized
        }

        console.log("Units to populate:", units); // Debug: Check units array
        units.forEach(function(unit) {
            var option = document.createElement('option');
            option.value = unit;
            option.text = unit;
            unitDropdown.appendChild(option);
        });
    }

    function updateEditUnitDropdown() {
        console.log("updateEditUnitDropdown called"); // Debug: Check if function is called
        var categoryId = document.getElementById('edit_category_id').value;
        console.log("Selected Edit Category ID:", categoryId); // Debug: Check selected category ID
        var unitDropdown = document.getElementById('edit_unit');
        unitDropdown.innerHTML = '<option value="">Select Unit</option>';

        if (!categoryId || !categoryNames[categoryId]) {
            console.log("No category selected or category not found for category ID:", categoryId);
            return; // No category selected or category not found
        }

        var categoryName = categoryNames[categoryId].toLowerCase().trim();
        console.log("Edit Category Name:", categoryName); // Debug: Check category name

        if (categoryName === 'cement') {
            var units = ['bags', 'kg', 'gram'];
        } else if (categoryName === 'rod') {
            var units = ['ton', 'piece', 'inches'];
        } else {
            console.log("Unrecognized category:", categoryName);
            return; // No units if category is not recognized
        }

        console.log("Edit Units to populate:", units); // Debug: Check units array
        units.forEach(function(unit) {
            var option = document.createElement('option');
            option.value = unit;
            option.text = unit;
            unitDropdown.appendChild(option);
        });

        // Pre-select the unit if editing
        var currentUnit = document.getElementById('edit_unit').dataset.currentUnit;
        console.log("Current Unit to pre-select:", currentUnit); // Debug: Check current unit
        if (currentUnit) {
            unitDropdown.value = currentUnit;
        }
    }

    function editCategory(id, name) {
        document.getElementById('edit_category_form').style.display = 'block';
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_name').value = name;
    }

    function editProduct(id, category_id, name, brand_name, type, price, quantity, unit) {
        console.log("editProduct called with unit:", unit); // Debug: Check unit passed to edit
        document.getElementById('edit_product_form').style.display = 'block';
        document.getElementById('edit_product_id').value = id;
        document.getElementById('edit_category_id').value = category_id;
        document.getElementById('edit_name').value = name;
        document.getElementById('edit_brand_name').value = brand_name;
        document.getElementById('edit_type').value = type;
        document.getElementById('edit_price').value = price;
        document.getElementById('edit_quantity').value = quantity;

        document.getElementById('edit_unit').dataset.currentUnit = unit;

        updateEditUnitDropdown();
    }
</script>

<style>
.section {
    background-color: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    margin-bottom: 20px;
}

.category-form, .product-form {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.category-form input, .product-form input, .product-form select {
    flex: 1 1 200px;
}

.category-form button, .product-form button {
    background-color: #4CAF50;
    color: white;
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}

.category-form button:hover, .product-form button:hover {
    background-color: #45a049;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 20px;
}

th, td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #ddd;
}

th {
    background-color: #4CAF50;
    color: white;
}

tr:nth-child(even) {
    background-color: #f9f9f9;
}

tr:hover {
    background-color: #f1f1f1;
}

button {
    padding: 8px 16px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}

button[type="submit"] {
    background-color: #d32f2f;
    color: white;
}

button[type="submit"]:hover {
    background-color: #b71c1c;
}

.success {
    color: green;
    background-color: #e0f7fa;
    padding: 10px;
    border-radius: 4px;
    margin-bottom: 20px;
}

.error {
    color: #d32f2f;
    background-color: #ffebee;
    padding: 10px;
    border-radius: 4px;
    margin-bottom: 20px;
}
</style>

<?php include '../includes/footer.php'; ?>