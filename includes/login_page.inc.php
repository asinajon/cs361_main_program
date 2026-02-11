<?php
// session_start();

if (isset($_SESSION['user_id'])) {

    // Need the functions:
    require_once('includes/login_functions.inc.php');
    redirect_user('loggedin.php');

}

$page_title = 'Weekly Movie Scheduler - Login';
include('includes/header.html');

// Print any error message, if they exist:
if (isset($errors) && !empty($errors)){
    echo '<h1> Errors! </h1>
    <p class="error">The following error(s) occurred:<br>';
    foreach ($errors as $msg) {
        echo " - $msg<br>\n";
    }
    echo '</p><p>Please try again.</p>';
}    
?>

<h1>Login</h1>
<form action="login.php" method="post">
    <p>Email Address: <input type="email" name="email" size="20" maxlength="60"> </p>
    <p>Password: <input type="password" name="pass" size="20" maxlength="60"> </p>
    <p> <input type="submit" name="submit" value="Login"> </p>
</form> 

<h2>New User? Register Below</h2>
<form action="register.php" method="POST">
	<p>Email Address: <br><input type="text" name="email" size="20" maxlength="60" value="<?php if (isset($_POST['email'])) echo $_POST['email']; ?>"  /> </p>
	<p>Password: <br><input type="password" name="pass1" size="10" maxlength="20" value="<?php if (isset($_POST['pass1'])) echo $_POST['pass1']; ?>"  /></p>
	<p>Confirm Password: <br><input type="password" name="pass2" size="10" maxlength="20" value="<?php if (isset($_POST['pass2'])) echo $_POST['pass2']; ?>"  /></p>
	<p><input type="submit" name="submit" value="Register" /></p>
</form>
<p>Build your movie schedule for the week!<br>
Keep track of the times and locations that work for you.</p>