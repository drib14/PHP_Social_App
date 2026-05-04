<?php
// includes/footer.php
?>
</div> <!-- Close container from header -->

<!-- Bootstrap JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<?php if(isset($_SESSION['user_id'])): ?>
<script>
    // Global script for checking notifications
    function checkNotifications() {
        $.ajax({
            url: '<?php echo BASE_URL; ?>/api/get_notifications.php',
            method: 'GET',
            dataType: 'json',
            success: function(res) {
                if(res.success) {
                    let count = res.unread_count;
                    let notifList = res.notifications;

                    if(count > 0) {
                        $('#notif-count').text(count).show();
                    } else {
                        $('#notif-count').hide();
                    }

                    if(notifList.length > 0) {
                        $('#notif-list').empty();
                        notifList.forEach(function(n) {
                            let text = n.actor_name + ' ' + n.type;
                            $('#notif-list').append('<li><a class="dropdown-item" href="#">' + text + '</a></li>');
                        });
                    }
                }
            }
        });
    }

    // Check notifications every 10 seconds
    setInterval(checkNotifications, 10000);
    // Initial check
    setTimeout(checkNotifications, 1000);
</script>
<?php endif; ?>

</body>
</html>