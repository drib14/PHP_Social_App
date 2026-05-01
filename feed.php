<?php
require_once __DIR__ . '/src/bootstrap.php';
require_once __DIR__ . '/src/Cloudinary.php';
require_auth();

$u = current_user();
if (!$u) { session_unset(); session_destroy(); header('Location: login.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) die('csrf');

    if (isset($_POST['new_post'])) {
        $body = trim($_POST['body']);
        $mediaUrl = null;
        if (!empty($_FILES['media']['tmp_name'])) {
            $mediaUrl = Cloudinary::upload($_FILES['media']['tmp_name']);
        }
        $stmt = db()->prepare('INSERT INTO posts(user_id, body, media_url) VALUES(:u, :b, :m)');
        $stmt->execute(['u' => $u['id'], 'b' => $body, 'm' => $mediaUrl]);
    }

    if (isset($_POST['share_post'])) {
        $sid=(int)$_POST['share_post_id'];
        db()->prepare('INSERT INTO posts(user_id,body,shared_post_id) VALUES(:u,:b,:s)')->execute(['u'=>$u['id'],'b'=>trim($_POST['share_body']??'Shared post'),'s'=>$sid]);
        $owner = get_post_owner($sid);
        if ($owner) add_notification($owner, $u['id'], 'share', 'post', $sid, "shared your post");
    }
    if (isset($_POST['comment'])) {
        $pid=(int)$_POST['post_id'];
        db()->prepare('INSERT INTO comments(post_id,user_id,body) VALUES(:p,:u,:b)')->execute(['p'=>$pid,'u'=>$u['id'],'b'=>trim($_POST['comment_body'])]);
        $owner = get_post_owner($pid);
        if ($owner) add_notification($owner, $u['id'], 'comment', 'post', $pid, "commented on your post");
    }
    if (isset($_POST['reply'])) {
        $cid=(int)$_POST['comment_id'];
        db()->prepare('INSERT INTO replies(comment_id,user_id,body) VALUES(:c,:u,:b)')->execute(['c'=>$cid,'u'=>$u['id'],'b'=>trim($_POST['reply_body'])]);
        $owner = get_comment_owner($cid);
        if ($owner) add_notification($owner, $u['id'], 'reply', 'comment', $cid, "replied to your comment");
    }
    if (isset($_POST['react'])) {
        $k=$_POST['reaction_key'];
        $label=$_POST['reaction_label'];
        if($k==='custom'){
            if (!empty($_FILES['custom_reaction_image']['tmp_name'])) {
                $label = Cloudinary::upload($_FILES['custom_reaction_image']['tmp_name']);
                $label = '<img src="'.$label.'" style="height:20px; width:auto; vertical-align:middle; border-radius:4px;">';
            } else {
                $label = htmlspecialchars(trim($_POST['custom_reaction_emoji'] ?? ''));
            }
            $k='custom';
        }
        $tt=$_POST['target_type'];
        $tid=(int)$_POST['target_id'];
        db()->prepare('INSERT INTO reactions(user_id,target_type,target_id,reaction_key,reaction_label) VALUES(:u,:t,:id,:k,:l) ON DUPLICATE KEY UPDATE reaction_key=VALUES(reaction_key),reaction_label=VALUES(reaction_label)')->execute(['u'=>$u['id'],'t'=>$tt,'id'=>$tid,'k'=>$k,'l'=>$label]);

        $owner = null;
        if ($tt === 'post') $owner = get_post_owner($tid);
        elseif ($tt === 'comment') $owner = get_comment_owner($tid);
        elseif ($tt === 'reply') $owner = get_reply_owner($tid);

        if ($owner) add_notification($owner, $u['id'], 'reaction', $tt, $tid, "reacted to your " . $tt);
    }

    header('Location: feed.php'); exit;
}

$posts = db()->query('SELECT p.*, u.name FROM posts p JOIN users u ON u.id = p.user_id ORDER BY p.id DESC LIMIT 40')->fetchAll();

// Fetch comments
$comments = db()->query('SELECT c.*, u.name FROM comments c JOIN users u ON u.id = c.user_id ORDER BY c.id ASC')->fetchAll();
$commentsByPost = [];
foreach ($comments as $c) { $commentsByPost[$c['post_id']][] = $c; }

// Fetch replies
$replies = db()->query('SELECT r.*, u.name FROM replies r JOIN users u ON u.id = r.user_id ORDER BY r.id ASC')->fetchAll();
$repliesByComment = [];
foreach ($replies as $r) { $repliesByComment[$r['comment_id']][] = $r; }

// Fetch reactions
$reactions = db()->query('SELECT r.*, u.name FROM reactions r JOIN users u ON u.id = r.user_id')->fetchAll();
$reactionsMap = ['post'=>[], 'comment'=>[], 'reply'=>[]];
foreach ($reactions as $r) {
    $reactionsMap[$r['target_type']][$r['target_id']][] = $r;
}

function renderReactions($type, $id, $reactionsMap, $u) {
    $list = $reactionsMap[$type][$id] ?? [];
    $userReact = null;
    $counts = [];
    foreach ($list as $r) {
        if ($r['user_id'] == $u['id']) $userReact = $r;
        $counts[$r['reaction_label']] = ($counts[$r['reaction_label']] ?? 0) + 1;
    }

    $summary = '';
    if (!empty($counts)) {
        $topReacts = array_keys($counts);
        $summary = '<div class="d-flex align-items-center gap-1 text-muted small">';
        foreach (array_slice($topReacts, 0, 3) as $lbl) {
            $summary .= '<span>'.$lbl.'</span>';
        }
        $summary .= '<span>'.count($list).'</span></div>';
    }

    return [$userReact, $summary];
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Feed | Socialize</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<!-- Topbar -->
<header class="topbar d-flex justify-content-between align-items-center">
    <div class="d-flex align-items-center gap-3">
        <a href="feed.php" class="text-decoration-none">
            <h3 class="mb-0 text-success fw-bold"><i class="fa-solid fa-leaf"></i> Socialize</h3>
        </a>
        <div class="position-relative">
            <i class="fa-solid fa-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
            <input type="text" class="form-control search-input" placeholder="Search Socialize" id="searchInput" autocomplete="off">
            <div id="searchResults" class="dropdown-menu position-absolute w-100 mt-1 shadow-sm" style="display: none; background: var(--card);"></div>
        </div>
    </div>

    <div class="d-flex align-items-center gap-3">
        <button class="icon-btn" title="Notifications" id="notifBtn">
            <i class="fa-solid fa-bell"></i>
            <span class="notif-badge d-none" id="notifBadge">0</span>
        </button>
        <div class="dropdown">
            <button class="icon-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <?= initials($u['name']) ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow" style="background-color: var(--card); border-color: var(--border);">
                <li><a class="dropdown-item text-white" href="#"><?= htmlspecialchars($u['name']) ?></a></li>
                <li><hr class="dropdown-divider border-secondary"></li>
                <li>
                    <form id="logoutForm" method="post" action="logout.php">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <button type="submit" class="dropdown-item text-danger"><i class="fa-solid fa-arrow-right-from-bracket me-2"></i>Logout</button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</header>

<div class="container-fluid feed-layout row mx-auto">
    <!-- Left Rail -->
    <div class="col-lg-3 d-none d-lg-block">
        <aside class="left-rail card p-3">
            <div class="d-flex align-items-center gap-3 mb-3">
                <div class="avatar"><?= initials($u['name']) ?></div>
                <div>
                    <h6 class="mb-0 fw-bold text-white"><?= htmlspecialchars($u['name']) ?></h6>
                    <small class="text-muted">@<?= strtolower(str_replace(' ', '', $u['name'])) ?></small>
                </div>
            </div>
            <nav class="nav flex-column gap-2">
                <a class="nav-link active" href="#"><i class="fa-solid fa-house me-2"></i> Feed</a>
                <a class="nav-link" href="#"><i class="fa-solid fa-user-group me-2"></i> Friends</a>
                <a class="nav-link" href="#"><i class="fa-solid fa-bookmark me-2"></i> Saved</a>
            </nav>
        </aside>
    </div>

    <!-- Center Feed -->
    <main class="col-lg-6 col-md-12">
        <!-- Composer -->
        <div class="card composer mb-3 p-3">
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <div class="d-flex gap-3 mb-3">
                    <div class="avatar"><?= initials($u['name']) ?></div>
                    <textarea class="form-control" name="body" rows="2" placeholder="What's on your mind, <?= htmlspecialchars(explode(' ', $u['name'])[0]) ?>?" required></textarea>
                </div>
                <hr class="border-secondary mb-2 mt-0">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <label for="mediaUpload" class="action-btn text-success m-0" style="width: auto; cursor: pointer;">
                            <i class="fa-solid fa-image"></i> Photo/Video
                        </label>
                        <input type="file" id="mediaUpload" name="media" accept="image/*,video/*" class="d-none">
                    </div>
                    <button type="submit" name="new_post" class="btn btn-primary px-4 rounded-pill">Post</button>
                </div>
            </form>
        </div>

        <!-- Posts -->
        <?php foreach ($posts as $p): ?>
        <article class="card p-3 mb-3">
            <div class="d-flex align-items-center gap-3 mb-2">
                <div class="avatar"><?= initials($p['name']) ?></div>
                <div>
                    <h6 class="mb-0 fw-bold text-white"><?= htmlspecialchars($p['name']) ?></h6>
                    <small class="text-muted"><?= date('M j, g:i a', strtotime($p['created_at'])) ?></small>
                </div>
            </div>

            <p class="mb-2 text-white"><?= nl2br(htmlspecialchars($p['body'])) ?></p>

            <?php if (!empty($p['media_url'])): ?>
                <?php if (preg_match('/\.(mp4|webm|ogg)$/i', $p['media_url'])): ?>
                    <video src="<?= htmlspecialchars($p['media_url']) ?>" class="post-media" controls></video>
                <?php else: ?>
                    <img src="<?= htmlspecialchars($p['media_url']) ?>" class="post-media" alt="Post media">
                <?php endif; ?>
            <?php endif; ?>

            <hr class="border-secondary my-2">

            <?php list($userReact, $reactSummary) = renderReactions('post', $p['id'], $reactionsMap, $u); ?>
            <?php if($reactSummary) echo '<div class="px-2 pb-2 border-bottom border-secondary">'.$reactSummary.'</div>'; ?>

            <div class="d-flex justify-content-between px-2 pt-2 pb-2 position-relative">

                <div class="dropdown">
                    <button class="action-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" data-bs-auto-close="outside">
                        <?php if($userReact): ?>
                            <?= $userReact['reaction_label'] ?>
                        <?php else: ?>
                            <i class="fa-regular fa-thumbs-up"></i> React
                        <?php endif; ?>
                    </button>
                    <div class="dropdown-menu p-2 shadow-sm border-secondary" style="background: var(--card); min-width: 250px;">
                        <form method="post" enctype="multipart/form-data" class="d-flex align-items-center gap-2">
                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                            <input type="hidden" name="target_type" value="post">
                            <input type="hidden" name="target_id" value="<?= $p['id'] ?>">

                            <?php foreach(react_options() as $r): ?>
                                <?php if($r['key'] !== 'custom'): ?>
                                    <button type="submit" name="react" class="btn btn-sm btn-outline-secondary border-0 fs-5" title="<?= strip_tags($r['label']) ?>" onclick="this.form.reaction_key.value='<?= $r['key'] ?>'; this.form.reaction_label.value='<?= htmlspecialchars($r['label'], ENT_QUOTES) ?>';">
                                        <?= $r['label'] ?>
                                    </button>
                                <?php endif; ?>
                            <?php endforeach; ?>

                            <button type="button" class="btn btn-sm btn-outline-info border-0 fs-5 ms-auto" title="Custom" data-bs-toggle="modal" data-bs-target="#customReactModal" onclick="setCustomReactTarget('post', <?= $p['id'] ?>)">
                                ✨
                            </button>

                            <input type="hidden" name="reaction_key" value="like">
                            <input type="hidden" name="reaction_label" value='<i class="fa-solid fa-thumbs-up text-primary"></i> Like'>
                        </form>
                    </div>
                </div>

                <button class="action-btn" type="button" data-bs-toggle="collapse" data-bs-target="#comments-<?= $p['id'] ?>">
                    <i class="fa-regular fa-comment"></i> Comment
                </button>
            </div>

            <!-- Comments Section -->
            <div class="collapse mt-2" id="comments-<?= $p['id'] ?>">
                <hr class="border-secondary my-2">

                <form method="post" class="d-flex gap-2 mb-3">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="post_id" value="<?= $p['id'] ?>">
                    <div class="avatar" style="width:32px;height:32px;font-size:0.8rem;"><?= initials($u['name']) ?></div>
                    <input type="text" name="comment_body" class="form-control comment-input" placeholder="Write a comment..." required>
                    <button type="submit" name="comment" class="btn btn-sm btn-primary rounded-circle" style="width:38px;height:38px;"><i class="fa-solid fa-paper-plane"></i></button>
                </form>

                <?php foreach ($commentsByPost[$p['id']] ?? [] as $c): ?>
                    <div class="d-flex gap-2 mb-2">
                        <div class="avatar" style="width:32px;height:32px;font-size:0.8rem;"><?= initials($c['name']) ?></div>
                        <div class="flex-grow-1">
                            <div class="comment-box">
                                <h6 class="mb-0 fw-bold text-white fs-6"><?= htmlspecialchars($c['name']) ?></h6>
                                <p class="mb-0 text-white"><?= nl2br(htmlspecialchars($c['body'])) ?></p>
                            </div>

                            <?php list($cReact, $cReactSum) = renderReactions('comment', $c['id'], $reactionsMap, $u); ?>
                            <div class="d-flex align-items-center gap-3 ms-2 mt-1">
                                <small class="text-muted fw-bold dropdown">
                                    <span class="dropdown-toggle" style="cursor:pointer" data-bs-toggle="dropdown" data-bs-auto-close="outside">
                                        <?= $cReact ? strip_tags($cReact['reaction_label']) : 'React' ?>
                                    </span>
                                    <div class="dropdown-menu p-1 shadow-sm border-secondary" style="background: var(--card);">
                                        <form method="post" enctype="multipart/form-data" class="d-flex gap-1">
                                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                            <input type="hidden" name="target_type" value="comment">
                                            <input type="hidden" name="target_id" value="<?= $c['id'] ?>">
                                            <?php foreach(react_options() as $r): ?>
                                                <?php if($r['key'] !== 'custom'): ?>
                                                    <button type="submit" name="react" class="btn btn-sm btn-outline-secondary border-0" onclick="this.form.reaction_key.value='<?= $r['key'] ?>'; this.form.reaction_label.value='<?= htmlspecialchars($r['label'], ENT_QUOTES) ?>';"><?= $r['label'] ?></button>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                            <button type="button" class="btn btn-sm btn-outline-info border-0" data-bs-toggle="modal" data-bs-target="#customReactModal" onclick="setCustomReactTarget('comment', <?= $c['id'] ?>)">✨</button>
                                            <input type="hidden" name="reaction_key" value="like">
                                            <input type="hidden" name="reaction_label" value='<i class="fa-solid fa-thumbs-up text-primary"></i> Like'>
                                        </form>
                                    </div>
                                </small>
                                <small class="text-muted fw-bold" style="cursor:pointer" data-bs-toggle="collapse" data-bs-target="#reply-form-<?= $c['id'] ?>">Reply</small>
                                <?= $cReactSum ?>
                            </div>

                            <!-- Replies -->
                            <div class="ms-4 mt-2">
                                <?php foreach ($repliesByComment[$c['id']] ?? [] as $rep): ?>
                                    <div class="d-flex gap-2 mb-2">
                                        <div class="avatar" style="width:24px;height:24px;font-size:0.7rem;"><?= initials($rep['name']) ?></div>
                                        <div>
                                            <div class="comment-box py-1 px-2">
                                                <h6 class="mb-0 fw-bold text-white" style="font-size: 0.85rem;"><?= htmlspecialchars($rep['name']) ?></h6>
                                                <p class="mb-0 text-white" style="font-size: 0.85rem;"><?= nl2br(htmlspecialchars($rep['body'])) ?></p>
                                            </div>

                                            <?php list($rReact, $rReactSum) = renderReactions('reply', $rep['id'], $reactionsMap, $u); ?>
                                            <div class="d-flex align-items-center gap-3 ms-2 mt-1">
                                                <small class="text-muted fw-bold dropdown">
                                                    <span class="dropdown-toggle" style="cursor:pointer" data-bs-toggle="dropdown" data-bs-auto-close="outside">
                                                        <?= $rReact ? strip_tags($rReact['reaction_label']) : 'React' ?>
                                                    </span>
                                                    <div class="dropdown-menu p-1 shadow-sm border-secondary" style="background: var(--card);">
                                                        <form method="post" enctype="multipart/form-data" class="d-flex gap-1">
                                                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                                            <input type="hidden" name="target_type" value="reply">
                                                            <input type="hidden" name="target_id" value="<?= $rep['id'] ?>">
                                                            <?php foreach(react_options() as $r): ?>
                                                                <?php if($r['key'] !== 'custom'): ?>
                                                                    <button type="submit" name="react" class="btn btn-sm btn-outline-secondary border-0" onclick="this.form.reaction_key.value='<?= $r['key'] ?>'; this.form.reaction_label.value='<?= htmlspecialchars($r['label'], ENT_QUOTES) ?>';"><?= $r['label'] ?></button>
                                                                <?php endif; ?>
                                                            <?php endforeach; ?>
                                                            <button type="button" class="btn btn-sm btn-outline-info border-0" data-bs-toggle="modal" data-bs-target="#customReactModal" onclick="setCustomReactTarget('reply', <?= $rep['id'] ?>)">✨</button>
                                                            <input type="hidden" name="reaction_key" value="like">
                                                            <input type="hidden" name="reaction_label" value='<i class="fa-solid fa-thumbs-up text-primary"></i> Like'>
                                                        </form>
                                                    </div>
                                                </small>
                                                <?= $rReactSum ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>

                                <div class="collapse mt-2" id="reply-form-<?= $c['id'] ?>">
                                    <form method="post" class="d-flex gap-2 mb-2">
                                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                        <input type="hidden" name="comment_id" value="<?= $c['id'] ?>">
                                        <div class="avatar" style="width:24px;height:24px;font-size:0.7rem;"><?= initials($u['name']) ?></div>
                                        <input type="text" name="reply_body" class="form-control form-control-sm comment-input" placeholder="Reply..." required>
                                        <button type="submit" name="reply" class="btn btn-sm btn-primary rounded-circle" style="width:30px;height:30px;padding:0;"><i class="fa-solid fa-paper-plane" style="font-size:0.7rem;"></i></button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </article>
        <?php endforeach; ?>
    </main>

    <!-- Right Rail -->
    <div class="col-lg-3 d-none d-lg-block">
        <aside class="right-rail card p-3">
            <h6 class="fw-bold text-white mb-3">Notifications</h6>
            <ul id="notifList" class="list-unstyled mb-0 d-flex flex-column gap-2 text-muted">
                <!-- Loaded via JS -->
            </ul>
        </aside>
    </div>
</div>

<!-- Toast Container -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" id="toastContainer"></div>

<!-- Custom Reaction Modal -->
<div class="modal fade" id="customReactModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="background: var(--card); border-color: var(--border);">
      <div class="modal-header border-secondary">
        <h5 class="modal-title text-white">Custom Reaction</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="customReactForm" method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="react" value="1">
            <input type="hidden" name="reaction_key" value="custom">
            <input type="hidden" name="reaction_label" value="custom">
            <input type="hidden" name="target_type" id="customTargetType">
            <input type="hidden" name="target_id" id="customTargetId">

            <ul class="nav nav-tabs mb-3 border-secondary" id="customReactTabs" role="tablist">
              <li class="nav-item" role="presentation">
                <button class="nav-link active text-white border-secondary bg-transparent" id="emoji-tab" data-bs-toggle="tab" data-bs-target="#emoji-pane" type="button" role="tab">Emoji</button>
              </li>
              <li class="nav-item" role="presentation">
                <button class="nav-link text-white border-secondary bg-transparent" id="image-tab" data-bs-toggle="tab" data-bs-target="#image-pane" type="button" role="tab">Image</button>
              </li>
            </ul>

            <div class="tab-content" id="myTabContent">
              <div class="tab-pane fade show active" id="emoji-pane" role="tabpanel" tabindex="0">
                  <label class="form-label text-white">Pick an emoji or type text:</label>
                  <input type="text" name="custom_reaction_emoji" class="form-control bg-dark text-white border-secondary" placeholder="e.g. 🍕 or 🔥">
              </div>
              <div class="tab-pane fade" id="image-pane" role="tabpanel" tabindex="0">
                  <label class="form-label text-white">Upload an image reaction:</label>
                  <input type="file" name="custom_reaction_image" class="form-control bg-dark text-white border-secondary" accept="image/*">
              </div>
            </div>

            <div class="mt-4 text-end">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">React</button>
            </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function setCustomReactTarget(type, id) {
        document.getElementById('customTargetType').value = type;
        document.getElementById('customTargetId').value = id;
    }

    // Search functionality
    const searchInput = document.getElementById('searchInput');
    const searchResults = document.getElementById('searchResults');

    searchInput.addEventListener('input', async (e) => {
        const q = e.target.value.trim();
        if (q.length < 2) {
            searchResults.style.display = 'none';
            return;
        }

        try {
            const res = await fetch(`search_users.php?q=${encodeURIComponent(q)}`);
            const users = await res.json();

            if (users.length > 0) {
                searchResults.innerHTML = users.map(u => `
                    <a href="#" class="dropdown-item d-flex align-items-center gap-2 text-white py-2 border-bottom border-secondary">
                        <div class="avatar" style="width:24px;height:24px;font-size:0.7rem;">${u.name.substring(0,2).toUpperCase()}</div>
                        ${u.name}
                    </a>
                `).join('');
                searchResults.style.display = 'block';
            } else {
                searchResults.innerHTML = '<div class="p-2 text-muted text-center">No users found</div>';
                searchResults.style.display = 'block';
            }
        } catch (e) {
            console.error(e);
        }
    });

    document.addEventListener('click', (e) => {
        if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
            searchResults.style.display = 'none';
        }
    });

    // Real-time Notifications Polling
    let displayedNotifIds = new Set();
    const notifBadge = document.getElementById('notifBadge');
    const notifBtn = document.getElementById('notifBtn');

    async function loadNotifications() {
        try {
            const res = await fetch('notifications.php');
            const data = await res.json();

            const list = document.getElementById('notifList');
            if(data.notifications.length === 0) {
                list.innerHTML = '<li class="text-center small">No notifications</li>';
            } else {
                list.innerHTML = data.notifications.slice(0, 8).map(n => `
                    <li class="p-2 rounded ${n.is_read ? '' : 'bg-dark'}">
                        <div class="d-flex align-items-start gap-2">
                            <i class="fa-solid fa-bell text-primary mt-1" style="font-size: 0.8rem;"></i>
                            <div style="font-size: 0.85rem;">
                                <strong class="text-white">${n.actor_name}</strong> ${n.message}
                            </div>
                        </div>
                    </li>
                `).join('');
            }

            // Badge
            if (data.unread_count > 0) {
                notifBadge.textContent = data.unread_count;
                notifBadge.classList.remove('d-none');
            } else {
                notifBadge.classList.add('d-none');
            }

            // Toasts for new notifications
            data.notifications.forEach(n => {
                if (!n.is_read && !displayedNotifIds.has(n.id)) {
                    showToast(n);
                    displayedNotifIds.add(n.id);
                }
            });

        } catch(e) {
            console.error(e);
        }
    }

    function showToast(n) {
        const toastContainer = document.getElementById('toastContainer');
        const toastId = 'toast-' + n.id;
        const toastHTML = `
            <div id="${toastId}" class="toast align-items-center border-primary" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="5000">
                <div class="toast-header">
                    <i class="fa-solid fa-bell text-primary me-2"></i>
                    <strong class="me-auto">New Notification</strong>
                    <small>Just now</small>
                    <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
                <div class="toast-body">
                    <strong>${n.actor_name}</strong> ${n.message}
                </div>
            </div>
        `;
        toastContainer.insertAdjacentHTML('beforeend', toastHTML);
        const toastElement = document.getElementById(toastId);
        const toast = new bootstrap.Toast(toastElement);
        toast.show();

        toastElement.addEventListener('hidden.bs.toast', () => {
            toastElement.remove();
        });
    }

    notifBtn.addEventListener('click', async () => {
        await fetch('notifications.php', { method: 'POST' });
        loadNotifications();
    });

    loadNotifications();
    setInterval(loadNotifications, 5000);
</script>
</body>
</html>
