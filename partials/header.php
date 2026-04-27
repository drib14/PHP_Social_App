<?php require_once 'config.php'; ?>
<!DOCTYPE html>
<html>

<head>
    <title>Mini Social</title>
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

    <nav class="navbar navbar-expand-lg navbar-dark px-3 mb-4 sticky-top">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold text-white" href="dashboard.php">
                <i class="fa-solid fa-fire text-primary me-2"></i>MiniSocial
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php"><i class="fa-solid fa-house me-1"></i> Feed</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="search.php"><i class="fa-solid fa-search me-1"></i> Search</a>
                    </li>
                </ul>
                <div class="d-flex align-items-center gap-3">
                    <!-- Notifications Dropdown -->
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
                    <div class="dropdown">
                        <button class="btn btn-link nav-link text-white dropdown-toggle position-relative" type="button" id="notificationsDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fa-solid fa-bell fs-5"></i>
                            <?php if ($unread_count > 0): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.6rem;">
                                    <?= $unread_count ?>
                                </span>
                            <?php endif; ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end dropdown-menu-dark p-2" aria-labelledby="notificationsDropdown" style="width: 300px;">
                            <li><h6 class="dropdown-header text-white border-bottom border-secondary mb-2">Notifications</h6></li>

                            <?php if ($notifications_result && $notifications_result->num_rows > 0): ?>
                                <?php while ($notif = $notifications_result->fetch_assoc()): ?>
                                    <?php
                                        $icon = 'fa-bell';
                                        $text = '';
                                        $link = '#';

                                        if ($notif['type'] == 'like') {
                                            $icon = 'fa-heart text-danger';
                                            $text = "liked your post.";
                                            // Ideally link to post, but linking to profile for now
                                            $link = "user_profile.php?id=" . $notif['actor_id'];
                                        } elseif ($notif['type'] == 'comment') {
                                            $icon = 'fa-comment text-primary';
                                            $text = "commented on your post.";
                                            $link = "user_profile.php?id=" . $notif['actor_id'];
                                        } elseif ($notif['type'] == 'follow') {
                                            $icon = 'fa-user-plus text-success';
                                            $text = "started following you.";
                                            $link = "user_profile.php?id=" . $notif['actor_id'];
                                        }
                                    ?>
                                    <li>
                                        <a class="dropdown-item d-flex align-items-center gap-2 <?= $notif['is_read'] ? 'text-muted' : 'fw-bold' ?>" href="read_notification.php?id=<?= $notif['id'] ?>&redirect=<?= urlencode($link) ?>">
                                            <i class="fa-solid <?= $icon ?>"></i>
                                            <div class="text-wrap" style="font-size: 0.85rem;">
                                                <span><strong><?= htmlspecialchars($notif['actor_name']) ?></strong> <?= $text ?></span>
                                                <div class="text-muted" style="font-size: 0.75rem;"><?= date('M j, g:i a', strtotime($notif['created_at'])) ?></div>
                                            </div>
                                        </a>
                                    </li>
                                <?php endwhile; ?>
                                <li><hr class="dropdown-divider border-secondary"></li>
                                <li>
                                    <form method="POST" action="read_notification.php" class="px-2">
                                        <input type="hidden" name="mark_all" value="1">
                                        <button type="submit" class="btn btn-sm btn-outline-light w-100">Mark all as read</button>
                                    </form>
                                </li>
                            <?php else: ?>
                                <li><span class="dropdown-item text-muted text-center py-3">No new notifications</span></li>
                            <?php endif; ?>
                        </ul>
                    </div>

                    <a href="user_profile.php" class="btn btn-sm btn-outline-light rounded-pill"><i class="fa-solid fa-user me-1"></i> Profile</a>
                    <a href="logout.php" class="btn btn-sm btn-danger rounded-pill"><i class="fa-solid fa-right-from-bracket me-1"></i> Logout</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <div class="container" style="max-width:700px;"></div>