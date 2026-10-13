const toast = document.getElementById('toast');
let toastTimer;
const usersByRole = { teacher: [], student: [] };
const loadedRoles = new Set();
let loadingRoles = 2;
let databaseLoadFailed = false;
let taskRecordsPromise;
const requestedUserId = new URLSearchParams(window.location.search).get('userId');
let requestedUserDetailOpened = false;
const commonUserActions = [
    ['view-details', 'View Details'],
    ['message', 'Message'],
    ['suspend-user', 'Suspend User'],
    ['delete-user', 'Delete User'],
];

function showToast(message) {
    toast.textContent = message;
    toast.classList.add('show');
    window.clearTimeout(toastTimer);
    toastTimer = window.setTimeout(() => toast.classList.remove('show'), 2200);
}

function filterUsers() {
    const query = document.getElementById('userSearch').value.trim().toLowerCase();
    const role = document.getElementById('roleFilter').value;
    let visibleCount = 0;
    const users = document.querySelectorAll('.user-row');

    users.forEach((user) => {
        const matchesQuery = !query || user.dataset.search.includes(query);
        const matchesRole = role === 'all' || user.dataset.role === role;
        const visible = matchesQuery && matchesRole;
        user.hidden = !visible;
        if (visible) visibleCount += 1;
    });

    const emptyState = document.getElementById('emptyState');
    if (visibleCount > 0 || loadingRoles > 0) {
        emptyState.hidden = true;
        return;
    }

    emptyState.hidden = false;
    if (visibleCount === 0) {
        if (users.length === 0 && databaseLoadFailed) {
            emptyState.textContent = 'Unable to load teachers and students.';
        } else if (users.length === 0) {
            emptyState.textContent = 'No teachers or students found.';
        } else {
            emptyState.textContent = 'No users match these filters.';
        }
    }
}

function setUserMenuScrollLock(isLocked) {
    if (isLocked) {
        document.body.classList.remove('user-menu-open');
        return;
    }
    document.body.classList.remove('user-menu-open');
}

function closeUserMenus() {
    document.querySelectorAll('.user-actions-menu').forEach((menu) => {
        menu.style.transform = 'translateY(-8px) scale(0.98)';
        menu.style.opacity = '0';
        menu.hidden = true;
        menu.closest('.user-row')?.classList.remove('menu-open', 'menu-above');
    });

    const backdrop = document.querySelector('.user-menu-backdrop');
    if (backdrop) {
        backdrop.remove();
    }

    setUserMenuScrollLock(false);
}

function positionUserMenu(row, menu) {
    row.classList.remove('menu-above');
    row.classList.add('menu-open');
    menu.style.top = 'calc(100% + 8px)';
    menu.style.right = '6px';
    menu.style.bottom = 'auto';
    menu.style.left = 'auto';
    menu.style.width = 'min(146px, calc(100vw - 32px))';
    menu.style.opacity = '0';
    menu.style.transform = 'translateY(-6px) scale(0.98)';
    menu.hidden = false;
    menu.classList.remove('expanded');

    requestAnimationFrame(() => {
        const rowRect = row.getBoundingClientRect();
        const menuHeight = menu.offsetHeight || 180;
        const spaceBelow = window.innerHeight - rowRect.bottom - 20;
        const spaceAbove = rowRect.top - 20;
        const shouldOpenAbove = spaceBelow < menuHeight + 8 && spaceAbove > menuHeight + 8;

        if (shouldOpenAbove) {
            row.classList.add('menu-above');
            menu.style.top = 'auto';
            menu.style.bottom = 'calc(100% + 8px)';
        } else {
            row.classList.remove('menu-above');
            menu.style.top = 'calc(100% + 8px)';
            menu.style.bottom = 'auto';
        }

        menu.style.opacity = '1';
        menu.style.transform = 'translateY(0) scale(1)';
    });
}

function setupBottomSheetGestures(menu) {
    const handle = menu.querySelector('.sheet-handle');
    if (!handle) return;

    let startY = 0;
    let startTranslate = 0;
    let dragging = false;
    let expanded = false;

    const setTranslate = (value) => {
        menu.style.transform = `translateX(-50%) translateY(${value}px)`;
    };

    const closeSheet = () => {
        menu.classList.remove('expanded');
        expanded = false;
        menu.style.transform = 'translateX(-50%) translateY(110%)';
        setTimeout(() => {
            closeUserMenus();
        }, 180);
    };

    handle.addEventListener('pointerdown', (event) => {
        if (menu.hidden) return;
        dragging = true;
        startY = event.clientY;
        startTranslate = Number.parseFloat(menu.style.transform?.match(/translateY\(([-\d.]+)px\)/)?.[1] || '0');
        handle.setPointerCapture(event.pointerId);
    });

    handle.addEventListener('pointermove', (event) => {
        if (!dragging) return;

        const delta = event.clientY - startY;
        const nextTranslate = Math.min(Math.max(delta + startTranslate, -window.innerHeight * 0.75), 120);
        setTranslate(nextTranslate);
    });

    handle.addEventListener('pointerup', (event) => {
        if (!dragging) return;
        dragging = false;
        const delta = event.clientY - startY;

        if (delta < -110) {
            expanded = true;
            menu.classList.add('expanded');
            menu.style.transform = 'translateX(-50%) translateY(0)';
            return;
        }

        if (delta > 110) {
            closeSheet();
            return;
        }

        menu.classList.toggle('expanded', expanded);
        setTranslate(0);
    });

    handle.addEventListener('pointercancel', () => {
        dragging = false;
        menu.classList.toggle('expanded', expanded);
        setTranslate(0);
    });
}

function makeElement(tagName, className, text) {
    const element = document.createElement(tagName);
    if (className) element.className = className;
    if (text !== undefined) element.textContent = text;
    return element;
}

function formatCreatedDate(value) {
    const timestamp = typeof value === 'number' && value < 1e12 ? value * 1000 : value;
    const date = timestamp ? new Date(timestamp) : null;
    if (!date || Number.isNaN(date.getTime())) return 'Creation date unavailable';
    return `Created ${date.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' })}`;
}

function formatLastLogin(value) {
    if (!value && value !== 0) return 'Not logged in yet';

    const timestamp = typeof value === 'number' && value < 1e12 ? value * 1000 : value;
    const date = timestamp ? new Date(timestamp) : null;
    if (!date || Number.isNaN(date.getTime())) return 'Not logged in yet';

    return date.toLocaleString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    }).replace(',', ' •');
}

function formatLastActiveLabel(value) {
    if (!value && value !== 0) return 'Never';

    let timestamp = value;
    if (typeof value === 'number' && value < 1e12) {
        timestamp = value * 1000;
    }

    const parsed = new Date(timestamp);
    if (Number.isNaN(parsed.getTime())) return 'Unknown';

    const diffMinutes = Math.floor((Date.now() - parsed.getTime()) / 60000);
    if (diffMinutes <= 0) return 'Just now';
    if (diffMinutes < 60) return `${diffMinutes}m ago`;

    const diffHours = Math.floor(diffMinutes / 60);
    if (diffHours < 24) return `${diffHours}h ago`;

    const diffDays = Math.floor(diffHours / 24);
    return `${diffDays}d ago`;
}

function isSameCalendarDay(value) {
    if (!value && value !== 0) return false;

    let parsed;
    if (typeof value === 'number') {
        parsed = new Date(value < 1e12 ? value * 1000 : value);
    } else if (typeof value === 'string') {
        const cleaned = value.replace(/•/g, ' ').replace(/\s+/g, ' ').trim();
        parsed = new Date(cleaned);
        if (Number.isNaN(parsed.getTime())) {
            const isoCandidate = cleaned.match(/\d{4}-\d{2}-\d{2}[T\s]\d{2}:\d{2}(:\d{2})?/);
            if (isoCandidate) {
                parsed = new Date(isoCandidate[0]);
            }
        }
    } else {
        parsed = new Date(value);
    }

    if (!parsed || Number.isNaN(parsed.getTime())) return false;

    const now = new Date();
    return parsed.getFullYear() === now.getFullYear()
        && parsed.getMonth() === now.getMonth()
        && parsed.getDate() === now.getDate();
}

function isRecentLogin(value) {
    if (!value && value !== 0) return false;

    let timestamp = value;
    if (typeof value === 'number' && value < 1e12) {
        timestamp = value * 1000;
    }

    const parsed = new Date(timestamp);
    if (Number.isNaN(parsed.getTime())) return false;

    const fiveMinutesMs = 5 * 60 * 1000;
    return (Date.now() - parsed.getTime()) < fiveMinutesMs;
}

function normalizeTaskStatus(task) {
    const rawStatus = String(task?.status || 'pending').trim().toLowerCase().replace(/[\s_]+/g, '-');
    if (['completed', 'complete', 'done'].includes(rawStatus)) return 'completed';
    if (['in-progress', 'ongoing'].includes(rawStatus)) return 'in-progress';
    if (['active', 'open', 'published'].includes(rawStatus)) return 'active';
    if (rawStatus === 'overdue') return 'overdue';

    const dueValue = task?.due_date || task?.due || '';
    if (dueValue && !['completed', 'complete', 'done', 'cancelled', 'canceled'].includes(rawStatus)) {
        const dueDateTime = new Date(`${dueValue}T${task?.due_time || task?.dueTime || '23:59:59'}`);
        if (!Number.isNaN(dueDateTime.getTime()) && dueDateTime < new Date()) return 'overdue';
    }

    return 'pending';
}

function matchesUserEmail(value, userEmail) {
    return typeof value === 'string' && userEmail && value.trim().toLowerCase() === userEmail.trim().toLowerCase();
}

function taskMatchesTeacher(task, user) {
    const teacherEmail = String(user.email || '').trim().toLowerCase();
    const teacherName = String(user.name || '').trim().toLowerCase();
    const taskTeacherEmail = String(task?.teacher_email || task?.teacherEmail || '').trim().toLowerCase();
    const taskTeacherName = String(task?.teacher_name || task?.teacherName || task?.createdBy || '').trim().toLowerCase();
    return !teacherEmail || matchesUserEmail(taskTeacherEmail, teacherEmail) || (teacherName && taskTeacherName && teacherName === taskTeacherName);
}

function taskMatchesStudent(task, user) {
    const userEmail = String(user.email || '').trim().toLowerCase();
    const userName = String(user.name || '').trim().toLowerCase();
    const candidateValues = [
        task?.student_email,
        task?.studentEmail,
        task?.assigned_to,
        task?.assignedTo,
        task?.assignee,
        task?.student_name,
        task?.studentName,
        task?.student_id,
        task?.studentId,
        task?.email,
        task?.user_email,
        task?.userEmail,
        Array.isArray(task?.applications) ? task.applications.map((entry) => entry?.student_email || entry?.studentEmail || entry?.email || entry?.assigned_to || entry?.assignedTo || entry?.student_id || entry?.studentId || entry?.assignee).filter(Boolean) : []
    ].flat();

    const stringValues = candidateValues
        .filter((value) => typeof value === 'string' || typeof value === 'number')
        .map((value) => String(value).trim().toLowerCase())
        .filter(Boolean);

    if (userEmail && stringValues.some((value) => value === userEmail)) return true;
    if (userName && stringValues.some((value) => value === userName)) return true;
    return false;
}

async function loadUserTaskStats(user) {
    try {
        const tasks = await getTaskRecords();

        if (user.role === 'teacher') {
            const teacherTasks = tasks.filter((task) => taskMatchesTeacher(task, user));
            const counts = { posted: teacherTasks.length, pending: 0, completed: 0, overdue: 0 };
            teacherTasks.forEach((task) => {
                const status = normalizeTaskStatus(task);
                if (status === 'completed') counts.completed += 1;
                else if (status === 'overdue') counts.overdue += 1;
                else counts.pending += 1;
            });
            return counts;
        }

        const studentTasks = tasks.filter((task) => taskMatchesStudent(task, user));
        const counts = { assigned: studentTasks.length, pending: 0, completed: 0, overdue: 0 };
        studentTasks.forEach((task) => {
            const status = normalizeTaskStatus(task);
            if (status === 'completed') counts.completed += 1;
            else if (status === 'overdue') counts.overdue += 1;
            else counts.pending += 1;
        });
        return counts;
    } catch (error) {
        console.error(`Unable to load task stats for ${user.role}:`, error);
        return user.role === 'teacher'
            ? { posted: 0, pending: 0, completed: 0, overdue: 0 }
            : { assigned: 0, pending: 0, completed: 0, overdue: 0 };
    }
}

function getTaskRecords() {
    if (!taskRecordsPromise) {
        taskRecordsPromise = (async () => {
            const database = await window.firebaseModule.waitForFirebase();
            const snapshot = await database.ref('tasks').once('value');
            return Object.entries(snapshot.val() || {})
                .filter(([, task]) => task && typeof task === 'object')
                .map(([id, task]) => ({ id, ...task }));
        })();
    }
    return taskRecordsPromise;
}

function createUserRow(user) {
    const row = makeElement('article', 'user-row');
    row.dataset.role = user.role;
    row.dataset.search = `${user.name} ${user.email} ${user.role} ${user.status}`.toLowerCase();

    const nameParts = user.name.split(/\s+/).filter(Boolean);
    const initials = nameParts.length > 1
        ? `${nameParts[0][0]}${nameParts[nameParts.length - 1][0]}`
        : (nameParts[0] || '?').slice(0, 2);
    row.append(makeElement('span', `avatar avatar-${user.role}`, initials.toUpperCase()));

    const details = makeElement('div', 'user-details');
    details.append(makeElement('strong', '', user.name));
    details.append(makeElement('small', '', user.email || 'No email address'));
    details.append(makeElement('small', 'created-date', user.createdDate));
    details.append(makeElement('em', `role ${user.role}`, user.role === 'teacher' ? 'Teacher' : 'Student'));
    row.append(details);

    const statusText = user.isSuspended ? 'Suspended' : user.status === 'Inactive' ? 'Inactive' : 'Active';
    const statusClass = user.isSuspended || user.status === 'Inactive' ? 'inactive' : 'active';
    row.append(makeElement('em', `status ${statusClass}`, statusText));

    const moreButton = makeElement('button', 'more-button', 'More');
    moreButton.type = 'button';
    moreButton.setAttribute('aria-label', `More options for ${user.name}`);
    row.append(moreButton);

    const menu = document.createElement('div');
    menu.className = 'user-actions-menu';
    menu.hidden = true;
    menu.setAttribute('role', 'menu');
    menu.style.opacity = '0';
    menu.style.transform = 'translateX(-50%) translateY(110%)';

    const handle = document.createElement('div');
    handle.className = 'sheet-handle';
    menu.append(handle);
    const taskAction = user.role === 'student'
        ? ['view-submission', 'View Submission']
        : ['view-all-tasks', 'View All Tasks'];
    const menuIcons = {
        'view-details': '<svg viewBox="0 0 24 24"><path d="M16 20v-1.5a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4V20"></path><circle cx="9" cy="7" r="4"></circle><path d="M19 8h2M20 7v2M17 20v-1.5a4 4 0 0 1 3-3.9"></path></svg>',
        'message': '<svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>',
        'view-submission': '<svg viewBox="0 0 24 24"><path d="M6 3h10l4 4v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"></path><path d="M14 3v4h4"></path><path d="M8 12h8M8 16h8"></path></svg>',
        'view-all-tasks': '<svg viewBox="0 0 24 24"><path d="M7 3h10l4 4v12a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"></path><path d="M14 3v4h4"></path><path d="M8 12h8M8 16h8"></path></svg>',
        'suspend-user': '<svg viewBox="0 0 24 24"><path d="M7 7a5 5 0 0 1 10 0v2.5A5 5 0 0 1 17 17H7a5 5 0 0 1 0-10z"></path><path d="M8 12h8"></path></svg>',
        'delete-user': '<svg viewBox="0 0 24 24"><path d="M3 6h18M8 6V4h8v2M18 6l-1 14H7L6 6"></path><path d="M10 11v6M14 11v6"></path></svg>'
    };
    [commonUserActions[0], commonUserActions[1], taskAction, commonUserActions[2], commonUserActions[3]].forEach(([action, label]) => {
        const actionButton = makeElement('button', '');
        actionButton.type = 'button';
        actionButton.dataset.action = action;
        actionButton.setAttribute('role', 'menuitem');

        const mainWrap = document.createElement('span');
        mainWrap.className = 'menu-main';

        const iconWrap = document.createElement('span');
        iconWrap.className = 'action-icon';
        iconWrap.innerHTML = menuIcons[action] || menuIcons['view-details'];

        const labelWrap = document.createElement('span');
        labelWrap.className = 'action-label';
        labelWrap.textContent = action === 'suspend-user' && user.isSuspended ? 'Unsuspend User' : label;

        mainWrap.append(iconWrap, labelWrap);
        actionButton.append(mainWrap);
        actionButton.addEventListener('click', () => {
            closeUserMenus();

            if (action === 'view-details') {
                openUserDetail(user);
                return;
            }

            if (action === 'message') {
                try {
                    sessionStorage.setItem('utosapp.admin.return-user-detail', JSON.stringify(user));
                } catch (error) {
                    console.error('Unable to cache the selected user details:', error);
                }

                const query = new URLSearchParams({
                    userId: user.id,
                    name: user.name,
                    email: user.email,
                    role: user.role
                });
                window.location.href = `/admin-messages?${query}`;
                return;
            }

            if (action === 'view-all-tasks') {
                try {
                    sessionStorage.setItem('utosapp.admin.skip-user-detail-restore', '1');
                    sessionStorage.removeItem('utosapp.admin.return-user-detail');
                } catch (error) {
                    console.error('Unable to reset the selected user detail state before opening task history:', error);
                }

                const query = new URLSearchParams({
                    userId: user.id,
                    name: user.name,
                    email: user.email,
                    role: user.role
                });
                window.location.href = `/admin-user-tasks?${query}`;
                return;
            }

            if (action === 'delete-user') {
                (async () => {
                    const confirmed = await requestDeleteConfirmation(user);
                    if (!confirmed) return;

                    actionButton.disabled = true;
                    actionButton.textContent = 'Deleting...';
                    try {
                        const response = await fetch(`/api/admin/users/${encodeURIComponent(user.role)}/${encodeURIComponent(user.id)}`, {
                            method: 'DELETE'
                        });
                        const result = await response.json().catch(() => ({}));
                        if (!response.ok) throw new Error(result.message || 'Could not delete this user account.');

                        usersByRole[user.role] = usersByRole[user.role].filter((listedUser) => listedUser.id !== user.id);
                        try {
                            const cachedUser = JSON.parse(sessionStorage.getItem('utosapp.admin.return-user-detail') || 'null');
                            if (cachedUser?.id === user.id) sessionStorage.removeItem('utosapp.admin.return-user-detail');
                        } catch (error) {
                            console.error('Unable to clear cached user details:', error);
                        }

                        renderUsers();
                        showToast(`${user.name}'s account was permanently deleted.`);
                    } catch (error) {
                        showToast(error.message);
                    } finally {
                        actionButton.disabled = false;
                        actionButton.innerHTML = '<span class="menu-main"><span class="action-icon"><svg viewBox="0 0 24 24"><path d="M3 6h18M8 6V4h8v2M18 6l-1 14H7L6 6"></path><path d="M10 11v6M14 11v6"></path></svg></span><span class="action-label">Delete User</span></span>';
                    }
                })();
                return;
            }

            if (action === 'suspend-user') {
                toggleUserSuspension(user, actionButton);
                return;
            }

            showToast(`${label} for ${user.name} is ready to configure.`);
        });
        menu.append(actionButton);
    });
    row.append(menu);
    setupBottomSheetGestures(menu);

    moreButton.addEventListener('click', (event) => {
        event.stopPropagation();
        const shouldOpen = menu.hidden;
        closeUserMenus();
        if (shouldOpen) {
            const backdrop = document.querySelector('.user-menu-backdrop');
            if (backdrop) {
                backdrop.remove();
            }
            menu.hidden = false;
            setUserMenuScrollLock(false);
            positionUserMenu(row, menu);
        }
    });

    row.addEventListener('click', (event) => {
        if (event.target.closest('.more-button') || event.target.closest('.user-actions-menu') || event.target.closest('button')) {
            return;
        }
        openUserDetail(user);
    });

    return row;
}

function normalizeUsers(role, snapshot) {
    const records = snapshot.val() || {};
    return Object.entries(records)
        .filter(([, record]) => record && typeof record === 'object')
        .map(([id, record]) => {
            const firstName = record.first_name || record.firstName || '';
            const middleName = record.middle_name || record.middleName || '';
            const lastName = record.last_name || record.lastName || '';
            const displayMiddleName = role === 'student' && middleName.trim().length === 1
                ? middleName.trim().toUpperCase()
                : middleName;
            const name = record.full_name || record.name || [firstName, displayMiddleName, lastName].filter(Boolean).join(' ') || record.email || `${role} ${id}`;
            const rawStatus = record.status ?? record.account_status ?? record.is_active;
            const inactiveStatuses = ['inactive', 'disabled', 'suspended', 'false', '0'];
            const isSuspended = record.is_suspended === true || record.isSuspended === true || String(rawStatus).toLowerCase() === 'suspended';
            const lastLogin = record.last_login || record.lastLogin || record.last_seen || record.lastSeen || null;
            const isRecent = isRecentLogin(lastLogin);
            const isInactiveRecord = isSuspended || rawStatus === false || inactiveStatuses.includes(String(rawStatus).toLowerCase());
            const status = isInactiveRecord || !isRecent ? 'Inactive' : 'Active';
            const createdDate = formatCreatedDate(record.created_at || record.createdAt);
            const lastActiveLabel = formatLastActiveLabel(lastLogin);
            return { id, role, name: String(name).trim(), email: String(record.email || ''), status, isSuspended, createdDate, lastLogin, lastActiveLabel };
        });
}

function openUserDetail(user) {
    document.body.style.overflow = 'hidden';
    createUserDetailSheet(user);
}

function closeUserDetailBackdrop(backdrop) {
    backdrop.remove();
    document.body.style.overflow = '';
}

function restoreRequestedUserDetail() {
    const skipRestoreFlag = sessionStorage.getItem('utosapp.admin.skip-user-detail-restore');
    if (skipRestoreFlag === '1') {
        try {
            sessionStorage.removeItem('utosapp.admin.skip-user-detail-restore');
            sessionStorage.removeItem('utosapp.admin.return-user-detail');
        } catch (error) {
            console.error('Unable to clear skip-user-detail restore state:', error);
        }
        requestedUserDetailOpened = true;
        return;
    }

    if (!requestedUserId) return;

    try {
        const cachedUser = JSON.parse(sessionStorage.getItem('utosapp.admin.return-user-detail') || 'null');
        if (!cachedUser || cachedUser.id !== requestedUserId || !['teacher', 'student'].includes(cachedUser.role)) return;

        sessionStorage.removeItem('utosapp.admin.return-user-detail');
        requestedUserDetailOpened = true;
        openUserDetail(cachedUser);
    } catch (error) {
        console.error('Unable to restore the selected user details:', error);
    }
}

function requestDeleteConfirmation(user) {
    const dialog = document.createElement('dialog');
    dialog.className = 'delete-confirm-notice';
    dialog.setAttribute('role', 'alertdialog');
    dialog.setAttribute('aria-labelledby', 'deleteConfirmTitle');
    dialog.setAttribute('aria-describedby', 'deleteConfirmMessage');
    dialog.innerHTML = `
        <div class="delete-confirm-content">
            <span class="delete-confirm-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24"><path d="M12 3 2.8 20h18.4L12 3Z"></path><path d="M12 9v4m0 3h.01"></path></svg>
            </span>
            <div class="delete-confirm-copy">
                <h2 id="deleteConfirmTitle">Delete this account?</h2>
                <p id="deleteConfirmMessage"><strong data-delete-user-name></strong>'s account will be permanently deleted.</p>
                <small>This action cannot be undone.</small>
            </div>
            <div class="delete-confirm-actions">
                <button type="button" class="delete-confirm-cancel">Cancel</button>
                <button type="button" class="delete-confirm-submit">Delete permanently</button>
            </div>
        </div>
    `;
    dialog.querySelector('[data-delete-user-name]').textContent = user.name;
    document.body.append(dialog);

    return new Promise((resolve) => {
        let resolved = false;
        const finish = (confirmed) => {
            if (resolved) return;
            resolved = true;
            if (dialog.open) dialog.close();
            dialog.remove();
            resolve(confirmed);
        };

        dialog.querySelector('.delete-confirm-cancel').addEventListener('click', () => finish(false));
        dialog.querySelector('.delete-confirm-submit').addEventListener('click', () => finish(true));
        dialog.addEventListener('cancel', (event) => {
            event.preventDefault();
            finish(false);
        });
        dialog.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                event.stopPropagation();
                finish(false);
            }
        });
        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) finish(false);
        });
        dialog.showModal();
        dialog.querySelector('.delete-confirm-cancel').focus();
    });
}

function requestSuspensionConfirmation(user, isSuspending) {
    const action = isSuspending ? 'Suspend' : 'Unsuspend';
    const dialog = document.createElement('dialog');
    dialog.className = 'delete-confirm-notice suspension-confirm-notice';
    dialog.setAttribute('role', 'alertdialog');
    dialog.setAttribute('aria-labelledby', 'suspensionConfirmTitle');
    dialog.setAttribute('aria-describedby', 'suspensionConfirmMessage');
    dialog.innerHTML = `
        <div class="delete-confirm-content">
            <span class="delete-confirm-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24"><path d="M12 3 4 6v5c0 5 3.4 8.2 8 10 4.6-1.8 8-5 8-10V6l-8-3Z"></path><path d="m9 12 2 2 4-4"></path></svg>
            </span>
            <div class="delete-confirm-copy">
                <h2 id="suspensionConfirmTitle">${action} this account?</h2>
                <p id="suspensionConfirmMessage"><strong data-suspension-user-name></strong>'s account will ${isSuspending ? 'be suspended and they will no longer be able to sign in' : 'be reactivated and they will be able to sign in again'}.</p>
            </div>
            <div class="delete-confirm-actions">
                <button type="button" class="delete-confirm-cancel">Cancel</button>
                <button type="button" class="delete-confirm-submit">${action} user</button>
            </div>
        </div>
    `;
    dialog.querySelector('[data-suspension-user-name]').textContent = user.name;
    document.body.append(dialog);

    return new Promise((resolve) => {
        let resolved = false;
        const finish = (confirmed) => {
            if (resolved) return;
            resolved = true;
            if (dialog.open) dialog.close();
            dialog.remove();
            resolve(confirmed);
        };
        dialog.querySelector('.delete-confirm-cancel').addEventListener('click', () => finish(false));
        dialog.querySelector('.delete-confirm-submit').addEventListener('click', () => finish(true));
        dialog.addEventListener('cancel', (event) => {
            event.preventDefault();
            finish(false);
        });
        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) finish(false);
        });
        dialog.showModal();
        dialog.querySelector('.delete-confirm-cancel').focus();
    });
}

async function toggleUserSuspension(user, button) {
    const isSuspending = !user.isSuspended;
    if (!await requestSuspensionConfirmation(user, isSuspending)) return;

    button.disabled = true;
    const originalLabel = button.textContent.trim();
    button.textContent = isSuspending ? 'Suspending...' : 'Unsuspending...';
    try {
        const response = await fetch(`/api/admin/users/${encodeURIComponent(user.role)}/${encodeURIComponent(user.id)}/suspension`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ suspended: isSuspending })
        });
        const result = await response.json().catch(() => ({}));
        if (!response.ok) {
            if (response.status === 401 || response.status === 403) {
                showToast('Admin session required. Please log in again.');
                setTimeout(() => {
                    window.location.href = '/login';
                }, 900);
                return;
            }
            throw new Error(result.message || 'Could not update this account.');
        }

        user.isSuspended = isSuspending;
        renderUsers();
        document.querySelectorAll('.user-detail-backdrop').forEach(closeUserDetailBackdrop);
        showToast(`${user.name}'s account was ${isSuspending ? 'suspended' : 'reactivated'}.`);
    } catch (error) {
        showToast(error.message);
        button.disabled = false;
        button.textContent = originalLabel;
    }
}

function createUserDetailSheet(user) {
    const existing = document.querySelector('.user-detail-backdrop');
    if (existing) existing.remove();

    const backdrop = document.createElement('div');
    backdrop.className = 'user-detail-backdrop show';
    backdrop.setAttribute('aria-hidden', 'false');

    const sheet = document.createElement('aside');
    sheet.className = 'user-detail-sheet';
    sheet.setAttribute('role', 'dialog');
    sheet.setAttribute('aria-modal', 'true');
    sheet.setAttribute('aria-labelledby', 'userDetailTitle');

    const roleLabel = user.role === 'teacher' ? 'Teacher' : 'Student';
    const statusText = user.isSuspended ? 'Suspended' : user.status === 'Inactive' ? 'Inactive' : 'Active';
    const statusClass = user.isSuspended || user.status === 'Inactive' ? 'inactive' : 'active';
    const lastLoginText = formatLastLogin(user.lastLogin || user.last_login || user.last_seen || null);
    const lastActiveLabel = formatLastActiveLabel(user.lastLogin || user.last_login || user.last_seen || null);
    const nameParts = user.name.split(/\s+/).filter(Boolean);
    const initials = nameParts.length > 1
        ? `${nameParts[0][0]}${nameParts[nameParts.length - 1][0]}`
        : (nameParts[0] || '?').slice(0, 2);

    sheet.innerHTML = `
        <div class="user-detail-header">
            <button class="detail-back-button" type="button" aria-label="Close user details">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 18 9 12l6-6"></path></svg>
            </button>
            <div class="detail-header-text">
                <h2 id="userDetailTitle">User Details</h2>
            </div>
        </div>
        <div class="user-detail-summary">
            <div class="detail-avatar avatar-${user.role}">${initials.toUpperCase()}</div>
            <div class="detail-summary-main">
                <h3>${user.name}</h3>
                <div class="detail-role-row">
                    <span class="detail-role-badge">${roleLabel}</span>
                    <span class="detail-status ${statusClass}"><i></i>${statusText}</span>
                </div>
            </div>
        </div>
        <div class="detail-meta-row">
            <div class="detail-meta-item"><svg viewBox="0 0 24 24"><path d="M4 6h16v12H4z"></path><path d="M4 8l8 6 8-6"></path></svg><span>${user.email || 'No email address'}</span></div>
            <div class="detail-meta-item"><svg viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2A19.86 19.86 0 0 1 3.08 4.18 2 2 0 0 1 5.06 2h3a2 2 0 0 1 2 1.72c.13.95.35 1.88.65 2.77a2 2 0 0 1-.45 2.11L9 9.91a16 16 0 0 0 6.09 6.09l1.31-1.31a2 2 0 0 1 2.11-.45c.89.3 1.82.52 2.77.65A2 2 0 0 1 22 16.92z"></path></svg><span>${user.phone || '+63 912 345 6789'}</span></div>
        </div>
        <div class="detail-stats-grid">
            ${user.role === 'teacher' ? `
                <div class="detail-stat-card" data-stat-key="posted"><span>0</span><small>Posted Tasks</small></div>
                <div class="detail-stat-card" data-stat-key="pending"><span>0</span><small>Pending Tasks</small></div>
                <div class="detail-stat-card" data-stat-key="completed"><span>0</span><small>Completed Tasks</small></div>
                <div class="detail-stat-card" data-stat-key="overdue"><span>0</span><small>Overdue Tasks</small></div>
            ` : `
                <div class="detail-stat-card" data-stat-key="assigned"><span>0</span><small>Assigned Tasks</small></div>
                <div class="detail-stat-card" data-stat-key="pending"><span>0</span><small>Pending Tasks</small></div>
                <div class="detail-stat-card" data-stat-key="completed"><span>0</span><small>Completed Tasks</small></div>
                <div class="detail-stat-card" data-stat-key="overdue"><span>0</span><small>Overdue Tasks</small></div>
            `}
        </div>
        <div class="detail-section open">
            <button class="detail-section-toggle" type="button" aria-expanded="true">
                <span class="detail-section-icon"><svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-3-3.87"></path><circle cx="9" cy="7" r="4"></circle><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg></span>
                <span>Personal Information</span>
                <svg class="caret" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"></path></svg>
            </button>
            <div class="detail-section-body">
                <div class="detail-row"><span>Full Name</span><strong>${user.name}</strong></div>
                <div class="detail-row email-detail-row"><span>Email Address</span><strong>${user.email || 'No email address'}</strong></div>
                <div class="detail-row"><span>Phone Number</span><strong>${user.phone || '+63 912 345 6789'}</strong></div>
                <div class="detail-row"><span>Role</span><strong>${roleLabel}</strong></div>
                <div class="detail-row"><span>Created</span><strong>${user.createdDate ? user.createdDate.replace(/^Created\s+/i, '') : 'Unavailable'}</strong></div>
            </div>
        </div>
        <div class="detail-section">
            <button class="detail-section-toggle" type="button" aria-expanded="false">
                <span class="detail-section-icon"><svg viewBox="0 0 24 24"><path d="M4 7h16"></path><path d="M4 12h16"></path><path d="M4 17h10"></path></svg></span>
                <span>Account Information</span>
                <svg class="caret" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"></path></svg>
            </button>
            <div class="detail-section-body" hidden>
                <div class="detail-row"><span>Username</span><strong>${user.email ? user.email.split('@')[0] : 'user'}</strong></div>
                <div class="detail-row"><span>Last Login</span><strong>${lastLoginText}</strong></div>
            </div>
        </div>
        <div class="detail-summary-card ${statusClass === 'active' ? 'is-active' : 'is-inactive'}">
            <div class="summary-inline"><span class="summary-dot"></span><strong>${user.isSuspended ? 'Suspended' : statusText === 'Active' ? 'Active' : `Inactive • ${lastActiveLabel}`}</strong></div>
        </div>
        <div class="detail-action-list">
            <button class="detail-action-button" data-action="message" type="button">Message</button>
            <button class="detail-action-button" data-action="view-all-tasks" type="button">View All Tasks</button>
        </div>
        <div class="detail-danger-actions">
            <button type="button" class="danger-button suspend" data-action="suspend-user">${user.isSuspended ? 'Unsuspend User' : 'Suspend User'}</button>
            <button type="button" class="danger-button delete" data-action="delete-user">Delete User</button>
        </div>
    `;

    backdrop.append(sheet);
    document.body.append(backdrop);

    const animateTaskStat = (valueNode, target) => {
        const targetValue = Number(target);
        if (!Number.isFinite(targetValue) || targetValue <= 0 || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            valueNode.textContent = String(Number.isFinite(targetValue) && targetValue > 0 ? targetValue : 0);
            return;
        }

        const duration = 850;
        let animationStart;
        let previousValue = -1;
        const updateValue = (currentTime) => {
            if (animationStart === undefined) animationStart = currentTime;
            const progress = Math.min((currentTime - animationStart) / duration, 1);
            const easedProgress = 1 - ((1 - progress) ** 3);
            const currentValue = Math.round(targetValue * easedProgress);
            if (currentValue !== previousValue) {
                valueNode.textContent = String(currentValue);
                previousValue = currentValue;
            }
            if (progress < 1) requestAnimationFrame(updateValue);
        };

        valueNode.textContent = '0';
        requestAnimationFrame(updateValue);
    };

    const applyTaskStats = (stats) => {
        const cardMap = new Map();
        sheet.querySelectorAll('[data-stat-key]').forEach((card) => {
            cardMap.set(card.dataset.statKey, card.querySelector('span'));
        });

        const keys = user.role === 'teacher'
            ? ['posted', 'pending', 'completed', 'overdue']
            : ['assigned', 'pending', 'completed', 'overdue'];

        const animatedStats = [];
        keys.forEach((key) => {
            const valueNode = cardMap.get(key);
            if (valueNode) {
                animateTaskStat(valueNode, stats[key] ?? 0);
            }
        });
    };

    loadUserTaskStats(user).then((taskStats) => applyTaskStats(taskStats));

    sheet.querySelector('.detail-back-button').addEventListener('click', () => {
        closeUserDetailBackdrop(backdrop);
    });
    sheet.querySelectorAll('.detail-section-toggle').forEach((toggle) => {
        toggle.addEventListener('click', () => {
            const section = toggle.closest('.detail-section');
            const body = section.querySelector('.detail-section-body');
            const isOpen = section.classList.contains('open');
            section.classList.toggle('open', !isOpen);
            toggle.setAttribute('aria-expanded', String(!isOpen));
            body.hidden = isOpen;
        });
    });

    const detailButtons = sheet.querySelectorAll('.detail-action-button, .danger-button');
    detailButtons.forEach((button) => {
        button.addEventListener('click', async () => {
            if (button.dataset.action === 'message') {
                try {
                    sessionStorage.setItem('utosapp.admin.return-user-detail', JSON.stringify(user));
                } catch (error) {
                    console.error('Unable to cache the selected user details:', error);
                }
                const query = new URLSearchParams({
                    userId: user.id,
                    name: user.name,
                    email: user.email,
                    role: user.role
                });
                window.location.href = `/admin-messages?${query}`;
                return;
            }
            if (button.dataset.action === 'view-all-tasks') {
                try {
                    sessionStorage.setItem('utosapp.admin.return-user-detail', JSON.stringify(user));
                } catch (error) {
                    console.error('Unable to cache the selected user details:', error);
                }
                const query = new URLSearchParams({
                    userId: user.id,
                    name: user.name,
                    email: user.email,
                    role: user.role
                });
                window.location.href = `/admin-user-tasks?${query}`;
                return;
            }
            if (button.dataset.action === 'delete-user') {
                const confirmed = await requestDeleteConfirmation(user);
                if (!confirmed) return;

                button.disabled = true;
                button.textContent = 'Deleting...';
                try {
                    const response = await fetch(`/api/admin/users/${encodeURIComponent(user.role)}/${encodeURIComponent(user.id)}`, {
                        method: 'DELETE'
                    });
                    const result = await response.json().catch(() => ({}));
                    if (!response.ok) throw new Error(result.message || 'Could not delete this user account.');

                    usersByRole[user.role] = usersByRole[user.role].filter((listedUser) => listedUser.id !== user.id);
                    try {
                        const cachedUser = JSON.parse(sessionStorage.getItem('utosapp.admin.return-user-detail') || 'null');
                        if (cachedUser?.id === user.id) sessionStorage.removeItem('utosapp.admin.return-user-detail');
                    } catch (error) {
                        console.error('Unable to clear cached user details:', error);
                    }

                    closeUserDetailBackdrop(backdrop);
                    renderUsers();
                    showToast(`${user.name}'s account was permanently deleted.`);
                } catch (error) {
                    showToast(error.message);
                } finally {
                    button.disabled = false;
                    button.textContent = 'Delete User';
                }
                return;
            }
            if (button.dataset.action === 'suspend-user') {
                await toggleUserSuspension(user, button);
                return;
            }
            showToast(button.textContent.trim());
        });
    });

    backdrop.addEventListener('click', (event) => {
        if (event.target === backdrop) {
            closeUserDetailBackdrop(backdrop);
        }
    });

    const closeButton = sheet.querySelector('.detail-back-button');
    closeButton.focus();
    requestAnimationFrame(() => {
        sheet.classList.add('visible');
    });
}

function renderUserSkeletons() {
    const userList = document.getElementById('userList');
    const fragment = document.createDocumentFragment();
    for (let index = 0; index < 4; index += 1) {
        const row = makeElement('article', 'user-row user-skeleton');
        row.setAttribute('aria-hidden', 'true');
        row.innerHTML = `
            <span class="avatar skeleton-block"></span>
            <div class="user-details">
                <span class="skeleton-block skeleton-name"></span>
                <span class="skeleton-block skeleton-email"></span>
                <span class="skeleton-block skeleton-date"></span>
                <span class="skeleton-block skeleton-role"></span>
            </div>
            <span class="skeleton-block skeleton-status"></span>
            <span class="skeleton-block skeleton-more"></span>
        `;
        fragment.append(row);
    }
    userList.setAttribute('aria-busy', 'true');
    userList.replaceChildren(fragment);
}

function renderUsers() {
    const users = [...usersByRole.teacher, ...usersByRole.student];
    const userList = document.getElementById('userList');
    const fragment = document.createDocumentFragment();

    users.forEach((user, index) => {
        const userRow = createUserRow(user);
        userRow.style.setProperty('--row-index', index);
        fragment.append(userRow);
    });
    userList.replaceChildren(fragment);
    userList.setAttribute('aria-busy', 'false');

    const totalUserCount = document.getElementById('totalUserCount');
    const teacherCount = document.getElementById('teacherCount');
    const studentCount = document.getElementById('studentCount');

    totalUserCount.textContent = String(users.length);
    teacherCount.textContent = String(usersByRole.teacher.length);
    studentCount.textContent = String(usersByRole.student.length);

    window.animateCountUps?.([totalUserCount, teacherCount, studentCount]);

    if (loadedRoles.size === 2) {
        try {
            localStorage.setItem('utosapp.admin.users.counts.v1', JSON.stringify({
                students: usersByRole.student.length,
                teachers: usersByRole.teacher.length,
                updatedAt: Date.now(),
            }));
        } catch (error) {
            console.error('Unable to share Admin Users counts with the dashboard:', error);
        }

        const skipRestoreFlag = sessionStorage.getItem('utosapp.admin.skip-user-detail-restore');
        if (skipRestoreFlag === '1') {
            try {
                sessionStorage.removeItem('utosapp.admin.skip-user-detail-restore');
                sessionStorage.removeItem('utosapp.admin.return-user-detail');
            } catch (error) {
                console.error('Unable to clear skip-user-detail restore state:', error);
            }
            requestedUserDetailOpened = true;
            return;
        }

        if (requestedUserId && !requestedUserDetailOpened) {
            const requestedUser = users.find((user) => user.id === requestedUserId);
            if (requestedUser) {
                requestedUserDetailOpened = true;
                openUserDetail(requestedUser);
            }
        }
    }
    filterUsers();
}

async function loadUsers() {
    try {
        const database = await window.firebaseModule.waitForFirebase();
        ['teacher', 'student'].forEach((role) => {
            const collection = role === 'teacher' ? 'teachers' : 'students';
            database.ref(collection).on('value', (snapshot) => {
                if (!loadedRoles.has(role)) {
                    loadedRoles.add(role);
                    loadingRoles -= 1;
                }
                usersByRole[role] = normalizeUsers(role, snapshot);
                if (loadedRoles.size === 2) renderUsers();
            }, (error) => {
                console.error(`Failed to load ${collection} from Realtime Database:`, error);
                databaseLoadFailed = true;
                if (!loadedRoles.has(role)) {
                    loadedRoles.add(role);
                    loadingRoles -= 1;
                }
                if (loadedRoles.size === 2) renderUsers();
            });
        });
    } catch (error) {
        console.error('Failed to connect to Firebase Realtime Database:', error);
        databaseLoadFailed = true;
        loadingRoles = 0;
        document.getElementById('userList').replaceChildren();
        document.getElementById('userList').setAttribute('aria-busy', 'false');
        filterUsers();
    }
}

document.addEventListener('click', (event) => {
    const target = event.target;
    if (target instanceof Element && (target.closest('.more-button') || target.closest('.user-actions-menu'))) {
        return;
    }
    closeUserMenus();
});
document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        const detailBackdrop = document.querySelector('.user-detail-backdrop');
        if (detailBackdrop) {
            closeUserDetailBackdrop(detailBackdrop);
            return;
        }
        closeUserMenus();
    }
});

document.getElementById('userSearch').addEventListener('input', filterUsers);
const roleFilter = document.getElementById('roleFilter');
const requestedRole = new URLSearchParams(window.location.search).get('role');
if (requestedRole === 'teacher' || requestedRole === 'student') roleFilter.value = requestedRole;
roleFilter.addEventListener('change', filterUsers);

renderUserSkeletons();
restoreRequestedUserDetail();
getTaskRecords().catch((error) => {
    console.error('Unable to preload task stats:', error);
});
loadUsers();
