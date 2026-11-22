<?php

session_start();

include "database.php";


$username=$_POST['username'];

$password=$_POST['password'];

if($username=="max" && $password=="max123"){

$stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ?");
if (!$stmt) {
    die("Unable to load staff account: " . mysqli_error($conn));
}
mysqli_stmt_bind_param($stmt, "s", $username);
if (!mysqli_stmt_execute($stmt)) {
    die("Unable to load staff account: " . mysqli_stmt_error($stmt));
}
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$user) {
    header("Location: login.php?error=1");
    exit();
}

$_SESSION['user_id'] = (int)$user['id'];
$_SESSION['username'] = $username;
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