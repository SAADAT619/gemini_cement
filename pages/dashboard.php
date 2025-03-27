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

$totalSales = getTotalSales($conn);
if ($totalSales === false) {
    $totalSales = 0;
    $error = isset($error) ? $error . "<br>Error fetching total sales: " . $conn->error : "Error fetching total sales: " . $conn->error;
}

$monthlySales = getMonthlySales($conn);
if ($monthlySales === false) {
    $monthlySales = array_fill(1, 12, 0); // Default to 0 for each month
    $error = isset($error) ? $error . "<br>Error fetching monthly sales: " . $conn->error : "Error fetching monthly sales: " . $conn->error;
}
?>

<h2>Dashboard</h2>

<?php if (isset($error)) {
    echo "<p class='error'>$error</p>";
} ?>

<div class="dashboard-summary">
    <div class="summary-card">
        <h3>Total Sales</h3>
        <p><?php echo number_format($totalSales, 2); ?></p>
    </div>
</div>

<h3>Product Stock</h3>
<table>
    <thead>
        <tr>
            <th>Product Name</th>
            <th>Category</th>
            <th>Quantity</th>
            <th>Price</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        <?php
        if (count($products) > 0) {
            foreach ($products as $product) {
                $lowStock = $product['quantity'] <= 10; // Define low stock threshold as 10
                echo "<tr" . ($lowStock ? " class='low-stock'" : "") . ">";
                echo "<td>" . htmlspecialchars($product['name']) . "</td>";
                echo "<td>" . htmlspecialchars(getCategoryName($product['category_id'], $conn)) . "</td>";
                echo "<td>" . htmlspecialchars($product['quantity']) . "</td>";
                echo "<td>" . number_format($product['price'], 2) . "</td>";
                echo "<td>" . ($lowStock ? "<span class='low-stock-warning'>Low Stock</span>" : "In Stock") . "</td>";
                echo "</tr>";
            }
        } else {
            echo "<tr><td colspan='5'>No products found</td></tr>";
        }
        ?>
    </tbody>
</table>

<h3>Monthly Sales</h3>
<canvas id="monthlySalesChart" width="400" height="200"></canvas>

<!-- Include Chart.js library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const ctx = document.getElementById('monthlySalesChart').getContext('2d');
const myChart = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
        datasets: [{
            label: 'Monthly Sales',
            data: [
                <?php echo $monthlySales[1] ?? 0; ?>, <?php echo $monthlySales[2] ?? 0; ?>, <?php echo $monthlySales[3] ?? 0; ?>,
                <?php echo $monthlySales[4] ?? 0; ?>, <?php echo $monthlySales[5] ?? 0; ?>, <?php echo $monthlySales[6] ?? 0; ?>,
                <?php echo $monthlySales[7] ?? 0; ?>, <?php echo $monthlySales[8] ?? 0; ?>, <?php echo $monthlySales[9] ?? 0; ?>,
                <?php echo $monthlySales[10] ?? 0; ?>, <?php echo $monthlySales[11] ?? 0; ?>, <?php echo $monthlySales[12] ?? 0; ?>
            ],
            backgroundColor: [
                'rgba(255, 99, 132, 0.2)',
                'rgba(54, 162, 235, 0.2)',
                'rgba(255, 206, 86, 0.2)',
                'rgba(75, 192, 192, 0.2)',
                'rgba(153, 102, 255, 0.2)',
                'rgba(255, 159, 64, 0.2)',
                'rgba(255, 99, 132, 0.2)',
                'rgba(54, 162, 235, 0.2)',
                'rgba(255, 206, 86, 0.2)',
                'rgba(75, 192, 192, 0.2)',
                'rgba(153, 102, 255, 0.2)',
                'rgba(255, 159, 64, 0.2)'
            ],
            borderColor: [
                'rgba(255, 99, 132, 1)',
                'rgba(54, 162, 235, 1)',
                'rgba(255, 206, 86, 1)',
                'rgba(75, 192, 192, 1)',
                'rgba(153, 102, 255, 1)',
                'rgba(255, 159, 64, 1)',
                'rgba(255, 99, 132, 1)',
                'rgba(54, 162, 235, 1)',
                'rgba(255, 206, 86, 1)',
                'rgba(75, 192, 192, 1)',
                'rgba(153, 102, 255, 1)',
                'rgba(255, 159, 64, 1)'
            ],
            borderWidth: 1
        }]
    },
    options: {
        scales: {
            y: {
                beginAtZero: true
            }
        },
        responsive: true,
        maintainAspectRatio: false
    }
});
</script>

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

/* Product stock table styling */
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

/* Chart styling */
canvas {
    max-width: 100%;
    height: 300px !important; /* Adjust height for better visibility */
    margin-bottom: 30px;
}

/* Responsive design */
@media (max-width: 768px) {
    .dashboard-summary {
        flex-direction: column;
    }

    table, canvas {
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