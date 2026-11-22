<?php

session_start();

if (!isset($_SESSION['admin'])) {

    header("Location: login.php");
    exit();

}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
Add Clothing Product - T-Shirt Plug
</title>

<style>

* {
    box-sizing: border-box;
}

body {

    font-family: Arial, sans-serif;

    background: #f4f7fb;

    margin: 0;

    color: #222;

}

.sidebar {

    position: fixed;

    left: 0;

    top: 0;

    width: 250px;

    height: 100vh;

    background: #0d47a1;

    color: white;

    overflow-y: auto;

    z-index: 1000;

}

.sidebar h2 {

    text-align: center;

    padding: 20px;

    margin: 0;

    border-bottom:
        1px solid rgba(255,255,255,0.15);

}

.sidebar a {

    display: block;

    padding: 15px 20px;

    color: white;

    text-decoration: none;

    transition: 0.2s;

}

.sidebar a:hover {

    background: #1565c0;

}

.sidebar a.active {

    background: #1565c0;

    font-weight: bold;

    border-left: 5px solid white;

}

.main {

    margin-left: 250px;

    padding: 30px;

}

.notification {

    padding: 16px 20px;

    margin-bottom: 25px;

    border-radius: 8px;

    font-weight: bold;

    display: flex;

    align-items: center;

    justify-content: space-between;

    box-shadow:
        0 4px 12px rgba(0,0,0,0.10);

}

.notification.success {

    background: #d1e7dd;

    color: #0f5132;

    border-left: 6px solid #198754;

}

.notification.error {

    background: #f8d7da;

    color: #842029;

    border-left: 6px solid #dc3545;

}

.notification .message {

    display: flex;

    align-items: center;

    gap: 10px;

}

.notification .icon {

    font-size: 20px;

}

.notification .close {

    background: transparent;

    color: inherit;

    border: none;

    font-size: 24px;

    cursor: pointer;

}

.page-header {

    margin-bottom: 25px;

}

.page-header h1 {

    margin: 0 0 8px 0;

}

.page-header p {

    color: #666;

    margin: 0;

}

.form-box {

    background: white;

    padding: 30px;

    margin-top: 25px;

    border-radius: 15px;

    width: 550px;

    max-width: 100%;

    box-shadow:
        0 5px 15px rgba(0,0,0,0.08);

}

.form-group {

    margin-bottom: 16px;

}

.form-group label {

    display: block;

    font-weight: bold;

    margin-bottom: 7px;

}

input,
select {

    padding: 12px;

    width: 100%;

    border: 1px solid #ccc;

    border-radius: 7px;

    font-size: 15px;

    background: white;

}

input:focus,
select:focus {

    outline: none;

    border-color: #0d47a1;

    box-shadow:
        0 0 0 2px rgba(13,71,161,0.10);

}

.save-button {

    width: 100%;

    background: #0d47a1;

    color: white;

    padding: 13px 25px;

    border: none;

    border-radius: 7px;

    cursor: pointer;

    font-size: 15px;

    font-weight: bold;

}

.save-button:hover {

    background: #1565c0;

}

@media (max-width: 800px) {

    .sidebar {

        width: 200px;

    }

    .main {

        margin-left: 200px;

        padding: 15px;

    }

}

@media (max-width: 600px) {

    .sidebar {

        position: relative;

        width: 100%;

        height: auto;

    }

    .sidebar a {

        display: inline-block;

        padding: 12px;

    }

    .sidebar h2 {

        padding: 15px;

    }

    .main {

        margin-left: 0;

        padding: 15px;

    }

    .form-box {

        width: 100%;

        padding: 20px;

    }

}

</style>

</head>

<body>

<div class="sidebar">

<h2>
T-SHIRT PLUG
</h2>

<a href="dashboard.php">
🏠 Dashboard
</a>

<a href="add_product.php" class="active">
➕ Add Clothing Product
</a>

<a href="reports.php">
📊 Reports
</a>

<a href="payments.php">
💰 Payments
</a>
<a href="expenditures.php">
💸 Expenditures.php
</a>
<a href="long_term_loans.php">
💳 Long Term Loans
</a>

<a href="records.php">
📁 Saved Records 
</a>
<a href="daily_report.php">
    📅 Daily Report
</a>


<a href="logout.php">
🚪 Logout
</a>

</div>

<div class="main">

<?php if (isset($_SESSION['success'])): ?>

<div class="notification success">

<div class="message">

<span class="icon">✅</span>

<span>
<?= htmlspecialchars($_SESSION['success']) ?>
</span>

</div>

</div>

<?php unset($_SESSION['success']); ?>

<?php endif; ?>


<?php if (isset($_SESSION['error'])): ?>

<div class="notification error">

<div class="message">

<span class="icon">❌</span>

<span>
<?= htmlspecialchars($_SESSION['error']) ?>
</span>

</div>

</div>

<?php unset($_SESSION['error']); ?>

<?php endif; ?>


<div class="page-header">

<h1>
Add Clothing Product
</h1>

<p>
Enter clothing product details below.
</p>

</div>


<div class="form-box">

<!-- IMPORTANT:
     Sales/product data goes to save_product.php
-->

<form
    action="save_product.php"
    method="POST"
>

<div class="form-group">

<label>
Product Name
</label>

<input
    type="text"
    name="product_name"
    placeholder="Example: T-Shirt"
    required
>

</div>


<div class="form-group">

<label>
Name of Buyer
</label>

<input
    type="text"
    name="category"
    placeholder="Enter name of buyer"
    required
>

</div>


<div class="form-group">

<label>
Brand
</label>

<input
    type="text"
    name="brand"
    placeholder="Example: Nike"
    required
>

</div>

<!-- 
<div class="form-group">

<label>
Size
</label>

<select
    name="size"
>

<option value="">
Select Size
</option>

<option value="S">S</option>

<option value="M">M</option>

<option value="L">L</option>

<option value="XL">XL</option>

<option value="XXL">XXL</option>

<option value="ALL">ALL</option>

</select>

</div>


<div class="form-group">

<label>
Color
</label>

<input
    type="text"
    name="color"
    placeholder="Example: Black"
>

</div> -->


<div class="form-group">

<label>
Quantity
</label>

<input
    type="number"
    name="quantity"
    placeholder="Enter quantity"
    min="1"
    required
>

</div>


<div class="form-group">

<label>
Buying Price
</label>

<input
    type="number"
    name="buying_price"
    placeholder="Enter buying price"
    step="0.01"
    min="0"
    required
>

</div>


<div class="form-group">

<label>
Selling Price
</label>

<input
    type="number"
    name="selling_price"
    placeholder="Enter selling price"
    step="0.01"
    min="0"
    required
>

</div>


<div class="form-group">

<label>
Buyer Date
</label>

<input
    type="date"
    name="date"
    value="<?= date('Y-m-d') ?>"
    required
>

</div>


<button
    type="submit"
    name="save_product"
    class="save-button"
>

💾 Save Clothing Item

</button>

</form>

</div>

</div>

</body>

</html>
