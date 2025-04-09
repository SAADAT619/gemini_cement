<?php
// core/functions.php

// This file contains all general helper functions for the application.

function sanitizeInput($data) {
    global $conn;
    return mysqli_real_escape_string($conn, trim($data));
}

function generateInvoiceNumber() {
    return "INV-" . date("YmdHis");
}

function getCategoryName($category_id, $conn) {
    if (!$category_id) {
        return 'Unknown';
    }
    $category_id = (int)$category_id; // Ensure category_id is an integer
    $sql = "SELECT name FROM categories WHERE id = $category_id";
    $result = $conn->query($sql);
    if (!$result) {
        error_log("getCategoryName Query Error: " . $conn->error);
        return 'Unknown';
    }
    $row = $result->fetch_assoc();
    return $row['name'] ?? 'Unknown';
}

// Function to get total sales (not currently used in dashboard.php but kept for future use)
function getTotalSales($conn) {
    $sql = "SELECT SUM(total) as total_sales FROM sales";
    $result = $conn->query($sql);
    if (!$result) {
        error_log("getTotalSales Query Error: " . $conn->error);
        return 0;
    }
    $row = $result->fetch_assoc();
    return $row['total_sales'] ? floatval($row['total_sales']) : 0;
}

// Function to get monthly sales (not currently used in dashboard.php but kept for future use)
function getMonthlySales($conn) {
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
        $monthlySales[$month] = $row['monthly_sale'] ? floatval($row['monthly_sale']) : 0;
    }
    return $monthlySales;
}

// Function to get product stock
function getProductStock($conn) {
    $sql = "SELECT p.id, p.name, p.category_id, p.quantity, p.price, p.unit, p.brand_name, p.type
            FROM products p
            ORDER BY p.category_id ASC, p.name ASC";
    $result = $conn->query($sql);
    if (!$result) {
        error_log("getProductStock Query Error: " . $conn->error);
        return [];
    }
    $products = array();
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $row['quantity'] = floatval($row['quantity']); // Ensure quantity is a float
            $row['price'] = floatval($row['price']); // Ensure price is a float
            $products[] = $row;
        }
    }
    return $products;
}

// Function to get product stock by ID (used for stock validation in sell.php)
function getProductStockById($conn, $product_id) {
    $product_id = (int)$product_id; // Ensure product_id is an integer
    $sql = "SELECT * FROM products WHERE id = $product_id";
    $result = $conn->query($sql);
    if (!$result) {
        error_log("getProductStockById Query Error: " . $conn->error);
        return ['quantity' => 0, 'unit' => ''];
    }
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $row['quantity'] = floatval($row['quantity']); // Ensure quantity is a float
        $row['price'] = floatval($row['price']); // Ensure price is a float
        return $row;
    }
    return ['quantity' => 0, 'unit' => ''];
}

// Function to get shop settings
function getShopSetting($key, $conn) {
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
function updateShopSetting($key, $value, $conn) {
    $key = sanitizeInput($key);
    $value = sanitizeInput($value);
    $sql = "INSERT INTO settings (setting_key, value) VALUES ('$key', '$value') 
            ON DUPLICATE KEY UPDATE value = '$value'";
    if ($conn->query($sql) === TRUE) {
        return true;
    } else {
        error_log("updateShopSetting Query Error: " . $conn->error);
        return false;
    }
}
?>