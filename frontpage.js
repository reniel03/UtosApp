import express from 'express';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const FIREBASE_WEB_API_KEY = 'AIzaSyDRUJHWP0mnZNepGMgZU8ESu_Pq2KPR-2U';
const FIREBASE_DB_URL = 'https://utosapp-fd977-default-rtdb.firebaseio.com';
const FIREBASE_AUTH_URL = 'https://identitytoolkit.googleapis.com/v1/accounts:signInWithPassword';
const FIREBASE_UPDATE_PASSWORD_URL = 'https://identitytoolkit.googleapis.com/v1/accounts:update';

const router = express.Router();

// Frontpage route
router.get('/', (req, res) => {
    res.sendFile(path.join(__dirname, '../public/frontpage.html'));
});

// Login redirect route (from carousel/button)
router.get('/login', (req, res) => {
    // Serve login page
    res.sendFile(path.join(__dirname, '../public/login.html'));
});

router.get('/teacher-dashboard', (req, res) => {
    if (!req.session?.email || req.session.user_role !== 'teacher') {
        return res.redirect('/login');
    }

    res.sendFile(path.join(__dirname, '../public/teacher-dashboard.html'));
});

router.get('/admin-dashboard', (req, res) => {
    res.sendFile(path.join(__dirname, '../public/admin-dashboard.html'));
});

router.get('/admin-tasks', (req, res) => {
    if (!req.session?.email || req.session.user_role !== 'admin') {
        return res.redirect('/login');
    }

    res.sendFile(path.join(__dirname, '../public/admin-tasks.html'));
});
router.get('/admin-user-tasks', (req, res) => {
    if (!req.session?.email || req.session.user_role !== 'admin') {
        return res.redirect('/login');
    }

    res.sendFile(path.join(__dirname, '../public/admin-user-tasks.html'));
});
router.get(['/admin-messages', '/admin-messages.html'], (req, res) => {
    if (!req.session?.email || req.session.user_role !== 'admin') {
        return res.redirect('/login');
    }

    res.sendFile(path.join(__dirname, '../public/admin-messages.html'));
});
router.get('/admin-users', (req, res) => {
    if (!req.session?.email || req.session.user_role !== 'admin') {
        return res.redirect('/login');
    }

    res.sendFile(path.join(__dirname, '../public/admin-users.html'));
});

router.get('/admin-transactions', (req, res) => {
    if (!req.session?.email || req.session.user_role !== 'admin') {
        return res.redirect('/login');
    }

    res.sendFile(path.join(__dirname, '../public/admin-transactions.html'));
});

router.get('/teacher-assigned-tasks', (req, res) => {
    if (!req.session?.email || req.session.user_role !== 'teacher') {
        return res.redirect('/login');
    }

    res.sendFile(path.join(__dirname, '../public/teacher-assigned-tasks.html'));
});

router.get('/teacher-payment-method.html', (req, res) => {
    if (!req.session?.email || req.session.user_role !== 'teacher') {
        return res.redirect('/login');
    }

    res.sendFile(path.join(__dirname, '../public/teacher-payment-method.html'));
});

router.get('/student-home', (req, res) => {
    if (!req.session?.email || req.session.user_role !== 'student') {
        return res.redirect('/login');
    }

    res.sendFile(path.join(__dirname, '../public/student-home.html'));
});

router.get('/student-tasks', (req, res) => {
    if (!req.session?.email || req.session.user_role !== 'student') {
        return res.redirect('/login');
    }

    res.sendFile(path.join(__dirname, '../public/student-tasks.html'));
});

router.get(['/student-submit-task', '/student-submit-task.html'], (req, res) => {
    if (!req.session?.email || req.session.user_role !== 'student') {
        return res.redirect('/login');
    }

    res.sendFile(path.join(__dirname, '../public/student-submit-task.html'));
});

router.get('/student-account', (req, res) => {
    if (!req.session?.email || req.session.user_role !== 'student') {
        return res.redirect('/login');
    }

    res.sendFile(path.join(__dirname, '../public/student-account.html'));
});

router.post('/api/logout', (req, res) => {
    req.session.destroy((error) => {
        if (error) return res.status(500).json({ success: false, message: 'Could not log out' });
        res.clearCookie('connect.sid');
        res.json({ success: true });
    });
});

router.get('/api/student-session', async (req, res) => {
    if (!req.session?.email || req.session.user_role !== 'student') {
        return res.status(401).json({ success: false, message: 'Student session required' });
    }

    let student = {};
    try {
        const response = await fetch(`${FIREBASE_DB_URL}/students.json`);
        const students = response.ok ? await response.json() : {};
        student = Object.values(students || {}).find((candidate) => (candidate.email || '').toLowerCase() === req.session.email.toLowerCase()) || {};
    } catch {
        student = {};
    }

    res.json({
        success: true,
        student: {
            first_name: student.first_name || req.session.first_name || '',
            middle_name: student.middle_name || req.session.middle_name || '',
            last_name: student.last_name || req.session.last_name || '',
            email: req.session.email || '',
            course: student.course || req.session.course || '',
            student_id: student.student_id || student.studentId || req.session.student_id || '',
            address: student.address || req.session.address || '',
            level: student.level || student.year_level || req.session.level || req.session.year_level || '',
            year_level: student.year_level || student.level || req.session.year_level || req.session.level || '',
            phone: student.phone || student.phone_number || req.session.phone || '',
            username: student.username || req.session.username || ''
        }
    });
});

router.patch('/api/student-username', async (req, res) => {
    if (!req.session?.email || req.session.user_role !== 'student') {
        return res.status(401).json({ success: false, message: 'Student session required.' });
    }

    const username = String(req.body?.username || '').trim().toLowerCase();
    if (username && !/^[a-z0-9._-]{3,30}$/.test(username)) {
        return res.status(400).json({
            success: false,
            message: 'Username must be 3–30 characters and use only letters, numbers, dots, underscores, or hyphens.'
        });
    }
    if (username === 'admin') {
        return res.status(400).json({ success: false, message: 'That username is reserved.' });
    }

    try {
        const [studentsResponse, teachersResponse] = await Promise.all([
            fetch(`${FIREBASE_DB_URL}/students.json`),
            fetch(`${FIREBASE_DB_URL}/teachers.json`)
        ]);
        if (!studentsResponse.ok || !teachersResponse.ok) {
            return res.status(502).json({ success: false, message: 'Could not check username availability.' });
        }

        const [students, teachers] = await Promise.all([
            studentsResponse.json(),
            teachersResponse.json()
        ]);
        const studentEntries = Object.entries(students || {});
        const studentEntry = studentEntries.find(([, student]) => (
            String(student?.email || '').trim().toLowerCase() === req.session.email.toLowerCase()
        ));
        if (!studentEntry) {
            return res.status(404).json({ success: false, message: 'Student account was not found.' });
        }

        const [studentKey] = studentEntry;
        if (username) {
            const duplicateStudent = studentEntries.some(([key, student]) => (
                key !== studentKey && String(student?.username || '').trim().toLowerCase() === username
            ));
            const duplicateTeacher = Object.values(teachers || {}).some((teacher) => (
                String(teacher?.username || '').trim().toLowerCase() === username
            ));
            if (duplicateStudent || duplicateTeacher) {
                return res.status(409).json({ success: false, message: 'That username is already in use.' });
            }
        }

        const updateResponse = await fetch(`${FIREBASE_DB_URL}/students/${encodeURIComponent(studentKey)}.json`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ username: username || null })
        });
        if (!updateResponse.ok) {
            return res.status(502).json({ success: false, message: 'Could not save your username.' });
        }

        req.session.username = username;
        await new Promise((resolve, reject) => req.session.save((error) => error ? reject(error) : resolve()));
        return res.json({ success: true, username, message: 'Username updated.' });
    } catch (error) {
        console.error('Student username update error:', error);
        return res.status(500).json({ success: false, message: 'Could not save your username. Please try again.' });
    }
});

router.patch('/api/student-profile', async (req, res) => {
    if (!req.session?.email || req.session.user_role !== 'student') {
        return res.status(401).json({ success: false, message: 'Student session required' });
    }

    try {
        const response = await fetch(`${FIREBASE_DB_URL}/students.json`);
        if (!response.ok) return res.status(502).json({ success: false, message: 'Could not load student profile' });
        const students = await response.json();
        const studentEmail = (req.session.email || '').toLowerCase();
        const sessionStudentId = req.session.student_id || '';
        const sessionFirebaseUid = req.session.firebase_uid || '';
        const entry = Object.entries(students || {}).find(([, student]) => {
            const matchesEmail = (student.email || '').toLowerCase() === studentEmail;
            const matchesStudentId = String(student.student_id || student.studentId || '').trim() && String(student.student_id || student.studentId || '').trim() === String(sessionStudentId).trim();
            const matchesFirebaseUid = String(student.firebase_uid || '').trim() && String(student.firebase_uid || '').trim() === String(sessionFirebaseUid).trim();
            return matchesEmail || matchesStudentId || matchesFirebaseUid;
        });
        if (!entry) return res.status(404).json({ success: false, message: 'Student profile not found' });

        const [studentKey] = entry;
        const editableFields = ['first_name', 'middle_name', 'last_name', 'email', 'address', 'level', 'phone', 'course'];
        const updates = Object.fromEntries(editableFields.filter((field) => field in req.body).map((field) => [field, String(req.body[field] || '').trim()]));
        if ('phone' in updates && !/^\d{11}$/.test(updates.phone)) {
            return res.status(400).json({ success: false, message: 'Phone number must contain exactly 11 digits.' });
        }
        if (updates.level) {
            updates.year_level = updates.level;
            req.session.level = updates.level;
            req.session.year_level = updates.level;
        }
        if (updates.email) {
            req.session.email = updates.email.toLowerCase();
        }
        const updateResponse = await fetch(`${FIREBASE_DB_URL}/students/${encodeURIComponent(studentKey)}.json`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(updates)
        });
        if (!updateResponse.ok) return res.status(502).json({ success: false, message: 'Could not save student profile' });
        await new Promise((resolve, reject) => req.session.save((error) => error ? reject(error) : resolve()));
        res.json({ success: true });
    } catch (error) {
        console.error('Student profile update error:', error);
        res.status(500).json({ success: false, message: 'Could not save student profile' });
    }
});

router.post('/api/student-change-password', async (req, res) => {
    if (!req.session?.email || req.session.user_role !== 'student') {
        return res.status(401).json({ success: false, message: 'Student session required' });
    }

    try {
        const currentPassword = String(req.body.currentPassword || '').trim();
        const newPassword = String(req.body.newPassword || '').trim();
        const confirmPassword = String(req.body.confirmPassword || '').trim();

        if (!currentPassword || !newPassword || !confirmPassword) {
            return res.status(400).json({ success: false, message: 'All password fields are required' });
        }

        if (newPassword.length < 6) {
            return res.status(400).json({ success: false, message: 'New password must be at least 6 characters long' });
        }

        if (newPassword !== confirmPassword) {
            return res.status(400).json({ success: false, message: 'Passwords do not match' });
        }

        const studentsResponse = await fetch(`${FIREBASE_DB_URL}/students.json`);
        if (!studentsResponse.ok) {
            return res.status(502).json({ success: false, message: 'Could not load student profile' });
        }

        const students = await studentsResponse.json();
        const studentEmail = (req.session.email || '').toLowerCase();
        const sessionStudentId = String(req.session.student_id || '').trim();
        const sessionFirebaseUid = String(req.session.firebase_uid || '').trim();
        const entry = Object.entries(students || {}).find(([, student]) => {
            const matchesEmail = (student.email || '').toLowerCase() === studentEmail;
            const studentId = String(student.student_id || student.studentId || '').trim();
            const firebaseUid = String(student.firebase_uid || '').trim();
            return matchesEmail || (sessionStudentId && studentId === sessionStudentId) || (sessionFirebaseUid && firebaseUid === sessionFirebaseUid);
        });
        if (!entry) {
            return res.status(404).json({ success: false, message: 'Student profile not found' });
        }

        const [studentKey, student] = entry;
        const savedPassword = String(student.password || '').trim();
        const sessionPassword = String(req.session.firebase_password || '').trim();
        let passwordVerified = savedPassword === currentPassword || sessionPassword === currentPassword;

        // Migrated accounts may store the authoritative password in Firebase Auth.
        if (!passwordVerified) {
            const authResponse = await fetch(`${FIREBASE_AUTH_URL}?key=${FIREBASE_WEB_API_KEY}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    email: String(student.email || req.session.email).trim().toLowerCase(),
                    password: currentPassword,
                    returnSecureToken: true
                })
            });
            passwordVerified = authResponse.ok;
        }

        if (!passwordVerified) {
            return res.status(401).json({ success: false, message: 'Current password is wrong' });
        }

        if (req.session.firebase_id_token) {
            const authUpdateResponse = await fetch(`${FIREBASE_UPDATE_PASSWORD_URL}?key=${FIREBASE_WEB_API_KEY}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    idToken: req.session.firebase_id_token,
                    password: newPassword,
                    returnSecureToken: true
                })
            });
            if (!authUpdateResponse.ok) {
                return res.status(502).json({ success: false, message: 'Could not update password' });
            }
            const authUpdate = await authUpdateResponse.json();
            req.session.firebase_id_token = authUpdate.idToken || req.session.firebase_id_token;
        }

        const updateResponse = await fetch(`${FIREBASE_DB_URL}/students/${encodeURIComponent(studentKey)}.json`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ password: newPassword })
        });

        if (!updateResponse.ok) {
            return res.status(502).json({ success: false, message: 'Could not update password' });
        }

        req.session.firebase_password = newPassword;
        await new Promise((resolve, reject) => req.session.save((error) => error ? reject(error) : resolve()));
        res.json({ success: true, message: 'Password updated' });
    } catch (error) {
        console.error('Student password update error:', error);
        res.status(500).json({ success: false, message: 'Could not update password' });
    }
});

router.get('/api/teacher-session', async (req, res) => {
    if (!req.session?.email || req.session.user_role !== 'teacher') {
        return res.status(401).json({ success: false, message: 'Teacher session required' });
    }

    let gender = '';
    let username = req.session.username || '';
    {
        try {
            const response = await fetch(`${FIREBASE_DB_URL}/teachers.json`);
            if (!response.ok) throw new Error(`Firebase returned ${response.status}`);
            const teachers = await response.json() || {};
            const teacher = Object.values(teachers || {}).find((candidate) => (
                candidate.email || '').toLowerCase() === req.session.email.toLowerCase()
            );
            gender = teacher?.gender || req.session.gender || '';
            username = teacher?.username || username;
            if (gender) {
                req.session.gender = gender;
                await new Promise((resolve) => req.session.save(() => resolve()));
            }
        } catch (error) {
            console.error('Could not load teacher profile details:', error);
        }
    }

    res.json({
        success: true,
        teacher: {
            first_name: req.session.first_name || '',
            middle_name: req.session.middle_name || '',
            last_name: req.session.last_name || '',
            gender,
            username,
            email: req.session.email || ''
        }
    });
});

router.patch('/api/teacher-username', async (req, res) => {
    if (!req.session?.email || req.session.user_role !== 'teacher') {
        return res.status(401).json({ success: false, message: 'Teacher session required.' });
    }

    const username = String(req.body?.username || '').trim().toLowerCase();
    if (username && !/^[a-z0-9._-]{3,30}$/.test(username)) {
        return res.status(400).json({
            success: false,
            message: 'Username must be 3–30 characters and use only letters, numbers, dots, underscores, or hyphens.'
        });
    }
    if (username === 'admin') {
        return res.status(400).json({ success: false, message: 'That username is reserved.' });
    }

    try {
        const teachersResponse = await fetch(`${FIREBASE_DB_URL}/teachers.json`);
        if (!teachersResponse.ok) {
            return res.status(502).json({ success: false, message: 'Could not check username availability.' });
        }
        const teachers = await teachersResponse.json() || {};
        const teacherEntry = Object.entries(teachers).find(([, teacher]) => (
            String(teacher?.email || '').trim().toLowerCase() === req.session.email.toLowerCase()
        ));
        if (!teacherEntry) {
            return res.status(404).json({ success: false, message: 'Teacher account was not found.' });
        }

        const [teacherKey] = teacherEntry;
        if (username) {
            const studentsResponse = await fetch(`${FIREBASE_DB_URL}/students.json`);
            if (!studentsResponse.ok) {
                return res.status(502).json({ success: false, message: 'Could not check username availability.' });
            }
            const students = await studentsResponse.json() || {};

            const duplicateTeacher = Object.entries(teachers).some(([key, teacher]) => (
                key !== teacherKey && String(teacher?.username || '').trim().toLowerCase() === username
            ));
            const duplicateStudent = Object.values(students).some((student) => (
                String(student?.username || '').trim().toLowerCase() === username
            ));
            if (duplicateTeacher || duplicateStudent) {
                return res.status(409).json({ success: false, message: 'That username is already in use.' });
            }
        }

        const updateResponse = await fetch(`${FIREBASE_DB_URL}/teachers/${encodeURIComponent(teacherKey)}.json`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ username: username || null })
        });
        if (!updateResponse.ok) {
            return res.status(502).json({ success: false, message: 'Could not save your username.' });
        }

        req.session.username = username;
        await new Promise((resolve, reject) => req.session.save((error) => error ? reject(error) : resolve()));
        return res.json({ success: true, username, message: 'Username updated.' });
    } catch (error) {
        console.error('Teacher username update error:', error);
        return res.status(500).json({ success: false, message: 'Could not save your username. Please try again.' });
    }
});

export default router;
