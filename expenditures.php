
<?php

session_start();

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

include "database.php";

// ==========================================
// EXPORT TO EXCEL
// ==========================================

if (isset($_GET['export'])) {

    $result = mysqli_query(
        $conn,
        "SELECT id, expenditure_date, amount, particular, created_at
         FROM business_expenditures
         ORDER BY expenditure_date DESC, id DESC"
    );

    if (!$result) {
        die("Export failed: " . mysqli_error($conn));
    }

    header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
    header("Content-Disposition: attachment; filename=business_expenditure_records.xls");
    header("Pragma: no-cache");
    header("Expires: 0");

    echo "\xEF\xBB\xBF";

    echo "ID\tDate\tAmount (TSh)\tParticular / Notes\tCreated At\n";

    while ($row = mysqli_fetch_assoc($result)) {

        $values = [
            $row['id'],
            $row['expenditure_date'],
            $row['amount'],
            $row['particular'],
            $row['created_at']
        ];

        $values = array_map(function($value) {

            $value = (string)$value;

            return '"' . str_replace(
                '"',
                '""',
                preg_replace('/[\r\n\t]+/', ' ', $value)
            ) . '"';

        }, $values);

        echo implode("\t", $values) . "\n";
    }

    exit();
}

// ==========================================
// ADD EXPENDITURE
// ==========================================

if (isset($_POST['add_expenditure'])) {

    $expenditure_date = trim($_POST['expenditure_date'] ?? '');
    $amount = (float)($_POST['amount'] ?? 0);
    $particular = trim($_POST['particular'] ?? '');

    if (
        $expenditure_date === '' ||
        $amount <= 0 ||
        $particular === ''
    ) {

        $_SESSION['error'] =
            "Please enter a valid date, amount and particular.";

        header("Location: expenditures.php");
        exit();
    }

    $stmt = mysqli_prepare(
        $conn,
        "INSERT INTO business_expenditures
        (expenditure_date, amount, particular)
        VALUES (?, ?, ?)"
    );

    mysqli_stmt_bind_param(
        $stmt,
        "sds",
        $expenditure_date,
        $amount,
        $particular
    );

    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    $_SESSION['success'] =
        "Expenditure added successfully.";

    header("Location: expenditures.php");
    exit();
}

// ==========================================
// UPDATE EXPENDITURE
// ==========================================

if (isset($_POST['update_expenditure'])) {

    $id = (int)($_POST['id'] ?? 0);
    $expenditure_date = trim($_POST['expenditure_date'] ?? '');
    $amount = (float)($_POST['amount'] ?? 0);
    $particular = trim($_POST['particular'] ?? '');

    if (
        $id <= 0 ||
        $expenditure_date === '' ||
        $amount <= 0 ||
        $particular === ''
    ) {

        $_SESSION['error'] =
            "Please enter valid expenditure information.";

        header("Location: expenditures.php");
        exit();
    }

    $stmt = mysqli_prepare(
        $conn,
        "UPDATE business_expenditures
         SET expenditure_date = ?,
             amount = ?,
             particular = ?
         WHERE id = ?"
    );

    mysqli_stmt_bind_param(
        $stmt,
        "sdsi",
        $expenditure_date,
        $amount,
        $particular,
        $id
    );

    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    $_SESSION['success'] =
        "Expenditure updated successfully.";

    header("Location: expenditures.php");
    exit();
}

// ==========================================
// DELETE EXPENDITURE
// ==========================================

if (isset($_GET['delete'])) {

    $id = (int)($_GET['delete'] ?? 0);

    if ($id > 0) {

        $stmt = mysqli_prepare(
            $conn,
            "DELETE FROM business_expenditures WHERE id = ?"
        );

        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $_SESSION['success'] =
            "Expenditure deleted successfully.";
    }

    header("Location: expenditures.php");
    exit();
}

// ==========================================
// GET RECORD TO EDIT
// ==========================================

$edit_record = null;

if (isset($_GET['edit'])) {

    $edit_id = (int)($_GET['edit'] ?? 0);

    if ($edit_id > 0) {

        $stmt = mysqli_prepare(
            $conn,
            "SELECT * FROM business_expenditures WHERE id = ?"
        );

        mysqli_stmt_bind_param($stmt, "i", $edit_id);
        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);
        $edit_record = mysqli_fetch_assoc($result);

        mysqli_stmt_close($stmt);
    }
}

// ==========================================
// SEARCH
// ==========================================

$search = trim($_GET['search'] ?? '');

if ($search !== '') {

    $like = "%" . $search . "%";

    $stmt = mysqli_prepare(
        $conn,
        "SELECT * FROM business_expenditures
         WHERE particular LIKE ?
         OR expenditure_date LIKE ?
         ORDER BY expenditure_date DESC, id DESC"
    );

    mysqli_stmt_bind_param(
        $stmt,
        "ss",
        $like,
        $like
    );

    mysqli_stmt_execute($stmt);

    $records = mysqli_stmt_get_result($stmt);

} else {

    $records = mysqli_query(
        $conn,
        "SELECT * FROM business_expenditures
         ORDER BY expenditure_date DESC, id DESC"
    );
}

// ==========================================
// TOTAL EXPENDITURE SUMMARY
// ==========================================

$total_result = mysqli_query(
    $conn,
    "SELECT
        COUNT(*) AS total_records,
        COALESCE(SUM(amount), 0) AS total_amount
     FROM business_expenditures"
);

$summary = mysqli_fetch_assoc($total_result);

$total_records = (int)$summary['total_records'];
$total_expenditure = (float)$summary['total_amount'];
$average_amount = (float)$summary['average_amount'];

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>T-Shirt Plug - Expenditure</title>

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

/* SIDEBAR */

.sidebar {
    position: fixed;
    left: 0;
    top: 0;
    width: 240px;
    height: 100vh;
    background: #0d47a1;
    color: white;
    overflow-y: auto;
}

.sidebar h2 {
    text-align: center;
    padding: 20px;
    margin: 0;
    border-bottom: 1px solid rgba(255,255,255,0.15);
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

/* MAIN */

.main {
    margin-left: 240px;
    padding: 30px;
}

.page-header {
    margin-bottom: 25px;
}

.page-header h1 {
    margin: 0 0 5px;
}

.page-header p {
    color: #666;
    margin: 0;
}

/* BOX */

.box {
    background: white;
    padding: 25px;
    margin-bottom: 25px;
    border-radius: 15px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
}

h2 {
    margin-top: 0;
    color: #222;
}

/* MESSAGES */

.success {
    background: #d4edda;
    color: #155724;
    padding: 12px;
    margin-bottom: 20px;
    border-radius: 6px;
}

.error {
    background: #f8d7da;
    color: #721c24;
    padding: 12px;
    margin-bottom: 20px;
    border-radius: 6px;
}

/* SUMMARY CARDS */

.summary-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
    margin-bottom: 25px;
}

.summary-card {
    background: white;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.07);
    border-left: 5px solid #0d47a1;
}

.summary-card.money {
    border-left-color: #6f42c1;
}

.summary-card.average {
    border-left-color: #198754;
}

.summary-title {
    color: #666;
    font-size: 14px;
    margin-bottom: 8px;
}

.summary-number {
    font-size: 23px;
    font-weight: bold;
}

/* FORM */

.expenditure-form {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group.full {
    grid-column: 1 / -1;
}

.form-group label {
    font-weight: bold;
    margin-bottom: 6px;
}

input, textarea {
    padding: 12px;
    border: 1px solid #ccc;
    border-radius: 6px;
    font-family: Arial, sans-serif;
    font-size: 15px;
    width: 100%;
}

textarea {
    min-height: 110px;
    resize: vertical;
}

button {
    padding: 12px 18px;
    background: #0d47a1;
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 15px;
}

button:hover {
    background: #083579;
}

.cancel-button {
    display: inline-block;
    padding: 12px 18px;
    margin-left: 8px;
    background: #777;
    color: white;
    text-decoration: none;
    border-radius: 6px;
}

.cancel-button:hover {
    background: #555;
}

/* TOTAL */

.total-box {
    background: #0d47a1;
    color: white;
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 25px;
}

.total-box h3 {
    margin: 0 0 8px;
}

.total-amount {
    font-size: 28px;
    font-weight: bold;
}

/* EXPORT */

.export-button {
    display: inline-block;
    padding: 12px 18px;
    background: #198754;
    color: white;
    text-decoration: none;
    border-radius: 6px;
    margin-bottom: 15px;
    font-weight: bold;
}

.export-button:hover {
    background: #146c43;
}

/* SEARCH */

.search-form {
    display: flex;
    gap: 8px;
    margin-bottom: 20px;
}

.search-form input {
    flex: 1;
}

/* TABLE */

.table-container {
    width: 100%;
    overflow-x: auto;
}

table {
    width: 100%;
    min-width: 700px;
    border-collapse: collapse;
    margin-top: 10px;
}

th {
    background: #0d47a1;
    color: white;
}

td, th {
    padding: 12px;
    border: 1px solid #ddd;
    text-align: center;
}

td.particular {
    text-align: left;
    white-space: pre-wrap;
    min-width: 300px;
}

.edit {
    color: #1565c0;
    text-decoration: none;
    font-weight: bold;
    margin-right: 10px;
}

.delete {
    color: #d32f2f;
    text-decoration: none;
    font-weight: bold;
}

.edit:hover,
.delete:hover {
    text-decoration: underline;
}

/* MOBILE */

@media (max-width: 1000px) {
    .summary-grid {
        grid-template-columns: repeat(2, 1fr);
    }
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

    .summary-grid,
    .expenditure-form {
        grid-template-columns: 1fr;
    }

    .form-group.full {
        grid-column: auto;
    }

    .search-form {
        flex-direction: column;
    }

    .cancel-button {
        display: inline-block;
        margin-left: 0;
        margin-top: 8px;
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

<!-- Existing Payments page -->
<a href="payments.php">
💰 Payments
</a>

<!-- New Expenditure page -->
<a href="expenditures.php" class="active">
💸 Expenditure
</a>

<a href="long_term_loans.php">💳 Long Term Loans</a>

<a href="daily_report.php">📅 Daily Report</a>

<a href="logout.php">🚪 Logout</a>

</div>

<!-- MAIN -->

<div class="main">

<div class="page-header">

<h1>Expenditure</h1>

<p>Manage and monitor all business expenditure records.</p>

</div>

<!-- MESSAGES -->

<?php if (isset($_SESSION['success'])): ?>

<div class="success">
<?= htmlspecialchars($_SESSION['success']) ?>
</div>

<?php unset($_SESSION['success']); ?>

<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>

<div class="error">
<?= htmlspecialchars($_SESSION['error']) ?>
</div>

<?php unset($_SESSION['error']); ?>

<?php endif; ?>

<!-- SUMMARY CARDS -->

<div class="summary-grid">

<div class="summary-card">

<div class="summary-title">Total Records</div>

<div class="summary-number">
<?= $total_records ?>
</div>

</div>

<div class="summary-card money">

<div class="summary-title">Total Expenditure</div>

<div class="summary-number">
TSh <?= number_format($total_expenditure) ?>
</div>

</div>


</div>

<!-- ADD / EDIT FORM -->

<div class="box">

<?php if ($edit_record): ?>

<h2>Edit Expenditure</h2>

<?php else: ?>

<h2>Add Expenditure</h2>

<?php endif; ?>

<form method="POST" action="expenditures.php" class="expenditure-form">

<?php if ($edit_record): ?>

<input type="hidden" name="id"
value="<?= (int)$edit_record['id'] ?>">

<?php endif; ?>

<div class="form-group">

<label>Date</label>

<input
    type="date"
    name="expenditure_date"
    value="<?= htmlspecialchars($edit_record['expenditure_date'] ?? date('Y-m-d')) ?>"
    required
>

</div>

<div class="form-group">

<label>Amount (TSh)</label>

<input
    type="number"
    name="amount"
    step="0.01"
    min="0.01"
    placeholder="Enter amount"
    value="<?= htmlspecialchars($edit_record['amount'] ?? '') ?>"
    required
>

</div>

<div class="form-group full">

<label>Particular / Notes</label>

<textarea
    name="particular"
    placeholder="Write expenditure details or notes..."
    required
><?= htmlspecialchars($edit_record['particular'] ?? '') ?></textarea>

</div>

<div class="form-group full">

<div>

<?php if ($edit_record): ?>

<button type="submit" name="update_expenditure">
Update Expenditure
</button>

<a href="expenditures.php" class="cancel-button">
Cancel
</a>

<?php else: ?>

<button type="submit" name="add_expenditure">
Save Expenditure
</button>

<?php endif; ?>

</div>

</div>

</form>

</div>



<!-- EXPENDITURE RECORDS -->

<div class="box">

<h2>Expenditure Records</h2>

<a href="expenditures.php?export=1" class="export-button">
📥 Export to Excel
</a>

<form method="GET" action="expenditures.php" class="search-form">

<input
    type="text"
    name="search"
    value="<?= htmlspecialchars($search) ?>"
    placeholder="Search date or expenditure particular..."
>

<button type="submit">Search</button>

</form>

<div class="table-container">

<table>

<thead>

<tr>

<th>#</th>
<th>Date</th>
<th>Amount</th>
<th>Particular / Notes</th>
<th>Action</th>

</tr>

</thead>

<tbody>

<?php if ($records && mysqli_num_rows($records) > 0): ?>

<?php $count = 1; ?>

<?php while ($row = mysqli_fetch_assoc($records)): ?>

<tr>

<td><?= $count++ ?></td>

<td>
<?= htmlspecialchars($row['expenditure_date']) ?>
</td>

<td>
TSh <?= number_format((float)$row['amount']) ?>
</td>

<td class="particular">
<?= htmlspecialchars($row['particular']) ?>
</td>

<td>

<a
    href="expenditures.php?edit=<?= (int)$row['id'] ?>"
    class="edit"
>
Edit
</a>

<a
    href="expenditures.php?delete=<?= (int)$row['id'] ?>"
    class="delete"
    onclick="return confirm('Are you sure you want to delete this expenditure?');"
>
Delete
</a>

</td>

</tr>

<?php endwhile; ?>

<?php else: ?>

<tr>

<td colspan="5">
No expenditure records found.
</td>

</tr>

<?php endif; ?>

</tbody>

</table>

</div>

</div>

</div>

</body>
</html>
