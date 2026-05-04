<?php
// includes/header.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/db.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Socialize</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS (Dark mode compatible concepts) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
</head>
<body>

<nav class="navbar navbar-expand-lg sticky-top">
  <div class="container">
    <a class="navbar-brand" href="<?php echo BASE_URL; ?>/index.php"><i class="fa-solid fa-people-group"></i> Socialize</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon" style="filter: invert(1);"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav ms-auto align-items-center">
        <?php if(isset($_SESSION['user_id'])): ?>
            <li class="nav-item">
                <a class="nav-link" href="<?php echo BASE_URL; ?>/index.php"><i class="fa-solid fa-house"></i> Home</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="<?php echo BASE_URL; ?>/network.php"><i class="fa-solid fa-user-group"></i> Network</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="<?php echo BASE_URL; ?>/chat.php"><i class="fa-solid fa-comment-dots"></i> Chat</a>
            </li>
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" id="notifDropdown" role="button" data-bs-toggle="dropdown">
                    <i class="fa-solid fa-bell"></i>
                    <span class="badge bg-danger rounded-pill" id="notif-count" style="display:none;">0</span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end dropdown-menu-dark" aria-labelledby="notifDropdown" id="notif-list">
                    <li><span class="dropdown-item">No new notifications</span></li>
                </ul>
            </li>
            <li class="nav-item dropdown ms-2">
                <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                    <i class="fa-solid fa-user-circle fs-4"></i>
                </a>
                <ul class="dropdown-menu dropdown-menu-end dropdown-menu-dark" aria-labelledby="userDropdown">
                    <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/profile.php?id=<?php echo $_SESSION['user_id']; ?>">Profile</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/logout.php">Logout</a></li>
                </ul>
            </li>
        <?php else: ?>
            <li class="nav-item">
                <a class="nav-link" href="<?php echo BASE_URL; ?>/login.php">Login</a>
            </li>
            <li class="nav-item">
                <a class="nav-link btn btn-emerald text-white px-3" href="<?php echo BASE_URL; ?>/register.php">Register</a>
            </li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>

<div class="container mt-4 mb-5">