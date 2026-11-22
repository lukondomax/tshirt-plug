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

$today = date('Y-m-d');
$summary_period = isset($_GET['summary_period']) && is_string($_GET['summary_period'])
    ? $_GET['summary_period']
    : 'today';
$summary_error = '';
$summary_totals = null;
$summary_start_date = $today;
$summary_end_date = $today;
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

if ($summary_period === 'today') {

    $summary_label = 'Today';

} elseif ($summary_period === 'month') {

    $summary_start_date = date('Y-m-01');
    $summary_end_date = date('Y-m-t');
    $summary_label = date('F Y');

} elseif ($summary_period === 'custom') {

    if (
        !$is_valid_date($custom_start_date)
        || !$is_valid_date($custom_end_date)
    ) {

        $summary_error = 'Choose a valid start date and end date for the custom range.';

    } elseif ($custom_start_date > $custom_end_date) {

        $summary_error = 'The custom range start date must be on or before the end date.';

    } else {

        $summary_start_date = $custom_start_date;
        $summary_end_date = $custom_end_date;
        $summary_label =
            date('M j, Y', strtotime($summary_start_date))
            . ' - '
            . date('M j, Y', strtotime($summary_end_date));

    }

} else {

    $summary_error = 'Choose Today, This month, or Custom range.';
    $summary_period = 'today';

}

if ($summary_error === '') {

    $summary_stmt = mysqli_prepare(
        $conn,
        "SELECT
            COALESCE(SUM(total_pieces), 0),
            COALESCE(SUM(total_sales), 0),
            COALESCE(SUM(total_cost), 0)
         FROM daily_reports
         WHERE report_date BETWEEN ? AND ?"
    );

    if (!$summary_stmt) {

        die(
            "Could not prepare daily report summary: "
            . mysqli_error($conn)
        );

    }

    mysqli_stmt_bind_param(
        $summary_stmt,
        "ss",
        $summary_start_date,
        $summary_end_date
    );

    if (!mysqli_stmt_execute($summary_stmt)) {

        die(
            "Could not load daily report summary: "
            . mysqli_stmt_error($summary_stmt)
        );

    }

    mysqli_stmt_bind_result(
        $summary_stmt,
        $summary_pieces,
        $summary_sales,
        $summary_cost
    );
    mysqli_stmt_fetch($summary_stmt);

    $summary_totals = [
        'pieces' => (int)$summary_pieces,
        'sales' => (float)$summary_sales,
        'cost' => (float)$summary_cost
    ];

    mysqli_stmt_close($summary_stmt);

}

$page_size = 10;
$total_result = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM daily_reports"
);

if (!$total_result) {

    die(
        "Could not count daily reports: "
        . mysqli_error($conn)
    );

}

$total_reports = (int)mysqli_fetch_assoc($total_result)['total'];
$total_pages = max(1, (int)ceil($total_reports / $page_size));
$page = max(1, (int)($_GET['page'] ?? 1));
$page = min($page, $total_pages);
$offset = ($page - 1) * $page_size;
$pagination_params = [
    'summary_period' => $summary_period
];

if ($summary_period === 'custom') {

    $pagination_params['start_date'] = $custom_start_date;
    $pagination_params['end_date'] = $custom_end_date;

}

$previous_page_url = 'daily_report.php?' . http_build_query(
    array_merge($pagination_params, ['page' => max(1, $page - 1)])
);
$next_page_url = 'daily_report.php?' . http_build_query(
    array_merge($pagination_params, ['page' => min($total_pages, $page + 1)])
);

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
        id DESC
     LIMIT $page_size OFFSET $offset"
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

.table-heading {

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 12px;

    margin-bottom: 18px;

}


.table-heading h2 {

    margin: 0;

}


.add-report-toggle {

    width: 42px;

    height: 42px;

    display: inline-flex;

    align-items: center;

    justify-content: center;

    border: none;

    border-radius: 50%;

    background: #0d47a1;

    color: white;

    font-size: 26px;

    line-height: 1;

    cursor: pointer;

}


.add-report-toggle:hover {

    background: #083579;

}


.add-report-panel {

    margin-bottom: 24px;

    padding: 20px;

    border-radius: 10px;

    background: #f8f9fa;

}


.add-report-panel[hidden] {

    display: none;

}


.add-report-panel h3 {

    margin: 0 0 16px;

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

.summary-filter {

    display: flex;

    flex-wrap: wrap;

    align-items: end;

    gap: 12px;

    margin: 18px 0;

}


.summary-filter [hidden] {

    display: none;

}


.summary-filter .form-group {

    min-width: 180px;

}


.summary-filter select,
.summary-filter input,
.summary-filter button {

    padding: 10px;

    border: 1px solid #ccc;

    border-radius: 6px;

    font-size: 14px;

}


.summary-filter button {

    background: #0d47a1;

    color: white;

    border: none;

    cursor: pointer;

    font-weight: bold;

}


.summary-filter button:hover {

    background: #083579;

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

.pagination {

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 12px;

    margin-top: 16px;

}


.pagination a,
.pagination span {

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


    <a href="payments.php">
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
         DAILY REPORT SUMMARY
    ================================================== -->

    <div class="box">

        <h2>Daily Report Summary</h2>

        <form method="GET" action="daily_report.php" class="summary-filter">

            <div class="form-group">
                <label for="summary-period">Show totals for</label>
                <select id="summary-period" name="summary_period">
                    <option value="today" <?= $summary_period === 'today' ? 'selected' : '' ?>>Today</option>
                    <option value="month" <?= $summary_period === 'month' ? 'selected' : '' ?>>This month</option>
                    <option value="custom" <?= $summary_period === 'custom' ? 'selected' : '' ?>>Custom range</option>
                </select>
            </div>

            <div
                class="form-group"
                id="custom-date-range"
                <?= $summary_period === 'custom' ? '' : 'hidden' ?>
            >
                <label for="start-date">Start date</label>
                <input
                    id="start-date"
                    type="date"
                    name="start_date"
                    value="<?= htmlspecialchars($custom_start_date) ?>"
                    <?= $summary_period === 'custom' ? 'required' : '' ?>
                >
            </div>

            <div
                class="form-group"
                id="custom-end-date"
                <?= $summary_period === 'custom' ? '' : 'hidden' ?>
            >
                <label for="end-date">End date</label>
                <input
                    id="end-date"
                    type="date"
                    name="end_date"
                    value="<?= htmlspecialchars($custom_end_date) ?>"
                    <?= $summary_period === 'custom' ? 'required' : '' ?>
                >
            </div>

            <button type="submit">Apply filter</button>

        </form>

        <?php if ($summary_error !== ''): ?>

            <div class="error" role="alert">
                <?= htmlspecialchars($summary_error) ?>
            </div>

        <?php else: ?>

            <p>Totals for <?= htmlspecialchars($summary_label) ?></p>

            <div class="summary">

                <div class="summary-card card-pieces">
                    <h3>Total Pieces</h3>
                    <div class="amount">
                        <?= number_format($summary_totals['pieces']) ?>
                    </div>
                </div>

                <div class="summary-card card-sales">
                    <h3>Total Sales</h3>
                    <div class="amount">
                        TSh <?= number_format($summary_totals['sales'], 2) ?>
                    </div>
                </div>

                <div class="summary-card card-cost">
                    <h3>Total Cost</h3>
                    <div class="amount">
                        TSh <?= number_format($summary_totals['cost'], 2) ?>
                    </div>
                </div>

            </div>

        <?php endif; ?>

    </div>


    <!-- =================================================
         SAVED DAILY REPORTS
    ================================================== -->

    <div class="box">

        <div class="table-heading">

            <h2>Saved Daily Reports</h2>

            <button
                type="button"
                class="add-report-toggle"
                id="add-report-toggle"
                aria-label="Add daily report"
                aria-expanded="false"
                aria-controls="add-report-panel"
            >
                <span aria-hidden="true">+</span>
            </button>

        </div>


        <div class="add-report-panel" id="add-report-panel" hidden>

            <h3>Add Daily Report</h3>

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

        <?php if ($total_reports > 0): ?>

            <?php
            $first_report = $offset + 1;
            $last_report = min($offset + $page_size, $total_reports);
            ?>

            <nav class="pagination" aria-label="Daily report pages">

                <span>
                    Showing <?= $first_report ?>–<?= $last_report ?>
                    of <?= $total_reports ?> reports
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
const summaryPeriod = document.getElementById('summary-period');
const customDateRange = document.getElementById('custom-date-range');
const customEndDate = document.getElementById('custom-end-date');
const startDateInput = document.getElementById('start-date');
const endDateInput = document.getElementById('end-date');

summaryPeriod.addEventListener('change', function () {
    const isCustom = summaryPeriod.value === 'custom';
    customDateRange.hidden = !isCustom;
    customEndDate.hidden = !isCustom;
    startDateInput.required = isCustom;
    endDateInput.required = isCustom;
});

const addReportToggle = document.getElementById('add-report-toggle');
const addReportPanel = document.getElementById('add-report-panel');
const addReportIcon = addReportToggle.querySelector('span');

addReportToggle.addEventListener('click', function () {
    const isExpanded = addReportToggle.getAttribute('aria-expanded') === 'true';
    addReportToggle.setAttribute('aria-expanded', String(!isExpanded));
    addReportToggle.setAttribute(
        'aria-label',
        isExpanded ? 'Add daily report' : 'Close add daily report form'
    );
    addReportIcon.textContent = isExpanded ? '+' : '-';
    addReportPanel.hidden = isExpanded;
});
</script>

</body>

</html>
