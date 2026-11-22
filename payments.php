<?php

session_start();

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

include "database.php";


// ==========================================
// ADD EXPENDITURE
// ==========================================

if (isset($_POST['add_expenditure'])) {

    $expenditure_date = trim($_POST['expenditure_date'] ?? '');
    $amount           = (float)($_POST['amount'] ?? 0);
    $particular       = trim($_POST['particular'] ?? '');


    if (
        $expenditure_date === '' ||
        $amount <= 0 ||
        $particular === ''
    ) {

        $_SESSION['error'] =
            "Please enter a valid date, amount and particular.";

        header("Location: payments.php");
        exit();
    }


    $stmt = mysqli_prepare(
        $conn,
        "INSERT INTO expenditures
        (
            expenditure_date,
            amount,
            particular
        )
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

    header("Location: payments.php");
    exit();
}



// ==========================================
// UPDATE EXPENDITURE
// ==========================================

if (isset($_POST['update_expenditure'])) {

    $id               = (int)($_POST['id'] ?? 0);
    $expenditure_date = trim($_POST['expenditure_date'] ?? '');
    $amount           = (float)($_POST['amount'] ?? 0);
    $particular       = trim($_POST['particular'] ?? '');


    if (
        $id <= 0 ||
        $expenditure_date === '' ||
        $amount <= 0 ||
        $particular === ''
    ) {

        $_SESSION['error'] =
            "Please enter valid expenditure information.";

        header("Location: payments.php");
        exit();
    }


    $stmt = mysqli_prepare(
        $conn,
        "UPDATE expenditures
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

    header("Location: payments.php");
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
            "DELETE FROM expenditures
             WHERE id = ?"
        );


        mysqli_stmt_bind_param(
            $stmt,
            "i",
            $id
        );


        mysqli_stmt_execute($stmt);

        mysqli_stmt_close($stmt);


        $_SESSION['success'] =
            "Expenditure deleted successfully.";
    }


    header("Location: payments.php");
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
            "SELECT *
             FROM expenditures
             WHERE id = ?"
        );


        mysqli_stmt_bind_param(
            $stmt,
            "i",
            $edit_id
        );


        mysqli_stmt_execute($stmt);


        $result =
            mysqli_stmt_get_result($stmt);


        $edit_record =
            mysqli_fetch_assoc($result);


        mysqli_stmt_close($stmt);
    }
}



// ==========================================
// EXPENDITURE TOTAL FILTER
// ==========================================

$today = date('Y-m-d');
$total_period = isset($_GET['total_period']) && is_string($_GET['total_period'])
    ? $_GET['total_period']
    : 'daily';
$total_error = '';
$total_expenditure = null;
$total_start_date = $today;
$total_end_date = $today;
$custom_start_date = isset($_GET['start_date']) && is_string($_GET['start_date'])
    ? $_GET['start_date']
    : '';
$custom_end_date = isset($_GET['end_date']) && is_string($_GET['end_date'])
    ? $_GET['end_date']
    : '';

$is_valid_date = static function ($value) {
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return false;
    }

    $parts = explode('-', $value);
    return checkdate((int)$parts[1], (int)$parts[2], (int)$parts[0]);
};

if ($total_period === 'daily') {

    $total_label = 'Today';

} elseif ($total_period === 'month') {

    $total_start_date = date('Y-m-01');
    $total_end_date = date('Y-m-t');
    $total_label = date('F Y');

} elseif ($total_period === 'custom') {

    if (
        !$is_valid_date($custom_start_date)
        || !$is_valid_date($custom_end_date)
    ) {

        $total_error = 'Choose a valid start date and end date for the custom range.';

    } elseif ($custom_start_date > $custom_end_date) {

        $total_error = 'The custom range start date must be on or before the end date.';

    } else {

        $total_start_date = $custom_start_date;
        $total_end_date = $custom_end_date;
        $total_label =
            date('M j, Y', strtotime($total_start_date))
            . ' - '
            . date('M j, Y', strtotime($total_end_date));

    }

} else {

    $total_error = 'Choose Daily, This month, or Custom range.';
    $total_period = 'daily';

}

if ($total_error === '') {

    $total_stmt = mysqli_prepare(
        $conn,
        "SELECT COALESCE(SUM(amount), 0)
         FROM expenditures
         WHERE expenditure_date BETWEEN ? AND ?"
    );

    if (!$total_stmt) {

        die(
            "Could not prepare expenditure total: "
            . mysqli_error($conn)
        );

    }

    mysqli_stmt_bind_param(
        $total_stmt,
        "ss",
        $total_start_date,
        $total_end_date
    );

    if (!mysqli_stmt_execute($total_stmt)) {

        die(
            "Could not load expenditure total: "
            . mysqli_stmt_error($total_stmt)
        );

    }

    mysqli_stmt_bind_result($total_stmt, $total_expenditure);
    mysqli_stmt_fetch($total_stmt);
    $total_expenditure = (float)$total_expenditure;
    mysqli_stmt_close($total_stmt);

}


// ==========================================
// PAGINATED EXPENDITURE RECORDS
// ==========================================

$page_size = 10;
$count_result = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM expenditures"
);

if (!$count_result) {

    die(
        "Could not count expenditure records: "
        . mysqli_error($conn)
    );

}

$total_records = (int)mysqli_fetch_assoc($count_result)['total'];
$total_pages = max(1, (int)ceil($total_records / $page_size));
$page_input = isset($_GET['page']) && is_string($_GET['page'])
    ? (int)$_GET['page']
    : 1;
$page = min(max(1, $page_input), $total_pages);
$offset = ($page - 1) * $page_size;

$records = mysqli_query(
    $conn,
    "SELECT *
     FROM expenditures
     ORDER BY expenditure_date DESC, id DESC
     LIMIT $page_size OFFSET $offset"
);

if (!$records) {

    die(
        "Could not load expenditure records: "
        . mysqli_error($conn)
    );

}

$pagination_params = [
    'total_period' => $total_period
];

if ($total_period === 'custom') {

    $pagination_params['start_date'] = $custom_start_date;
    $pagination_params['end_date'] = $custom_end_date;

}

$previous_page_url = 'payments.php?' . http_build_query(
    array_merge($pagination_params, ['page' => max(1, $page - 1)])
);
$next_page_url = 'payments.php?' . http_build_query(
    array_merge($pagination_params, ['page' => min($total_pages, $page + 1)])
);

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>T-Shirt Plug - Expenditure</title>


<style>

/* =========================================
   GENERAL
========================================= */

* {
    box-sizing: border-box;
}


body {

    font-family: Arial, sans-serif;

    background: #f4f7fb;

    margin: 0;

}


/* =========================================
   SIDEBAR
========================================= */

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

    border-bottom: 1px solid
        rgba(255,255,255,0.15);

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


/* =========================================
   MAIN
========================================= */

.main {

    margin-left: 240px;

    padding: 30px;

}


.box {

    background: white;

    padding: 25px;

    margin-bottom: 25px;

    border-radius: 15px;

    box-shadow:
        0 5px 15px rgba(0,0,0,0.08);

}


h2 {

    margin-top: 0;

    color: #222;

}

.box-heading {

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 12px;

}


.box-heading h2 {

    margin-bottom: 0;

}


.form-toggle {

    width: 42px;

    height: 42px;

    display: inline-flex;

    align-items: center;

    justify-content: center;

    padding: 0;

    border-radius: 50%;

    font-size: 26px;

    line-height: 1;

}


.add-expenditure-panel {

    margin-top: 20px;

}


.add-expenditure-panel[hidden] {

    display: none;

}


/* =========================================
   MESSAGES
========================================= */

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


/* =========================================
   FORM
========================================= */

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

    color: #333;

}


input,
textarea {

    padding: 12px;

    border: 1px solid #ccc;

    border-radius: 6px;

    font-family: Arial, sans-serif;

    font-size: 15px;

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


/* =========================================
   TOTAL
========================================= */

.total-box {

    background: #0d47a1;

    color: white;

    padding: 20px;

    border-radius: 10px;

    margin-bottom: 25px;

}


.total-box h3 {

    margin: 0 0 8px 0;

}


.total-amount {

    font-size: 28px;

    font-weight: bold;

}

.total-error {

    margin-top: 16px;

    padding: 12px;

    border-radius: 6px;

    background: #f8d7da;

    color: #721c24;

}


.total-filter {

    display: flex;

    flex-wrap: wrap;

    align-items: end;

    gap: 12px;

    margin-top: 18px;

}


.total-filter .form-group {

    min-width: 170px;

}


.total-filter label {

    color: white;

}


.total-filter input,
.total-filter select {

    padding: 10px;

    border: 1px solid #ccc;

    border-radius: 6px;

    font-size: 14px;

}


.total-filter button {

    padding: 10px 14px;

    background: white;

    color: #0d47a1;

    font-weight: bold;

}


.total-filter button:hover {

    background: #e8eef8;

}


.total-filter [hidden] {

    display: none;

}


/* =========================================
   TABLE
========================================= */

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


td,
th {

    padding: 12px;

    border: 1px solid #ddd;

    text-align: center;

}


td.particular {

    text-align: left;

    white-space: pre-wrap;

    min-width: 300px;

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


/* =========================================
   MOBILE
========================================= */

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


    .expenditure-form {

        grid-template-columns: 1fr;

    }


    .form-group.full {

        grid-column: auto;

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


<!-- =========================================
     SIDEBAR
========================================= -->

<div class="sidebar">

<h2>
T-SHIRT PLUG
</h2>


<a href="dashboard.php">
🏠 Dashboard
</a>


<a href="add_product.php">
➕ Add Clothing Product
</a>


<a href="reports.php">
📊 Reports
</a>

<a href="records.php">
📁 Saved Records
</a>

<a
    href="payments.php"
    class="active"
>
💰 Payments
</a>
 <a href="expenditures.php">

        💸  Expenditures

    </a>
<a href="long_term_loans.php">
💳 Long Term Loans
</a>
<a href="daily_report.php">
    📅 Daily Report
</a>

<a href="logout.php">
🚪 Logout
</a>

</div>



<!-- =========================================
     MAIN CONTENT
========================================= -->

<div class="main">


<!-- SUCCESS MESSAGE -->

<?php if (isset($_SESSION['success'])): ?>

<div class="success">

<?= htmlspecialchars(
    $_SESSION['success']
) ?>

</div>

<?php unset($_SESSION['success']); ?>

<?php endif; ?>



<!-- ERROR MESSAGE -->

<?php if (isset($_SESSION['error'])): ?>

<div class="error">

<?= htmlspecialchars(
    $_SESSION['error']
) ?>

</div>

<?php unset($_SESSION['error']); ?>

<?php endif; ?>




<!-- =========================================
     TOTAL EXPENDITURE
========================================= -->

<div class="total-box">

<h3>
Total Payments
</h3>

<form method="GET" action="payments.php" class="total-filter">

<div class="form-group">
<label for="total-period">Show total for</label>
<select id="total-period" name="total_period">
    <option value="daily" <?= $total_period === 'daily' ? 'selected' : '' ?>>Daily</option>
    <option value="month" <?= $total_period === 'month' ? 'selected' : '' ?>>This month</option>
    <option value="custom" <?= $total_period === 'custom' ? 'selected' : '' ?>>Custom range</option>
</select>
</div>

<div
    class="form-group"
    id="total-start-date-group"
    <?= $total_period === 'custom' ? '' : 'hidden' ?>
>
<label for="total-start-date">Start date</label>
<input
    id="total-start-date"
    type="date"
    name="start_date"
    value="<?= htmlspecialchars($custom_start_date) ?>"
    <?= $total_period === 'custom' ? 'required' : '' ?>
>
</div>

<div
    class="form-group"
    id="total-end-date-group"
    <?= $total_period === 'custom' ? '' : 'hidden' ?>
>
<label for="total-end-date">End date</label>
<input
    id="total-end-date"
    type="date"
    name="end_date"
    value="<?= htmlspecialchars($custom_end_date) ?>"
    <?= $total_period === 'custom' ? 'required' : '' ?>
>
</div>

<button type="submit">Apply filter</button>

</form>

<?php if ($total_error !== ''): ?>
<div class="total-error" role="alert">
    <?= htmlspecialchars($total_error) ?>
</div>
<?php else: ?>
<p>Showing totals for <?= htmlspecialchars($total_label) ?></p>
<div class="total-amount">
    TSh <?= number_format($total_expenditure, 2) ?>
</div>
<?php endif; ?>

</div>



<!-- =========================================
     ADD / EDIT EXPENDITURE
========================================= -->

<div class="box">

<?php if ($edit_record): ?>

<h2>
Edit Expenditure
</h2>

<form
    method="POST"
    class="expenditure-form"
>


<input
    type="hidden"
    name="id"
    value="<?= (int)$edit_record['id'] ?>"
>


<div class="form-group">

<label>
Date
</label>

<input
    type="date"
    name="expenditure_date"
    value="<?= htmlspecialchars(
        $edit_record['expenditure_date']
    ) ?>"
    required
>

</div>



<div class="form-group">

<label>
Amount
</label>

<input
    type="number"
    name="amount"
    step="0.01"
    min="0.01"
    value="<?= htmlspecialchars(
        $edit_record['amount']
    ) ?>"
    required
>

</div>



<div class="form-group full">

<label>
Particular / Notes
</label>

<textarea
    name="particular"
    required
><?= htmlspecialchars(
    $edit_record['particular']
) ?></textarea>

</div>



<div class="form-group full">

<div>

<button
    type="submit"
    name="update_expenditure"
>
Update Expenditure
</button>


<a
    href="payments.php"
    class="cancel-button"
>
Cancel
</a>

</div>

</div>


</form>


<?php else: ?>

<div class="box-heading">
    <h2>Add Expenditure</h2>
    <button
        type="button"
        class="form-toggle"
        id="add-expenditure-toggle"
        aria-label="Add expenditure"
        aria-expanded="false"
        aria-controls="add-expenditure-panel"
    >
        <span aria-hidden="true">+</span>
    </button>
</div>

<div class="add-expenditure-panel" id="add-expenditure-panel" hidden>

<form
    method="POST"
    class="expenditure-form"
>


<div class="form-group">

<label>
Date
</label>

<input
    type="date"
    name="expenditure_date"
    value="<?= date('Y-m-d') ?>"
    required
>

</div>



<div class="form-group">

<label>
Amount
</label>

<input
    type="number"
    name="amount"
    step="0.01"
    min="0.01"
    placeholder="Enter amount"
    required
>

</div>



<div class="form-group full">

<label>
Particular / Notes
</label>

<textarea
    name="particular"
    placeholder="Write expenditure details or notes..."
    required
></textarea>

</div>



<div class="form-group full">

<button
    type="submit"
    name="add_expenditure"
>
Save Expenditure
</button>

</div>


</form>

</div>

<?php endif; ?>

</div>



<!-- =========================================
     EXPENDITURE RECORDS
========================================= -->

<div class="box">

<h2>
Expenditure Records
</h2>


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


<?php if (
    mysqli_num_rows($records) > 0
): ?>


<?php

$count = $offset + 1;

while (
    $row = mysqli_fetch_assoc($records)
):

?>


<tr>

<td>
<?= $count++ ?>
</td>


<td>

<?= htmlspecialchars(
    $row['expenditure_date']
) ?>

</td>


<td>

TSh <?= number_format(
    (float)$row['amount']
) ?>

</td>


<td class="particular">

<?= htmlspecialchars(
    $row['particular']
) ?>

</td>


<td>

<a
    href="payments.php?edit=<?= (int)$row['id'] ?>"
    class="edit"
>
Edit
</a>


<a
    href="payments.php?delete=<?= (int)$row['id'] ?>"
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

<?php if ($total_records > 0): ?>

<nav class="pagination" aria-label="Expenditure pages">

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

<script>
const totalPeriod = document.getElementById('total-period');
const totalStartDateGroup = document.getElementById('total-start-date-group');
const totalEndDateGroup = document.getElementById('total-end-date-group');
const totalStartDate = document.getElementById('total-start-date');
const totalEndDate = document.getElementById('total-end-date');

totalPeriod.addEventListener('change', function () {
    const isCustom = totalPeriod.value === 'custom';
    totalStartDateGroup.hidden = !isCustom;
    totalEndDateGroup.hidden = !isCustom;
    totalStartDate.required = isCustom;
    totalEndDate.required = isCustom;
});

const addExpenditureToggle = document.getElementById('add-expenditure-toggle');

if (addExpenditureToggle) {
    const addExpenditurePanel = document.getElementById('add-expenditure-panel');
    const addExpenditureIcon = addExpenditureToggle.querySelector('span');

    addExpenditureToggle.addEventListener('click', function () {
        const isExpanded =
            addExpenditureToggle.getAttribute('aria-expanded') === 'true';

        addExpenditureToggle.setAttribute('aria-expanded', String(!isExpanded));
        addExpenditureToggle.setAttribute(
            'aria-label',
            isExpanded ? 'Add expenditure' : 'Close add expenditure form'
        );
        addExpenditureIcon.textContent = isExpanded ? '+' : '-';
        addExpenditurePanel.hidden = isExpanded;
    });
}
</script>

</body>

</html>
