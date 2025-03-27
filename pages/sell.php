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

// Handle sale form submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['add_sale'])) {
        $customer_id = sanitizeInput($_POST['customer_id']);
        $sale_date = sanitizeInput($_POST['sale_date']);
        $payment_method = sanitizeInput($_POST['payment_method']);
        $invoice_number = generateInvoiceNumber();
        $paid = sanitizeInput($_POST['paid']);

        // 1. Insert into 'sales' table
        $sql_sales = "INSERT INTO sales (customer_id, sale_date, invoice_number, payment_method, paid, total, due)
                      VALUES ($customer_id, '$sale_date', '$invoice_number', '$payment_method', $paid, 0, 0)";

        if ($conn->query($sql_sales) === TRUE) {
            $sale_id = $conn->insert_id; // Get the ID of the new sale
            $total = 0;

            // 2. Insert into 'sale_items' table
            for ($i = 0; $i < count($_POST['product_id']); $i++) {
                $product_id = sanitizeInput($_POST['product_id'][$i]);
                $quantity = sanitizeInput($_POST['quantity'][$i]);
                $price = sanitizeInput($_POST['price'][$i]);
                $subtotal = $quantity * $price;

                $sql_sale_items = "INSERT INTO sale_items (sale_id, product_id, quantity, price, subtotal)
                                   VALUES ($sale_id, $product_id, $quantity, $price, $subtotal)";

                if ($conn->query($sql_sale_items) !== TRUE) {
                    $error = "Error adding sale item: " . $conn->error;
                    break; // Stop the loop on error
                }

                // 3. Update product quantity
                $update_product_sql = "UPDATE products SET quantity = quantity - $quantity WHERE id = $product_id";
                if ($conn->query($update_product_sql) !== TRUE) {
                    $error = "Error updating product quantity: " . $conn->error;
                    break; // Stop the loop on error
                }

                $total += $subtotal; // Accumulate the subtotal
            }

            // 4. Update the 'sales' table with the total and due
            $due = $total - $paid;
            $update_sales_total_sql = "UPDATE sales SET total = $total, due = $due WHERE id = $sale_id";
            if ($conn->query($update_sales_total_sql) !== TRUE) {
                $error = "Error updating sale total: " . $conn->error;
            }

            if (!isset($error)) { // Only redirect if there were no errors
                $message = "Sale added successfully. Invoice Number: $invoice_number";
                header("Location: sell.php?message=" . urlencode($message));
                exit();
            }
        } else {
            $error = "Error adding sale: " . $conn->error;
        }
    } elseif (isset($_POST['delete_sale'])) {
        $id = sanitizeInput($_POST['id']);

        // 1. Get product IDs and quantities from 'sale_items'
        $get_sale_items_sql = "SELECT product_id, quantity FROM sale_items WHERE sale_id = $id";
        $sale_items_result = $conn->query($get_sale_items_sql);

        if ($sale_items_result && $sale_items_result->num_rows > 0) {
            while ($sale_item_row = $sale_items_result->fetch_assoc()) {
                $product_id_to_update = $sale_item_row['product_id'];
                $quantity_to_update = $sale_item_row['quantity'];

                // 2. Update product quantity in 'products' table
                $update_product_sql = "UPDATE products SET quantity = quantity + $quantity_to_update WHERE id = $product_id_to_update";
                if ($conn->query($update_product_sql) !== TRUE) {
                    $error = "Error updating product quantity: " . $conn->error;
                    break; // Stop on error
                }
            }

            if (!isset($error)) {
                // 3. Delete the sale and associated sale items. Use a transaction for atomicity
                $conn->begin_transaction();
                $delete_sale_items_sql = "DELETE FROM sale_items WHERE sale_id = $id";
                $delete_sale_sql = "DELETE FROM sales WHERE id = $id";

                if ($conn->query($delete_sale_items_sql) === TRUE && $conn->query($delete_sale_sql) === TRUE) {
                    $conn->commit();
                    $message = "Sale deleted successfully";
                } else {
                    $conn->rollback();
                    $error = "Error deleting sale: " . $conn->error;
                }
            }
        } else {
            $error = "Error deleting sale: Sale not found";
        }
    } elseif (isset($_POST['update_sale'])) {
        $id = sanitizeInput($_POST['id']);
        $customer_id = sanitizeInput($_POST['customer_id']);
        $sale_date = sanitizeInput($_POST['sale_date']);
        $payment_method = sanitizeInput($_POST['payment_method']);
        $paid = sanitizeInput($_POST['paid']);

        $conn->begin_transaction();

        // 1. Delete old sale items
        $delete_sale_items_sql = "DELETE FROM sale_items WHERE sale_id = $id";
        if ($conn->query($delete_sale_items_sql) !== TRUE) {
            $error = "Error deleting previous sale items: " . $conn->error;
            $conn->rollback();
        }

        $total = 0;
        // 2. Insert new sale items and update product quantities
        if (!isset($error)) {
            for ($i = 0; $i < count($_POST['product_id']); $i++) {
                $product_id = sanitizeInput($_POST['product_id'][$i]);
                $quantity = sanitizeInput($_POST['quantity'][$i]);
                $price = sanitizeInput($_POST['price'][$i]);
                $subtotal = $quantity * $price;

                $sql_sale_items = "INSERT INTO sale_items (sale_id, product_id, quantity, price, subtotal)
                                    VALUES ($id, $product_id, $quantity, $price, $subtotal)";
                if ($conn->query($sql_sale_items) !== TRUE) {
                    $error = "Error adding sale items: " . $conn->error;
                    $conn->rollback();
                    break;
                }

                // Update product quantity
                $update_product_sql = "UPDATE products SET quantity = quantity - $quantity WHERE id = $product_id";
                if ($conn->query($update_product_sql) !== TRUE) {
                    $error = "Error updating product quantity: " . $conn->error;
                    $conn->rollback();
                    break;
                }
                $total += $subtotal;
            }
        }
        $due = $total - $paid;
        // 3. Update sale
        if (!isset($error)) {
            $sql_update_sale = "UPDATE sales 
                            SET customer_id = $customer_id, sale_date = '$sale_date', 
                            payment_method = '$payment_method', paid = $paid, total = $total, due = $due
                            WHERE id = $id";

            if ($conn->query($sql_update_sale) === TRUE) {
                $conn->commit();
                $message = "Sale updated successfully";
            } else {
                $conn->rollback();
                $error = "Error updating sale: " . $conn->error;
            }
        }
    }
}

// Fetch customers for dropdown
$customerSql = "SELECT * FROM customers";
$customerResult = $conn->query($customerSql);

// Fetch products for dropdown (using getProductStock to include stock data)
$products = getProductStock($conn);

// Fetch sales for listing
$salesSql = "SELECT sales.*, customers.name as customer_name
            FROM sales
            LEFT JOIN customers ON sales.customer_id = customers.id
            ORDER BY sales.sale_date DESC";
$salesResult = $conn->query($salesSql);

// Fetch sale items for listing
$saleItemsSql = "SELECT sale_items.*, products.name as product_name
                FROM sale_items
                LEFT JOIN products ON sale_items.product_id = products.id";
$saleItemsResult = $conn->query($saleItemsSql);
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
    <select name="customer_id" id="customer_id" required>
        <option value="">Select Customer</option>
        <?php
        if ($customerResult->num_rows > 0) {
            while ($customerRow = $customerResult->fetch_assoc()) {
                echo "<option value='" . $customerRow['id'] . "'>" . $customerRow['name'] . "</option>";
            }
        }
        ?>
    </select><br>

    <div id="product_list">
        <div class="product_item">
            <select name="product_id[]" required>
                <option value="">Select Product</option>
                <?php
                if (count($products) > 0) {
                    foreach ($products as $product) {
                        // Display product name with stock in the dropdown
                        echo "<option value='" . $product['id'] . "'>" . $product['name'] . " (Stock: " . $product['quantity'] . ")</option>";
                    }
                }
                ?>
            </select>
            <input type="number" name="quantity[]" placeholder="Quantity" min="0" step="1" required oninput="validateInput(this); calculateTotal()">
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

<!-- Optional: Uncomment this section if you want to display a separate stock table -->
<!--
<h3>Current Product Stock</h3>
<table>
    <thead>
        <tr>
            <th>Product Name</th>
            <th>Category</th>
            <th>Quantity</th>
            <th>Price</th>
        </tr>
    </thead>
    <tbody>
        <?php
        if (count($products) > 0) {
            foreach ($products as $product) {
                echo "<tr>";
                echo "<td>" . $product['name'] . "</td>";
                echo "<td>" . getCategoryName($product['category_id'], $conn) . "</td>";
                echo "<td>" . $product['quantity'] . "</td>";
                echo "<td>" . $product['price'] . "</td>";
                echo "</tr>";
            }
        } else {
            echo "<tr><td colspan='4'>No products found</td></tr>";
        }
        ?>
    </tbody>
</table>
-->

<hr>

<h3>Sale List</h3>
<table>
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
    <tbody>
        <?php
        if ($salesResult->num_rows > 0) {
            while ($saleRow = $salesResult->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . $saleRow['invoice_number'] . "</td>";
                echo "<td>" . $saleRow['customer_name'] . "</td>";
                echo "<td>";
                // Display the products for each sale
                $sale_id = $saleRow['id'];
                $saleItemsResult->data_seek(0);
                while ($saleItemRow = $saleItemsResult->fetch_assoc()) {
                    if ($saleItemRow['sale_id'] == $sale_id) {
                        echo $saleItemRow['product_name'] . " (" . $saleItemRow['quantity'] . ")<br>";
                    }
                }
                echo "</td>";
                echo "<td>" . $saleRow['total'] . "</td>";
                echo "<td>" . $saleRow['paid'] . "</td>";
                echo "<td>" . $saleRow['due'] . "</td>";
                echo "<td>" . $saleRow['sale_date'] . "</td>";
                echo "<td>" . $saleRow['payment_method'] . "</td>";
                echo "<td>";
                echo "<button onclick=\"generateInvoice('" . $saleRow['invoice_number'] . "')\">Invoice</button> | ";
                echo "<button onclick=\"editSale(" . $saleRow['id'] . ")\">Edit</button> | ";
                echo "<form method='post' style='display:inline;'><input type='hidden' name='id' value='" . $saleRow['id'] . "'><button type='submit' name='delete_sale'>Delete</button></form>";
                echo "</td>";
                echo "</tr>";
            }
        } else {
            echo "<tr><td colspan='9'>No sales found</td></tr>";
        }
        ?>
    </tbody>
</table>

<div id="edit_sale_form" style="display:none;">
    <h3>Edit Sale</h3>
    <form method="post">
        <input type="hidden" name="id" id="edit_sale_id">
        <select name="customer_id" id="edit_customer_id" required>
            <option value="">Select Customer</option>
            <?php
            $customerSql = "SELECT * FROM customers";
            $customerResult = $conn->query($customerSql);
            if ($customerResult->num_rows > 0) {
                while ($customerRow = $customerResult->fetch_assoc()) {
                    echo "<option value='" . $customerRow['id'] . "'>" . $customerRow['name'] . "</option>";
                }
            }
            ?>
        </select><br>

        <div id="edit_product_list">
            <div class="product_item">
                <select name="product_id[]" required>
                    <option value="">Select Product</option>
                    <?php
                    if (count($products) > 0) {
                        foreach ($products as $product) {
                            // Display product name with stock in the dropdown
                            echo "<option value='" . $product['id'] . "'>" . $product['name'] . " (Stock: " . $product['quantity'] . ")</option>";
                        }
                    }
                    ?>
                </select>
                <input type="number" name="quantity[]" placeholder="Quantity" min="0" step="1" required oninput="validateInput(this); calculateTotal(true)">
                <input type="number" name="price[]" placeholder="Price" min="0" step="0.01" required oninput="validateInput(this); calculateTotal(true)">
                <span>Subtotal: <span class="subtotal">0.00</span></span>
                <button type="button" class="remove_product" onclick="removeProduct(this, true)">✖</button><br>
            </div>
        </div>
        <button type="button" id="edit_add_product">Add Product</button><br>

        <input type="number" name="paid" id="edit_paid" placeholder="Paid Amount" min="0" step="0.01" required oninput="validateInput(this); calculateDue(true)"><br>
        <strong>Total:</strong> <span id="edit_total">0.00</span><br>
        <strong>Due:</strong> <span id="edit_due">0.00</span><br>
        <input type="date" name="sale_date" id="edit_sale_date" required><br>
        <select name="payment_method" id="edit_payment_method" required>
            <option value="">Select Payment Method</option>
            <option value="cash">Cash</option>
            <option value="credit_card">Credit Card</option>
            <option value="bank_transfer">Bank Transfer</option>
            <option value="other">Other</option>
        </select><br>
        <button type="submit" name="update_sale">Update Sale</button>
        <button type="button" onclick="document.getElementById('edit_sale_form').style.display='none';">Cancel</button>
    </form>
</div>

<script>
    // Function to validate input and ensure non-negative values
    function validateInput(input) {
        if (input.value < 0) {
            input.value = 0; // Set to 0 if the user tries to input a negative value
        }
    }

    function calculateTotal(isEdit = false) {
        var productListId = isEdit ? 'edit_product_list' : 'product_list';
        var totalId = isEdit ? 'edit_total' : 'total';
        var dueId = isEdit ? 'edit_due' : 'due';
        var paidId = isEdit ? 'edit_paid' : 'paid';

        var productItems = document.getElementById(productListId).getElementsByClassName('product_item');
        var total = 0;

        for (var i = 0; i < productItems.length; i++) {
            var quantityInput = productItems[i].querySelector('input[name="quantity[]"]');
            var priceInput = productItems[i].querySelector('input[name="price[]"]');
            var subtotalDisplay = productItems[i].querySelector('.subtotal');

            // Ensure quantity and price are non-negative
            var quantity = quantityInput.value && quantityInput.value >= 0 ? parseFloat(quantityInput.value) : 0;
            var price = priceInput.value && priceInput.value >= 0 ? parseFloat(priceInput.value) : 0;

            var subtotal = quantity * price;
            subtotalDisplay.textContent = subtotal.toFixed(2);
            total += subtotal;
        }

        document.getElementById(totalId).textContent = total.toFixed(2);
        calculateDue(isEdit);
    }

    function calculateDue(isEdit = false) {
        var totalId = isEdit ? 'edit_total' : 'total';
        var dueId = isEdit ? 'edit_due' : 'due';
        var paidId = isEdit ? 'edit_paid' : 'paid';

        var total = parseFloat(document.getElementById(totalId).textContent) || 0;
        var paid = document.getElementById(paidId).value && document.getElementById(paidId).value >= 0 ? parseFloat(document.getElementById(paidId).value) : 0;
        var due = total - paid;

        document.getElementById(dueId).textContent = due.toFixed(2);
    }

    function removeProduct(button, isEdit = false) {
        var productItem = button.parentElement;
        productItem.remove();
        calculateTotal(isEdit); // Recalculate total after removal
    }

    function generateInvoice(invoiceNumber) {
        window.location.href = "invoice_sell.php?invoice_number=" + invoiceNumber;
    }

    function editSale(id) {
        document.getElementById('edit_sale_form').style.display = 'block';
        document.getElementById('edit_sale_id').value = id;

        // Fetch sale details using AJAX
        var xhr = new XMLHttpRequest();
        xhr.onreadystatechange = function() {
            if (xhr.readyState == 4 && xhr.status == 200) {
                var response = JSON.parse(xhr.responseText);
                populateCustomerDropdown(response.customer_id);
                populateProductDropdown(id); // Pass sale ID to fetch products
                document.getElementById('edit_sale_date').value = response.sale_date;
                document.getElementById('edit_payment_method').value = response.payment_method;
                document.getElementById('edit_paid').value = response.paid;
                document.getElementById('edit_total').textContent = response.total.toFixed(2);
                document.getElementById('edit_due').textContent = (response.total - response.paid).toFixed(2);
            }
        };
        xhr.open("GET", "get_sale_details.php?id=" + id, true);
        xhr.send();
    }

    function populateCustomerDropdown(selectedCustomerId) {
        var customerDropdown = document.getElementById("edit_customer_id");
        customerDropdown.innerHTML = '';
        <?php
        $customerSql = "SELECT * FROM customers";
        $customerResult = $conn->query($customerSql);
        if ($customerResult->num_rows > 0) {
            while ($customerRow = $customerResult->fetch_assoc()) {
                echo "var option = document.createElement('option');";
                echo "option.value = '" . $customerRow['id'] . "';";
                echo "option.text = '" . $customerRow['name'] . "';";
                echo "if (" . $customerRow['id'] . " == selectedCustomerId) {";
                echo " option.selected = true;";
                echo "}";
                echo "customerDropdown.appendChild(option);";
            }
        }
        ?>
    }

    function populateProductDropdown(saleId) {
        var productDropdown = document.getElementById("edit_product_list");
        productDropdown.innerHTML = ''; // Clear previous product list

        // Fetch products associated with the sale
        var xhr = new XMLHttpRequest();
        xhr.onreadystatechange = function() {
            if (xhr.readyState == 4 && xhr.status == 200) {
                var response = JSON.parse(xhr.responseText);
                var products = response.products;

                for (var i = 0; i < products.length; i++) {
                    var product = products[i];
                    var productDiv = document.createElement('div');
                    productDiv.className = 'product_item';

                    var select = document.createElement('select');
                    select.name = 'product_id[]';
                    <?php
                    if (count($products) > 0) {
                        foreach ($products as $product) {
                            echo "var option = document.createElement('option');";
                            echo "option.value = '" . $product['id'] . "';";
                            echo "option.text = '" . $product['name'] . " (Stock: " . $product['quantity'] . ")';";
                            echo "if (" . $product['id'] . " == product.product_id) {";
                            echo "option.selected = true;";
                            echo "}";
                            echo "select.appendChild(option);";
                        }
                    }
                    ?>
                    var quantityInput = document.createElement('input');
                    quantityInput.type = 'number';
                    quantityInput.name = 'quantity[]';
                    quantityInput.placeholder = 'Quantity';
                    quantityInput.min = '0';
                    quantityInput.step = '1';
                    quantityInput.value = product.quantity;
                    quantityInput.required = true;
                    quantityInput.addEventListener('input', function() { validateInput(this); calculateTotal(true); });

                    var priceInput = document.createElement('input');
                    priceInput.type = 'number';
                    priceInput.name = 'price[]';
                    priceInput.placeholder = 'Price';
                    priceInput.min = '0';
                    priceInput.step = '0.01';
                    priceInput.value = product.price;
                    priceInput.required = true;
                    priceInput.addEventListener('input', function() { validateInput(this); calculateTotal(true); });

                    var subtotalSpan = document.createElement('span');
                    subtotalSpan.innerHTML = "Subtotal: <span class='subtotal'>" + product.subtotal.toFixed(2) + "</span>";

                    var removeButton = document.createElement('button');
                    removeButton.type = 'button';
                    removeButton.className = 'remove_product';
                    removeButton.textContent = '✖';
                    removeButton.onclick = function() { removeProduct(this, true); };

                    productDiv.appendChild(select);
                    productDiv.appendChild(quantityInput);
                    productDiv.appendChild(priceInput);
                    productDiv.appendChild(subtotalSpan);
                    productDiv.appendChild(removeButton);
                    productDiv.appendChild(document.createElement('br'));
                    productDropdown.appendChild(productDiv);
                }
                calculateTotal(true);
            }
        };
        xhr.open("GET", "get_sale_items.php?sale_id=" + saleId, true);
        xhr.send();
    }

    document.getElementById('add_product').addEventListener('click', function() {
        var productList = document.getElementById('product_list');
        var newProductItem = document.createElement('div');
        newProductItem.className = 'product_item';

        var select = document.createElement('select');
        select.name = 'product_id[]';
        <?php
        if (count($products) > 0) {
            foreach ($products as $product) {
                echo "var option = document.createElement('option');";
                echo "option.value = '" . $product['id'] . "';";
                echo "option.text = '" . $product['name'] . " (Stock: " . $product['quantity'] . ")';";
                echo "select.appendChild(option);";
            }
        }
        ?>

        var quantityInput = document.createElement('input');
        quantityInput.type = 'number';
        quantityInput.name = 'quantity[]';
        quantityInput.placeholder = 'Quantity';
        quantityInput.min = '0';
        quantityInput.step = '1';
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

        // Recalculate total after adding a new product
        calculateTotal();
    });

    document.getElementById('edit_add_product').addEventListener('click', function() {
        var productList = document.getElementById('edit_product_list');
        var newProductItem = document.createElement('div');
        newProductItem.className = 'product_item';

        var select = document.createElement('select');
        select.name = 'product_id[]';
        <?php
        if (count($products) > 0) {
            foreach ($products as $product) {
                echo "var option = document.createElement('option');";
                echo "option.value = '" . $product['id'] . "';";
                echo "option.text = '" . $product['name'] . " (Stock: " . $product['quantity'] . ")';";
                echo "select.appendChild(option);";
            }
        }
        ?>

        var quantityInput = document.createElement('input');
        quantityInput.type = 'number';
        quantityInput.name = 'quantity[]';
        quantityInput.placeholder = 'Quantity';
        quantityInput.min = '0';
        quantityInput.step = '1';
        quantityInput.required = true;
        quantityInput.addEventListener('input', function() { validateInput(this); calculateTotal(true); });

        var priceInput = document.createElement('input');
        priceInput.type = 'number';
        priceInput.name = 'price[]';
        priceInput.placeholder = 'Price';
        priceInput.min = '0';
        priceInput.step = '0.01';
        priceInput.required = true;
        priceInput.addEventListener('input', function() { validateInput(this); calculateTotal(true); });

        var subtotalSpan = document.createElement('span');
        subtotalSpan.innerHTML = "Subtotal: <span class='subtotal'>0.00</span>";

        var removeButton = document.createElement('button');
        removeButton.type = 'button';
        removeButton.className = 'remove_product';
        removeButton.textContent = '✖';
        removeButton.onclick = function() { removeProduct(this, true); };

        newProductItem.appendChild(select);
        newProductItem.appendChild(quantityInput);
        newProductItem.appendChild(priceInput);
        newProductItem.appendChild(subtotalSpan);
        newProductItem.appendChild(removeButton);
        newProductItem.appendChild(document.createElement('br'));
        productList.appendChild(newProductItem);

        // Recalculate total after adding a new product in edit mode
        calculateTotal(true);
    });
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
</style>

<?php
include '../includes/footer.php';
?>