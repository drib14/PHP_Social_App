<?php require_once 'config.php'; ?>
<!DOCTYPE html>
<html>

<head>
    <title>Mini Social</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/style.css" rel="stylesheet">
</head>

<body>

    <nav class="navbar navbar-dark px-3 mb-4">
        <a class="navbar-brand text-white" href="dashboard.php">MiniSocial</a>

        <div>
            <a href="dashboard.php" class="btn btn-sm btn-light">Feed</a>
            <a href="profile.php" class="btn btn-sm btn-light">Profile</a>
            <a href="logout.php" class="btn btn-sm btn-danger">Logout</a>
        </div>
    </nav>

    <div class="container" style="max-width:700px;"></div>