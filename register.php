<?php
require_once 'db.php';

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $passwordRaw = $_POST['password'];

    // Basic validation
    if (empty($name) || empty($email) || empty($passwordRaw)) {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } elseif (strlen($passwordRaw) < 6) {
        $error = "Password must be at least 6 characters.";
    } else {

        // Check if email exists
        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $error = "Email already registered.";
        } else {
            $password = password_hash($passwordRaw, PASSWORD_DEFAULT);

            $stmt = $conn->prepare(
                "INSERT INTO users (name, email, password) VALUES (?, ?, ?)"
            );
            $stmt->bind_param("sss", $name, $email, $password);

            if ($stmt->execute()) {
                $success = "Registration successful. You can now login.";
            } else {
                $error = "Something went wrong.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Register</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-5">
    <div class="card p-4 shadow mx-auto" style="max-width:400px;">
        <h3 class="text-center mb-3">Register</h3>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>

        <form method="POST">
            <input class="form-control mb-2" name="name" placeholder="Name" required>
            <input class="form-control mb-2" type="email" name="email" placeholder="Email" required>
            <input class="form-control mb-3" type="password" name="password" placeholder="Password" required>

            <button class="btn btn-primary w-100">Register</button>
        </form>

        <p class="mt-3 text-center">
            Already have an account?
            <a href="login.php">Login</a>
        </p>
    </div>
</div>

</body>
</html>