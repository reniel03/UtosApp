// ====== SIGNUP MODULE ======
let scanValidation = {
    completed: false,
    valid: true,
    text: ''
};
let selectedComFile = null;
let comPreviewObjectUrl = null;
let comScanGeneration = 0;
let activeOcrWorker = null;
let pdfJsLoadingPromise = null;

const signupModule = (() => {
    let passwordRequirements = {
        length: false,
        lowercase: false,
        uppercase: false,
        number: false,
        special: false
    };

    /**
     * Toggle password visibility
     */
    const togglePassword = (inputId) => {
        const input = document.getElementById(inputId);
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
     * Check password strength and update requirements
     */
    const checkPasswordStrength = (password) => {
        const strengthBar = document.getElementById('passwordStrengthBar');
        const strengthText = document.getElementById('passwordStrengthText');

        // Check requirements
        passwordRequirements.length = password.length >= 8;
        passwordRequirements.lowercase = /[a-z]/.test(password);
        passwordRequirements.uppercase = /[A-Z]/.test(password);
        passwordRequirements.number = /\d/.test(password);
        passwordRequirements.special = /[@_#*]/.test(password);

        // Update UI for requirements
        updateRequirementUI('req-length', passwordRequirements.length);
        updateRequirementUI('req-lowercase', passwordRequirements.lowercase);
        updateRequirementUI('req-uppercase', passwordRequirements.uppercase);
        updateRequirementUI('req-number', passwordRequirements.number);
        updateRequirementUI('req-special', passwordRequirements.special);

        // Clear classes
        strengthBar.classList.remove('weak', 'medium', 'strong');

        // Only show strength if password is not empty
        if (password.length === 0) {
            strengthText.textContent = '';
            return;
        }

        // Calculate strength
        const metRequirements = Object.values(passwordRequirements).filter(v => v).length;

        if (metRequirements <= 2) {
            strengthBar.classList.add('weak');
            strengthText.textContent = 'Weak';
        } else if (metRequirements <= 4) {
            strengthBar.classList.add('medium');
            strengthText.textContent = 'Medium';
        } else {
            strengthBar.classList.add('strong');
            strengthText.textContent = 'Strong';
        }
    };

    /**
     * Update requirement UI element
     */
    const updateRequirementUI = (elementId, isMet) => {
        const element = document.getElementById(elementId);
        if (isMet) {
            element.classList.add('met');
        } else {
            element.classList.remove('met');
        }
    };

    /**
     * Validate form data
     */
    const validateForm = (formData, userType) => {
        // Check if all requirements are met
        const allRequirementsMet = Object.values(passwordRequirements).every(v => v);
        if (!allRequirementsMet) {
            showErrorAlert('Password does not meet all requirements');
            return false;
        }

        // Check email format
        if (!isValidEmail(formData.email)) {
            showErrorAlert('Please enter a valid email address');
            return false;
        }

        return true;
    };

    /**
     * Validate email format
     */
    const isValidEmail = (email) => {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    };

    /**
     * Show error alert
     */
    const showErrorAlert = (message) => {
        document.getElementById('errorMessage').textContent = message;
        document.getElementById('errorAlert').classList.add('show');
    };

    /**
     * Close alert
     */
    const closeAlert = (alertId) => {
        document.getElementById(alertId).classList.remove('show');
    };

    const saveTeacherFormData = (formData) => {
        sessionStorage.setItem('utosapp_teacher_signup', JSON.stringify(formData));
        localStorage.setItem('utosapp_teacher_profile', JSON.stringify({
            first_name: formData.first_name || '',
            last_name: formData.last_name || '',
            email: formData.email || '',
            department: formData.department || ''
        }));
    };

    const saveCurrentTeacherForm = () => {
        if (!document.body.classList.contains('teacher-signup')) return;

        saveTeacherFormData({
            first_name: document.getElementById('first_name')?.value || '',
            middle_name: document.getElementById('middle_name')?.value || '',
            last_name: document.getElementById('last_name')?.value || '',
            gender: document.getElementById('gender')?.value || '',
            email: document.getElementById('email')?.value || '',
            password: document.getElementById('password')?.value || '',
            department: document.getElementById('department')?.value || ''
        });
    };

    const clearTeacherForm = () => {
        ['first_name', 'middle_name', 'last_name', 'email', 'password', 'department'].forEach((field) => {
            const input = document.getElementById(field);
            if (input) input.value = '';
        });

        const genderInput = document.getElementById('gender');
        const genderLabel = document.getElementById('genderSelected');
        if (genderInput) genderInput.value = '';
        if (genderLabel) genderLabel.textContent = 'Select your gender';
        signupModule.checkPasswordStrength('');
    };

    const saveCurrentStudentForm = () => {
        if (!document.body.classList.contains('student-signup')) return;

        const scannedDetails = {};
        document.querySelectorAll('#scanResultsContent [name^="scanned_"]').forEach((field) => {
            scannedDetails[field.name] = field.value;
        });

        const formData = {
            first_name: document.getElementById('first_name')?.value || '',
            middle_name: document.getElementById('middle_name')?.value || '',
            last_name: document.getElementById('last_name')?.value || '',
            gender: document.getElementById('gender')?.value || '',
            course: document.getElementById('course')?.value || '',
            email: document.getElementById('email')?.value || '',
            password: document.getElementById('password')?.value || '',
            scannedDetails,
            scanValidation
        };

        try {
            sessionStorage.setItem('utosapp_student_signup', JSON.stringify(formData));
        } catch (error) {
            console.error('Unable to save student signup progress:', error);
        }
    };

    const restoreStudentForm = () => {
        const savedData = sessionStorage.getItem('utosapp_student_signup');
        if (!savedData) return;

        try {
            const formData = JSON.parse(savedData);
            ['first_name', 'middle_name', 'last_name', 'email', 'password'].forEach((field) => {
                const input = document.getElementById(field);
                if (input && typeof formData[field] === 'string') input.value = formData[field];
            });

            const genderInput = document.getElementById('gender');
            const genderLabel = document.getElementById('genderSelected');
            if (genderInput && typeof formData.gender === 'string') {
                genderInput.value = formData.gender;
                if (genderLabel && formData.gender) genderLabel.textContent = formData.gender;
            }

            const courseInput = document.getElementById('course');
            const courseLabel = document.getElementById('courseSelected');
            if (courseInput && typeof formData.course === 'string') {
                courseInput.value = formData.course;
                if (courseLabel && formData.course) courseLabel.textContent = formData.course;
            }

            const scannedFields = {
                scanned_full_name: { label: 'Full Name', type: 'text' },
                scanned_student_number: { label: 'Student Number', type: 'text' },
                scanned_address: { label: 'Address', type: 'textarea' },
                scanned_level: { label: 'Level', type: 'text' },
                scanned_phone_number: { label: 'Phone Number', type: 'tel' },
                scanned_course: { label: 'Course', type: 'text' }
            };
            const resultsContent = document.getElementById('scanResultsContent');
            let hasScannedDetails = false;

            Object.entries(scannedFields).forEach(([name, fieldInfo]) => {
                const value = formData.scannedDetails?.[name];
                if (typeof value !== 'string' || !value) return;

                const item = document.createElement('div');
                item.className = 'scan-result-item';
                const label = document.createElement('label');
                label.htmlFor = name;
                label.textContent = `${fieldInfo.label}:`;

                const field = document.createElement(fieldInfo.type === 'textarea' ? 'textarea' : 'input');
                field.name = name;
                field.id = name;
                field.value = value;
                if (fieldInfo.type !== 'textarea') field.type = fieldInfo.type;
                if (name === 'scanned_student_number') field.readOnly = true;
                if (fieldInfo.type === 'textarea') field.rows = 2;

                item.append(label, field);
                resultsContent.append(item);
                hasScannedDetails = true;
            });

            if (hasScannedDetails) {
                document.getElementById('scanResults').style.display = 'block';
            }

            if (formData.scanValidation && typeof formData.scanValidation.text === 'string') {
                scanValidation = {
                    completed: formData.scanValidation.completed === true,
                    valid: formData.scanValidation.valid === true,
                    text: formData.scanValidation.text
                };
            }

            if (formData.password) signupModule.checkPasswordStrength(formData.password);
        } catch (error) {
            console.error('Unable to restore student signup progress:', error);
            sessionStorage.removeItem('utosapp_student_signup');
        }
    };

    const clearStudentForm = () => {
        ['first_name', 'middle_name', 'last_name', 'email', 'password'].forEach((field) => {
            const input = document.getElementById(field);
            if (input) input.value = '';
        });

        const genderInput = document.getElementById('gender');
        const genderLabel = document.getElementById('genderSelected');
        if (genderInput) genderInput.value = '';
        if (genderLabel) genderLabel.textContent = 'Select your gender';

        const courseInput = document.getElementById('course');
        const courseLabel = document.getElementById('courseSelected');
        const courseSearch = document.getElementById('courseSearchInput');
        if (courseInput) courseInput.value = '';
        if (courseLabel) courseLabel.textContent = 'Select Course';
        if (courseSearch) courseSearch.value = '';

        document.getElementById('genderDropdownMenu')?.classList.remove('active');
        document.getElementById('courseDropdownMenu')?.classList.remove('active');
        signupModule.checkPasswordStrength('');
        removeFile();
        sessionStorage.removeItem('utosapp_student_signup');
        document.getElementById('errorAlert')?.classList.remove('show');
    };

    /**
     * Handle form submission
     */
    const handleSignup = async (event, userType) => {
        event.preventDefault();

        // Get form data
        const formData = {
            first_name: document.getElementById('first_name').value.trim(),
            middle_name: document.getElementById('middle_name').value.trim(),
            last_name: document.getElementById('last_name').value.trim(),
            gender: document.getElementById('gender').value,
            email: document.getElementById('email').value.trim(),
            password: document.getElementById('password').value
        };

        // Add course for students
        if (userType === 'student') {
            const courseInput = document.getElementById('course');
            if (courseInput) {
                formData.course = courseInput.value.trim();
            }
            formData.student_id = document.querySelector('[name="scanned_student_number"]')?.value.trim() || '';
            formData.address = document.querySelector('[name="scanned_address"]')?.value.trim() || '';
            formData.level = document.querySelector('[name="scanned_level"]')?.value.trim() || '';
            formData.phone = document.querySelector('[name="scanned_phone_number"]')?.value.trim() || '';
        }

        if (userType === 'student') saveCurrentStudentForm();

        // Validate form
        if (!validateForm(formData, userType)) {
            return;
        }

        if (userType === 'student') {
            const fileInput = document.getElementById('com_certificate');
            const hasUploadedDocument = fileInput && fileInput.files.length > 0;

            if (!hasUploadedDocument) {
                showErrorAlert('Please upload your Certificate of Matriculation before signing up.');
                return;
            }

            if (hasUploadedDocument && !scanValidation.completed) {
                showErrorAlert('Please scan the uploaded document before signing up.');
                return;
            }

            if (hasUploadedDocument && scanValidation.text) {
                const mismatches = getScanMismatches(scanValidation.text, formData.course);
                if (mismatches.length) {
                    scanValidation.valid = false;
                    showErrorAlert(formatMismatchMessage(mismatches));
                    return;
                }
                scanValidation.valid = true;
            }
        }

        // Disable button
        const signupBtn = document.getElementById('signupBtn');
        signupBtn.disabled = true;
        signupBtn.textContent = 'Signing up...';

        try {
            const endpoint = userType === 'teacher' ? '/signup-teacher' : '/signup-student';
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(formData)
            });
            const result = await response.json();

            if (response.ok && result.success) {
                if (userType === 'teacher') {
                    localStorage.setItem('utosapp_teacher_profile', JSON.stringify({
                        first_name: formData.first_name,
                        last_name: formData.last_name,
                        email: formData.email,
                        department: formData.department || ''
                    }));
                }
                if (result.verificationRequired) {
                    if (userType === 'teacher') saveTeacherFormData(formData);
                    window.location.href = `/verify-${userType}?email=${encodeURIComponent(formData.email)}`;
                } else {
                    if (userType === 'student') sessionStorage.removeItem('utosapp_student_signup');
                    showSuccessAlert(userType);
                }
            } else {
                showErrorAlert(result.message || 'Failed to save data to Firebase');
                signupBtn.disabled = false;
                signupBtn.textContent = 'SIGN UP';
            }
        } catch (error) {
            console.error('❌ Signup error:', error);
            showErrorAlert('An error occurred. Please try again.');
            signupBtn.disabled = false;
            signupBtn.textContent = 'SIGN UP';
        }
    };

    /**
     * Show success alert and redirect
     */
    const showSuccessAlert = (userType) => {
        document.getElementById('successAlert').classList.add('show');

        setTimeout(() => {
            window.location.href = '/login';
        }, 1500);
    };

    /**
     * Navigate back to signup choice
     */
    const goBack = () => {
        if (document.body.classList.contains('student-signup')) {
            window.removeEventListener('beforeunload', saveCurrentStudentForm);
            clearStudentForm();
        }
        window.location.href = '/signup';
    };

    // Public API
    return {
        togglePassword,
        checkPasswordStrength,
        validateForm,
        showErrorAlert,
        closeAlert,
        handleSignup,
        saveCurrentTeacherForm,
        saveCurrentStudentForm,
        restoreStudentForm,
        clearStudentForm,
        clearTeacherForm,
        goBack
    };
})();

// ====== FILE UPLOAD HANDLER ======
function handleFileSelect(event) {
    const file = event.target.files[0];
    const uploadBox = document.getElementById('uploadBox');
    const filePreviewSection = document.getElementById('filePreviewSection');
    const filePreviewImage = document.getElementById('filePreviewImage');
    const filePreviewPdf = document.getElementById('filePreviewPdf');
    const filePreviewFallback = document.getElementById('filePreviewFallback');
    const scanResults = document.getElementById('scanResults');
    const scanResultsContent = document.getElementById('scanResultsContent');

    if (!file) return;

    const fileType = getComFileType(file);
    if (!fileType) {
        event.target.value = '';
        if (selectedComFile) removeFile();
        showErrorAlert('Please select a PDF, JPG, JPEG, or PNG document.');
        return;
    }

    invalidateComScan();
    scanValidation = { completed: false, valid: true, text: '' };
    selectedComFile = file;
    scanResultsContent.replaceChildren();
    scanResults.style.display = 'none';

    if (comPreviewObjectUrl) URL.revokeObjectURL(comPreviewObjectUrl);
    comPreviewObjectUrl = URL.createObjectURL(file);
    filePreviewImage.hidden = true;
    filePreviewPdf.hidden = true;
    filePreviewFallback.hidden = true;
    filePreviewImage.removeAttribute('src');
    filePreviewPdf.removeAttribute('src');

    document.getElementById('comFileName').textContent = file.name;
    document.getElementById('comFileSize').textContent = formatComFileSize(file.size);
    document.getElementById('comFileType').textContent = fileType.label;
    document.getElementById('comPreviewStage').dataset.state = 'scanning';
    document.querySelector('.scan-btn').textContent = 'SCANNING...';
    document.querySelector('.scan-btn').disabled = true;

    uploadBox.style.display = 'none';
    filePreviewSection.style.display = 'block';

    if (fileType.kind === 'pdf') {
        filePreviewPdf.src = comPreviewObjectUrl;
        filePreviewPdf.hidden = false;
    } else {
        filePreviewImage.src = comPreviewObjectUrl;
        filePreviewImage.hidden = false;
    }

    setComScanStatus('scanning', 0, 'Scanning your COM file...', 'Please wait while we process your document on this device.');
    signupModule.saveCurrentStudentForm();
    scanFileDetails();
}

function removeFile() {
    const uploadBox = document.getElementById('uploadBox');
    const filePreviewSection = document.getElementById('filePreviewSection');
    const fileInput = document.getElementById('com_certificate');
    const scanResults = document.getElementById('scanResults');
    const scanResultsContent = document.getElementById('scanResultsContent');
    const filePreviewImage = document.getElementById('filePreviewImage');
    const filePreviewPdf = document.getElementById('filePreviewPdf');

    invalidateComScan();
    scanValidation = { completed: false, valid: true, text: '' };
    selectedComFile = null;
    fileInput.value = '';
    scanResultsContent.replaceChildren();
    filePreviewImage.removeAttribute('src');
    filePreviewPdf.removeAttribute('src');
    filePreviewImage.hidden = true;
    filePreviewPdf.hidden = true;
    document.getElementById('filePreviewFallback').hidden = true;
    document.getElementById('comFileName').textContent = 'COM file';
    document.getElementById('comFileSize').textContent = '';
    document.getElementById('comFileType').textContent = '';
    document.querySelector('.scan-btn').textContent = 'SCAN';
    document.querySelector('.scan-btn').disabled = false;
    setComScanStatus('idle', 0, 'Ready to scan', 'Select a COM file to begin processing.');
    uploadBox.style.display = 'block';
    filePreviewSection.style.display = 'none';
    scanResults.style.display = 'none';
    if (comPreviewObjectUrl) {
        URL.revokeObjectURL(comPreviewObjectUrl);
        comPreviewObjectUrl = null;
    }
    signupModule.saveCurrentStudentForm();
}

async function scanFileDetails() {
    const filePreviewImage = document.getElementById('filePreviewImage');
    const scanResults = document.getElementById('scanResults');
    const scanResultsContent = document.getElementById('scanResultsContent');
    const scanBtn = document.querySelector('.scan-btn');
    const file = selectedComFile;

    if (!file) {
        showErrorAlert('Please select your COM file before scanning.');
        return;
    }

    const generation = ++comScanGeneration;
    const previousWorker = activeOcrWorker;
    activeOcrWorker = null;

    try {
        if (previousWorker) await previousWorker.terminate();
        if (generation !== comScanGeneration) return;

        scanValidation = { completed: false, valid: false, text: '' };
        scanBtn.textContent = 'SCANNING...';
        scanBtn.disabled = true;
        document.getElementById('comPreviewStage').dataset.state = 'scanning';
        setComScanStatus('scanning', 0, 'Scanning your COM file...', 'Please wait while we process your document on this device.');

        const fileType = getComFileType(file);
        let text = '';
        if (fileType.kind === 'pdf') {
            const pdfJs = await loadPdfJs();
            if (generation !== comScanGeneration) return;
            pdfJs.GlobalWorkerOptions.workerSrc = 'https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/legacy/build/pdf.worker.min.js';
            const pdf = await pdfJs.getDocument({ data: await file.arrayBuffer() }).promise;
            if (!pdf.numPages) throw new Error('This PDF does not contain any pages.');
            for (let pageNumber = 1; pageNumber <= pdf.numPages; pageNumber += 1) {
                if (generation !== comScanGeneration) return;
                const page = await pdf.getPage(pageNumber);
                const pageText = await page.getTextContent();
                text += `${pageText.items.map(item => 'str' in item ? item.str : '').join(' ')}\n`;
                const progress = 5 + 90 * (pageNumber / pdf.numPages);
                setComScanStatus('scanning', progress, 'Scanning your COM file...', 'Please wait while we process your document on this device.');
            }
            if (!text.trim()) {
                throw new Error('No selectable text was found in this PDF. Upload a clear image or a text-based PDF of your COM.');
            }
        } else {
            if (typeof Tesseract === 'undefined' || typeof Tesseract.createWorker !== 'function') {
                throw new Error('The document scanning library could not be loaded. Check your connection and retry.');
            }
            if (!filePreviewImage.complete || !filePreviewImage.naturalWidth) {
                await new Promise((resolve, reject) => {
                    filePreviewImage.addEventListener('load', resolve, { once: true });
                    filePreviewImage.addEventListener('error', reject, { once: true });
                });
            }
            if (generation !== comScanGeneration) return;
            const canvas = await imageFileToCanvas(filePreviewImage);
            const worker = await Tesseract.createWorker('eng', 1, {
                logger: (message) => {
                    if (generation !== comScanGeneration || message.status !== 'recognizing text') return;
                    const progress = 5 + 90 * Math.max(0, Math.min(1, message.progress || 0));
                    setComScanStatus('scanning', progress, 'Scanning your COM file...', 'Please wait while we process your document on this device.');
                }
            });
            if (generation !== comScanGeneration) {
                await worker.terminate();
                canvas.width = 0;
                canvas.height = 0;
                return;
            }
            activeOcrWorker = worker;
            const result = await worker.recognize(canvas);
            text = result.data.text;
            canvas.width = 0;
            canvas.height = 0;
            await worker.terminate();
            activeOcrWorker = null;
        }

        if (generation !== comScanGeneration) return;

        const extractedInfo = {
            'Full Name': extractNameFromText(text),
            'Student Number': extractStudentNumberFromText(text),
            'Address': extractAddressFromText(text),
            'Level': extractLevelFromText(text),
            'Phone Number': extractPhoneNumberFromText(text)
        };

        const selectedCourse = document.getElementById('course')?.value || '';
        const courseFromScan = extractCourseFromText(text);
        if (courseFromScan) extractedInfo.Course = courseFromScan;

        if (!hasComDocumentIndicators(text)) {
            scanResultsContent.replaceChildren();
            scanResults.style.display = 'none';
            scanValidation = { completed: false, valid: false, text: '' };
            document.getElementById('comPreviewStage').dataset.state = 'error';
            setComScanStatus('error', 0, 'This file is not a COM.', 'Upload your Certificate of Matriculation to continue.');
            scanBtn.textContent = 'RETRY SCAN';
            signupModule.showErrorAlert('This file is not a COM');
            return;
        }

        showScanMismatchAlert(text, selectedCourse);

        let resultsHTML = '';
        for (const [key, value] of Object.entries(extractedInfo)) {
            if (!value) continue;
            const fieldName = key.toLowerCase().replace(/\s+/g, '_');
            const inputType = key === 'Phone Number' ? 'tel' : 'text';
            const field = key === 'Address'
                ? `<textarea name="scanned_${fieldName}" rows="2">${escapeHtml(value)}</textarea>`
                : `<input type="${inputType}" name="scanned_${fieldName}" value="${escapeHtml(value)}"${fieldName === 'student_number' ? ' readonly' : ''}>`;
            resultsHTML += `<div class="scan-result-item"><label for="scanned_${fieldName}">${key}:</label>${field}</div>`;
        }

        if (resultsHTML) {
            scanResultsContent.innerHTML = resultsHTML;
        } else {
            scanResultsContent.replaceChildren();
            scanValidation = { completed: false, valid: false, text: '' };
            document.getElementById('comPreviewStage').dataset.state = 'error';
            setComScanStatus('error', 0, 'This file is not a COM.', 'Upload your Certificate of Matriculation to continue.');
            scanBtn.textContent = 'RETRY SCAN';
            signupModule.showErrorAlert('This file is not a COM');
            return;
        }
        scanResults.style.display = 'block';
        document.getElementById('comPreviewStage').dataset.state = 'complete';
        setComScanStatus('complete', 100, 'Document ready!', 'Your COM file was processed on this device. It has not been authenticated.');
        scanBtn.textContent = 'SCAN AGAIN';
        signupModule.saveCurrentStudentForm();
    } catch (error) {
        if (generation !== comScanGeneration) return;
        console.error('Scan error:', error);
        scanValidation = { completed: false, valid: false, text: '' };
        document.getElementById('comPreviewStage').dataset.state = 'error';
        setComScanStatus('error', 0, 'Unable to process this file.', `${error.message || 'An unexpected error occurred.'} Select Retry scan to try again.`);
        scanBtn.textContent = 'RETRY SCAN';
    } finally {
        if (generation === comScanGeneration) {
            if (activeOcrWorker) {
                await activeOcrWorker.terminate();
                activeOcrWorker = null;
            }
            scanBtn.disabled = false;
        }
    }
}

function hasComDocumentIndicators(text) {
    const normalizedText = normalizeScanText(text);
    if (/\bcertificate of matriculation\b/.test(normalizedText)) return true;

    const indicators = [
        /\bstudent (?:number|no|id)\b/,
        /\baddress\b/,
        /\b(?:course|program|degree)\b/,
        /\b(?:phone|telephone|mobile)\b/,
        /\bsemester\b/,
        /\b(?:subject|schedule|units|academic)\b/
    ];
    return indicators.filter(pattern => pattern.test(normalizedText)).length >= 2;
}

function getComFileType(file) {
    const extension = file.name.split('.').pop().toLowerCase();
    if (file.type === 'application/pdf' || extension === 'pdf') return { kind: 'pdf', label: 'PDF' };
    if (['image/jpeg', 'image/png'].includes(file.type) || ['jpg', 'jpeg', 'png'].includes(extension)) {
        return { kind: 'image', label: file.type === 'image/png' || extension === 'png' ? 'PNG image' : 'JPEG image' };
    }
    return null;
}

function formatComFileSize(size) {
    if (size < 1024) return `${size} B`;
    if (size < 1024 * 1024) return `${(size / 1024).toFixed(1)} KB`;
    return `${(size / (1024 * 1024)).toFixed(2)} MB`;
}

function loadPdfJs() {
    if (typeof pdfjsLib !== 'undefined') return Promise.resolve(pdfjsLib);
    if (!pdfJsLoadingPromise) {
        pdfJsLoadingPromise = new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/legacy/build/pdf.min.js';
            script.onload = () => {
                if (typeof pdfjsLib === 'undefined') {
                    pdfJsLoadingPromise = null;
                    reject(new Error('The PDF preview processor could not be initialized.'));
                    return;
                }
                resolve(pdfjsLib);
            };
            script.onerror = () => {
                pdfJsLoadingPromise = null;
                reject(new Error('PDF processing is unavailable. Check your connection and retry.'));
            };
            document.head.appendChild(script);
        });
    }
    return pdfJsLoadingPromise;
}

function setComScanStatus(state, progress, title, description) {
    const value = Math.round(Math.max(0, Math.min(100, progress)));
    document.getElementById('comScanChip').dataset.state = state;
    document.getElementById('comScanChipText').textContent = state === 'scanning'
        ? 'Processing'
        : state === 'complete' ? 'Processed' : state === 'error' ? 'Needs retry' : 'Ready to scan';
    document.getElementById('comScanStatus').dataset.state = state;
    document.getElementById('comScanStatusTitle').textContent = title;
    document.getElementById('comScanStatusDescription').textContent = description;
    document.getElementById('comProgressValue').textContent = `${value}%`;
    document.getElementById('comProgressTrack').setAttribute('aria-valuenow', String(value));
    document.getElementById('comProgressFill').style.width = `${value}%`;
}

function invalidateComScan() {
    comScanGeneration += 1;
    if (activeOcrWorker) {
        const worker = activeOcrWorker;
        activeOcrWorker = null;
        worker.terminate().catch(error => console.error('Unable to stop previous document scan:', error));
    }
}

async function imageFileToCanvas(image) {
    const maxDimension = 2400;
    const scale = Math.min(1, maxDimension / Math.max(image.naturalWidth, image.naturalHeight));
    const canvas = document.createElement('canvas');
    canvas.width = Math.max(1, Math.round(image.naturalWidth * scale));
    canvas.height = Math.max(1, Math.round(image.naturalHeight * scale));
    const context = canvas.getContext('2d');
    if (!context) throw new Error('This browser could not prepare the image for scanning.');
    context.drawImage(image, 0, 0, canvas.width, canvas.height);
    return canvas;
}

function escapeHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function normalizeScanText(value) {
    return String(value || '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, ' ')
        .trim();
}

function scanContainsValue(scanText, value, minimumMatch = 0.6) {
    const scanWords = new Set(normalizeScanText(scanText).split(/\s+/));
    const valueWords = normalizeScanText(value)
        .split(/\s+/)
        .filter(word => word.length > 2);

    if (!valueWords.length) return false;
    const matchedWords = valueWords.filter(word => scanWords.has(word));
    return matchedWords.length / valueWords.length >= minimumMatch;
}

function scanContainsCourse(scanText, course) {
    const commonCourseWords = new Set(['bachelor', 'science', 'associate', 'of', 'in', 'the', 'and', 'major']);
    const courseWords = normalizeScanText(course)
        .split(/\s+/)
        .filter(word => word.length > 2 && !commonCourseWords.has(word));
    const scanWords = new Set(normalizeScanText(scanText).split(/\s+/));

    if (!courseWords.length) return scanContainsValue(scanText, course, 0.8);
    return courseWords.every(word => scanWords.has(word));
}

function scanContainsFullName(scanText) {
    const firstName = document.getElementById('first_name')?.value || '';
    const middleName = document.getElementById('middle_name')?.value || '';
    const lastName = document.getElementById('last_name')?.value || '';
    const enteredNameWords = normalizeScanText(`${firstName} ${middleName} ${lastName}`).split(/\s+/).filter(Boolean);
    const scannedNameWords = new Set(normalizeScanText(scanText).split(/\s+/).filter(Boolean));

    return enteredNameWords.length > 0 && enteredNameWords.every(word => scannedNameWords.has(word));
}

function showScanMismatchAlert(scanText, selectedCourse) {
    const mismatches = getScanMismatches(scanText, selectedCourse);

    scanValidation = {
        completed: true,
        valid: mismatches.length === 0,
        text: scanText
    };

    if (mismatches.length) {
        signupModule.showErrorAlert(formatMismatchMessage(mismatches));
    }
}

function getScanMismatches(scanText, selectedCourse) {
    const firstName = document.getElementById('first_name')?.value || '';
    const lastName = document.getElementById('last_name')?.value || '';
    const mismatches = [];

    if (!scanContainsValue(scanText, firstName, 1)) {
        mismatches.push('First Name');
    }
    if (!scanContainsValue(scanText, lastName, 1)) {
        mismatches.push('Last Name');
    }
    if (selectedCourse && !scanContainsCourse(scanText, selectedCourse)) {
        mismatches.push('Course');
    }
    return mismatches;
}

function formatMismatchMessage(mismatches) {
    const labels = mismatches.join(', ').replace(/, ([^,]*)$/, ' and $1');
    return `${labels} ${mismatches.length === 1 ? 'does' : 'do'} not match the uploaded document. Please correct it before signing up.`;
}

function extractCourseFromText(text) {
    const match = text.match(/(?:Course|Program|Degree)[\s:]*([^\n]+)/i);
    return match ? match[1].trim() : '';
}

function extractNameFromText(text) {
    // Try to find name patterns
    const namePatterns = [
        /(?:Name|NAME|Student Name|STUDENT NAME)[\s:]*([A-Z][a-z]+ [A-Z][a-z]+(?:\s+[A-Z][a-z]+)?)/i,
        /^([A-Z][a-z]+ [A-Z][a-z]+(?:\s+[A-Z][a-z]+)?)$/m
    ];
    
    for (const pattern of namePatterns) {
        const match = text.match(pattern);
        if (match) {
            let name = match[1].trim();
            // Ensure it's a reasonable name length
            if (name.length >= 3 && name.split(' ').length >= 2) {
                return name;
            }
        }
    }
    return '';
}

function extractStudentNumberFromText(text) {
    // Try to find student number patterns
    const numberPatterns = [
        /(?:Student Number|STUDENT NUMBER|Student No|Student ID|ID|Stud No)[\s:]*(\d{2}-\d{5}|\d{8,10})/i,
        /\b(\d{2}-\d{5})\b/,
        /\b(\d{8,10})\b/
    ];
    
    for (const pattern of numberPatterns) {
        const match = text.match(pattern);
        if (match) {
            const number = match[1] || match[0];
            // Validate it's likely a student number (reasonable length)
            if (number.replace(/\D/g, '').length >= 5) {
                return number;
            }
        }
    }
    return '';
}

function extractAddressFromText(text) {
    // Try to find address patterns and stop before phone number
    let address = '';
    
    // Find address labels
    const addressPatterns = [
        /(?:Address|ADDRESS|Addr)[\s:]*([^\n]+)/i,
        /(?:Street|St\.|No\.|Lot|Blk)[\s:]*([^\n]+)/i
    ];
    
    for (const pattern of addressPatterns) {
        const match = text.match(pattern);
        if (match) {
            address = match[1].trim();
            break;
        }
    }
    
    if (address) {
        // Remove phone number from address if it's included
        address = address
            .replace(/\s*Phone[\s:]*[\d\-\s()]+$/i, '')
            .replace(/\s*Tel[\s:]*[\d\-\s()]+$/i, '')
            .replace(/\s*Mobile[\s:]*[\d\-\s()]+$/i, '')
            .replace(/\s*Cell[\s:]*[\d\-\s()]+$/i, '')
            .replace(/\s*\d{3,}[\s\-()]*\d{3,}[\s\-()]*\d{3,}[\s\-()]*\d{3,}.*$/i, '')
            .trim();
    }
    
    return address;
}

function extractLevelFromText(text) {
    // Try to find level/year patterns
    const levelPatterns = [
        /(?:Level|LEVEL|Year|YEAR|Yr)[\s:]*(\d+(?:st|nd|rd|th)?|First|Second|Third|Fourth|1st|2nd|3rd|4th)/i,
        /\b(First|Second|Third|Fourth|1st|2nd|3rd|4th)[\s](?:Year|Yr)\b/i,
        /Year[\s:]*(\d+)/i
    ];
    
    for (const pattern of levelPatterns) {
        const match = text.match(pattern);
        if (match) {
            let level = (match[1] || match[0]).trim();
            // Standardize level output
            if (level.toLowerCase().includes('first') || level === '1' || level === '1st') return '1st Year';
            if (level.toLowerCase().includes('second') || level === '2' || level === '2nd') return '2nd Year';
            if (level.toLowerCase().includes('third') || level === '3' || level === '3rd') return '3rd Year';
            if (level.toLowerCase().includes('fourth') || level === '4' || level === '4th') return '4th Year';
            return level;
        }
    }
    return '';
}

function extractPhoneNumberFromText(text) {
    // Try to find phone number patterns
    const phonePatterns = [
        /(?:Phone|PHONE|Tel|Mobile|Cell)[\s:]*([+\d\s\-()]+?)(?:\n|$|Phone|Tel|Mobile|Email|Address)/i,
        /(?:Phone|PHONE|Tel)[\s:]*([+\d\s\-()]+?)(?:\s{2,}|$)/,
        /\b((?:\+63|09)\d{9,10})\b/,
        /\b(\d{3}[-.\s]?\d{3}[-.\s]?\d{4})\b/
    ];
    
    for (const pattern of phonePatterns) {
        const match = text.match(pattern);
        if (match) {
            let phone = (match[1] || match[0]).trim();
            // Clean up phone number - remove extra spaces and keep only valid characters
            phone = phone.replace(/\s+/g, '').replace(/[^\d\-\+()]/g, '');
            if (phone.length >= 10) {
                return phone;
            }
        }
    }
    return '';
}

// ====== IMAGE VIEWER MODAL ======
function openImageViewer(imageSrc) {
    const modal = document.getElementById('imageViewerModal');
    const imageViewer = document.getElementById('imageViewerLarge');
    imageViewer.src = imageSrc;
    modal.style.display = 'flex';
}

function closeImageViewer() {
    const modal = document.getElementById('imageViewerModal');
    modal.style.display = 'none';
}

// Close modal when clicking outside the image
window.addEventListener('click', function(event) {
    const modal = document.getElementById('imageViewerModal');
    if (event.target === modal) {
        modal.style.display = 'none';
    }
});


// ====== CUSTOM DROPDOWN FUNCTIONS ======
function toggleCourseDropdown(event) {
    event.stopPropagation();
    const dropdown = event.currentTarget;
    // Close other dropdowns
    document.querySelectorAll('.custom-dropdown').forEach(d => {
        if (d !== dropdown) d.classList.remove('active');
    });
    const isOpening = !dropdown.classList.contains('active');
    dropdown.classList.toggle('active');

    if (isOpening && document.body.classList.contains('student-signup')) {
        window.setTimeout(() => {
            if (!dropdown.classList.contains('active')) return;

            const menu = dropdown.querySelector('#courseDropdownMenu');
            const menuBounds = menu.getBoundingClientRect();
            const header = document.querySelector('.logo-section');
            const topBoundary = (header ? header.getBoundingClientRect().bottom : 0) + 8;
            const bottomBoundary = window.innerHeight - 16;
            const overflow = menuBounds.bottom - bottomBoundary;
            const availableScroll = Math.max(0, menuBounds.top - topBoundary);

            if (overflow > 0 && availableScroll > 0) {
                window.scrollBy({
                    top: Math.min(overflow, availableScroll),
                    behavior: 'smooth'
                });
            }
        }, 420);
    }
}

function selectCourse(courseName, event) {
    event.stopPropagation();
    // Find the parent dropdown
    const dropdown = event.target.closest('.custom-dropdown');
    document.getElementById('courseSelected').textContent = courseName;
    document.getElementById('course').value = courseName;
    if (dropdown) dropdown.classList.remove('active');
    signupModule.saveCurrentStudentForm();
}

function toggleGenderDropdown(event) {
    event.stopPropagation();
    const dropdown = event.currentTarget;
    // Close other dropdowns
    document.querySelectorAll('.custom-dropdown').forEach(d => {
        if (d !== dropdown) d.classList.remove('active');
    });
    dropdown.classList.toggle('active');
}

function selectGender(genderName, event) {
    event.stopPropagation();
    const dropdown = event.target.closest('.custom-dropdown');
    if (!dropdown) return;

    const selectedLabel = dropdown.querySelector('#genderSelected');
    const hiddenInput = dropdown.parentElement.querySelector('#gender');
    if (selectedLabel) selectedLabel.textContent = genderName;
    if (hiddenInput) hiddenInput.value = genderName;
    if (document.body.classList.contains('teacher-signup')) signupModule.saveCurrentTeacherForm();
    if (document.body.classList.contains('student-signup')) signupModule.saveCurrentStudentForm();
    dropdown.classList.remove('active');
}

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
    document.querySelectorAll('.custom-dropdown').forEach(dropdown => {
        if (dropdown && !dropdown.contains(event.target)) {
            dropdown.classList.remove('active');
        }
    });
});

// Search functionality for course dropdown
document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('courseSearchInput');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const items = document.querySelectorAll('.dropdown-item');
            const groups = document.querySelectorAll('.dropdown-group');
            
            items.forEach(item => {
                const text = item.textContent.toLowerCase();
                if (text.includes(searchTerm)) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
            
            // Hide groups that have no visible items
            groups.forEach(group => {
                const visibleItems = group.querySelectorAll('.dropdown-item[style="display: block"], .dropdown-item:not([style*="display: none"])');
                const hasVisibleItems = Array.from(visibleItems).some(item => item.style.display !== 'none');
                
                if (searchTerm.length > 0) {
                    // When searching, only show groups with visible items
                    const allItems = group.querySelectorAll('.dropdown-item');
                    const anyVisible = Array.from(allItems).some(item => item.style.display !== 'none');
                    group.style.display = anyVisible ? 'block' : 'none';
                } else {
                    // When not searching, show all groups
                    group.style.display = 'block';
                }
            });
        });
    }
});

// ====== INITIALIZE ON PAGE LOAD ======
document.addEventListener('DOMContentLoaded', () => {
    // Focus on first name input
    const firstNameInput = document.getElementById('first_name');
    if (!firstNameInput) return;

    if (document.body.classList.contains('teacher-signup')) {
        ['first_name', 'middle_name', 'last_name', 'email', 'password', 'department'].forEach((field) => {
            const input = document.getElementById(field);
            if (input) input.addEventListener('input', signupModule.saveCurrentTeacherForm);
        });

        const savedData = sessionStorage.getItem('utosapp_teacher_signup');
        if (savedData) {
            try {
                const formData = JSON.parse(savedData);
                ['first_name', 'middle_name', 'last_name', 'email', 'password', 'department'].forEach((field) => {
                    const input = document.getElementById(field);
                    if (input && formData[field] !== undefined) input.value = formData[field];
                });

                const genderInput = document.getElementById('gender');
                const genderLabel = document.getElementById('genderSelected');
                if (genderInput && formData.gender) {
                    genderInput.value = formData.gender;
                    if (genderLabel) genderLabel.textContent = formData.gender;
                }
                if (formData.password) signupModule.checkPasswordStrength(formData.password);
            } catch (error) {
                sessionStorage.removeItem('utosapp_teacher_signup');
            }
        }

        window.addEventListener('beforeunload', signupModule.saveCurrentTeacherForm);
    } else if (document.body.classList.contains('student-signup')) {
        document.getElementById('studentSignupForm').addEventListener('input', signupModule.saveCurrentStudentForm);
        document.getElementById('studentSignupForm').addEventListener('change', signupModule.saveCurrentStudentForm);
        signupModule.restoreStudentForm();
        window.addEventListener('beforeunload', signupModule.saveCurrentStudentForm);
    }

    firstNameInput.focus();
});

window.addEventListener('pageshow', () => {
    if (document.body.classList.contains('teacher-signup') && !sessionStorage.getItem('utosapp_teacher_signup')) {
        signupModule.clearTeacherForm();
    }
});
