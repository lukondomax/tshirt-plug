<?php

session_start();

if(!isset($_SESSION['admin'])){

    header("Location: login.php");
    exit();

}

include "database.php";


// CHECK ID FROM URL

if(!isset($_GET['id'])){

    echo "No record selected";
    exit();

}

$id = mysqli_real_escape_string($conn, $_GET['id']);


// GET RECORD DETAILS

$query = "SELECT * FROM products WHERE id='$id'";

$result = mysqli_query($conn,$query);


if(mysqli_num_rows($result) == 0){

    echo "Record not found";
    exit();

}


$row = mysqli_fetch_assoc($result);



// UPDATE RECORD

if(isset($_POST['update'])){


$product_name = mysqli_real_escape_string($conn,$_POST['product_name']);

$category = mysqli_real_escape_string($conn,$_POST['category']);

$brand = mysqli_real_escape_string($conn,$_POST['brand']);

$size = mysqli_real_escape_string($conn,$_POST['size']);

$color = mysqli_real_escape_string($conn,$_POST['color']);

$quantity = mysqli_real_escape_string($conn,$_POST['quantity']);

$buying_price = mysqli_real_escape_string($conn,$_POST['buying_price']);

$selling_price = mysqli_real_escape_string($conn,$_POST['selling_price']);


// CALCULATE PROFIT

$profit = ($selling_price - $buying_price) * $quantity;

$loss = 0;

$net_profit = $profit - $loss;



$update = "

UPDATE products SET

product_name='$product_name',

category='$category',

brand='$brand',

size='$size',

color='$color',

quantity='$quantity',

buying_price='$buying_price',

selling_price='$selling_price',

profit='$profit',

loss='$loss',

net_profit='$net_profit'


WHERE id='$id'

";



if(mysqli_query($conn,$update)){


header("Location: records.php");

exit();


}else{


echo "Update failed: ".mysqli_error($conn);


}



}


?>



<!DOCTYPE html>
<html>

<head>

<title>Edit Clothing Record</title>


<style>


body{

font-family:Arial;
background:#f4f7fb;

}


.box{

width:600px;
background:white;
padding:30px;
margin:40px auto;
border-radius:15px;
box-shadow:0 5px 15px #ddd;

}


input{

width:100%;
padding:12px;
margin-bottom:15px;
border:1px solid #ccc;
border-radius:5px;

}


button{

width:100%;
padding:14px;
background:#0d47a1;
color:white;
border:none;
border-radius:5px;
font-size:16px;
cursor:pointer;

}


button:hover{

background:#1565c0;

}


.back{

display:block;
margin-top:15px;
text-align:center;
background:#555;
color:white;
padding:12px;
text-decoration:none;
border-radius:5px;

}


</style>


</head>


<body>


<div class="box">


<h2>Edit Clothing Record</h2>


<form method="POST">



<label>Product Name</label>

<input type="text" 
name="product_name"
value="<?php echo htmlspecialchars($row['product_name']); ?>"
required>



<label>Person Name</label>

<input type="text"
name="category"
value="<?php echo htmlspecialchars($row['category']); ?>"
required>



<label>Brand</label>

<input type="text"
name="brand"
value="<?php echo htmlspecialchars($row['brand']); ?>"
required>



<label>Size</label>

<input type="text"
name="size"
value="<?php echo htmlspecialchars($row['size']); ?>"
required>



<label>Color</label>

<input type="text"
name="color"
value="<?php echo htmlspecialchars($row['color']); ?>"
required>



<label>Quantity</label>

<input type="number"
name="quantity"
value="<?php echo $row['quantity']; ?>"
required>



<label>Buying Price</label>

<input type="number"
name="buying_price"
value="<?php echo $row['buying_price']; ?>"
required>



<label>Selling Price</label>

<input type="number"
name="selling_price"
value="<?php echo $row['selling_price']; ?>"
required>



<button name="update">

Update Record

</button>



<a href="records.php" class="back">

← Back To Records

</a>


</form>


</div>


</body>

</html>
