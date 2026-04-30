<?php
session_start();
require_once 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $target_type = $_POST['target_type'] ?? '';
    $target_id = $_POST['target_id'] ?? 0;
    $reaction_type = $_POST['reaction_type'] ?? '';
    $user_id = $_SESSION['user_id'];

    if (!in_array($target_type, ['post', 'comment']) || !$target_id || empty($reaction_type)) {
        http_response_code(400);
        exit();
    }

    // Check if user already reacted
    $stmt = $pdo->prepare("SELECT id, reaction_type FROM reactions WHERE user_id = ? AND target_type = ? AND target_id = ?");
    $stmt->execute([$user_id, $target_type, $target_id]);
    $existing = $stmt->fetch();

    if ($existing) {
        if ($existing['reaction_type'] === $reaction_type) {
            // Remove reaction if clicking the same one
            $del = $pdo->prepare("DELETE FROM reactions WHERE id = ?");
            $del->execute([$existing['id']]);
        } else {
            // Update reaction
            $upd = $pdo->prepare("UPDATE reactions SET reaction_type = ? WHERE id = ?");
            $upd->execute([$reaction_type, $existing['id']]);
        }
    } else {
        // Insert new reaction
        $ins = $pdo->prepare("INSERT INTO reactions (user_id, target_type, target_id, reaction_type) VALUES (?, ?, ?, ?)");
        $ins->execute([$user_id, $target_type, $target_id, $reaction_type]);

        // Send Notification
        $receiver_id = 0;
        if ($target_type === 'post') {
            $rStmt = $pdo->prepare("SELECT user_id FROM posts WHERE id = ?");
            $rStmt->execute([$target_id]);
            $res = $rStmt->fetch();
            $receiver_id = $res['user_id'] ?? 0;
        } else {
            $rStmt = $pdo->prepare("SELECT user_id FROM comments WHERE id = ?");
            $rStmt->execute([$target_id]);
            $res = $rStmt->fetch();
            $receiver_id = $res['user_id'] ?? 0;
        }

        if ($receiver_id && $receiver_id != $user_id) {
            $nStmt = $pdo->prepare("INSERT INTO notifications (user_id, sender_id, type, target_id) VALUES (?, ?, 'reaction', ?)");
            $nStmt->execute([$receiver_id, $user_id, $target_id]);
        }
    }

    // Return new count and list of reactions
    $cStmt = $pdo->prepare("SELECT reaction_type, COUNT(*) as count FROM reactions WHERE target_type = ? AND target_id = ? GROUP BY reaction_type");
    $cStmt->execute([$target_type, $target_id]);
    $counts = $cStmt->fetchAll(PDO::FETCH_KEY_PAIR);

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'counts' => $counts]);
    exit();
}
?>