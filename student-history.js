const state = { tasks: [], filter: 'all', filterOpen: false };
const elements = { list: document.getElementById('historyList'), empty: document.getElementById('historyEmpty'), status: document.getElementById('historyStatus'), toast: document.getElementById('toast'), total: document.getElementById('totalCount'), completed: document.getElementById('completedCount'), overdue: document.getElementById('overdueCount'), cancelled: document.getElementById('cancelledCount'), filter: document.getElementById('timeFilter'), filterMenu: document.getElementById('historyFilterMenu'), filterOptions: [...document.querySelectorAll('.history-filter-option')], filterCountBadges: [...document.querySelectorAll('.history-filter-count')] };
const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (character) => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', "'":'&#39;', '"':'&quot;' }[character]));
const renderSkeleton = () => {
    elements.list.setAttribute('aria-busy', 'true');
    elements.list.innerHTML = Array.from({ length: 4 }, () => `
        <article class="history-card history-skeleton-card" aria-hidden="true">
            <span class="timeline-marker"><span class="history-skeleton-block history-skeleton-marker"></span></span>
            <div class="history-teacher">
                <span class="history-skeleton-block history-skeleton-avatar"></span>
                <span class="history-teacher-copy"><span class="history-skeleton-block history-skeleton-teacher-name"></span><span class="history-skeleton-block history-skeleton-teacher-role"></span></span>
            </div>
            <div class="task-copy">
                <span class="history-skeleton-block history-skeleton-task-title"></span>
                <div class="history-skeleton-meta"><span class="history-skeleton-block"></span><span class="history-skeleton-block history-skeleton-meta-short"></span></div>
            </div>
            <span class="history-skeleton-block history-skeleton-status"></span>
            <span class="task-chevron" aria-hidden="true"></span>
        </article>
    `).join('');
};
const isCompleted = (task) => Boolean(task.is_completed || task.completed
    || ['completed', 'complete', 'done'].includes(String(task.status || '').trim().toLowerCase()));
const isPaymentSettled = (task) => ['paid', 'received', 'released', 'completed', 'success', 'successful', 'payment_successful']
    .includes(String(task.payment_status || '').trim().toLowerCase().replace(/[\s-]+/g, '_'));
const isForPayment = (task) => isCompleted(task) && !isPaymentSettled(task);
const isOverdue = (task) => { if (isCompleted(task) || task.status === 'cancelled' || task.status === 'canceled') return false; const dateValue = task.due_date || task.due; if (!dateValue || dateValue === 'No due date') return false; const timeValue = task.due_time || task.dueTime || '23:59'; const deadline = new Date(`${String(dateValue).replace(/^Due\s+/i, '')} ${timeValue}`); return !Number.isNaN(deadline.getTime()) && deadline < new Date(); };
const hasTeacherApproval = (task) => ['approved', 'accepted', 'hired', 'assigned', 'in_progress'].includes(
    String(task.student_application?.status
        || task.student_application?.application_status
        || task.student_application?.approval_status
        || task.student_application_status
        || '').trim().toLowerCase().replace(/[\s-]+/g, '_')
);
const getStatus = (task) => { if (isCompleted(task)) return { key:'completed', label:'Completed' }; if (task.status === 'cancelled' || task.status === 'canceled') return { key:'cancelled', label:'Cancelled' }; if (hasTeacherApproval(task) && isOverdue(task)) return { key:'overdue', label:'Overdue' }; return { key:'pending', label:'Pending' }; };
const formatDate = (task) => { const value = task.created_at || task.completed_at || task.updated_at || task.due_date || task.due; if (!value) return 'No date'; const date = new Date(value); return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleDateString('en-US', { month:'short', day:'2-digit', year:'numeric' }); };
const formatTime = (task) => { const value = task.created_at || task.completed_at || task.updated_at; if (!value) return ''; const date = new Date(value); return Number.isNaN(date.getTime()) ? '' : date.toLocaleTimeString('en-US', { hour:'numeric', minute:'2-digit' }); };
const iconFor = (task) => { const text = `${task.title || ''} ${task.description || ''}`.toLowerCase(); if (text.includes('meeting')) return '<svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3"/><circle cx="17" cy="9" r="2"/><path d="M3 19a6 6 0 0 1 12 0M15 15a5 5 0 0 1 6 4"/></svg>'; if (text.includes('exam') || text.includes('study')) return '<svg viewBox="0 0 24 24"><path d="m3 9 9-4 9 4-9 4-9-4Z"/><path d="M7 11v5c2.8 2 7.2 2 10 0v-5M21 10v5"/></svg>'; if (text.includes('report') || text.includes('profile')) return '<svg viewBox="0 0 24 24"><path d="M6 3h9l3 3v15H6z"/><path d="M14 3v4h4M9 12h6M9 16h6"/></svg>'; return '<svg viewBox="0 0 24 24"><path d="m5 13 4 4L19 7"/></svg>'; };
const getTeacherName = (task) => String(task.teacher_name || task.teacherName || task.first_name || task.createdBy || 'Teacher').trim();
const getTeacherAvatar = (task) => task.teacher_photo || task.teacherPhoto || '';
const formatAmount = (task) => {
    const value = task.amount ?? task.budget ?? task.total_amount;
    if (value === null || value === undefined || value === '') return '';
    const amount = Number(value);
    return Number.isFinite(amount) && amount >= 0
        ? `PHP ${new Intl.NumberFormat('en-PH', { maximumFractionDigits: 2 }).format(amount)}`
        : '';
};
const historyTasks = () => state.tasks.filter((task) => !isForPayment(task)
    && ['completed', 'overdue', 'cancelled'].includes(getStatus(task).key));
const visibleTasks = () => historyTasks().filter((task) => state.filter === 'all' || getStatus(task).key === state.filter);
const updateSummary = () => { const statuses = historyTasks().map(getStatus); elements.total.textContent = statuses.length; elements.completed.textContent = statuses.filter((status) => status.key === 'completed').length; elements.overdue.textContent = statuses.filter((status) => status.key === 'overdue').length; elements.cancelled.textContent = statuses.filter((status) => status.key === 'cancelled').length; };
const updateFilter = () => { const labels = { all:'All Tasks', completed:'Completed', overdue:'Overdue', cancelled:'Cancelled' }; elements.filter.querySelector('span').textContent = labels[state.filter]; elements.filterOptions.forEach((option) => { const selected = option.dataset.filter === state.filter; option.classList.toggle('is-selected', selected); option.setAttribute('aria-checked', String(selected)); }); const statuses = historyTasks().map(getStatus); const counts = { all: statuses.length, completed: statuses.filter((status) => status.key === 'completed').length, overdue: statuses.filter((status) => status.key === 'overdue').length, cancelled: statuses.filter((status) => status.key === 'cancelled').length }; elements.filterCountBadges.forEach((badge) => { badge.textContent = String(counts[badge.dataset.filterCount] || 0); }); };
const render = () => {
    const tasks = visibleTasks();
    elements.list.innerHTML = tasks.map((task, index) => {
        const status = getStatus(task);
        const teacher = getTeacherName(task);
        const avatar = getTeacherAvatar(task);
        const amount = formatAmount(task);
        return `<a class="history-card" href="/student-history-details.html?id=${encodeURIComponent(String(task.id || ''))}" style="animation-delay:${Math.min(index * 55, 400)}ms">
            <span class="timeline-marker" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8"/><path d="M12 8v4l3 2"/></svg></span>
            <div class="history-teacher">
                ${avatar
                    ? `<img class="history-teacher-avatar" src="${escapeHtml(avatar)}" alt="">`
                    : `<span class="history-teacher-avatar history-teacher-avatar--fallback" aria-hidden="true">${escapeHtml(teacher.split(/\s+/).filter(Boolean).slice(0, 2).map((part) => part[0]).join('').toUpperCase())}</span>`}
                <span class="history-teacher-copy"><span class="history-teacher-name">${escapeHtml(teacher)}</span><span class="history-teacher-role">${escapeHtml(task.teacher_role || task.teacherRole || task.role || 'Teacher')}</span></span>
            </div>
            <div class="task-copy">
                <h2>${escapeHtml(task.title || 'Untitled task')}</h2>
                <div class="task-meta">
                    <span><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/></svg>${escapeHtml(formatDate(task))}${formatTime(task) ? ` · ${escapeHtml(formatTime(task))}` : ''}</span>
                    ${amount ? `<span><svg viewBox="0 0 24 24"><ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v6c0 1.7 3.6 3 8 3s8-1.3 8-3V5M4 11v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6"/></svg>${escapeHtml(amount)}</span>` : ''}
                </div>
            </div>
            <div class="history-card-badges">
                <span class="task-state ${status.key}">${status.label}</span>
            </div>
            <span class="task-chevron" aria-hidden="true">›</span>
        </a>`;
    }).join('');
    elements.list.setAttribute('aria-busy', 'false');
    elements.empty.hidden = tasks.length > 0;
    elements.status.textContent = '';
    updateFilter();
};
elements.filter.addEventListener('click', (event) => { event.stopPropagation(); state.filterOpen = !state.filterOpen; elements.filterMenu.classList.toggle('open', state.filterOpen); elements.filter.setAttribute('aria-expanded', String(state.filterOpen)); });
elements.filterOptions.forEach((option) => option.addEventListener('click', (event) => { event.stopPropagation(); state.filter = option.dataset.filter || 'all'; state.filterOpen = false; elements.filterMenu.classList.remove('open'); elements.filter.setAttribute('aria-expanded', 'false'); render(); }));
document.addEventListener('click', (event) => { if (!event.target.closest('.history-filter-wrap')) { state.filterOpen = false; elements.filterMenu.classList.remove('open'); elements.filter.setAttribute('aria-expanded', 'false'); } });
document.getElementById('accountButton').addEventListener('click', (event) => {
    event.preventDefault();
    window.location.href = '/student-account';
});
const loadHistory = async () => { renderSkeleton(); try { const response = await fetch('/api/student-tasks'); if (!response.ok) throw new Error('Student session required'); const result = await response.json(); state.tasks = (result.tasks || []).sort((first, second) => new Date(second.created_at || 0) - new Date(first.created_at || 0)); updateSummary(); render(); } catch (error) { elements.list.replaceChildren(); elements.list.setAttribute('aria-busy', 'false'); elements.status.textContent = 'We could not load your history right now.'; elements.empty.hidden = false; elements.toast.textContent = error.message; elements.toast.classList.add('show'); } };
loadHistory();
