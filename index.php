<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CEG Civic Portal</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #d9fdd3;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .login-container {
            background: #fff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0px 4px 10px rgba(0,0,0,0.2);
            text-align: center;
            width: 300px;
        }
        h2 {
            margin-bottom: 10px;
            color: #2e7d32;
        }
        p {
            font-style: italic;
            color: #555;
        }
        input {
            width: 90%;
            padding: 10px;
            margin: 8px 0;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        button {
            width: 100%;
            padding: 10px;
            background: #2e7d32;
            color: #fff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        button:hover {
            background: #1b5e20;
        }
        .error {
            color: red;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>Welcome to CEG Civic Portal</h2>
        <p>"Not Me, But You"</p>

        <?php if (isset($_GET['error'])) { ?>
            <p class="error">Invalid Login!</p>
        <?php } ?>

        <form action="login.php" method="POST">
            <input type="text" name="username" placeholder="Enter Username" required><br>
            <input type="password" name="password" placeholder="Enter Password" required><br>
            <button type="submit">Login</button>
        </form>
    </div>
</body>
</html>
