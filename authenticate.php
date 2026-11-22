<?php

session_start();

include "database.php";


$username=$_POST['username'];

$password=$_POST['password'];

if($username=="max" && $password=="max123"){

$_SESSION['admin']="Max";

$_SESSION['role']="Administrator";

header("Location: dashboard.php");

exit();


}

else{


header("Location: login.php?error=1");

exit();


}


?>