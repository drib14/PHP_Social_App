<?php require_once 'config.php'; ?>
<!DOCTYPE html>
<html>

<head>
    <title>Socialize</title>
    <!-- Google Fonts: Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="assets/style.css" rel="stylesheet">
</head>

<body>

    <nav class="navbar sticky-top d-flex justify-content-between align-items-center px-3">
        <!-- Left: Brand & Search -->
        <div class="d-flex align-items-center gap-2" style="width: 30%;">
            <a class="navbar-brand fw-bold mb-0 text-decoration-none" href="dashboard.php" style="color: var(--accent-color);">
                <i class="fa-brands fa-envira fs-3"></i>
            </a>
            <?php if (isset($_SESSION['user_id'])): ?>
            <form method="GET" action="search.php" class="d-none d-md-flex position-relative flex-grow-1" style="max-width: 240px;">
                <i class="fa-solid fa-search position-absolute text-muted" style="left: 12px; top: 50%; transform: translateY(-50%);"></i>
                <input type="text" name="q" class="form-control ps-5 rounded-pill border-0 text-white" placeholder="Search Socialize" style="background-color: var(--bg-hover) !important; font-size: 0.95rem;">
            </form>
            <?php endif; ?>
        </div>

        <!-- Center: Nav Icons -->
        <?php if (isset($_SESSION['user_id'])): ?>
        <?php
            $current_page = basename($_SERVER['PHP_SELF']);
        ?>
        <div class="nav-icon-container d-none d-md-flex">
            <a href="dashboard.php" class="nav-icon <?= ($current_page == 'dashboard.php') ? 'active' : '' ?>">
                <i class="fa-solid fa-house"></i>
            </a>
            <a href="dashboard.php?feed=following" class="nav-icon <?= (isset($_GET['feed']) && $_GET['feed'] == 'following') ? 'active' : '' ?>">
                <i class="fa-solid fa-user-group"></i>
            </a>
            <a href="search.php" class="nav-icon d-md-none <?= ($current_page == 'search.php') ? 'active' : '' ?>">
                <i class="fa-solid fa-magnifying-glass"></i>
            </a>
        </div>
        <?php endif; ?>

        <!-- Right: Profile & Notifications -->
        <div class="d-flex align-items-center justify-content-end gap-2" style="width: 25%;">
            <?php
            $unread_count = 0;
            $notifications_result = null;
            if (isset($_SESSION['user_id'])) {
                $current_user_id = $_SESSION['user_id'];
                // Get unread count
                $count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = FALSE");
                $count_stmt->bind_param("i", $current_user_id);
                $count_stmt->execute();
                $unread_count = $count_stmt->get_result()->fetch_assoc()['count'];

                // Get latest notifications
                $notif_stmt = $conn->prepare("
                    SELECT n.*, u.name as actor_name
                    FROM notifications n
                    JOIN users u ON u.id = n.actor_id
                    WHERE n.user_id = ?
                    ORDER BY n.created_at DESC LIMIT 5
                ");
                $notif_stmt->bind_param("i", $current_user_id);
                $notif_stmt->execute();
                $notifications_result = $notif_stmt->get_result();
            }
            ?>
            <?php if (isset($_SESSION['user_id'])): ?>

            <!-- Notifications Dropdown -->
            <div class="dropdown">
                <button class="nav-action-btn position-relative" type="button" id="notificationsDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fa-solid fa-bell"></i>
                    <?php if ($unread_count > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.6rem; margin-left: -5px; margin-top: 5px;">
                            <?= $unread_count ?>
                        </span>
                    <?php endif; ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end dropdown-menu-dark p-2 mt-2" aria-labelledby="notificationsDropdown" style="width: 320px;">
                    <li><h5 class="dropdown-header text-white mb-1 fw-bold fs-5">Notifications</h5></li>

                    <?php if ($notifications_result && $notifications_result->num_rows > 0): ?>
                        <?php while ($notif = $notifications_result->fetch_assoc()): ?>
                            <?php
                                $icon = 'fa-bell';
                                $bg_color = 'var(--accent-color)';
                                $text = '';
                                $link = '#';

                                if ($notif['type'] == 'like') {
                                    $icon = 'fa-heart';
                                    $bg_color = 'var(--danger)';
                                    $text = "liked your post.";
                                    $link = "user_profile.php?id=" . $notif['actor_id'];
                                } elseif ($notif['type'] == 'comment') {
                                    $icon = 'fa-comment';
                                    $bg_color = 'var(--accent-color)';
                                    $text = "commented on your post.";
                                    $link = "user_profile.php?id=" . $notif['actor_id'];
                                } elseif ($notif['type'] == 'follow') {
                                    $icon = 'fa-user-plus';
                                    $bg_color = '#3b82f6';
                                    $text = "started following you.";
                                    $link = "user_profile.php?id=" . $notif['actor_id'];
                                }
                            ?>
                            <li>
                                <a class="dropdown-item d-flex align-items-center gap-3 <?= $notif['is_read'] ? 'opacity-75' : 'fw-bold bg-hover' ?>" href="read_notification.php?id=<?= $notif['id'] ?>&redirect=<?= urlencode($link) ?>">
                                    <div class="position-relative">
                                        <div class="avatar-placeholder" style="width: 48px; height: 48px;">
                                            <?= strtoupper(substr($notif['actor_name'], 0, 1)) ?>
                                        </div>
                                        <div class="position-absolute bottom-0 end-0 rounded-circle d-flex align-items-center justify-content-center" style="width: 20px; height: 20px; background-color: <?= $bg_color ?>; border: 2px solid var(--bg-secondary);">
                                            <i class="fa-solid <?= $icon ?> text-white" style="font-size: 0.5rem;"></i>
                                        </div>
                                    </div>
                                    <div class="text-wrap flex-grow-1" style="font-size: 0.9rem;">
                                        <span class="text-white"><strong><?= htmlspecialchars($notif['actor_name']) ?></strong> <?= $text ?></span>
                                        <div class="text-primary" style="font-size: 0.8rem; color: var(--accent-color) !important;"><?= date('M j, g:i a', strtotime($notif['created_at'])) ?></div>
                                    </div>
                                    <?php if (!$notif['is_read']): ?>
                                        <div class="rounded-circle" style="width: 10px; height: 10px; background-color: var(--accent-color);"></div>
                                    <?php endif; ?>
                                </a>
                            </li>
                        <?php endwhile; ?>
                        <li><hr class="dropdown-divider border-secondary my-2"></li>
                        <li>
                            <form method="POST" action="read_notification.php" class="px-2">
                                <input type="hidden" name="mark_all" value="1">
                                <button type="submit" class="btn text-center w-100 text-white" style="background-color: var(--bg-hover);">Mark all as read</button>
                            </form>
                        </li>
                    <?php else: ?>
                        <li><span class="dropdown-item text-center py-4 text-muted">No new notifications</span></li>
                    <?php endif; ?>
                </ul>
            </div>

            <a href="user_profile.php" class="nav-action-btn">
                <i class="fa-solid fa-user"></i>
            </a>

            <!-- Menu Dropdown (Logout) -->
            <div class="dropdown">
                <button class="nav-action-btn" type="button" id="menuDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end dropdown-menu-dark p-2 mt-2" aria-labelledby="menuDropdown">
                    <li><a class="dropdown-item d-flex align-items-center gap-2" href="user_profile.php"><i class="fa-solid fa-user"></i> My Profile</a></li>
                    <li><a class="dropdown-item d-flex align-items-center gap-2" href="search.php"><i class="fa-solid fa-search"></i> Search Users</a></li>
                    <li><hr class="dropdown-divider border-secondary"></li>
                    <li><a class="dropdown-item d-flex align-items-center gap-2 text-danger" href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a></li>
                </ul>
            </div>
            <?php endif; ?>
        </div>
    </nav>

    <!-- Keep this container empty as it serves as a spacer or wrapper if needed, but we'll use a fluid container below -->
    <div class="container-fluid" style="max-width: 1600px; padding-top: 20px;">