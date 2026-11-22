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

        header("Location: expenditure.php");
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

    header("Location: expenditure.php");
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

        header("Location: expenditure.php");
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

    header("Location: expenditure.php");
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


    header("Location: expenditure.php");
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
// GET ALL EXPENDITURES
// ==========================================

$records = mysqli_query(
    $conn,
    "SELECT *
     FROM expenditures
     ORDER BY expenditure_date DESC, id DESC"
);



// ==========================================
// TOTAL EXPENDITURE
// ==========================================

$total_result = mysqli_query(
    $conn,
    "SELECT COALESCE(SUM(amount), 0) AS total
     FROM expenditures"
);


$total_row =
    mysqli_fetch_assoc($total_result);


$total_expenditure =
    (float)$total_row['total'];

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
    href="expenditure.php"
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
    href="expenditure.php"
    class="cancel-button"
>
Cancel
</a>

</div>

</div>


</form>


<?php else: ?>

<h2>
Add Expenditure
</h2>


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

<?php endif; ?>

</div>



<!-- =========================================
     TOTAL EXPENDITURE
========================================= -->

<div class="total-box">

<h3>
Total Expenditure
</h3>

<div class="total-amount">

TSh <?= number_format(
    $total_expenditure
) ?>

</div>

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
    href="expenditure.php?edit=<?= (int)$row['id'] ?>"
    class="edit"
>
Edit
</a>


<a
    href="expenditure.php?delete=<?= (int)$row['id'] ?>"
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
