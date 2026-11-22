<!DOCTYPE html>
<html>

<head>

<title>
Max Smart Clothing | Sales & Profit Management
</title>


<style>

*{

margin:0;
padding:0;
box-sizing:border-box;
font-family:Arial, sans-serif;

}



body{

background:#f4f7fb;

}



/* Navigation */

.navbar{

background:#0d47a1;

padding:20px 50px;

display:flex;

justify-content:space-between;

align-items:center;

color:white;

}



.logo{

font-size:25px;

font-weight:bold;

}



.navbar a{

color:white;

text-decoration:none;

background:#1565c0;

padding:12px 25px;

border-radius:8px;

}



/* Hero Section */

.hero{

height:80vh;

display:flex;

align-items:center;

justify-content:center;

text-align:center;

background:linear-gradient(
135deg,
#0d47a1,
#1976d2
);

color:white;

padding:30px;

}



.hero h1{

font-size:45px;

margin-bottom:20px;

}



.hero p{

font-size:20px;

max-width:700px;

line-height:1.6;

}



.btn{

display:inline-block;

margin-top:30px;

background:white;

color:#0d47a1;

padding:15px 35px;

border-radius:30px;

text-decoration:none;

font-weight:bold;

}




/* Features */

.features{

padding:50px;

text-align:center;

}



.features h2{

color:#0d47a1;

margin-bottom:30px;

}



.cards{

display:flex;

justify-content:center;

gap:25px;

flex-wrap:wrap;

}



.card{

background:white;

width:280px;

padding:25px;

border-radius:15px;

box-shadow:0 5px 15px #ddd;

}

.card h3{

color:#0d47a1;

margin-bottom:15px;

}


.footer{

background:#0d47a1;

color:white;

text-align:center;

padding:20px;

}

</style>

</head>

<body>

<div class="navbar">


<div class="logo">

👕 T-SHIRT PLUG

</div>

<a href="login.php">

Admin Login

</a>
</div>

<section class="hero">

<div>

<h1>

T-SHIRT PLUG

</h1>


<h2>

Sales & Profit Management System

</h2>


<p>

A professional clothing business management system designed to manage T-shirts and fashion products, record sales, track profits and losses, and maintain accurate business records.

</p>
<a href="login.php" class="btn">

Login As Administrator

</a>

</div>

</section>

<section class="features">
<h2>

System Features

</h2>
<div class="cards">
<div class="card">

<h3>

👕 Clothing Records

</h3>

<p>

Store T-shirt and clothing information including category, brand, size, color, quantity, and prices.

</p>


</div>
<div class="card">

<h3>

📊 Profit Analysis

</h3>

<p>

Calculate total sales, profit, loss, and net profit for different clothing products and dates.

</p>

</div>

<div class="card">

<h3>
📁 Business Records
</h3>

<p>

Keep saved sales records for future reference and generate professional reports.

</p>

</div>

</div>
</section>

<section class="features">

<h2>

About T-shirt Plug

</h2>
<p>

T-shirt Plug is a fashion business focused on providing quality clothing products such as T-shirts and other wear. This system helps the administrator manage daily sales activities efficiently and make better business decisions.

</p>
</section>
<div class="footer">
<p>

© <?php echo date("Y"); ?> T-shirt plug.

All Rights Reserved.

</p>

</div>
</body>
</html>