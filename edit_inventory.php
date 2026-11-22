<?php

session_start();

if(!isset($_SESSION['admin'])){

    header("Location: login.php");
    exit();

}

include "database.php";


// CHECK ID

if(!isset($_GET['id'])){

    echo "No record selected";
    exit();

}


$id = intval($_GET['id']);


// GET RECORD

$result = mysqli_query($conn,"SELECT * FROM products WHERE id='$id'");


if(mysqli_num_rows($result)==0){

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



$query = "

UPDATE products SET

product_name='$product_name',

category='$category',

brand='$brand',

size='$size',

color='$color',

quantity='$quantity',

buying_price='$buying_price',

selling_price='$selling_price'

WHERE id='$id'

";



if(mysqli_query($conn,$query)){


header("Location: reports.php");
exit();


}else{


echo "Update failed: ".mysqli_error($conn);


}


}


?>


<!DOCTYPE html>

<html>

<head>

<title>Edit Inventory</title>


<style>

body{

font-family:Arial;
background:#f4f7fb;

}


.box{

width:500px;
margin:40px auto;
background:white;
padding:25px;
border-radius:10px;

}


input{

width:100%;
padding:10px;
margin:8px 0;

}


button{

width:100%;
padding:12px;
background:#0d47a1;
color:white;
border:none;
cursor:pointer;

}


</style>


</head>


<body>


<div class="box">


<h2>Edit Inventory Record</h2>


<form method="POST">


<input type="text"
name="product_name"
value="<?php echo htmlspecialchars($row['product_name']); ?>"
required>


<input type="text"
name="category"
value="<?php echo htmlspecialchars($row['category']); ?>"
required>


<input type="text"
name="brand"
value="<?php echo htmlspecialchars($row['brand']); ?>">


<input type="text"
name="size"
value="<?php echo htmlspecialchars($row['size']); ?>">


<input type="text"
name="color"
value="<?php echo htmlspecialchars($row['color']); ?>">


<input type="number"
name="quantity"
value="<?php echo $row['quantity']; ?>"
required>


<input type="number"
name="buying_price"
value="<?php echo $row['buying_price']; ?>"
required>


<input type="number"
name="selling_price"
value="<?php echo $row['selling_price']; ?>"
required>



<button name="update">

Update Inventory

</button>


</form>


</div>


</body>

</html>
