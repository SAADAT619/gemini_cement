<?php
// pages/invoice_template.php

// Determine if it's buy or sell
$isBuy = strpos($_SERVER['PHP_SELF'], 'invoice_buy.php') !== false;

// Fetch data based on type
if ($isBuy) {
    $data = $purchase;
    $sellerOrCustomer = 'Seller';
    $sellerOrCustomerName = $data['seller_name'];
    $sellerOrCustomerAddress = $data['seller_address'];
    $sellerOrCustomerPhone = $data['seller_phone'];
    $dateLabel = 'Purchase Date';
} else {
    $data = $sale;
    $sellerOrCustomer = 'Customer';
    $sellerOrCustomerName = $data['customer_name'];
    $sellerOrCustomerAddress = $data['customer_address'];
    $sellerOrCustomerPhone = $data['customer_phone'];
    $dateLabel = 'Sale Date';
}
?>

<div class="header">
    <h1><?php echo getShopSetting('shop_name'); ?></h1>
    <p>Address: <?php echo getShopSetting('shop_address'); ?></p>
    <p>Phone: <?php echo getShopSetting('shop_phone'); ?></p>
</div>

<div class="details">
    <div class="seller-customer-info">
        <h3><?php echo $sellerOrCustomer; ?> Details</h3>
        <p><strong>Name:</strong> <?php echo $sellerOrCustomerName; ?></p>
        <p><strong>Address:</strong> <?php echo $sellerOrCustomerAddress; ?></p>
        <p><strong>Phone:</strong> <?php echo $sellerOrCustomerPhone; ?></p>
    </div>
    <div class="invoice-info">
        <p><strong>Invoice Number:</strong> <?php echo $data['invoice_number']; ?></p>
        <p><strong><?php echo $dateLabel; ?>:</strong> <?php echo $data['sale_date'] ?? $data['purchase_date']; ?></p>
        <p><strong>Payment Method:</strong> <?php echo $data['payment_method']; ?></p>
    </div>
</div>

<h3>Product Details</h3>
<table>
    <thead>
        <tr>
            <th>Product</th>
            <th>Quantity</th>
            <th>Price</th>
            <th>Total</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td><?php echo $data['product_name']; ?></td>
            <td><?php echo $data['quantity']; ?></td>
            <td><?php echo $data['price']; ?></td>
            <td><?php echo $data['total']; ?></td>
        </tr>
    </tbody>
</table>

<div class="total">
    <p><strong>Paid:</strong> <?php echo $data['paid']; ?></p>
    <p><strong>Due:</strong> <?php echo $data['due']; ?></p>
</div>

<div class="footer">
    <p>Thank you for your business!</p>
</div>