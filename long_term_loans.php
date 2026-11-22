<?php

session_start();

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

include "database.php";


// ==========================================
// ADD LONG TERM LOAN
// ==========================================

if (isset($_POST['add_loan'])) {

    $loan_date     = trim($_POST['loan_date'] ?? '');
    $customer_name = trim($_POST['customer_name'] ?? '');
    $amount        = (float)($_POST['amount'] ?? 0);
    $particular    = trim($_POST['particular'] ?? '');
    $from_date     = trim($_POST['from_date'] ?? '');
    $duration      = trim($_POST['duration'] ?? '');


    if (
        $loan_date === '' ||
        $customer_name === '' ||
        $amount <= 0 ||
        $particular === '' ||
        $from_date === '' ||
        $duration === ''
    ) {

        $_SESSION['error'] =
            "Please complete all loan information.";

        header("Location: long_term_loans.php");
        exit();
    }


    $stmt = mysqli_prepare(
        $conn,
        "INSERT INTO long_term_loans
        (
            loan_date,
            customer_name,
            amount,
            particular,
            from_date,
            duration,
            clearance
        )
        VALUES (?, ?, ?, ?, ?, ?, 'Active')"
    );


    mysqli_stmt_bind_param(
        $stmt,
        "ssdsss",
        $loan_date,
        $customer_name,
        $amount,
        $particular,
        $from_date,
        $duration
    );


    mysqli_stmt_execute($stmt);

    mysqli_stmt_close($stmt);


    $_SESSION['success'] =
        "Long term loan added successfully.";

    header("Location: long_term_loans.php");
    exit();
}



// ==========================================
// UPDATE LONG TERM LOAN
// ==========================================

if (isset($_POST['update_loan'])) {

    $id            = (int)($_POST['id'] ?? 0);
    $loan_date     = trim($_POST['loan_date'] ?? '');
    $customer_name = trim($_POST['customer_name'] ?? '');
    $amount        = (float)($_POST['amount'] ?? 0);
    $particular    = trim($_POST['particular'] ?? '');
    $from_date     = trim($_POST['from_date'] ?? '');
    $duration      = trim($_POST['duration'] ?? '');


    if (
        $id <= 0 ||
        $loan_date === '' ||
        $customer_name === '' ||
        $amount <= 0 ||
        $particular === '' ||
        $from_date === '' ||
        $duration === ''
    ) {

        $_SESSION['error'] =
            "Please complete all loan information.";

        header("Location: long_term_loans.php");
        exit();
    }


    $stmt = mysqli_prepare(
        $conn,
        "UPDATE long_term_loans
         SET loan_date = ?,
             customer_name = ?,
             amount = ?,
             particular = ?,
             from_date = ?,
             duration = ?
         WHERE id = ?"
    );


    mysqli_stmt_bind_param(
        $stmt,
        "ssdsssi",
        $loan_date,
        $customer_name,
        $amount,
        $particular,
        $from_date,
        $duration,
        $id
    );


    mysqli_stmt_execute($stmt);

    mysqli_stmt_close($stmt);


    $_SESSION['success'] =
        "Loan updated successfully.";

    header("Location: long_term_loans.php");
    exit();
}



// ==========================================
// CLEAR LOAN
// ==========================================

if (isset($_GET['clear'])) {

    $id = (int)($_GET['clear'] ?? 0);


    if ($id > 0) {

        $clearance_date = date("Y-m-d");


        $stmt = mysqli_prepare(
            $conn,
            "UPDATE long_term_loans
             SET clearance = 'Cleared',
                 clearance_date = ?
             WHERE id = ?"
        );


        mysqli_stmt_bind_param(
            $stmt,
            "si",
            $clearance_date,
            $id
        );


        mysqli_stmt_execute($stmt);

        mysqli_stmt_close($stmt);


        $_SESSION['success'] =
            "Loan marked as cleared.";
    }


    header("Location: long_term_loans.php");
    exit();
}



// ==========================================
// REOPEN LOAN
// ==========================================

if (isset($_GET['reopen'])) {

    $id = (int)($_GET['reopen'] ?? 0);


    if ($id > 0) {

        $stmt = mysqli_prepare(
            $conn,
            "UPDATE long_term_loans
             SET clearance = 'Active',
                 clearance_date = NULL
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
            "Loan reopened successfully.";
    }


    header("Location: long_term_loans.php");
    exit();
}



// ==========================================
// DELETE LOAN
// ==========================================

if (isset($_GET['delete'])) {

    $id = (int)($_GET['delete'] ?? 0);


    if ($id > 0) {

        $stmt = mysqli_prepare(
            $conn,
            "DELETE FROM long_term_loans
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
            "Loan deleted successfully.";
    }


    header("Location: long_term_loans.php");
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
             FROM long_term_loans
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
// SEARCH
// ==========================================

$search = trim($_GET['search'] ?? '');


if ($search !== '') {

    $like = "%" . $search . "%";


    $stmt = mysqli_prepare(
        $conn,
        "SELECT *
         FROM long_term_loans
         WHERE customer_name LIKE ?
         OR particular LIKE ?
         OR duration LIKE ?
         OR clearance LIKE ?
         ORDER BY loan_date DESC, id DESC"
    );


    mysqli_stmt_bind_param(
        $stmt,
        "ssss",
        $like,
        $like,
        $like,
        $like
    );


    mysqli_stmt_execute($stmt);


    $records =
        mysqli_stmt_get_result($stmt);

} else {

    $records = mysqli_query(
        $conn,
        "SELECT *
         FROM long_term_loans
         ORDER BY loan_date DESC, id DESC"
    );
}



// ==========================================
// SUMMARY STATISTICS
// ==========================================

$total_result = mysqli_query(
    $conn,
    "SELECT
        COUNT(*) AS total_loans,
        COALESCE(SUM(amount), 0) AS total_amount,
        COALESCE(
            SUM(
                CASE
                    WHEN clearance = 'Active'
                    THEN amount
                    ELSE 0
                END
            ),
            0
        ) AS active_amount,
        SUM(
            CASE
                WHEN clearance = 'Active'
                THEN 1
                ELSE 0
            END
        ) AS active_loans,
        SUM(
            CASE
                WHEN clearance = 'Cleared'
                THEN 1
                ELSE 0
            END
        ) AS cleared_loans
     FROM long_term_loans"
);


$summary =
    mysqli_fetch_assoc($total_result);


$total_loans =
    (int)$summary['total_loans'];


$total_amount =
    (float)$summary['total_amount'];


$active_amount =
    (float)$summary['active_amount'];


$active_loans =
    (int)$summary['active_loans'];


$cleared_loans =
    (int)$summary['cleared_loans'];

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>T-Shirt Plug - Long Term Loans</title>


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

    color: #222;

}


/* =========================================
   SIDEBAR
========================================= */

.sidebar {

    position: fixed;

    left: 0;

    top: 0;

    width: 250px;

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

    margin-left: 250px;

    padding: 30px;

}


.page-header {

    margin-bottom: 25px;

}


.page-header h1 {

    margin: 0 0 5px 0;

}


.page-header p {

    margin: 0;

    color: #666;

}


/* =========================================
   BOX
========================================= */

.box {

    background: white;

    padding: 25px;

    margin-bottom: 25px;

    border-radius: 15px;

    box-shadow:
        0 5px 15px rgba(0,0,0,0.08);

}


.box h2 {

    margin-top: 0;

}


/* =========================================
   MESSAGES
========================================= */

.success {

    background: #d4edda;

    color: #155724;

    padding: 13px;

    margin-bottom: 20px;

    border-radius: 7px;

}


.error {

    background: #f8d7da;

    color: #721c24;

    padding: 13px;

    margin-bottom: 20px;

    border-radius: 7px;

}


/* =========================================
   SUMMARY CARDS
========================================= */

.summary-grid {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 15px;

    margin-bottom: 25px;

}


.summary-card {

    background: white;

    padding: 20px;

    border-radius: 12px;

    box-shadow:
        0 5px 15px rgba(0,0,0,0.07);

    border-left: 5px solid #0d47a1;

}


.summary-card.active {

    border-left-color: #f59e0b;

}


.summary-card.cleared {

    border-left-color: #198754;

}


.summary-card.money {

    border-left-color: #6f42c1;

}


.summary-title {

    color: #666;

    font-size: 14px;

    margin-bottom: 8px;

}


.summary-number {

    font-size: 24px;

    font-weight: bold;

}


.summary-money {

    font-size: 21px;

    font-weight: bold;

}


/* =========================================
   FORM
========================================= */

.loan-form {

    display: grid;

    grid-template-columns:
        1fr 1fr;

    gap: 16px;

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


input,
textarea {

    padding: 12px;

    border: 1px solid #ccc;

    border-radius: 7px;

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

    border-radius: 7px;

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

    border-radius: 7px;

}


/* =========================================
   SEARCH
========================================= */

.search-form {

    display: flex;

    gap: 8px;

    margin-bottom: 20px;

}


.search-form input {

    flex: 1;

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

    min-width: 1250px;

    border-collapse: collapse;

}


th {

    background: #0d47a1;

    color: white;

}


td,
th {

    padding: 11px;

    border: 1px solid #ddd;

    text-align: center;

}


td.particular {

    text-align: left;

    min-width: 250px;

    white-space: pre-wrap;

}


/* =========================================
   STATUS
========================================= */

.status {

    display: inline-block;

    padding: 6px 10px;

    border-radius: 20px;

    font-size: 13px;

    font-weight: bold;

}


.status.active {

    background: #fff3cd;

    color: #856404;

}


.status.cleared {

    background: #d4edda;

    color: #155724;

}


/* =========================================
   ACTIONS
========================================= */

.edit {

    color: #1565c0;

    text-decoration: none;

    font-weight: bold;

    margin-right: 8px;

}


.delete {

    color: #d32f2f;

    text-decoration: none;

    font-weight: bold;

}


.clear {

    color: #198754;

    text-decoration: none;

    font-weight: bold;

    margin-right: 8px;

}


.reopen {

    color: #f57c00;

    text-decoration: none;

    font-weight: bold;

    margin-right: 8px;

}


.edit:hover,
.delete:hover,
.clear:hover,
.reopen:hover {

    text-decoration: underline;

}


/* =========================================
   MOBILE
========================================= */

@media (max-width: 1000px) {

    .summary-grid {

        grid-template-columns:
            repeat(2, 1fr);

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


    .summary-grid {

        grid-template-columns: 1fr;

    }


    .loan-form {

        grid-template-columns: 1fr;

    }


    .form-group.full {

        grid-column: auto;

    }


    .search-form {

        flex-direction: column;

    }


    .cancel-button {

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



<a href="payments.php">
💰 Payments
</a>
 <a href="expenditures.php">

        💸 Expenditures

    </a>

<a
    href="long_term_loans.php"
    class="active"
>
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
     MAIN
========================================= -->

<div class="main">


<div class="page-header">

<h1>
Long Term Loans
</h1>

<p>
Manage customer loans, repayment periods and clearance.
</p>

</div>



<!-- =========================================
     MESSAGES
========================================= -->

<?php if (isset($_SESSION['success'])): ?>

<div class="success">

<?= htmlspecialchars(
    $_SESSION['success']
) ?>

</div>

<?php unset($_SESSION['success']); ?>

<?php endif; ?>



<?php if (isset($_SESSION['error'])): ?>

<div class="error">

<?= htmlspecialchars(
    $_SESSION['error']
) ?>

</div>

<?php unset($_SESSION['error']); ?>

<?php endif; ?>



<!-- =========================================
     SUMMARY
========================================= -->

<div class="summary-grid">


<div class="summary-card">

<div class="summary-title">
Total Loans
</div>

<div class="summary-number">
<?= $total_loans ?>
</div>

</div>



<div class="summary-card active">

<div class="summary-title">
Active Loans
</div>

<div class="summary-number">
<?= $active_loans ?>
</div>

</div>



<div class="summary-card cleared">

<div class="summary-title">
Cleared Loans
</div>

<div class="summary-number">
<?= $cleared_loans ?>
</div>

</div>



<div class="summary-card money">

<div class="summary-title">
Active Loan Amount
</div>

<div class="summary-money">

TSh <?= number_format(
    $active_amount
) ?>

</div>

</div>

</div>



<!-- =========================================
     ADD / EDIT LOAN
========================================= -->

<div class="box">


<?php if ($edit_record): ?>

<h2>
Edit Long Term Loan
</h2>


<form
    method="POST"
    class="loan-form"
>


<input
    type="hidden"
    name="id"
    value="<?= (int)$edit_record['id'] ?>"
>


<div class="form-group">

<label>
Loan Date
</label>

<input
    type="date"
    name="loan_date"
    value="<?= htmlspecialchars(
        $edit_record['loan_date']
    ) ?>"
    required
>

</div>



<div class="form-group">

<label>
Customer / Borrower
</label>

<input
    type="text"
    name="customer_name"
    value="<?= htmlspecialchars(
        $edit_record['customer_name']
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



<div class="form-group">

<label>
From Date
</label>

<input
    type="date"
    name="from_date"
    value="<?= htmlspecialchars(
        $edit_record['from_date']
    ) ?>"
    required
>

</div>



<div class="form-group">

<label>
Duration
</label>

<input
    type="text"
    name="duration"
    value="<?= htmlspecialchars(
        $edit_record['duration']
    ) ?>"
    placeholder="Example: 12 months"
    required
>

</div>



<div class="form-group full">

<label>
Particular / Notes
</label>

<textarea
    name="particular"
    placeholder="Write details about this loan..."
    required
><?= htmlspecialchars(
    $edit_record['particular']
) ?></textarea>

</div>



<div class="form-group full">

<div>

<button
    type="submit"
    name="update_loan"
>
Update Loan
</button>


<a
    href="long_term_loans.php"
    class="cancel-button"
>
Cancel
</a>

</div>

</div>


</form>


<?php else: ?>


<h2>
Add Long Term Loan
</h2>


<form
    method="POST"
    class="loan-form"
>


<div class="form-group">

<label>
Loan Date
</label>

<input
    type="date"
    name="loan_date"
    value="<?= date('Y-m-d') ?>"
    required
>

</div>



<div class="form-group">

<label>
Customer / Borrower
</label>

<input
    type="text"
    name="customer_name"
    placeholder="Enter customer name"
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
    placeholder="Enter loan amount"
    required
>

</div>



<div class="form-group">

<label>
From Date
</label>

<input
    type="date"
    name="from_date"
    value="<?= date('Y-m-d') ?>"
    required
>

</div>



<div class="form-group">

<label>
Duration
</label>

<input
    type="text"
    name="duration"
    placeholder="Example: 12 months"
    required
>

</div>



<div class="form-group full">

<label>
Particular / Notes
</label>

<textarea
    name="particular"
    placeholder="Write loan details, purpose or other notes..."
    required
></textarea>

</div>



<div class="form-group full">

<button
    type="submit"
    name="add_loan"
>
Save Long Term Loan
</button>

</div>


</form>


<?php endif; ?>

</div>



<!-- =========================================
     LOAN RECORDS
========================================= -->

<div class="box">

<h2>
Loan Records
</h2>


<form
    method="GET"
    class="search-form"
>


<input
    type="text"
    name="search"
    value="<?= htmlspecialchars($search) ?>"
    placeholder="Search customer, particular, duration..."
>


<button type="submit">
Search
</button>


</form>



<div class="table-container">

<table>

<thead>

<tr>

<th>#</th>

<th>Loan Date</th>

<th>Customer</th>

<th>Amount</th>

<th>Particular / Notes</th>

<th>From</th>

<th>Duration</th>

<th>Status</th>

<th>Clearance Date</th>

<th>Actions</th>

</tr>

</thead>


<tbody>


<?php if (
    mysqli_num_rows($records) > 0
): ?>


<?php

$count = 1;

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
    $row['loan_date']
) ?>

</td>


<td>

<strong>

<?= htmlspecialchars(
    $row['customer_name']
) ?>

</strong>

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

<?= htmlspecialchars(
    $row['from_date']
) ?>

</td>


<td>

<?= htmlspecialchars(
    $row['duration']
) ?>

</td>


<td>


<?php if (
    $row['clearance'] === 'Cleared'
): ?>

<span class="status cleared">
✓ Cleared
</span>

<?php else: ?>

<span class="status active">
● Active
</span>

<?php endif; ?>


</td>


<td>

<?php if (
    !empty($row['clearance_date'])
): ?>

<?= htmlspecialchars(
    $row['clearance_date']
) ?>

<?php else: ?>

—

<?php endif; ?>

</td>


<td>


<a
    href="long_term_loans.php?edit=<?= (int)$row['id'] ?>"
    class="edit"
>
Edit
</a>


<?php if (
    $row['clearance'] === 'Active'
): ?>


<a
    href="long_term_loans.php?clear=<?= (int)$row['id'] ?>"
    class="clear"
    onclick="return confirm('Mark this customer loan as cleared?');"
>
Clear
</a>


<?php else: ?>


<a
    href="long_term_loans.php?reopen=<?= (int)$row['id'] ?>"
    class="reopen"
    onclick="return confirm('Reopen this cleared loan?');"
>
Reopen
</a>


<?php endif; ?>


<a
    href="long_term_loans.php?delete=<?= (int)$row['id'] ?>"
    class="delete"
    onclick="return confirm('Are you sure you want to permanently delete this loan?');"
>
Delete
</a>


</td>


</tr>


<?php endwhile; ?>


<?php else: ?>


<tr>

<td colspan="10">

No long term loan records found.

</td>

</tr>


<?php endif; ?>


</tbody>

</table>

</div>

</div>



<!-- =========================================
     TOTAL LOAN INFORMATION
========================================= -->

<div class="box">

<h2>
Loan Financial Summary
</h2>


<p>

<strong>
Total Amount Issued:
</strong>

TSh <?= number_format(
    $total_amount
) ?>

</p>


<p>

<strong>
Currently Active:
</strong>

TSh <?= number_format(
    $active_amount
) ?>

</p>


<p>

<strong>
Cleared Amount:
</strong>

TSh <?= number_format(
    $total_amount - $active_amount
) ?>

</p>

</div>


</div>

</body>

</html>
