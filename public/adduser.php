<?php
require "../include/bittorrent.php";
dbconn();
loggedinorreturn();
if (get_user_class() < UC_ADMINISTRATOR)
stderr("Error", "Access denied.");
if ($_SERVER["REQUEST_METHOD"] == "POST")
{

    try {
        $userRep = new \App\Repositories\UserRepository();
        $newUser = $userRep->store([
            'username' => $_POST['username'],
            'email' => $_POST['email'],
            'password' => $_POST['password'],
            'password_confirmation' => $_POST['password2'],
        ]);
    } catch (\Exception $e) {
        stderr("ERROR", $e->getMessage());
    }
	header("Location: " . get_protocol_prefix() . "$BASEURL/userdetails.php?id=".htmlspecialchars($newUser->id));
	die;
}
stdhead("Add user");

?>
<h1>Add user</h1>
<form method=post action=adduser.php>
<table border=1 cellspacing=0 cellpadding=5>
<tr><td class=rowhead>User name</td><td><input type=text name=username size=40></td></tr>
<tr><td class=rowhead>Password</td><td><input type=password name=password size=40></td></tr>
<tr><td class=rowhead>Re-type password</td><td><input type=password name=password2 size=40></td></tr>
<tr><td class=rowhead>E-mail</td><td><input type=text name=email size=40></td></tr>
<tr><td colspan=2 align=center><input type=submit value="Okay" class=btn></td></tr>
</table>
</form>
<?php stdfoot();
