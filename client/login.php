<?php
require __DIR__ . "/../jwt.php";
require __DIR__ . "/db_handler.php";

$db_controller = new ClientDBHandler();
$jwt = new JWT();
//if (isset($_COOKIE['jwt'])) {
//    header("Location: client.php");
//    exit();
//}
if (isset($_POST['login']) && !empty($_POST['userid'])) {
    $rooms = json_decode('[' . $_POST['college'] . ']', true);

    if ($user = $db_controller->getUserDetails($_POST['userid'])) {

        if (isset($_COOKIE['jwt'])) {
//        unset($_COOKIE['jwt']);
            setcookie('jwt', '', 1, '/');
        }
        $token = $jwt->create_jwt_token($_POST['userid'], $user['user_name']);
        setcookie('jwt', $token, time() + 1200000, '/');
        header("Location: client.php");
        exit();
    } else {
        if ($db_controller->createUser($_POST['userid'], $_POST['username'], $rooms)) {
            if (isset($_COOKIE['jwt'])) {
                setcookie('jwt', '', 1, '/');
            }
            $token = $jwt->create_jwt_token($_POST['userid'], $_POST['username']);
            setcookie('jwt', $token, time() + 1200000, '/');
            header("Location: client.php");
            exit();
        } else {
            echo 'Error inserting in database.';
        }
    }
}

//if (isset($_COOKIE['jwt'])){
//    var_dump(json_decode((new JWT())->get_user_details_from_jwt($_COOKIE['jwt']), true));
//}
?>
<form action="login.php" method="post">
    <input type="text" name="userid" placeholder="roll no" required>
    <input type="text" name="username" placeholder="username" required>
    <input type="text" name="college" placeholder="room ids. ex: 1,2,3" required>
    <input type="submit" name="login">
</form>
