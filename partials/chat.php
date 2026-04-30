<?php if (isset($_SESSION['user_id'])): ?>
<style>
/* Chat Interface CSS (Facebook 2026 Style) */
#chatheads-container {
    position: fixed;
    bottom: 20px;
    right: 20px;
    display: flex;
    flex-direction: column-reverse;
    gap: 10px;
    z-index: 1050;
    align-items: flex-end;
}

.chathead {
    width: 54px;
    height: 54px;
    border-radius: 50%;
    background-color: var(--bg-hover);
    border: 2px solid var(--border-color);
    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
    cursor: pointer;
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: transform 0.2s ease, border-color 0.2s ease;
}

.chathead:hover {
    transform: scale(1.05);
    border-color: var(--text-primary);
}

.chathead.active {
    border-color: var(--accent-color);
}

.chathead-badge {
    position: absolute;
    top: -2px;
    right: -2px;
    background-color: var(--danger);
    color: white;
    font-size: 0.7rem;
    font-weight: bold;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid var(--bg-primary);
}

/* Floating Chat Popup */
#chat-popup {
    position: fixed;
    bottom: 85px;
    right: 20px;
    width: 340px;
    height: 480px;
    background-color: var(--bg-secondary);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.5);
    display: none;
    flex-direction: column;
    z-index: 1040;

    transform-origin: bottom right;
    animation: popupIn 0.2s ease forwards;
}

@keyframes popupIn {
    from { opacity: 0; transform: scale(0.9); }
    to { opacity: 1; transform: scale(1); }
}

.chat-header {
    padding: 12px 16px;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
    background-color: var(--bg-secondary);
}

.chat-messages {
    flex: 1;
    overflow-y: auto;
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    background-color: var(--bg-primary);
}

.chat-message {
    max-width: 75%;
    padding: 8px 12px;
    border-radius: 18px;
    font-size: 0.9rem;
    word-break: break-word;
}

.chat-message.me {
    align-self: flex-end;
    background-color: var(--accent-color);
    color: white;
    border-bottom-right-radius: 4px;
}

.chat-message.them {
    align-self: flex-start;
    background-color: var(--bg-hover);
    color: var(--text-primary);
    border-bottom-left-radius: 4px;
}

.chat-input-area {
    padding: 12px;
    border-top: 1px solid var(--border-color);
    background-color: var(--bg-secondary);
    display: flex;
    gap: 8px;
    align-items: center;
}

.chat-input {
    flex: 1;
    background-color: var(--bg-hover) !important;
    border: none;
    border-radius: 20px;
    padding: 8px 16px;
    color: var(--text-primary);
    font-size: 0.9rem;
}

.chat-input:focus {
    outline: none;
}

.chat-action-btn {
    background: transparent;
    border: none;
    color: var(--accent-color);
    font-size: 1.2rem;
    cursor: pointer;
    transition: transform 0.1s ease;
}

.chat-action-btn:hover {
    transform: scale(1.1);
}

.chat-action-btn.disabled {
    color: var(--text-secondary);
    cursor: not-allowed;
}

/* Media in chat */
.chat-media-img {
    max-width: 100%;
    border-radius: 12px;
    margin-top: 5px;
}

.chat-file {
    display: flex;
    align-items: center;
    gap: 8px;
    background: rgba(0,0,0,0.2);
    padding: 6px 10px;
    border-radius: 8px;
    margin-top: 5px;
    text-decoration: none;
    color: inherit;
}

/* Sidebar List for selecting who to chat with */
#chat-sidebar {
    height: 100%;
    display: flex;
    flex-direction: column;
}

.chat-contact {
    display: flex;
    align-items: center;
    padding: 10px 16px;
    cursor: pointer;
    transition: background-color 0.2s ease;
}

.chat-contact:hover {
    background-color: var(--bg-hover);
}

.chat-contact.unread {
    background-color: rgba(16, 185, 129, 0.1);
}
</style>

<!-- HTML Structure -->
<div id="chatheads-container">
    <!-- Main trigger button -->
    <div class="chathead" id="main-chat-trigger" style="background-color: var(--accent-color); border-color: var(--accent-color);">
        <i class="fa-brands fa-facebook-messenger text-white fs-3"></i>
    </div>
    <!-- Dynamic chatheads will be prepended here -->
</div>

<div id="chat-popup" style="overflow: visible;">
    <!-- View 1: Contacts List -->
    <div id="chat-view-list" class="h-100 d-flex flex-column" style="overflow: visible;">
        <div class="chat-header">
            <h5 class="mb-0 fw-bold">Chats</h5>
            <div>
                <i class="fa-solid fa-ellipsis text-muted me-2 cursor-pointer"></i>
                <i class="fa-solid fa-up-right-and-down-left-from-center text-muted cursor-pointer" onclick="closeChatPopup()"></i>
            </div>
        </div>
        <div class="p-2 border-bottom border-secondary position-relative" style="overflow: visible;">
            <input type="text" id="chat-search-input" class="form-control bg-dark border-0 rounded-pill" placeholder="Search Messenger" style="font-size: 0.85rem; color: var(--text-primary);">
            <div id="chat-search-results" class="position-absolute w-100 bg-secondary rounded shadow-lg d-none" style="top: 100%; left: 0; z-index: 9999; max-height: 250px; overflow-y: auto;">
                <!-- Search results injected here -->
            </div>
        </div>
        <div class="chat-messages" id="contacts-list" style="padding: 0;">
            <!-- Contacts loaded via JS -->
            <div class="text-center p-4 text-muted">Loading chats...</div>
        </div>
    </div>

    <!-- View 2: Active Conversation -->
    <div id="chat-view-convo" class="h-100 d-none flex-column">
        <div class="chat-header">
            <div class="d-flex align-items-center gap-2 cursor-pointer" onclick="showChatList()">
                <i class="fa-solid fa-arrow-left text-primary" style="color: var(--accent-color) !important;"></i>
                <div class="avatar-placeholder" id="convo-avatar" style="width: 32px; height: 32px; font-size: 0.9rem;">?</div>
                <div>
                    <div class="fw-bold lh-1" id="convo-name">User</div>
                    <div class="text-muted" style="font-size: 0.7rem;">Active now</div>
                </div>
            </div>
            <i class="fa-solid fa-xmark fs-4 text-muted cursor-pointer" onclick="closeChatPopup()"></i>
        </div>

        <div class="chat-messages" id="convo-messages">
            <!-- Messages loaded via JS -->
        </div>

        <!-- Attachment Preview Container -->
        <div id="chat-attachment-preview-container" class="d-none bg-dark p-2 border-top border-secondary position-relative">
            <div class="position-absolute top-0 end-0 m-1 z-1">
                <button type="button" class="btn btn-dark btn-sm rounded-circle opacity-75 hover-opacity-100" onclick="clearChatAttachment()">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <img id="chat-image-preview" src="" class="img-fluid rounded border border-secondary d-none" style="max-height: 120px; object-fit: contain; width: auto; max-width: 100%;">
            <video id="chat-video-preview" src="" class="img-fluid rounded border border-secondary d-none" style="max-height: 120px; object-fit: contain; width: auto; max-width: 100%;" controls></video>
            <div id="chat-file-preview" class="d-none align-items-center gap-2 px-2 py-1">
                <i class="fa-solid fa-file text-muted fs-5"></i>
                <span id="chat-attachment-name" class="text-truncate small flex-grow-1 text-white"></span>
            </div>
        </div>

        <form id="chat-form" class="chat-input-area m-0">
            <input type="hidden" id="chat-receiver-id" value="">
            <input type="hidden" id="chat-group-id" value="">
            <input type="hidden" id="chat-type" value="user">

            <label for="chat-file-upload" class="chat-action-btn mb-0">
                <i class="fa-solid fa-circle-plus"></i>
            </label>
            <input type="file" id="chat-file-upload" class="d-none">

            <label for="chat-image-upload" class="chat-action-btn mb-0">
                <i class="fa-regular fa-image"></i>
            </label>
            <input type="file" id="chat-image-upload" class="d-none" accept="image/*,video/*">

            <input type="text" id="chat-message-input" class="chat-input" placeholder="Aa" autocomplete="off">
            <button type="submit" class="chat-action-btn" id="chat-send-btn">
                <i class="fa-solid fa-paper-plane"></i>
            </button>
        </form>
    </div>
</div>

<script>
    let activeChatId = null;
    let pollInterval = null;
    let notifPollInterval = null;

    // Start a global notification polling to update chat badge and normal notifications
    function startGlobalPolling() {
        if (!notifPollInterval) {
            notifPollInterval = setInterval(async () => {
                // Poll unread chat messages count
                try {
                    const res = await fetch('api_chat.php?action=get_unread_count');
                    const data = await res.json();
                    const badge = document.getElementById('chat-global-badge');
                    if (data.unread && data.unread > 0) {
                        badge.textContent = data.unread;
                        badge.classList.remove('d-none');
                    } else {
                        badge.classList.add('d-none');
                    }
                } catch(e) {}
            }, 3000);
        }
    }

    // Start global polling immediately
    startGlobalPolling();

    let selectedFile = null;

    const chatPopup = document.getElementById('chat-popup');
    const mainTrigger = document.getElementById('main-chat-trigger');
    const viewList = document.getElementById('chat-view-list');
    const viewConvo = document.getElementById('chat-view-convo');
    const contactsList = document.getElementById('contacts-list');
    const convoMessages = document.getElementById('convo-messages');

    // Chat Search Logic
    const chatSearchInput = document.getElementById('chat-search-input');
    const chatSearchResults = document.getElementById('chat-search-results');
    let searchTimeout = null;

    chatSearchInput.addEventListener('input', (e) => {
        clearTimeout(searchTimeout);
        const query = e.target.value.trim();

        if (query.length === 0) {
            chatSearchResults.classList.add('d-none');
            return;
        }

        searchTimeout = setTimeout(async () => {
            try {
                const res = await fetch(`api_chat.php?action=search_users&query=${encodeURIComponent(query)}`);
                const users = await res.json();

                chatSearchResults.innerHTML = '';
                chatSearchResults.classList.remove('d-none'); // explicitly show right away
                if (users.length > 0) {
                    let hasOther = false;

                    users.forEach(u => {
                        if (u.category === 'other' && !hasOther) {
                            hasOther = true;
                            const label = document.createElement('div');
                            label.className = 'px-2 py-1 text-muted fw-bold border-bottom border-dark';
                            label.style.fontSize = '0.75rem';
                            label.innerText = 'Other people';
                            chatSearchResults.appendChild(label);
                        }

                        const div = document.createElement('div');
                        div.className = 'p-2 border-bottom border-dark cursor-pointer bg-hover d-flex align-items-center gap-2';
                        div.innerHTML = `
                            <div class="avatar-placeholder rounded-circle" style="width: 32px; height: 32px; font-size: 0.8rem; flex-shrink: 0; ${u.profile_pic ? 'background-image: url('+u.profile_pic+'); background-size: cover;' : ''}">
                                ${u.profile_pic ? '' : u.initial}
                            </div>
                            <div class="fw-bold text-white fs-6">${u.name}</div>
                        `;
                        div.onclick = () => {
                            chatSearchResults.classList.add('d-none');
                            chatSearchInput.value = '';
                            openConversation(u.id, u.name, u.initial, u.type);
                        };
                        chatSearchResults.appendChild(div);
                    });
                    chatSearchResults.classList.remove('d-none');
                } else {
                    chatSearchResults.innerHTML = '<div class="p-3 text-center text-muted">No users found</div>';
                    chatSearchResults.classList.remove('d-none');
                }
            } catch (e) {
                console.error("Search failed", e);
            }
        }, 300);
    });

    // Close search results if clicked outside
    document.addEventListener('click', (e) => {
        if (!chatSearchResults.contains(e.target) && e.target !== chatSearchInput) {
            chatSearchResults.classList.add('d-none');
        }
    });

    // Toggle Chat Popup
    mainTrigger.addEventListener('click', () => {
        if (chatPopup.style.display === 'flex') {
            closeChatPopup();
        } else {
            chatPopup.style.display = 'flex';
            showChatList();
        }
    });

    function closeChatPopup() {
        chatPopup.style.display = 'none';
        activeChatId = null;
        stopPolling();
    }

    function showChatList() {
        viewConvo.classList.add('d-none');
        viewList.classList.remove('d-none');
        viewList.classList.add('d-flex');
        activeChatId = null;
        loadConversations();
        startPolling('list');
    }

    function openConversation(id, name, initial, type) {
        activeChatId = id;
        viewList.classList.remove('d-flex');
        viewList.classList.add('d-none');
        viewConvo.classList.remove('d-none');
        viewConvo.classList.add('d-flex');

        document.getElementById('convo-name').innerText = name;
        document.getElementById('convo-avatar').innerText = initial;

        document.getElementById('chat-type').value = type;
        if (type === 'user') {
            document.getElementById('chat-receiver-id').value = id;
            document.getElementById('chat-group-id').value = '';
        } else {
            document.getElementById('chat-receiver-id').value = '';
            document.getElementById('chat-group-id').value = id;
        }

        convoMessages.innerHTML = '<div class="text-center p-4 text-muted">Loading...</div>';

        loadMessages();
        startPolling('convo');

        // Mark chathead as read visually
        const head = document.getElementById(`chathead-${id}`);
        if(head) {
            const badge = head.querySelector('.chathead-badge');
            if(badge) badge.remove();
        }
    }

    async function unsendMessage(messageId) {
        if(!confirm('Unsend this message for everyone?')) return;

        const formData = new FormData();
        formData.append('message_id', messageId);

        await fetch('api_chat.php?action=unsend_message', {
            method: 'POST',
            body: formData
        });

        loadMessages(true); // reload messages silently
    }

    // Polling Logic
    function startPolling(mode) {
        stopPolling();
        pollInterval = setInterval(() => {
            if (mode === 'list') {
                loadConversations(true); // silent load
            } else if (mode === 'convo') {
                loadMessages(true);
            }
        }, 3000);
    }

    function stopPolling() {
        if (pollInterval) clearInterval(pollInterval);
    }

    // AJAX Loaders
    async function loadConversations(silent = false) {
        try {
            const response = await fetch('api_chat.php?action=get_conversations');
            const data = await response.json();

            contactsList.innerHTML = '';

            // Maintain dynamic chatheads in container (max 3 recent)
            const container = document.getElementById('chatheads-container');
            // Remove existing dynamic heads
            container.querySelectorAll('.chathead-dynamic').forEach(el => el.remove());

            let unreadCount = 0;

            data.forEach((c, index) => {
                const initial = c.name ? c.name.charAt(0).toUpperCase() : 'G';
                const isUnread = (c.last_read_status == "0" || c.last_read_status === 0);

                // Build List Item
                const div = document.createElement('div');
                div.className = `chat-contact ${isUnread ? 'unread' : ''}`;
                div.onclick = () => openConversation(c.id, c.name, initial, c.type);

                div.innerHTML = `
                    <div class="avatar-placeholder me-3" style="width: 48px; height: 48px; flex-shrink: 0; background-color: ${c.type === 'group' ? 'var(--bg-hover)' : 'var(--accent-color)'}; color: ${c.type === 'group' ? 'var(--text-primary)' : 'var(--bg-primary)'};">${initial}</div>
                    <div class="flex-grow-1 overflow-hidden">
                        <div class="fw-bold ${isUnread ? 'text-white' : ''}">${c.name}</div>
                        <div class="text-truncate text-muted" style="font-size: 0.8rem; ${isUnread ? 'color: var(--text-primary) !important; font-weight: bold;' : ''}">
                            ${c.last_message || 'Start a conversation'}
                        </div>
                    </div>
                    ${isUnread ? '<div class="rounded-circle ms-2" style="width: 10px; height: 10px; background-color: var(--accent-color); flex-shrink: 0;"></div>' : ''}
                `;
                contactsList.appendChild(div);

                // Build Chatheads for top 3
                if (index < 3) {
                    const head = document.createElement('div');
                    head.className = 'chathead chathead-dynamic';
                    head.id = `chathead-${c.id}`;
                    head.onclick = () => {
                        if(chatPopup.style.display !== 'flex') chatPopup.style.display = 'flex';
                        openConversation(c.id, c.name, initial, c.type);
                    };
                    head.innerHTML = `
                        <div class="fw-bold fs-4">${initial}</div>
                        ${isUnread ? '<div class="chathead-badge">1</div>' : ''}
                    `;
                    // Insert before the main trigger
                    container.insertBefore(head, mainTrigger);
                }

                if(isUnread) unreadCount++;
            });

            if (data.length === 0) {
                contactsList.innerHTML = '<div class="text-center p-4 text-muted">No conversations yet.<br>Search or go to a profile to start one!</div>';
            }

        } catch (e) {
            console.error("Error loading conversations", e);
        }
    }

    async function loadMessages(silent = false) {
        if (!activeChatId) return;
        const type = document.getElementById('chat-type').value;
        try {
            const response = await fetch(`api_chat.php?action=get_messages&chat_type=${type}&target_id=${activeChatId}`);
            const data = await response.json();

            // Only update DOM if we have new data or initial load
            // A simple length check or tracking last ID is better, doing full re-render for MVP
            const wasAtBottom = convoMessages.scrollHeight - convoMessages.scrollTop <= convoMessages.clientHeight + 10;

            convoMessages.innerHTML = '';

            data.forEach(m => {
                const wrapper = document.createElement('div');
                wrapper.className = `d-flex flex-column mb-1 ${m.is_mine ? 'align-items-end' : 'align-items-start'}`;

                if (type === 'group' && !m.is_mine) {
                    const nameDiv = document.createElement('div');
                    nameDiv.className = 'text-muted small ms-2 mb-1';
                    nameDiv.innerText = m.sender_name;
                    wrapper.appendChild(nameDiv);
                }

                const div = document.createElement('div');
                div.className = `chat-message position-relative ${m.is_mine ? 'me' : 'them'}`;

                let contentHTML = '';
                if (m.message) {
                    // basic sanitize
                    contentHTML += `<div>${m.message.replace(/</g, "&lt;").replace(/>/g, "&gt;")}</div>`;
                }

                if (m.has_media) {
                    if (m.media_type.startsWith('image/')) {
                        contentHTML += `<img src="${m.media_url}" class="chat-media-img">`;
                    } else if (m.media_type.startsWith('video/')) {
                        contentHTML += `<video src="${m.media_url}" class="chat-media-img" controls></video>`;
                    } else {
                        contentHTML += `
                            <a href="${m.media_url}" class="chat-file" target="_blank" download>
                                <i class="fa-solid fa-file"></i> ${m.media_name}
                            </a>
                        `;
                    }
                }

                div.innerHTML = contentHTML;

                if (m.is_mine && !m.is_deleted) {
                    div.innerHTML += `
                        <div class="position-absolute dropdown" style="left: -20px; top: 50%; transform: translateY(-50%); display: none;" onmouseover="this.style.display='block'" id="opts-${m.id}">
                            <i class="fa-solid fa-ellipsis-vertical text-muted cursor-pointer" data-bs-toggle="dropdown"></i>
                            <ul class="dropdown-menu dropdown-menu-dark p-1 shadow">
                                <li><a class="dropdown-item text-danger" href="#" onclick="unsendMessage(${m.id})">Unsend</a></li>
                            </ul>
                        </div>
                    `;
                    div.onmouseover = () => document.getElementById(`opts-${m.id}`).style.display = 'block';
                    div.onmouseout = () => document.getElementById(`opts-${m.id}`).style.display = 'none';
                }

                wrapper.appendChild(div);
                convoMessages.appendChild(wrapper);
            });

            if (data.length === 0) {
                convoMessages.innerHTML = '<div class="text-center mt-auto mb-auto text-muted"><i class="fa-brands fa-facebook-messenger fs-1 mb-2"></i><br>Say Hi!</div>';
            }

            if (!silent || wasAtBottom) {
                convoMessages.scrollTop = convoMessages.scrollHeight;
            }
        } catch (e) {
            console.error("Error loading messages", e);
        }
    }

    // Attachments Handling
    const fileUpload = document.getElementById('chat-file-upload');
    const imgUpload = document.getElementById('chat-image-upload');
    const previewContainer = document.getElementById('chat-attachment-preview-container');
    const imgPreview = document.getElementById('chat-image-preview');
    const vidPreview = document.getElementById('chat-video-preview');
    const filePreview = document.getElementById('chat-file-preview');
    const previewName = document.getElementById('chat-attachment-name');

    function handleFileSelect(e) {
        imgPreview.classList.add('d-none');
        vidPreview.classList.add('d-none');
        filePreview.classList.add('d-none');
        previewContainer.classList.add('d-none');

        if (this.files && this.files[0]) {
            selectedFile = this.files[0];
            previewContainer.classList.remove('d-none');

            if (selectedFile.type.startsWith('image/')) {
                imgPreview.src = URL.createObjectURL(selectedFile);
                imgPreview.classList.remove('d-none');
            } else if (selectedFile.type.startsWith('video/')) {
                vidPreview.src = URL.createObjectURL(selectedFile);
                vidPreview.classList.remove('d-none');
            } else {
                previewName.innerText = selectedFile.name;
                filePreview.classList.remove('d-none');
                filePreview.classList.add('d-flex');
            }
            document.getElementById('chat-message-input').focus();
        }
    }

    fileUpload.addEventListener('change', handleFileSelect);
    imgUpload.addEventListener('change', handleFileSelect);

    window.clearChatAttachment = function() {
        selectedFile = null;
        fileUpload.value = '';
        imgUpload.value = '';
        previewContainer.classList.add('d-none');
    }

    // Send Message Form
    document.getElementById('chat-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const input = document.getElementById('chat-message-input');
        const receiverId = document.getElementById('chat-receiver-id').value;
        const chatGroupId = document.getElementById('chat-group-id').value;
        const msgText = input.value.trim();

        if (!msgText && !selectedFile) return;

        const btn = document.getElementById('chat-send-btn');
        btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i>';

        const formData = new FormData();
        if (receiverId) formData.append('receiver_id', receiverId);
        if (chatGroupId) formData.append('chat_group_id', chatGroupId);
        formData.append('message', msgText);

        if (selectedFile) {
            formData.append('media', selectedFile);
        }

        try {
            const res = await fetch('api_chat.php?action=send_message', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();

            if (data.status === 'success') {
                input.value = '';
                clearChatAttachment();
                loadMessages(); // Force reload immediate
            }
        } catch (e) {
            console.error("Failed to send", e);
        } finally {
            btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i>';
            input.focus();
        }
    });
</script>
<?php endif; ?>