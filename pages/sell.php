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

// Function to check if stock is sufficient
function checkStockAvailability($conn, $product_id, $quantity) {
    $stmt = $conn->prepare("SELECT quantity FROM products WHERE id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        $product = $result->fetch_assoc();
        $stmt->close();
        return $product['quantity'] >= $quantity;
    }
    $stmt->close();
    return false;
}

// Function to get the total previous due for a customer
function getCustomerPreviousDue($conn, $customer_id) {
    $stmt = $conn->prepare("SELECT SUM(due) as total_due FROM sales WHERE customer_id = ?");
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row['total_due'] ?? 0;
}

// Handle sale form submissions
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_sale'])) {
    $customer_id = sanitizeInput($_POST['customer_id']);
    $sale_date = sanitizeInput($_POST['sale_date']);
    $payment_method = sanitizeInput($_POST['payment_method']);
    $invoice_number = generateInvoiceNumber();
    $paid = floatval(sanitizeInput($_POST['paid']));
    $include_previous_due = isset($_POST['include_previous_due']) ? 1 : 0;

    // Validate inputs
    if (empty($customer_id) || empty($sale_date) || empty($payment_method) || !isset($_POST['product_id']) || !isset($_POST['quantity']) || !isset($_POST['price'])) {
        $error = "Please fill in all required fields.";
    } else {
        // Validate stock availability before proceeding
        $stock_error = false;
        for ($i = 0; $i < count($_POST['product_id']); $i++) {
            $product_id = sanitizeInput($_POST['product_id'][$i]);
            $quantity = floatval(sanitizeInput($_POST['quantity'][$i]));
            if (!checkStockAvailability($conn, $product_id, $quantity)) {
                $stock_error = true;
                $error = "Insufficient stock for product ID $product_id. Requested: $quantity, Available: " . getProductStockById($conn, $product_id)['quantity'];
                break;
            }
        }

        if (!$stock_error) {
            // Start a transaction for atomicity
            $conn->begin_transaction();

            // Get previous due for the customer
            $previous_due = getCustomerPreviousDue($conn, $customer_id);
            $previous_due_to_add = $include_previous_due ? $previous_due : 0;

            // 1. Insert into 'sales' table using prepared statement
            $stmt = $conn->prepare("INSERT INTO sales (customer_id, sale_date, invoice_number, payment_method, paid, total, due) VALUES (?, ?, ?, ?, ?, 0, 0)");
            $stmt->bind_param("isssd", $customer_id, $sale_date, $invoice_number, $payment_method, $paid);
            if ($stmt->execute()) {
                $sale_id = $conn->insert_id; // Get the ID of the new sale
                $total = 0;

                // 2. Insert into 'sale_items' table
                for ($i = 0; $i < count($_POST['product_id']); $i++) {
                    $product_id = sanitizeInput($_POST['product_id'][$i]);
                    $quantity = floatval(sanitizeInput($_POST['quantity'][$i]));
                    $price = floatval(sanitizeInput($_POST['price'][$i]));
                    $subtotal = $quantity * $price;

                    $stmt_items = $conn->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, price, subtotal) VALUES (?, ?, ?, ?, ?)");
                    $stmt_items->bind_param("iidd", $sale_id, $product_id, $quantity, $price, $subtotal);
                    if (!$stmt_items->execute()) {
                        $error = "Error adding sale item: " . $stmt_items->error;
                        $conn->rollback();
                        $stmt_items->close();
                        break;
                    }
                    $stmt_items->close();

                    // 3. Update product quantity
                    $stmt_update = $conn->prepare("UPDATE products SET quantity = GREATEST(quantity - ?, 0) WHERE id = ?");
                    $stmt_update->bind_param("di", $quantity, $product_id);
                    if (!$stmt_update->execute()) {
                        $error = "Error updating product quantity: " . $stmt_update->error;
                        $conn->rollback();
                        $stmt_update->close();
                        break;
                    }
                    $stmt_update->close();

                    $total += $subtotal;
                }

                // 4. Update the 'sales' table with the total and due
                $total_with_previous_due = $total + $previous_due_to_add;
                $due = $total_with_previous_due - $paid;

                $stmt_update_sales = $conn->prepare("UPDATE sales SET total = ?, due = ? WHERE id = ?");
                $stmt_update_sales->bind_param("ddi", $total_with_previous_due, $due, $sale_id);
                if (!$stmt_update_sales->execute()) {
                    $error = "Error updating sale total: " . $stmt_update_sales->error;
                    $conn->rollback();
                } else {
                    // If previous due was included, reset the due of previous sales to 0
                    if ($include_previous_due && $previous_due > 0) {
                        $stmt_reset_due = $conn->prepare("UPDATE sales SET due = 0 WHERE customer_id = ? AND id != ?");
                        $stmt_reset_due->bind_param("ii", $customer_id, $sale_id);
                        if (!$stmt_reset_due->execute()) {
                            $error = "Error resetting previous due: " . $stmt_reset_due->error;
                            $conn->rollback();
                        }
                        $stmt_reset_due->close();
                    }

                    if (!isset($error)) {
                        $conn->commit();
                        $message = "Sale added successfully. Invoice Number: $invoice_number";
                        header("Location: sell.php?message=" . urlencode($message));
                        exit();
                    }
                }
                $stmt_update_sales->close();
            } else {
                $error = "Error adding sale: " . $stmt->error;
                $conn->rollback();
            }
            $stmt->close();
        }
    }
}

// Fetch customers for dropdown
$customerSql = "SELECT * FROM customers";
$customerResult = $conn->query($customerSql);

// Fetch products for dropdown (using getProductStock to include stock data)
$products = getProductStock($conn);

// Fetch sales for initial listing (this will be replaced by AJAX)
$stmt_sales = $conn->prepare("SELECT sales.*, customers.name as customer_name, customers.phone as customer_phone, customers.address as customer_address 
        FROM sales 
        LEFT JOIN customers ON sales.customer_id = customers.id 
        ORDER BY sales.sale_date DESC");
$stmt_sales->execute();
$salesResult = $stmt_sales->get_result();

// Fetch sale items for listing
$stmt_sale_items = $conn->prepare("SELECT sale_items.*, products.name as product_name FROM sale_items LEFT JOIN products ON sale_items.product_id = products.id");
$stmt_sale_items->execute();
$saleItemsResult = $stmt_sale_items->get_result();
?>

<h2>Sell Products</h2>

<?php if (isset($_GET['message'])) {
    $message = urldecode($_GET['message']);
    echo "<p class='success'>$message</p>";
} ?>
<?php if (isset($error)) {
    echo "<p class='error'>$error</p>";
} ?>

<form method="post">
    <select name="customer_id" id="customer_id" required onchange="fetchPreviousDue(this.value)">
        <option value="">Select Customer</option>
        <?php
        if ($customerResult->num_rows > 0) {
            while ($customerRow = $customerResult->fetch_assoc()) {
                echo "<option value='" . $customerRow['id'] . "'>" . $customerRow['name'] . "</option>";
            }
        }
        ?>
    </select><br>

    <div id="previous_due_container" style="display:none;">
        <strong>Previous Due:</strong> <span id="previous_due">0.00</span><br>
        <label><input type="checkbox" name="include_previous_due" id="include_previous_due" onchange="calculateTotal()"> Include Previous Due in This Sale</label><br>
    </div>

    <div id="product_list">
        <div class="product_item">
            <select name="product_id[]" required>
                <option value="">Select Product</option>
                <?php
                if (count($products) > 0) {
                    foreach ($products as $product) {
                        echo "<option value='" . $product['id'] . "'>" . $product['name'] . " (Stock: " . $product['quantity'] . " " . ($product['unit'] ?? '') . ")</option>";
                    }
                }
                ?>
            </select>
            <input type="number" name="quantity[]" placeholder="Quantity" min="0" step="0.01" required oninput="validateInput(this); calculateTotal()">
            <input type="number" name="price[]" placeholder="Price" min="0" step="0.01" required oninput="validateInput(this); calculateTotal()">
            <span>Subtotal: <span class="subtotal">0.00</span></span>
            <button type="button" class="remove_product" onclick="removeProduct(this)">✖</button><br>
        </div>
    </div>
    <button type="button" id="add_product">Add Product</button><br>

    <input type="number" name="paid" id="paid" placeholder="Paid Amount" min="0" step="0.01" required oninput="validateInput(this); calculateDue()"><br>
    <strong>Total:</strong> <span id="total">0.00</span><br>
    <strong>Due:</strong> <span id="due">0.00</span><br>
    <input type="date" name="sale_date" required><br>
    <select name="payment_method" required>
        <option value="">Select Payment Method</option>
        <option value="cash">Cash</option>
        <option value="credit_card">Credit Card</option>
        <option value="bank_transfer">Bank Transfer</option>
        <option value="other">Other</option>
    </select><br>
    <button type="submit" name="add_sale">Add Sale</button>
</form>

<hr>

<h3>Sale List</h3>

<!-- Single Search Form -->
<div class="search-container">
    <input type="text" id="search_input" placeholder="Search by Name, Phone, Address, or Invoice Number" oninput="searchSales()">
</div>

<!-- Sales Table (to be updated dynamically) -->
<table id="sales_table">
    <thead>
        <tr>
            <th>Invoice #</th>
            <th>Customer</th>
            <th>Products</th>
            <th>Total</th>
            <th>Paid</th>
            <th>Due</th>
            <th>Sale Date</th>
            <th>Payment Method</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody id="sales_table_body">
        <?php
        if ($salesResult->num_rows > 0) {
            while ($saleRow = $salesResult->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($saleRow['invoice_number']) . "</td>";
                echo "<td>" . htmlspecialchars($saleRow['customer_name']) . "</td>";
                echo "<td>";
                // Display the products for each sale
                $sale_id = $saleRow['id'];
                $saleItemsResult->data_seek(0);
                while ($saleItemRow = $saleItemsResult->fetch_assoc()) {
                    if ($saleItemRow['sale_id'] == $sale_id) {
                        echo htmlspecialchars($saleItemRow['product_name']) . " (" . $saleItemRow['quantity'] . ")<br>";
                    }
                }
                echo "</td>";
                echo "<td>" . number_format($saleRow['total'], 2) . "</td>";
                echo "<td>" . number_format($saleRow['paid'], 2) . "</td>";
                echo "<td>" . number_format($saleRow['due'], 2) . "</td>";
                echo "<td>" . htmlspecialchars($saleRow['sale_date']) . "</td>";
                echo "<td>" . htmlspecialchars($saleRow['payment_method']) . "</td>";
                echo "<td>";
                echo "<button onclick=\"generateInvoice('" . htmlspecialchars($saleRow['invoice_number']) . "')\">Invoice</button>";
                echo "</td>";
                echo "</tr>";
            }
        } else {
            echo "<tr><td colspan='9'>No sales found</td></tr>";
        }
        ?>
    </tbody>
</table>

<script>
// Function to validate input and ensure non-negative values
function validateInput(input) {
    if (input.value < 0) {
        input.value = 0; // Set to 0 if the user tries to input a negative value
    }
}

// Function to fetch previous due for the selected customer
function fetchPreviousDue(customerId) {
    if (customerId === "") {
        document.getElementById('previous_due_container').style.display = 'none';
        document.getElementById('previous_due').textContent = "0.00";
        document.getElementById('include_previous_due').checked = false;
        calculateTotal();
        return;
    }

    var xhr = new XMLHttpRequest();
    xhr.onreadystatechange = function() {
        if (xhr.readyState == 4) {
            if (xhr.status == 200) {
                try {
                    var response = JSON.parse(xhr.responseText);
                    if (response.error) {
                        alert("Error: " + response.error);
                        return;
                    }

                    var previousDue = parseFloat(response.previous_due) || 0;
                    document.getElementById('previous_due').textContent = previousDue.toFixed(2);
                    document.getElementById('previous_due_container').style.display = previousDue > 0 ? 'block' : 'none';
                    document.getElementById('include_previous_due').checked = false;
                    calculateTotal();
                } catch (e) {
                    alert("Error parsing previous due: " + e.message);
                }
            } else {
                alert("Error fetching previous due: " + xhr.status + " " + xhr.statusText);
            }
        }
    };
    xhr.open("GET", "get_customer_due.php?customer_id=" + customerId, true);
    xhr.send();
}

function calculateTotal() {
    var productItems = document.getElementById('product_list').getElementsByClassName('product_item');
    var total = 0;

    for (var i = 0; i < productItems.length; i++) {
        var quantityInput = productItems[i].querySelector('input[name="quantity[]"]');
        var priceInput = productItems[i].querySelector('input[name="price[]"]');
        var subtotalDisplay = productItems[i].querySelector('.subtotal');

        var quantity = quantityInput.value && quantityInput.value >= 0 ? parseFloat(quantityInput.value) : 0;
        var price = priceInput.value && priceInput.value >= 0 ? parseFloat(priceInput.value) : 0;

        var subtotal = quantity * price;
        subtotalDisplay.textContent = subtotal.toFixed(2);
        total += subtotal;
    }

    // Add previous due if the checkbox is checked
    var includePreviousDue = document.getElementById('include_previous_due').checked;
    var previousDue = parseFloat(document.getElementById('previous_due').textContent) || 0;
    if (includePreviousDue) {
        total += previousDue;
    }

    document.getElementById('total').textContent = total.toFixed(2);
    calculateDue();
}

function calculateDue() {
    var total = parseFloat(document.getElementById('total').textContent) || 0;
    var paid = document.getElementById('paid').value && document.getElementById('paid').value >= 0 ? parseFloat(document.getElementById('paid').value) : 0;
    var due = total - paid;

    document.getElementById('due').textContent = due.toFixed(2);
}

function removeProduct(button) {
    var productItem = button.parentElement;
    productItem.remove();
    calculateTotal();
}

function generateInvoice(invoiceNumber) {
    window.location.href = "invoice_sell.php?invoice_number=" + encodeURIComponent(invoiceNumber);
}

function addProductRow() {
    var productList = document.getElementById('product_list');
    var newProductItem = document.createElement('div');
    newProductItem.className = 'product_item';

    var select = document.createElement('select');
    select.name = 'product_id[]';
    select.required = true;
    <?php
    if (count($products) > 0) {
        foreach ($products as $product) {
            echo "var option = document.createElement('option');";
            echo "option.value = '" . $product['id'] . "';";
            echo "option.text = '" . $product['name'] . " (Stock: " . $product['quantity'] . " " . ($product['unit'] ?? '') . ")';";
            echo "select.appendChild(option);";
        }
    }
    ?>

    var quantityInput = document.createElement('input');
    quantityInput.type = 'number';
    quantityInput.name = 'quantity[]';
    quantityInput.placeholder = 'Quantity';
    quantityInput.min = '0';
    quantityInput.step = '0.01';
    quantityInput.required = true;
    quantityInput.addEventListener('input', function() { validateInput(this); calculateTotal(); });

    var priceInput = document.createElement('input');
    priceInput.type = 'number';
    priceInput.name = 'price[]';
    priceInput.placeholder = 'Price';
    priceInput.min = '0';
    priceInput.step = '0.01';
    priceInput.required = true;
    priceInput.addEventListener('input', function() { validateInput(this); calculateTotal(); });

    var subtotalSpan = document.createElement('span');
    subtotalSpan.innerHTML = "Subtotal: <span class='subtotal'>0.00</span>";

    var removeButton = document.createElement('button');
    removeButton.type = 'button';
    removeButton.className = 'remove_product';
    removeButton.textContent = '✖';
    removeButton.onclick = function() { removeProduct(this); };

    newProductItem.appendChild(select);
    newProductItem.appendChild(quantityInput);
    newProductItem.appendChild(priceInput);
    newProductItem.appendChild(subtotalSpan);
    newProductItem.appendChild(removeButton);
    newProductItem.appendChild(document.createElement('br'));
    productList.appendChild(newProductItem);

    calculateTotal();
}

document.getElementById('add_product').addEventListener('click', function() {
    addProductRow();
});

// Real-time search function
function searchSales() {
    var searchTerm = document.getElementById('search_input').value;

    var xhr = new XMLHttpRequest();
    xhr.onreadystatechange = function() {
        if (xhr.readyState == 4) {
            if (xhr.status == 200) {
                document.getElementById('sales_table_body').innerHTML = xhr.responseText;
            } else {
                console.error("Error fetching sales: " + xhr.status + " " + xhr.statusText);
            }
        }
    };
    xhr.open("GET", "search_sales.php?search=" + encodeURIComponent(searchTerm), true);
    xhr.send();
}
</script>

<style>
.remove_product {
    margin-left: 10px;
    color: red;
    border: none;
    background: none;
    cursor: pointer;
    font-size: 16px;
}
.remove_product:hover {
    color: darkred;
}
input[type="number"] {
    width: 100px;
    padding: 5px;
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
.search-container {
    margin-bottom: 20px;
}
.search-container input {
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
    width: 300px;
}
table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 30px;
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
</style>

<?php
include '../includes/footer.php';

// Close prepared statements
$stmt_sales->close();
$stmt_sale_items->close();
$conn->close();
?>