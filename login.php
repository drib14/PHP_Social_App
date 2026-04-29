<?php
require_once 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, name, password FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {

        if (password_verify($password, $user['password'])) {

            // Store session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];

            header("Location: dashboard.php");
            exit;

        } else {
            $error = "The password you've entered is incorrect.";
        }

    } else {
        $error = "The email address you entered isn't connected to an account.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MiniSocial - Login</title>
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
            border-color: var(--accent-color) !important;
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.15) !important;
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

    <div class="auth-wrapper">
        <!-- Brand Section -->
        <div class="auth-brand">
            <div class="auth-brand-content">
                <h1><i class="fa-brands fa-envira me-3"></i>MiniSocial</h1>
                <p>Connect with friends and the world around you on MiniSocial.</p>
            </div>
        </div>

        <!-- Form Section -->
        <div class="auth-form-container">
            <div class="glass-card">
                <div class="d-lg-none text-center mb-4">
                    <h1 class="fw-bold" style="color: var(--accent-color);"><i class="fa-brands fa-envira me-2"></i>MiniSocial</h1>
                </div>

                <?php if($error): ?>
                    <div class="alert alert-danger" style="background: rgba(239, 68, 68, 0.1); border-color: var(--danger); color: #fca5a5; border-radius: 12px;">
                        <i class="fa-solid fa-circle-exclamation me-2"></i><?= $error ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="mb-3 position-relative">
                        <input type="email" name="email" class="form-control auth-input" placeholder="Email address" required autofocus>
                    </div>
                    <div class="mb-3">
                        <input type="password" name="password" class="form-control auth-input" placeholder="Password" required>
                    </div>
                    <button type="submit" class="btn auth-btn w-100 mb-3">Log In</button>

                    <div class="text-center mb-3">
                        <a href="#" class="text-decoration-none" style="color: var(--accent-color); font-size: 0.9rem;">Forgot password?</a>
                    </div>

                    <hr class="border-secondary mb-4">

                    <div class="text-center">
                        <a href="register.php" class="btn auth-btn create-btn w-auto px-4">Create new account</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

</body>
</html>