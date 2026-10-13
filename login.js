// ====== LOGIN MODULE ======
const loginModule = (() => {
    const emailInput = document.getElementById('email');
    const emailSuggestion = document.getElementById('emailSuggestion');
    const emailSuggestionChoice = document.getElementById('emailSuggestionChoice');
    const emailSuggestionDismiss = document.getElementById('emailSuggestionDismiss');

    const updateEmailSuggestion = () => {
        const value = emailInput.value.trim();
        const atIndex = value.indexOf('@');
        const localPart = atIndex === -1 ? value : value.slice(0, atIndex);
        const hasIncompleteDomain = atIndex !== -1 && atIndex === value.length - 1;
        if (!value || (atIndex === -1 && value !== '@') || (atIndex !== -1 && !hasIncompleteDomain) || /\s/.test(value)) {
            emailSuggestion.hidden = true;
            return;
        }
        const domainSuggestion = document.createElement('strong');
        domainSuggestion.textContent = '@gmail.com';
        emailSuggestionChoice.replaceChildren(document.createTextNode(localPart), domainSuggestion);
        emailSuggestion.hidden = false;
    };

    emailInput.addEventListener('input', updateEmailSuggestion);
    document.getElementById('password').addEventListener('focus', () => {
        emailSuggestion.hidden = true;
    });
    emailSuggestionChoice.addEventListener('click', () => {
        const localPart = emailInput.value.trim().split('@')[0];
        emailInput.value = `${localPart}@gmail.com`;
        emailSuggestion.hidden = true;
        emailInput.focus();
        emailInput.setSelectionRange(emailInput.value.length, emailInput.value.length);
    });
    emailSuggestionDismiss.addEventListener('click', () => {
        emailSuggestion.hidden = true;
    });

    /**
     * Toggle password visibility
     */
    const togglePassword = () => {
        const input = document.getElementById('password');
        const openEye = document.getElementById('openEye');
        const closedEye = document.getElementById('closedEye');

        if (input.type === 'password') {
            input.type = 'text';
            openEye.style.display = 'none';
            closedEye.style.display = 'block';
        } else {
            input.type = 'password';
            openEye.style.display = 'block';
            closedEye.style.display = 'none';
        }
    };

    /**
     * Show error alert
     * @param {string} message - Error message to display
     */
    const showErrorAlert = (message = 'Invalid email or password. Please try again.') => {
        document.getElementById('errorMessage').textContent = message;
        document.getElementById('errorAlert').classList.add('show');
    };

    /**
     * Close alert
     * @param {string} alertId - ID of the alert to close
     */
    const closeAlert = (alertId) => {
        document.getElementById(alertId).classList.remove('show');
    };

    /**
     * Show success alert and redirect
     * @param {string} userType - 'student' or 'teacher'
     */
    const showSuccessAlert = (userType = 'teacher') => {
        document.getElementById('successAlert').classList.add('show');

        setTimeout(() => {
            if (userType === 'admin') {
                window.location.href = '/admin-dashboard';
            } else if (userType === 'teacher') {
                window.location.href = '/teacher-dashboard';
            } else {
                window.location.href = '/student-home';
            }
        }, 1500);
    };

    /**
     * Handle login form submission
     * @param {Event} event - Form submit event
     */
    const handleLogin = async (event) => {
        event.preventDefault();

        const email = document.getElementById('email').value.trim().toLowerCase();
        const password = document.getElementById('password').value.trim();
        const loginBtn = document.getElementById('loginBtn');
        
        console.log('📝 Login attempt with:', { email, passwordLength: password.length });

        if (!email || !password) {
            showErrorAlert('Email and password are required');
            return;
        }

        // Disable button during request
        loginBtn.disabled = true;
        loginBtn.textContent = 'Logging in...';

        try {
            const response = await fetch('/login/auth', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ email, password })
            });

            const data = await response.json();

            if (data.success) {
                if (data.role === 'teacher' && data.user) {
                    localStorage.setItem('utosapp_teacher_profile', JSON.stringify({
                        first_name: data.user.first_name || '',
                        last_name: data.user.last_name || '',
                        email: data.user.email || email,
                        username: data.user.username || '',
                        department: data.user.department || ''
                    }));
                }
                if (data.role === 'admin' && data.user) {
                    localStorage.setItem('utosapp_admin_profile', JSON.stringify({
                        first_name: data.user.first_name || 'Admin',
                        last_name: data.user.last_name || '',
                        email: data.user.email || email,
                        admin_id: data.user.admin_id || ''
                    }));
                }
                showSuccessAlert(data.role);
            } else {
                showErrorAlert(data.message);
                loginBtn.disabled = false;
                loginBtn.textContent = 'Log In';
            }
        } catch (error) {
            console.error('Login error:', error);
            showErrorAlert('An error occurred. Please try again.');
            loginBtn.disabled = false;
            loginBtn.textContent = 'Log In';
        }
    };

    /**
     * Navigate back to frontpage
     */
    const goBack = () => {
        window.location.href = '/';
    };

    // Public API
    return {
        togglePassword,
        showErrorAlert,
        closeAlert,
        showSuccessAlert,
        handleLogin,
        goBack
    };
})();

// ====== INITIALIZE ON PAGE LOAD ======
document.addEventListener('DOMContentLoaded', () => {
    sessionStorage.removeItem('utosapp_teacher_signup');

    const signupLink = document.querySelector('.signup-btn');
    if (signupLink) {
        signupLink.addEventListener('click', (event) => {
            if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                return;
            }

            event.preventDefault();
            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                window.location.assign(signupLink.href);
                return;
            }

            document.body.classList.add('auth-leaving');
            window.setTimeout(() => window.location.assign(signupLink.href), 180);
        });
    }
});
