const taskList = document.getElementById('taskList');
const allTasksTotalCount = document.getElementById('allTasksTotalCount');
let taskCards = [];
const searchInput = document.getElementById('taskSearch');
const teacherSearchInput = document.getElementById('teacherSearch');
const statusFilter = document.getElementById('statusFilter');
const emptyState = document.getElementById('emptyTasks');
const taskShell = document.querySelector('.admin-task-shell');
const taskPageTitle = document.getElementById('taskPageTitle');
const taskPageSubtitle = document.getElementById('taskPageSubtitle');
const taskDetailView = document.getElementById('taskDetailView');
const allTasksBack = document.getElementById('allTasksBack');
const taskStatusTabs = document.querySelector('.task-status-tabs');
const statusTabs = [...document.querySelectorAll('[data-status-tab]')];
const summaryCards = [...document.querySelectorAll('[data-summary-status]')];
const toast = document.getElementById('toast');
const taskDeleteConfirm = document.getElementById('taskDeleteConfirm');
const taskDeleteDescription = document.getElementById('taskDeleteDescription');
const cancelTaskDelete = document.getElementById('cancelTaskDelete');
const confirmTaskDelete = document.getElementById('confirmTaskDelete');
let toastTimer;
let transitionTimer;
let tasksLoaded = false;
const taskSnapshotStorageKey = 'utosapp.admin.tasks.snapshot.v1';
let taskRecordsById = new Map();
let detailReturnToAllTasks = false;
let detailReturnFocus = null;
let pendingTaskDeleteId = null;
const bottomNav = document.querySelector('.bottom-nav');
const bottomNavAnchor = bottomNav?.getBoundingClientRect().bottom ?? window.innerHeight;
let bottomNavSyncUntil = 0;
let bottomNavSyncFrame = 0;

function syncBottomNavToVisualViewport() {
    if (!bottomNav || !window.visualViewport || window.innerWidth > 650) return;

    const keyboardOpen = document.activeElement instanceof HTMLElement
        && document.activeElement.matches('input, textarea, select');
    const currentBottom = bottomNav.getBoundingClientRect().bottom;
    const currentOffset = Number.parseFloat(bottomNav.style.bottom) || 0;
    const drift = currentBottom - bottomNavAnchor;
    const offset = !keyboardOpen && Math.abs(drift) <= 40 ? currentOffset + drift : 0;
    bottomNav.style.setProperty('bottom', `${offset}px`, 'important');
}

function trackBottomNavDuringTaskReveal() {
    bottomNavSyncUntil = performance.now() + 1000;
    if (bottomNavSyncFrame) return;

    const updatePosition = () => {
        syncBottomNavToVisualViewport();
        if (performance.now() < bottomNavSyncUntil) {
            bottomNavSyncFrame = window.requestAnimationFrame(updatePosition);
        } else {
            bottomNavSyncFrame = 0;
        }
    };
    bottomNavSyncFrame = window.requestAnimationFrame(updatePosition);
}

window.visualViewport?.addEventListener('resize', syncBottomNavToVisualViewport);
window.addEventListener('resize', syncBottomNavToVisualViewport);
new MutationObserver(trackBottomNavDuringTaskReveal).observe(taskList, { childList: true });
syncBottomNavToVisualViewport();

taskList.replaceChildren();

function showToast(message) {
    toast.textContent = message;
    toast.classList.add('show');
    window.clearTimeout(toastTimer);
    toastTimer = window.setTimeout(() => toast.classList.remove('show'), 2200);
}

function closeTaskDeleteConfirmation() {
    taskDeleteConfirm.hidden = true;
    pendingTaskDeleteId = null;
}

function openTaskDeleteConfirmation(taskId, task) {
    pendingTaskDeleteId = String(taskId);
    const taskTitle = String(task.title || task.task_title || task.taskName || 'this task');
    taskDeleteDescription.textContent = `Delete “${taskTitle}” permanently from the Realtime Database? This action cannot be undone.`;
    taskDeleteConfirm.hidden = false;
    cancelTaskDelete.focus();
}

async function deleteTaskFromRealtimeDatabase(taskId) {
    confirmTaskDelete.disabled = true;
    confirmTaskDelete.textContent = 'Deleting...';
    try {
        const response = await fetch(`https://utosapp-fd977-default-rtdb.firebaseio.com/tasks/${encodeURIComponent(String(taskId))}.json`, {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' }
        });
        if (!response.ok) {
            const result = await response.json().catch(() => ({}));
            throw new Error(result?.error || 'Could not delete task from the database.');
        }

        taskRecordsById.delete(String(taskId));
        closeTaskDeleteConfirmation();
        closeTaskDetails();
        showToast('Task deleted');
    } catch (error) {
        console.error('Admin task delete error:', error);
        closeTaskDeleteConfirmation();
        showToast(error.message || 'Unable to delete task.');
    } finally {
        confirmTaskDelete.disabled = false;
        confirmTaskDelete.textContent = 'Delete task';
    }
}

cancelTaskDelete.addEventListener('click', closeTaskDeleteConfirmation);
confirmTaskDelete.addEventListener('click', () => {
    if (pendingTaskDeleteId) deleteTaskFromRealtimeDatabase(pendingTaskDeleteId);
});
taskDeleteConfirm.addEventListener('click', (event) => {
    if (event.target === taskDeleteConfirm) closeTaskDeleteConfirmation();
});
document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && !taskDeleteConfirm.hidden) closeTaskDeleteConfirmation();
});

function updateTaskCounts() {
    const counts = taskCards.reduce((result, card) => {
        result.all += 1;
        result[card.dataset.status] = (result[card.dataset.status] || 0) + 1;
        return result;
    }, { all: 0 });

    if (allTasksTotalCount) allTasksTotalCount.textContent = String(counts.all || 0);
    statusTabs.forEach((tab) => {
        const count = tab.querySelector('b');
        if (count) count.textContent = counts[tab.dataset.statusTab] || 0;
    });

    const animatedSummaryCounts = [];
    summaryCards.forEach((card) => {
        const count = card.querySelector('strong');
        if (count) {
            count.textContent = counts[card.dataset.summaryStatus] || 0;
            animatedSummaryCounts.push(count);
        }
    });
    if (animatedSummaryCounts.length) window.animateCountUps?.(animatedSummaryCounts);
}

function normalizedTaskStatus(task) {
    const rawStatus = String(task.status || 'pending').toLowerCase().replace(/[\s_]+/g, '-');
    if (['completed', 'complete', 'done'].includes(rawStatus)) return 'completed';
    if (['in-progress', 'ongoing'].includes(rawStatus)) return 'in-progress';
    if (['active', 'open', 'published'].includes(rawStatus)) return 'active';
    if (rawStatus === 'overdue') return 'overdue';

    const dueValue = task.due_date || task.due || '';
    if (dueValue && !['completed', 'complete', 'done', 'cancelled', 'canceled'].includes(rawStatus)) {
        const dueAt = new Date(`${dueValue}T${task.due_time || task.dueTime || '23:59:59'}`);
        if (!Number.isNaN(dueAt.getTime()) && dueAt < new Date()) return 'overdue';
    }
    return 'pending';
}

function formatTaskDate(value) {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return String(value);
    return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
}

function formatTaskTime(value) {
    if (!value || value === 'No due time') return 'No due time';
    const normalizedValue = String(value).trim();
    const match = normalizedValue.match(/^([0-9]{1,2}):([0-9]{2})(?::[0-9]{2})?$/);
    if (!match) return normalizedValue;

    const hours = Number(match[1]);
    const minutes = match[2];
    const period = hours >= 12 ? 'PM' : 'AM';
    const twelveHour = hours % 12 || 12;

    return `${twelveHour}:${minutes} ${period}`;
}

function getTaskOverdueDays(task) {
    const dueValue = String(task.due_date || task.due || task.deadline || '').replace(/^Due\s+/i, '').trim();
    if (!dueValue || /^no due date$/i.test(dueValue)) return 0;

    const rawTime = String(task.due_time || task.dueTime || '23:59:59').trim();
    const dueTime = /^\d{1,2}:\d{2}$/.test(rawTime) ? `${rawTime}:00` : rawTime || '23:59:59';
    const deadline = new Date(`${dueValue}T${dueTime}`);

    if (Number.isNaN(deadline.getTime())) return 0;

    const diffMs = Date.now() - deadline.getTime();
    if (diffMs <= 0) return 0;

    return Math.max(1, Math.ceil(diffMs / 86400000));
}

function showTaskLoader() {
    taskList.setAttribute('aria-busy', 'true');
    const fragment = document.createDocumentFragment();
    for (let index = 0; index < 4; index += 1) {
        const card = makeTaskElement('article', 'task-card task-skeleton');
        card.setAttribute('aria-hidden', 'true');
        card.innerHTML = `
            <span class="task-thumb skeleton-block"></span>
            <div class="task-main">
                <span class="skeleton-block skeleton-line skeleton-title"></span>
                <span class="skeleton-block skeleton-line skeleton-detail"></span>
                <span class="skeleton-block skeleton-line skeleton-detail-short"></span>
            </div>
            <div class="task-teacher">
                <span class="skeleton-block skeleton-line skeleton-teacher"></span>
                <span class="skeleton-block skeleton-line skeleton-detail-short"></span>
            </div>
            <span class="skeleton-block skeleton-pill"></span>
            <time class="skeleton-block skeleton-date"></time>
            <span class="skeleton-block skeleton-more"></span>
        `;
        fragment.append(card);
    }
    taskList.replaceChildren(fragment);
}

function hideTaskLoader() {
    taskList.setAttribute('aria-busy', 'false');
}
function formatTaskAmount(task) {
    const value = task.amount ?? task.task_amount ?? task.payment_amount ?? task.price ?? task.reward;
    if (value === undefined || value === null || value === '') return 'Not specified';
    const normalizedAmount = String(value).replace(/[^\d.-]/g, '');
    if (!normalizedAmount) return String(value);
    const amount = Number(normalizedAmount);
    return Number.isFinite(amount)
        ? `₱${new Intl.NumberFormat('en-PH', { maximumFractionDigits: 2 }).format(amount)}`
        : String(value);
}

function makeTaskElement(tagName, className, text) {
    const element = document.createElement(tagName);
    if (className) element.className = className;
    if (text !== undefined) element.textContent = text;
    return element;
}

function createTaskCard(id, task, index) {
    const status = normalizedTaskStatus(task);
    const title = String(task.title || task.task_title || task.taskName || 'Untitled task');
    const teacherName = String(task.teacher_name || task.createdBy || task.teacher || 'Teacher');
    const teacherEmail = String(task.teacher_email || '');
    const room = String(task.room || task.class_name || task.className || 'Room not set');
    const taskType = String(task.category || task.task_type || task.type || 'Task');
    const createdAt = task.created_at || '';
    const createdDate = formatTaskDate(createdAt);
    const thumbVariants = ['blue', 'violet', 'green', 'orange', 'red', 'purple'];

    const card = makeTaskElement('article', 'task-card');
    card.style.setProperty('--enter-delay', `${Math.min(index, 10) * 38}ms`);
    card.dataset.taskId = id;
    card.dataset.status = status;
    card.tabIndex = 0;
    card.setAttribute('aria-label', `View details for ${title}`);
    card.dataset.teacher = `${teacherName} ${teacherEmail}`.toLowerCase();
    card.dataset.search = `${title} ${task.description || ''} ${room} ${taskType} ${teacherName} ${teacherEmail} ${status}`.toLowerCase();

    const thumbnail = makeTaskElement('span', `task-thumb ${thumbVariants[index % thumbVariants.length]}`);
    thumbnail.setAttribute('aria-hidden', 'true');
    thumbnail.innerHTML = '<svg viewBox="0 0 24 24"><path d="M6 3h12v18H6z"></path><path d="M9 8h6M9 12h6M9 16h4"></path></svg>';
    card.append(thumbnail);

    const main = makeTaskElement('div', 'task-main');
    main.append(makeTaskElement('h3', '', title));
    main.append(makeTaskElement('small', '', `Created by: ${teacherName}`));
    main.append(makeTaskElement('small', 'task-amount', `Amount: ${formatTaskAmount(task)}`));
    card.append(main);

    const teacher = makeTaskElement('div', 'task-teacher');
    teacher.append(makeTaskElement('strong', '', teacherName));
    teacher.append(makeTaskElement('small', '', teacherEmail || 'Teacher'));
    card.append(teacher);

    card.append(makeTaskElement('em', `status ${status}`, status === 'in-progress' ? 'In Progress' : status.charAt(0).toUpperCase() + status.slice(1)));

    const time = makeTaskElement('time', '', createdDate ? `Created ${createdDate}` : 'Date unavailable');
    if (createdAt) time.dateTime = createdAt;
    card.append(time);

    const moreButton = makeTaskElement('button', 'more-button');
    moreButton.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 18 6-6-6-6"></path></svg>';
    moreButton.type = 'button';
    moreButton.setAttribute('aria-label', `View details for ${title}`);
    card.append(moreButton);
    return card;
}

function escapeTaskHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (character) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;',
    })[character]);
}

function safeTaskResourceUrl(value) {
    const source = String(value || '').trim();
    if (!source) return '';
    try {
        const url = new URL(source, window.location.href);
        return ['http:', 'https:', 'data:', 'blob:'].includes(url.protocol) ? source : '';
    } catch (error) {
        return '';
    }
}

function taskAttachmentList(task) {
    return (Array.isArray(task.attachments) ? task.attachments : [task.attachment]).filter(Boolean);
}

function formatAttachmentSize(value) {
    const size = Number(value);
    if (Number.isFinite(size) && size > 0) return `${Math.max(1, Math.round(size / 1024))} KB`;
    return String(value || 'Task file');
}

function renderTaskDetails(taskId) {
    const task = taskRecordsById.get(String(taskId));
    if (!task) return;

    const title = String(task.title || task.task_title || task.taskName || 'Untitled task');
    const teacherName = String(task.teacher_name || task.createdBy || task.teacher || 'Teacher');
    const teacherEmail = String(task.teacher_email || task.teacherEmail || '');
    const room = String(task.room || task.class_name || task.className || 'Room not set');
    const description = String(task.description || task.details || 'No description provided');
    const dueValue = String(task.due_date || task.due || task.deadline || '').replace(/^Due\s+/i, '').trim();
    const dueDate = dueValue && !/^no due date$/i.test(dueValue) ? formatTaskDate(dueValue) : 'No due date';
    const dueTime = formatTaskTime(task.due_time || task.dueTime || 'No due time');
    const createdValue = task.created_at || task.createdAt || '';
    const createdDate = createdValue ? formatTaskDate(createdValue) : 'Date unavailable';
    const status = normalizedTaskStatus(task);
    const statusLabel = status === 'in-progress' ? 'In Progress' : status.charAt(0).toUpperCase() + status.slice(1);
    const overdueDays = status === 'overdue' ? getTaskOverdueDays(task) : 0;
    const overdueNote = overdueDays > 0 ? `${overdueDays} day${overdueDays === 1 ? '' : 's'} overdue` : '';
    const nameParts = teacherName.split(/\s+/).filter(Boolean);
    const initials = nameParts.length > 1
        ? `${nameParts[0][0]}${nameParts[nameParts.length - 1][0]}`
        : (nameParts[0] || '?').slice(0, 2);
    const attachments = taskAttachmentList(task);
    const attachmentMarkup = attachments.length
        ? attachments.map((attachment, index) => {
            const item = typeof attachment === 'string' ? { name: attachment.split(/[\\/]/).pop(), url: attachment } : attachment;
            const fileName = String(item.name || `Attachment ${index + 1}`);
            const source = safeTaskResourceUrl(item.data || item.url || item.path);
            const isImage = Boolean(source) && /\.(png|jpe?g|gif|webp|bmp|svg|avif)(\?.*)?$/i.test(source) || /image\//i.test(String(item.type || ''));
            const addedValue = item.uploaded_at || item.uploadedAt || item.created_at || item.createdAt;
            const addedLabel = addedValue ? `Added ${formatTaskDate(addedValue)}` : '';
            const download = source
                ? `<a class="task-attachment-download" href="${escapeTaskHtml(source)}" download="${escapeTaskHtml(fileName)}" target="_blank" rel="noopener noreferrer" aria-label="Download ${escapeTaskHtml(fileName)}"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12M7 10l5 5 5-5M5 20h14"></path></svg></a>`
                : '';
            const clickClass = isImage ? 'task-detail-attachment-image' : '';
            const clickData = isImage ? `data-image-src="${escapeTaskHtml(source)}"` : '';
            return `<article class="task-detail-attachment ${clickClass}" ${clickData} tabindex="${isImage ? '0' : '-1'}"><span class="task-attachment-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="3" width="16" height="18" rx="2"></rect><path d="M8 8h8M8 12h8M8 16h5"></path></svg></span><div class="task-attachment-copy"><strong>${escapeTaskHtml(fileName)}</strong><small>${escapeTaskHtml(formatAttachmentSize(item.size))}${addedLabel ? ` · ${escapeTaskHtml(addedLabel)}` : ''}</small></div>${download}</article>`;
        }).join('')
        : '<p class="task-detail-empty">No attachments</p>';

    const titleNeedsTruncation = title.length > 34;
    const titleMarkup = titleNeedsTruncation
        ? `${escapeTaskHtml(title.slice(0, 34))}... <button type="button" class="task-detail-title-more" aria-expanded="false">see more</button>`
        : escapeTaskHtml(title);
    taskDetailView.innerHTML = `
        <article class="task-detail-card">
            <header class="task-detail-heading">
                <span class="task-detail-task-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h9l3 3v15H6z"></path><path d="M15 3v4h4M9 12h6M9 16h4"></path></svg></span>
                <div class="task-detail-title"><h2 id="taskDetailTitle" class="${titleNeedsTruncation ? 'has-long-title' : ''}" data-full-title="${escapeTaskHtml(title)}">${titleMarkup}</h2><em class="status ${status}">${escapeTaskHtml(statusLabel)}</em></div>
                <div class="task-detail-menu-wrap">
                    <button type="button" class="task-detail-more" aria-label="More task actions" aria-expanded="false" aria-controls="taskDetailMenu-${escapeTaskHtml(String(taskId))}">⋮</button>
                    <div class="task-detail-menu" id="taskDetailMenu-${escapeTaskHtml(String(taskId))}" role="menu" hidden>
                        <button type="button" class="task-detail-menu-item" data-task-action="attachments" data-task-id="${escapeTaskHtml(String(taskId))}" role="menuitem"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21.4 11.1 12.7 19.8a5 5 0 1 1-7.1-7.1L14.3 4a3.5 3.5 0 1 1 5 5L8.6 18.5a2 2 0 1 1-2.8-2.8l8.9-8.9"></path></svg>View Attachments</button>
                        <button type="button" class="task-detail-menu-item" data-task-action="message" data-task-id="${escapeTaskHtml(String(taskId))}" role="menuitem"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3h11A2.5 2.5 0 0 1 20 5.5v8A2.5 2.5 0 0 1 17.5 16H9l-5 5v-5.5A2.5 2.5 0 0 1 4 13.5v-8Z"></path></svg>Message teacher</button>
                        <button type="button" class="task-detail-menu-item task-detail-menu-item-danger" data-task-action="delete" data-task-id="${escapeTaskHtml(String(taskId))}" role="menuitem"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18"></path><path d="M8 6V4h8v2"></path><path d="M19 6l-1 14H6L5 6"></path><path d="M10 11v5M14 11v5"></path></svg>Delete Task</button>
                    </div>
                </div>
            </header>
            <div class="task-detail-teacher"><span class="task-detail-avatar">${escapeTaskHtml(initials.toUpperCase())}</span><div><strong>${escapeTaskHtml(teacherName)}</strong><small>Teacher</small>${teacherEmail ? `<a href="mailto:${escapeTaskHtml(teacherEmail)}">${escapeTaskHtml(teacherEmail)}</a>` : ''}</div></div>
            <div class="task-detail-meta-grid">
                <div class="task-detail-meta-item"><span class="task-detail-meta-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="5" width="16" height="15" rx="2"></rect><path d="M8 3v4M16 3v4M4 9h16"></path></svg></span><div><small>Due Date</small><strong>${escapeTaskHtml(dueDate)}</strong></div></div>
                <div class="task-detail-meta-item"><span class="task-detail-meta-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2"></path></svg></span><div><small>Due Time</small><strong>${escapeTaskHtml(dueTime)}</strong></div></div>
                <div class="task-detail-meta-item"><span class="task-detail-meta-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 10c0 5-7 11-7 11S5 15 5 10a7 7 0 1 1 14 0Z"></path><circle cx="12" cy="10" r="2"></circle></svg></span><div><small>Room</small><strong>${escapeTaskHtml(room)}</strong></div></div>
                <div class="task-detail-meta-item"><span class="task-detail-meta-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="m8 12 3 3 5-6"></path></svg></span><div><small>Status</small><strong>${escapeTaskHtml(statusLabel)}</strong>${overdueNote ? `<span class="task-detail-status-note">${escapeTaskHtml(overdueNote)}</span>` : ''}</div></div>
            </div>
            <section class="task-detail-section"><h3><span class="task-detail-section-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h12v18H6z"></path><path d="M9 8h6M9 12h6M9 16h4"></path></svg></span>Description</h3><p class="task-detail-description">${escapeTaskHtml(description)}</p></section>
            <section class="task-detail-section"><h3><span class="task-detail-section-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m8 12 6-6a4 4 0 0 1 6 6l-8 8a5 5 0 0 1-7-7l7-7"></path></svg></span>Attachments${attachments.length ? `<small>${attachments.length} ${attachments.length === 1 ? 'file' : 'files'}</small>` : ''}</h3><div class="task-detail-attachments">${attachmentMarkup}</div></section>
            <div class="task-detail-created"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2"></path></svg><span>Created ${escapeTaskHtml(createdDate)}</span></div>
        </article>
        <div class="task-detail-image-modal" id="taskDetailImageModal" hidden>
            <img src="" alt="Task attachment preview" />
        </div>
    `;
}

function handleTaskDetailAction(action, taskId) {
    const task = taskRecordsById.get(String(taskId));
    if (!task) return;

    const teacherName = String(task.teacher_name || task.createdBy || task.teacher || 'Teacher');
    const teacherEmail = String(task.teacher_email || task.teacherEmail || '');

    switch (action) {
        case 'edit':
            break;
        case 'attachments':
            document.querySelector('.task-detail-section:last-of-type')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            showToast('Viewing attachments.');
            break;
        case 'message': {
            const recipientName = String(task.teacher_name || task.createdBy || task.teacher || 'Teacher');
            const recipientEmail = String(task.teacher_email || task.teacherEmail || '');
            const recipientId = String(task.teacher_id || task.teacherId || recipientEmail || recipientName || 'teacher');
            const query = new URLSearchParams({
                userId: recipientId,
                name: recipientName,
                email: recipientEmail,
                role: 'teacher',
                returnTo: 'task',
                taskId: String(taskId)
            });
            if (recipientEmail) {
                window.location.href = `/admin-messages?${query}`;
            } else {
                showToast('Teacher email is not available.');
            }
            break;
        }
        case 'delete': {
            openTaskDeleteConfirmation(taskId, task);
            break;
        }
        default:
            break;
    }
}

function closeTaskDetailMenu() {
    const menu = taskDetailView.querySelector('.task-detail-menu:not([hidden])');
    if (!menu) return;
    menu.hidden = true;
    const toggle = taskDetailView.querySelector('.task-detail-more[aria-expanded="true"]');
    if (toggle) toggle.setAttribute('aria-expanded', 'false');
}

function openTaskDetails(taskId, returnFocus) {
    if (!taskRecordsById.has(String(taskId))) return;
    detailReturnToAllTasks = taskShell.classList.contains('all-tasks-view');
    detailReturnFocus = returnFocus || null;
    renderTaskDetails(taskId);
    taskDetailView.hidden = false;
    taskDetailView.classList.remove('visible');
    taskShell.classList.add('task-detail-open');
    document.body.classList.add('task-detail-open');
    allTasksBack.hidden = false;
    taskStatusTabs.hidden = true;
    taskPageTitle.textContent = 'Task Details';
    taskPageSubtitle.hidden = true;
    void taskDetailView.offsetWidth;
    taskDetailView.classList.add('visible');
}

function openTaskImagePreview(source) {
    const modal = taskDetailView.querySelector('#taskDetailImageModal');
    const image = modal?.querySelector('img');
    if (!modal || !image || !source) return;
    image.src = source;
    modal.hidden = false;
}

function closeTaskImagePreview() {
    const modal = taskDetailView.querySelector('#taskDetailImageModal');
    if (!modal) return;
    modal.hidden = true;
    const image = modal.querySelector('img');
    if (image) image.src = '';
}

taskDetailView.addEventListener('click', (event) => {
    if (!(event.target instanceof Element)) return;

    const titleToggle = event.target.closest('.task-detail-title-more');
    if (titleToggle) {
        const title = taskDetailView.querySelector('#taskDetailTitle');
        if (!title) return;
        const expanded = titleToggle.getAttribute('aria-expanded') === 'true';
        title.innerHTML = expanded
            ? `${escapeTaskHtml(title.dataset.fullTitle.slice(0, 34))}... <button type="button" class="task-detail-title-more" aria-expanded="false">see more</button>`
            : `${escapeTaskHtml(title.dataset.fullTitle)} <button type="button" class="task-detail-title-more" aria-expanded="true">show less</button>`;
        titleToggle.setAttribute('aria-expanded', String(!expanded));
        return;
    }

    const imagePreview = event.target.closest('.task-detail-attachment-image');
    if (imagePreview) {
        if (event.target.closest('.task-attachment-download')) return;
        openTaskImagePreview(imagePreview.dataset.imageSrc);
        return;
    }

    const modalBackdrop = event.target.closest('#taskDetailImageModal');
    if (modalBackdrop && !event.target.closest('img')) {
        closeTaskImagePreview();
        return;
    }

    const menuToggle = event.target.closest('.task-detail-more');
    if (menuToggle) {
        const menu = taskDetailView.querySelector('.task-detail-menu');
        if (!menu) return;
        const isOpen = menuToggle.getAttribute('aria-expanded') === 'true';
        menu.hidden = isOpen;
        menuToggle.setAttribute('aria-expanded', String(!isOpen));
        return;
    }

    const menuItem = event.target.closest('[data-task-action]');
    if (menuItem) {
        handleTaskDetailAction(menuItem.dataset.taskAction, menuItem.dataset.taskId);
        closeTaskDetailMenu();
        return;
    }

    if (!event.target.closest('.task-detail-menu')) {
        closeTaskDetailMenu();
    }
});

taskDetailView.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        closeTaskDetailMenu();
    }
});

document.addEventListener('click', (event) => {
    if (!(event.target instanceof Element)) return;
    if (!event.target.closest('.task-detail-menu') && !event.target.closest('.task-detail-more')) {
        closeTaskDetailMenu();
    }
});

function closeTaskDetails() {
    taskDetailView.hidden = true;
    taskDetailView.classList.remove('visible');
    taskShell.classList.remove('task-detail-open');
    document.body.classList.remove('task-detail-open');
    allTasksBack.hidden = !detailReturnToAllTasks;
    taskStatusTabs.hidden = !detailReturnToAllTasks;
    taskPageTitle.textContent = detailReturnToAllTasks ? 'All Tasks' : 'Task Management';
    taskPageSubtitle.textContent = detailReturnToAllTasks ? 'View and manage all your tasks.' : 'Monitor and manage all posted tasks.';
    taskPageSubtitle.hidden = detailReturnToAllTasks;
    if (detailReturnToAllTasks) centerStatusTab(statusFilter.value);
    detailReturnFocus?.focus();
}

function restoreRequestedTaskDetail() {
    const requestedTaskId = new URLSearchParams(window.location.search).get('taskId');
    if (!requestedTaskId || !taskRecordsById.has(String(requestedTaskId))) return;

    const currentDetailTaskId = taskDetailView.querySelector('[data-task-id]')?.dataset?.taskId;
    if (currentDetailTaskId === String(requestedTaskId)) return;

    requestAnimationFrame(() => {
        openTaskDetails(requestedTaskId, null);
    });
}

function renderTasks(records) {
    tasksLoaded = true;
    hideTaskLoader();
    taskRecordsById = new Map(records.map(({ id, task }) => [String(id), task]));
    const fragment = document.createDocumentFragment();
    records.forEach(({ id, task }, index) => fragment.append(createTaskCard(id, task, index)));
    taskList.replaceChildren(fragment);
    taskCards = [...taskList.querySelectorAll('.task-card')];
    emptyState.textContent = taskCards.length ? 'No tasks match these filters.' : 'No tasks found.';
    updateTaskCounts();
    filterTasks();

    const counts = taskCards.reduce((result, card) => {
        result.all += 1;
        result[card.dataset.status] = (result[card.dataset.status] || 0) + 1;
        return result;
    }, { all: 0 });
    ['active', 'completed', 'pending', 'in-progress', 'overdue'].forEach((status) => {
        counts[status] = counts[status] || 0;
    });
    const dashboardTasks = records.map(({ id, task }) => ({
        id,
        title: String(task.title || task.task_title || task.taskName || 'Untitled task'),
        teacher: String(task.teacher_name || task.createdBy || 'Teacher'),
        amount: formatTaskAmount(task),
        status: normalizedTaskStatus(task),
    }));
    try {
        localStorage.setItem(taskSnapshotStorageKey, JSON.stringify({ counts, tasks: dashboardTasks, updatedAt: Date.now() }));
    } catch (error) {
        console.error('Unable to share Admin Tasks data with the dashboard:', error);
    }

    restoreRequestedTaskDetail();
}

async function loadRealtimeTasks() {
    try {
        const database = await window.firebaseModule.waitForFirebase();
        database.ref('tasks').on('value', (snapshot) => {
            const records = Object.entries(snapshot.val() || {})
                .filter(([, task]) => task && typeof task === 'object')
                .map(([id, task]) => ({ id, task }))
                .sort((first, second) => new Date(second.task.created_at || 0) - new Date(first.task.created_at || 0));
            renderTasks(records);
        }, (error) => {
            console.error('Admin Firebase task read error:', error);
            tasksLoaded = true;
            taskList.replaceChildren();
            hideTaskLoader();
            emptyState.textContent = 'Unable to load tasks.';
            emptyState.hidden = false;
        });
    } catch (error) {
        console.error('Admin Firebase connection error:', error);
        tasksLoaded = true;
        taskList.replaceChildren();
        hideTaskLoader();
        emptyState.textContent = 'Unable to load tasks right now.';
        emptyState.hidden = false;
    }
}

function filterTasks() {
    const query = searchInput.value.trim().toLowerCase();
    const teacherQuery = teacherSearchInput.value.trim().toLowerCase();
    const status = statusFilter.value;
    let visibleCount = 0;

    taskCards.forEach((card) => {
        const matchesQuery = !query || card.dataset.search.includes(query);
        const matchesTeacher = !teacherQuery || card.dataset.teacher.includes(teacherQuery);
        const matchesStatus = status === 'all' || card.dataset.status === status;
        const visible = matchesQuery && matchesTeacher && matchesStatus;
        card.hidden = !visible;
        if (visible) visibleCount += 1;
    });

    emptyState.hidden = visibleCount !== 0 || !tasksLoaded;
}

updateTaskCounts();
function centerStatusTab(status) {
    const activeTab = statusTabs.find((tab) => tab.dataset.statusTab === status);
    if (activeTab) {
        const target = activeTab.offsetLeft - (taskStatusTabs.clientWidth - activeTab.offsetWidth) / 2;
        const maxScroll = taskStatusTabs.scrollWidth - taskStatusTabs.clientWidth;
        taskStatusTabs.scrollLeft = Math.max(0, Math.min(target, maxScroll));
    }
}
function syncStatusTab(status) {
    statusTabs.forEach((tab) => tab.classList.toggle('active', tab.dataset.statusTab === status));
    centerStatusTab(status);
}

[searchInput, teacherSearchInput].forEach((control) => control.addEventListener('input', filterTasks));
statusFilter.addEventListener('change', () => {
    syncStatusTab(statusFilter.value);
    filterTasks();
});
function setAllTasksView(isOpen) {
    window.clearTimeout(transitionTimer);
    taskShell.classList.remove('view-transition-forward', 'view-transition-back');
    if (isOpen) {
        taskShell.classList.add('all-tasks-view');
        document.body.classList.add('all-tasks-open');
        void taskShell.offsetWidth;
        taskShell.classList.add('view-transition-forward');
        transitionTimer = window.setTimeout(() => taskShell.classList.remove('view-transition-forward'), 420);
    } else {
        taskShell.classList.remove('all-tasks-view');
        document.body.classList.remove('all-tasks-open');
        void taskShell.offsetWidth;
        transitionTimer = window.setTimeout(() => {
            taskShell.classList.remove('view-transition-back');
        }, 420);
    }
    allTasksBack.hidden = !isOpen;
    taskStatusTabs.hidden = !isOpen;
    if (isOpen) centerStatusTab(statusFilter.value);
    taskPageTitle.textContent = isOpen ? 'All Tasks' : 'Task Management';
    taskPageSubtitle.textContent = isOpen ? 'View and manage all your tasks.' : 'Monitor and manage all posted tasks.';
    taskPageSubtitle.hidden = isOpen;
    document.getElementById('viewAllButton').setAttribute('aria-expanded', String(isOpen));
}

document.getElementById('viewAllButton').addEventListener('click', () => {
    searchInput.value = '';
    teacherSearchInput.value = '';
    statusFilter.value = 'all';
    syncStatusTab('all');
    setAllTasksView(true);
    filterTasks();
});
allTasksBack.addEventListener('click', () => {
    if (taskShell.classList.contains('task-detail-open')) {
        closeTaskDetails();
        return;
    }
    searchInput.value = '';
    teacherSearchInput.value = '';
    statusFilter.value = 'all';
    syncStatusTab('all');
    setAllTasksView(false);
    filterTasks();
});
function openStatusTasks(status) {
    searchInput.value = '';
    teacherSearchInput.value = '';
    statusFilter.value = status;
    syncStatusTab(status);
    setAllTasksView(true);
    filterTasks();
}
function activateSummaryCard(event, status) {
    if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        openStatusTasks(status);
    }
}
summaryCards.forEach((card) => {
    card.addEventListener('click', () => openStatusTasks(card.dataset.summaryStatus));
    card.addEventListener('keydown', (event) => activateSummaryCard(event, card.dataset.summaryStatus));
});
statusTabs.forEach((tab) => tab.addEventListener('click', () => {
    const status = tab.dataset.statusTab;
    statusFilter.value = status;
    syncStatusTab(status);
    filterTasks();
}));
taskList.addEventListener('click', (event) => {
    if (!(event.target instanceof Element)) return;
    const card = event.target.closest('.task-card:not(.task-skeleton)');
    if (!card || (event.target.closest('button') && !event.target.closest('.more-button'))) return;
    openTaskDetails(card.dataset.taskId, card);
});
taskList.addEventListener('keydown', (event) => {
    if (!['Enter', ' '].includes(event.key) || event.target.closest('button')) return;
    const card = event.target.closest('.task-card:not(.task-skeleton)');
    if (!card) return;
    event.preventDefault();
    openTaskDetails(card.dataset.taskId, card);
});
if (new URLSearchParams(window.location.search).get('view') === 'all') {
    setAllTasksView(true);
}

showTaskLoader();
loadRealtimeTasks();
