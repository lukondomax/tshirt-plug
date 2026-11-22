<?php

session_start();

include "database.php";


$product_name=$_POST['product_name'];

$category=$_POST['category'];

$brand=$_POST['brand'];

$size=$_POST['size'];

$color=$_POST['color'];

$quantity=$_POST['quantity'];

$buying_price=$_POST['buying_price'];

$selling_price=$_POST['selling_price'];

$date=$_POST['date'];

// code section ya kufanya hesabu

$total_cost=$quantity*$buying_price;

$total_sales=$quantity*$selling_price;


$profit=0;

$loss=0;


if($selling_price>$buying_price){

$profit=($selling_price-$buying_price)*$quantity;

}

else if($buying_price>$selling_price){

$loss=($buying_price-$selling_price)*$quantity;

}


$net_profit=$profit-$loss;



$sql="INSERT INTO products

(
product_name,
category,
brand,
size,
color,
quantity,
buying_price,
selling_price,
total_cost,
total_sales,
profit,
loss,
net_profit,
sale_date

)

VALUES

(
'$product_name',
'$category',
'$brand',
'$size',
'$color',
'$quantity',
'$buying_price',
'$selling_price',
'$total_cost',
'$total_sales',
'$profit',
'$loss',
'$net_profit',
'$date'

)";



if(mysqli_query($conn,$sql)){


$_SESSION['message']="Clothing item saved successfully!";

$redirect_to = (
    isset($_POST['return_to'])
    && $_POST['return_to'] === 'records'
)
    ? "records.php"
    : "dashboard.php";

header("Location: " . $redirect_to);

exit();


}

else{


echo "Error: ".mysqli_error($conn);


}


?>