const params = new URLSearchParams(window.location.search);
const userName = params.get('name') || 'User';
const userEmail = params.get('email') || '';
const userRole = ['teacher', 'student'].includes(params.get('role')) ? params.get('role') : 'user';
const backLink = document.querySelector('.back-button');
const initials = userName.split(/\s+/).filter(Boolean).slice(0, 2).map((part) => part[0]).join('').toUpperCase() || 'U';
const messageList = document.getElementById('chatMessages');
const messageInput = document.getElementById('chatInput');
const chatStatus = document.getElementById('chatStatus');
const sendButton = document.querySelector('.send-button');
let messages = [];
let isSending = false;
let isLoading = false;

if (backLink) {
    const returnTo = params.get('returnTo');
    const returnTaskId = params.get('taskId');
    const returnPath = returnTo === 'task' && returnTaskId
        ? `/admin-tasks?taskId=${encodeURIComponent(returnTaskId)}`
        : '/admin-users';

    backLink.href = returnPath;
    backLink.addEventListener('click', (event) => {
        event.preventDefault();
        try {
            sessionStorage.setItem('utosapp.admin.skip-user-detail-restore', '1');
            sessionStorage.removeItem('utosapp.admin.return-user-detail');
        } catch (error) {
            console.error('Unable to clear cached user detail when returning to the admin page:', error);
        }
        window.location.href = returnPath;
    });
}

document.getElementById('recipientName').textContent = userName;
document.getElementById('recipientRole').textContent = userRole[0].toUpperCase() + userRole.slice(1);
document.getElementById('recipientEmail').textContent = userEmail || 'No email address';
document.getElementById('recipientAvatar').textContent = initials;
document.getElementById('conversationName').textContent = userName;

function showChatStatus(message = '') {
    chatStatus.textContent = message;
    chatStatus.hidden = !message;
}

function getConversationUrl() {
    return `/api/messages/admin/${encodeURIComponent(userRole)}/${encodeURIComponent(userEmail)}`;
}

async function postMessage(text, welcome = false) {
    const response = await fetch(getConversationUrl(), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ text, welcome })
    });
    const result = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(result.message || 'Could not save message to Realtime Database.');
    return result.message;
}

function createMessageElement(message) {
    const sent = message.sender === 'admin';
    const row = document.createElement('article');
    row.className = `message-row ${sent ? 'sent' : 'received'}`;

    const avatar = document.createElement('span');
    avatar.className = 'message-avatar';
    avatar.textContent = sent ? 'A' : initials;
    avatar.setAttribute('aria-hidden', 'true');

    const content = document.createElement('div');
    content.className = 'message-content';

    const bubble = document.createElement('div');
    bubble.className = 'message-bubble';

    const text = document.createElement('p');
    text.textContent = message.text;
    bubble.append(text);

    const time = document.createElement('time');
    const createdAt = new Date(message.createdAt);
    time.className = 'message-time';
    time.dateTime = Number.isNaN(createdAt.getTime()) ? '' : createdAt.toISOString();
    time.textContent = Number.isNaN(createdAt.getTime())
        ? ''
        : createdAt.toLocaleString(undefined, { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' });

    content.append(bubble, time);
    row.append(avatar, content);
    return row;
}

function isAutoWelcomeMessage(message) {
    if (!message || typeof message !== 'object') return false;
    const text = String(message.text || '').trim();
    return message.welcome === true || /hello\s+.*\s+welcome!/i.test(text);
}

function renderMessages() {
    const visibleMessages = messages.filter((message) => !isAutoWelcomeMessage(message));

    const renderSignature = visibleMessages
        .map((message) => `${message.sender || 'unknown'}|${message.createdAt || 'missing'}|${String(message.text || '').trim()}`)
        .join('||');

    if (messageList.dataset.renderSignature === renderSignature && messageList.children.length === visibleMessages.length) {
        return;
    }

    messageList.dataset.renderSignature = renderSignature;
    messageList.replaceChildren(...visibleMessages.map(createMessageElement));
    messageList.scrollTop = messageList.scrollHeight;
}

async function loadMessages() {
    if (isLoading || !userEmail || !['teacher', 'student'].includes(userRole)) return;
    isLoading = true;
    try {
        const response = await fetch(getConversationUrl());
        const result = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(result.message || 'Could not load messages from Realtime Database.');
        messages = Array.isArray(result.messages) ? result.messages : [];

        showChatStatus();
        renderMessages();
    } catch (error) {
        showChatStatus(error.message);
    } finally {
        isLoading = false;
    }
}

document.getElementById('chatForm').addEventListener('submit', (event) => {
    event.preventDefault();
    const text = messageInput.value.trim();
    if (!text || isSending) return;

    isSending = true;
    sendButton.disabled = true;
    showChatStatus();
    postMessage(text)
        .then((message) => {
            messages.push(message);
            messageInput.value = '';
            renderMessages();
            messageInput.focus();
        })
        .catch((error) => showChatStatus(error.message))
        .finally(() => {
            isSending = false;
            sendButton.disabled = false;
        });
});

loadMessages();
window.setInterval(loadMessages, 5000);
messageInput.focus({ preventScroll: true });