<?php

session_start();

if(isset($_SESSION['admin'])){

    header("Location: dashboard.php");
    exit();

}

?>


<!DOCTYPE html>
<html>

<head>

<title>
Max Smart | Administrator Login
</title>


<style>

*{
margin:0;
padding:0;
box-sizing:border-box;
font-family:Arial;
}


body{

height:100vh;
display:flex;
justify-content:center;
align-items:center;

background:linear-gradient(
135deg,
#0d47a1,
#1976d2
);

}
.login-box{

background:white;
width:400px;
padding:40px;

border-radius:15px;

box-shadow:0 10px 30px rgba(0,0,0,0.2);

}
.logo{

text-align:center;

font-size:30px;

font-weight:bold;

color:#0d47a1;

margin-bottom:10px;

}

.subtitle{

text-align:center;

margin-bottom:30px;

color:#666;

}

input{

width:100%;

padding:14px;

margin-bottom:15px;

border:1px solid #ddd;

border-radius:8px;

font-size:16px;

}

button{

width:100%;

padding:14px;

background:#0d47a1;

color:white;

border:none;

border-radius:8px;

font-size:17px;

cursor:pointer;

}

button:hover{

background:#1565c0;

}
.error{

background:#ffdddd;

color:red;

padding:10px;

border-radius:5px;

text-align:center;

margin-bottom:15px;

}

.admin{

background:#eef5ff;

padding:10px;

border-radius:8px;

text-align:center;

margin-top:20px;

}

</style>

</head>

<body>

<div class="login-box">

<div class="logo">

🛒 T-SHIRT PLUG

</div>

<div class="subtitle">

Administrator Login

</div>

<?php

if(isset($_GET['error'])){

echo "

<div class='error'>

Invalid username or password

</div>

";

}

?>



<form action="authenticate.php" method="POST">


<input 
type="text"
name="username"
placeholder="Username"
required>


<input 
type="password"
name="password"
placeholder="Password"
required>


<button type="submit">

Login

</button>

</form>

</div>

</body>

</html>