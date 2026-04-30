// assets/js/main.js
function submitReaction(targetType, targetId, reactionType) {
    if (!reactionType.trim()) return;

    const formData = new FormData();
    formData.append('target_type', targetType);
    formData.append('target_id', targetId);
    formData.append('reaction_type', reactionType.trim());

    fetch('react.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const countsSpan = document.getElementById(`reactionCounts_${targetType}_${targetId}`);
            if(countsSpan) {
                let newHtml = '';
                for (const [emoji, count] of Object.entries(data.counts)) {
                    newHtml += `${emoji} <span class="badge bg-secondary">${count}</span> `;
                }
                countsSpan.innerHTML = newHtml;
            }
            const customInput = document.getElementById(`customReaction_${targetType}_${targetId}`);
            if(customInput) customInput.value = '';
        }
    })
    .catch(error => console.error('Error submitting reaction:', error));
}

document.addEventListener("DOMContentLoaded", function() {
    // Global Notifications Polling
    const notifCount = document.getElementById('notif-count');
    const notifList = document.getElementById('notif-list');
    const notifDropdown = document.getElementById('notifDropdown');
    let lastNotifId = 0;

    function fetchNotifications() {
        if(!notifCount) return; // User not logged in

        fetch(`fetch_notifications.php?last_id=${lastNotifId}`)
            .then(res => res.json())
            .then(data => {
                if (data.unread_count > 0) {
                    notifCount.style.display = 'inline-block';
                    notifCount.textContent = data.unread_count;
                } else {
                    notifCount.style.display = 'none';
                }

                if (data.notifications && data.notifications.length > 0) {
                    if (lastNotifId === 0) notifList.innerHTML = ''; // Clear default

                    data.notifications.forEach(notif => {
                        let text = '';
                        let link = '#';
                        if (notif.type === 'reaction') { text = 'reacted to your post/comment'; }
                        else if (notif.type === 'comment') { text = 'commented on your post'; }
                        else if (notif.type === 'mention_post') { text = 'mentioned you in a post'; }
                        else if (notif.type === 'mention_comment') { text = 'mentioned you in a comment'; }
                        else if (notif.type === 'connection_request') { text = 'sent you a connection request'; link = `profile.php?id=${notif.sender_id}`; }
                        else if (notif.type === 'connection_accepted') { text = 'accepted your connection request'; link = `profile.php?id=${notif.sender_id}`; }

                        // Escape first_name to prevent XSS
                        const escFirstName = document.createElement('div');
                        escFirstName.textContent = notif.first_name;
                        const safeFirstName = escFirstName.innerHTML;

                        const html = `
                            <li>
                                <a class="dropdown-item ${notif.is_read ? 'text-muted' : 'fw-bold'} bg-dark border-bottom border-secondary" href="${link}">
                                    <div class="d-flex align-items-center">
                                        <img src="${notif.avatar || '/assets/img/default-avatar.png'}" class="rounded-circle me-2" width="30" height="30" style="object-fit:cover;">
                                        <span>${safeFirstName} ${text}</span>
                                    </div>
                                </a>
                            </li>
                        `;
                        notifList.insertAdjacentHTML('afterbegin', html);
                        lastNotifId = Math.max(lastNotifId, notif.id);
                    });
                }
            });
    }

    if(notifDropdown) {
        notifDropdown.addEventListener('show.bs.dropdown', function () {
            fetch('mark_notifications_read.php', { method: 'POST' })
                .then(res => res.json())
                .then(data => {
                    if(data.success) {
                        notifCount.style.display = 'none';
                        notifCount.textContent = '0';
                    }
                });
        });

        // Poll every 5 seconds
        fetchNotifications();
        setInterval(fetchNotifications, 5000);
    }
});