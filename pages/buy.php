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

// Handle buy form submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['add_purchase'])) {
        $seller_id = sanitizeInput($_POST['seller_id']);
        $product_id = sanitizeInput($_POST['product_id']);
        $quantity = sanitizeInput($_POST['quantity']);
        $price = sanitizeInput($_POST['price']);
        $paid = sanitizeInput($_POST['paid']);
        $purchase_date = sanitizeInput($_POST['purchase_date']);
        $payment_method = sanitizeInput($_POST['payment_method']);
        $invoice_number = generateInvoiceNumber();

        $total = $quantity * $price;
        $due = $total - $paid;

        $sql = "INSERT INTO purchases (seller_id, product_id, quantity, price, total, paid, due, purchase_date, payment_method, invoice_number)
                VALUES ($seller_id, $product_id, $quantity, $price, $total, $paid, $due, '$purchase_date', '$payment_method', '$invoice_number')";

        if ($conn->query($sql) === TRUE) {
            $message = "Purchase added successfully. Invoice Number: $invoice_number";
            $updateSql = "UPDATE products SET quantity = quantity + $quantity WHERE id = $product_id";
            if($conn->query($updateSql) !== TRUE){
                $error = "Error updating product quantity: " . $conn->error;
            }

            header("Location: buy.php?message=" . urlencode($message));
            exit();
        } else {
            $error = "Error adding purchase: " . $conn->error;
        }
    } elseif (isset($_POST['delete_purchase'])) {
        $id = sanitizeInput($_POST['id']);
        //get product ID before deleting purchase
        $getPurchaseSql = "SELECT product_id, quantity FROM purchases WHERE id = $id";
        $purchaseResult = $conn->query($getPurchaseSql);

        if ($purchaseResult && $purchaseRow = $purchaseResult->fetch_assoc()) {
            $product_id_to_update = $purchaseRow['product_id'];
            $quantity_to_update = $purchaseRow['quantity'];

            $sql = "DELETE FROM purchases WHERE id=$id";
            if ($conn->query($sql) === TRUE) {
                $message = "Purchase deleted successfully";
                //update product quantity
                $updateProductSql = "UPDATE products SET quantity = quantity - $quantity_to_update WHERE id = $product_id_to_update";
                if($conn->query($updateProductSql) !== TRUE){
                    $error = "Error updating product quantity: " . $conn->error;
                }
            } else {
                $error = "Error deleting purchase: " . $conn->error;
            }
        } else {
            $error = "Error deleting purchase: Purchase not found";
        }
    } elseif (isset($_POST['update_purchase'])) {
        $id = sanitizeInput($_POST['id']);
        $seller_id = sanitizeInput($_POST['seller_id']);
        $product_id = sanitizeInput($_POST['product_id']);
        $quantity = sanitizeInput($_POST['quantity']);
        $price = sanitizeInput($_POST['price']);
        $paid = sanitizeInput($_POST['paid']);
        $purchase_date = sanitizeInput($_POST['purchase_date']);
        $payment_method = sanitizeInput($_POST['payment_method']);

        $total = $quantity * $price;
        $due = $total - $paid;

        $sql = "UPDATE purchases SET seller_id=$seller_id, product_id=$product_id, quantity=$quantity, price=$price, total=$total, 
                    paid=$paid, due=$due, purchase_date='$purchase_date', payment_method='$payment_method' WHERE id=$id";

        if ($conn->query($sql) === TRUE) {
            $message = "Purchase updated successfully";
            // Update the product quantity based on the change
            $originalQuantitySql = "SELECT quantity, product_id FROM purchases WHERE id=$id";
            $originalQuantityResult = $conn->query($originalQuantitySql);
            $originalQuantityRow = $originalQuantityResult->fetch_assoc();
            $originalQuantity = $originalQuantityRow['quantity'];
            $originalProductId = $originalQuantityRow['product_id'];

            $quantityDifference = $quantity - $originalQuantity;
            if ($quantityDifference != 0) {
                $updateProductQuantitySql = "UPDATE products SET quantity = quantity + $quantityDifference WHERE id = $originalProductId";
                if($conn->query($updateProductQuantitySql) !== TRUE){
                    $error = "Error updating product quantity: " . $conn->error;
                }
            }
        } else {
            $error = "Error updating purchase: " . $conn->error;
        }
    }
}

// Fetch sellers for dropdown
$sellerSql = "SELECT * FROM sellers";
$sellerResult = $conn->query($sellerSql);

// Fetch products for dropdown
$productSql = "SELECT * FROM products";
$productResult = $conn->query($productSql);

// Fetch purchases for listing
$purchasesSql = "SELECT purchases.*, sellers.name as seller_name, products.name as product_name 
                    FROM purchases 
                    LEFT JOIN sellers ON purchases.seller_id = sellers.id 
                    LEFT JOIN products ON purchases.product_id = products.id
                    ORDER BY purchases.purchase_date DESC";
$purchasesResult = $conn->query($purchasesSql);
?>

<h2>Buy Products</h2>

<?php if (isset($_GET['message'])) {
    $message = urldecode($_GET['message']);
    echo "<p class='success'>$message</p>";
} ?>
<?php if (isset($error)) {
    echo "<p class='error'>$error</p>";
} ?>

<form method="post">
    <select name="seller_id" id="seller_id" required>
        <option value="">Select Seller</option>
        <?php
        if ($sellerResult->num_rows > 0) {
            while ($sellerRow = $sellerResult->fetch_assoc()) {
                echo "<option value='" . $sellerRow['id'] . "'>" . $sellerRow['name'] . "</option>";
            }
        }
        ?>
    </select><br>

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
    <input type="date" name="purchase_date" required><br>
    <select name="payment_method" id="payment_method" required>
        <option value="">Select Payment Method</option>
        <option value="cash">Cash</option>
        <option value="credit_card">Credit Card</option>
        <option value="bank_transfer">Bank Transfer</option>
        <option value="other">Other</option>
    </select><br>
    <button type="submit" name="add_purchase">Add Purchase</button>
</form>

<hr>

<h3>Purchase List</h3>
<table>
    <thead>
        <tr>
            <th>Invoice #</th>
            <th>Seller</th>
            <th>Product</th>
            <th>Quantity</th>
            <th>Price</th>
            <th>Total</th>
            <th>Paid</th>
            <th>Due</th>
            <th>Purchase Date</th>
            <th>Payment Method</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <?php
        if ($purchasesResult->num_rows > 0) {
            while ($purchaseRow = $purchasesResult->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . $purchaseRow['invoice_number'] . "</td>";
                echo "<td>" . $purchaseRow['seller_name'] . "</td>";
                echo "<td>" . $purchaseRow['product_name'] . "</td>";
                echo "<td>" . $purchaseRow['quantity'] . "</td>";
                echo "<td>" . $purchaseRow['price'] . "</td>";
                echo "<td>" . $purchaseRow['total'] . "</td>";
                echo "<td>" . $purchaseRow['paid'] . "</td>";
                echo "<td>" . $purchaseRow['due'] . "</td>";
                echo "<td>" . $purchaseRow['purchase_date'] . "</td>";
                echo "<td>" . $purchaseRow['payment_method'] . "</td>";
                echo "<td>";
                echo "<button onclick=\"generateInvoice('" . $purchaseRow['invoice_number'] . "')\">Invoice</button> | ";
                echo "<button onclick=\"editPurchase(" . $purchaseRow['id'] . ", " . $purchaseRow['seller_id'] . ", " . $purchaseRow['product_id'] . ", " . $purchaseRow['quantity'] . ", " . $purchaseRow['price'] . ", " . $purchaseRow['paid'] . ", '" . $purchaseRow['purchase_date'] . "', '" . $purchaseRow['payment_method'] . "')\">Edit</button> | ";
                echo "<form method='post' style='display:inline;'><input type='hidden' name='id' value='" . $purchaseRow['id'] . "'><button type='submit' name='delete_purchase'>Delete</button></form>";
                echo "</td>";
                echo "</tr>";
            }
        } else {
            echo "<tr><td colspan='11'>No purchases found</td></tr>";
        }
        ?>
    </tbody>
</table>

<div id="edit_purchase_form" style="display:none;">
    <h3>Edit Purchase</h3>
    <form method="post">
        <input type="hidden" name="id" id="edit_purchase_id">
        <select name="seller_id" id="edit_seller_id" required>
            <option value="">Select Seller</option>
            <?php
            if ($sellerResult->num_rows > 0) {
                while ($sellerRow = $sellerResult->fetch_assoc()) {
                    echo "<option value='" . $sellerRow['id'] . "'>" . $sellerRow['name'] . "</option>";
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
        <input type="date" name="purchase_date" id="edit_purchase_date" required><br>
        <select name="payment_method" id="edit_payment_method" required>
            <option value="">Select Payment Method</option>
            <option value="cash">Cash</option>
            <option value="credit_card">Credit Card</option>
            <option value="bank_transfer">Bank Transfer</option>
            <option value="other">Other</option>
        </select><br>
        <button type="submit" name="update_purchase">Update Purchase</button>
        <button type="button" onclick="document.getElementById('edit_purchase_form').style.display='none';">Cancel</button>
    </form>
</div>

<script>
    function calculateTotal(isEdit = false) {
        var quantityId = isEdit ? 'edit_quantity' : 'quantity';
        var priceId = isEdit ? 'edit_price' : 'price';
        var totalId = isEdit ? 'edit_total' : 'total';

        var quantity = document.getElementById(quantityId).value;
        var price = document.getElementById(priceId).value;
        var total = quantity * price;
        document.getElementById(totalId).innerHTML = total.toFixed(2);
        calculateDue(isEdit);
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
        window.location.href = "invoice_buy.php?invoice_number=" + invoiceNumber;
    }

    function editPurchase(id, seller_id, product_id, quantity, price, paid, purchase_date, payment_method) {
        document.getElementById('edit_purchase_form').style.display = 'block';
        document.getElementById('edit_purchase_id').value = id;

        populateSellerDropdown(seller_id);
        populateProductDropdown(product_id);

        document.getElementById('edit_quantity').value = quantity;
        document.getElementById('edit_price').value = price;
        document.getElementById('edit_paid').value = paid;
        document.getElementById('edit_purchase_date').value = purchase_date;
        document.getElementById('edit_payment_method').value = payment_method;

        calculateTotal(true);
    }

    function populateSellerDropdown(selectedSellerId) {
        var sellerDropdown = document.getElementById("edit_seller_id");
        sellerDropdown.innerHTML = '';

        <?php
        $sellerSql = "SELECT * FROM sellers";
        $sellerResult = $conn->query($sellerSql);
        if ($sellerResult->num_rows > 0) {
            while ($sellerRow = $sellerResult->fetch_assoc()) {
                echo "var option = document.createElement('option');";
                echo "option.value = '" . $sellerRow['id'] . "';";
                echo "option.text = '" . $sellerRow['name'] . "';";
                echo "if (" . $sellerRow['id'] . " == selectedSellerId) {";
                echo "  option.selected = true;";
                echo "}";
                echo "sellerDropdown.appendChild(option);";
            }
        }
        ?>
    }

    function populateProductDropdown(selectedProductId) {
        var productDropdown = document.getElementById("edit_product_id");
        productDropdown.innerHTML = '';

        <?php
        $productSql = "SELECT * FROM products";
        $productResult = $conn->query($productSql);
        if ($productResult->num_rows > 0) {
            while ($productRow = $productResult->fetch_assoc()) {
                echo "var option = document.createElement('option');";
                echo "option.value = '" . $productRow['id'] . "';";
                echo "option.text = '" . $productRow['name'] . "';";
                echo "if (" . $productRow['id'] . " == selectedProductId) {";
                echo "  option.selected = true;";
                echo "}";
                echo "productDropdown.appendChild(option);";
            }
        }
        ?>
    }
</script>

<?php include '../includes/footer.php'; ?>
