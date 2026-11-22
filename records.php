
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
// EXPORT TO CSV OR PDF
// ==========================================

$export = $_GET['export'] ?? '';


// Prepare records query

function getProductRecords($conn, $search) {

    if ($search !== '') {

        $stmt = mysqli_prepare(
            $conn,
            "SELECT * FROM products
             WHERE sale_date IS NOT NULL
             AND category LIKE ?
             ORDER BY sale_date DESC, id DESC"
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
             WHERE sale_date IS NOT NULL
             ORDER BY sale_date DESC, id DESC"
        );
    }
}


// ==========================================
// EXPORT TO CSV
// ==========================================

if ($export === 'csv') {

    $export_records = getProductRecords(
        $conn,
        $search
    );

    if (!$export_records) {
        die("Unable to export records.");
    }

    $output = fopen('php://output', 'w');

    if ($output === false) {
        die("Unable to create CSV export.");
    }

    header(
        "Content-Type: text/csv; charset=UTF-8"
    );

    header(
        "Content-Disposition: attachment; filename=sales_records.csv"
    );

    header("Pragma: no-cache");
    header("Expires: 0");

    echo "\xEF\xBB\xBF";


    fputcsv($output, [
        'Product',
        'Person Name',
        'Brand',
        'Quantity',
        'Buying Price',
        'Selling Price',
        'Profit',
        'Loss',
        'Net Profit',
        'Date'
    ]);


    while ($row = mysqli_fetch_assoc($export_records)) {

        $values = [

            $row['product_name'],
            $row['category'],
            $row['brand'],
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

            return $value;

        }, $values);


        fputcsv($output, $values);

    }

    fclose($output);
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

$page_size = 10;

if ($search !== '') {

    $like = '%' . $search . '%';
    $count_stmt = mysqli_prepare(
        $conn,
        "SELECT COUNT(*)
         FROM products
         WHERE sale_date IS NOT NULL
         AND category LIKE ?"
    );

    if (!$count_stmt) {
        die("Unable to count matching records: " . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($count_stmt, "s", $like);

} else {

    $count_stmt = mysqli_prepare(
        $conn,
        "SELECT COUNT(*)
         FROM products
         WHERE sale_date IS NOT NULL"
    );

    if (!$count_stmt) {
        die("Unable to count records: " . mysqli_error($conn));
    }

}

if (!mysqli_stmt_execute($count_stmt)) {
    die("Unable to count records: " . mysqli_stmt_error($count_stmt));
}

mysqli_stmt_bind_result($count_stmt, $total_records);
mysqli_stmt_fetch($count_stmt);
$total_records = (int)$total_records;
mysqli_stmt_close($count_stmt);

$total_pages = max(1, (int)ceil($total_records / $page_size));
$page_input = isset($_GET['page']) && is_string($_GET['page'])
    ? (int)$_GET['page']
    : 1;
$page = min(max(1, $page_input), $total_pages);
$offset = ($page - 1) * $page_size;

if ($search !== '') {

    $records_stmt = mysqli_prepare(
        $conn,
        "SELECT *
         FROM products
         WHERE sale_date IS NOT NULL
         AND category LIKE ?
         ORDER BY sale_date DESC, id DESC
         LIMIT ? OFFSET ?"
    );

    if (!$records_stmt) {
        die("Unable to prepare matching records: " . mysqli_error($conn));
    }

    mysqli_stmt_bind_param(
        $records_stmt,
        "sii",
        $like,
        $page_size,
        $offset
    );

} else {

    $records_stmt = mysqli_prepare(
        $conn,
        "SELECT *
         FROM products
         WHERE sale_date IS NOT NULL
         ORDER BY sale_date DESC, id DESC
         LIMIT ? OFFSET ?"
    );

    if (!$records_stmt) {
        die("Unable to prepare records: " . mysqli_error($conn));
    }

    mysqli_stmt_bind_param(
        $records_stmt,
        "ii",
        $page_size,
        $offset
    );

}

if (!mysqli_stmt_execute($records_stmt)) {
    die("Unable to retrieve records: " . mysqli_stmt_error($records_stmt));
}

$records = mysqli_stmt_get_result($records_stmt);

if (!$records) {
    die("Unable to retrieve records: " . mysqli_stmt_error($records_stmt));
}

mysqli_stmt_close($records_stmt);

$previous_page_url = 'records.php?' . http_build_query([
    'search' => $search,
    'page' => max(1, $page - 1)
]);
$next_page_url = 'records.php?' . http_build_query([
    'search' => $search,
    'page' => min($total_pages, $page + 1)
]);

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
.sidebar a.active {

    background: #1565c0;

    font-weight: bold;

    border-left: 5px solid white;

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

.records-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    margin-bottom: 18px;
}

.records-header h2 {
    margin: 0;
}

.add-product-button {
    padding: 10px 16px;
    border: none;
    border-radius: 6px;
    background: #198754;
    color: white;
    cursor: pointer;
    font-weight: bold;
    white-space: nowrap;
}

.add-product-button:hover {
    background: #146c43;
}

.flash-success {
    margin-bottom: 18px;
    padding: 12px 16px;
    border-radius: 6px;
    background: #d1e7dd;
    color: #0f5132;
}

.product-modal-backdrop {
    position: fixed;
    inset: 0;
    z-index: 2000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    overflow-y: auto;
    background: rgba(0, 0, 0, 0.55);
}

.product-modal-backdrop[hidden] {
    display: none;
}

.product-modal {
    width: 100%;
    max-width: 720px;
    max-height: 90vh;
    overflow-y: auto;
    padding: 24px;
    border-radius: 12px;
    background: white;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
}

.product-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    margin-bottom: 18px;
}

.product-modal-header h2 {
    margin: 0;
}

.product-modal-close {
    padding: 4px 10px;
    background: transparent;
    color: #555;
    font-size: 28px;
    line-height: 1;
}

.product-modal-close:hover {
    background: #f0f0f0;
}

.product-form {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
}

.product-form .form-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.product-form label {
    font-weight: bold;
}

.product-form input {
    width: 100%;
    padding: 11px;
    border: 1px solid #ccc;
    border-radius: 5px;
}

.product-form-actions {
    display: flex;
    grid-column: 1 / -1;
    gap: 10px;
    justify-content: flex-end;
}

.product-form-actions button[type="submit"] {
    background: #0d47a1;
    color: white;
}

.product-form-cancel {
    background: #e9ecef;
    color: #333;
}

body.modal-open {
    overflow: hidden;
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

.csv-btn {
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

.csv-btn:hover {
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

.pagination {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    margin-top: 16px;
}

.pagination a,
.pagination span {
    display: inline-block;
    padding: 8px 12px;
    border-radius: 5px;
}

.pagination a {
    background: #0d47a1;
    color: white;
    text-decoration: none;
}

.pagination a:hover {
    background: #1565c0;
}

.pagination .disabled {
    background: #e9ecef;
    color: #777;
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

    .records-header {
        align-items: flex-start;
        flex-direction: column;
    }

    .product-form {
        grid-template-columns: 1fr;
    }

    .product-form-actions {
        grid-column: auto;
        flex-direction: column;
    }

}

</style>

<link rel="stylesheet" href="sidebar.css">

</head>


<body>


<!-- SIDEBAR -->

<?php include "sidebar.php"; ?>

<div class="main">

<?php if (isset($_SESSION['message'])): ?>

<div class="flash-success" role="status">
    <?= htmlspecialchars($_SESSION['message']) ?>
</div>

<?php unset($_SESSION['message']); ?>

<?php endif; ?>

<div class="box">


<!-- PRINT HEADER -->

<div class="company-print">

<h1>T-SHIRT PLUG</h1>

<h3>Sales Records Report</h3>

<p>
Date Printed: <?= date("d-m-Y") ?>
</p>

</div>


<div class="records-header">
    <h2>Saved Clothing Records</h2>
    <button
        type="button"
        class="add-product-button"
        id="open-add-product"
    >
        + Add Product
    </button>
</div>


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
        'export' => 'csv',
        'search' => $search
    ]) ?>"
    class="csv-btn"
>
📊 Export CSV
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

<?php if ($total_records > 0): ?>

<nav class="pagination" aria-label="Saved record pages">

    <span>
        Showing <?= $offset + 1 ?>–<?= min($offset + $page_size, $total_records) ?>
        of <?= $total_records ?> records
    </span>

    <div>
        <?php if ($page > 1): ?>
            <a href="<?= htmlspecialchars($previous_page_url) ?>">Previous</a>
        <?php else: ?>
            <span class="disabled">Previous</span>
        <?php endif; ?>

        <?php if ($page < $total_pages): ?>
            <a href="<?= htmlspecialchars($next_page_url) ?>">Next</a>
        <?php else: ?>
            <span class="disabled">Next</span>
        <?php endif; ?>
    </div>

</nav>

<?php endif; ?>


</div>

</div>

<div
    class="product-modal-backdrop"
    id="add-product-modal"
    hidden
>
    <section
        class="product-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="add-product-title"
    >
        <div class="product-modal-header">
            <h2 id="add-product-title">Add Product</h2>
            <button
                type="button"
                class="product-modal-close"
                aria-label="Close add product form"
            >
                &times;
            </button>
        </div>

        <form action="save_product.php" method="POST" class="product-form">
            <input type="hidden" name="return_to" value="records">
            <input type="hidden" name="size" value="">
            <input type="hidden" name="color" value="">

            <div class="form-group">
                <label for="modal-product-name">Product Name</label>
                <input
                    id="modal-product-name"
                    type="text"
                    name="product_name"
                    placeholder="Example: T-Shirt"
                    required
                >
            </div>

            <div class="form-group">
                <label for="modal-category">Name of Buyer</label>
                <input
                    id="modal-category"
                    type="text"
                    name="category"
                    placeholder="Enter name of buyer"
                    required
                >
            </div>

            <div class="form-group">
                <label for="modal-brand">Brand</label>
                <input
                    id="modal-brand"
                    type="text"
                    name="brand"
                    placeholder="Example: Nike"
                    required
                >
            </div>

            <div class="form-group">
                <label for="modal-quantity">Quantity</label>
                <input
                    id="modal-quantity"
                    type="number"
                    name="quantity"
                    min="1"
                    placeholder="Enter quantity"
                    required
                >
            </div>

            <div class="form-group">
                <label for="modal-buying-price">Buying Price</label>
                <input
                    id="modal-buying-price"
                    type="number"
                    name="buying_price"
                    min="0"
                    step="0.01"
                    required
                >
            </div>

            <div class="form-group">
                <label for="modal-selling-price">Selling Price</label>
                <input
                    id="modal-selling-price"
                    type="number"
                    name="selling_price"
                    min="0"
                    step="0.01"
                    required
                >
            </div>

            <div class="form-group">
                <label for="modal-date">Buyer Date</label>
                <input
                    id="modal-date"
                    type="date"
                    name="date"
                    value="<?= date('Y-m-d') ?>"
                    required
                >
            </div>

            <div class="product-form-actions">
                <button type="submit" name="save_product">
                    Save Product
                </button>
                <button type="button" class="product-form-cancel">
                    Cancel
                </button>
            </div>
        </form>
    </section>
</div>

<script>
const addProductModal = document.getElementById('add-product-modal');
const openAddProductButton = document.getElementById('open-add-product');
const closeAddProductButtons = addProductModal.querySelectorAll(
    '.product-modal-close, .product-form-cancel'
);
let addProductPreviousFocus = null;

function closeAddProductModal() {
    addProductModal.hidden = true;
    document.body.classList.remove('modal-open');

    if (addProductPreviousFocus) {
        addProductPreviousFocus.focus();
    }
}

openAddProductButton.addEventListener('click', function () {
    addProductPreviousFocus = document.activeElement;
    addProductModal.hidden = false;
    document.body.classList.add('modal-open');
    addProductModal.querySelector('input:not([type="hidden"])').focus();
});

closeAddProductButtons.forEach(function (button) {
    button.addEventListener('click', closeAddProductModal);
});

addProductModal.addEventListener('click', function (event) {
    if (event.target === addProductModal) {
        closeAddProductModal();
    }
});

document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && !addProductModal.hidden) {
        closeAddProductModal();
    }
});
</script>

</body>

</html>
