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

// Fetch categories for dropdown
$categorySql = "SELECT * FROM categories";
$categoryResult = $conn->query($categorySql);

// Fetch products for dropdown
$products = getProductStock($conn);

// Fetch sellers for dropdown
$sellerSql = "SELECT * FROM sellers";
$sellerResult = $conn->query($sellerSql);

// Define unit options for categories
$unitOptions = [
    'Cement' => ['bags', 'kg', 'gram'],
    'Rod' => ['ton', 'piece', 'inches']
];

// Define type options for Rods
$rodTypes = ['8mm', '10mm', '12mm', '16mm', '20mm'];

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_purchase'])) {
    $seller_id = sanitizeInput($_POST['seller_id']);
    $products_data = $_POST['products'];
    $quantities = $_POST['quantities'];
    $prices = $_POST['prices'];
    $units = $_POST['units'];
    $types = isset($_POST['types']) ? $_POST['types'] : [];
    $paid_amount = floatval($_POST['paid_amount']);
    $payment_method = sanitizeInput($_POST['payment_method']);
    $purchase_date = sanitizeInput($_POST['purchase_date']);

    // Validate inputs
    $total = 0;
    $items = [];
    for ($i = 0; $i < count($products_data); $i++) {
        $product_id = (int)$products_data[$i];
        $quantity = floatval($quantities[$i]);
        $price = floatval($prices[$i]);
        $unit = sanitizeInput($units[$i]);
        $type = isset($types[$i]) ? sanitizeInput($types[$i]) : null;
        $subtotal = $quantity * $price;

        if ($product_id <= 0 || $quantity <= 0 || $price <= 0 || empty($unit)) {
            $error = "Invalid product, quantity, price, or unit.";
            break;
        }

        $items[] = [
            'product_id' => $product_id,
            'quantity' => $quantity,
            'price' => $price,
            'unit' => $unit,
            'type' => $type,
            'subtotal' => $subtotal
        ];
        $total += $subtotal;
    }

    if (empty($items)) {
        $error = "No valid products selected.";
    }

    if (!isset($error)) {
        $due = $total - $paid_amount;
        if ($due < 0) {
            $error = "Paid amount cannot exceed total.";
        } else {
            // Generate invoice number
            $invoice_number = generateInvoiceNumber();

            // Begin transaction
            $conn->begin_transaction();
            try {
                // Insert into purchases table (main purchase record)
                $purchaseSql = "INSERT INTO purchases (seller_id, total, paid, due, purchase_date, payment_method, invoice_number, created_at) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
                $stmt = $conn->prepare($purchaseSql);
                $stmt->bind_param("iddsdss", $seller_id, $total, $paid_amount, $due, $purchase_date, $payment_method, $invoice_number);
                $stmt->execute();

                $purchase_id = $conn->insert_id;

                // Insert individual purchase items with product details
                foreach ($items as $item) {
                    $product_id = $item['product_id'];
                    $quantity = $item['quantity'];
                    $price = $item['price'];
                    $unit = $item['unit'];
                    $type = $item['type'];

                    // Update product quantity
                    $updateSql = "UPDATE products SET quantity = quantity + ? WHERE id = ?";
                    $updateStmt = $conn->prepare($updateSql);
                    $updateStmt->bind_param("di", $quantity, $product_id);
                    $updateStmt->execute();

                    // Insert into purchases with unit and type
                    $purchaseItemSql = "INSERT INTO purchases (seller_id, product_id, quantity, price, total, paid, due, purchase_date, payment_method, invoice_number, unit, type, created_at) 
                                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                    $purchaseItemStmt = $conn->prepare($purchaseItemSql);
                    $itemTotal = $quantity * $price;
                    $purchaseItemStmt->bind_param("iiddsdssdsss", $seller_id, $product_id, $quantity, $price, $itemTotal, $paid_amount, $due, $purchase_date, $payment_method, $invoice_number, $unit, $type);
                    $purchaseItemStmt->execute();
                }

                $conn->commit();
                $message = "Purchase recorded successfully. Invoice Number: $invoice_number";
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Error recording purchase: " . $e->getMessage();
            }
        }
    }
}

// Handle delete purchase
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['invoice_number'])) {
    $invoice_number = sanitizeInput($_GET['invoice_number']);
    
    // Begin transaction
    $conn->begin_transaction();
    try {
        // Fetch products to revert stock
        $itemsSql = "SELECT product_id, quantity FROM purchases WHERE invoice_number = ? AND product_id IS NOT NULL";
        $stmt = $conn->prepare($itemsSql);
        $stmt->bind_param("s", $invoice_number);
        $stmt->execute();
        $itemsResult = $stmt->get_result();

        while ($item = $itemsResult->fetch_assoc()) {
            $product_id = $item['product_id'];
            $quantity = $item['quantity'];
            // Revert product quantity
            $updateSql = "UPDATE products SET quantity = quantity - ? WHERE id = ?";
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->bind_param("di", $quantity, $product_id);
            $updateStmt->execute();
        }

        // Delete all records with this invoice_number
        $deleteSql = "DELETE FROM purchases WHERE invoice_number = ?";
        $deleteStmt = $conn->prepare($deleteSql);
        $deleteStmt->bind_param("s", $invoice_number);
        $deleteStmt->execute();

        $conn->commit();
        $message = "Purchase deleted successfully.";
    } catch (Exception $e) {
        $conn->rollback();
        $error = "Error deleting purchase: " . $e->getMessage();
    }
    // Redirect to avoid resubmission
    header("Location: buy.php");
    exit();
}

// Fetch purchase history for display (only main purchase records)
$purchaseSql = "SELECT p.*, s.name as seller_name 
                FROM purchases p 
                LEFT JOIN sellers s ON p.seller_id = s.id 
                WHERE p.product_id IS NULL 
                ORDER BY p.created_at DESC";
$purchaseResult = $conn->query($purchaseSql);
?>

<h2>Add Product</h2>

<?php if (isset($message)) {
    echo "<p class='success'>$message</p>";
} ?>
<?php if (isset($error)) {
    echo "<p class='error'>$error</p>";
} ?>

<form method="post" id="purchaseForm">
    <label for="seller_id">Select Seller:</label>
    <select name="seller_id" id="seller_id" required>
        <option value="">Select Seller</option>
        <?php
        if ($sellerResult->num_rows > 0) {
            while ($sellerRow = $sellerResult->fetch_assoc()) {
                echo "<option value='" . $sellerRow['id'] . "'>" . htmlspecialchars($sellerRow['name']) . "</option>";
            }
        }
        ?>
    </select><br>

    <label for="category_id">Select Category:</label>
    <select name="category_id" id="category_id" onchange="filterProducts()">
        <option value="">Select Category</option>
        <?php
        if ($categoryResult->num_rows > 0) {
            while ($categoryRow = $categoryResult->fetch_assoc()) {
                echo "<option value='" . $categoryRow['id'] . "'>" . htmlspecialchars($categoryRow['name']) . "</option>";
            }
        }
        ?>
    </select><br>

    <h3>Select Product:</h3>
    <div id="product-list">
        <div class="product-row">
            <select name="products[]" class="product-select" onchange="updateProductDetails(this)" required>
                <option value="">Select Product</option>
                <?php
                foreach ($products as $product) {
                    $displayText = htmlspecialchars($product['name'] . " (" . $product['brand_name'] . ", " . ($product['type'] ?: 'N/A') . ") - Stock: " . $product['quantity']);
                    echo "<option value='" . $product['id'] . "' data-price='" . $product['price'] . "' data-category='" . htmlspecialchars($product['category_name']) . "' data-category-id='" . $product['category_id'] . "'>" . $displayText . "</option>";
                }
                ?>
            </select>
            <input type="number" name="quantities[]" placeholder="Quantity" step="0.01" min="0" oninput="updateSubtotal(this)" required>
            <input type="number" name="prices[]" placeholder="Price" step="0.01" min="0" oninput="updateSubtotal(this)" required>
            <select name="units[]" class="unit-select" required>
                <option value="">Select Unit</option>
            </select>
            <div class="type-container" style="display: none;">
                <select name="types[]" class="type-select">
                    <option value="">Select Type</option>
                    <?php
                    foreach ($rodTypes as $type) {
                        echo "<option value='" . htmlspecialchars($type) . "'>" . htmlspecialchars($type) . "</option>";
                    }
                    ?>
                </select>
            </div>
            <span class="subtotal">Subtotal: 0.00</span>
            <button type="button" class="remove-product" onclick="removeProduct(this)">X</button>
        </div>
    </div>
    <button type="button" id="add-product-btn">Add Product</button><br>

    <label>Total: <span id="total">0.00</span></label><br>
    <label for="paid_amount">Paid Amount:</label>
    <input type="number" name="paid_amount" id="paid_amount" step="0.01" min="0" value="0" oninput="updateDue()" required><br>
    <label>Due: <span id="due">0.00</span></label><br>
    <label for="purchase_date">Purchase Date:</label>
    <input type="date" name="purchase_date" id="purchase_date" value="<?php echo date('Y-m-d'); ?>" required><br>
    <label for="payment_method">Payment Method:</label>
    <select name="payment_method" id="payment_method" required>
        <option value="">Select Payment Method</option>
        <option value="cash">Cash</option>
        <option value="bank_transfer">Bank Transfer</option>
        <option value="credit_card">Credit Card</option>
    </select><br>
    <button type="submit" name="add_purchase">Add Purchase</button>
</form>

<h3>Product List</h3>
<input type="text" id="searchInput" placeholder="Search by Name, Phone, Address, or Invoice Number" onkeyup="filterTable()">
<table id="purchaseTable">
    <thead>
        <tr>
            <th>Seller Name</th>
            <th>Invoice Number</th>
            <th>Purchase Date</th>
            <th>Total Stock Purchased</th>
            <th>Total</th>
            <th>Paid</th>
            <th>Due</th>
            <th>Payment Method</th>
            <th>Products</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <?php
        if ($purchaseResult->num_rows > 0) {
            while ($purchaseRow = $purchaseResult->fetch_assoc()) {
                // Fetch products for this purchase
                $invoice_number = $purchaseRow['invoice_number'];
                $itemsSql = "SELECT p.*, pr.name as product_name, pr.brand_name, pr.type as product_type 
                             FROM purchases p 
                             LEFT JOIN products pr ON p.product_id = pr.id 
                             WHERE p.invoice_number = ? AND p.product_id IS NOT NULL";
                $stmt = $conn->prepare($itemsSql);
                $stmt->bind_param("s", $invoice_number);
                $stmt->execute();
                $itemsResult = $stmt->get_result();

                // Calculate total stock purchased
                $totalStockSql = "SELECT SUM(quantity) as total_quantity 
                                  FROM purchases 
                                  WHERE invoice_number = ? AND product_id IS NOT NULL";
                $stockStmt = $conn->prepare($totalStockSql);
                $stockStmt->bind_param("s", $invoice_number);
                $stockStmt->execute();
                $stockResult = $stockStmt->get_result();
                $stockRow = $stockResult->fetch_assoc();
                $totalStock = $stockRow['total_quantity'] ?? 0;

                echo "<tr>";
                echo "<td>" . htmlspecialchars($purchaseRow['seller_name'] ?? 'N/A') . "</td>";
                echo "<td>" . htmlspecialchars($purchaseRow['invoice_number']) . "</td>";
                echo "<td>" . htmlspecialchars($purchaseRow['purchase_date']) . "</td>";
                echo "<td>" . number_format($totalStock, 2) . "</td>";
                echo "<td>" . number_format($purchaseRow['total'], 2) . "</td>";
                echo "<td>" . number_format($purchaseRow['paid'], 2) . "</td>";
                echo "<td>" . number_format($purchaseRow['due'], 2) . "</td>";
                echo "<td>" . htmlspecialchars(ucfirst($purchaseRow['payment_method'])) . "</td>";
                echo "<td>";
                if ($itemsResult->num_rows > 0) {
                    echo "<button onclick=\"toggleProducts('" . $purchaseRow['invoice_number'] . "')\">Show Products</button>";
                    echo "<div id='products-" . $purchaseRow['invoice_number'] . "' style='display:none;'>";
                    echo "<table class='sub-table'>";
                    echo "<thead><tr><th>Product Name</th><th>Quantity</th><th>Price</th><th>Unit</th><th>Type</th><th>Subtotal</th></tr></thead>";
                    echo "<tbody>";
                    while ($item = $itemsResult->fetch_assoc()) {
                        $subtotal = $item['quantity'] * $item['price'];
                        $productDisplay = htmlspecialchars($item['product_name'] . " (" . $item['brand_name'] . ", " . ($item['product_type'] ?: 'N/A') . ")");
                        echo "<tr>";
                        echo "<td>" . $productDisplay . "</td>";
                        echo "<td>" . htmlspecialchars($item['quantity']) . "</td>";
                        echo "<td>" . number_format($item['price'], 2) . "</td>";
                        echo "<td>" . htmlspecialchars($item['unit']) . "</td>";
                        echo "<td>" . htmlspecialchars($item['type'] ?: 'N/A') . "</td>";
                        echo "<td>" . number_format($subtotal, 2) . "</td>";
                        echo "</tr>";
                    }
                    echo "</tbody></table>";
                    echo "</div>";
                } else {
                    echo "No products";
                }
                echo "</td>";
                echo "<td>";
                echo "<a href='invoice.php?invoice_number=" . urlencode($purchaseRow['invoice_number']) . "' target='_blank'><button>View Invoice</button></a> ";
                echo "<a href='update_purchase.php?invoice_number=" . urlencode($purchaseRow['invoice_number']) . "'><button>Update</button></a> ";
                echo "<a href='buy.php?action=delete&invoice_number=" . urlencode($purchaseRow['invoice_number']) . "' onclick=\"return confirm('Are you sure you want to delete this purchase?')\"><button class='delete-btn'>Delete</button></a>";
                echo "</td>";
                echo "</tr>";
            }
        } else {
            echo "<tr><td colspan='10'>No purchases found</td></tr>";
        }
        ?>
    </tbody>
</table>

<script>
const unitOptions = <?php echo json_encode($unitOptions); ?>;
const rodTypes = <?php echo json_encode($rodTypes); ?>;
const allProducts = <?php echo json_encode($products); ?>;

function updateProductDetails(selectElement) {
    const row = selectElement.parentElement;
    const priceInput = row.querySelector('input[name="prices[]"]');
    const unitSelect = row.querySelector('.unit-select');
    const typeContainer = row.querySelector('.type-container');
    const typeSelect = row.querySelector('.type-select');

    // Update price
    const selectedOption = selectElement.options[selectElement.selectedIndex];
    const price = selectedOption.getAttribute('data-price') || 0;
    priceInput.value = parseFloat(price).toFixed(2);

    // Update unit dropdown based on category
    const category = selectedOption.getAttribute('data-category') || '';
    unitSelect.innerHTML = '<option value="">Select Unit</option>';
    if (unitOptions[category]) {
        unitOptions[category].forEach(unit => {
            const option = document.createElement('option');
            option.value = unit;
            option.textContent = unit;
            unitSelect.appendChild(option);
        });
    } else {
        unitSelect.innerHTML = '<option value="">Select Unit</option>';
    }

    // Show/hide type dropdown for Rods
    if (category === 'Rod') {
        typeContainer.style.display = 'block';
        typeSelect.setAttribute('required', 'required');
    } else {
        typeContainer.style.display = 'none';
        typeSelect.removeAttribute('required');
        typeSelect.value = '';
    }

    updateSubtotal(priceInput);
}

function filterProducts() {
    const categoryId = document.getElementById('category_id').value;
    const productSelects = document.querySelectorAll('.product-select');

    productSelects.forEach(select => {
        const currentValue = select.value;
        select.innerHTML = '<option value="">Select Product</option>';
        
        allProducts.forEach(product => {
            if (!categoryId || product.category_id == categoryId) {
                const displayText = `${product.name} (${product.brand_name}, ${product.type || 'N/A'}) - Stock: ${product.quantity}`;
                const option = document.createElement('option');
                option.value = product.id;
                option.textContent = displayText;
                option.setAttribute('data-price', product.price);
                option.setAttribute('data-category', product.category_name);
                option.setAttribute('data-category-id', product.category_id);
                if (product.id == currentValue) {
                    option.selected = true;
                }
                select.appendChild(option);
            }
        });

        // Trigger updateProductDetails to refresh unit and type dropdowns
        updateProductDetails(select);
    });
}

function updateSubtotal(element) {
    const row = element.parentElement;
    const quantity = parseFloat(row.querySelector('input[name="quantities[]"]').value) || 0;
    const price = parseFloat(row.querySelector('input[name="prices[]"]').value) || 0;
    const subtotal = quantity * price;
    row.querySelector('.subtotal').textContent = `Subtotal: ${subtotal.toFixed(2)}`;
    updateTotal();
}

function updateTotal() {
    const subtotals = document.querySelectorAll('.subtotal');
    let total = 0;
    subtotals.forEach(subtotal => {
        const value = parseFloat(subtotal.textContent.replace('Subtotal: ', '')) || 0;
        total += value;
    });
    document.getElementById('total').textContent = total.toFixed(2);
    updateDue();
}

function updateDue() {
    const total = parseFloat(document.getElementById('total').textContent) || 0;
    const paid = parseFloat(document.getElementById('paid_amount').value) || 0;
    const due = total - paid;
    document.getElementById('due').textContent = due.toFixed(2);
}

function removeProduct(button) {
    const productRows = document.querySelectorAll('.product-row');
    if (productRows.length > 1) {
        button.parentElement.remove();
        updateTotal();
    }
}

document.getElementById('add-product-btn').addEventListener('click', function() {
    const productList = document.getElementById('product-list');
    const newRow = document.createElement('div');
    newRow.className = 'product-row';
    newRow.innerHTML = `
        <select name="products[]" class="product-select" onchange="updateProductDetails(this)" required>
            <option value="">Select Product</option>
            <?php
            foreach ($products as $product) {
                $displayText = htmlspecialchars($product['name'] . " (" . $product['brand_name'] . ", " . ($product['type'] ?: 'N/A') . ") - Stock: " . $product['quantity']);
                echo "<option value='" . $product['id'] . "' data-price='" . $product['price'] . "' data-category='" . htmlspecialchars($product['category_name']) . "' data-category-id='" . $product['category_id'] . "'>" . $displayText . "</option>";
            }
            ?>
        </select>
        <input type="number" name="quantities[]" placeholder="Quantity" step="0.01" min="0" oninput="updateSubtotal(this)" required>
        <input type="number" name="prices[]" placeholder="Price" step="0.01" min="0" oninput="updateSubtotal(this)" required>
        <select name="units[]" class="unit-select" required>
            <option value="">Select Unit</option>
        </select>
        <div class="type-container" style="display: none;">
            <select name="types[]" class="type-select">
                <option value="">Select Type</option>
                <?php
                foreach ($rodTypes as $type) {
                    echo "<option value='" . htmlspecialchars($type) . "'>" . htmlspecialchars($type) . "</option>";
                }
                ?>
            </select>
        </div>
        <span class="subtotal">Subtotal: 0.00</span>
        <button type="button" class="remove-product" onclick="removeProduct(this)">X</button>
    `;
    productList.appendChild(newRow);
    filterProducts(); // Apply category filter to new row
});

function filterTable() {
    const input = document.getElementById('searchInput').value.toLowerCase();
    const table = document.getElementById('purchaseTable');
    const rows = table.getElementsByTagName('tr');

    for (let i = 1; i < rows.length; i++) {
        const cells = rows[i].getElementsByTagName('td');
        let match = false;
        for (let j = 0; j < cells.length - 1; j++) { // Exclude the last column (Action)
            if (cells[j].textContent.toLowerCase().indexOf(input) > -1) {
                match = true;
                break;
            }
        }
        rows[i].style.display = match ? '' : 'none';
    }
}

function toggleProducts(invoiceNumber) {
    const productDiv = document.getElementById('products-' + invoiceNumber);
    const button = event.currentTarget;
    if (productDiv.style.display === 'none') {
        productDiv.style.display = 'block';
        button.textContent = 'Hide Products';
    } else {
        productDiv.style.display = 'none';
        button.textContent = 'Show Products';
    }
}
</script>

<style>
form {
    background-color: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    margin-bottom: 20px;
}

label {
    display: block;
    margin: 10px 0 5px;
}

select, input[type="number"], input[type="date"] {
    width: 100%;
    padding: 10px;
    margin: 5px 0;
    border: 1px solid #ddd;
    border-radius: 4px;
    box-sizing: border-box;
}

.product-row {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
}

.product-row select, .product-row input {
    flex: 1;
}

.product-row .subtotal {
    flex: 0 0 100px;
    font-weight: bold;
}

.product-row .remove-product {
    background-color: #d32f2f;
    color: white;
    border: none;
    padding: 5px 10px;
    border-radius: 4px;
    cursor: pointer;
}

.product-row .remove-product:hover {
    background-color: #b71c1c;
}

#add-product-btn {
    background-color: #2196F3;
    color: white;
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    margin: 10px 0;
}

#add-product-btn:hover {
    background-color: #1e88e5;
}

button[type="submit"] {
    background-color: #4CAF50;
    color: white;
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}

button[type="submit"]:hover {
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

.sub-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
}

.sub-table th, .sub-table td {
    padding: 8px;
    border-bottom: 1px solid #ddd;
}

.sub-table th {
    background-color: #e0e0e0;
    color: #333;
}

#searchInput {
    width: 100%;
    padding: 10px;
    margin-bottom: 20px;
    border: 1px solid #ddd;
    border-radius: 4px;
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

.delete-btn {
    background-color: #d32f2f;
    color: white;
}

.delete-btn:hover {
    background-color: #b71c1c;
}
</style>

<?php include '../includes/footer.php'; ?>