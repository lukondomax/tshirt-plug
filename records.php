
<?php

session_start();

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

include "database.php";


// ==========================================
// SEARCH
// ==========================================

$search = trim($_GET['search'] ?? '');


// ==========================================
// EXPORT TO EXCEL OR PDF
// ==========================================

$export = $_GET['export'] ?? '';


// Prepare records query

function getProductRecords($conn, $search) {

    if ($search !== '') {

        $stmt = mysqli_prepare(
            $conn,
            "SELECT * FROM products
             WHERE category LIKE ?
             ORDER BY id DESC"
        );

        $like = "%" . $search . "%";

        mysqli_stmt_bind_param(
            $stmt,
            "s",
            $like
        );

        mysqli_stmt_execute($stmt);

        return mysqli_stmt_get_result($stmt);

    } else {

        return mysqli_query(
            $conn,
            "SELECT * FROM products
             ORDER BY id DESC"
        );
    }
}


// ==========================================
// EXPORT TO EXCEL
// ==========================================

if ($export === 'excel') {

    $export_records = getProductRecords(
        $conn,
        $search
    );

    if (!$export_records) {
        die("Unable to export records.");
    }

    header(
        "Content-Type: application/vnd.ms-excel; charset=UTF-8"
    );

    header(
        "Content-Disposition: attachment; filename=sales_records.xls"
    );

    header("Pragma: no-cache");
    header("Expires: 0");

    echo "\xEF\xBB\xBF";


    echo "Product\tPerson Name\tBrand\tSize\tColor\tQuantity\tBuying Price\tSelling Price\tProfit\tLoss\tNet Profit\tDate\n";


    while ($row = mysqli_fetch_assoc($export_records)) {

        $values = [

            $row['product_name'],
            $row['category'],
            $row['brand'],
            // $row['size'],
            // $row['color'],
            $row['quantity'],
            $row['buying_price'],
            $row['selling_price'],
            $row['profit'],
            $row['loss'],
            $row['net_profit'],
            $row['sale_date']

        ];


        $values = array_map(function($value) {

            $value = (string)$value;

            // Prevent spreadsheet formula injection
            if (preg_match('/^[\s]*[=+\-@]/', $value)) {
                $value = "'" . $value;
            }

            $value = preg_replace(
                '/[\r\n\t]+/',
                ' ',
                $value
            );

            $value = str_replace(
                '"',
                '""',
                $value
            );

            return '"' . $value . '"';

        }, $values);


        echo implode("\t", $values) . "\n";

    }

    exit();
}



// ==========================================
// EXPORT TO PDF
// ==========================================

if ($export === 'pdf') {

    $pdf_records = getProductRecords(
        $conn,
        $search
    );

    if (!$pdf_records) {
        die("Unable to generate PDF report.");
    }

    ?>

    <!DOCTYPE html>
    <html lang="en">

    <head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>T-Shirt Plug - Sales Records Report</title>


    <style>

    * {
        box-sizing: border-box;
    }

    body {
        font-family: Arial, sans-serif;
        padding: 20px;
        color: #222;
    }

    .company {
        text-align: center;
        margin-bottom: 20px;
    }

    .company h1 {
        margin-bottom: 5px;
        color: #0d47a1;
    }

    .company p {
        margin: 5px;
    }

    .report-info {
        margin-bottom: 15px;
        text-align: center;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        font-size: 10px;
    }

    th {
        background: #0d47a1;
        color: white;
    }

    td, th {
        padding: 7px 4px;
        border: 1px solid #ccc;
        text-align: center;
    }

    .profit {
        color: green;
        font-weight: bold;
    }

    .loss {
        color: red;
        font-weight: bold;
    }

    .net {
        color: #0d47a1;
        font-weight: bold;
    }

    .print-controls {
        margin-bottom: 20px;
        text-align: center;
    }

    .print-controls button {
        background: #198754;
        color: white;
        padding: 12px 20px;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        font-size: 15px;
    }

    @media print {

        @page {
            size: landscape;
            margin: 10mm;
        }

        body {
            padding: 0;
        }

        .print-controls {
            display: none;
        }

    }

    </style>

    </head>


    <body>


    <div class="print-controls">

        <button onclick="window.print()">
            Save / Print PDF
        </button>

    </div>


    <div class="company">

        <h1>T-SHIRT PLUG</h1>

        <h3>Sales Records Report</h3>

        <p>
            Date Printed:
            <?= date("d-m-Y") ?>
        </p>

    </div>


    <div class="report-info">

        <?php if ($search !== ''): ?>

            <strong>
                Search:
            </strong>

            <?= htmlspecialchars($search) ?>

        <?php else: ?>

            All Saved Clothing Records

        <?php endif; ?>

    </div>


    <table>

    <thead>

    <tr>

        <th>Product</th>

        <th>Person Name</th>

        <th>Brand</th>

     

        <th>Quantity</th>

        <th>Buying Price</th>

        <th>Selling Price</th>

        <th>Profit</th>

        <th>Loss</th>

        <th>Net Profit</th>

        <th>Date</th>

    </tr>

    </thead>


    <tbody>


    <?php if (mysqli_num_rows($pdf_records) > 0): ?>

        <?php while ($row = mysqli_fetch_assoc($pdf_records)): ?>

        <tr>

            <td>
                <?= htmlspecialchars($row['product_name']) ?>
            </td>

            <td>
                <?= htmlspecialchars($row['category']) ?>
            </td>

            <td>
                <?= htmlspecialchars($row['brand']) ?>
            </td>

            <td>
                <?= (int)$row['quantity'] ?>
            </td>

            <td>
                TSh <?= number_format((float)$row['buying_price']) ?>
            </td>

            <td>
                TSh <?= number_format((float)$row['selling_price']) ?>
            </td>

            <td class="profit">
                TSh <?= number_format((float)$row['profit']) ?>
            </td>

            <td class="loss">
                TSh <?= number_format((float)$row['loss']) ?>
            </td>

            <td class="net">
                TSh <?= number_format((float)$row['net_profit']) ?>
            </td>

            <td>
                <?= htmlspecialchars($row['sale_date']) ?>
            </td>

        </tr>

        <?php endwhile; ?>

    <?php else: ?>

        <tr>
            <td colspan="12">
                No records found.
            </td>
        </tr>

    <?php endif; ?>


    </tbody>

    </table>


    <script>

    window.onload = function() {

        window.print();

    };

    </script>


    </body>

    </html>

    <?php

    exit();

}



// ==========================================
// NORMAL RECORD DISPLAY
// ==========================================

$records = getProductRecords(
    $conn,
    $search
);

if (!$records) {
    die("Unable to retrieve product records.");
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>T-Shirt Plug Records</title>


<style>

/* GENERAL */

* {
    box-sizing: border-box;
}

body {
    font-family: Arial, sans-serif;
    background: #f4f7fb;
    margin: 0;
    color: #222;
}


/* SIDEBAR */

.sidebar {
    position: fixed;
    width: 240px;
    height: 100vh;
    background: #0d47a1;
    color: white;
    overflow-y: auto;
}

.sidebar h2 {
    text-align: center;
    padding: 20px;
}

.sidebar a {
    display: block;
    padding: 15px;
    color: white;
    text-decoration: none;
}

.sidebar a:hover {
    background: #1565c0;
}


/* MAIN */

.main {
    margin-left: 240px;
    padding: 30px;
}


/* BOX */

.box {
    background: white;
    padding: 25px;
    border-radius: 15px;
    box-shadow: 0 5px 15px #ddd;
    overflow-x: auto;
}


/* PRINT HEADER */

.company-print {
    display: none;
    text-align: center;
}


/* SEARCH */

.search-box {
    margin-bottom: 20px;
}

.search-box form {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

input {
    padding: 12px;
    width: 300px;
    border: 1px solid #ccc;
    border-radius: 5px;
}

button {
    padding: 12px 20px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
}


/* BUTTONS */

.search-btn {
    background: #0d47a1;
    color: white;
}

.excel-btn {
    display: inline-block;
    background: #198754;
    color: white;
    padding: 12px 15px;
    border-radius: 5px;
    text-decoration: none;
    font-weight: bold;
}

.pdf-btn {
    display: inline-block;
    background: #dc3545;
    color: white;
    padding: 12px 15px;
    border-radius: 5px;
    text-decoration: none;
    font-weight: bold;
}

.excel-btn:hover {
    background: #146c43;
}

.pdf-btn:hover {
    background: #b02a37;
}


/* TABLE */

table {
    width: 100%;
    min-width: 1100px;
    border-collapse: collapse;
    margin-top: 20px;
    font-size: 14px;
}

th {
    background: #0d47a1;
    color: white;
}

td, th {
    padding: 10px;
    border: 1px solid #ddd;
    text-align: center;
}

.profit {
    color: green;
    font-weight: bold;
}

.loss {
    color: red;
    font-weight: bold;
}

.net {
    color: #0d47a1;
    font-weight: bold;
}


/* ACTIONS */

.edit-btn {
    color: #1565c0;
    font-weight: bold;
    text-decoration: none;
    margin-right: 8px;
}

.delete-btn {
    color: red;
    font-weight: bold;
    text-decoration: none;
}


/* MOBILE */

@media(max-width: 800px) {

    .sidebar {
        width: 200px;
    }

    .main {
        margin-left: 200px;
        padding: 15px;
    }

}

@media(max-width: 600px) {

    .sidebar {
        position: relative;
        width: 100%;
        height: auto;
    }

    .sidebar a {
        display: inline-block;
        padding: 12px;
    }

    .main {
        margin-left: 0;
        padding: 15px;
    }

    .search-box form {
        flex-direction: column;
    }

    input {
        width: 100%;
    }

}

</style>

</head>


<body>


<!-- SIDEBAR -->

<div class="sidebar">

<h2>T-SHIRT PLUG</h2>

<a href="dashboard.php">🏠 Dashboard</a>

<a href="add_product.php">➕ Add Clothing Product</a>

<a href="reports.php">📊 Reports</a>

<a href="records.php">📁 Saved Records</a>

<a href="expenditure.php">💰 Payments</a>

<a href="expenditures.php">
💸 Expenditures
</a>

<a href="long_term_loans.php">
💳 Long Term Loans
</a>

<a href="daily_report.php">
📅 Daily Report
</a>

<a href="logout.php">🚪 Logout</a>

</div>



<!-- MAIN -->

<div class="main">

<div class="box">


<!-- PRINT HEADER -->

<div class="company-print">

<h1>T-SHIRT PLUG</h1>

<h3>Sales Records Report</h3>

<p>
Date Printed: <?= date("d-m-Y") ?>
</p>

</div>


<h2>Saved Clothing Records</h2>


<!-- SEARCH AND EXPORT -->

<div class="search-box">

<form method="GET">

<input
    type="text"
    name="search"
    placeholder="Enter person's name..."
    value="<?= htmlspecialchars($search) ?>"
>

<button type="submit" class="search-btn">
Search
</button>


<a
    href="records.php?<?= http_build_query([
        'export' => 'excel',
        'search' => $search
    ]) ?>"
    class="excel-btn"
>
📊 Export Excel
</a>


<a
    href="records.php?<?= http_build_query([
        'export' => 'pdf',
        'search' => $search
    ]) ?>"
    class="pdf-btn"
>
📄 Export PDF
</a>


</form>

</div>



<!-- RECORDS TABLE -->

<table>

<tr>

<th>Product</th>

<th>Person Name</th>

<th>Brand</th>

<th>Quantity</th>

<th>Buying Price</th>

<th>Selling Price</th>

<th>Profit</th>

<th>Loss</th>

<th>Net Profit</th>

<th>Date</th>

<th>Actions</th>

</tr>


<?php if (mysqli_num_rows($records) > 0): ?>

<?php while ($row = mysqli_fetch_assoc($records)): ?>

<tr>

<td>
<?= htmlspecialchars($row['product_name']) ?>
</td>

<td>
<?= htmlspecialchars($row['category']) ?>
</td>

<td>
<?= htmlspecialchars($row['brand']) ?>
</td>



<td>
<?= (int)$row['quantity'] ?>
</td>

<td>
TSh <?= number_format((float)$row['buying_price']) ?>
</td>

<td>
TSh <?= number_format((float)$row['selling_price']) ?>
</td>

<td class="profit">
TSh <?= number_format((float)$row['profit']) ?>
</td>

<td class="loss">
TSh <?= number_format((float)$row['loss']) ?>
</td>

<td class="net">
TSh <?= number_format((float)$row['net_profit']) ?>
</td>

<td>
<?= htmlspecialchars($row['sale_date']) ?>
</td>

<td>

<a
    href="edit_record.php?id=<?= (int)$row['id'] ?>"
    class="edit-btn"
>
Edit
</a>


<a
    href="delete_record.php?id=<?= (int)$row['id'] ?>"
    class="delete-btn"
    onclick="return confirm('Are you sure you want to delete this record?');"
>
Delete
</a>

</td>

</tr>

<?php endwhile; ?>

<?php else: ?>

<tr>

<td colspan="13">
No records found for "<?= htmlspecialchars($search) ?>"
</td>

</tr>

<?php endif; ?>


</table>


</div>

</div>


</body>

</html>
