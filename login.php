<?php
session_start();

include "config.php";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $identifier = $_SESSION['identifier'];
    $password_input = $_POST['password'];

    $stmt = mysqli_prepare($conn, "SELECT passwords FROM notes WHERE identifier = ?");
    mysqli_stmt_bind_param($stmt, "s", $identifier);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    $authenticated = false;
    if ($row && !empty($row['passwords'])) {
        if (password_verify($password_input, $row['passwords'])) {
            $authenticated = true;
        } elseif (preg_match('/^[a-f0-9]{32}$/', $row['passwords']) && md5($password_input) === $row['passwords']) {
            // Legacy md5 hash from before the password_hash() upgrade: verify against it once,
            // then transparently re-hash with password_hash() so future logins use bcrypt.
            $authenticated = true;
            $upgraded_hash = password_hash($password_input, PASSWORD_DEFAULT);
            $update_stmt = mysqli_prepare($conn, "UPDATE notes SET passwords = ? WHERE identifier = ?");
            mysqli_stmt_bind_param($update_stmt, "ss", $upgraded_hash, $identifier);
            mysqli_stmt_execute($update_stmt);
            mysqli_stmt_close($update_stmt);
        }
    }

    if ($authenticated) {
        setcookie('authenticated', $identifier, time() + (86400 * 30), "/");
        header("Location: https://rionotes.com/$identifier");
        exit();
    } else {
        $error = "Incorrect password. Please try again.";
    }
}

mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f4f4f4;
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }

        .container {
            max-width: 400px;
            padding: 20px;
            background: #fff;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            border-radius: 10px;
        }
    </style>
</head>

<body>
    <div class="container">
        <h3 class="text-center">Please Enter Password</h3>
        <?php if (isset($error)) { ?>
            <div class="alert alert-danger" role="alert"><?php echo $error; ?></div>
        <?php } ?>
        <form method="post" action="">
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Login</button>
        </form>
    </div>
</body>

</html>