import express from 'express';
import { createHash } from 'node:crypto';
import { getAuth } from 'firebase-admin/auth';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import { cert, getApp, getApps, initializeApp } from 'firebase-admin/app';
import { getDatabase } from 'firebase-admin/database';

const router = express.Router();
const allowedRecipientRoles = new Set(['teacher', 'student']);

function getMessageDatabase() {
    if (!messageDatabase) {
        const credentialPath = process.env.FIREBASE_SERVICE_ACCOUNT_PATH || process.env.GOOGLE_APPLICATION_CREDENTIALS;
        if (!credentialPath) throw new Error('Firebase service account path is not configured.');
        const serviceAccount = JSON.parse(readFileSync(path.resolve(credentialPath), 'utf8'));
        const app = getApps().length
            ? getApp()
            : initializeApp({
                credential: cert(serviceAccount),
                databaseURL: process.env.FIREBASE_DATABASE_URL || 'https://utosapp-fd977-default-rtdb.firebaseio.com'
            });
        messageDatabase = getDatabase(app);
    }
    return messageDatabase;
}

let messageDatabase;
function normalizeEmail(value) {
    return String(value || '').trim().toLowerCase();
}

function getConversationRef(role, email) {
    const emailKey = Buffer.from(normalizeEmail(email)).toString('base64url');
    return getMessageDatabase().ref(`admin_messages/${role}/${emailKey}`);
}

function normalizeStatus(application) {
    return String(application?.status || application?.application_status
        || application?.approval_status || '').trim().toLowerCase();
}

function getApplicationEmail(application) {
    const student = application?.student && typeof application.student === 'object'
        ? application.student
        : {};
    return normalizeEmail(application?.student_email || application?.studentEmail
        || application?.applicant_email || application?.applicantEmail
        || application?.email || application?.user_email || application?.userEmail
        || student.email);
}

function getTaskApplications(task) {
    const sources = [
        task?.applicants_by_student,
        task?.student_applications,
        task?.studentApplications,
        task?.applicants,
        task?.applications
    ].filter((source) => source && typeof source === 'object');
    return sources.flatMap((source) => Array.isArray(source)
        ? source.map((application, index) => [String(index), application])
        : Object.entries(source));
}

function hashValue(value) {
    return createHash('sha256').update(String(value)).digest('hex');
}

async function getAuthorizedTaskConversation(req, res) {
    const taskId = String(req.params.taskId || '').trim();
    const role = req.session.user_role;
    const email = normalizeEmail(req.session.email);
    if (!taskId || taskId.length > 768 || /[.#$\[\]/]/.test(taskId)) {
        res.status(400).json({ success: false, message: 'A valid task is required.' });
        return null;
    }

    const taskSnapshot = await getMessageDatabase().ref('tasks').child(taskId).get();
    const task = taskSnapshot.val();
    if (!task || typeof task !== 'object') {
        res.status(404).json({ success: false, message: 'Task not found.' });
        return null;
    }
    if (role === 'teacher' && normalizeEmail(task.teacher_email || task.teacherEmail) !== email) {
        res.status(403).json({ success: false, message: 'You do not have access to this task conversation.' });
        return null;
    }

    const applications = getTaskApplications(task);
    const requestedApplicantKey = String(req.params.applicantKey || req.query.applicantKey
        || req.body?.applicantKey || '').trim();
    const applicationEntry = role === 'teacher'
        ? applications.find(([key]) => key === requestedApplicantKey)
        : applications.find(([key, application]) => {
            const appEmail = getApplicationEmail(application);
            return appEmail === email || key === hashValue(email);
        });
    if (!applicationEntry) {
        res.status(404).json({ success: false, message: 'Approved student application not found.' });
        return null;
    }

    const [applicantKey, application] = applicationEntry;
    if (normalizeStatus(application) !== 'approved' && normalizeStatus(application) !== 'accepted') {
        res.status(403).json({ success: false, message: 'This task conversation is available after approval.' });
        return null;
    }

    const studentEmail = getApplicationEmail(application);
    if (role === 'student' && studentEmail && studentEmail !== email) {
        res.status(403).json({ success: false, message: 'You do not have access to this task conversation.' });
        return null;
    }
    const studentIdentity = studentEmail || applicantKey;
    const conversationKey = hashValue(`${taskId}\u0000${studentIdentity}`);
    return {
        task,
        taskId,
        applicantKey,
        application,
        conversationRef: getMessageDatabase().ref(`task_messages/${conversationKey}`)
    };
}

function getTaskConversationMessages(value, application) {
    const messages = serializeMessages(value);
    const acceptanceMessage = application?.acceptance_message;
    if (!acceptanceMessage || typeof acceptanceMessage.text !== 'string') return messages;

    const remainingMessages = messages
        .filter((message) => message.id !== acceptanceMessage.id
            && !(message.sender === acceptanceMessage.sender && message.text === acceptanceMessage.text))
        .sort((left, right) => String(left.createdAt || '').localeCompare(String(right.createdAt || '')));
    return [acceptanceMessage, ...remainingMessages];
}

function requireAdmin(req, res, next) {
    if (!req.session?.email || req.session.user_role !== 'admin') {
        return res.status(401).json({ success: false, message: 'Admin session required.' });
    }
    next();
}

function requireRecipient(req, res, next) {
    if (!req.session?.email || !allowedRecipientRoles.has(req.session.user_role)) {
        return res.status(401).json({ success: false, message: 'Teacher or student session required.' });
    }
    next();
}

function requireAppMessageSession(req, res, next) {
    if (!req.session?.email || !['teacher', 'student'].includes(req.session.user_role)) {
        return res.status(401).json({ success: false, message: 'Teacher or student session required.' });
    }
    next();
}

function validateRecipient(role, email, res) {
    const normalizedEmail = normalizeEmail(email);
    if (!allowedRecipientRoles.has(role) || !normalizedEmail.includes('@')) {
        res.status(400).json({ success: false, message: 'A valid teacher or student recipient is required.' });
        return null;
    }
    return normalizedEmail;
}

function serializeMessages(value) {
    return Object.values(value?.messages || {})
        .filter((message) => message && typeof message.text === 'string')
        .sort((left, right) => String(left.createdAt).localeCompare(String(right.createdAt)));
}

async function sendStorageError(res, error) {
    console.error('RTDB admin message error:', error);
    const credentialError = error.code === 'ENOENT'
        || error instanceof SyntaxError
        || error.message.includes('Firebase service account path is not configured');
    return res.status(503).json({
        success: false,
        message: credentialError
            ? 'Set FIREBASE_SERVICE_ACCOUNT_PATH in .env to a readable Firebase service-account JSON file, then restart the server.'
            : 'Realtime Database is unavailable. Check the Firebase service account and database URL.'
    });
}

router.get('/api/messages/inbox', requireRecipient, async (req, res) => {
    const role = req.session.user_role;
    const email = normalizeEmail(req.session.email);

    try {
        const snapshot = await getConversationRef(role, email).get();
        const conversation = snapshot.val();
        res.json({ success: true, messages: serializeMessages(conversation) });
    } catch (error) {
        return sendStorageError(res, error);
    }
});

async function getTaskMessages(req, res) {
    try {
        const authorized = await getAuthorizedTaskConversation(req, res);
        if (!authorized) return;
        const snapshot = await authorized.conversationRef.get();
        res.json({
            success: true,
            messages: getTaskConversationMessages(snapshot.val(), authorized.application)
        });
    } catch (error) {
        return sendStorageError(res, error);
    }
}

async function postTaskMessage(req, res) {
    const text = String(req.body?.text || '').trim();
    const clientMessageId = String(req.body?.clientMessageId || '').trim();
    if (!text || text.length > 4000) {
        return res.status(400).json({ success: false, message: 'Message must be between 1 and 4000 characters.' });
    }
    if (!clientMessageId || clientMessageId.length > 128) {
        return res.status(400).json({ success: false, message: 'A valid message identifier is required.' });
    }

    try {
        const authorized = await getAuthorizedTaskConversation(req, res);
        if (!authorized) return;
        const messageId = hashValue(`${req.session.user_role}:${clientMessageId}`);
        const message = {
            id: messageId,
            sender: req.session.user_role,
            senderEmail: normalizeEmail(req.session.email),
            text,
            createdAt: new Date().toISOString()
        };
        await authorized.conversationRef.child('messages').child(messageId).set(message);
        await authorized.conversationRef.update({ taskId: authorized.taskId, updatedAt: message.createdAt });
        res.status(201).json({ success: true, message });
    } catch (error) {
        return sendStorageError(res, error);
    }
}

router.get('/api/task-messages/:taskId', requireAppMessageSession, getTaskMessages);
router.get('/api/task-messages/:taskId/:applicantKey', requireAppMessageSession, getTaskMessages);
router.post('/api/task-messages/:taskId', requireAppMessageSession, postTaskMessage);
router.post('/api/task-messages/:taskId/:applicantKey', requireAppMessageSession, postTaskMessage);

router.get('/api/messages/admin/:role/:email', requireAdmin, async (req, res) => {
    const email = validateRecipient(req.params.role, req.params.email, res);
    if (!email) return;

    try {
        const snapshot = await getConversationRef(req.params.role, email).get();
        res.json({ success: true, messages: serializeMessages(snapshot.val()) });
    } catch (error) {
        return sendStorageError(res, error);
    }
});

router.delete('/api/admin/users/:role/:userId', requireAdmin, async (req, res) => {
    const { role, userId } = req.params;
    if (!allowedRecipientRoles.has(role) || !userId || userId.length > 768 || /[.#$\[\]/]/.test(userId)) {
        return res.status(400).json({ success: false, message: 'A valid teacher or student account is required.' });
    }

    try {
        const app = getMessageDatabase().app;
        const databaseRef = getMessageDatabase().ref(`${role}s/${userId}`);
        const snapshot = await databaseRef.get();
        const record = snapshot.val();
        if (!record || typeof record !== 'object') {
            return res.status(404).json({ success: false, message: 'User account was not found.' });
        }

        const auth = getAuth(app);
        let authUser = null;
        const firebaseUid = String(record.firebase_uid || record.firebaseUid || '').trim();
        if (firebaseUid) {
            try {
                authUser = await auth.getUser(firebaseUid);
            } catch (error) {
                if (!['auth/user-not-found', 'auth/invalid-uid'].includes(error.code)) throw error;
            }
        }
        if (!authUser && record.email) {
            try {
                authUser = await auth.getUserByEmail(String(record.email).trim());
            } catch (error) {
                if (error.code !== 'auth/user-not-found') throw error;
            }
        }

        await databaseRef.remove();
        if (authUser) {
            try {
                await auth.deleteUser(authUser.uid);
            } catch (error) {
                if (error.code !== 'auth/user-not-found') {
                    try {
                        await databaseRef.set(record);
                    } catch (restoreError) {
                        console.error('Failed to restore user RTDB record after Auth deletion failed:', restoreError);
                    }
                    throw error;
                }
            }
        }

        res.json({ success: true, deletedUserId: userId, deletedAuthAccount: Boolean(authUser) });
    } catch (error) {
        console.error('Firebase admin user deletion error:', error);
        return res.status(503).json({ success: false, message: 'Could not permanently delete the user account. Please try again.' });
    }
});

router.patch('/api/admin/users/:role/:userId/suspension', requireAdmin, async (req, res) => {
    const { role, userId } = req.params;
    const suspended = req.body?.suspended;
    if (!allowedRecipientRoles.has(role) || !userId || userId.length > 768 || /[.#$\[\]/]/.test(userId)) {
        return res.status(400).json({ success: false, message: 'A valid teacher or student account is required.' });
    }
    if (typeof suspended !== 'boolean') {
        return res.status(400).json({ success: false, message: 'Choose whether the account should be suspended.' });
    }

    let databaseRef;
    let record;
    try {
        databaseRef = getMessageDatabase().ref(`${role}s/${userId}`);
        const snapshot = await databaseRef.get();
        record = snapshot.val();
        if (!record || typeof record !== 'object') {
            return res.status(404).json({ success: false, message: 'User account was not found.' });
        }

        const statusUpdate = { is_suspended: suspended };
        if (!suspended && String(record.status || '').toLowerCase() === 'suspended') {
            statusUpdate.status = 'active';
        }
        await databaseRef.update(statusUpdate);

        const auth = getAuth(getMessageDatabase().app);
        let authUser = null;
        const firebaseUid = String(record.firebase_uid || record.firebaseUid || '').trim();
        if (firebaseUid) {
            try {
                authUser = await auth.getUser(firebaseUid);
            } catch (error) {
                if (!['auth/user-not-found', 'auth/invalid-uid'].includes(error.code)) throw error;
            }
        }
        if (!authUser && record.email) {
            try {
                authUser = await auth.getUserByEmail(String(record.email).trim());
            } catch (error) {
                if (error.code !== 'auth/user-not-found') throw error;
            }
        }
        if (authUser) await auth.updateUser(authUser.uid, { disabled: suspended });

        return res.json({ success: true, suspended });
    } catch (error) {
        if (record) {
            try {
                await databaseRef.update({
                    is_suspended: record.is_suspended ?? null,
                    ...(String(record.status || '').toLowerCase() === 'suspended' && !suspended ? { status: record.status } : {})
                });
            } catch (restoreError) {
                console.error('Failed to restore user suspension state:', restoreError);
            }
        }
        console.error('Firebase admin user suspension error:', error);
        return res.status(503).json({ success: false, message: 'Could not update this account. Please try again.' });
    }
});

router.post('/api/messages/admin/:role/:email', requireAdmin, async (req, res) => {
    const role = req.params.role;
    const email = validateRecipient(role, req.params.email, res);
    if (!email) return;

    const text = String(req.body?.text || '').trim();
    if (!text || text.length > 4000) {
        return res.status(400).json({ success: false, message: 'Message must be between 1 and 4000 characters.' });
    }

    try {
        const conversationRef = getConversationRef(role, email);
        const messageRef = conversationRef.child('messages').push();
        const message = {
            id: messageRef.key,
            sender: 'admin',
            senderEmail: normalizeEmail(req.session.email),
            text,
            createdAt: new Date().toISOString(),
            ...(req.body?.welcome === true ? { welcome: true } : {})
        };
        await messageRef.set(message);
        await conversationRef.update({ recipientEmail: email, recipientRole: role, updatedAt: message.createdAt });
        res.status(201).json({ success: true, message });
    } catch (error) {
        return sendStorageError(res, error);
    }
});

router.post('/api/messages/inbox', requireRecipient, async (req, res) => {
    const role = req.session.user_role;
    const email = normalizeEmail(req.session.email);
    const text = String(req.body?.text || '').trim();
    if (!text || text.length > 4000) {
        return res.status(400).json({ success: false, message: 'Message must be between 1 and 4000 characters.' });
    }

    try {
        const conversationRef = getConversationRef(role, email);
        const messageRef = conversationRef.child('messages').push();
        const message = {
            id: messageRef.key,
            sender: role,
            senderEmail: email,
            text,
            createdAt: new Date().toISOString()
        };
        await messageRef.set(message);
        await conversationRef.update({ recipientEmail: email, recipientRole: role, updatedAt: message.createdAt });
        res.status(201).json({ success: true, message });
    } catch (error) {
        return sendStorageError(res, error);
    }
});

export default router;