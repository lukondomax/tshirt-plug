<?php

session_start();


if(!isset($_SESSION['admin'])){

    header("Location: login.php");
    exit();

}


include "database.php";



if(!isset($_GET['id'])){

    echo "No record selected";
    exit();

}



$id = intval($_GET['id']);



// DELETE RECORD

$query = "DELETE FROM products WHERE id='$id'";


if(mysqli_query($conn,$query)){


    header("Location: reports.php");
    exit();


}else{


    echo "Delete failed: ".mysqli_error($conn);


}


?>
