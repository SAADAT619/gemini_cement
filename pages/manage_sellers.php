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

// Handle seller creation, update, and deletion
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['add_seller'])) {
        $name = sanitizeInput($_POST['name']);
        $phone = sanitizeInput($_POST['phone']);
        $address = sanitizeInput($_POST['address']);

        $sql = "INSERT INTO sellers (name, phone, address) VALUES ('$name', '$phone', '$address')";
        if ($conn->query($sql) === TRUE) {
            $message = "Seller added successfully";
        } else {
            $error = "Error adding seller: " . $conn->error;
        }
    } elseif (isset($_POST['update_seller'])) {
        $id = sanitizeInput($_POST['id']);
        $name = sanitizeInput($_POST['name']);
        $phone = sanitizeInput($_POST['phone']);
        $address = sanitizeInput($_POST['address']);
        $sql = "UPDATE sellers SET name='$name', phone='$phone', address='$address' WHERE id=$id";
        if ($conn->query($sql) === TRUE) {
            $message = "Seller updated successfully";
        } else {
            $error = "Error updating seller: " . $conn->error;
        }
    } elseif (isset($_POST['delete_seller'])) {
        $id = sanitizeInput($_POST['id']);
        $sql = "DELETE FROM sellers WHERE id=$id";
        if ($conn->query($sql) === TRUE) {
            $message = "Seller deleted successfully";
        } else {
            $error = "Error deleting seller: " . $conn->error;
        }
    }
}

// Fetch sellers for display
$sellerSql = "SELECT * FROM sellers";
$sellerResult = $conn->query($sellerSql);
?>

<h2>Manage Sellers</h2>

<?php if (isset($message)) {
    echo "<p class='success'>$message</p>";
} ?>
<?php if (isset($error)) {
    echo "<p class='error'>$error</p>";
} ?>

<h3>Add Seller</h3>
<form method="post">
    <input type="text" name="name" placeholder="Seller Name" required><br>
    <input type="text" name="phone" placeholder="Phone" required><br>
    <textarea name="address" placeholder="Address" required></textarea><br>
    <button type="submit" name="add_seller">Add Seller</button>
</form>

<h3>Seller List</h3>
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
        if ($sellerResult->num_rows > 0) {
            while ($sellerRow = $sellerResult->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . $sellerRow['name'] . "</td>";
                echo "<td>" . $sellerRow['phone'] . "</td>";
                echo "<td>" . $sellerRow['address'] . "</td>";
                echo "<td>";
                echo "<button onclick=\"editSeller(" . $sellerRow['id'] . ", '" . $sellerRow['name'] . "', '" . $sellerRow['phone'] . "', '" . $sellerRow['address'] . "')\">Edit</button> | ";
                echo "<form method='post' style='display:inline;'><input type='hidden' name='id' value='" . $sellerRow['id'] . "'><button type='submit' name='delete_seller'>Delete</button></form>";
                echo "</td>";
                echo "</tr>";
            }
        } else {
            echo "<tr><td colspan='4'>No sellers found</td></tr>";
        }
        ?>
    </tbody>
</table>

<div id="edit_seller_form" style="display:none;">
    <h3>Edit Seller</h3>
    <form method="post">
        <input type="hidden" name="id" id="edit_id">
        <input type="text" name="name" id="edit_name" placeholder="Seller Name" required><br>
        <input type="text" name="phone" id="edit_phone" placeholder="Phone" required><br>
        <textarea name="address" id="edit_address" placeholder="Address" required></textarea><br>
        <button type="submit" name="update_seller">Update Seller</button>
        <button type="button" onclick="document.getElementById('edit_seller_form').style.display='none';">Cancel</button>
    </form>
</div>

<script>
    function editSeller(id, name, phone, address) {
        document.getElementById('edit_seller_form').style.display = 'block';
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_name').value = name;
        document.getElementById('edit_phone').value = phone;
        document.getElementById('edit_address').value = address;
    }
</script>

<?php include '../includes/footer.php'; ?>
