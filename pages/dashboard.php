<?php
session_start();
if (!isset($_SESSION['user_email'])) {
    header("Location: login.php");
    exit();
}
include '../config/database.php';
include '../core/functions.php'; // Include the functions.php file
include '../includes/header.php';
include '../includes/sidebar.php';

// Fetch product stock and other dashboard data with error handling
$products = getProductStock($conn);
if ($products === false) {
    $products = [];
    $error = "Error fetching product stock: " . $conn->error;
}

// Fetch the low stock threshold from settings
$lowStockThresholdSql = "SELECT value FROM settings WHERE setting_key = 'low_stock_threshold'";
$lowStockThresholdResult = $conn->query($lowStockThresholdSql);
$lowStockThreshold = $lowStockThresholdResult && $lowStockThresholdResult->num_rows > 0 ? (int)$lowStockThresholdResult->fetch_assoc()['value'] : 5;

// Fetch daily sales amount (for today)
$today = date('Y-m-d');
$dailySalesSql = "SELECT SUM(total) as daily_sales FROM sales WHERE DATE(sale_date) = '$today'";
$dailySalesResult = $conn->query($dailySalesSql);
$dailySales = $dailySalesResult && $dailySalesResult->num_rows > 0 ? $dailySalesResult->fetch_assoc()['daily_sales'] : 0;

// Fetch monthly sales amount (for the current month)
$currentMonth = date('Y-m');
$monthlySalesSql = "SELECT SUM(total) as monthly_sales FROM sales WHERE DATE_FORMAT(sale_date, '%Y-%m') = '$currentMonth'";
$monthlySalesResult = $conn->query($monthlySalesSql);
$monthlySales = $monthlySalesResult && $monthlySalesResult->num_rows > 0 ? $monthlySalesResult->fetch_assoc()['monthly_sales'] : 0;

// Fetch daily buy amount (for today)
$dailyBuySql = "SELECT SUM(total) as daily_buy FROM purchases WHERE DATE(purchase_date) = '$today'";
$dailyBuyResult = $conn->query($dailyBuySql);
$dailyBuy = $dailyBuyResult && $dailyBuyResult->num_rows > 0 ? $dailyBuyResult->fetch_assoc()['daily_buy'] : 0;

// Fetch monthly buy amount (for the current month)
$monthlyBuySql = "SELECT SUM(total) as monthly_buy FROM purchases WHERE DATE_FORMAT(purchase_date, '%Y-%m') = '$currentMonth'";
$monthlyBuyResult = $conn->query($monthlyBuySql);
$monthlyBuy = $monthlyBuyResult && $monthlyBuyResult->num_rows > 0 ? $monthlyBuyResult->fetch_assoc()['monthly_buy'] : 0;

// Fetch total due amount
$totalDueSql = "SELECT SUM(due) as total_due FROM sales";
$totalDueResult = $conn->query($totalDueSql);
$totalDue = $totalDueResult && $totalDueResult->num_rows > 0 ? $totalDueResult->fetch_assoc()['total_due'] : 0;

// Fetch top 5 customers by total sales
$topCustomersSql = "SELECT c.id, c.name, SUM(s.total) as total_sales 
                    FROM customers c 
                    JOIN sales s ON c.id = s.customer_id 
                    GROUP BY c.id, c.name 
                    ORDER BY total_sales DESC 
                    LIMIT 5";
$topCustomersResult = $conn->query($topCustomersSql);
?>

<h2>Dashboard</h2>

<?php if (isset($error)) {
    echo "<p class='error'>$error</p>";
} ?>

<div class="dashboard-summary">
    <div class="summary-card">
        <h3>Daily Sales</h3>
        <p><?php echo number_format($dailySales, 2); ?></p>
    </div>
    <div class="summary-card">
        <h3>Monthly Sales</h3>
        <p><?php echo number_format($monthlySales, 2); ?></p>
    </div>
    <div class="summary-card">
        <h3>Daily Buy</h3>
        <p><?php echo number_format($dailyBuy, 2); ?></p>
    </div>
    <div class="summary-card">
        <h3>Monthly Buy</h3>
        <p><?php echo number_format($monthlyBuy, 2); ?></p>
    </div>
    <div class="summary-card">
        <h3>Total Due</h3>
        <p><?php echo number_format($totalDue, 2); ?></p>
    </div>
</div>

<h3>Top 5 Customers by Sales</h3>
<table>
    <thead>
        <tr>
            <th>Customer Name</th>
            <th>Total Sales</th>
        </tr>
    </thead>
    <tbody>
        <?php
        if ($topCustomersResult && $topCustomersResult->num_rows > 0) {
            while ($customer = $topCustomersResult->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($customer['name']) . "</td>";
                echo "<td>" . number_format($customer['total_sales'], 2) . "</td>";
                echo "</tr>";
            }
        } else {
            echo "<tr><td colspan='2'>No customers found</td></tr>";
        }
        ?>
    </tbody>
</table>

<h3>Product Stock</h3>
<table>
    <thead>
        <tr>
            <th>Product Name</th>
            <th>Category</th>
            <th>Quantity</th>
            <th>Unit</th>
            <th>Price</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        <?php
        if (count($products) > 0) {
            foreach ($products as $product) {
                $lowStock = $product['quantity'] <= $lowStockThreshold;
                echo "<tr" . ($lowStock ? " class='low-stock'" : "") . ">";
                echo "<td>" . htmlspecialchars($product['name']) . "</td>";
                echo "<td>" . htmlspecialchars(getCategoryName($product['category_id'], $conn)) . "</td>";
                echo "<td>" . htmlspecialchars($product['quantity']) . "</td>";
                echo "<td>" . htmlspecialchars($product['unit'] ?? 'N/A') . "</td>";
                echo "<td>" . number_format($product['price'], 2) . "</td>";
                echo "<td>" . ($lowStock ? "<span class='low-stock-warning'>Low Stock</span>" : "In Stock") . "</td>";
                echo "</tr>";
            }
        } else {
            echo "<tr><td colspan='6'>No products found</td></tr>";
        }
        ?>
    </tbody>
</table>

<style>
/* General styling for the dashboard */
.dashboard-summary {
    display: flex;
    gap: 20px;
    margin-bottom: 30px;
}

.summary-card {
    background-color: #f5f5f5;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    flex: 1;
    text-align: center;
}

.summary-card h3 {
    margin: 0 0 10px;
    color: #333;
}

.summary-card p {
    font-size: 24px;
    font-weight: bold;
    color: #2e7d32; /* Green color for emphasis */
    margin: 0;
}

/* Table styling */
table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 30px;
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

.low-stock {
    background-color: #ffebee; /* Light red for low stock */
}

.low-stock-warning {
    color: #d32f2f; /* Red for warning */
    font-weight: bold;
}

/* Responsive design */
@media (max-width: 768px) {
    .dashboard-summary {
        flex-direction: column;
    }

    table {
        font-size: 14px;
    }

    th, td {
        padding: 8px;
    }
}

/* Error message styling */
.error {
    color: #d32f2f;
    background-color: #ffebee;
    padding: 10px;
    border-radius: 4px;
    margin-bottom: 20px;
}
</style>

<?php include '../includes/footer.php'; ?>