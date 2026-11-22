<?php

session_start();

if (!isset($_SESSION['admin'])) {

    header("Location: login.php");
    exit();

}

include "database.php";


// ======================================================
// SAVE DAILY REPORT
// ======================================================

if (isset($_POST['save_report'])) {

    $report_date =
        trim($_POST['report_date'] ?? '');

    $total_pieces =
        (int)($_POST['total_pieces'] ?? 0);

    $total_sales =
        (float)($_POST['total_sales'] ?? 0);

    $total_cost =
        (float)($_POST['total_cost'] ?? 0);


    // ==================================================
    // VALIDATION
    // ==================================================

    if ($report_date === '') {

        $_SESSION['error'] =
            "Please select the report date.";

        header("Location: daily_report.php");
        exit();

    }


    if ($total_pieces < 0) {

        $_SESSION['error'] =
            "Total pieces cannot be negative.";

        header("Location: daily_report.php");
        exit();

    }


    if ($total_sales < 0) {

        $_SESSION['error'] =
            "Total sales cannot be negative.";

        header("Location: daily_report.php");
        exit();

    }


    if ($total_cost < 0) {

        $_SESSION['error'] =
            "Total cost cannot be negative.";

        header("Location: daily_report.php");
        exit();

    }


    // ==================================================
    // SAVE REPORT
    // ==================================================

    $stmt = mysqli_prepare(
        $conn,

        "INSERT INTO daily_reports
        (
            report_date,
            total_pieces,
            total_sales,
            total_cost
        )
        VALUES
        (
            ?,
            ?,
            ?,
            ?
        )"
    );


    if (!$stmt) {

        $_SESSION['error'] =
            "Could not prepare daily report: "
            . mysqli_error($conn);

        header("Location: daily_report.php");
        exit();

    }


    mysqli_stmt_bind_param(
        $stmt,
        "sidd",
        $report_date,
        $total_pieces,
        $total_sales,
        $total_cost
    );


    if (mysqli_stmt_execute($stmt)) {

        $_SESSION['success'] =
            "Daily report saved successfully.";

    } else {

        $_SESSION['error'] =
            "Daily report could not be saved: "
            . mysqli_stmt_error($stmt);

    }


    mysqli_stmt_close($stmt);


    header("Location: daily_report.php");
    exit();

}


// ======================================================
// DELETE DAILY REPORT
// ======================================================

if (isset($_GET['delete'])) {

    $id =
        (int)$_GET['delete'];


    if ($id > 0) {

        $stmt = mysqli_prepare(
            $conn,

            "DELETE FROM daily_reports
             WHERE id = ?"
        );


        mysqli_stmt_bind_param(
            $stmt,
            "i",
            $id
        );


        if (mysqli_stmt_execute($stmt)) {

            $_SESSION['success'] =
                "Daily report deleted successfully.";

        } else {

            $_SESSION['error'] =
                "Daily report could not be deleted.";

        }


        mysqli_stmt_close($stmt);

    }


    header("Location: daily_report.php");
    exit();

}


// ======================================================
// GET SAVED DAILY REPORTS
// ======================================================

$reports = mysqli_query(
    $conn,

    "SELECT
        id,
        report_date,
        total_pieces,
        total_sales,
        total_cost,
        created_at

     FROM daily_reports

     ORDER BY
        report_date DESC,
        id DESC"
);


if (!$reports) {

    die(
        "Could not load daily reports: "
        . mysqli_error($conn)
    );

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
T-Shirt Plug - Daily Report
</title>


<style>

/* =====================================================
   GENERAL
===================================================== */

* {
    box-sizing: border-box;
}


body {

    margin: 0;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    background: #f4f7fb;

    color: #222;

}


/* =====================================================
   SIDEBAR
===================================================== */

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

    border-bottom:
        1px solid
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

    border-left:
        5px solid white;

}


/* =====================================================
   MAIN
===================================================== */

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
        0 5px 15px
        rgba(0,0,0,0.08);

}


.box h2 {

    margin-top: 0;

    color: #222;

}


/* =====================================================
   SUCCESS / ERROR
===================================================== */

.success {

    background: #d4edda;

    color: #155724;

    padding: 14px;

    margin-bottom: 20px;

    border-radius: 7px;

    border-left:
        5px solid #198754;

}


.error {

    background: #f8d7da;

    color: #721c24;

    padding: 14px;

    margin-bottom: 20px;

    border-radius: 7px;

    border-left:
        5px solid #dc3545;

}


/* =====================================================
   DAILY REPORT FORM
===================================================== */

.report-form {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 15px;

    align-items: end;

}


.form-group {

    display: flex;

    flex-direction: column;

    gap: 7px;

}


.form-group label {

    font-weight: bold;

    color: #333;

}


.form-group input {

    width: 100%;

    padding: 12px;

    border:
        1px solid #ccc;

    border-radius: 7px;

    font-size: 15px;

    outline: none;

}


.form-group input:focus {

    border-color: #0d47a1;

    box-shadow:
        0 0 0 2px
        rgba(13,71,161,0.1);

}


.save-button {

    padding: 12px;

    background: #0d47a1;

    color: white;

    border: none;

    border-radius: 7px;

    cursor: pointer;

    font-size: 15px;

    font-weight: bold;

}


.save-button:hover {

    background: #083579;

}


/* =====================================================
   SUMMARY CARDS
===================================================== */

.summary {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 20px;

    margin-bottom: 25px;

}


.summary-card {

    padding: 20px;

    border-radius: 12px;

    color: white;

}


.summary-card h3 {

    margin: 0 0 8px 0;

    font-size: 16px;

}


.summary-card .amount {

    font-size: 25px;

    font-weight: bold;

}


.card-pieces {

    background: #0d47a1;

}


.card-sales {

    background: #198754;

}


.card-cost {

    background: #dc3545;

}


/* =====================================================
   TABLE
===================================================== */

.table-container {

    width: 100%;

    overflow-x: auto;

}


table {

    width: 100%;

    min-width: 850px;

    border-collapse: collapse;

}


th {

    background: #0d47a1;

    color: white;

    padding: 13px;

    border: 1px solid #ddd;

}


td {

    padding: 11px;

    border: 1px solid #ddd;

    text-align: center;

}


tbody tr:nth-child(even) {

    background: #f8f9fa;

}


tbody tr:hover {

    background: #eef4ff;

}


/* =====================================================
   DELETE BUTTON
===================================================== */

.delete {

    display: inline-block;

    padding: 7px 12px;

    background: #dc3545;

    color: white;

    text-decoration: none;

    border-radius: 5px;

    font-size: 13px;

}


.delete:hover {

    background: #b02a37;

}


/* =====================================================
   NET AMOUNT
===================================================== */

.net-positive {

    color: #198754;

    font-weight: bold;

}


.net-negative {

    color: #dc3545;

    font-weight: bold;

}


/* =====================================================
   MOBILE
===================================================== */

@media (max-width: 1000px) {

    .report-form {

        grid-template-columns:
            repeat(2, 1fr);

    }

}


@media (max-width: 700px) {

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


    .report-form {

        grid-template-columns: 1fr;

    }


    .summary {

        grid-template-columns: 1fr;

    }

}

</style>

</head>


<body>


<!-- =====================================================
     SIDEBAR
===================================================== -->

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
        📊 Received Clothes
    </a>


    <a href="records.php">
        📁 Saved Records
    </a>


    <a href="expenditure.php">
        💰 Payments
    </a>

 <a href="expenditures.php">

        💸Expenditures

    </a>
    <a href="long_term_loans.php">
        💳 Long Term Loans
    </a>
 <a
        href="daily_report.php"
        class="active"
    >
        📅 Daily Report
    </a>


    <a href="logout.php">
        🚪 Logout
    </a>

</div>


<!-- =====================================================
     MAIN
===================================================== -->

<div class="main">


    <!-- =================================================
         SUCCESS MESSAGE
    ================================================== -->

    <?php if (isset($_SESSION['success'])): ?>

        <div class="success">

            <?= htmlspecialchars(
                $_SESSION['success']
            ) ?>

        </div>

        <?php unset($_SESSION['success']); ?>

    <?php endif; ?>


    <!-- =================================================
         ERROR MESSAGE
    ================================================== -->

    <?php if (isset($_SESSION['error'])): ?>

        <div class="error">

            <?= htmlspecialchars(
                $_SESSION['error']
            ) ?>

        </div>

        <?php unset($_SESSION['error']); ?>

    <?php endif; ?>


    <!-- =================================================
         ADD DAILY REPORT
    ================================================== -->

    <div class="box">

        <h2>
            Add Daily Report
        </h2>


        <form
            method="POST"
            action="daily_report.php"
            class="report-form"
        >


            <!-- DATE -->

            <div class="form-group">

                <label>
                    Report Date
                </label>

                <input
                    type="date"
                    name="report_date"
                    value="<?= date('Y-m-d') ?>"
                    required
                >

            </div>


            <!-- PIECES -->

            <div class="form-group">

                <label>
                    Total Pieces Sold
                </label>

                <input
                    type="number"
                    name="total_pieces"
                    min="0"
                    placeholder="Enter total pieces"
                    required
                >

            </div>


            <!-- SALES -->

            <div class="form-group">

                <label>
                    Total Sales (TSh)
                </label>

                <input
                    type="number"
                    name="total_sales"
                    min="0"
                    step="0.01"
                    placeholder="Enter total sales"
                    required
                >

            </div>


            <!-- COST -->

            <div class="form-group">

                <label>
                    Total Shop Cost (TSh)
                </label>

                <input
                    type="number"
                    name="total_cost"
                    min="0"
                    step="0.01"
                    placeholder="Enter total cost"
                    required
                >

            </div>


            <!-- SAVE -->

            <button
                type="submit"
                name="save_report"
                class="save-button"
            >
                💾 Save Daily Report
            </button>


        </form>

    </div>


    <!-- =================================================
         SAVED DAILY REPORTS
    ================================================== -->

    <div class="box">

        <h2>
            Saved Daily Reports
        </h2>


        <div class="table-container">

            <table>

                <thead>

                    <tr>

                        <th>
                            Date
                        </th>

                        <th>
                            Total Pieces Sold
                        </th>

                        <th>
                            Total Sales
                        </th>

                        <th>
                            Total Cost
                        </th>

                        <th>
                            Net Amount
                        </th>

                        <th>
                            Action
                        </th>

                    </tr>

                </thead>


                <tbody>


                <?php if (
                    mysqli_num_rows($reports) > 0
                ): ?>


                    <?php while (
                        $row =
                        mysqli_fetch_assoc($reports)
                    ): ?>


                        <?php

                        $net_amount =
                            (float)$row['total_sales']
                            -
                            (float)$row['total_cost'];

                        ?>


                        <tr>


                            <!-- DATE -->

                            <td>

                                <?= htmlspecialchars(
                                    $row['report_date']
                                ) ?>

                            </td>


                            <!-- PIECES -->

                            <td>

                                <strong>

                                    <?= number_format(
                                        (int)$row['total_pieces']
                                    ) ?>

                                </strong>

                                pieces

                            </td>


                            <!-- SALES -->

                            <td>

                                <strong>

                                    TSh
                                    <?= number_format(
                                        (float)$row['total_sales'],
                                        2
                                    ) ?>

                                </strong>

                            </td>


                            <!-- COST -->

                            <td>

                                TSh
                                <?= number_format(
                                    (float)$row['total_cost'],
                                    2
                                ) ?>

                            </td>


                            <!-- NET -->

                            <td>

                                <?php if (
                                    $net_amount >= 0
                                ): ?>

                                    <span
                                        class="net-positive"
                                    >

                                        TSh
                                        <?= number_format(
                                            $net_amount,
                                            2
                                        ) ?>

                                    </span>

                                <?php else: ?>

                                    <span
                                        class="net-negative"
                                    >

                                        -TSh
                                        <?= number_format(
                                            abs($net_amount),
                                            2
                                        ) ?>

                                    </span>

                                <?php endif; ?>

                            </td>


                            <!-- DELETE -->

                            <td>

                                <a
                                    href="daily_report.php?delete=<?= (int)$row['id'] ?>"
                                    class="delete"
                                    onclick="return confirm('Are you sure you want to delete this daily report?');"
                                >
                                    Delete
                                </a>

                            </td>


                        </tr>


                    <?php endwhile; ?>


                <?php else: ?>


                    <tr>

                        <td colspan="6">

                            No daily reports have been saved yet.

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
