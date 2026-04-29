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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MiniSocial - Sign Up</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/style.css" rel="stylesheet">
    <style>
        .auth-wrapper {
            display: flex;
            min-height: 100vh;
            background: linear-gradient(135deg, var(--bg-primary), #0f172a);
        }
        .auth-brand {
            flex: 1;
            display: none;
            flex-direction: column;
            justify-content: center;
            padding: 4rem;
            position: relative;
            overflow: hidden;
        }
        @media (min-width: 992px) {
            .auth-brand {
                display: flex;
            }
        }
        .auth-brand::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(16,185,129,0.15) 0%, rgba(24,25,26,0) 60%);
            z-index: 0;
            animation: pulse 10s infinite alternate;
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            100% { transform: scale(1.2); }
        }
        .auth-brand-content {
            z-index: 1;
        }
        .auth-brand h1 {
            font-size: 4.5rem;
            font-weight: 900;
            color: var(--accent-color);
            letter-spacing: -2px;
            margin-bottom: 1rem;
        }
        .auth-brand p {
            font-size: 1.5rem;
            color: var(--text-primary);
            max-width: 500px;
        }
        .auth-form-container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            z-index: 1;
        }
        .glass-card {
            background: rgba(36, 37, 38, 0.7);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 20px;
            padding: 2.5rem;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }
        .auth-input {
            background: rgba(24, 25, 26, 0.8) !important;
            border: 1px solid rgba(255,255,255,0.1) !important;
            border-radius: 12px !important;
            padding: 14px 18px !important;
            font-size: 1.05rem !important;
            color: var(--text-primary) !important;
            transition: all 0.3s ease !important;
        }
        .auth-input:focus {
            background: var(--bg-primary) !important;
            border-color: #3b82f6 !important;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.15) !important;
        }
        .auth-btn {
            background: var(--accent-color);
            color: white;
            border: none;
            border-radius: 12px;
            padding: 14px;
            font-size: 1.2rem;
            font-weight: 700;
            transition: all 0.3s ease;
        }
        .auth-btn:hover {
            background: var(--accent-hover);
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.3);
        }
        .create-btn {
            background: #3b82f6;
            margin-top: 1rem;
        }
        .create-btn:hover {
            background: #2563eb;
            box-shadow: 0 10px 15px -3px rgba(59, 130, 246, 0.3);
        }
    </style>
</head>
<body>

    <div class="auth-wrapper flex-row-reverse"> <!-- Flex reverse for signup to look slightly different -->
        <!-- Brand Section -->
        <div class="auth-brand">
            <div class="auth-brand-content ps-5">
                <h1>Join the Club</h1>
                <p>Create a MiniSocial account to start sharing photos, videos, and thoughts with the people you care about.</p>
            </div>
        </div>

        <!-- Form Section -->
        <div class="auth-form-container">
            <div class="glass-card">
                <div class="d-lg-none text-center mb-4">
                    <h1 class="fw-bold" style="color: var(--accent-color);"><i class="fa-brands fa-envira me-2"></i>MiniSocial</h1>
                </div>

                <div class="text-center mb-4">
                    <h2 class="fw-bold text-white mb-1">Sign Up</h2>
                    <p class="text-muted small">It's quick and easy.</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger" style="background: rgba(239, 68, 68, 0.1); border-color: var(--danger); color: #fca5a5; border-radius: 12px;">
                        <i class="fa-solid fa-circle-exclamation me-2"></i><?= $error ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success" style="background: rgba(16, 185, 129, 0.1); border-color: var(--accent-color); color: #6ee7b7; border-radius: 12px;">
                        <i class="fa-solid fa-check-circle me-2"></i><?= $success ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="mb-3">
                        <input class="form-control auth-input" name="name" placeholder="Full name" required>
                    </div>
                    <div class="mb-3">
                        <input class="form-control auth-input" type="email" name="email" placeholder="Email address" required>
                    </div>
                    <div class="mb-3 text-muted small px-2">
                        Password must be at least 6 characters.
                    </div>
                    <div class="mb-3">
                        <input class="form-control auth-input" type="password" name="password" placeholder="New password" required>
                    </div>

                    <button class="btn auth-btn create-btn w-100 mt-2 mb-4">Sign Up</button>

                    <div class="text-center text-muted small px-2 mb-3">
                        By clicking Sign Up, you agree to our Terms, Privacy Policy and Cookies Policy.
                    </div>

                    <div class="text-center mt-3">
                        <a href="login.php" class="text-decoration-none fw-bold" style="color: var(--accent-color);">Already have an account? Log in</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

</body>
</html>