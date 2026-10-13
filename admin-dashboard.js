const toast = document.getElementById('toast');
let toastTimer;

function showToast(message) {
    toast.textContent = message;
    toast.classList.add('show');
    window.clearTimeout(toastTimer);
    toastTimer = window.setTimeout(() => toast.classList.remove('show'), 2200);
}

document.querySelectorAll('[data-action]').forEach((button) => {
    button.addEventListener('click', () => showToast(`${button.dataset.action} is ready to configure.`));
});

document.getElementById('notificationButton').addEventListener('click', () => showToast('You have 3 new notifications.'));

const userCountsStorageKey = 'utosapp.admin.users.counts.v1';
const taskSnapshotStorageKey = 'utosapp.admin.tasks.snapshot.v1';
const transactionSnapshotStorageKey = 'utosapp.admin.transactions.snapshot.v2';

function readDashboardSnapshot(key) {
    try {
        const value = localStorage.getItem(key);
        return value ? JSON.parse(value) : null;
    } catch (error) {
        console.error(`Unable to read dashboard snapshot ${key}:`, error);
        return null;
    }
}

function setDashboardCount(elementId, value) {
    const count = Number(value);
    document.getElementById(elementId).textContent = Number.isInteger(count) && count >= 0 ? String(count) : '—';
}

function renderDashboardTransactionSummary(snapshot) {
    if (!snapshot) return;
    const platformEarnings = Number(snapshot.platformEarnings);
    const transactionCount = Number(snapshot.transactionCount);
    if (!Number.isFinite(platformEarnings) || platformEarnings < 0) return;
    if (!Number.isInteger(transactionCount) || transactionCount < 0) return;

    document.getElementById('dashboardPlatformEarningsTotal').textContent = `₱${new Intl.NumberFormat('en-PH').format(platformEarnings)}`;
    document.getElementById('dashboardTransactionCount').textContent = `${new Intl.NumberFormat('en-PH').format(transactionCount)} Total Transactions`;
}

function renderDashboardUserCounts(snapshot, animate = false) {
    setDashboardCount('dashboardStudentCount', snapshot?.students);
    setDashboardCount('dashboardTeacherCount', snapshot?.teachers);
    if (animate && snapshot) {
        window.animateCountUps?.([
            document.getElementById('dashboardStudentCount'),
            document.getElementById('dashboardTeacherCount'),
        ]);
    }
}

function renderRecentTaskRows(tasks) {
    const rows = document.getElementById('dashboardRecentTaskRows');
    const emptyState = document.getElementById('dashboardRecentTaskEmpty');
    const fragment = document.createDocumentFragment();
    const rowColors = ['blue', 'violet', 'mint'];
    const badgeClasses = { active: 'active', completed: 'done', pending: 'pending', 'in-progress': 'in-progress', overdue: 'overdue' };
    const statusLabels = { active: 'Active', completed: 'Completed', pending: 'Pending', 'in-progress': 'In Progress', overdue: 'Overdue' };
    const recentTasks = Array.isArray(tasks) ? tasks.slice(0, 3) : [];

    recentTasks.forEach((task, index) => {
        const row = document.createElement('div');
        row.className = 'table-row';

        const titleCell = document.createElement('span');
        const fullTitle = String(task.title || 'Untitled task');
        const visibleTitle = fullTitle.length > 11 ? `${fullTitle.slice(0, 11)}...` : fullTitle;
        titleCell.title = fullTitle;
        const icon = document.createElement('i');
        icon.className = `row-icon ${rowColors[index % rowColors.length]}`;
        titleCell.append(icon, document.createTextNode(visibleTitle));
        row.append(titleCell);

        const teacherCell = document.createElement('span');
        teacherCell.textContent = task.teacher || 'Teacher';
        row.append(teacherCell);

        const amountCell = document.createElement('span');
        amountCell.textContent = task.amount || 'Not specified';
        row.append(amountCell);

        const statusCell = document.createElement('span');
        const badge = document.createElement('em');
        badge.className = `badge ${badgeClasses[task.status] || 'pending'}`;
        badge.textContent = statusLabels[task.status] || 'Pending';
        statusCell.append(badge);
        row.append(statusCell);

        const arrow = document.createElement('b');
        arrow.textContent = '›';
        row.append(arrow);
        fragment.append(row);
    });

    rows.replaceChildren(fragment);
    emptyState.hidden = recentTasks.length > 0;
}

function renderDashboardTaskSnapshot(snapshot, animate = false) {
    const counts = snapshot?.counts;
    setDashboardCount('dashboardTaskCount', counts?.all);
    const statusTargets = {
        active: 'dashboardTaskActiveCount',
        completed: 'dashboardTaskCompletedCount',
        pending: 'dashboardTaskPendingCount',
        'in-progress': 'dashboardTaskInProgressCount',
        overdue: 'dashboardTaskOverdueCount',
    };
    Object.entries(statusTargets).forEach(([status, elementId]) => {
        setDashboardCount(elementId, snapshot ? counts?.[status] ?? 0 : undefined);
    });
    renderRecentTaskRows(snapshot?.tasks);
    if (animate && snapshot) window.animateCountUp?.(document.getElementById('dashboardTaskCount'));
}

function loadDashboardPageSnapshots(animate = false) {
    renderDashboardUserCounts(readDashboardSnapshot(userCountsStorageKey), animate);
    renderDashboardTaskSnapshot(readDashboardSnapshot(taskSnapshotStorageKey), animate);
    renderDashboardTransactionSummary(readDashboardSnapshot(transactionSnapshotStorageKey));
}

loadDashboardPageSnapshots(true);
window.addEventListener('storage', (event) => {
    if (event.key === userCountsStorageKey) renderDashboardUserCounts(readDashboardSnapshot(userCountsStorageKey), true);
    if (event.key === taskSnapshotStorageKey) renderDashboardTaskSnapshot(readDashboardSnapshot(taskSnapshotStorageKey), true);
    if (event.key === transactionSnapshotStorageKey) renderDashboardTransactionSummary(readDashboardSnapshot(transactionSnapshotStorageKey));
});

