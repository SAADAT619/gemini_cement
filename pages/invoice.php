<?php
session_start();
if (!isset($_SESSION['user_email'])) {
    header("Location: login.php");
    exit();
}
include '../config/database.php';
include '../core/functions.php';
include '../includes/header.php';

// Check if invoice_number is provided
if (!isset($_GET['invoice_number']) || empty($_GET['invoice_number'])) {
    die("Invoice number not provided.");
}

$invoice_number = sanitizeInput($_GET['invoice_number']);

// Fetch purchase details (main purchase record)
$purchaseSql = "SELECT p.*, s.name as seller_name, s.phone as seller_phone, s.address as seller_address 
                FROM purchases p 
                LEFT JOIN sellers s ON p.seller_id = s.id 
                WHERE p.invoice_number = ? AND p.product_id IS NULL";
$stmt = $conn->prepare($purchaseSql);
$stmt->bind_param("s", $invoice_number);
$stmt->execute();
$purchaseResult = $stmt->get_result();

if ($purchaseResult->num_rows == 0) {
    die("Invoice not found.");
}

$purchase = $purchaseResult->fetch_assoc();

// Fetch purchased products
$itemsSql = "SELECT p.*, pr.name as product_name, pr.brand_name, pr.type as product_type 
             FROM purchases p 
             LEFT JOIN products pr ON p.product_id = pr.id 
             WHERE p.invoice_number = ? AND p.product_id IS NOT NULL";
$stmt = $conn->prepare($itemsSql);
$stmt->bind_param("s", $invoice_number);
$stmt->execute();
$itemsResult = $stmt->get_result();

// Fetch shop settings
$shop_name = getShopSetting('shop_name', $conn);
$shop_address = getShopSetting('shop_address', $conn);
$shop_phone = getShopSetting('shop_phone', $conn);
?>

<h2>Invoice</h2>

<div class="invoice">
    <div class="invoice-header">
        <h1><?php echo htmlspecialchars($shop_name); ?></h1>
        <p>Address: <?php echo htmlspecialchars($shop_address); ?></p>
        <p>Phone: <?php echo htmlspecialchars($shop_phone); ?></p>
    </div>

    <div class="invoice-details">
        <div class="invoice-info">
            <h3>Invoice Number: <?php echo htmlspecialchars($invoice_number); ?></h3>
            <p>Date: <?php echo htmlspecialchars($purchase['purchase_date']); ?></p>
        </div>
        <div class="seller-info">
            <h3>Seller Information</h3>
            <p>Name: <?php echo htmlspecialchars($purchase['seller_name'] ?? 'N/A'); ?></p>
            <p>Phone: <?php echo htmlspecialchars($purchase['seller_phone'] ?? 'N/A'); ?></p>
            <p>Address: <?php echo htmlspecialchars($purchase['seller_address'] ?? 'N/A'); ?></p>
        </div>
    </div>

    <h3>Purchased Products</h3>
    <table>
        <thead>
            <tr>
                <th>Product Name</th>
                <th>Quantity</th>
                <th>Unit</th>
                <th>Type</th>
                <th>Price</th>
                <th>Subtotal</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if ($itemsResult->num_rows > 0) {
                while ($item = $itemsResult->fetch_assoc()) {
                    $subtotal = $item['quantity'] * $item['price'];
                    $productDisplay = htmlspecialchars($item['product_name'] . " (" . $item['brand_name'] . ", " . ($item['product_type'] ?: 'N/A') . ")");
                    echo "<tr>";
                    echo "<td>" . $productDisplay . "</td>";
                    echo "<td>" . htmlspecialchars($item['quantity']) . "</td>";
                    echo "<td>" . htmlspecialchars($item['unit']) . "</td>";
                    echo "<td>" . htmlspecialchars($item['type'] ?: 'N/A') . "</td>";
                    echo "<td>" . number_format($item['price'], 2) . "</td>";
                    echo "<td>" . number_format($subtotal, 2) . "</td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='6'>No products found for this invoice</td></tr>";
            }
            ?>
        </tbody>
    </table>

    <div class="invoice-summary">
        <p><strong>Total:</strong> <?php echo number_format($purchase['total'], 2); ?></p>
        <p><strong>Paid:</strong> <?php echo number_format($purchase['paid'], 2); ?></p>
        <p><strong>Due:</strong> <?php echo number_format($purchase['due'], 2); ?></p>
        <p><strong>Payment Method:</strong> <?php echo htmlspecialchars(ucfirst($purchase['payment_method'])); ?></p>
    </div>

    <button onclick="window.print()">Print Invoice</button>
</div>

<style>
.invoice {
    background-color: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    margin-bottom: 20px;
}

.invoice-header {
    text-align: center;
    margin-bottom: 20px;
}

.invoice-details {
    display: flex;
    justify-content: space-between;
    margin-bottom: 20px;
}

.invoice-info, .seller-info {
    width: 45%;
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

.invoice-summary {
    text-align: right;
    margin-top: 20px;
}

button {
    background-color: #4CAF50;
    color: white;
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}

button:hover {
    background-color: #45a049;
}

@media print {
    .invoice {
        box-shadow: none;
        border: none;
    }
    button {
        display: none;
    }
    .sidebar, .header {
        display: none;
    }
}
</style>

<?php include '../includes/footer.php'; ?>