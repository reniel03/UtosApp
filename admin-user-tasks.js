const params = new URLSearchParams(window.location.search);
const user = {
    id: params.get('userId') || '',
    name: params.get('name') || 'User',
    email: params.get('email') || '',
    role: ['teacher', 'student'].includes(params.get('role')) ? params.get('role') : 'user'
};
const taskList = document.getElementById('taskList');
const emptyState = document.getElementById('emptyState');
const searchInput = document.getElementById('taskSearch');
const statusFilter = document.getElementById('statusFilter');
const backLink = document.getElementById('backToUser');
let tasks = [];
let isLoadingTasks = true;

document.title = `UtosApp | Tasks for ${user.name}`;
document.getElementById('userName').textContent = user.name;
document.getElementById('userRole').textContent = user.role.charAt(0).toUpperCase() + user.role.slice(1);
if (backLink) {
    const returnPath = '/admin-users';
    backLink.href = returnPath;
    backLink.addEventListener('click', (event) => {
        event.preventDefault();
        try {
            sessionStorage.setItem('utosapp.admin.skip-user-detail-restore', '1');
            sessionStorage.removeItem('utosapp.admin.return-user-detail');
        } catch (error) {
            console.error('Unable to clear cached user detail when returning to the admin users page:', error);
        }
        window.location.href = returnPath;
    });
}

function normalizeStatus(task) {
    const status = String(task.status || 'pending').toLowerCase().replace(/[\s_]+/g, '-');
    if (['completed', 'complete', 'done'].includes(status)) return 'completed';
    if (['in-progress', 'ongoing'].includes(status)) return 'in-progress';
    if (['active', 'open', 'published'].includes(status)) return 'active';
    if (status === 'overdue') return 'overdue';
    const dueDate = String(task.due_date || task.due || task.deadline || '').replace(/^Due\s+/i, '');
    if (dueDate && !['cancelled', 'canceled'].includes(status)) {
        const dueAt = new Date(`${dueDate}T${task.due_time || task.dueTime || '23:59:59'}`);
        if (!Number.isNaN(dueAt.getTime()) && dueAt < new Date()) return 'overdue';
    }
    return 'pending';
}

function taskBelongsToUser(task) {
    const email = user.email.trim().toLowerCase();
    const name = user.name.trim().toLowerCase();
    if (user.role === 'teacher') {
        const teacherEmail = String(task.teacher_email || task.teacherEmail || '').trim().toLowerCase();
        const teacherName = String(task.teacher_name || task.teacherName || task.createdBy || task.teacher || '').trim().toLowerCase();
        return Boolean((email && teacherEmail === email) || (name && teacherName === name));
    }

    const fields = [task.student_email, task.studentEmail, task.assigned_to, task.assignedTo, task.assignee,
        task.student_name, task.studentName, task.student_id, task.studentId, task.email, task.user_email, task.userEmail];
    if (Array.isArray(task.applications)) {
        task.applications.forEach((application) => fields.push(application?.student_email, application?.studentEmail,
            application?.email, application?.assigned_to, application?.assignedTo, application?.student_id,
            application?.studentId, application?.assignee, application?.student_name, application?.studentName));
    }
    return fields.some((value) => {
        if (typeof value !== 'string' && typeof value !== 'number') return false;
        const normalized = String(value).trim().toLowerCase();
        return Boolean(normalized && ((email && normalized === email) || (name && normalized === name) || (user.id && normalized === user.id.toLowerCase())));
    });
}

function formatDate(value) {
    if (!value) return 'No due date';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
}

function createIcon() {
    const wrapper = document.createElement('span');
    wrapper.className = 'task-icon';
    wrapper.setAttribute('aria-hidden', 'true');
    wrapper.innerHTML = '<svg viewBox="0 0 24 24"><path d="M6 3h12v18H6z"></path><path d="M9 8h6M9 12h6M9 16h4"></path></svg>';
    return wrapper;
}

function createTaskCard(task) {
    const status = normalizeStatus(task);
    const title = String(task.title || task.task_title || task.taskName || 'Untitled task');
    const card = document.createElement('article');
    card.className = `task-card status-${status}`;

    const main = document.createElement('div');
    main.className = 'task-main';
    const heading = document.createElement('h3');
    heading.textContent = title;
    main.append(heading);
    const description = document.createElement('p');
    description.textContent = String(task.description || task.task_description || 'No description provided.');
    main.append(description);

    const meta = document.createElement('div');
    meta.className = 'task-meta';
    const due = document.createElement('span');
    due.textContent = `Due ${formatDate(task.due_date || task.due || task.deadline)}`;
    meta.append(due);
    const roomValue = task.room || task.class_name || task.className;
    if (roomValue) {
        const room = document.createElement('span');
        room.textContent = String(roomValue);
        meta.append(room);
    }
    main.append(meta);

    const badge = document.createElement('span');
    badge.className = `status-badge ${status}`;
    badge.textContent = status === 'in-progress' ? 'In Progress' : status.charAt(0).toUpperCase() + status.slice(1);
    card.append(createIcon(), main, badge);
    return card;
}

function updateSummary() {
    const counts = tasks.reduce((result, task) => {
        result.total += 1;
        result[normalizeStatus(task)] += 1;
        return result;
    }, { total: 0, active: 0, pending: 0, 'in-progress': 0, completed: 0, overdue: 0 });
    document.getElementById('totalCount').textContent = String(counts.total);
    document.getElementById('pendingCount').textContent = String(counts.pending);
    document.getElementById('progressCount').textContent = String(counts['in-progress']);
    document.getElementById('completedCount').textContent = String(counts.completed);
    document.getElementById('overdueCount').textContent = String(counts.overdue);
}

function createSkeletonCard() {
    const card = document.createElement('article');
    card.className = 'task-card task-skeleton-card';

    const icon = document.createElement('span');
    icon.className = 'task-skeleton-icon';
    card.append(icon);

    const main = document.createElement('div');
    main.className = 'task-main';
    main.innerHTML = `
        <div class="task-skeleton-line title"></div>
        <div class="task-skeleton-line subtitle"></div>
        <div class="task-skeleton-meta">
            <span class="task-skeleton-line meta"></span>
            <span class="task-skeleton-line meta short"></span>
        </div>
    `;
    card.append(main);

    const badge = document.createElement('span');
    badge.className = 'task-skeleton-badge';
    card.append(badge);

    return card;
}

function renderSkeletons(count = 3) {
    taskList.replaceChildren(...Array.from({ length: count }, createSkeletonCard));
    taskList.setAttribute('aria-busy', 'true');
    emptyState.hidden = true;
}

function renderTasks() {
    if (isLoadingTasks) {
        renderSkeletons();
        return;
    }

    const query = searchInput.value.trim().toLowerCase();
    const status = statusFilter.value;
    const visibleTasks = tasks.filter((task) => {
        const taskStatus = normalizeStatus(task);
        const searchable = `${task.title || ''} ${task.task_title || ''} ${task.taskName || ''} ${task.description || ''} ${task.room || ''} ${task.class_name || ''}`.toLowerCase();
        return (!query || searchable.includes(query)) && (status === 'all' || taskStatus === status);
    });
    taskList.replaceChildren(...visibleTasks.map(createTaskCard));
    taskList.setAttribute('aria-busy', 'false');
    document.getElementById('visibleCount').textContent = String(visibleTasks.length);
    emptyState.textContent = tasks.length
        ? 'No tasks match these filters.'
        : 'This user doesn\'t have recent activity yet.';
    emptyState.hidden = visibleTasks.length > 0;
}

searchInput.addEventListener('input', renderTasks);
statusFilter.addEventListener('change', renderTasks);
document.querySelectorAll('[data-summary]').forEach((button) => {
    button.addEventListener('click', () => {
        statusFilter.value = button.dataset.summary;
        renderTasks();
    });
});

async function loadTasks() {
    isLoadingTasks = true;
    renderSkeletons();

    if (!['teacher', 'student'].includes(user.role)) {
        isLoadingTasks = false;
        taskList.setAttribute('aria-busy', 'false');
        emptyState.textContent = 'This user role does not have task records.';
        emptyState.hidden = false;
        return;
    }
    try {
        const database = await window.firebaseModule.waitForFirebase();
        database.ref('tasks').on('value', (snapshot) => {
            tasks = Object.entries(snapshot.val() || {})
            .filter(([, task]) => task && typeof task === 'object')
            .map(([id, task]) => ({ id, ...task }))
            .filter(taskBelongsToUser)
            .sort((first, second) => new Date(second.created_at || 0) - new Date(first.created_at || 0));
            isLoadingTasks = false;
            updateSummary();
            renderTasks();
        }, (error) => {
            console.error('Unable to load user tasks from Realtime Database:', error);
            isLoadingTasks = false;
            taskList.setAttribute('aria-busy', 'false');
            emptyState.textContent = 'Unable to load tasks right now.';
            emptyState.hidden = false;
        });
    } catch (error) {
        console.error('Unable to connect to Realtime Database for user tasks:', error);
        isLoadingTasks = false;
        taskList.setAttribute('aria-busy', 'false');
        emptyState.textContent = 'Unable to load tasks right now.';
        emptyState.hidden = false;
    }
}

loadTasks();