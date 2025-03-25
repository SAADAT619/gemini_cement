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

// Handle sales and invoice details
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['add_sale'])) {
        $customer_id = sanitizeInput($_POST['customer_id']);
        $product_id = sanitizeInput($_POST['product_id']);
        $quantity = sanitizeInput($_POST['quantity']);
        $price = sanitizeInput($_POST['price']);
        $paid = sanitizeInput($_POST['paid']);
        $sale_date = sanitizeInput($_POST['sale_date']);
        $payment_method = sanitizeInput($_POST['payment_method']);
        $invoice_number = generateInvoiceNumber();

        $total = $quantity * $price;
        $due = $total - $paid;

        $sql = "INSERT INTO sales (customer_id, product_id, quantity, price, total, paid, due, sale_date, payment_method, invoice_number)
                VALUES ($customer_id, $product_id, $quantity, $price, $total, $paid, $due, '$sale_date', '$payment_method', '$invoice_number')";

        if ($conn->query($sql) === TRUE) {
            $message = "Sale added successfully. Invoice Number: $invoice_number";

             //update product quantity
             $updateSql = "UPDATE products SET quantity = quantity - $quantity WHERE id = $product_id";
             $conn->query($updateSql);

        } else {
            $error = "Error adding sale: " . $conn->error;
        }
    }  elseif (isset($_POST['delete_sale'])) {
        $id = sanitizeInput($_POST['id']);
        $sql = "DELETE FROM sales WHERE id=$id";
        if ($conn->query($sql) === TRUE) {
            $message = "Sale deleted successfully";
        } else {
            $error = "Error deleting sale: " . $conn->error;
        }
    }  elseif (isset($_POST['update_sale'])) {
        $id = sanitizeInput($_POST['id']);
        $customer_id = sanitizeInput($_POST['customer_id']);
        $product_id = sanitizeInput($_POST['product_id']);
        $quantity = sanitizeInput($_POST['quantity']);
        $price = sanitizeInput($_POST['price']);
        $paid = sanitizeInput($_POST['paid']);
        $sale_date = sanitizeInput($_POST['sale_date']);
        $payment_method = sanitizeInput($_POST['payment_method']);

        $total = $quantity * $price;
        $due = $total - $paid;

        $sql = "UPDATE sales SET customer_id=$customer_id, product_id=$product_id, quantity=$quantity, price=$price, total=$total, paid=$paid, due=$due, sale_date='$sale_date', payment_method='$payment_method' WHERE id=$id";

        if ($conn->query($sql) === TRUE) {
            $message = "Sale updated successfully";
        } else {
            $error = "Error updating sale: " . $conn->error;
        }
    }
}



// Fetch customers for dropdown
$customerSql = "SELECT * FROM customers";
$customerResult = $conn->query($customerSql);

// Fetch products for dropdown
$productSql = "SELECT * FROM products";
$productResult = $conn->query($productSql);

// Fetch sales for listing
$salesSql = "SELECT sales.*, customers.name as customer_name, products.name as product_name 
             FROM sales 
             LEFT JOIN customers ON sales.customer_id = customers.id 
             LEFT JOIN products ON sales.product_id = products.id
             ORDER BY sales.sale_date DESC";  // Order by sale date
$salesResult = $conn->query($salesSql);


?>

<h2>Sell Products</h2>

<?php if (isset($message)) {
    echo "<p class='success'>$message</p>";
} ?>
<?php if (isset($error)) {
    echo "<p class='error'>$error</p>";
} ?>

<form method="post">
    <select name="customer_id" id="customer_id" required onchange="getCustomerDetails(this.value)">
        <option value="">Select Customer</option>
        <?php
        if ($customerResult->num_rows > 0) {
            while ($customerRow = $customerResult->fetch_assoc()) {
                echo "<option value='" . $customerRow['id'] . "'>" . $customerRow['name'] . "</option>";
            }
        }
        ?>
    </select><br>

    <div id="customer_details" style="display:none;">
        <strong>Name:</strong> <span id="customer_name"></span><br>
        <strong>Phone:</strong> <span id="customer_phone"></span><br>
        <strong>Address:</strong> <span id="customer_address"></span><br>
    </div>

    <select name="product_id" id="product_id" required>
        <option value="">Select Product</option>
        <?php
        if ($productResult->num_rows > 0) {
            while ($productRow = $productResult->fetch_assoc()) {
                echo "<option value='" . $productRow['id'] . "'>" . $productRow['name'] . "</option>";
            }
        }
        ?>
    </select><br>

    <input type="number" name="quantity" id="quantity" placeholder="Quantity" required oninput="calculateTotal()"><br>
    <input type="number" name="price" id="price" placeholder="Price" required oninput="calculateTotal()"><br>
    <strong>Total:</strong> <span id="total">0</span><br>
    <input type="number" name="paid" id="paid" placeholder="Paid Amount" required oninput="calculateDue()"><br>
    <strong>Due:</strong> <span id="due">0</span><br>
    <input type="date" name="sale_date" required><br>
     <select name="payment_method" id="payment_method" required>
        <option value="">Select Payment Method</option>
        <option value="cash">Cash</option>
        <option value="credit_card">Credit Card</option>
        <option value="bank_transfer">Bank Transfer</option>
        <option value="other">Other</option>
    </select><br>
    <button type="submit" name="add_sale">Add Sale</button>
</form>

<hr>

<h3>Sales List</h3>
<table>
    <thead>
        <tr>
            <th>Invoice #</th>
            <th>Customer</th>
            <th>Product</th>
            <th>Quantity</th>
            <th>Price</th>
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
                echo "<td>" . $saleRow['product_name'] . "</td>";
                echo "<td>" . $saleRow['quantity'] . "</td>";
                echo "<td>" . $saleRow['price'] . "</td>";
                echo "<td>" . $saleRow['total'] . "</td>";
                echo "<td>" . $saleRow['paid'] . "</td>";
                echo "<td>" . $saleRow['due'] . "</td>";
                echo "<td>" . $saleRow['sale_date'] . "</td>";
                echo "<td>" . $saleRow['payment_method'] . "</td>";
                echo "<td>";
                echo "<button onclick=\"generateInvoice('" . $saleRow['invoice_number'] . "')\">Invoice</button> | ";
                echo "<button onclick=\"editSale(" . $saleRow['id'] . ", " . $saleRow['customer_id'] . ", " . $saleRow['product_id'] . ", " . $saleRow['quantity'] . ", " . $saleRow['price'] . ", " . $saleRow['paid'] . ", '" . $saleRow['sale_date'] . "', '" . $saleRow['payment_method'] . "')\">Edit</button> | ";
                echo "<form method='post' style='display:inline;'><input type='hidden' name='id' value='" . $saleRow['id'] . "'><button type='submit' name='delete_sale'>Delete</button></form>";
                echo "</td>";
                echo "</tr>";
            }
        } else {
            echo "<tr><td colspan='11'>No sales found</td></tr>";
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
            if ($customerResult->num_rows > 0) {
                while ($customerRow = $customerResult->fetch_assoc()) {
                    echo "<option value='" . $customerRow['id'] . "'>" . $customerRow['name'] . "</option>";
                }
            }
            ?>
        </select><br>
        <select name="product_id" id="edit_product_id" required>
            <option value="">Select Product</option>
            <?php
            if ($productResult->num_rows > 0) {
                while ($productRow = $productResult->fetch_assoc()) {
                     echo "<option value='" . $productRow['id'] . "'>" . $productRow['name'] . "</option>";
                }
            }
            ?>
        </select><br>
        <input type="number" name="quantity" id="edit_quantity" placeholder="Quantity" required oninput="calculateTotal(true)"><br>
        <input type="number" name="price" id="edit_price" placeholder="Price" required oninput="calculateTotal(true)"><br>
        <strong>Total:</strong> <span id="edit_total">0</span><br>
        <input type="number" name="paid" id="edit_paid" placeholder="Paid Amount" required oninput="calculateDue(true)"><br>
         <strong>Due:</strong> <span id="edit_due">0</span><br>
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
    function getCustomerDetails(customerId) {
        var xhttp = new XMLHttpRequest();
        xhttp.onreadystatechange = function() {
            if (this.readyState == 4 && this.status == 200) {
                var response = JSON.parse(this.responseText);
                if (response.error) {
                    alert(response.error);
                    document.getElementById('customer_details').style.display = 'none';
                } else {
                    document.getElementById('customer_name').innerHTML = response.name;
                    document.getElementById('customer_phone').innerHTML = response.phone;
                    document.getElementById('customer_address').innerHTML = response.address;
                    document.getElementById('customer_details').style.display = 'block';
                }
            }
        };
        xhttp.open("GET", "get_customer_details.php?id=" + customerId, true);
        xhttp.send();
    }

    function calculateTotal(isEdit = false) {
        var quantityId = isEdit ? 'edit_quantity' : 'quantity';
        var priceId = isEdit ? 'edit_price' : 'price';
        var totalId = isEdit ? 'edit_total' : 'total';

        var quantity = document.getElementById(quantityId).value;
        var price = document.getElementById(priceId).value;
        var total = quantity * price;
        document.getElementById(totalId).innerHTML = total.toFixed(2);
        calculateDue(isEdit); // Recalculate due whenever total changes
    }

    function calculateDue(isEdit = false) {
        var totalId = isEdit ? 'edit_total' : 'total';
        var paidId = isEdit ? 'edit_paid' : 'paid';
        var dueId = isEdit ? 'edit_due' : 'due';
        
        var total = parseFloat(document.getElementById(totalId).innerHTML);
        var paid = document.getElementById(paidId).value;
        var due = total - paid;
        document.getElementById(dueId).innerHTML = due.toFixed(2);
    }

    function generateInvoice(invoiceNumber) {
       // Redirect to invoice.php with the invoice number
        window.location.href = "invoice.php?invoice_number=" + invoiceNumber;
    }

function editSale(id, customer_id, product_id, quantity, price, paid, sale_date, payment_method) {
    document.getElementById('edit_sale_form').style.display = 'block';
    document.getElementById('edit_sale_id').value = id;

    // Populate customer and product dropdowns
    populateCustomerDropdown(customer_id);
    populateProductDropdown(product_id);

    document.getElementById('edit_quantity').value = quantity;
    document.getElementById('edit_price').value = price;
    document.getElementById('edit_paid').value = paid;
    document.getElementById('edit_sale_date').value = sale_date;
    document.getElementById('edit_payment_method').value = payment_method;

    calculateTotal(true);
}

function populateCustomerDropdown(selectedCustomerId) {
    var customerDropdown = document.getElementById("edit_customer_id");
    customerDropdown.innerHTML = ''; // Clear existing options

    <?php
    $customerSql = "SELECT * FROM customers";
    $customerResult = $conn->query($customerSql);
    if ($customerResult->num_rows > 0) {
        while ($customerRow = $customerResult->fetch_assoc()) {
            echo "var option = document.createElement('option');";
            echo "option.value = '" . $customerRow['id'] . "';";
            echo "option.text = '" . $customerRow['name'] . "';";
            echo "if (" . $customerRow['id'] . " == selectedCustomerId) {";
            echo "    option.selected = true;";
            echo "}";
            echo "customerDropdown.appendChild(option);";
        }
    }
    ?>
}

function populateProductDropdown(selectedProductId) {
    var productDropdown = document.getElementById("edit_product_id");
    productDropdown.innerHTML = ''; // Clear existing options

    <?php
    $productSql = "SELECT * FROM products";
    $productResult = $conn->query($productSql);
    if ($productResult->num_rows > 0) {
        while ($productRow = $productResult->fetch_assoc()) {
            echo "var option = document.createElement('option');";
            echo "option.value = '" . $productRow['id'] . "';";
            echo "option.text = '" . $productRow['name'] . "';";
            echo "if (" . $productRow['id'] . " == selectedProductId) {";
            echo "    option.selected = true;";
            echo "}";
            echo "productDropdown.appendChild(option);";
        }
    }
    ?>
}

</script>

<?php include '../includes/footer.php'; ?>