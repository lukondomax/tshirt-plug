<?php

session_start();

// ==========================================
// CHECK MAIN ADMIN LOGIN
// ==========================================

if(!isset($_SESSION['admin'])){

    header("Location: login.php");
    exit();

}

include "database.php";


// ==========================================
// DASHBOARD PASSWORD
// ==========================================

$dashboard_password = "1979";

$dashboard_unlocked = false;


// ==========================================
// CHECK IF DASHBOARD IS ALREADY UNLOCKED
// ==========================================

if(
    isset($_SESSION['dashboard_unlocked']) &&
    $_SESSION['dashboard_unlocked'] === true
){

    $dashboard_unlocked = true;

}


// ==========================================
// DASHBOARD LOGOUT
// ==========================================

if(isset($_GET['dashboard_logout'])){

    unset($_SESSION['dashboard_unlocked']);

    $dashboard_unlocked = false;

}


// ==========================================
// CHECK DASHBOARD PASSWORD
// ==========================================

if(isset($_POST['dashboard_password'])){

    if($_POST['dashboard_password'] === $dashboard_password){

        session_regenerate_id(true);

        $_SESSION['dashboard_unlocked'] = true;

        $dashboard_unlocked = true;

    }else{

        $password_error = "Incorrect password!";

    }

}


// ==========================================
// DASHBOARD STATISTICS
// ==========================================

$stats_period_input = $_GET['stats_period'] ?? 'day';
$stats_start_input = $_GET['stats_start_date'] ?? date('Y-m-d');
$stats_end_input = $_GET['stats_end_date'] ?? date('Y-m-d');
$stats_period = is_string($stats_period_input) ? $stats_period_input : '';
$stats_start_input = is_string($stats_start_input) ? trim($stats_start_input) : '';
$stats_end_input = is_string($stats_end_input) ? trim($stats_end_input) : '';
$stats_period_error = '';
$stats_period_valid = true;
$today = date('Y-m-d');
$stats_start_date = $today;
$stats_end_date = $today;
$stats_period_label = 'Today';

if ($stats_period === 'week') {
    $stats_start_date = (new DateTimeImmutable('monday this week'))->format('Y-m-d');
    $stats_period_label = 'This week';
} elseif ($stats_period === 'month') {
    $stats_start_date = date('Y-m-01');
    $stats_period_label = 'This month';
} elseif ($stats_period === 'custom') {
    $start_parts = explode('-', $stats_start_input);
    $end_parts = explode('-', $stats_end_input);
    $valid_start = count($start_parts) === 3 &&
        checkdate((int)$start_parts[1], (int)$start_parts[2], (int)$start_parts[0]) &&
        preg_match('/^\d{4}-\d{2}-\d{2}$/', $stats_start_input);
    $valid_end = count($end_parts) === 3 &&
        checkdate((int)$end_parts[1], (int)$end_parts[2], (int)$end_parts[0]) &&
        preg_match('/^\d{4}-\d{2}-\d{2}$/', $stats_end_input);

    if (!$valid_start || !$valid_end || $stats_start_input > $stats_end_input) {
        $stats_period_valid = false;
        $stats_period_error = 'Choose a valid custom start and end date.';
        $stats_period_label = 'Custom range';
    } else {
        $stats_start_date = $stats_start_input;
        $stats_end_date = $stats_end_input;
        $stats_period_label = date('M j, Y', strtotime($stats_start_date))
            . ' - '
            . date('M j, Y', strtotime($stats_end_date));
    }
} elseif ($stats_period !== 'day') {
    $stats_period_valid = false;
    $stats_period_error = 'Choose Day, Week, Month, or Custom.';
}

if($dashboard_unlocked){

    $total_items = 0;
    $total_revenue = 0.0;
    $daily_report_cost = 0.0;
    $business_expenditures = 0.0;
    $total_expenses = 0.0;
    $net_profit = 0.0;

    if ($stats_period_valid) {
        $stats_stmt = mysqli_prepare(
            $conn,
            "SELECT
                (SELECT COALESCE(SUM(quantity), 0)
                 FROM products
                 WHERE sale_date BETWEEN ? AND ?) AS total_items,
                (SELECT COALESCE(SUM(total_sales), 0)
                 FROM daily_reports
                 WHERE report_date BETWEEN ? AND ?) AS total_revenue,
                (SELECT COALESCE(SUM(total_cost), 0)
                 FROM daily_reports
                 WHERE report_date BETWEEN ? AND ?) AS daily_report_cost,
                (SELECT COALESCE(SUM(amount), 0)
                 FROM business_expenditures
                 WHERE expenditure_date BETWEEN ? AND ?) AS business_expenditures"
        );

        if (!$stats_stmt) {
            die("Could not prepare dashboard statistics: " . mysqli_error($conn));
        }

        mysqli_stmt_bind_param(
            $stats_stmt,
            "ssssssss",
            $stats_start_date,
            $stats_end_date,
            $stats_start_date,
            $stats_end_date,
            $stats_start_date,
            $stats_end_date,
            $stats_start_date,
            $stats_end_date
        );

        if (!mysqli_stmt_execute($stats_stmt)) {
            die("Could not load dashboard statistics: " . mysqli_stmt_error($stats_stmt));
        }

        $stats = mysqli_fetch_assoc(mysqli_stmt_get_result($stats_stmt));
        mysqli_stmt_close($stats_stmt);
        if (!$stats) {
            die("Could not load dashboard statistics.");
        }

        $total_items = (int)$stats['total_items'];
        $total_revenue = (float)$stats['total_revenue'];
        $daily_report_cost = (float)$stats['daily_report_cost'];
        $business_expenditures = (float)$stats['business_expenditures'];
        $total_expenses = $daily_report_cost + $business_expenditures;
        $net_profit = $total_revenue - $total_expenses;
    }

}

$chart_period_input = $_GET['chart_period'] ?? 'week';
$chart_start_input = $_GET['chart_start_date'] ?? date('Y-m-d');
$chart_end_input = $_GET['chart_end_date'] ?? date('Y-m-d');
$chart_period = is_string($chart_period_input) ? $chart_period_input : '';
$chart_start_input = is_string($chart_start_input) ? trim($chart_start_input) : '';
$chart_end_input = is_string($chart_end_input) ? trim($chart_end_input) : '';
$chart_period_valid = true;
$chart_period_error = '';
$chart_period_label = 'Last 12 weeks';
$chart_start_date = (new DateTimeImmutable('monday this week'))->modify('-11 weeks')->format('Y-m-d');
$chart_end_date = $today;

if ($chart_period === 'month') {
    $chart_start_date = (new DateTimeImmutable(date('Y-m-01')))->modify('-11 months')->format('Y-m-d');
    $chart_period_label = 'Last 12 months';
} elseif ($chart_period === 'custom') {
    $chart_start_parts = explode('-', $chart_start_input);
    $chart_end_parts = explode('-', $chart_end_input);
    $valid_chart_start = count($chart_start_parts) === 3 &&
        checkdate((int)$chart_start_parts[1], (int)$chart_start_parts[2], (int)$chart_start_parts[0]) &&
        preg_match('/^\d{4}-\d{2}-\d{2}$/', $chart_start_input);
    $valid_chart_end = count($chart_end_parts) === 3 &&
        checkdate((int)$chart_end_parts[1], (int)$chart_end_parts[2], (int)$chart_end_parts[0]) &&
        preg_match('/^\d{4}-\d{2}-\d{2}$/', $chart_end_input);

    if (!$valid_chart_start || !$valid_chart_end || $chart_start_input > $chart_end_input) {
        $chart_period_valid = false;
        $chart_period_error = 'Choose a valid custom chart start and end date.';
        $chart_period_label = 'Custom range';
    } else {
        $chart_start_date = $chart_start_input;
        $chart_end_date = $chart_end_input;
        $chart_period_label = date('M j, Y', strtotime($chart_start_date))
            . ' - '
            . date('M j, Y', strtotime($chart_end_date));
    }
} elseif ($chart_period !== 'week') {
    $chart_period_valid = false;
    $chart_period_error = 'Choose Weekly, Monthly, or Custom for the chart.';
}

$chart_data = [];
$chart_has_activity = false;
$chart_granularity = $chart_period === 'week' ? 'week' : ($chart_period === 'month' ? 'month' : 'day');
$chart_granularity_label = $chart_granularity === 'week'
    ? 'Weekly'
    : ($chart_granularity === 'month' ? 'Monthly' : 'Daily');
$chart_start_object = null;
$chart_end_object = null;
$chart_svg_revenue_points = '';
$chart_svg_cost_points = '';
$chart_svg_ticks = [];
$chart_svg_label_step = 1;

if ($dashboard_unlocked && $chart_period_valid) {
    $chart_start_object = new DateTimeImmutable($chart_start_date);
    $chart_end_object = new DateTimeImmutable($chart_end_date);
    $chart_span_days = (int)$chart_start_object->diff($chart_end_object)->days + 1;

    if ($chart_period === 'custom' && $chart_span_days > 365) {
        $chart_granularity = 'month';
        $chart_granularity_label = 'Monthly';
    } elseif ($chart_period === 'custom' && $chart_span_days > 31) {
        $chart_granularity = 'week';
        $chart_granularity_label = 'Weekly';
    }

    $chart_buckets = [];
    $chart_bucket_indexes = [];

    if ($chart_granularity === 'day') {
        for ($date = $chart_start_object; $date <= $chart_end_object; $date = $date->modify('+1 day')) {
            $key = $date->format('Y-m-d');
            $chart_bucket_indexes[$key] = count($chart_buckets);
            $chart_buckets[] = [
                'key' => $key,
                'label' => $date->format('M j'),
                'revenue' => 0.0,
                'cost' => 0.0
            ];
        }
    } elseif ($chart_granularity === 'week') {
        $bucket_index = 0;
        for ($date = $chart_start_object; $date <= $chart_end_object; $date = $date->modify('+7 days')) {
            $key = (string)$bucket_index;
            $bucket_end = $date->modify('+6 days');
            if ($bucket_end > $chart_end_object) {
                $bucket_end = $chart_end_object;
            }
            $chart_bucket_indexes[$key] = count($chart_buckets);
            $chart_buckets[] = [
                'key' => $key,
                'label' => $date->format('M j') . ' - ' . $bucket_end->format('M j'),
                'revenue' => 0.0,
                'cost' => 0.0
            ];
            $bucket_index++;
        }
    } else {
        for ($date = $chart_start_object->modify('first day of this month');
             $date <= $chart_end_object;
             $date = $date->modify('+1 month')) {
            $key = $date->format('Y-m');
            $chart_bucket_indexes[$key] = count($chart_buckets);
            $chart_buckets[] = [
                'key' => $key,
                'label' => $date->format('M Y'),
                'revenue' => 0.0,
                'cost' => 0.0
            ];
        }
    }

    $chart_stmt = mysqli_prepare(
        $conn,
        "SELECT activity_date,
                SUM(revenue) AS revenue,
                SUM(report_cost) AS report_cost,
                SUM(expenditure) AS expenditure
         FROM (
            SELECT report_date AS activity_date,
                   total_sales AS revenue,
                   total_cost AS report_cost,
                   0 AS expenditure
            FROM daily_reports
            WHERE report_date BETWEEN ? AND ?
            UNION ALL
            SELECT expenditure_date AS activity_date,
                   0 AS revenue,
                   0 AS report_cost,
                   amount AS expenditure
            FROM business_expenditures
            WHERE expenditure_date BETWEEN ? AND ?
         ) AS financial_activity
         GROUP BY activity_date
         ORDER BY activity_date"
    );

    if (!$chart_stmt) {
        die("Could not prepare revenue and cost trend: " . mysqli_error($conn));
    }

    mysqli_stmt_bind_param(
        $chart_stmt,
        "ssss",
        $chart_start_date,
        $chart_end_date,
        $chart_start_date,
        $chart_end_date
    );
    if (!mysqli_stmt_execute($chart_stmt)) {
        die("Could not load revenue and cost trend: " . mysqli_stmt_error($chart_stmt));
    }

    $chart_result = mysqli_stmt_get_result($chart_stmt);
    while ($activity = mysqli_fetch_assoc($chart_result)) {
        $activity_date = new DateTimeImmutable($activity['activity_date']);
        if ($chart_granularity === 'week') {
            $days_from_start = (int)$chart_start_object->diff($activity_date)->days;
            $bucket_key = (string)intdiv($days_from_start, 7);
        } elseif ($chart_granularity === 'month') {
            $bucket_key = $activity_date->format('Y-m');
        } else {
            $bucket_key = $activity_date->format('Y-m-d');
        }

        if (!isset($chart_bucket_indexes[$bucket_key])) {
            continue;
        }
        $bucket_index = $chart_bucket_indexes[$bucket_key];
        $chart_buckets[$bucket_index]['revenue'] += (float)$activity['revenue'];
        $chart_buckets[$bucket_index]['cost'] +=
            (float)$activity['report_cost'] + (float)$activity['expenditure'];
        $chart_has_activity = true;
    }
    mysqli_stmt_close($chart_stmt);

    $chart_data = $chart_buckets;
    if ($chart_data) {
        $chart_max_value = 1.0;
        foreach ($chart_data as $point) {
            $chart_max_value = max($chart_max_value, (float)$point['revenue'], (float)$point['cost']);
        }
        $chart_max_value *= 1.1;
        $chart_plot_left = 100;
        $chart_plot_right = 740;
        $chart_plot_top = 18;
        $chart_plot_bottom = 258;
        $chart_plot_width = $chart_plot_right - $chart_plot_left;
        $chart_plot_height = $chart_plot_bottom - $chart_plot_top;

        for ($tick = 0; $tick <= 4; $tick++) {
            $ratio = $tick / 4;
            $chart_svg_ticks[] = [
                'y' => $chart_plot_top + $chart_plot_height * $ratio,
                'label' => 'TSh ' . number_format($chart_max_value * (1 - $ratio))
            ];
        }

        $chart_svg_label_step = max(1, (int)ceil(count($chart_data) / 6));
        $revenue_coordinates = [];
        $cost_coordinates = [];
        foreach ($chart_data as $index => $point) {
            $x = count($chart_data) === 1
                ? $chart_plot_left + $chart_plot_width / 2
                : $chart_plot_left + $chart_plot_width * $index / (count($chart_data) - 1);
            $revenue_y = $chart_plot_bottom - $chart_plot_height * (float)$point['revenue'] / $chart_max_value;
            $cost_y = $chart_plot_bottom - $chart_plot_height * (float)$point['cost'] / $chart_max_value;
            $revenue_coordinates[] = round($x, 1) . ',' . round($revenue_y, 1);
            $cost_coordinates[] = round($x, 1) . ',' . round($cost_y, 1);
        }
        $chart_svg_revenue_points = implode(' ', $revenue_coordinates);
        $chart_svg_cost_points = implode(' ', $cost_coordinates);
    }
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Max Smart | Business Dashboard</title>

<style>

/* ==========================================
   GENERAL
========================================== */

*{

    box-sizing:border-box;

}

body{

    font-family:Arial, Helvetica, sans-serif;

    background:#f3f6fb;

    margin:0;

    color:#263238;

}


/* ==========================================
   SIDEBAR
========================================== */

.sidebar{

    position:fixed;

    top:0;

    left:0;

    width:250px;

    height:100vh;

    background:#0d47a1;

    color:white;

    overflow-y:auto;

    box-shadow:3px 0 15px rgba(0,0,0,0.08);

    z-index:1000;

}


.sidebar h2{

    text-align:center;

    padding:25px 15px;

    margin:0;

    font-size:22px;

    letter-spacing:1px;

    border-bottom:1px solid rgba(255,255,255,0.15);

}


.sidebar .sidebar-subtitle{

    text-align:center;

    font-size:11px;

    color:#bbd5f5;

    padding:10px;

    letter-spacing:1px;

}


.sidebar a{

    display:block;

    padding:15px 20px;

    color:white;

    text-decoration:none;

    font-size:14px;

    border-left:4px solid transparent;

    transition:0.3s;

}


.sidebar a:hover{

    background:#1565c0;

    border-left:4px solid white;

    padding-left:25px;

}


.sidebar a.active{

    background:#1565c0;

    border-left:4px solid white;

    font-weight:bold;

}


.sidebar-divider{

    height:1px;

    background:rgba(255,255,255,0.15);

    margin:12px 15px;

}


.sidebar-footer{

    padding:20px;

    color:#bbd5f5;

    font-size:11px;

    text-align:center;

}


/* ==========================================
   MAIN CONTENT
========================================== */

.main{

    margin-left:250px;

    padding:30px;

    min-height:100vh;

}


.top-header{

    display:flex;

    justify-content:space-between;

    align-items:center;

    flex-wrap:wrap;

    gap:15px;

    margin-bottom:30px;

}


.welcome-text h1{

    margin:0 0 8px;

    font-size:27px;

    color:#172b4d;

}


.welcome-text p{

    margin:0;

    color:#77818f;

    font-size:14px;

}


.header-date{

    background:white;

    padding:13px 18px;

    border-radius:10px;

    box-shadow:0 3px 12px rgba(0,0,0,0.05);

    color:#0d47a1;

    font-weight:bold;

    font-size:13px;

}


/* ==========================================
   DASHBOARD INTRO
========================================== */

.dashboard-banner{

    background:linear-gradient(120deg,#0d47a1,#1976d2);

    color:white;

    padding:28px;

    border-radius:15px;

    margin-bottom:30px;

    box-shadow:0 6px 20px rgba(13,71,161,0.15);

}


.dashboard-banner h2{

    margin:0 0 10px;

    font-size:23px;

}


.dashboard-banner p{

    margin:0;

    color:#e0edff;

    font-size:14px;

    line-height:1.7;

}


/* ==========================================
   DASHBOARD SECTION
========================================== */

.section-heading{

    display:flex;

    justify-content:space-between;

    align-items:center;

    flex-wrap:wrap;

    margin-bottom:18px;

}


.section-heading h2{

    font-size:19px;

    margin:0;

    color:#263238;

}


.section-heading span{

    font-size:12px;

    color:#7a8795;

}

.stats-filter{

    display:flex;

    align-items:flex-end;

    flex-wrap:wrap;

    gap:12px;

    padding:16px;

    margin-bottom:18px;

    background:#fff;

    border-radius:12px;

    box-shadow:0 4px 15px rgba(0,0,0,0.05);

}

.stats-filter label{

    display:flex;

    flex-direction:column;

    gap:6px;

    color:#44515f;

    font-size:13px;

    font-weight:600;

}

.stats-filter select,
.stats-filter input{

    min-width:170px;

    padding:10px;

    border:1px solid #cbd5e1;

    border-radius:7px;

    font:inherit;

}

.stats-filter-range{

    display:flex;

    flex-wrap:wrap;

    gap:12px;

}

.stats-filter-range[hidden]{

    display:none;

}

.stats-filter button{

    padding:11px 18px;

    border:0;

    border-radius:7px;

    background:#0d47a1;

    color:#fff;

    font-weight:600;

    cursor:pointer;

}

.stats-filter-error{

    padding:12px 14px;

    margin-bottom:18px;

    border-radius:8px;

    background:#fff3cd;

    color:#856404;

}


/* ==========================================
   DASHBOARD CARDS
========================================== */

.cards{

    display:grid;

    grid-template-columns:repeat(4,1fr);

    gap:18px;

    margin-bottom:30px;

}


.card{

    background:white;

    padding:22px;

    border-radius:14px;

    box-shadow:0 4px 15px rgba(0,0,0,0.05);

    border-top:4px solid #0d47a1;

    transition:0.3s;

    min-width:0;

}


.card:hover{

    transform:translateY(-5px);

    box-shadow:0 8px 22px rgba(0,0,0,0.09);

}


.card.revenue-card{

    border-top-color:#198754;

}


.card.expense-card{

    border-top-color:#dc3545;

}


.card.net-card{

    border-top-color:#6f42c1;

}


.card-icon{

    width:43px;

    height:43px;

    border-radius:11px;

    display:flex;

    align-items:center;

    justify-content:center;

    background:#e8f0fe;

    font-size:21px;

    margin-bottom:15px;

}


.revenue-card .card-icon{

    background:#e4f5eb;

}


.expense-card .card-icon{

    background:#fde8e8;

}


.net-card .card-icon{

    background:#eee8ff;

}


.card h3{

    font-size:13px;

    color:#7a8795;

    margin:0 0 12px;

    font-weight:normal;

}


.card h1{

    color:#172b4d;

    font-size:25px;

    margin:0;

    overflow-wrap:anywhere;

}


.card.revenue-card h1{

    color:#198754;

}


.card.expense-card h1{

    color:#dc3545;

}


.card.net-card h1{

    color:#6f42c1;

}


.card-description{

    font-size:11px;

    color:#9aa3ad;

    margin-top:12px;

}


/* ==========================================
   PROFIT INFORMATION
========================================== */

.financial-overview-grid{

    display:grid;

    grid-template-columns:minmax(0,1.6fr) minmax(280px,1fr);

    gap:18px;

    align-items:start;

    margin-bottom:30px;

}

.revenue-trend,
.profit-information{

    background:white;

    padding:22px;

    border-radius:13px;

    margin-bottom:30px;

    box-shadow:0 4px 15px rgba(0,0,0,0.05);

}

.revenue-trend h3{

    margin:0 0 15px;

    font-size:16px;

}

.trend-filter{

    display:flex;

    align-items:flex-end;

    flex-wrap:wrap;

    gap:10px;

    margin-bottom:12px;

}

.trend-filter label{

    display:flex;

    flex-direction:column;

    gap:5px;

    color:#596575;

    font-size:12px;

}

.trend-filter select,
.trend-filter input{

    min-width:130px;

    padding:8px;

    border:1px solid #cbd5e1;

    border-radius:6px;

    font:inherit;

}

.trend-filter-range{

    display:flex;

    flex-wrap:wrap;

    gap:10px;

}

.trend-filter-range[hidden]{

    display:none;

}

.trend-filter button{

    padding:9px 13px;

    border:0;

    border-radius:6px;

    background:#0d47a1;

    color:#fff;

    font-weight:600;

    cursor:pointer;

}

.trend-legend{

    display:flex;

    flex-wrap:wrap;

    gap:16px;

    margin:6px 0 10px;

    color:#596575;

    font-size:12px;

}

.trend-legend span{

    display:inline-flex;

    align-items:center;

    gap:7px;

}

.trend-legend i{

    width:18px;

    height:3px;

    border-radius:2px;

    background:#0d47a1;

}

.trend-legend .cost-key{

    background:#dc3545;

}

.trend-chart{

    display:block;

    width:100%;

    height:auto;

    overflow:visible;

}

.trend-chart text{

    fill:#758195;

    font-size:11px;

    font-family:Arial, Helvetica, sans-serif;

}

.trend-chart circle{

    cursor:help;

}

.trend-empty,
.trend-error{

    padding:20px 10px;

    border-radius:8px;

    background:#f4f7fb;

    color:#667085;

    font-size:13px;

}

.trend-error{

    background:#fff3cd;

    color:#856404;

}


.profit-information h3{

    margin:0 0 15px;

    font-size:16px;

}


.profit-line{

    display:flex;

    justify-content:space-between;

    padding:12px 0;

    border-bottom:1px solid #edf0f4;

    font-size:14px;

}


.profit-line:last-child{

    border-bottom:none;

}


.profit-line strong{

    color:#0d47a1;

}


/* ==========================================
   QUICK ACTIONS
========================================== */

.quick-actions{

    margin-bottom:30px;

}


.quick-grid{

    display:grid;

    grid-template-columns:repeat(3,1fr);

    gap:15px;

}


.quick-action{

    background:white;

    padding:20px;

    border-radius:12px;

    text-decoration:none;

    color:#263238;

    box-shadow:0 4px 15px rgba(0,0,0,0.05);

    border:1px solid #edf0f4;

    transition:0.3s;

}


.quick-action:hover{

    border-color:#0d47a1;

    transform:translateY(-3px);

}


.quick-icon{

    font-size:25px;

    margin-bottom:12px;

}


.quick-action h3{

    margin:0 0 7px;

    font-size:15px;

}


.quick-action p{

    margin:0;

    font-size:12px;

    color:#84909d;

    line-height:1.5;

}


/* ==========================================
   PASSWORD BOX
========================================== */

.password-box{

    background:white;

    padding:35px;

    max-width:450px;

    border-radius:16px;

    box-shadow:0 8px 30px rgba(0,0,0,0.07);

    margin:50px auto;

    text-align:center;

    border-top:5px solid #0d47a1;

}


.lock-icon{

    width:75px;

    height:75px;

    border-radius:50%;

    background:#e8f0fe;

    display:flex;

    align-items:center;

    justify-content:center;

    margin:0 auto 20px;

    font-size:35px;

}


.password-box h2{

    color:#172b4d;

    margin:0 0 12px;

}


.password-box p{

    color:#7a8795;

    font-size:14px;

    line-height:1.6;

}


.password-box label{

    display:block;

    text-align:left;

    font-size:13px;

    font-weight:bold;

    margin-bottom:8px;

}


.password-box input{

    width:100%;

    padding:14px;

    box-sizing:border-box;

    border:1px solid #dce2ea;

    border-radius:8px;

    margin-bottom:15px;

    font-size:16px;

}


.password-box input:focus{

    outline:none;

    border-color:#0d47a1;

    box-shadow:0 0 0 3px rgba(13,71,161,0.1);

}


.password-box button{

    background:#0d47a1;

    color:white;

    border:none;

    padding:14px 20px;

    border-radius:8px;

    cursor:pointer;

    width:100%;

    font-size:15px;

    font-weight:bold;

}


.password-box button:hover{

    background:#1565c0;

}


/* ==========================================
   ERROR
========================================== */

.error{

    background:#fde8e8;

    color:#b42318;

    padding:12px;

    margin-bottom:15px;

    border-radius:8px;

    font-size:13px;

}


/* ==========================================
   DASHBOARD LOGOUT BUTTON
========================================== */

.dashboard-logout{

    display:inline-block;

    margin-top:10px;

    background:#fff0f0;

    color:#c62828;

    padding:12px 20px;

    border-radius:8px;

    text-decoration:none;

    font-size:13px;

    font-weight:bold;

    border:1px solid #f5c6c6;

    transition:0.3s;

}


.dashboard-logout:hover{

    background:#c62828;

    color:white;

}


/* ==========================================
   FOOTER
========================================== */

.dashboard-footer{

    text-align:center;

    color:#9aa3ad;

    font-size:12px;

    padding:25px 10px;

    border-top:1px solid #e5eaf0;

    margin-top:40px;

}


/* ==========================================
   TABLET
========================================== */

@media(max-width:1100px){

    .cards{

        grid-template-columns:repeat(2,1fr);

    }

    .quick-grid{

        grid-template-columns:repeat(2,1fr);

    }

}


/* ==========================================
   SMALL SCREEN
========================================== */

@media(max-width:800px){

    .sidebar{

        width:200px;

    }

    .main{

        margin-left:200px;

        padding:20px;

    }

    .cards{

        grid-template-columns:repeat(2,1fr);

    }

    .financial-overview-grid{

        grid-template-columns:1fr;

    }

}


/* ==========================================
   MOBILE
========================================== */

@media(max-width:600px){

    .sidebar{

        position:relative;

        width:100%;

        height:auto;

    }

    .sidebar h2{

        padding:16px;

    }

    .sidebar a{

        display:inline-block;

        padding:11px;

        font-size:12px;

    }

    .sidebar-footer{

        display:none;

    }

    .main{

        margin-left:0;

        padding:15px;

    }

    .top-header{

        align-items:flex-start;

    }

    .welcome-text h1{

        font-size:23px;

    }

    .header-date{

        width:100%;

    }

    .dashboard-banner{

        padding:22px;

    }

    .dashboard-banner h2{

        font-size:19px;

    }

    .cards{

        grid-template-columns:1fr;

    }

    .quick-grid{

        grid-template-columns:1fr;

    }

    .card h1{

        font-size:23px;

    }

    .password-box{

        padding:25px 20px;

        margin:25px auto;

    }

}

</style>

<link rel="stylesheet" href="sidebar.css">

</head>

<body>


<?php include "sidebar.php"; ?>



<!-- ==========================================
     MAIN CONTENT
========================================== -->

<div class="main">


    <!-- ==========================================
         HEADER
    =========================================== -->

    <div class="top-header">

        <div class="welcome-text">

            <h1>Welcome.....!!!</h1>

            <p>
                Clothing Sales & Profit Management System
            </p>

        </div>


        <div class="header-date">

            📅 <?php echo date("l, d F Y"); ?>

        </div>

    </div>



    <?php if(!$dashboard_unlocked): ?>


        <!-- ==========================================
             DASHBOARD LOCKED
        =========================================== -->

        <div class="dashboard-banner">

            <h2>Business Overview</h2>

            <p>
                Welcome to T-Shirt Plug Business Management.
                Securely access your business performance,
                clothing item totals and financial information.
            </p>

        </div>


        <div class="password-box">


            <div class="lock-icon">
                🔐
            </div>


            <h2>Dashboard Locked</h2>


            <p>

                Enter the dashboard password to view
                your business sales, profit and financial
                performance information.

            </p>


            <?php if(isset($password_error)): ?>

                <div class="error">

                    <?php echo htmlspecialchars($password_error); ?>

                </div>

            <?php endif; ?>


            <form method="POST">


                <label>
                    Dashboard Password
                </label>


                <input
                    type="password"
                    name="dashboard_password"
                    placeholder="Enter dashboard password"
                    autocomplete="current-password"
                    required
                >


                <button type="submit">

                    🔓 Unlock Dashboard

                </button>


            </form>


        </div>


    <?php else: ?>


        <!-- ==========================================
             DASHBOARD UNLOCKED
        =========================================== -->


        <div class="dashboard-banner">

            <h2>Business Performance Overview</h2>

            <p>

                Your business statistics and financial
                performance at a glance. Monitor your
                clothing sales, profit, expenses and
                overall business performance.

            </p>

        </div>



        <!-- ==========================================
             DASHBOARD STATISTICS
        =========================================== -->


        <div class="section-heading">

            <h2>📊 Financial Overview</h2>

            <span>
                Selected period: <?php echo htmlspecialchars($stats_period_label); ?>
            </span>

        </div>

        <form method="GET" class="stats-filter" id="stats-filter">
            <input type="hidden" name="chart_period" value="<?php echo htmlspecialchars($chart_period); ?>">
            <input type="hidden" name="chart_start_date" value="<?php echo htmlspecialchars($chart_start_input); ?>">
            <input type="hidden" name="chart_end_date" value="<?php echo htmlspecialchars($chart_end_input); ?>">
            <label for="stats-period">
                Period
                <select name="stats_period" id="stats-period">
                    <option value="day" <?php echo $stats_period === 'day' ? 'selected' : ''; ?>>Today</option>
                    <option value="week" <?php echo $stats_period === 'week' ? 'selected' : ''; ?>>This week</option>
                    <option value="month" <?php echo $stats_period === 'month' ? 'selected' : ''; ?>>This month</option>
                    <option value="custom" <?php echo $stats_period === 'custom' ? 'selected' : ''; ?>>Custom range</option>
                </select>
            </label>
            <div class="stats-filter-range" id="stats-custom-range" <?php echo $stats_period === 'custom' ? '' : 'hidden'; ?>>
                <label for="stats-start-date">
                    Start date
                    <input type="date" name="stats_start_date" id="stats-start-date" value="<?php echo htmlspecialchars($stats_start_input); ?>" <?php echo $stats_period === 'custom' ? 'required' : ''; ?>>
                </label>
                <label for="stats-end-date">
                    End date
                    <input type="date" name="stats_end_date" id="stats-end-date" value="<?php echo htmlspecialchars($stats_end_input); ?>" <?php echo $stats_period === 'custom' ? 'required' : ''; ?>>
                </label>
            </div>
            <button type="submit">Apply Filter</button>
        </form>

        <?php if ($stats_period_error !== ''): ?>
            <div class="stats-filter-error" role="alert"><?php echo htmlspecialchars($stats_period_error); ?> Statistics are not shown until the period is corrected.</div>
        <?php endif; ?>


        <div class="cards">


            <!-- TOTAL ITEMS -->

            <div class="card">

                <div class="card-icon">
                    👕
                </div>

                <h3>Total Clothing Items</h3>

                <h1>
                    <?php echo $stats_period_valid ? number_format($total_items) : '—'; ?>
                </h1>

                <div class="card-description">
                    Pieces sold in Store Records for this period
                </div>

            </div>



            <!-- TOTAL PROFIT -->

            <div class="card revenue-card">

                <div class="card-icon">
                    📈
                </div>

                <h3>Total Revenue</h3>

                <h1>
                    <?php echo $stats_period_valid ? 'TSh ' . number_format($total_revenue) : '—'; ?>
                </h1>

                <div class="card-description">
                    Total sales recorded in Daily Report
                </div>

            </div>



            <!-- TOTAL EXPENSES -->

            <div class="card expense-card">

                <div class="card-icon">
                    📉
                </div>

                <h3>Total Expenses</h3>

                <h1>
                    <?php echo $stats_period_valid ? 'TSh ' . number_format($total_expenses) : '—'; ?>
                </h1>

                <div class="card-description">
                    Daily Report costs plus recorded expenditures
                </div>

            </div>



            <!-- NET PROFIT -->

            <div class="card net-card">

                <div class="card-icon">
                    💰
                </div>

                <h3>Net Profit</h3>

                <h1>
                    <?php echo $stats_period_valid ? 'TSh ' . number_format($net_profit) : '—'; ?>
                </h1>

                <div class="card-description">
                    Revenue minus Daily Report costs and expenditures
                </div>

            </div>


        </div>



        <div class="financial-overview-grid">
            <section class="revenue-trend" aria-labelledby="revenue-trend-title">
                <h3 id="revenue-trend-title">Revenue and Total Cost Trend</h3>
                <form method="GET" class="trend-filter" id="trend-filter">
                    <input type="hidden" name="stats_period" value="<?php echo htmlspecialchars($stats_period); ?>">
                    <input type="hidden" name="stats_start_date" value="<?php echo htmlspecialchars($stats_start_input); ?>">
                    <input type="hidden" name="stats_end_date" value="<?php echo htmlspecialchars($stats_end_input); ?>">
                    <label for="chart-period">
                        Chart period
                        <select name="chart_period" id="chart-period">
                            <option value="week" <?php echo $chart_period === 'week' ? 'selected' : ''; ?>>Weekly (last 12 weeks)</option>
                            <option value="month" <?php echo $chart_period === 'month' ? 'selected' : ''; ?>>Monthly (last 12 months)</option>
                            <option value="custom" <?php echo $chart_period === 'custom' ? 'selected' : ''; ?>>Custom range</option>
                        </select>
                    </label>
                    <div class="trend-filter-range" id="chart-custom-range" <?php echo $chart_period === 'custom' ? '' : 'hidden'; ?>>
                        <label for="chart-start-date">
                            Start date
                            <input type="date" name="chart_start_date" id="chart-start-date" value="<?php echo htmlspecialchars($chart_start_input); ?>" <?php echo $chart_period === 'custom' ? 'required' : ''; ?>>
                        </label>
                        <label for="chart-end-date">
                            End date
                            <input type="date" name="chart_end_date" id="chart-end-date" value="<?php echo htmlspecialchars($chart_end_input); ?>" <?php echo $chart_period === 'custom' ? 'required' : ''; ?>>
                        </label>
                    </div>
                    <button type="submit">Update Chart</button>
                </form>

                <div class="section-heading">
                    <span><?php echo htmlspecialchars($chart_period_label); ?> · <?php echo htmlspecialchars($chart_granularity_label); ?> trend</span>
                </div>

                <?php if ($chart_period_error !== ''): ?>
                    <div class="trend-error" role="alert"><?php echo htmlspecialchars($chart_period_error); ?></div>
                <?php elseif (!$chart_has_activity): ?>
                    <div class="trend-empty">No revenue or expense records were found for this chart period.</div>
                <?php else: ?>
                    <div class="trend-legend" aria-hidden="true">
                        <span><i></i>Revenue</span>
                        <span><i class="cost-key"></i>Total Cost</span>
                    </div>
                    <svg class="trend-chart" viewBox="0 0 760 310" role="img" aria-label="Revenue and total cost trend for <?php echo htmlspecialchars($chart_period_label); ?>">
                        <title>Revenue and total cost trend</title>
                        <?php foreach ($chart_svg_ticks as $tick): ?>
                            <line x1="100" y1="<?php echo round($tick['y'], 1); ?>" x2="740" y2="<?php echo round($tick['y'], 1); ?>" stroke="#e7ecf2" stroke-width="1" />
                            <text x="92" y="<?php echo round($tick['y'] + 4, 1); ?>" text-anchor="end"><?php echo htmlspecialchars($tick['label']); ?></text>
                        <?php endforeach; ?>
                        <polyline points="<?php echo htmlspecialchars($chart_svg_revenue_points, ENT_QUOTES); ?>" fill="none" stroke="#0d47a1" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                        <polyline points="<?php echo htmlspecialchars($chart_svg_cost_points, ENT_QUOTES); ?>" fill="none" stroke="#dc3545" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                        <?php foreach ($chart_data as $index => $point): ?>
                            <?php
                            $x = count($chart_data) === 1
                                ? 420
                                : 100 + 640 * $index / (count($chart_data) - 1);
                            $revenue_y = 258 - 240 * (float)$point['revenue'] / $chart_max_value;
                            $cost_y = 258 - 240 * (float)$point['cost'] / $chart_max_value;
                            ?>
                            <circle cx="<?php echo round($x, 1); ?>" cy="<?php echo round($revenue_y, 1); ?>" r="4" fill="#0d47a1" tabindex="0">
                                <title><?php echo htmlspecialchars($point['label'] . ' | Revenue: TSh ' . number_format((float)$point['revenue']) . ' | Total Cost: TSh ' . number_format((float)$point['cost']), ENT_QUOTES); ?></title>
                            </circle>
                            <circle cx="<?php echo round($x, 1); ?>" cy="<?php echo round($cost_y, 1); ?>" r="4" fill="#dc3545" tabindex="0">
                                <title><?php echo htmlspecialchars($point['label'] . ' | Revenue: TSh ' . number_format((float)$point['revenue']) . ' | Total Cost: TSh ' . number_format((float)$point['cost']), ENT_QUOTES); ?></title>
                            </circle>
                            <?php if ($index % $chart_svg_label_step === 0 || $index === count($chart_data) - 1): ?>
                                <text x="<?php echo round($x, 1); ?>" y="286" text-anchor="middle"><?php echo htmlspecialchars($point['label']); ?></text>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </svg>
                <?php endif; ?>
            </section>

            <div class="profit-information">
                <h3>💼 Financial Breakdown</h3>
                <div class="profit-line">
                    <span>Total Revenue</span>
                    <strong><?php echo $stats_period_valid ? 'TSh ' . number_format($total_revenue) : '—'; ?></strong>
                </div>
                <div class="profit-line">
                    <span>Daily Report Total Cost</span>
                    <strong><?php echo $stats_period_valid ? 'TSh ' . number_format($daily_report_cost) : '—'; ?></strong>
                </div>
                <div class="profit-line">
                    <span>Business Expenditures</span>
                    <strong><?php echo $stats_period_valid ? 'TSh ' . number_format($business_expenditures) : '—'; ?></strong>
                </div>
                <div class="profit-line">
                    <span>Total Expenses</span>
                    <strong style="color:#dc3545;"><?php echo $stats_period_valid ? 'TSh ' . number_format($total_expenses) : '—'; ?></strong>
                </div>
                <div class="profit-line">
                    <span><strong>Net Profit</strong></span>
                    <strong style="color:#198754;"><?php echo $stats_period_valid ? 'TSh ' . number_format($net_profit) : '—'; ?></strong>
                </div>
            </div>
        </div>



        <!-- ==========================================
             QUICK ACTIONS
        =========================================== -->


        <div class="quick-actions">


            <div class="section-heading">

                <h2>⚡ Quick Actions</h2>

                <span>
                    Quickly access important pages
                </span>

            </div>


            <div class="quick-grid">


                <a href="add_product.php" class="quick-action">

                    <div class="quick-icon">
                        👕
                    </div>

                    <h3>Add Clothing Product</h3>

                    <p>
                        Record new clothing products and sales.
                    </p>

                </a>



                <a href="records.php" class="quick-action">

                    <div class="quick-icon">
                        📁
                    </div>

                    <h3>Store Records</h3>

                    <p>
                        View, search and manage saved clothing records.
                    </p>

                </a>



                <a href="reports.php" class="quick-action">

                    <div class="quick-icon">
                        📊
                    </div>

                    <h3>Business Reports</h3>

                    <p>
                        Review business performance and reports.
                    </p>

                </a>



                <a href="payments.php" class="quick-action">

                    <div class="quick-icon">
                        💰
                    </div>

                    <h3>Payments</h3>

                    <p>
                        Manage payment records.
                    </p>

                </a>



                <a href="expenditures.php" class="quick-action">

                    <div class="quick-icon">
                        💸
                    </div>

                    <h3>Expenditures</h3>

                    <p>
                        Manage business expenditure records.
                    </p>

                </a>



                <a href="long_term_loans.php" class="quick-action">

                    <div class="quick-icon">
                        💳
                    </div>

                    <h3>Long Term Loans</h3>

                    <p>
                        Manage customer loans and clearance.
                    </p>

                </a>



                <a href="daily_report.php" class="quick-action">

                    <div class="quick-icon">
                        📅
                    </div>

                    <h3>Daily Report</h3>

                    <p>
                        View daily business activity.
                    </p>

                </a>


            </div>

        </div>



        <!-- ==========================================
             DASHBOARD-ONLY LOGOUT
        =========================================== -->


        <a
            href="dashboard.php?dashboard_logout=1"
            class="dashboard-logout"
            onclick="return confirm('Are you sure you want to lock the dashboard?');"
        >

            🔒 Lock Dashboard

        </a>


    <?php endif; ?>



    <!-- ==========================================
         FOOTER
    =========================================== -->


    <div class="dashboard-footer">

        <strong>T-SHIRT PLUG</strong>

        <br><br>

        Clothing Sales & Profit Management System

        <br><br>

        &copy; <?php echo date("Y"); ?>
        All Rights Reserved.

    </div>


</div>


<script>
const statsPeriodSelect = document.getElementById('stats-period');
const statsCustomRange = document.getElementById('stats-custom-range');
if (statsPeriodSelect && statsCustomRange) {
    const customDateInputs = statsCustomRange.querySelectorAll('input[type="date"]');
    const updateCustomDateVisibility = function () {
        const isCustom = statsPeriodSelect.value === 'custom';
        statsCustomRange.hidden = !isCustom;
        customDateInputs.forEach(function (input) {
            input.required = isCustom;
        });
    };
    statsPeriodSelect.addEventListener('change', updateCustomDateVisibility);
    updateCustomDateVisibility();
}
const chartPeriodSelect = document.getElementById('chart-period');
const chartCustomRange = document.getElementById('chart-custom-range');
if (chartPeriodSelect && chartCustomRange) {
    const chartDateInputs = chartCustomRange.querySelectorAll('input[type="date"]');
    const updateChartDateVisibility = function () {
        const isCustom = chartPeriodSelect.value === 'custom';
        chartCustomRange.hidden = !isCustom;
        chartDateInputs.forEach(function (input) {
            input.required = isCustom;
        });
    };
    chartPeriodSelect.addEventListener('change', updateChartDateVisibility);
    updateChartDateVisibility();
}
</script>

</body>

</html>
