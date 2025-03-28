<?php
include '../config/database.php';
include '../core/functions.php';

// Get the search term from the query string
$search_term = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';

// Build the search query
$sql = "SELECT sales.*, customers.name as customer_name, customers.phone as customer_phone, customers.address as customer_address 
        FROM sales 
        LEFT JOIN customers ON sales.customer_id = customers.id 
        WHERE 1=1";

if (!empty($search_term)) {
    $search_term_like = "%$search_term%";
    // Use MATCH ... AGAINST for address (full-text search) and LIKE for other fields
    $sql .= " AND (customers.name LIKE ? OR COALESCE(customers.phone, '') LIKE ? OR MATCH(customers.address) AGAINST(? IN BOOLEAN MODE) OR sales.invoice_number LIKE ?)";
}

// Order by sale date
$sql .= " ORDER BY sales.sale_date DESC";

// Prepare and execute the query
$stmt = $conn->prepare($sql);
if (!empty($search_term)) {
    // For MATCH ... AGAINST, we pass the raw search term (without % wildcards)
    $stmt->bind_param("ssss", $search_term_like, $search_term_like, $search_term, $search_term_like);
}
$stmt->execute();
$result = $stmt->get_result();

// Fetch sale items for displaying products
$stmt_sale_items = $conn->prepare("SELECT sale_items.*, products.name as product_name FROM sale_items LEFT JOIN products ON sale_items.product_id = products.id");
$stmt_sale_items->execute();
$saleItemsResult = $stmt_sale_items->get_result();

// Output the table rows
if ($result->num_rows > 0) {
    while ($saleRow = $result->fetch_assoc()) {
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

// Close statements and connection
$stmt->close();
$stmt_sale_items->close();
$conn->close();
?>