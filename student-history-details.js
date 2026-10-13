const elements = {
    back: document.querySelector('.back-button'),
    status: document.getElementById('detailsStatus'),
    skeleton: document.getElementById('detailsSkeleton'),
    content: document.getElementById('detailsContent'),
    error: document.getElementById('detailsError'),
    errorMessage: document.getElementById('detailsErrorMessage'),
    teacherAvatar: document.getElementById('teacherAvatar'),
    teacherName: document.getElementById('teacherName'),
    teacherRole: document.getElementById('teacherRole'),
    teacherMoreButton: document.getElementById('teacherMoreButton'),
    teacherOptions: document.getElementById('teacherOptions'),
    teacherMessage: document.getElementById('teacherMessage'),
    rateTeacherButton: document.getElementById('rateTeacherButton'),
    teacherRating: document.getElementById('teacherRating'),
    teacherRatingFeedback: document.getElementById('teacherRatingFeedback'),
    title: document.getElementById('taskTitle'),
    taskStatus: document.getElementById('taskStatus'),
    description: document.getElementById('taskDescription'),
    facts: document.getElementById('taskFacts'),
    attachmentsSection: document.getElementById('attachmentsSection'),
    attachmentLabel: document.getElementById('attachmentLabel'),
    attachmentGallery: document.getElementById('attachmentGallery'),
    attachmentList: document.getElementById('attachmentList'),
    attachmentViewer: document.getElementById('attachmentViewer'),
    attachmentViewerImage: document.getElementById('attachmentViewerImage'),
    closeAttachmentViewer: document.getElementById('closeAttachmentViewer'),
    timeline: document.getElementById('activityTimeline')
};

let previewImages = [];

const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (character) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;'
}[character]));

const openAttachmentViewer = (index) => {
    const attachment = previewImages[index];
    if (!attachment) return;
    elements.attachmentViewerImage.src = attachment.url;
    elements.attachmentViewerImage.alt = attachment.name;
    elements.attachmentViewer.showModal();
};

elements.closeAttachmentViewer.addEventListener('click', () => elements.attachmentViewer.close());
elements.attachmentViewer.addEventListener('click', (event) => {
    if (event.target === elements.attachmentViewer) elements.attachmentViewer.close();
});
elements.attachmentGallery.addEventListener('click', (event) => {
    const previewButton = event.target.closest('[data-preview-index]');
    if (previewButton) openAttachmentViewer(Number(previewButton.dataset.previewIndex));
});

const statusFor = (task) => {
    if (task.is_completed || task.completed || task.status === 'completed') return { key: 'completed', label: 'Completed' };
    if (task.status === 'cancelled' || task.status === 'canceled') return { key: 'cancelled', label: 'Cancelled' };
    const due = task.due_date || task.due;
    const deadline = due ? new Date(`${String(due).replace(/^Due\s+/i, '')} ${task.due_time || '23:59'}`) : null;
    if (deadline && !Number.isNaN(deadline.getTime()) && deadline < new Date()) return { key: 'overdue', label: 'Overdue' };
    return { key: 'pending', label: 'Pending' };
};

const formatDate = (value, includeTime = false) => {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return String(value);
    return new Intl.DateTimeFormat('en-US', {
        month: 'short',
        day: '2-digit',
        year: 'numeric',
        ...(includeTime ? { hour: 'numeric', minute: '2-digit' } : {})
    }).format(date);
};

const formatTaskTime = (value) => {
    if (!value) return 'Not set';
    const match = String(value).trim().match(/^(\d{1,2}):(\d{2})(?::\d{2})?$/);
    if (!match) return String(value);
    const hour = Number(match[1]);
    return `${hour % 12 || 12}:${match[2]} ${hour >= 12 ? 'PM' : 'AM'}`;
};

const formatAmount = (value) => {
    if (value === null || value === undefined || value === '') return '';
    const amount = Number(value);
    return Number.isFinite(amount)
        ? `PHP ${new Intl.NumberFormat('en-PH', { maximumFractionDigits: 2 }).format(amount)}`
        : '';
};

const taskAttachments = (task) => (Array.isArray(task.attachments) ? task.attachments : [task.attachment])
    .filter(Boolean)
    .map((attachment) => typeof attachment === 'string'
        ? { name: attachment.split('/').pop() || 'Task attachment', url: attachment }
        : {
            name: attachment.name || attachment.file_name || attachment.filename || 'Task attachment',
            url: attachment.url || attachment.download_url || attachment.data || attachment.file_url || ''
        })
    .filter((attachment) => attachment.url);

const isImageAttachment = (attachment) =>
        /^data:image\//i.test(attachment.url) || /\.(?:jpe?g|png|gif|webp|svg|avif)(?:[?#]|$)/i.test(attachment.url);

const renderTask = (task) => {
    const teacher = String(task.teacher_name || task.teacherName || task.createdBy || 'Teacher').trim();
    const status = statusFor(task);
    const initials = teacher.split(/\s+/).filter(Boolean).slice(0, 2).map((part) => part[0]).join('').toUpperCase();
    const avatar = task.teacher_photo || task.teacherPhoto;
    elements.teacherAvatar.innerHTML = avatar
        ? `<img src="${escapeHtml(avatar)}" alt="">`
        : escapeHtml(initials || 'T');
    const teacherEmail = String(task.teacher_email || task.teacherEmail || '').trim();
    if (teacherEmail) {
        const profileUrl = new URL('/student-teacher-profile', window.location.origin);
        profileUrl.searchParams.set('email', teacherEmail);
        profileUrl.searchParams.set('taskId', String(task.id || ''));
        elements.teacherAvatar.href = `${profileUrl.pathname}${profileUrl.search}`;
        elements.teacherAvatar.removeAttribute('aria-disabled');
        elements.teacherAvatar.tabIndex = 0;
    } else {
        elements.teacherAvatar.removeAttribute('href');
        elements.teacherAvatar.setAttribute('aria-disabled', 'true');
        elements.teacherAvatar.tabIndex = -1;
    }
    elements.teacherName.textContent = teacher;
    elements.teacherRole.textContent = teacherEmail || 'Teacher email unavailable';
    elements.teacherMessage.hidden = !teacherEmail;
    if (teacherEmail) elements.teacherMessage.href = `mailto:${encodeURIComponent(teacherEmail)}`;
    elements.title.textContent = task.title || task.task_title || task.taskName || 'Untitled task';
    elements.taskStatus.textContent = status.label;
    elements.taskStatus.className = `status-pill ${status.key}`;
    elements.description.textContent = task.description || 'No additional instructions were provided for this task.';
    const dueDate = task.due_date || task.due;
    const dueTime = task.due_time || task.dueTime;
    const urgent = [task.urgency, task.priority, task.status, task.is_urgent, task.isUrgent, task.urgent]
        .some((value) => value === true || value === 1 || value === '1'
            || ['urgent', 'high', 'high_priority'].includes(String(value ?? '').trim().toLowerCase()));
    const paymentMethods = {
        cash: 'Cash',
        e_wallet_bank_transfer: 'E-Wallet / Bank Transfer'
    };
    const paymentMethod = String(task.payment_method || task.paymentMethod || '').trim();
    const facts = [
        dueDate ? `<span><b>Due</b><strong>${escapeHtml(formatDate(dueDate))}</strong></span>` : '',
        `<span><b>Due Time</b><strong>${escapeHtml(formatTaskTime(dueTime))}</strong></span>`,
        `<span><b>Urgent</b><strong>${urgent ? 'Yes' : 'No'}</strong></span>`,
        task.room ? `<span><b>Location</b><strong>${escapeHtml(task.room)}</strong></span>` : '',
        formatAmount(task.amount ?? task.budget ?? task.total_amount)
            ? `<span><b>Amount</b><strong>${escapeHtml(formatAmount(task.amount ?? task.budget ?? task.total_amount))}</strong></span>`
            : '',
        `<span><b>Payment Method</b><strong>${escapeHtml(paymentMethods[paymentMethod] || paymentMethod || 'Not specified')}</strong></span>`
    ].filter(Boolean);
    elements.facts.innerHTML = facts.join('');
    const attachments = taskAttachments(task);
    const imageAttachments = attachments.filter(isImageAttachment);
    const fileAttachments = attachments.filter((attachment) => !isImageAttachment(attachment));
    previewImages = imageAttachments;
    const application = task.student_application;
    const hasApplied = task.has_applied === true
        || Boolean(application?.created_at || application?.createdAt
            || application?.applied_at || application?.appliedAt || application?.confirmations);
    elements.attachmentLabel.hidden = attachments.length === 0;
    elements.attachmentGallery.innerHTML = imageAttachments.map((attachment, index) => {
        const url = escapeHtml(attachment.url);
        const name = escapeHtml(attachment.name);
        if (!hasApplied) {
            return `<div class="attachment-image-item attachment-image-item-blurred" role="img" aria-label="Image blurred because you did not apply for this task"><img src="${url}" alt="" loading="lazy"><span>Apply to view</span></div>`;
        }
        return `<button class="attachment-image-item" type="button" data-preview-index="${index}" aria-label="View image: ${name}" title="${name}"><img src="${url}" alt="" loading="lazy"></button>`;
    }).join('');
    elements.attachmentList.innerHTML = fileAttachments.map((attachment) => {
        const url = escapeHtml(attachment.url);
        const name = escapeHtml(attachment.name);
        return `<a class="attachment-item" href="${url}" target="_blank" rel="noopener noreferrer"><span class="file-mark">FILE</span><span class="attachment-name">${name}</span><span aria-hidden="true">↗</span></a>`;
    }).join('');

    const appliedAt = application?.created_at || application?.createdAt
        || application?.applied_at || application?.appliedAt;
    const events = [
        { label: 'Task posted', value: task.created_at || task.createdAt },
        { label: 'You applied', value: appliedAt },
        { label: status.label, value: task.completed_at || task.completedAt || task.cancelled_at || task.updated_at || task.updatedAt }
    ].filter((event) => event.value).filter((event, index, list) =>
        list.findIndex((candidate) => candidate.label === event.label && candidate.value === event.value) === index
    );
    elements.timeline.innerHTML = events.length
        ? events.map((event) => `<li><span class="timeline-dot" aria-hidden="true"></span><strong>${escapeHtml(event.label)}</strong><time>${escapeHtml(formatDate(event.value, true))}</time></li>`).join('')
        : `<li><span class="timeline-dot" aria-hidden="true"></span><strong>${escapeHtml(status.label)}</strong><time>Task status</time></li>`;

    elements.status.textContent = '';
    elements.status.hidden = true;
    elements.skeleton.hidden = true;
    elements.content.hidden = false;
};

document.querySelectorAll('.teacher-reaction[data-reaction]').forEach((button) => {
    button.addEventListener('click', () => {
        const pressed = button.getAttribute('aria-pressed') === 'true';
        button.setAttribute('aria-pressed', String(!pressed));
    });
});

elements.rateTeacherButton.addEventListener('click', () => {
    const expanded = elements.rateTeacherButton.getAttribute('aria-expanded') === 'true';
    elements.rateTeacherButton.setAttribute('aria-expanded', String(!expanded));
    elements.teacherRating.hidden = expanded;
});

elements.teacherRating.querySelectorAll('[data-rating]').forEach((button) => {
    button.addEventListener('click', () => {
        const rating = Number(button.dataset.rating);
        elements.teacherRating.querySelectorAll('[data-rating]').forEach((star) => {
            star.classList.toggle('selected', Number(star.dataset.rating) <= rating);
            star.setAttribute('aria-pressed', String(Number(star.dataset.rating) === rating));
        });
        elements.teacherRatingFeedback.textContent = `You rated ${rating} out of 5`;
    });
});

elements.teacherMoreButton.addEventListener('click', () => {
    const expanded = elements.teacherMoreButton.getAttribute('aria-expanded') === 'true';
    elements.teacherMoreButton.setAttribute('aria-expanded', String(!expanded));
    elements.teacherOptions.hidden = expanded;
});

document.addEventListener('click', (event) => {
    if (!event.target.closest('.teacher-card')) {
        elements.teacherMoreButton.setAttribute('aria-expanded', 'false');
        elements.teacherOptions.hidden = true;
    }
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && !elements.teacherOptions.hidden) {
        elements.teacherMoreButton.setAttribute('aria-expanded', 'false');
        elements.teacherOptions.hidden = true;
        elements.teacherMoreButton.focus();
    }
});

const showError = (message) => {
    elements.status.textContent = '';
    elements.status.hidden = true;
    elements.skeleton.hidden = true;
    elements.content.hidden = true;
    elements.errorMessage.textContent = message;
    elements.error.hidden = false;
};

const loadTask = async () => {
    const query = new URLSearchParams(window.location.search);
    const taskId = query.get('id');
    if (query.get('returnTo') === 'student-teacher-profile') {
        const profileUrlValue = query.get('profileUrl');
        if (profileUrlValue) {
            try {
                const profileUrl = new URL(profileUrlValue, window.location.origin);
                if (profileUrl.origin === window.location.origin
                    && profileUrl.pathname === '/student-teacher-profile') {
                    elements.back.href = `${profileUrl.pathname}${profileUrl.search}`;
                    elements.back.setAttribute('aria-label', 'Back to Teacher Profile');
                }
            } catch (error) {
                console.error('Invalid teacher profile return URL:', error);
            }
        }
    }
    if (!taskId) {
        showError('This page needs a task ID. Open a task from your History list.');
        return;
    }

    try {
        const response = await fetch('/api/student-tasks');
        if (!response.ok) throw new Error('Could not load task details. Please sign in again and retry.');
        const result = await response.json();
        const task = (result.tasks || []).find((item) => String(item.id) === taskId);
        if (!task) throw new Error('This task is no longer available in your history.');
        renderTask(task);
    } catch (error) {
        showError(error.message || 'Could not load task details. Please try again.');
    }
};

loadTask();
