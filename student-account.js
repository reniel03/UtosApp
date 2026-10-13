const profileKey = 'utosapp_student_profile';
const settingsKey = 'utosapp_student_settings';
const elements = {
    name: document.getElementById('profileName'), email: document.getElementById('profileEmail'), headerEmail: document.getElementById('profileHeaderEmail'), id: document.getElementById('studentId'), address: document.getElementById('studentAddress'), level: document.getElementById('studentLevel'), phone: document.getElementById('studentPhone'), avatar: document.getElementById('profileAvatar'), photo: document.getElementById('profilePhoto'), avatarButton: document.getElementById('avatarButton'), avatarMenu: document.getElementById('avatarMenu'), photoInput: document.getElementById('photoInput'),
    modal: document.getElementById('modalBackdrop'), form: document.getElementById('accountForm'), title: document.getElementById('modalTitle'), eyebrow: document.getElementById('modalEyebrow'), toast: document.getElementById('toast'), logoutConfirm: document.getElementById('logoutConfirm'), cropBackdrop: document.getElementById('cropBackdrop'), cropCanvas: document.getElementById('cropCanvas'), cropStage: document.getElementById('cropStage'), cropZoom: document.getElementById('cropZoom')
};
let profile = JSON.parse(localStorage.getItem(profileKey) || 'null') || {};
let cropImage = null;
let cropScale = 1;
let cropOffsetX = 0;
let cropOffsetY = 0;
let cropDrag = null;
let cropFrameX = 50;
let cropFrameY = 50;
let cropFrameSize = 540;
let activeDetailField = null;

const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[character]));
const fullName = () => [profile.first_name, profile.last_name].filter(Boolean).join(' ') || 'Student';
const showToast = (message) => { elements.toast.textContent = message; elements.toast.classList.add('show'); clearTimeout(showToast.timer); showToast.timer = setTimeout(() => elements.toast.classList.remove('show'), 2400); };

function renderProfile() {
    elements.name.textContent = fullName();
    elements.email.textContent = profile.email || 'Not available';
    elements.headerEmail.textContent = profile.email || 'Not available';
    elements.id.textContent = profile.student_id || profile.studentId || 'Not available';
    elements.address.textContent = profile.address || 'Not available';
    elements.level.textContent = profile.level || profile.year_level || 'Not available';
    elements.phone.textContent = profile.phone || profile.phone_number || 'Not available';
    elements.photo.src = profile.photo || '';
    elements.avatar.style.backgroundImage = '';
    elements.avatar.classList.toggle('has-photo', Boolean(profile.photo));
    elements.avatarMenu.querySelector('[data-avatar-action="remove"]').hidden = !profile.photo;
}

let closeModalTimer;
function openModal(title, eyebrow, html, variant = '') {
    clearTimeout(closeModalTimer);
    elements.title.textContent = title;
    elements.eyebrow.textContent = eyebrow;
    elements.form.innerHTML = html;
    elements.modal.classList.remove('is-closing', 'photo-preview-modal');
    if (variant) elements.modal.classList.add(variant);
    elements.modal.hidden = false;
    document.body.classList.add('account-modal-open');
}
function closeModal() {
    if (elements.modal.hidden || elements.modal.classList.contains('is-closing')) return;
    elements.modal.classList.add('is-closing');
    closeModalTimer = setTimeout(() => {
        elements.modal.hidden = true;
        elements.modal.classList.remove('is-closing', 'photo-preview-modal');
        elements.form.innerHTML = '';
        document.body.classList.remove('account-modal-open');
    }, 240);
}
function closeAvatarMenu() { elements.avatarMenu.hidden = true; }
function openProfilePhoto() { if (!profile.photo) return showToast('No profile picture to view'); openModal('Profile picture', 'PROFILE PHOTO', `<div class="profile-photo-preview"><img src="${escapeHtml(profile.photo)}" alt="Profile picture"></div>`, 'photo-preview-modal'); closeAvatarMenu(); }
function bindCustomSelect() {
    const wrapper = document.querySelector('[data-custom-select]');
    if (!wrapper || wrapper.dataset.bound === 'true') return;
    wrapper.dataset.bound = 'true';
    const trigger = wrapper.querySelector('.custom-select-trigger');
    const menu = wrapper.querySelector('.custom-select-menu');
    const hiddenInput = wrapper.querySelector('input[name="detail_value"]');
    const options = wrapper.querySelectorAll('.custom-select-option');

    const setSelectedValue = (nextValue) => {
        const actualValue = nextValue === 'Select year level' ? '' : nextValue;
        hiddenInput.value = actualValue;
        const currentLabel = wrapper.querySelector('.custom-select-value');
        if (currentLabel) currentLabel.textContent = nextValue;
        options.forEach((option) => {
            const isSelected = option.dataset.value === nextValue;
            option.classList.toggle('is-selected', isSelected);
            option.setAttribute('aria-selected', String(isSelected));
        });
    };

    trigger.addEventListener('click', () => {
        const isOpen = !menu.hidden;
        menu.hidden = isOpen;
        trigger.setAttribute('aria-expanded', String(!isOpen));
    });

    options.forEach((option) => option.addEventListener('click', () => {
        setSelectedValue(option.dataset.value);
        menu.hidden = true;
        trigger.setAttribute('aria-expanded', 'false');
    }));

    document.addEventListener('click', (event) => {
        if (!wrapper.contains(event.target)) {
            menu.hidden = true;
            trigger.setAttribute('aria-expanded', 'false');
        }
    });
}

function openEditableDetail(field, title, value, type = 'text', readOnly = false) { activeDetailField = field; if (field === 'level') {
    const levelOptions = ['Select year level', '1st Year', '2nd Year', '3rd Year', '4th Year'];
    const selectedValue = levelOptions.includes(value) ? value : 'Select year level';
    const optionsHtml = levelOptions.map((option) => `<button type="button" class="custom-select-option${option === selectedValue ? ' is-selected' : ''}" data-value="${option}" ${option === selectedValue ? 'aria-selected="true"' : ''}>${option}</button>`).join('');
    openModal(title, 'PERSONAL INFORMATION', `<label>${title}<div class="custom-select" data-custom-select><button type="button" class="custom-select-trigger" aria-expanded="false"><span class="custom-select-value">${selectedValue}</span><span class="custom-select-chevron">▼</span></button><div class="custom-select-menu" hidden>${optionsHtml}</div><input type="hidden" name="detail_value" value="${selectedValue === 'Select year level' ? '' : escapeHtml(selectedValue)}" required></div></label>${readOnly ? '' : '<button class="save-button" type="submit">Save changes</button>'}`);
    bindCustomSelect();
    return;
}
const phoneAttributes = field === 'phone' ? 'inputmode="numeric" pattern="[0-9]{11}" minlength="11" maxlength="11" title="Enter exactly 11 digits"' : '';
const input = `<input type="${type}" name="detail_value" value="${escapeHtml(value || '')}" ${phoneAttributes} ${readOnly ? 'readonly aria-readonly="true"' : 'required'}>`; openModal(title, 'PERSONAL INFORMATION', `<label>${title}${input}</label>${readOnly ? '' : '<button class="save-button" type="submit">Save changes</button>'}`); }
function openAddressDetails() { openEditableDetail('address', 'Address', profile.address); }
function openLevelDetails() { openEditableDetail('level', 'Level', profile.level || profile.year_level); }
function openPhoneDetails() { openEditableDetail('phone', 'Phone Number', profile.phone || profile.phone_number, 'tel'); }
function openEmailDetails() { openEditableDetail('email', 'Email Address', profile.email, 'email', true); }
 function drawCropPreview() { if (!cropImage) return; const context = elements.cropCanvas.getContext('2d'); const canvasSize = elements.cropCanvas.width; const baseScale = Math.max(canvasSize / cropImage.width, canvasSize / cropImage.height); const imageWidth = cropImage.width * baseScale * cropScale; const imageHeight = cropImage.height * baseScale * cropScale; const imageX = (canvasSize - imageWidth) / 2; const imageY = (canvasSize - imageHeight) / 2; context.clearRect(0, 0, canvasSize, canvasSize); context.fillStyle = '#172333'; context.fillRect(0, 0, canvasSize, canvasSize); context.drawImage(cropImage, imageX, imageY, imageWidth, imageHeight); context.fillStyle = 'rgba(6, 16, 29, 0.38)'; context.fillRect(0, 0, canvasSize, cropFrameY); context.fillRect(0, cropFrameY + cropFrameSize, canvasSize, canvasSize - cropFrameY - cropFrameSize); context.fillRect(0, cropFrameY, cropFrameX, cropFrameSize); context.fillRect(cropFrameX + cropFrameSize, cropFrameY, canvasSize - cropFrameX - cropFrameSize, cropFrameSize); context.save(); context.beginPath(); context.rect(cropFrameX, cropFrameY, cropFrameSize, cropFrameSize); context.clip(); context.strokeStyle = 'rgba(255, 255, 255, 0.8)'; context.lineWidth = 2; context.setLineDash([7, 7]); for (let lineIndex = 1; lineIndex < 3; lineIndex += 1) { const verticalPosition = cropFrameX + cropFrameSize * lineIndex / 3; const horizontalPosition = cropFrameY + cropFrameSize * lineIndex / 3; context.beginPath(); context.moveTo(verticalPosition, cropFrameY); context.lineTo(verticalPosition, cropFrameY + cropFrameSize); context.stroke(); context.beginPath(); context.moveTo(cropFrameX, horizontalPosition); context.lineTo(cropFrameX + cropFrameSize, horizontalPosition); context.stroke(); } context.restore(); context.setLineDash([]); context.strokeStyle = '#fff'; context.lineWidth = 8; context.strokeRect(cropFrameX, cropFrameY, cropFrameSize, cropFrameSize); const handleSize = 26; context.fillStyle = '#fff'; [[cropFrameX, cropFrameY], [cropFrameX + cropFrameSize, cropFrameY], [cropFrameX, cropFrameY + cropFrameSize], [cropFrameX + cropFrameSize, cropFrameY + cropFrameSize]].forEach(([handleX, handleY]) => { context.fillRect(handleX - handleSize / 2, handleY - handleSize / 2, handleSize, handleSize); }); }
function openCropEditor(dataUrl) { cropImage = new Image(); cropImage.onload = () => { cropScale = 1; cropOffsetX = 0; cropOffsetY = 0; cropFrameX = 50; cropFrameY = 50; cropFrameSize = 540; elements.cropZoom.value = '1'; elements.cropBackdrop.hidden = false; drawCropPreview(); }; cropImage.src = dataUrl; }
function closeCropEditor() { elements.cropBackdrop.hidden = true; cropImage = null; cropDrag = null; }
 function confirmCrop() { if (!cropImage) return; const outputCanvas = document.createElement('canvas'); outputCanvas.width = 512; outputCanvas.height = 512; const outputContext = outputCanvas.getContext('2d'); const canvasSize = elements.cropCanvas.width; const baseScale = Math.max(canvasSize / cropImage.width, canvasSize / cropImage.height) * cropScale; const imageWidth = cropImage.width * baseScale; const imageHeight = cropImage.height * baseScale; const imageX = (canvasSize - imageWidth) / 2; const imageY = (canvasSize - imageHeight) / 2; const sourceX = Math.max(0, Math.min(cropImage.width, (cropFrameX - imageX) / baseScale)); const sourceY = Math.max(0, Math.min(cropImage.height, (cropFrameY - imageY) / baseScale)); const sourceSize = Math.min(cropImage.width - sourceX, cropImage.height - sourceY, cropFrameSize / baseScale); outputContext.drawImage(cropImage, sourceX, sourceY, sourceSize, sourceSize, 0, 0, outputCanvas.width, outputCanvas.height); profile.photo = outputCanvas.toDataURL('image/jpeg', 0.9); localStorage.setItem(profileKey, JSON.stringify(profile)); renderProfile(); closeCropEditor(); showToast('Profile picture updated'); }

function openProfileEditor() { activeDetailField = null; openModal('Personal Information', 'PROFILE', `<label>First name<input name="first_name" value="${escapeHtml(profile.first_name)}" readonly required></label><label>Middle name<input name="middle_name" value="${escapeHtml(String(profile.middle_name || '').toUpperCase())}" readonly></label><label>Last name<input name="last_name" value="${escapeHtml(profile.last_name)}" readonly required></label><label>Student ID<input name="student_id" value="${escapeHtml(profile.student_id || profile.studentId)}" readonly></label><label>Course<input name="course" value="${escapeHtml(profile.course)}" readonly></label><label>Email<input type="email" name="email" value="${escapeHtml(profile.email || '')}" readonly></label><label>Username<input type="text" name="username" autocomplete="username" value="${escapeHtml(profile.username || '')}" pattern="[a-z0-9._\\-]{3,30}" minlength="3" maxlength="30" title="Use 3–30 letters, numbers, dots, underscores, or hyphens"><small class="field-help">You can use your username to log in instead of your email.</small></label><button class="save-button" type="submit">Save Profile</button>`); }
function bindPasswordToggles() {
    document.querySelectorAll('[data-password-field]').forEach((field) => {
        const input = field.querySelector('input');
        const toggle = field.querySelector('[data-password-toggle]');
        if (!input || !toggle || toggle.dataset.bound === 'true') return;
        toggle.dataset.bound = 'true';
        toggle.addEventListener('click', () => {
            const isVisible = input.type === 'text';
            input.type = isVisible ? 'password' : 'text';
            toggle.setAttribute('aria-label', isVisible ? 'Show password' : 'Hide password');
            toggle.innerHTML = isVisible
                ? '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"></path><circle cx="12" cy="12" r="2.5"></circle></svg>'
                : '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3l18 18"></path><path d="M10.6 6.2A10.7 10.7 0 0 1 12 6c6.5 0 10 6 10 6a18.8 18.8 0 0 1-3.3 3.8M6.2 6.3C3.5 8.1 2 12 2 12s3.5 6 10 6c1.5 0 2.8-.3 4-.8"></path></svg>';
        });
    });
}

function passwordField(name, label, minlength = '') {
    return `<label>${label}<span class="password-field" data-password-field><input type="password" name="${name}" ${minlength ? `minlength="${minlength}"` : ''} required><button type="button" class="password-toggle" data-password-toggle aria-label="Show password"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"></path><circle cx="12" cy="12" r="2.5"></circle></svg></button></span></label>`;
}

function openPassword() { openModal('Change Password', 'ACCOUNT SECURITY', `${passwordField('current', 'Current password')}${passwordField('password', 'New password', 6)}${passwordField('confirm', 'Confirm password', 6)}<button class="save-button" type="submit">Update password</button>`); bindPasswordToggles(); }
function openNotifications() { const settings = JSON.parse(localStorage.getItem(settingsKey) || '{}'); openModal('Notifications', 'PREFERENCES', `<label class="toggle-row"><span>Task updates<small>Receive updates about new tasks</small></span><input type="checkbox" name="tasks" ${settings.tasks !== false ? 'checked' : ''}></label><label class="toggle-row"><span>Message alerts<small>Notify me about new messages</small></span><input type="checkbox" name="messages" ${settings.messages !== false ? 'checked' : ''}></label><button class="save-button" type="submit">Save settings</button>`); }
function openHelp() { openModal('Help & Support', 'SUPPORT', '<p style="margin:0;color:#7186a6;font-size:13px;line-height:1.6">Need help with your account or tasks? Contact your teacher or the UtosApp support team.</p><a class="save-button" href="mailto:support@utosapp.com" style="display:block;text-align:center;text-decoration:none">Contact support</a>'); }

document.querySelectorAll('[data-action]').forEach((button) => button.addEventListener('click', () => ({ profile: openProfileEditor, address: openAddressDetails, level: openLevelDetails, phone: openPhoneDetails, email: openEmailDetails, password: openPassword, notifications: openNotifications, help: openHelp }[button.dataset.action])()));
document.querySelectorAll('[data-action][role="button"]').forEach((row) => row.addEventListener('keydown', (event) => { if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); row.click(); } }));
elements.form.addEventListener('input', (event) => {
    if (event.target.name === 'detail_value' && activeDetailField === 'phone') {
        event.target.value = event.target.value.replace(/\D/g, '').slice(0, 11);
    }
    if (event.target.name === 'username') {
        event.target.value = event.target.value.toLowerCase();
    }
});
elements.form.addEventListener('submit', async (event) => { event.preventDefault(); const data = new FormData(elements.form); if (activeDetailField) { const value = String(data.get('detail_value') || '').trim(); if (activeDetailField === 'phone' && !/^\d{11}$/.test(value)) return showToast('Phone number must contain exactly 11 digits'); const response = await fetch('/api/student-profile', { method: 'PATCH', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ [activeDetailField]: value }) }); if (!response.ok) return showToast('Could not save detail'); profile = { ...profile, [activeDetailField]: value }; localStorage.setItem(profileKey, JSON.stringify(profile)); renderProfile(); showToast('Details updated'); } else if (elements.title.textContent === 'Personal Information') { const saveButton = elements.form.querySelector('.save-button'); saveButton.disabled = true; try { const response = await fetch('/api/student-username', { method: 'PATCH', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ username: data.get('username') || '' }) }); const result = await response.json(); if (!response.ok) { showToast(result.message || 'Could not save your username.'); saveButton.disabled = false; return; } profile = { ...profile, username: result.username || '' }; localStorage.setItem(profileKey, JSON.stringify(profile)); renderProfile(); showToast('Profile updated'); } catch (error) { console.error('Could not save student username:', error); showToast('Could not save your username. Please try again.'); saveButton.disabled = false; return; } } else if (elements.title.textContent === 'Change Password') { const currentPassword = String(data.get('current') || '').trim(); const newPassword = String(data.get('password') || '').trim(); const confirmPassword = String(data.get('confirm') || '').trim(); if (!currentPassword || !newPassword || !confirmPassword) return showToast('Please complete all password fields'); if (newPassword !== confirmPassword) return showToast('Passwords do not match'); const response = await fetch('/api/student-change-password', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ currentPassword, newPassword, confirmPassword }) }); const result = await response.json().catch(() => ({})); if (!response.ok) return showToast(result.message || 'Current password is wrong'); showToast('Password updated'); } else { localStorage.setItem(settingsKey, JSON.stringify({ tasks: data.has('tasks'), messages: data.has('messages') })); showToast('Settings saved'); } closeModal(); });
document.getElementById('modalClose').addEventListener('click', closeModal);
elements.modal.addEventListener('click', (event) => { if (event.target === elements.modal) closeModal(); });
elements.avatarButton.addEventListener('click', () => { elements.avatarMenu.hidden = !elements.avatarMenu.hidden; });
elements.avatarMenu.addEventListener('click', (event) => { const action = event.target.closest('[data-avatar-action]')?.dataset.avatarAction; if (!action) return; if (action === 'view') openProfilePhoto(); if (action === 'choose') { closeAvatarMenu(); elements.photoInput.click(); } if (action === 'remove') { profile.photo = ''; localStorage.setItem(profileKey, JSON.stringify(profile)); renderProfile(); closeAvatarMenu(); showToast('Profile picture removed'); } });
elements.photoInput.addEventListener('change', (event) => { const file = event.target.files[0]; if (!file || !file.type.startsWith('image/')) return; if (file.size > 8 * 1024 * 1024) return showToast('Please choose an image under 8 MB.'); const reader = new FileReader(); reader.onload = () => openCropEditor(reader.result); reader.readAsDataURL(file); event.target.value = ''; });
elements.cropZoom.addEventListener('input', (event) => { cropScale = Number(event.target.value); drawCropPreview(); });
function cropCanvasPoint(event) { const bounds = elements.cropCanvas.getBoundingClientRect(); const scale = elements.cropCanvas.width / bounds.width; return { x: (event.clientX - bounds.left) * scale, y: (event.clientY - bounds.top) * scale }; }
elements.cropStage.addEventListener('pointerdown', (event) => { if (!cropImage) return; const point = cropCanvasPoint(event); const handleRadius = 28; const handles = { nw: [cropFrameX, cropFrameY], ne: [cropFrameX + cropFrameSize, cropFrameY], sw: [cropFrameX, cropFrameY + cropFrameSize], se: [cropFrameX + cropFrameSize, cropFrameY + cropFrameSize] }; const handle = Object.entries(handles).find(([, position]) => Math.hypot(point.x - position[0], point.y - position[1]) <= handleRadius)?.[0]; elements.cropStage.setPointerCapture(event.pointerId); cropDrag = { mode: handle || 'move', x: point.x, y: point.y, offsetX: cropOffsetX, offsetY: cropOffsetY, frameX: cropFrameX, frameY: cropFrameY, frameSize: cropFrameSize }; });
elements.cropStage.addEventListener('pointermove', (event) => { if (!cropDrag) return; const point = cropCanvasPoint(event); const deltaX = point.x - cropDrag.x; const deltaY = point.y - cropDrag.y; if (cropDrag.mode === 'move') { cropFrameX = Math.max(0, Math.min(640 - cropFrameSize, cropDrag.frameX + deltaX)); cropFrameY = Math.max(0, Math.min(640 - cropFrameSize, cropDrag.frameY + deltaY)); } else { const mode = cropDrag.mode; const delta = mode === 'se' ? Math.max(deltaX, deltaY) : mode === 'nw' ? Math.min(deltaX, deltaY) : mode === 'ne' ? Math.max(deltaX, -deltaY) : Math.min(deltaX, -deltaY); const nextSize = Math.max(220, Math.min(600, cropDrag.frameSize + (mode === 'nw' || mode === 'ne' ? -delta : delta))); if (mode === 'nw') { cropFrameX = cropDrag.frameX + cropDrag.frameSize - nextSize; cropFrameY = cropDrag.frameY + cropDrag.frameSize - nextSize; } else if (mode === 'ne') { cropFrameX = cropDrag.frameX; cropFrameY = cropDrag.frameY + cropDrag.frameSize - nextSize; } else if (mode === 'sw') { cropFrameX = cropDrag.frameX + cropDrag.frameSize - nextSize; cropFrameY = cropDrag.frameY; } else { cropFrameX = cropDrag.frameX; cropFrameY = cropDrag.frameY; } cropFrameSize = nextSize; if (cropFrameX < 0) cropFrameX = 0; if (cropFrameY < 0) cropFrameY = 0; if (cropFrameX + cropFrameSize > 640) cropFrameX = 640 - cropFrameSize; if (cropFrameY + cropFrameSize > 640) cropFrameY = 640 - cropFrameSize; } drawCropPreview(); });
elements.cropStage.addEventListener('pointerup', () => { cropDrag = null; });
document.getElementById('cropClose').addEventListener('click', closeCropEditor);
document.getElementById('cropCancel').addEventListener('click', closeCropEditor);
document.getElementById('cropConfirm').addEventListener('click', confirmCrop);
document.addEventListener('click', (event) => { if (!elements.avatarButton.contains(event.target) && !elements.avatarMenu.contains(event.target)) closeAvatarMenu(); });
function closeLogoutConfirm() { elements.logoutConfirm.hidden = true; }
function openLogoutConfirm() { elements.logoutConfirm.hidden = false; document.getElementById('cancelLogout').focus(); }
async function completeLogout() { try { await fetch('/api/logout', { method: 'POST' }); } finally { window.location.href = '/login'; } }
document.getElementById('logoutButton').addEventListener('click', openLogoutConfirm);
document.getElementById('cancelLogout').addEventListener('click', closeLogoutConfirm);
document.getElementById('confirmLogout').addEventListener('click', completeLogout);
elements.logoutConfirm.addEventListener('click', (event) => { if (event.target === elements.logoutConfirm) closeLogoutConfirm(); });
document.addEventListener('keydown', (event) => { if (event.key === 'Escape') { closeModal(); closeLogoutConfirm(); closeCropEditor(); } });

async function loadStudent() { try { const response = await fetch('/api/student-session'); if (!response.ok) throw new Error('Student session required'); profile = { ...profile, ...(await response.json()).student }; localStorage.setItem(profileKey, JSON.stringify(profile)); renderProfile(); } catch { window.location.href = '/login'; } }
renderProfile();
loadStudent();