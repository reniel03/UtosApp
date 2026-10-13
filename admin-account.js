const profileKey = 'utosapp_admin_profile';
const settingsKey = 'utosapp_admin_settings';
const storedProfile = JSON.parse(localStorage.getItem(profileKey) || 'null');
let profile = {
    first_name: 'Admin',
    last_name: '',
    email: 'admin@utosapp.com',
    admin_id: 'Not assigned',
    phone: '',
    photo: '',
    ...storedProfile
};

const accountModal = document.getElementById('accountModal');
const accountForm = document.getElementById('accountForm');
const avatarMenu = document.getElementById('avatarMenu');
const logoutConfirm = document.getElementById('logoutConfirm');
const toast = document.getElementById('toast');
let activeDetailField = null;
let toastTimer;

function escapeHTML(value) {
    return String(value ?? '').replace(/[&<>"']/g, (character) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    })[character]);
}

function saveProfile() {
    localStorage.setItem(profileKey, JSON.stringify(profile));
    renderProfile();
}

function renderProfile() {
    const name = [profile.first_name, profile.last_name].filter(Boolean).join(' ') || 'Admin';
    document.getElementById('profileName').textContent = name;
    document.querySelector('#profileEmail span').textContent = profile.email || 'admin@utosapp.com';
    document.getElementById('adminEmailValue').textContent = profile.email || 'admin@utosapp.com';
    document.getElementById('adminId').textContent = profile.admin_id || 'Not assigned';
    document.getElementById('adminPhone').textContent = profile.phone || 'Not available';

    const avatar = document.getElementById('profileAvatar');
    const removePhoto = avatarMenu.querySelector('[data-avatar-action="remove"]');
    if (profile.photo) {
        avatar.querySelector('.profile-photo').src = profile.photo;
        avatar.style.backgroundImage = `url(${profile.photo})`;
        avatar.classList.add('has-photo');
    } else {
        avatar.style.backgroundImage = '';
        avatar.classList.remove('has-photo');
    }
    removePhoto.hidden = !profile.photo;
}

function showToast(message) {
    toast.textContent = message;
    toast.classList.add('show');
    window.clearTimeout(toastTimer);
    toastTimer = window.setTimeout(() => toast.classList.remove('show'), 2200);
}

function showModal(title, eyebrow, content) {
    activeDetailField = null;
    document.getElementById('modalTitle').textContent = title;
    document.getElementById('modalEyebrow').textContent = eyebrow;
    accountForm.innerHTML = content;
    accountModal.hidden = false;
    accountModal.setAttribute('aria-hidden', 'false');
}

function closeModal() {
    accountModal.hidden = true;
    accountModal.setAttribute('aria-hidden', 'true');
    accountForm.innerHTML = '';
}

function openProfile() {
    showModal('Edit Profile', 'PERSONAL INFORMATION', `<label>First name<input name="first_name" value="${escapeHTML(profile.first_name)}" required></label><label>Last name<input name="last_name" value="${escapeHTML(profile.last_name)}"></label><label>Email<input type="email" name="email" value="${escapeHTML(profile.email)}" readonly></label><button class="save-button" type="submit">Save Profile</button>`);
}

function openPhone() {
    showModal('Phone Number', 'PERSONAL INFORMATION', `<label>Phone Number<input name="phone" type="tel" value="${escapeHTML(profile.phone)}"></label><button class="save-button" type="submit">Save changes</button>`);
    activeDetailField = 'phone';
}

function openPassword() {
    showModal('Change Password', 'ACCOUNT SECURITY', '<label>Current password<input type="password" name="current" required></label><label>New password<input type="password" name="password" minlength="6" required></label><label>Confirm password<input type="password" name="confirm" minlength="6" required></label><button class="save-button" type="submit">Update Password</button>');
}

function openSettings() {
    const settings = JSON.parse(localStorage.getItem(settingsKey) || '{}');
    showModal('Settings', 'PREFERENCES', `<label class="toggle-row"><span>Email notifications<small>Receive important system updates</small></span><input type="checkbox" name="notifications" ${settings.notifications !== false ? 'checked' : ''}></label><label class="toggle-row"><span>Security alerts<small>Receive alerts about account activity</small></span><input type="checkbox" name="security" ${settings.security !== false ? 'checked' : ''}></label><button class="save-button" type="submit">Save Settings</button>`);
}

function openHelp() {
    showModal('Help & Support', 'SUPPORT', '<p style="margin:0;color:#7186a6;font-size:13px;line-height:1.6">Need help with your administrator account? Contact the UtosApp support team.</p><a class="save-button" href="mailto:support@utosapp.com" style="display:block;text-align:center;text-decoration:none">Contact support</a>');
}

function openEmail() {
    showModal('Email Address', 'PERSONAL INFORMATION', `<label>Email Address<input type="email" value="${escapeHTML(profile.email)}" readonly></label>`);
}

function openPhoto() {
    if (!profile.photo) return showToast('No profile picture to view.');
    showModal('Profile picture', 'PROFILE PHOTO', `<div class="profile-photo-preview"><img src="${escapeHTML(profile.photo)}" alt="Profile picture"></div>`);
}

function closeAvatarMenu() {
    avatarMenu.hidden = true;
}

function setPhoto(file) {
    if (!file || !file.type.startsWith('image/')) return;
    if (file.size > 8 * 1024 * 1024) return showToast('Choose an image smaller than 8 MB.');

    const reader = new FileReader();
    reader.onload = () => {
        const image = new Image();
        image.onload = () => {
            const scale = Math.min(1, 512 / Math.max(image.width, image.height));
            const canvas = document.createElement('canvas');
            canvas.width = Math.round(image.width * scale);
            canvas.height = Math.round(image.height * scale);
            canvas.getContext('2d').drawImage(image, 0, 0, canvas.width, canvas.height);
            profile.photo = canvas.toDataURL('image/jpeg', 0.82);
            saveProfile();
            showToast('Profile picture updated.');
        };
        image.src = reader.result;
    };
    reader.readAsDataURL(file);
}

function closeLogoutConfirm() {
    logoutConfirm.hidden = true;
    logoutConfirm.setAttribute('aria-hidden', 'true');
}

async function logout() {
    try {
        await fetch('/api/logout', { method: 'POST' });
    } finally {
        window.location.href = '/login';
    }
}

document.querySelectorAll('[data-action]').forEach((item) => {
    item.addEventListener('click', () => {
        const actions = {
            profile: openProfile,
            phone: openPhone,
            email: openEmail,
            password: openPassword,
            notifications: openSettings,
            help: openHelp
        };
        actions[item.dataset.action]?.();
    });
    item.addEventListener('keydown', (event) => {
        if ((event.key === 'Enter' || event.key === ' ') && item.getAttribute('role') === 'button') {
            event.preventDefault();
            item.click();
        }
    });
});

accountForm.addEventListener('submit', (event) => {
    event.preventDefault();
    const data = new FormData(accountForm);
    const modalTitle = document.getElementById('modalTitle').textContent;

    if (activeDetailField) {
        profile[activeDetailField] = String(data.get(activeDetailField) || '').trim();
        saveProfile();
        showToast('Details updated.');
    } else if (modalTitle === 'Edit Profile') {
        profile.first_name = String(data.get('first_name') || '').trim();
        profile.last_name = String(data.get('last_name') || '').trim();
        saveProfile();
        showToast('Profile updated.');
    } else if (modalTitle === 'Change Password') {
        if (data.get('password') !== data.get('confirm')) return showToast('Passwords do not match.');
        showToast('Password updates are not available here yet.');
    } else {
        localStorage.setItem(settingsKey, JSON.stringify({
            notifications: data.has('notifications'),
            security: data.has('security')
        }));
        showToast('Settings saved.');
    }
    closeModal();
});

document.getElementById('closeAccountModal').addEventListener('click', closeModal);
accountModal.addEventListener('click', (event) => {
    if (event.target === accountModal) closeModal();
});
document.getElementById('profileAvatarButton').addEventListener('click', () => {
    avatarMenu.hidden = !avatarMenu.hidden;
});
avatarMenu.addEventListener('click', (event) => {
    const action = event.target.closest('[data-avatar-action]')?.dataset.avatarAction;
    if (action === 'view') openPhoto();
    if (action === 'choose') {
        closeAvatarMenu();
        document.getElementById('profilePhotoInput').click();
    }
    if (action === 'remove') {
        profile.photo = '';
        saveProfile();
        closeAvatarMenu();
        showToast('Profile picture removed.');
    }
});
document.getElementById('profilePhotoInput').addEventListener('change', (event) => {
    setPhoto(event.target.files[0]);
    event.target.value = '';
});
document.addEventListener('click', (event) => {
    if (!document.getElementById('profileAvatarButton').contains(event.target) && !avatarMenu.contains(event.target)) closeAvatarMenu();
});
document.getElementById('logoutButton').addEventListener('click', () => {
    logoutConfirm.hidden = false;
    logoutConfirm.setAttribute('aria-hidden', 'false');
});
document.getElementById('cancelLogout').addEventListener('click', closeLogoutConfirm);
document.getElementById('confirmLogout').addEventListener('click', logout);
logoutConfirm.addEventListener('click', (event) => {
    if (event.target === logoutConfirm) closeLogoutConfirm();
});
document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        closeModal();
        closeLogoutConfirm();
        closeAvatarMenu();
    }
});

renderProfile();
