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

// Fetch product stock and other dashboard data
$products = getProductStock();
$totalSales = getTotalSales();
$monthlySales = getMonthlySales();
?>

<h2>Dashboard</h2>

<div class="dashboard-summary">
    <div class="summary-card">
        <h3>Total Sales</h3>
        <p><?php echo $totalSales; ?></p>
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
        </tr>
    </thead>
    <tbody>
        <?php
        if (count($products) > 0) {
            foreach ($products as $product) {
                echo "<tr>";
                echo "<td>" . $product['name'] . "</td>";
                echo "<td>" . getCategoryName($product['category_id']) . "</td>";
                echo "<td>" . $product['quantity'] . "</td>";
                echo "<td>" . $product['price'] . "</td>";
                echo "</tr>";
            }
        } else {
            echo "<tr><td colspan='4'>No products found</td></tr>";
        }
        ?>
    </tbody>
</table>

<h3>Monthly Sales</h3>
<canvas id="monthlySalesChart" width="400" height="200"></canvas>

<script>
const ctx = document.getElementById('monthlySalesChart').getContext('2d');
const myChart = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
        datasets: [{
            label: 'Monthly Sales',
            data: [
                <?php echo $monthlySales[1]; ?>, <?php echo $monthlySales[2]; ?>, <?php echo $monthlySales[3]; ?>,
                <?php echo $monthlySales[4]; ?>, <?php echo $monthlySales[5]; ?>, <?php echo $monthlySales[6]; ?>,
                <?php echo $monthlySales[7]; ?>, <?php echo $monthlySales[8]; ?>, <?php echo $monthlySales[9]; ?>,
                <?php echo $monthlySales[10]; ?>, <?php echo $monthlySales[11]; ?>, <?php echo $monthlySales[12]; ?>
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
        }
    }
});
</script>

<?php include '../includes/footer.php'; ?>