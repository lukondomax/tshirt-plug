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

if($dashboard_unlocked){

    // Total clothing items

    $total_items = mysqli_fetch_assoc(
        mysqli_query(
            $conn,
            "SELECT COUNT(*) total FROM products"
        )
    )['total'];


    // Total profit

    $total_profit = mysqli_fetch_assoc(
        mysqli_query(
            $conn,
            "SELECT SUM(profit) total FROM products"
        )
    )['total'] ?? 0;


    // Total loss

    $total_loss = mysqli_fetch_assoc(
        mysqli_query(
            $conn,
            "SELECT SUM(loss) total FROM products"
        )
    )['total'] ?? 0;


    // Net profit

    $net_profit = $total_profit - $total_loss;


    // Convert values to numbers

    $total_items = (int)$total_items;

    $total_profit = (float)$total_profit;

    $total_loss = (float)$total_loss;

    $net_profit = (float)$net_profit;

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


.card.profit-card{

    border-top-color:#198754;

}


.card.loss-card{

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


.profit-card .card-icon{

    background:#e4f5eb;

}


.loss-card .card-icon{

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


.card.profit-card h1{

    color:#198754;

}


.card.loss-card h1{

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

.profit-information{

    background:white;

    padding:22px;

    border-radius:13px;

    margin-bottom:30px;

    box-shadow:0 4px 15px rgba(0,0,0,0.05);

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

</head>

<body>


<!-- ==========================================
     SIDEBAR
========================================== -->

<div class="sidebar">

    <h2>T-SHIRTS PLUG</h2>

    <div class="sidebar-subtitle">
        BUSINESS MANAGEMENT SYSTEM
    </div>


    <a href="dashboard.php" class="active">
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


    <div class="sidebar-divider"></div>


    <a href="expenditure.php">
        💰 Payments
    </a>


    <a href="expenditures.php">
        💸 Expenditures
    </a>


    <a href="long_term_loans.php">
        💳 Long Term Loans
    </a>


    <a href="daily_report.php">
        📅 Daily Report
    </a>


    <div class="sidebar-divider"></div>


    <a href="logout.php">
        🚪 Logout
    </a>


    <div class="sidebar-footer">
        T-SHIRT PLUG MANAGEMENT SYSTEM
        <br><br>
        Version 2.0
    </div>

</div>



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
                sales statistics and financial information.
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
                clothing inventory, profit, losses and
                overall business performance.

            </p>

        </div>



        <!-- ==========================================
             DASHBOARD STATISTICS
        =========================================== -->


        <div class="section-heading">

            <h2>📊 Financial Overview</h2>

            <span>
                Overall business statistics
            </span>

        </div>


        <div class="cards">


            <!-- TOTAL ITEMS -->

            <div class="card">

                <div class="card-icon">
                    👕
                </div>

                <h3>Total Clothing Items</h3>

                <h1>
                    <?php echo number_format($total_items); ?>
                </h1>

                <div class="card-description">
                    Total saved product records
                </div>

            </div>



            <!-- TOTAL PROFIT -->

            <div class="card profit-card">

                <div class="card-icon">
                    📈
                </div>

                <h3>Total Profit</h3>

                <h1>
                    TSh <?php echo number_format($total_profit); ?>
                </h1>

                <div class="card-description">
                    Total recorded profit
                </div>

            </div>



            <!-- TOTAL LOSS -->

            <div class="card loss-card">

                <div class="card-icon">
                    📉
                </div>

                <h3>Total Loss</h3>

                <h1>
                    TSh <?php echo number_format($total_loss); ?>
                </h1>

                <div class="card-description">
                    Total recorded loss
                </div>

            </div>



            <!-- NET PROFIT -->

            <div class="card net-card">

                <div class="card-icon">
                    💰
                </div>

                <h3>Net Profit</h3>

                <h1>
                    TSh <?php echo number_format($net_profit); ?>
                </h1>

                <div class="card-description">
                    Profit minus recorded losses
                </div>

            </div>


        </div>



        <!-- ==========================================
             PROFIT INFORMATION
        =========================================== -->


        <div class="profit-information">

            <h3>💼 Financial Breakdown</h3>


            <div class="profit-line">

                <span>Total Profit</span>

                <strong>
                    TSh <?php echo number_format($total_profit); ?>
                </strong>

            </div>


            <div class="profit-line">

                <span>Total Loss</span>

                <strong style="color:#dc3545;">

                    TSh <?php echo number_format($total_loss); ?>

                </strong>

            </div>


            <div class="profit-line">

                <span><strong>Net Profit</strong></span>

                <strong style="color:#198754;">

                    TSh <?php echo number_format($net_profit); ?>

                </strong>

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

                    <h3>Saved Records</h3>

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



                <a href="expenditure.php" class="quick-action">

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


</body>

</html>
