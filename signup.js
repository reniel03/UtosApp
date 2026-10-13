import 'dotenv/config';
import express from 'express';
import nodemailer from 'nodemailer';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const router = express.Router();

// Firebase credentials
const FIREBASE_WEB_API_KEY = 'AIzaSyDRUJHWP0mnZNepGMgZU8ESu_Pq2KPR-2U';
const FIREBASE_AUTH_URL = 'https://identitytoolkit.googleapis.com/v1/accounts:signUp';
const FIREBASE_LOGIN_URL = 'https://identitytoolkit.googleapis.com/v1/accounts:signInWithPassword';
const FIREBASE_DB_URL = 'https://utosapp-fd977-default-rtdb.firebaseio.com';

const getDatabaseKey = (email) => email.replace(/[.@]/g, '_');

const saveSession = (req) => new Promise((resolve, reject) => {
    req.session.save((error) => error ? reject(error) : resolve());
});

const emailExistsInRealtimeDatabase = async (email) => {
    const accountCollections = await Promise.all(['students', 'teachers'].map(async (collection) => {
        const response = await fetch(`${FIREBASE_DB_URL}/${collection}.json`);
        if (!response.ok) {
            throw new Error(`Could not check existing ${collection} accounts in Firebase.`);
        }
        return response.json();
    }));

    return accountCollections.some((accounts) =>
        Object.values(accounts || {}).some((account) =>
            String(account?.email || '').trim().toLowerCase() === email
        )
    );
};

const mailTransporter = process.env.GMAIL_USER && process.env.GMAIL_APP_PASSWORD
    ? nodemailer.createTransport({
        service: 'gmail',
        auth: {
            user: process.env.GMAIL_USER,
            pass: process.env.GMAIL_APP_PASSWORD
        }
    })
    : null;

const buildVerificationEmail = (code, userLabel = 'teacher') => ({
    subject: `UtosApp ${userLabel} verification code`,
    text: `Your UtosApp ${userLabel} verification code is ${code}. It expires in 3 minutes.`,
    attachments: [{
        filename: 'utosapp-logo-blue.png',
        path: path.join(__dirname, '../public/utosapp_logo_new.png'),
        cid: 'utosapp-logo-blue-20260906@utosapp',
        contentType: 'image/png',
        contentDisposition: 'inline'
    }],
    html: `
        <div style="margin:0;padding:32px 16px;background:#f3f5fb;font-family:Arial,sans-serif;color:#1d2040;">
            <div style="max-width:520px;margin:0 auto;background:#ffffff;border:1px solid #dfe3f2;border-radius:12px;overflow:hidden;">
                <div style="padding:20px;background:#0A1172;color:#ffffff;text-align:center;">
                    <img src="cid:utosapp-logo-blue-20260906@utosapp" alt="UtosApp" style="display:block;width:120px;height:120px;object-fit:contain;margin:0 auto;background:#ffffff;border-radius:8px;">
                    <div style="margin-top:6px;font-size:14px;">${userLabel} email verification</div>
                </div>
                <div style="padding:32px 28px;text-align:center;">
                    <h1 style="margin:0 0 12px;color:#0A1172;font-size:24px;">Verify your email</h1>
                    <p style="margin:0;color:#555;line-height:1.5;">Use the verification code below to complete your ${userLabel} signup.</p>
                    <div style="margin:28px 0;padding:18px 12px;border:2px solid #0A1172;border-radius:8px;background:#f7f8ff;color:#0A1172;font-size:36px;font-weight:700;letter-spacing:8px;">${code}</div>
                    <p style="margin:0;color:#777;font-size:14px;">This code expires in <strong>3 minutes</strong>.</p>
                </div>
                <div style="padding:16px 28px;background:#f7f7f9;color:#777;text-align:center;font-size:12px;">If you did not request this code, you can ignore this email.</div>
            </div>
        </div>`
});

/**
 * GET /signup - Serve signup choice page
 */
router.get('/signup', (req, res) => {
    res.sendFile(path.join(__dirname, '../public/signup.html'));
});

/**
 * GET /signup-teacher - Serve teacher signup form
 */
router.get('/signup-teacher', (req, res) => {
    res.sendFile(path.join(__dirname, '../public/signup-teacher.html'));
});

/**
 * GET /signup-student - Serve student signup form
 */
router.get('/signup-student', (req, res) => {
    res.sendFile(path.join(__dirname, '../public/signup-student.html'));
});

router.get('/verify-teacher', (req, res) => {
    res.sendFile(path.join(__dirname, '../public/verify-teacher.html'));
});

router.get('/verify-student', (req, res) => {
    res.sendFile(path.join(__dirname, '../public/verify-teacher.html'));
});

/**
 * POST /signup-teacher - Handle teacher registration using Firebase
 */
router.post('/signup-teacher', async (req, res) => {
    try {
        let { first_name, middle_name, last_name, gender, email, password, department } = req.body;
        
        // Normalize email
        email = (email || '').trim().toLowerCase();
        password = (password || '').trim();

        console.log('\n🔥 ======================');
        console.log('🔥 TEACHER SIGNUP START');
        console.log('🔥 ======================');
        console.log('📝 Received data:', { 
            first_name, 
            last_name, 
            email,
            email_length: email.length,
            password: `"${password}"`,
            password_length: password.length
        });

        if (!first_name || !last_name || !email || !password) {
            console.log('❌ Missing required fields');
            return res.status(400).json({
                success: false,
                message: 'All required fields must be filled'
            });
        }

        console.log('✅ All fields present');

        console.log('🔍 Checking whether email already exists in Firebase...');
        if (await emailExistsInRealtimeDatabase(email)) {
            console.log('❌ Email already registered');
            return res.status(409).json({
                success: false,
                message: 'This email already exists. Please use a different email address.'
            });
        }

        console.log('✅ Email is unique');

        if (!mailTransporter) {
            return res.status(503).json({
                success: false,
                message: 'Gmail verification is not configured. Set GMAIL_USER and GMAIL_APP_PASSWORD, then restart the server.'
            });
        }

        // Keep the account pending until the email code is verified.
        const teacherData = {
            email,
            first_name,
            middle_name: middle_name || '',
            last_name,
            gender: gender || 'Not specified',
            department: department || '',
            created_at: new Date().toISOString()
        };

        const dbKey = getDatabaseKey(email);

        const verificationCode = String(Math.floor(100000 + Math.random() * 900000));
        req.session.pending_teacher_verification = {
            code: verificationCode,
            email,
            password,
            expires_at: Date.now() + 3 * 60 * 1000,
            teacher_data: teacherData,
            db_key: dbKey
        };

        await saveSession(req);

        await mailTransporter.sendMail({
            from: `"${process.env.GMAIL_SENDER_NAME || 'UtosApp'}" <${process.env.GMAIL_USER}>`,
            to: email,
            ...buildVerificationEmail(verificationCode)
        });

        console.log('✉️ Teacher verification code sent:', email);
        console.log('🔥 ======================\n');
        res.json({
            success: true,
            verificationRequired: true,
            message: 'Verification code sent to your email'
        });
    } catch (error) {
        console.error('❌ Teacher signup error:', error.message);
        console.error('   Stack:', error.stack);
        console.log('🔥 ======================\n');
        res.status(500).json({
            success: false,
            message: 'Server error during signup: ' + error.message
        });
    }
});

router.post('/verify-teacher', async (req, res) => {
    try {
        const pending = req.session.pending_teacher_verification;
        const code = String(req.body.code || '').trim();

        if (!pending || Date.now() > pending.expires_at) {
            return res.status(400).json({ success: false, message: 'Verification code expired. Please request a new code.' });
        }

        if (!/^\d{6}$/.test(code) || code !== pending.code) {
            return res.status(400).json({ success: false, message: 'Incorrect verification code.' });
        }

        const existingTeacherResponse = await fetch(`${FIREBASE_DB_URL}/teachers/${pending.db_key}.json`);
        if (!existingTeacherResponse.ok) {
            return res.status(500).json({ success: false, message: 'Could not check the teacher account.' });
        }

        const existingTeacher = await existingTeacherResponse.json();
        if (existingTeacher?.email?.toLowerCase() === pending.email) {
            return res.status(409).json({ success: false, message: 'This email is already registered.' });
        }

        const firebaseAuthResponse = await fetch(`${FIREBASE_AUTH_URL}?key=${FIREBASE_WEB_API_KEY}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                email: pending.email,
                password: pending.password,
                returnSecureToken: true
            })
        });
        const authData = await firebaseAuthResponse.json();

        let firebaseUser = authData;
        let usesLegacyPassword = false;
        if (!firebaseAuthResponse.ok && authData.error?.message === 'EMAIL_EXISTS') {
            const existingAccountResponse = await fetch(`${FIREBASE_LOGIN_URL}?key=${FIREBASE_WEB_API_KEY}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    email: pending.email,
                    password: pending.password,
                    returnSecureToken: true
                })
            });
            const existingAccount = await existingAccountResponse.json();
            if (!existingAccountResponse.ok) {
                usesLegacyPassword = true;
                firebaseUser = { localId: null };
            } else {
                firebaseUser = existingAccount;
            }
        } else if (!firebaseAuthResponse.ok) {
            return res.status(400).json({
                success: false,
                message: authData.error?.message || 'Failed to create account'
            });
        }

        const teacherData = {
            ...pending.teacher_data,
            firebase_uid: firebaseUser.localId,
            email_verified: true
        };

        if (usesLegacyPassword) {
            teacherData.password = pending.password;
            delete teacherData.firebase_uid;
        }

        const dbResponse = await fetch(`${FIREBASE_DB_URL}/teachers/${pending.db_key}.json`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(teacherData)
        });

        if (!dbResponse.ok) {
            return res.status(500).json({ success: false, message: 'Could not finalize teacher account.' });
        }

        req.session.email = pending.email;
        req.session.user_role = 'teacher';
        req.session.first_name = teacherData.first_name;
        req.session.last_name = teacherData.last_name;
        req.session.middle_name = teacherData.middle_name;
        req.session.firebase_uid = teacherData.firebase_uid;
        req.session.firebase_id_token = firebaseUser.idToken || null;
        req.session.firebase_refresh_token = firebaseUser.refreshToken || null;
        delete req.session.pending_teacher_verification;
        await saveSession(req);

        res.json({ success: true, message: 'Teacher email verified successfully' });
    } catch (error) {
        console.error('❌ Teacher verification error:', error.message);
        res.status(500).json({ success: false, message: 'Server error during verification' });
    }
});

router.post('/resend-teacher-code', async (req, res) => {
    try {
        const pending = req.session.pending_teacher_verification;

        if (!pending) {
            return res.status(400).json({ success: false, message: 'No pending teacher verification found. Please sign up again.' });
        }

        const verificationCode = String(Math.floor(100000 + Math.random() * 900000));
        pending.code = verificationCode;
        pending.expires_at = Date.now() + 3 * 60 * 1000;

        await saveSession(req);

        await mailTransporter.sendMail({
            from: `"${process.env.GMAIL_SENDER_NAME || 'UtosApp'}" <${process.env.GMAIL_USER}>`,
            to: pending.email,
            ...buildVerificationEmail(verificationCode, 'student')
        });

        res.json({ success: true, message: 'A new verification code was sent to your email.' });
    } catch (error) {
        console.error('❌ Resend verification error:', error.message);
        res.status(500).json({ success: false, message: 'Could not resend verification code.' });
    }
});

const completeStudentVerification = async (req, res) => {
    try {
        const pending = req.session.pending_student_verification;
        const code = String(req.body.code || '').trim();

        if (!pending || Date.now() > pending.expires_at) {
            return res.status(400).json({ success: false, message: 'Verification code expired. Please request a new code.' });
        }
        if (!/^\d{6}$/.test(code) || code !== pending.code) {
            return res.status(400).json({ success: false, message: 'Incorrect verification code.' });
        }

        const existingResponse = await fetch(`${FIREBASE_DB_URL}/students/${pending.db_key}.json`);
        if (!existingResponse.ok) {
            return res.status(500).json({ success: false, message: 'Could not check the student account.' });
        }
        const existingStudent = await existingResponse.json();
        if (existingStudent?.email?.toLowerCase() === pending.email) {
            return res.status(409).json({ success: false, message: 'This email is already registered.' });
        }

        const firebaseAuthResponse = await fetch(`${FIREBASE_AUTH_URL}?key=${FIREBASE_WEB_API_KEY}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email: pending.email, password: pending.password, returnSecureToken: true })
        });
        const authData = await firebaseAuthResponse.json();
        let firebaseUser = authData;
        let usesLegacyPassword = false;

        if (!firebaseAuthResponse.ok && authData.error?.message === 'EMAIL_EXISTS') {
            const existingAccountResponse = await fetch(`${FIREBASE_LOGIN_URL}?key=${FIREBASE_WEB_API_KEY}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email: pending.email, password: pending.password, returnSecureToken: true })
            });
            const existingAccount = await existingAccountResponse.json();
            if (existingAccountResponse.ok) {
                firebaseUser = existingAccount;
            } else {
                usesLegacyPassword = true;
                firebaseUser = { localId: null };
            }
        } else if (!firebaseAuthResponse.ok) {
            return res.status(400).json({ success: false, message: authData.error?.message || 'Failed to create account' });
        }

        const studentData = {
            ...pending.student_data,
            firebase_uid: firebaseUser.localId,
            email_verified: true
        };
        if (usesLegacyPassword) {
            studentData.password = pending.password;
            delete studentData.firebase_uid;
        }

        const dbResponse = await fetch(`${FIREBASE_DB_URL}/students/${pending.db_key}.json`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(studentData)
        });
        if (!dbResponse.ok) {
            return res.status(500).json({ success: false, message: 'Could not finalize student account.' });
        }

        req.session.email = pending.email;
        req.session.user_role = 'student';
        req.session.first_name = studentData.first_name;
        req.session.last_name = studentData.last_name;
        req.session.middle_name = studentData.middle_name;
        req.session.course = studentData.course;
        req.session.firebase_uid = studentData.firebase_uid || null;
        req.session.firebase_id_token = firebaseUser.idToken || null;
        req.session.firebase_refresh_token = firebaseUser.refreshToken || null;
        delete req.session.pending_student_verification;
        await saveSession(req);

        res.json({ success: true, message: 'Student email verified successfully' });
    } catch (error) {
        console.error('Student verification error:', error.message);
        res.status(500).json({ success: false, message: 'Server error during verification' });
    }
};

router.post('/verify-student', completeStudentVerification);

router.post('/resend-student-code', async (req, res) => {
    try {
        const pending = req.session.pending_student_verification;
        if (!pending) {
            return res.status(400).json({ success: false, message: 'No pending student verification found. Please sign up again.' });
        }

        pending.code = String(Math.floor(100000 + Math.random() * 900000));
        pending.expires_at = Date.now() + 3 * 60 * 1000;
        await saveSession(req);
        await mailTransporter.sendMail({
            from: `"${process.env.GMAIL_SENDER_NAME || 'UtosApp'}" <${process.env.GMAIL_USER}>`,
            to: pending.email,
            ...buildVerificationEmail(pending.code, 'student')
        });
        res.json({ success: true, message: 'A new verification code was sent to your email.' });
    } catch (error) {
        console.error('Student resend verification error:', error.message);
        res.status(500).json({ success: false, message: 'Could not resend verification code.' });
    }
});

/**
 * POST /signup-student - Handle student registration using Firebase
 */
router.post('/signup-student', async (req, res) => {
    try {
        let { first_name, middle_name, last_name, gender, email, course, password, student_id, address, level, phone } = req.body;
        
        // Normalize email
        email = (email || '').trim().toLowerCase();
        password = (password || '').trim();

        console.log('\n👨‍🎓 ======================');
        console.log('👨‍🎓 STUDENT SIGNUP START');
        console.log('👨‍🎓 ======================');
        console.log('📝 Received data:', { 
            first_name, 
            last_name, 
            email,
            email_length: email.length,
            password: `"${password}"`,
            password_length: password.length,
            course
        });

        if (!first_name || !last_name || !email || !password) {
            console.log('❌ Missing required fields');
            return res.status(400).json({
                success: false,
                message: 'All required fields must be filled'
            });
        }

        console.log('✅ All fields present');

        // Reject email addresses already used by either account type.
        console.log('🔍 Checking whether email already exists in Firebase...');
        if (await emailExistsInRealtimeDatabase(email)) {
            console.log('❌ Email already registered');
            return res.status(409).json({
                success: false,
                message: 'This email already exists. Please use a different email address.'
            });
        }

        if (!mailTransporter) {
            return res.status(503).json({
                success: false,
                message: 'Gmail verification is not configured. Set GMAIL_USER and GMAIL_APP_PASSWORD, then restart the server.'
            });
        }

        const studentData = {
            email,
            first_name,
            middle_name: middle_name || '',
            last_name,
            gender: gender || 'Not specified',
            course: course || '',
            student_id: student_id || '',
            address: address || '',
            level: level || '',
            phone: phone || '',
            created_at: new Date().toISOString()
        };

        const dbKey = getDatabaseKey(email);
        const verificationCode = String(Math.floor(100000 + Math.random() * 900000));
        req.session.pending_student_verification = {
            code: verificationCode,
            email,
            password,
            expires_at: Date.now() + 3 * 60 * 1000,
            student_data: studentData,
            db_key: dbKey
        };
        await saveSession(req);

        await mailTransporter.sendMail({
            from: `"${process.env.GMAIL_SENDER_NAME || 'UtosApp'}" <${process.env.GMAIL_USER}>`,
            to: email,
            ...buildVerificationEmail(verificationCode)
        });

        console.log('✉️ Student verification code sent:', email);
        console.log('👨‍🎓 ======================\n');
        res.json({
            success: true,
            verificationRequired: true,
            message: 'Verification code sent to your email'
        });
    } catch (error) {
        console.error('❌ Student signup error:', error.message);
        console.error('   Stack:', error.stack);
        console.log('👨‍🎓 ======================\n');
        res.status(500).json({
            success: false,
            message: 'Server error during signup: ' + error.message
        });
    }
});

export default router;
