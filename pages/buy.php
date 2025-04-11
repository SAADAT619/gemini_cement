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

// Debug: Check database connection
if ($conn->connect_error) {
    $error = "Database connection failed: " . $conn->connect_error;
    error_log("Database connection failed in buy.php: " . $conn->connect_error);
}

// Fetch categories for dropdown
$categorySql = "SELECT * FROM categories";
$categoryResult = $conn->query($categorySql);
if ($categoryResult === false) {
    $error = "Error fetching categories: " . $conn->error;
    error_log("Categories query failed in buy.php: " . $conn->error);
}

// Fetch products for dropdown
$products = getProductStock($conn);

// Fetch sellers for dropdown
$sellerSql = "SELECT * FROM sellers";
$sellerResult = $conn->query($sellerSql);
if ($sellerResult === false) {
    $error = "Error fetching sellers: " . $conn->error;
    error_log("Sellers query failed in buy.php: " . $conn->error);
}

// Fetch shop details
$shopSql = "SELECT * FROM shop_details WHERE id = 1"; // Assuming only one shop entry
$shopResult = $conn->query($shopSql);
if ($shopResult === false) {
    $error = "Error fetching shop details: " . $conn->error;
    error_log("Shop details query failed in buy.php: " . $conn->error);
} else {
    $shopDetails = $shopResult->num_rows > 0 ? $shopResult->fetch_assoc() : [
        'shop_name' => 'Gemini Cement Store',
        'address' => '123 Business Avenue, City, Country',
        'phone' => '+123-456-7890',
        'email' => 'contact@geminicement.com'
    ];
}

// Handle shop details update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_shop_details'])) {
    $shop_name = sanitizeInput($_POST['shop_name']);
    $address = sanitizeInput($_POST['address']);
    $phone = sanitizeInput($_POST['phone']);
    $email = sanitizeInput($_POST['email']);

    // Validate inputs
    if (empty($shop_name) || empty($address) || empty($phone) || empty($email)) {
        $error = "All shop details fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } else {
        // Check if shop details exist
        $checkSql = "SELECT id FROM shop_details WHERE id = 1";
        $checkResult = $conn->query($checkSql);
        if ($checkResult->num_rows > 0) {
            // Update existing record
            $updateSql = "UPDATE shop_details SET shop_name = ?, address = ?, phone = ?, email = ?, updated_at = NOW() WHERE id = 1";
            $stmt = $conn->prepare($updateSql);
            $stmt->bind_param("ssss", $shop_name, $address, $phone, $email);
        } else {
            // Insert new record
            $insertSql = "INSERT INTO shop_details (id, shop_name, address, phone, email, updated_at) VALUES (1, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($insertSql);
            $stmt->bind_param("ssss", $shop_name, $address, $phone, $email);
        }

        if ($stmt->execute()) {
            $message = "Shop details updated successfully.";
            // Refresh shop details
            $shopDetails = [
                'shop_name' => $shop_name,
                'address' => $address,
                'phone' => $phone,
                'email' => $email
            ];
        } else {
            $error = "Error updating shop details: " . $stmt->error;
        }
    }
}

// Define unit options for categories
$unitOptions = [
    'Cement' => ['bag', 'kg', 'gram'],
    'Rod' => ['piece', 'ton', 'inches']
];

// Define type options for Rods
$rodTypes = ['8mm', '10mm', '12mm', '16mm', '20mm'];

// Handle form submission for adding a purchase
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_purchase'])) {
    $seller_id = (int)sanitizeInput($_POST['seller_id']);
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
            // Generate invoice number (pass $conn)
            $invoice_number = generateInvoiceNumber($conn);
            if ($invoice_number === false) {
                $error = "Failed to generate invoice number. Check the error log for details.";
            } else {
                // Debug: Log the values being passed to bind_param
                error_log("Add Purchase - Values: seller_id=$seller_id, total=$total, paid_amount=$paid_amount, due=$due, purchase_date=$purchase_date, payment_method=$payment_method, invoice_number=$invoice_number");

                // Begin transaction
                $conn->begin_transaction();
                try {
                    // Insert into purchase_headers table (main purchase record)
                    $purchaseSql = "INSERT INTO purchase_headers (seller_id, total, paid, due, purchase_date, payment_method, invoice_number, created_at) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
                    $stmt = $conn->prepare($purchaseSql);
                    if ($stmt === false) {
                        throw new Exception("Prepare failed (main purchase): " . $conn->error);
                    }
                    $stmt->bind_param("iddsdss", $seller_id, $total, $paid_amount, $due, $purchase_date, $payment_method, $invoice_number);
                    if (!$stmt->execute()) {
                        throw new Exception("Execute failed (main purchase): " . $stmt->error);
                    }

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
                        if ($updateStmt === false) {
                            throw new Exception("Prepare failed (update product): " . $conn->error);
                        }
                        $updateStmt->bind_param("di", $quantity, $product_id);
                        if (!$updateStmt->execute()) {
                            throw new Exception("Execute failed (update product): " . $updateStmt->error);
                        }

                        // Insert into purchase_items
                        $purchaseItemSql = "INSERT INTO purchase_items (purchase_id, product_id, quantity, price, total, unit, type, created_at) 
                                            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
                        $purchaseItemStmt = $conn->prepare($purchaseItemSql);
                        if ($purchaseItemStmt === false) {
                            throw new Exception("Prepare failed (purchase item): " . $conn->error);
                        }
                        $itemTotal = $quantity * $price;
                        $purchaseItemStmt->bind_param("iiddsss", $purchase_id, $product_id, $quantity, $price, $itemTotal, $unit, $type);
                        if (!$purchaseItemStmt->execute()) {
                            throw new Exception("Execute failed (purchase item): " . $purchaseItemStmt->error);
                        }
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
}

// Handle form submission for updating a purchase
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_purchase'])) {
    $invoice_number = sanitizeInput($_POST['invoice_number']);
    $seller_id = (int)sanitizeInput($_POST['seller_id']);
    $products_data = $_POST['products'];
    $quantities = $_POST['quantities'];
    $prices = $_POST['prices'];
    $units = $_POST['units'];
    $types = isset($_POST['types']) ? $_POST['types'] : [];
    $paid_amount = floatval($_POST['paid_amount']);
    $payment_method = sanitizeInput($_POST['payment_method']);
    $purchase_date = sanitizeInput($_POST['purchase_date']);

    // Fetch the purchase_id from purchase_headers
    $fetchSql = "SELECT id FROM purchase_headers WHERE invoice_number = ?";
    $fetchStmt = $conn->prepare($fetchSql);
    $fetchStmt->bind_param("s", $invoice_number);
    $fetchStmt->execute();
    $fetchResult = $fetchStmt->get_result();
    if ($fetchResult->num_rows == 0) {
        $error = "Purchase not found.";
    } else {
        $purchase_id = $fetchResult->fetch_assoc()['id'];

        // Fetch existing items to revert stock
        $itemsSql = "SELECT product_id, quantity FROM purchase_items WHERE purchase_id = ?";
        $stmt = $conn->prepare($itemsSql);
        if ($stmt === false) {
            $error = "Prepare failed (fetch items): " . $conn->error;
        } else {
            $stmt->bind_param("i", $purchase_id);
            $stmt->execute();
            $itemsResult = $stmt->get_result();
            $oldItems = [];
            while ($item = $itemsResult->fetch_assoc()) {
                $oldItems[] = $item;
            }

            // Validate inputs
            $total = 0;
            $newItems = [];
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

                $newItems[] = [
                    'product_id' => $product_id,
                    'quantity' => $quantity,
                    'price' => $price,
                    'unit' => $unit,
                    'type' => $type,
                    'subtotal' => $subtotal
                ];
                $total += $subtotal;
            }

            if (empty($newItems)) {
                $error = "No valid products selected.";
            }

            if (!isset($error)) {
                $due = $total - $paid_amount;
                if ($due < 0) {
                    $error = "Paid amount cannot exceed total.";
                } else {
                    // Debug: Log the values being passed to bind_param
                    error_log("Update Purchase - Values: seller_id=$seller_id, total=$total, paid_amount=$paid_amount, due=$due, purchase_date=$purchase_date, payment_method=$payment_method, invoice_number=$invoice_number");

                    // Begin transaction
                    $conn->begin_transaction();
                    try {
                        // Revert stock for old items
                        foreach ($oldItems as $item) {
                            $product_id = $item['product_id'];
                            $quantity = $item['quantity'];
                            $updateSql = "UPDATE products SET quantity = quantity - ? WHERE id = ?";
                            $updateStmt = $conn->prepare($updateSql);
                            if ($updateStmt === false) {
                                throw new Exception("Prepare failed (revert stock): " . $conn->error);
                            }
                            $updateStmt->bind_param("di", $quantity, $product_id);
                            if (!$updateStmt->execute()) {
                                throw new Exception("Execute failed (revert stock): " . $updateStmt->error);
                            }
                        }

                        // Delete old purchase items
                        $deleteSql = "DELETE FROM purchase_items WHERE purchase_id = ?";
                        $deleteStmt = $conn->prepare($deleteSql);
                        if ($deleteStmt === false) {
                            throw new Exception("Prepare failed (delete items): " . $conn->error);
                        }
                        $deleteStmt->bind_param("i", $purchase_id);
                        if (!$deleteStmt->execute()) {
                            throw new Exception("Execute failed (delete items): " . $deleteStmt->error);
                        }

                        // Update main purchase record in purchase_headers
                        $updatePurchaseSql = "UPDATE purchase_headers SET seller_id = ?, total = ?, paid = ?, due = ?, purchase_date = ?, payment_method = ? WHERE invoice_number = ?";
                        $updateStmt = $conn->prepare($updatePurchaseSql);
                        if ($updateStmt === false) {
                            throw new Exception("Prepare failed (update purchase): " . $conn->error);
                        }
                        $updateStmt->bind_param("iddsdss", $seller_id, $total, $paid_amount, $due, $purchase_date, $payment_method, $invoice_number);
                        if (!$updateStmt->execute()) {
                            throw new Exception("Execute failed (update purchase): " . $updateStmt->error);
                        }

                        // Insert new purchase items
                        foreach ($newItems as $item) {
                            $product_id = $item['product_id'];
                            $quantity = $item['quantity'];
                            $price = $item['price'];
                            $unit = $item['unit'];
                            $type = $item['type'];

                            // Update product quantity
                            $updateSql = "UPDATE products SET quantity = quantity + ? WHERE id = ?";
                            $updateStmt = $conn->prepare($updateSql);
                            if ($updateStmt === false) {
                                throw new Exception("Prepare failed (update stock): " . $conn->error);
                            }
                            $updateStmt->bind_param("di", $quantity, $product_id);
                            if (!$updateStmt->execute()) {
                                throw new Exception("Execute failed (update stock): " . $updateStmt->error);
                            }

                            // Insert new purchase item
                            $purchaseItemSql = "INSERT INTO purchase_items (purchase_id, product_id, quantity, price, total, unit, type, created_at) 
                                                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
                            $purchaseItemStmt = $conn->prepare($purchaseItemSql);
                            if ($purchaseItemStmt === false) {
                                throw new Exception("Prepare failed (insert item): " . $conn->error);
                            }
                            $itemTotal = $quantity * $price;
                            $purchaseItemStmt->bind_param("iiddsss", $purchase_id, $product_id, $quantity, $price, $itemTotal, $unit, $type);
                            if (!$purchaseItemStmt->execute()) {
                                throw new Exception("Execute failed (insert item): " . $purchaseItemStmt->error);
                            }
                        }

                        $conn->commit();
                        $message = "Purchase updated successfully.";
                    } catch (Exception $e) {
                        $conn->rollback();
                        $error = "Error updating purchase: " . $e->getMessage();
                    }
                }
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
        // Fetch the purchase_id from purchase_headers
        $fetchSql = "SELECT id FROM purchase_headers WHERE invoice_number = ?";
        $fetchStmt = $conn->prepare($fetchSql);
        $fetchStmt->bind_param("s", $invoice_number);
        $fetchStmt->execute();
        $fetchResult = $fetchStmt->get_result();
        if ($fetchResult->num_rows == 0) {
            throw new Exception("Purchase not found.");
        }
        $purchase_id = $fetchResult->fetch_assoc()['id'];

        // Fetch items to revert stock
        $itemsSql = "SELECT product_id, quantity FROM purchase_items WHERE purchase_id = ?";
        $stmt = $conn->prepare($itemsSql);
        if ($stmt === false) {
            throw new Exception("Prepare failed (fetch items for delete): " . $conn->error);
        }
        $stmt->bind_param("i", $purchase_id);
        if (!$stmt->execute()) {
            throw new Exception("Execute failed (fetch items for delete): " . $stmt->error);
        }
        $itemsResult = $stmt->get_result();

        while ($item = $itemsResult->fetch_assoc()) {
            $product_id = $item['product_id'];
            $quantity = $item['quantity'];
            // Revert product quantity
            $updateSql = "UPDATE products SET quantity = quantity - ? WHERE id = ?";
            $updateStmt = $conn->prepare($updateSql);
            if ($updateStmt === false) {
                throw new Exception("Prepare failed (revert stock for delete): " . $conn->error);
            }
            $updateStmt->bind_param("di", $quantity, $product_id);
            if (!$updateStmt->execute()) {
                throw new Exception("Execute failed (revert stock for delete): " . $updateStmt->error);
            }
        }

        // Delete the purchase (this will cascade to purchase_items due to ON DELETE CASCADE)
        $deleteSql = "DELETE FROM purchase_headers WHERE invoice_number = ?";
        $deleteStmt = $conn->prepare($deleteSql);
        if ($deleteStmt === false) {
            throw new Exception("Prepare failed (delete purchase): " . $conn->error);
        }
        $deleteStmt->bind_param("s", $invoice_number);
        if (!$deleteStmt->execute()) {
            throw new Exception("Execute failed (delete purchase): " . $deleteStmt->error);
        }

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

// Check if we need to display an invoice
$showInvoice = false;
$invoiceData = null;
if (isset($_GET['view_invoice']) && !empty($_GET['view_invoice'])) {
    $showInvoice = true;
    $invoice_number = sanitizeInput($_GET['view_invoice']);

    // Fetch purchase details for the invoice
    $purchaseSql = "SELECT ph.*, s.name as seller_name, s.phone as seller_phone, s.address as seller_address 
                    FROM purchase_headers ph 
                    LEFT JOIN sellers s ON ph.seller_id = s.id 
                    WHERE ph.invoice_number = ?";
    $stmt = $conn->prepare($purchaseSql);
    if ($stmt === false) {
        $error = "Prepare failed: " . $conn->error;
    } else {
        $stmt->bind_param("s", $invoice_number);
        $stmt->execute();
        $purchaseResult = $stmt->get_result();

        if ($purchaseResult->num_rows === 0) {
            $error = "Purchase not found for invoice number: " . htmlspecialchars($invoice_number);
        } else {
            $invoiceData = $purchaseResult->fetch_assoc();
            $purchase_id = $invoiceData['id'];

            // Fetch purchase items
            $itemsSql = "SELECT pi.*, p.name as product_name, p.brand_name, p.type as product_type 
                         FROM purchase_items pi 
                         LEFT JOIN products p ON pi.product_id = p.id 
                         WHERE pi.purchase_id = ?";
            $stmt = $conn->prepare($itemsSql);
            if ($stmt === false) {
                $error = "Prepare failed: " . $conn->error;
            } else {
                $stmt->bind_param("i", $purchase_id);
                $stmt->execute();
                $itemsResult = $stmt->get_result();
                $invoiceData['items'] = [];
                while ($item = $itemsResult->fetch_assoc()) {
                    $invoiceData['items'][] = $item;
                }
            }
        }
    }
}

// Fetch purchase history for display (only main purchase records)
if (!$showInvoice) {
    $purchaseSql = "SELECT ph.*, s.name as seller_name 
                    FROM purchase_headers ph 
                    LEFT JOIN sellers s ON ph.seller_id = s.id 
                    ORDER BY ph.created_at DESC";
    $purchaseResult = $conn->query($purchaseSql);

    // Check if the query was successful
    if ($purchaseResult === false) {
        $error = "Error fetching purchase history: " . $conn->error;
        error_log("Purchase history query failed in buy.php: " . $conn->error);
    }
}
?>

<!-- Include html2pdf.js for PDF download -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<?php if ($showInvoice && isset($invoiceData)): ?>
    <div class="invoice-container" id="invoice">
        <div class="invoice-header">
            <h1>Invoice</h1>
            <div class="shop-details">
                <h2><?php echo htmlspecialchars($shopDetails['shop_name']); ?></h2>
                <p><?php echo htmlspecialchars($shopDetails['address']); ?></p>
                <p><strong>Phone:</strong> <?php echo htmlspecialchars($shopDetails['phone']); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($shopDetails['email']); ?></p>
            </div>
            <div class="invoice-meta">
                <p><strong>Invoice Number:</strong> <?php echo htmlspecialchars($invoiceData['invoice_number']); ?></p>
                <p><strong>Date:</strong> <?php echo htmlspecialchars($invoiceData['purchase_date']); ?></p>
            </div>
        </div>

        <div class="seller-info">
            <h3>Seller Information</h3>
            <p><strong>Name:</strong> <?php echo htmlspecialchars($invoiceData['seller_name'] ?? 'N/A'); ?></p>
            <p><strong>Phone:</strong> <?php echo htmlspecialchars($invoiceData['seller_phone'] ?? 'N/A'); ?></p>
            <p><strong>Address:</strong> <?php echo htmlspecialchars($invoiceData['seller_address'] ?? 'N/A'); ?></p>
        </div>

        <h3>Items</h3>
        <table class="invoice-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Quantity</th>
                    <th>Unit</th>
                    <th>Type</th>
                    <th>Price</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($invoiceData['items'] as $item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['product_name'] . " (" . $item['brand_name'] . ")"); ?></td>
                        <td><?php echo number_format($item['quantity'], 2); ?></td>
                        <td><?php echo htmlspecialchars($item['unit']); ?></td>
                        <td><?php echo htmlspecialchars($item['type'] ?: 'N/A'); ?></td>
                        <td><?php echo number_format($item['price'], 2); ?></td>
                        <td><?php echo number_format($item['quantity'] * $item['price'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="invoice-summary">
            <p><strong>Total:</strong> <?php echo number_format($invoiceData['total'], 2); ?></p>
            <p><strong>Paid:</strong> <?php echo number_format($invoiceData['paid'], 2); ?></p>
            <p><strong>Due:</strong> <?php echo number_format($invoiceData['due'], 2); ?></p>
        </div>

        <div class="invoice-actions">
            <button onclick="printInvoice()" class="print-btn">Print Invoice</button>
            <button onclick="downloadInvoice('<?php echo htmlspecialchars($invoiceData['invoice_number']); ?>')" class="download-btn">Download PDF</button>
            <a href="buy.php"><button class="back-btn">Back to Purchase List</button></a>
        </div>
    </div>

<?php else: ?>

    <h2>Shop Details</h2>
    <?php if (isset($message)) {
        echo "<p class='success'>" . htmlspecialchars($message) . "</p>";
    } ?>
    <?php if (isset($error)) {
        echo "<p class='error'>" . htmlspecialchars($error) . "</p>";
    } ?>
    <form method="post" class="shop-details-form">
        <label for="shop_name">Shop Name:</label>
        <input type="text" name="shop_name" id="shop_name" value="<?php echo htmlspecialchars($shopDetails['shop_name']); ?>" required>
        
        <label for="address">Address:</label>
        <textarea name="address" id="address" required><?php echo htmlspecialchars($shopDetails['address']); ?></textarea>
        
        <label for="phone">Phone:</label>
        <input type="text" name="phone" id="phone" value="<?php echo htmlspecialchars($shopDetails['phone']); ?>" required>
        
        <label for="email">Email:</label>
        <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($shopDetails['email']); ?>" required>
        
        <button type="submit" name="update_shop_details">Update Shop Details</button>
    </form>

    <h2>Add Purchase</h2>

    <form method="post" id="purchaseForm">
        <label for="seller_id">Select Seller:</label>
        <select name="seller_id" id="seller_id" required>
            <option value="">Select Seller</option>
            <?php
            if (isset($sellerResult) && $sellerResult !== false && $sellerResult->num_rows > 0) {
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
            if (isset($categoryResult) && $categoryResult !== false && $categoryResult->num_rows > 0) {
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
                    if (!empty($products)) {
                        foreach ($products as $product) {
                            $displayText = htmlspecialchars($product['name'] . " (" . $product['brand_name'] . ", " . ($product['type'] ?: 'N/A') . ") - Stock: " . $product['quantity']);
                            echo "<option value='" . $product['id'] . "' data-price='" . $product['price'] . "' data-category='" . htmlspecialchars($product['category_name']) . "' data-category-id='" . $product['category_id'] . "'>" . $displayText . "</option>";
                        }
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

    <h3>Purchase List</h3>
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
            if (isset($purchaseResult) && $purchaseResult !== false && $purchaseResult->num_rows > 0) {
                while ($purchaseRow = $purchaseResult->fetch_assoc()) {
                    $purchase_id = $purchaseRow['id'];
                    // Fetch products for this purchase
                    $itemsSql = "SELECT pi.*, pr.name as product_name, pr.brand_name, pr.type as product_type, pr.category_id, c.name as category_name 
                                 FROM purchase_items pi 
                                 LEFT JOIN products pr ON pi.product_id = pr.id 
                                 LEFT JOIN categories c ON pr.category_id = c.id 
                                 WHERE pi.purchase_id = ?";
                    $stmt = $conn->prepare($itemsSql);
                    if ($stmt === false) {
                        echo "<tr><td colspan='10'>Error fetching items: " . htmlspecialchars($conn->error) . "</td></tr>";
                        continue;
                    }
                    $stmt->bind_param("i", $purchase_id);
                    $stmt->execute();
                    $itemsResult = $stmt->get_result();
                    $items = [];
                    while ($item = $itemsResult->fetch_assoc()) {
                        $items[] = $item;
                    }

                    // Calculate total stock purchased
                    $totalStockSql = "SELECT SUM(quantity) as total_quantity 
                                      FROM purchase_items 
                                      WHERE purchase_id = ?";
                    $stockStmt = $conn->prepare($totalStockSql);
                    if ($stockStmt === false) {
                        echo "<tr><td colspan='10'>Error calculating stock: " . htmlspecialchars($conn->error) . "</td></tr>";
                        continue;
                    }
                    $stockStmt->bind_param("i", $purchase_id);
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
                        echo "<button onclick=\"toggleProducts('" . htmlspecialchars($purchaseRow['invoice_number']) . "')\">Show Products</button>";
                        echo "<div id='products-" . htmlspecialchars($purchaseRow['invoice_number']) . "' style='display:none;'>";
                        echo "<table class='sub-table'>";
                        echo "<thead><tr><th>Product Name</th><th>Quantity</th><th>Price</th><th>Unit</th><th>Type</th><th>Subtotal</th></tr></thead>";
                        echo "<tbody>";
                        foreach ($items as $item) {
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
                    echo "<button onclick=\"toggleUpdateForm('" . htmlspecialchars($purchaseRow['invoice_number']) . "')\">Update</button> ";
                    echo "<a href='buy.php?action=delete&invoice_number=" . urlencode($purchaseRow['invoice_number']) . "' onclick=\"return confirm('Are you sure you want to delete this purchase?')\"><button class='delete-btn'>Delete</button></a> ";
                    echo "<a href='buy.php?view_invoice=" . urlencode($purchaseRow['invoice_number']) . "'><button class='invoice-btn'>Generate Invoice</button></a>";
                    echo "</td>";
                    echo "</tr>";

                    // Inline Update Form
                    echo "<tr>";
                    echo "<td colspan='10'>";
                    echo "<div id='update-form-" . htmlspecialchars($purchaseRow['invoice_number']) . "' style='display:none;'>";
                    echo "<h3>Update Purchase</h3>";
                    echo "<form method='post' class='update-purchase-form'>";
                    echo "<input type='hidden' name='invoice_number' value='" . htmlspecialchars($purchaseRow['invoice_number']) . "'>";
                    echo "<label for='seller_id_" . htmlspecialchars($purchaseRow['invoice_number']) . "'>Select Seller:</label>";
                    echo "<select name='seller_id' id='seller_id_" . htmlspecialchars($purchaseRow['invoice_number']) . "' required>";
                    echo "<option value=''>Select Seller</option>";
                    if (isset($sellerResult) && $sellerResult !== false) {
                        $sellerResult->data_seek(0); // Reset seller result pointer
                        while ($sellerRow = $sellerResult->fetch_assoc()) {
                            $selected = $sellerRow['id'] == $purchaseRow['seller_id'] ? 'selected' : '';
                            echo "<option value='" . $sellerRow['id'] . "' $selected>" . htmlspecialchars($sellerRow['name']) . "</option>";
                        }
                    }
                    echo "</select><br>";

                    echo "<label for='category_id_" . htmlspecialchars($purchaseRow['invoice_number']) . "'>Select Category:</label>";
                    echo "<select name='category_id' id='category_id_" . htmlspecialchars($purchaseRow['invoice_number']) . "' onchange=\"filterProducts('" . htmlspecialchars($purchaseRow['invoice_number']) . "')\">";
                    echo "<option value=''>Select Category</option>";
                    if (isset($categoryResult) && $categoryResult !== false) {
                        $categoryResult->data_seek(0);
                        while ($categoryRow = $categoryResult->fetch_assoc()) {
                            echo "<option value='" . $categoryRow['id'] . "'>" . htmlspecialchars($categoryRow['name']) . "</option>";
                        }
                    }
                    echo "</select><br>";

                    echo "<h4>Products:</h4>";
                    echo "<div id='update-product-list-" . htmlspecialchars($purchaseRow['invoice_number']) . "'>";
                    foreach ($items as $index => $item) {
                        echo "<div class='product-row'>";
                        echo "<select name='products[]' class='product-select' onchange='updateProductDetails(this)' required>";
                        echo "<option value=''>Select Product</option>";
                        if (!empty($products)) {
                            foreach ($products as $product) {
                                $displayText = htmlspecialchars($product['name'] . " (" . $product['brand_name'] . ", " . ($product['type'] ?: 'N/A') . ") - Stock: " . $product['quantity']);
                                $selected = $product['id'] == $item['product_id'] ? 'selected' : '';
                                echo "<option value='" . $product['id'] . "' data-price='" . $product['price'] . "' data-category='" . htmlspecialchars($product['category_name']) . "' data-category-id='" . $product['category_id'] . "' $selected>" . $displayText . "</option>";
                            }
                        }
                        echo "</select>";
                        echo "<input type='number' name='quantities[]' placeholder='Quantity' step='0.01' min='0' value='" . htmlspecialchars($item['quantity']) . "' oninput='updateSubtotal(this)' required>";
                        echo "<input type='number' name='prices[]' placeholder='Price' step='0.01' min='0' value='" . htmlspecialchars($item['price']) . "' oninput='updateSubtotal(this)' required>";
                        echo "<select name='units[]' class='unit-select' required>";
                        echo "<option value=''>Select Unit</option>";
                        $categoryName = $item['category_name'];
                        if (isset($unitOptions[$categoryName])) {
                            foreach ($unitOptions[$categoryName] as $unitOption) {
                                $selected = $unitOption == $item['unit'] ? 'selected' : '';
                                echo "<option value='" . htmlspecialchars($unitOption) . "' $selected>" . htmlspecialchars($unitOption) . "</option>";
                            }
                        }
                        echo "</select>";
                        echo "<div class='type-container' style='display: " . ($categoryName == 'Rod' ? 'block' : 'none') . ";'>";
                        echo "<select name='types[]' class='type-select' " . ($categoryName == 'Rod' ? 'required' : '') . ">";
                        echo "<option value=''>Select Type</option>";
                        foreach ($rodTypes as $type) {
                            $selected = $type == $item['type'] ? 'selected' : '';
                            echo "<option value='" . htmlspecialchars($type) . "' $selected>" . htmlspecialchars($type) . "</option>";
                        }
                        echo "</select>";
                        echo "</div>";
                        echo "<span class='subtotal'>Subtotal: " . number_format($item['quantity'] * $item['price'], 2) . "</span>";
                        echo "<button type='button' class='remove-product' onclick='removeProduct(this)'>X</button>";
                        echo "</div>";
                    }
                    echo "</div>";
                    echo "<button type='button' onclick=\"addProduct('" . htmlspecialchars($purchaseRow['invoice_number']) . "')\">Add Product</button><br>";

                    echo "<label>Total: <span id='update-total-" . htmlspecialchars($purchaseRow['invoice_number']) . "'>" . number_format($purchaseRow['total'], 2) . "</span></label><br>";
                    echo "<label for='paid_amount_" . htmlspecialchars($purchaseRow['invoice_number']) . "'>Paid Amount:</label>";
                    echo "<input type='number' name='paid_amount' id='paid_amount_" . htmlspecialchars($purchaseRow['invoice_number']) . "' step='0.01' min='0' value='" . htmlspecialchars($purchaseRow['paid']) . "' oninput=\"updateDue('" . htmlspecialchars($purchaseRow['invoice_number']) . "')\" required><br>";
                    echo "<label>Due: <span id='update-due-" . htmlspecialchars($purchaseRow['invoice_number']) . "'>" . number_format($purchaseRow['due'], 2) . "</span></label><br>";
                    echo "<label for='purchase_date_" . htmlspecialchars($purchaseRow['invoice_number']) . "'>Purchase Date:</label>";
                    echo "<input type='date' name='purchase_date' id='purchase_date_" . htmlspecialchars($purchaseRow['invoice_number']) . "' value='" . htmlspecialchars($purchaseRow['purchase_date']) . "' required><br>";
                    echo "<label for='payment_method_" . htmlspecialchars($purchaseRow['invoice_number']) . "'>Payment Method:</label>";
                    echo "<select name='payment_method' id='payment_method_" . htmlspecialchars($purchaseRow['invoice_number']) . "' required>";
                    echo "<option value=''>Select Payment Method</option>";
                    echo "<option value='cash' " . ($purchaseRow['payment_method'] == 'cash' ? 'selected' : '') . ">Cash</option>";
                    echo "<option value='bank_transfer' " . ($purchaseRow['payment_method'] == 'bank_transfer' ? 'selected' : '') . ">Bank Transfer</option>";
                    echo "<option value='credit_card' " . ($purchaseRow['payment_method'] == 'credit_card' ? 'selected' : '') . ">Credit Card</option>";
                    echo "</select><br>";
                    echo "<button type='submit' name='update_purchase'>Update Purchase</button>";
                    echo "</form>";
                    echo "</div>";
                    echo "</td>";
                    echo "</tr>";
                }
            } else {
                if (!isset($error)) {
                    echo "<tr><td colspan='10'>No purchases found</td></tr>";
                }
            }
            ?>
        </tbody>
    </table>

<?php endif; ?>

<script>
const unitOptions = <?php echo json_encode($unitOptions); ?>;
const rodTypes = <?php echo json_encode($rodTypes); ?>;
const allProducts = <?php echo json_encode($products); ?>;

function updateProductDetails(selectElement) {
    const row = selectElement.closest('.product-row');
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
        unitSelect.innerHTML = '<option value="">Select Unit</option>';
        unitOptions[category].forEach(unit => {
            const option = document.createElement('option');
            option.value = unit;
            option.textContent = unit;
            unitSelect.appendChild(option);
        });
    } else {
        console.warn('No units available for category:', category);
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

    updateSubtotal(selectElement);
}

function filterProducts(invoiceNumber = null) {
    const categoryId = invoiceNumber 
        ? document.getElementById('category_id_' + invoiceNumber).value 
        : document.getElementById('category_id').value;
    const productSelects = invoiceNumber 
        ? document.querySelectorAll('#update-product-list-' + invoiceNumber + ' .product-select') 
        : document.querySelectorAll('#product-list .product-select');

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

        updateProductDetails(select);
    });
}

function updateSubtotal(element) {
    const row = element.closest('.product-row');
    const quantityInput = row.querySelector('input[name="quantities[]"]');
    const priceInput = row.querySelector('input[name="prices[]"]');
    const subtotalSpan = row.querySelector('.subtotal');

    const quantity = parseFloat(quantityInput.value) || 0;
    const price = parseFloat(priceInput.value) || 0;
    const subtotal = quantity * price;
    subtotalSpan.textContent = `Subtotal: ${subtotal.toFixed(2)}`;

    updateTotal(row.closest('form'));
}

function updateTotal(form) {
    const subtotals = form.querySelectorAll('.subtotal');
    let total = 0;
    subtotals.forEach(subtotal => {
        const value = parseFloat(subtotal.textContent.replace('Subtotal: ', '')) || 0;
        total += value;
    });

    const totalSpan = form.querySelector('[id^="total"], [id^="update-total-"]');
    const invoiceNumber = totalSpan.id.replace('update-total-', '');
    totalSpan.textContent = total.toFixed(2);

    updateDue(invoiceNumber || null);
}

function updateDue(invoiceNumber = null) {
    const totalSpan = invoiceNumber 
        ? document.getElementById('update-total-' + invoiceNumber) 
        : document.getElementById('total');
    const paidInput = invoiceNumber 
        ? document.getElementById('paid_amount_' + invoiceNumber) 
        : document.getElementById('paid_amount');
    const dueSpan = invoiceNumber 
        ? document.getElementById('update-due-' + invoiceNumber) 
        : document.getElementById('due');

    const total = parseFloat(totalSpan.textContent) || 0;
    const paid = parseFloat(paidInput.value) || 0;
    const due = total - paid;
    dueSpan.textContent = due.toFixed(2);
}

function removeProduct(button) {
    const productRows = button.closest('.product-list') ? button.closest('.product-list').querySelectorAll('.product-row') : button.closest('#product-list, [id^="update-product-list-"]').querySelectorAll('.product-row');
    if (productRows.length > 1) {
        const row = button.closest('.product-row');
        const form = row.closest('form');
        row.remove();
        updateTotal(form);
    }
}

function addProduct(invoiceNumber = null) {
    const productList = invoiceNumber 
        ? document.getElementById('update-product-list-' + invoiceNumber) 
        : document.getElementById('product-list');
    const newRow = document.createElement('div');
    newRow.className = 'product-row';
    newRow.innerHTML = `
        <select name="products[]" class="product-select" onchange="updateProductDetails(this)" required>
            <option value="">Select Product</option>
            <?php
            if (!empty($products)) {
                foreach ($products as $product) {
                    $displayText = htmlspecialchars($product['name'] . " (" . $product['brand_name'] . ", " . ($product['type'] ?: 'N/A') . ") - Stock: " . $product['quantity']);
                    echo "<option value='" . $product['id'] . "' data-price='" . $product['price'] . "' data-category='" . htmlspecialchars($product['category_name']) . "' data-category-id='" . $product['category_id'] . "'>" . $displayText . "</option>";
                }
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
    filterProducts(invoiceNumber);
}

document.getElementById('add-product-btn')?.addEventListener('click', function() {
    addProduct();
});

function filterTable() {
    const input = document.getElementById('searchInput')?.value.toLowerCase();
    const table = document.getElementById('purchaseTable');
    const rows = table?.getElementsByTagName('tr');

    if (!rows) return;

    for (let i = 1; i < rows.length; i += 2) { // Increment by 2 to skip update form rows
        const cells = rows[i].getElementsByTagName('td');
        let match = false;
        for (let j = 0; j < cells.length - 1; j++) {
            if (cells[j].textContent.toLowerCase().indexOf(input) > -1) {
                match = true;
                break;
            }
        }
        rows[i].style.display = match ? '' : 'none';
        rows[i + 1].style.display = match ? '' : 'none'; // Hide/show the update form row
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

function toggleUpdateForm(invoiceNumber) {
    const updateFormDiv = document.getElementById('update-form-' + invoiceNumber);
    const button = event.currentTarget;
    if (updateFormDiv.style.display === 'none') {
        updateFormDiv.style.display = 'block';
        button.textContent = 'Hide Update Form';
        // Trigger updateProductDetails for pre-filled products
        const productSelects = updateFormDiv.querySelectorAll('.product-select');
        productSelects.forEach(select => {
            if (select.value) {
                updateProductDetails(select);
            }
        });
    } else {
        updateFormDiv.style.display = 'none';
        button.textContent = 'Update';
    }
}

function printInvoice() {
    window.print();
}

function downloadInvoice(invoiceNumber) {
    const element = document.getElementById('invoice');
    const opt = {
        margin: 0.5,
        filename: `Invoice_${invoiceNumber}.pdf`,
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2 },
        jsPDF: { unit: 'in', format: 'letter', orientation: 'portrait' }
    };
    html2pdf().set(opt).from(element).save();
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

select, input[type="number"], input[type="date"], input[type="text"], input[type="email"], textarea {
    width: 100%;
    padding: 10px;
    margin: 5px 0;
    border: 1px solid #ddd;
    border-radius: 4px;
    box-sizing: border-box;
}

textarea {
    height: 80px;
    resize: vertical;
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

#add-product-btn, button[onclick^="addProduct"] {
    background-color: #2196F3;
    color: white;
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    margin: 10px 0;
}

#add-product-btn:hover, button[onclick^="addProduct"]:hover {
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
    border: none;
    padding: 5px 10px;
    border-radius: 4px;
    cursor: pointer;
}

.delete-btn:hover {
    background-color: #b71c1c;
}

.invoice-btn {
    background-color: #ff9800;
    color: white;
    border: none;
    padding: 5px 10px;
    border-radius: 4px;
    cursor: pointer;
}

.invoice-btn:hover {
    background-color: #f57c00;
}

/* Invoice Styles */
.invoice-container {
    background-color: #fff;
    padding: 30px;
    border-radius: 10px;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
    margin: 20px auto;
    max-width: 900px;
    font-family: 'Arial', sans-serif;
}

.invoice-header {
    border-bottom: 2px solid #4CAF50;
    padding-bottom: 20px;
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.invoice-header h1 {
    color: #4CAF50;
    margin: 0;
    font-size: 36px;
    text-transform: uppercase;
}

.shop-details {
    text-align: left;
}

.shop-details h2 {
    margin: 0;
    font-size: 24px;
    color: #333;
}

.shop-details p {
    margin: 5px 0;
    color: #666;
}

.invoice-meta {
    text-align: right;
}

.invoice-meta p {
    margin: 5px 0;
    color: #333;
    font-weight: bold;
}

.seller-info {
    margin-bottom: 30px;
}

.seller-info h3 {
    color: #4CAF50;
    border-left: 4px solid #4CAF50;
    padding-left: 10px;
    margin-bottom: 15px;
}

.seller-info p {
    margin: 5px 0;
    color: #666;
}

.invoice-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 30px;
}

.invoice-table th, .invoice-table td {
    padding: 12px;
    border: 1px solid #ddd;
    text-align: left;
}

.invoice-table th {
    background-color: #4CAF50;
    color: white;
    text-transform: uppercase;
    font-weight: bold;
}

.invoice-table td {
    background-color: #f9f9f9;
}

.invoice-table tr:nth-child(even) td {
    background-color: #fff;
}

.invoice-summary {
    text-align: right;
    margin-bottom: 30px;
    font-size: 16px;
}

.invoice-summary p {
    margin: 5px 0;
    color: #333;
}

.invoice-summary p strong {
    color: #4CAF50;
}

.invoice-actions {
    text-align: center;
}

.print-btn, .download-btn, .back-btn {
    padding: 10px 20px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    margin: 0 10px;
    font-size: 16px;
    transition: background-color 0.3s;
}

.print-btn {
    background-color: #4CAF50;
    color: white;
}

.print-btn:hover {
    background-color: #45a049;
}

.download-btn {
    background-color: #ff9800;
    color: white;
}

.download-btn:hover {
    background-color: #f57c00;
}

.back-btn {
    background-color: #2196F3;
    color: white;
}

.back-btn:hover {
    background-color: #1e88e5;
}

/* Print Styles */
@media print {
    body * {
        visibility: hidden;
    }
    .invoice-container, .invoice-container * {
        visibility: visible;
    }
    .invoice-container {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        margin: 0;
        box-shadow: none;
    }
    .invoice-actions {
        display: