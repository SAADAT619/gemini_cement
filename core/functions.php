<?php
function sanitizeInput($data) {
    global $conn;
    return mysqli_real_escape_string($conn, trim($data));
}

function generateInvoiceNumber() {
    return "INV-" . date("YmdHis");
}

function getProductDetails($productId) {
    global $conn;
    $productId = sanitizeInput($productId);
    $sql = "SELECT * FROM products WHERE id = $productId";
    $result = $conn->query($sql);
    if (!$result) {
        error_log("getProductDetails Query Error: " . $conn->error); // Log the error
        return null; // Or throw an exception
    }
    return $result->fetch_assoc();
}

function getSellerDetails($sellerId) {
    global $conn;
    $sellerId = sanitizeInput($sellerId);
    $sql = "SELECT * FROM sellers WHERE id = $sellerId";
    $result = $conn->query($sql);
     if (!$result) {
        error_log("getSellerDetails Query Error: " . $conn->error);
        return null;
    }
    return $result->fetch_assoc();
}

function getCustomerDetails($customerId) {
    global $conn;
    $customerId = sanitizeInput($customerId);
    $sql = "SELECT * FROM customers WHERE id = $customerId";
    $result = $conn->query($sql);
     if (!$result) {
        error_log("getCustomerDetails Query Error: " . $conn->error);
        return null;
    }
    return $result->fetch_assoc();
}

function getCategoryName($categoryId) {
    global $conn;
    $categoryId = sanitizeInput($categoryId);
    $sql = "SELECT name FROM categories WHERE id = $categoryId";
    $result = $conn->query($sql);
     if (!$result) {
        error_log("getCategoryName Query Error: " . $conn->error);
        return null;
    }
    $row = $result->fetch_assoc();
    return $row['name'];
}

// Function to get total sales for the dashboard
function getTotalSales() {
    global $conn;
    $sql = "SELECT SUM(total) as total_sales FROM sales";
    $result = $conn->query($sql);
     if (!$result) {
        error_log("getTotalSales Query Error: " . $conn->error);
        return 0;
    }
    $row = $result->fetch_assoc();
    return $row['total_sales'] ? $row['total_sales'] : 0;
}

// Function to get monthly sales
function getMonthlySales() {
    global $conn;
    $monthlySales = array();
    for ($month = 1; $month <= 12; $month++) {
        $sql = "SELECT SUM(total) as monthly_sale FROM sales WHERE MONTH(sale_date) = $month";
        $result = $conn->query($sql);
         if (!$result) {
            error_log("getMonthlySales Query Error: " . $conn->error);
            $monthlySales[$month] = 0;
            continue;
        }
        $row = $result->fetch_assoc();
        $monthlySales[$month] = $row['monthly_sale'] ? $row['monthly_sale'] : 0;
    }
    return $monthlySales;
}

// Function to get product stock
function getProductStock() {
    global $conn;
    $sql = "SELECT id, name, quantity, price, category_id FROM products";
    $result = $conn->query($sql);
     if (!$result) {
        error_log("getProductStock Query Error: " . $conn->error);
        return [];
    }
    $products = array();
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $products[] = $row;
        }
    }
    return $products;
}

// Function to get shop settings
function getShopSetting($key) {
    global $conn;
    $key = sanitizeInput($key);
    $sql = "SELECT value FROM settings WHERE setting_key = '$key'";
    $result = $conn->query($sql);
     if (!$result) {
        error_log("getShopSetting Query Error: " . $conn->error);
        return '';
    }
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['value'];
    }
    return '';
}

// Function to update shop settings
function updateShopSetting($key, $value) {
    global $conn;
    $key = sanitizeInput($key);
    $value = sanitizeInput($value);
    $sql = "UPDATE settings SET value = '$value' WHERE setting_key = '$key'";
    if ($conn->query($sql) === TRUE) {
        return true;
    } else {
        error_log("updateShopSetting Query Error: " . $conn->error);
        return false;
    }
}
?>
