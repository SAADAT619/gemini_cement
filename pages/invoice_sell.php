<?php
session_start();
if (!isset($_SESSION['user_email'])) {
    header("Location: login.php");
    exit();
}
include '../config/database.php';
include '../core/functions.php';

// Fetch invoice details based on invoice number
$invoice_number = isset($_GET['invoice_number']) ? sanitizeInput($_GET['invoice_number']) : '';
if (empty($invoice_number)) {
    die("Invalid invoice number");
}

// Fetch sale details
$saleSql = "SELECT sales.*, customers.name as customer_name, customers.phone, customers.address
            FROM sales
            LEFT JOIN customers ON sales.customer_id = customers.id
            WHERE sales.invoice_number = '$invoice_number'";
$saleResult = $conn->query($saleSql);

if (!$saleResult || $saleResult->num_rows == 0) {
    die("Sale not found for invoice number: " . htmlspecialchars($invoice_number));
}
$sale = $saleResult->fetch_assoc();

// Fetch sale items
$saleItemsSql = "SELECT sale_items.*, products.name as product_name, products.unit
                 FROM sale_items
                 LEFT JOIN products ON sale_items.product_id = products.id
                 WHERE sale_items.sale_id = " . $sale['id'];
$saleItemsResult = $conn->query($saleItemsSql);

if (!$saleItemsResult) {
    die("Error fetching sale items: " . $conn->error);
}

// Fetch shop details from the settings table
$shopDetails = [
    'shop_name' => 'Your Shop Name',
    'shop_phone' => 'Your Shop Phone',
    'shop_address' => 'Your Shop Address'
];

// Fetch settings from the database
$settingsSql = "SELECT setting_key, value FROM settings WHERE setting_key IN ('shop_name', 'shop_phone', 'shop_address')";
$settingsResult = $conn->query($settingsSql);

if ($settingsResult && $settingsResult->num_rows > 0) {
    while ($row = $settingsResult->fetch_assoc()) {
        if ($row['setting_key'] == 'shop_name') {
            $shopDetails['shop_name'] = $row['value'];
        } elseif ($row['setting_key'] == 'shop_phone') {
            $shopDetails['shop_phone'] = $row['value'];
        } elseif ($row['setting_key'] == 'shop_address') {
            $shopDetails['shop_address'] = $row['value'];
        }
    }
}

// Handle shop details update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_shop_details'])) {
    $shop_name = sanitizeInput($_POST['shop_name']);
    $shop_phone = sanitizeInput($_POST['shop_phone']);
    $shop_address = sanitizeInput($_POST['shop_address']);

    // Update settings in the database
    $conn->begin_transaction();
    $updateSuccess = true;

    if (!updateShopSetting('shop_name', $shop_name, $conn)) {
        $updateSuccess = false;
    }
    if (!updateShopSetting('shop_phone', $shop_phone, $conn)) {
        $updateSuccess = false;
    }
    if (!updateShopSetting('shop_address', $shop_address, $conn)) {
        $updateSuccess = false;
    }

    if ($updateSuccess) {
        $conn->commit();
        header("Location: invoice_sell.php?invoice_number=" . urlencode($invoice_number));
        exit();
    } else {
        $conn->rollback();
        echo "Error updating shop details: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice - <?php echo htmlspecialchars($invoice_number); ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .invoice-container {
            max-width: 900px;
            margin: 0 auto;
            background-color: #fff;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
        }
        .header {
            background-color: #2e7d32; /* Darker green for header */
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 15px 15px 0 0;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
        }
        .header p {
            margin: 5px 0;
            font-size: 16px;
        }
        .shop-details {
            margin: 20px 0;
            text-align: center;
            border-bottom: 2px solid #2e7d32;
            padding-bottom: 10px;
        }
        .shop-details h2 {
            margin: 0;
            color: #2e7d32;
            font-size: 24px;
        }
        .shop-details p {
            margin: 5px 0;
            color: #555;
            font-size: 14px;
        }
        .invoice-details {
            margin: 20px 0;
            padding: 15px;
            background-color: #e8f5e9; /* Light green background */
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
        }
        .invoice-details p {
            margin: 5px 0;
            font-size: 14px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #4CAF50; /* Green for table header */
            color: white;
            font-weight: bold;
        }
        td {
            font-size: 14px;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9; /* Light gray for alternating rows */
        }
        tr:hover {
            background-color: #f1f1f1; /* Hover effect */
        }
        .totals {
            margin-top: 20px;
            text-align: right;
            padding: 10px;
            background-color: #e8f5e9;
            border-radius: 8px;
        }
        .totals p {
            margin: 5px 0;
            font-weight: bold;
            font-size: 16px;
            color: #2e7d32;
        }
        .edit-shop-details {
            margin: 30px 0;
            padding: 15px;
            background-color: #fff3e0; /* Light orange background */
            border-radius: 8px;
            box-shadow: 0 0 5px rgba(0, 0, 0, 0.1);
        }
        .edit-shop-details h3 {
            margin-top: 0;
            color: #e65100; /* Orange for heading */
        }
        .edit-shop-details input, .edit-shop-details textarea {
            width: 100%;
            padding: 10px;
            margin: 8px 0;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        .edit-shop-details textarea {
            height: 80px;
            resize: vertical;
        }
        .edit-shop-details button {
            background-color: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        .edit-shop-details button:hover {
            background-color: #45a049;
        }
        .print-button {
            text-align: center;
            margin-top: 20px;
        }
        .print-button button {
            background-color: #1976D2; /* Blue for print button */
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        .print-button button:hover {
            background-color: #1565C0;
        }
        @media print {
            .edit-shop-details, .print-button {
                display: none;
            }
            .invoice-container {
                box-shadow: none;
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        <div class="header">
            <h1>Invoice</h1>
            <p>Invoice Number: <?php echo htmlspecialchars($invoice_number); ?></p>
        </div>

        <div class="shop-details">
            <h2><?php echo htmlspecialchars($shopDetails['shop_name']); ?></h2>
            <p>Mobile: <?php echo htmlspecialchars($shopDetails['shop_phone']); ?></p>
            <p>Address: <?php echo htmlspecialchars($shopDetails['shop_address']); ?></p>
        </div>

        <div class="invoice-details">
            <div>
                <p><strong>Customer:</strong> <?php echo htmlspecialchars($sale['customer_name']); ?></p>
                <p><strong>Phone:</strong> <?php echo htmlspecialchars($sale['phone'] ?? 'N/A'); ?></p>
                <p><strong>Address:</strong> <?php echo htmlspecialchars($sale['address'] ?? 'N/A'); ?></p>
                <p><strong>Sale Date:</strong> <?php echo htmlspecialchars($sale['sale_date']); ?></p>
            </div>
            <div>
                <p><strong>Payment Method:</strong> <?php echo htmlspecialchars($sale['payment_method']); ?></p>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Quantity</th>
                    <th>Unit</th>
                    <th>Price</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if ($saleItemsResult->num_rows > 0) {
                    while ($item = $saleItemsResult->fetch_assoc()) {
                        echo "<tr>";
                        echo "<td>" . htmlspecialchars($item['product_name']) . "</td>";
                        echo "<td>" . number_format($item['quantity'], 2) . "</td>";
                        echo "<td>" . htmlspecialchars($item['unit'] ?? 'N/A') . "</td>";
                        echo "<td>" . number_format($item['price'], 2) . "</td>";
                        echo "<td>" . number_format($item['subtotal'], 2) . "</td>";
                        echo "</tr>";
                    }
                } else {
                    echo "<tr><td colspan='5'>No items found for this sale.</td></tr>";
                }
                ?>
            </tbody>
        </table>

        <div class="totals">
            <p>Total: <?php echo number_format($sale['total'], 2); ?></p>
            <p>Paid: <?php echo number_format($sale['paid'], 2); ?></p>
            <p>Due: <?php echo number_format($sale['due'], 2); ?></p>
        </div>

        <div class="edit-shop-details">
            <h3>Edit Shop Details</h3>
            <form method="post">
                <input type="text" name="shop_name" value="<?php echo htmlspecialchars($shopDetails['shop_name']); ?>" placeholder="Shop Name" required>
                <input type="text" name="shop_phone" value="<?php echo htmlspecialchars($shopDetails['shop_phone']); ?>" placeholder="Shop Phone" required>
                <textarea name="shop_address" placeholder="Shop Address" required><?php echo htmlspecialchars($shopDetails['shop_address']); ?></textarea>
                <button type="submit" name="update_shop_details">Update Shop Details</button>
            </form>
        </div>

        <div class="print-button">
            <button onclick="window.print()">Print Invoice</button>
        </div>
    </div>
</body>
</html>