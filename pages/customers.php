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

// Handle customer form submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['add_customer'])) {
        $name = sanitizeInput($_POST['name']);
        $phone = sanitizeInput($_POST['phone']);
        $address = sanitizeInput($_POST['address']);

        $sql = "INSERT INTO customers (name, phone, address) VALUES ('$name', '$phone', '$address')";

        if ($conn->query($sql) === TRUE) {
            $message = "Customer added successfully";
        } else {
            $error = "Error adding customer: " . $conn->error;
        }
    } elseif (isset($_POST['update_customer'])) {
        $id = sanitizeInput($_POST['id']);
        $name = sanitizeInput($_POST['name']);
        $phone = sanitizeInput($_POST['phone']);
        $address = sanitizeInput($_POST['address']);

        $sql = "UPDATE customers SET name='$name', phone='$phone', address='$address' WHERE id=$id";

        if ($conn->query($sql) === TRUE) {
            $message = "Customer updated successfully";
        } else {
            $error = "Error updating customer: " . $conn->error;
        }
    } elseif (isset($_POST['delete_customer'])) {
        $id = sanitizeInput($_POST['id']);
        $sql = "DELETE FROM customers WHERE id=$id";

        if ($conn->query($sql) === TRUE) {
            $message = "Customer deleted successfully";
        } else {
            $error = "Error deleting customer: " . $conn->error;
        }
    }
}

// Fetch customers for listing
$customersSql = "SELECT * FROM customers";
$customersResult = $conn->query($customersSql);

?>

<h2>Customers</h2>

<?php if (isset($message)) {
    echo "<p class='success'>$message</p>";
} ?>
<?php if (isset($error)) {
    echo "<p class='error'>$error</p>";
} ?>

<form method="post">
    <input type="text" name="name" placeholder="Customer Name" required><br>
    <input type="text" name="phone" placeholder="Phone" required><br>
    <input type="text" name="address" placeholder="Address" required><br>
    <button type="submit" name="add_customer">Add Customer</button>
</form>

<hr>

<h3>Customer List</h3>
<table>
    <thead>
        <tr>
            <th>Name</th>
            <th>Phone</th>
            <th>Address</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <?php
        if ($customersResult->num_rows > 0) {
            while ($customerRow = $customersResult->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . $customerRow['name'] . "</td>";
                echo "<td>" . $customerRow['phone'] . "</td>";
                echo "<td>" . $customerRow['address'] . "</td>";
                echo "<td>";
                echo "<button onclick=\"editCustomer(" . $customerRow['id'] . ", '" . $customerRow['name'] . "', '" . $customerRow['phone'] . "', '" . $customerRow['address'] . "')\">Edit</button> | ";
                echo "<form method='post' style='display:inline;'><input type='hidden' name='id' value='" . $customerRow['id'] . "'><button type='submit' name='delete_customer'>Delete</button></form>";
                echo "</td>";
                echo "</tr>";
            }
        } else {
            echo "<tr><td colspan='4'>No customers found</td></tr>";
        }
        ?>
    </tbody>
</table>

<div id="edit_customer_form" style="display:none;">
    <h3>Edit Customer</h3>
    <form method="post">
        <input type="hidden" name="id" id="edit_customer_id">
        <input type="text" name="name" id="edit_name" placeholder="Customer Name" required><br>
        <input type="text" name="phone" id="edit_phone" placeholder="Phone" required><br>
        <input type="text" name="address" id="edit_address" placeholder="Address" required><br>
        <button type="submit" name="update_customer">Update Customer</button>
        <button type="button" onclick="document.getElementById('edit_customer_form').style.display='none';">Cancel</button>
    </form>
</div>

<script>
    function editCustomer(id, name, phone, address) {
        document.getElementById('edit_customer_form').style.display = 'block';
        document.getElementById('edit_customer_id').value = id;
        document.getElementById('edit_name').value = name;
        document.getElementById('edit_phone').value = phone;
        document.getElementById('edit_address').value = address;
    }
</script>

<?php include '../includes/footer.php'; ?>