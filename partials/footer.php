</div>

    <!-- Real-time Notifications Toast Container -->
    <?php if (isset($_SESSION['user_id'])): ?>
    <div class="toast-container position-fixed bottom-0 start-0 p-3" id="toastContainer" style="z-index: 1060; margin-bottom: 70px;">
        <!-- Toasts injected here -->
    </div>

    <script>
        let knownNotifs = new Set();

        async function pollNotifications() {
            try {
                const res = await fetch('api_notifications.php');
                if(!res.ok) return;
                const data = await res.json();

                // Update badge if exists
                const badge = document.getElementById('notif-badge');
                if(badge) {
                    if (data.length > 0) {
                        badge.innerText = data.length;
                        badge.classList.remove('d-none');
                    } else {
                        badge.classList.add('d-none');
                    }
                }

                // Trigger toasts for new ones
                data.forEach(n => {
                    if(!knownNotifs.has(n.id)) {
                        knownNotifs.add(n.id);
                        showToast(n);
                    }
                });

            } catch (e) { console.error('Notification polling error', e); }
        }

        function showToast(n) {
            let icon = 'fa-bell';
            let text = 'interacted with you.';
            if (n.type === 'like') { icon = 'fa-heart text-danger'; text = 'liked your post.'; }
            if (n.type === 'comment') { icon = 'fa-comment text-primary'; text = 'commented on your post.'; }
            if (n.type === 'follow') { icon = 'fa-user-plus text-success'; text = 'started following you.'; }

            const toastHTML = `
                <div class="toast align-items-center text-white bg-dark border-secondary shadow-lg" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="d-flex">
                        <div class="toast-body d-flex align-items-center gap-3">
                            <i class="fa-solid ${icon} fs-4"></i>
                            <div>
                                <strong>${n.actor_name}</strong> ${text}
                            </div>
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                </div>
            `;

            const container = document.getElementById('toastContainer');
            container.insertAdjacentHTML('beforeend', toastHTML);

            const toastEl = container.lastElementChild;
            const bsToast = new bootstrap.Toast(toastEl, { delay: 5000 });
            bsToast.show();

            toastEl.addEventListener('hidden.bs.toast', () => { toastEl.remove(); });
        }

        // Poll every 5 seconds
        setInterval(pollNotifications, 5000);
        // Initial fetch to seed known notifs without alerting
        fetch('api_notifications.php').then(r => r.json()).then(d => {
            if(d) d.forEach(n => knownNotifs.add(n.id));
        }).catch(e => {});

    </script>
    <?php endif; ?>

    <?php
    // Include chat interface if user is logged in
    if (isset($_SESSION['user_id'])) {
        include 'partials/chat.php';
    }
    ?>

    <!-- Bootstrap Bundle with Popper for Dropdowns -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>