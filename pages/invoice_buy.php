<?php
// pages/invoice_buy.php
session_start();
if (!isset($_SESSION['user_email'])) {
    header("Location: login.php");
    exit();
}
include '../config/database.php';
include '../core/functions.php';
include '../includes/header.php';
include '../includes/sidebar.php';

if (isset($_GET['invoice_number'])) {
    $invoice_number = sanitizeInput($_GET['invoice_number']);

    // Fetch purchase details based on invoice number
    $sql = "SELECT purchases.*,
                   sellers.name as seller_name,
                   sellers.address as seller_address,
                   sellers.phone as seller_phone,
                   products.name as product_name
            FROM purchases
            LEFT JOIN sellers ON purchases.seller_id = sellers.id
            LEFT JOIN products ON purchases.product_id = products.id
            WHERE purchases.invoice_number = '$invoice_number'";

    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $purchase = $result->fetch_assoc();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Buy Invoice - <?php echo $purchase['invoice_number']; ?></title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    margin: 0;
                    padding: 0;
                    background-color: #f0fff0;
                }

                .invoice-container {
                    max-width: 800px;
                    margin: 30px auto;
                    padding: 30px;
                    background-color: #fff;
                    border-radius: 8px;
                    box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
                }

                .header {
                    text-align: center;
                    margin-bottom: 30px;
                    border-bottom: 2px solid #007bff;
                    padding-bottom: 10px;
                }

                .header h1 {
                    margin: 0 0 5px 0;
                    color: #007bff;
                }

                .header p {
                    margin: 0 0 10px 0;
                    font-size: 14px;
                    color: #555;
                }

                .details {
                    margin-bottom: 30px;
                    display: flex;
                    justify-content: space-between;
                    font-size: 14px;
                }

                .details .seller-customer-info {
                    width: 45%;
                }

                .details .invoice-info {
                    width: 45%;
                    text-align: right;
                }

                .details h3 {
                    margin: 0 0 10px 0;
                    color: #333;
                }

                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-bottom: 30px;
                    border: 1px solid #ddd;
                }

                th,
                td {
                    padding: 12px;
                    text-align: left;
                    border-bottom: 1px solid #ddd;
                }

                th {
                    background-color: #f0f0f0;
                    font-weight: bold;
                }

                .total {
                    text-align: right;
                    margin-top: 20px;
                    font-size: 16px;
                    font-weight: bold;
                    color: #333;
                }

                .footer {
                    text-align: center;
                    margin-top: 30px;
                    padding-top: 10px;
                    border-top: 1px solid #ddd;
                    font-size: 12px;
                    color: #888;
                }
            </style>
        </head>
        <body>
            <div class="invoice-container">
                <?php include 'invoice_template.php'; ?>
            </div>
        </body>
        </html>
        <?php
    } else {
        echo "Invoice not found.";
    }
} else {
    echo "Invoice number not provided.";
}
include '../includes/footer.php';
?>