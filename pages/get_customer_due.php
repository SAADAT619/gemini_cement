<?php
include '../config/database.php';
include '../core/functions.php';

header('Content-Type: application/json');

if (isset($_GET['customer_id'])) {
    $customer_id = sanitizeInput($_GET['customer_id']);

    // Fetch the total due for the customer
    $stmt = $conn->prepare("SELECT SUM(due) as total_due FROM sales WHERE customer_id = ?");
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    $previous_due = $row['total_due'] ?? 0;

    echo json_encode(['previous_due' => $previous_due]);
} else {
    echo json_encode(['error' => 'Customer ID not provided']);
}

$conn->close();
?>