<?php
// includes/reaction_ui.php
// Expected variables: $target_type ('post' or 'comment'), $target_id
?>
<div class="reaction-container d-inline-block position-relative">
    <button class="btn btn-sm btn-secondary reaction-trigger text-light" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="fa-regular fa-thumbs-up"></i> React
    </button>
    <ul class="dropdown-menu dropdown-menu-dark p-2 bg-dark border-secondary text-center shadow" style="min-width: 250px; border-radius: 20px;">
        <li class="d-inline-block mx-1">
            <span class="fs-4 reaction-btn" onclick="submitReaction('<?php echo $target_type; ?>', <?php echo $target_id; ?>, '👍')" title="Like">👍</span>
        </li>
        <li class="d-inline-block mx-1">
            <span class="fs-4 reaction-btn" onclick="submitReaction('<?php echo $target_type; ?>', <?php echo $target_id; ?>, '❤️')" title="Love">❤️</span>
        </li>
        <li class="d-inline-block mx-1">
            <span class="fs-4 reaction-btn" onclick="submitReaction('<?php echo $target_type; ?>', <?php echo $target_id; ?>, '😂')" title="Haha">😂</span>
        </li>
        <li class="d-inline-block mx-1">
            <span class="fs-4 reaction-btn" onclick="submitReaction('<?php echo $target_type; ?>', <?php echo $target_id; ?>, '🔥')" title="Fire">🔥</span>
        </li>
        <li class="d-inline-block mx-1 position-relative">
            <input type="text" id="customReaction_<?php echo $target_type; ?>_<?php echo $target_id; ?>" class="form-control form-control-sm bg-secondary text-light border-0 d-inline-block text-center" style="width: 40px; border-radius: 50%;" placeholder="+" maxlength="2" onkeypress="if(event.key === 'Enter') submitReaction('<?php echo $target_type; ?>', <?php echo $target_id; ?>, this.value)">
        </li>
    </ul>

    <span class="reaction-counts ms-2 text-muted" id="reactionCounts_<?php echo $target_type; ?>_<?php echo $target_id; ?>">
        <?php
        // Fetch existing reactions
        $cStmt = $pdo->prepare("SELECT reaction_type, COUNT(*) as count FROM reactions WHERE target_type = ? AND target_id = ? GROUP BY reaction_type");
        $cStmt->execute([$target_type, $target_id]);
        $counts = $cStmt->fetchAll(PDO::FETCH_KEY_PAIR);
        foreach($counts as $emoji => $count) {
            echo htmlspecialchars($emoji) . ' <span class="badge bg-secondary">' . $count . '</span> ';
        }
        ?>
    </span>
</div>
