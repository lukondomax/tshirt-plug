<?php
session_start();

if(!isset($_SESSION['admin'])){
    header("Location: login.php");
    exit();
}

include "database.php";

if(isset($_GET['id'])){

$id = $_GET['id'];

mysqli_query($conn,"DELETE FROM products WHERE id='$id'");

}

header("Location: records.php");
exit();
