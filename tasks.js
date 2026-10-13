import express from 'express';
import { createHash, randomUUID } from 'node:crypto';
import { readFileSync } from 'node:fs';
import { mkdir, unlink, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { cert, getApp, getApps, initializeApp } from 'firebase-admin/app';
import { getDatabase } from 'firebase-admin/database';
import multer from 'multer';
import {
    amountToCentavos,
    createPayMongoCheckoutSession,
    getPayMongoConfig,
    isExpectedPayMongoPaymentAmount,
    retrievePayMongoCheckoutSession,
    verifyPayMongoWebhookSignature
} from '../lib/paymongo.js';

const router = express.Router();
const FIREBASE_DB_URL = 'https://utosapp-fd977-default-rtdb.firebaseio.com';
const FIREBASE_AUTH_URL = 'https://identitytoolkit.googleapis.com/v1/accounts:signInWithPassword';
const FIREBASE_TOKEN_URL = 'https://securetoken.googleapis.com/v1/token';
const FIREBASE_WEB_API_KEY = 'AIzaSyDRUJHWP0mnZNepGMgZU8ESu_Pq2KPR-2U';
let taskDatabase;
const calculateTaskPlatformFee = (amount) => {
    if (amount < 100) return 5;
    if (amount <= 500) return 10;
    if (amount <= 1000) return 20;
    return 20 + Math.ceil((amount - 1000) / 500) * 10;
};
const getTaskPaymentBreakdown = (task) => {
    const rawAmount = task.amount ?? task.budget;
    const amount = Number(rawAmount);
    const taskAmount = Number.isFinite(amount) && amount > 0 ? amount : 0;
    const savedFee = Number(task.platform_fee);
    const platformFee = Number.isFinite(savedFee) && savedFee > 0
        ? savedFee
        : calculateTaskPlatformFee(taskAmount);
    return {
        taskAmount,
        platformFee,
        totalAmount: taskAmount + platformFee
    };
};
const taskSubmissionDirectory = path.resolve(
    path.dirname(fileURLToPath(import.meta.url)),
    '..',
    'uploads',
    'task-submissions'
);
const submissionUpload = multer({
    storage: multer.memoryStorage(),
    limits: { fileSize: 10 * 1024 * 1024, files: 5 },
    fileFilter: (req, file, callback) => {
        const extension = path.extname(file.originalname).toLowerCase();
        if (!['.pdf', '.doc', '.docx', '.png', '.jpg', '.jpeg'].includes(extension)) {
            callback(new Error('Upload a PDF, DOC, DOCX, PNG, or JPG file.'));
            return;
        }
        callback(null, true);
    }
});

const handleSubmissionUpload = (req, res, next) => submissionUpload.array('proof', 5)(req, res, (error) => {
    if (!error) return next();
    const status = error.code === 'LIMIT_FILE_SIZE' ? 413 : 400;
    const message = error.code === 'LIMIT_FILE_SIZE'
        ? 'Proof files must be 10 MB or smaller.'
        : error.code === 'LIMIT_UNEXPECTED_FILE'
            ? 'You can attach up to 5 proof files.'
            : error.message;
    res.status(status).json({
        success: false,
        message
    });
});

const getFirebaseAdminApp = () => {
    if (getApps().length) return getApp();
    const credentialPath = process.env.FIREBASE_SERVICE_ACCOUNT_PATH || process.env.GOOGLE_APPLICATION_CREDENTIALS;
    if (!credentialPath) throw new Error('Firebase service account path is not configured.');
    const serviceAccount = JSON.parse(readFileSync(path.resolve(credentialPath), 'utf8'));
    return initializeApp({
        credential: cert(serviceAccount),
        databaseURL: process.env.FIREBASE_DATABASE_URL || FIREBASE_DB_URL,
        storageBucket: process.env.FIREBASE_STORAGE_BUCKET || 'utosapp-fd977.firebasestorage.app'
    });
};

const getTaskDatabase = () => {
    if (!taskDatabase) {
        taskDatabase = getDatabase(getFirebaseAdminApp());
    }
    return taskDatabase;
};

const getStudentApplicationKey = (email) => createHash('sha256')
    .update(String(email || '').trim().toLowerCase())
    .digest('hex');

const getTaskKey = (title) => String(title || '')
    .trim()
    .replace(/[.#$[\]/]/g, '-')
    .replace(/\s+/g, ' ')
    .slice(0, 120);

const normalizeIdentity = (value) => String(value ?? '').trim().toLowerCase();
const matchesCurrentStudent = (application, session) => {
    if (!application || typeof application !== 'object') return false;
    const student = application.student && typeof application.student === 'object' ? application.student : {};
    const assignee = application.assigned_student || application.assignedStudent;
    const assignedStudent = assignee && typeof assignee === 'object' ? assignee : {};
    const identityValues = [
        application.student_email,
        application.studentEmail,
        application.applicant_email,
        application.applicantEmail,
        application.email,
        application.student_id,
        application.studentId,
        application.applicant_id,
        application.applicantId,
        application.user_id,
        application.userId,
        application.firebase_uid,
        application.firebaseUid,
        application.applicant_uid,
        application.applicantUid,
        application.uid,
        application.user_email,
        application.userEmail,
        typeof application.assigned_to === 'string' ? application.assigned_to : '',
        typeof application.assignedTo === 'string' ? application.assignedTo : '',
        application.assignee_id,
        application.assigneeId,
        student.email,
        student.student_id,
        student.studentId,
        student.firebase_uid,
        student.firebaseUid,
        student.uid,
        assignedStudent.email,
        assignedStudent.student_email,
        assignedStudent.studentEmail,
        assignedStudent.student_id,
        assignedStudent.studentId,
        assignedStudent.firebase_uid,
        assignedStudent.firebaseUid,
        assignedStudent.uid
    ].map(normalizeIdentity).filter(Boolean);
    const currentValues = [
        session.email,
        session.student_id,
        session.firebase_uid
    ].map(normalizeIdentity).filter(Boolean);
    return identityValues.some((value) => currentValues.includes(value));
};

const hasCurrentStudentAssignment = (task, session) => {
    const assigned = task?.assignedStudent || task?.assigned_student || task?.assignee;
    const assignedStudent = assigned && typeof assigned === 'object' ? assigned : {};
    const student = task?.student && typeof task.student === 'object' ? task.student : {};
    return matchesCurrentStudent({
        assigned_to: typeof assigned === 'string'
            ? assigned
            : task?.assigned_to || task?.assignedTo || '',
        assignee_id: task?.assignee_id || task?.assigneeId || '',
        student_email: task?.student_email || task?.studentEmail
            || assignedStudent.email || assignedStudent.student_email || assignedStudent.studentEmail
            || student.email,
        student_id: task?.student_id || task?.studentId
            || assignedStudent.student_id || assignedStudent.studentId
            || student.student_id || student.studentId,
        firebase_uid: task?.firebase_uid || task?.firebaseUid
            || assignedStudent.firebase_uid || assignedStudent.firebaseUid || assignedStudent.uid
            || student.firebase_uid || student.firebaseUid || student.uid
    }, session);
};

const isTaskOwnedByTeacher = (task, email) =>
    normalizeIdentity(task?.teacher_email || task?.teacherEmail) === normalizeIdentity(email);

const getCompletedTaskApplicant = (task) => {
    const sources = [
        task.applicants_by_student,
        task.student_applications,
        task.studentApplications,
        task.applicants,
        task.applications
    ];
    for (const source of sources) {
        if (!source || typeof source !== 'object') continue;
        const applications = Array.isArray(source) ? source : Object.values(source);
        const application = applications.find((candidate) => {
            if (!candidate || typeof candidate !== 'object') return false;
            const status = String(candidate.status || candidate.application_status || candidate.approval_status || '')
                .trim().toLowerCase().replace(/[\s-]+/g, '_');
            return ['completed', 'complete', 'done'].includes(status)
                || Boolean(candidate.completed_at || candidate.completedAt);
        });
        if (application) return application;
    }
    if (task.submission && typeof task.submission === 'object') {
        return {
            submission: task.submission,
            student_name: task.submission.student_name || task.student_name || task.studentName || '',
            student_email: task.student_email || task.studentEmail || ''
        };
    }
    return null;
};

const getPaymentStudentName = (application) => {
    const student = application.student && typeof application.student === 'object'
        ? application.student
        : application.applicant && typeof application.applicant === 'object'
            ? application.applicant
            : {};
    const firstName = application.first_name || application.firstName || student.first_name || student.firstName;
    const lastName = application.last_name || application.lastName || student.last_name || student.lastName;
    if (firstName && lastName) return `${firstName} ${lastName}`.trim();
    const name = application.submission?.student_name || application.student_name || application.studentName
        || application.full_name || application.fullName || application.name
        || student.full_name || student.fullName || student.name || 'Student';
    const parts = String(name).trim().split(/\s+/).filter(Boolean);
    if (parts.length === 3) return `${parts[0]} ${parts[2]}`;
    if (parts.length > 3 && /^[a-z]\.?$/i.test(parts[2])) parts.splice(2, 1);
    return parts.join(' ') || 'Student';
};

const getPaymentStudentEmail = (application) => {
    const student = application.student && typeof application.student === 'object'
        ? application.student
        : application.applicant && typeof application.applicant === 'object'
            ? application.applicant
            : {};
    const assignedStudent = application.assigned_student && typeof application.assigned_student === 'object'
        ? application.assigned_student
        : application.assignedStudent && typeof application.assignedStudent === 'object'
            ? application.assignedStudent
            : {};
    return String(application.student_email || application.studentEmail
        || application.applicant_email || application.applicantEmail || application.email
        || student.email || student.student_email || student.studentEmail
        || assignedStudent.email || assignedStudent.student_email || assignedStudent.studentEmail || '').trim();
};

const readAllTasks = async () => (await readFirebaseJson(`${FIREBASE_DB_URL}/tasks.json`)) || {};
const readTask = async (taskId) => readFirebaseJson(
    `${FIREBASE_DB_URL}/tasks/${encodeURIComponent(taskId)}.json`
);

const saveTaskWithTitleKey = async (task) => {
    const baseKey = getTaskKey(task.title);
    if (!baseKey) throw new Error('Task title cannot be used as a Firebase key.');

    const keysResponse = await fetch(`${FIREBASE_DB_URL}/tasks.json?shallow=true`);
    if (!keysResponse.ok) throw new Error(`Firebase returned ${keysResponse.status} while checking task keys.`);
    const existingKeys = await keysResponse.json() || {};

    for (let duplicateNumber = 1; duplicateNumber <= 10000; duplicateNumber += 1) {
        const suffix = duplicateNumber === 1 ? '' : ` (${duplicateNumber})`;
        const taskKey = `${baseKey.slice(0, 120 - suffix.length)}${suffix}`;
        if (Object.hasOwn(existingKeys, taskKey)) continue;
        const response = await fetch(`${FIREBASE_DB_URL}/tasks/${encodeURIComponent(taskKey)}.json`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', 'If-Match': 'null_etag' },
            body: JSON.stringify(task)
        });
        if (response.status === 412) continue;
        if (!response.ok) throw new Error(`Firebase returned ${response.status} while saving the task.`);
        return taskKey;
    }

    throw new Error('Could not generate a unique task key. Please try again.');
};

const requireAppSession = (role) => (req, res, next) => {
    if (!req.session?.email || req.session.user_role !== role) {
        return res.status(401).json({ success: false, message: `${role} Firebase session required` });
    }
    next();
};

const getFirebaseHeaders = (req, extraHeaders = {}) => {
    const headers = { ...extraHeaders };
    const token = req.session?.firebase_id_token || req.session?.firebaseToken;
    if (token) {
        headers.Authorization = `Bearer ${token}`;
    }
    return headers;
};

const refreshFirebaseSessionToken = async (req) => {
    const refreshToken = req.session?.firebase_refresh_token;
    const hasPasswordFallback = Boolean(req.session?.firebase_password && req.session?.email);
    let result = null;
    let refreshError = null;

    if (refreshToken) {
        try {
            const response = await fetch(`${FIREBASE_TOKEN_URL}?key=${FIREBASE_WEB_API_KEY}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ grant_type: 'refresh_token', refresh_token: refreshToken })
            });
            const refreshResult = await response.json();
            if (response.ok) {
                result = refreshResult;
            } else {
                refreshError = new Error(`Firebase token refresh failed: ${refreshResult?.error?.message || response.status}`);
            }
        } catch (error) {
            refreshError = error;
        }
    }

    if (!result && hasPasswordFallback) {
        const response = await fetch(`${FIREBASE_AUTH_URL}?key=${FIREBASE_WEB_API_KEY}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                email: req.session.email,
                password: req.session.firebase_password,
                returnSecureToken: true
            })
        });
        const passwordResult = await response.json();
        if (!response.ok) {
            throw new Error(`Firebase session renewal failed: ${passwordResult?.error?.message || response.status}`);
        }
        result = passwordResult;
    }

    if (!result) {
        if (refreshError) throw refreshError;
        throw new Error('Firebase session expired; sign in again to continue.');
    }

    const idToken = result.id_token || result.idToken;
    if (!idToken) {
        throw new Error('Firebase did not return a renewed session token.');
    }
    req.session.firebase_id_token = idToken;
    req.session.firebase_refresh_token = result.refresh_token || result.refreshToken || refreshToken || null;
    if (result.user_id || result.localId) req.session.firebase_uid = result.user_id || result.localId;
    await new Promise((resolve, reject) => req.session.save((error) => error ? reject(error) : resolve()));
}

const readFirebaseJson = async (url, { allowPermissionDenied = false, requestHeaders = {} } = {}) => {
    const response = await fetch(url, {
        headers: requestHeaders
    });
    const text = await response.text();

    if (!response.ok) {
        const payload = text ? (() => {
            try { return JSON.parse(text); } catch { return { error: text }; }
        })() : {};

        if (allowPermissionDenied && [401, 403].includes(response.status)) {
            return null;
        }

        throw new Error(`Firebase returned ${response.status}: ${payload?.error || 'request failed'}`);
    }

    if (!text) return null;

    try {
        return JSON.parse(text);
    } catch {
        return null;
    }
};

router.get('/api/student-tasks', requireAppSession('student'), async (req, res) => {
    try {
        const studentApplicationKey = getStudentApplicationKey(req.session.email);
        const [tasksData, applications, transactionsData] = await Promise.all([
            readAllTasks(),
            readFirebaseJson(`${FIREBASE_DB_URL}/student_applications/${studentApplicationKey}.json`, {
                allowPermissionDenied: true,
                requestHeaders: getFirebaseHeaders(req)
            }),
            readFirebaseJson(`${FIREBASE_DB_URL}/transactions.json`, {
                allowPermissionDenied: true,
                requestHeaders: getFirebaseHeaders(req)
            })
        ]);
        const transactionsByTaskId = new Map(
            Object.values(transactionsData || {})
                .filter((transaction) => transaction && typeof transaction === 'object' && transaction.task_id)
                .map((transaction) => [String(transaction.task_id), transaction])
        );

        const tasks = Object.entries(tasksData || {})
            .map(([id, task]) => {
                const transactionKey = task?.transaction_id
                    || `task_${createHash('sha256').update(id).digest('hex')}`;
                const transaction = transactionsData?.[transactionKey] || transactionsByTaskId.get(id);
                const applicationSources = [
                    task?.applicants_by_student,
                    task?.student_applications,
                    task?.studentApplications,
                    task?.applicants,
                    task?.applications,
                    task?.offers,
                    task?.student_offers,
                    task?.studentOffers
                ].filter((source) => source && typeof source === 'object');
                const keyedApplication = applicationSources
                    .map((source) => source[studentApplicationKey])
                    .find(Boolean);
                const matchedApplication = applicationSources
                    .flatMap((source) => Array.isArray(source) ? source : Object.values(source))
                    .find((application) => matchesCurrentStudent(application, req.session));
                const approvedApplication = applicationSources
                    .flatMap((source) => Array.isArray(source) ? source : Object.values(source))
                    .find((candidate) => {
                        if (!matchesCurrentStudent(candidate, req.session)) return false;
                        const status = String(candidate.status || candidate.application_status
                            || candidate.approval_status || '').trim().toLowerCase();
                        return status === 'approved' || status === 'accepted';
                    });
                const submittedApplication = applicationSources
                    .flatMap((source) => Array.isArray(source) ? source : Object.values(source))
                    .find((candidate) => (
                        matchesCurrentStudent(candidate, req.session)
                        && (
                            String(candidate.status || candidate.application_status
                                || candidate.approval_status || '').trim().toLowerCase().replace(/[\s-]+/g, '_') === 'under_review'
                            || Boolean(candidate.submission)
                        )
                    ));
                const application = submittedApplication || approvedApplication
                    || applications?.[id] || keyedApplication || matchedApplication || null;
                const assignedToCurrentStudent = hasCurrentStudentAssignment(task, req.session);
                const studentApplication = application || (assignedToCurrentStudent ? { status: 'assigned' } : null);
                const visibleTask = {
                    ...task,
                    id,
                    student_application: studentApplication,
                    has_applied: Boolean(application),
                    payment_status: transaction?.student_payment_status || transaction?.status || task?.payment_status
                        || (['completed', 'complete', 'done'].includes(String(task?.status || '').toLowerCase())
                            || task?.is_completed === true || task?.completed === true
                            ? 'pending'
                            : ''),
                    title: String(task?.title || task?.task_title || task?.taskName || '').trim()
                };
                return visibleTask;
            })
            .filter((task) => task.title);

        res.json({ success: true, tasks });
    } catch (error) {
        console.error('Student Firebase task read error:', error.message);
        res.status(502).json({ success: false, message: 'Could not load tasks from Firebase.' });
    }
});

router.get('/api/student-tasks/:taskId/submissions/files/:fileId', async (req, res) => {
    if (!req.session?.email || !['student', 'teacher'].includes(req.session.user_role)) {
        return res.status(401).json({ success: false, message: 'A student or teacher session is required.' });
    }

    const taskId = String(req.params.taskId || '').trim();
    const fileId = String(req.params.fileId || '').trim();
    if (!taskId || !/^[\da-f-]{36}$/i.test(fileId)) {
        return res.status(400).json({ success: false, message: 'A valid task and proof file are required.' });
    }

    try {
        const task = await readTask(taskId);
        if (!task) return res.status(404).json({ success: false, message: 'Task not found.' });

        let authorized = req.session.user_role === 'teacher'
            ? isTaskOwnedByTeacher(task, req.session.email)
            : hasCurrentStudentAssignment(task, req.session);
        const applicationSources = [
            task.applicants_by_student,
            task.student_applications,
            task.studentApplications,
            task.applicants,
            task.applications
        ];
        const matchingApplications = applicationSources
            .filter((source) => source && typeof source === 'object')
            .flatMap((source) => Array.isArray(source) ? source : Object.values(source))
            .filter((application) => matchesCurrentStudent(application, req.session));
        if (req.session.user_role === 'student' && matchingApplications.length) authorized = true;
        if (!authorized) {
            return res.status(403).json({ success: false, message: 'You do not have access to this proof file.' });
        }

        const taskApplications = req.session.user_role === 'teacher'
            ? applicationSources
                .filter((source) => source && typeof source === 'object')
                .flatMap((source) => Array.isArray(source) ? source : Object.values(source))
            : matchingApplications;
        const submissions = [
            task.submission,
            ...taskApplications.map((application) => application?.submission)
        ].filter((submission) => submission && typeof submission === 'object');
        const proof = submissions
            .flatMap((submission) => Array.isArray(submission.files)
                ? submission.files
                : submission.file ? [submission.file] : [])
            .find((file) => file && file.id === fileId);
        if (!proof) return res.status(404).json({ success: false, message: 'Proof file not found.' });

        const extension = path.extname(String(proof.name || '')).toLowerCase();
        if (!['.pdf', '.doc', '.docx', '.png', '.jpg', '.jpeg'].includes(extension)) {
            return res.status(404).json({ success: false, message: 'Proof file not found.' });
        }
        const filePath = path.join(
            taskSubmissionDirectory,
            createHash('sha256').update(taskId).digest('hex'),
            `${fileId}${extension}`
        );
        const downloadName = path.basename(proof.name).replace(/["\\]/g, '_');
        const sendOptions = req.query.inline === '1'
            ? { headers: { 'Content-Disposition': `inline; filename="${downloadName}"` } }
            : { headers: { 'Content-Disposition': `attachment; filename="${downloadName}"` } };
        return res.sendFile(filePath, sendOptions, (error) => {
            if (error && !res.headersSent) {
                console.error('Could not serve a task proof:', error.message);
                res.status(error.code === 'ENOENT' ? 404 : 500).json({
                    success: false,
                    message: error.code === 'ENOENT' ? 'Proof file not found.' : 'Could not open this proof file.'
                });
            }
        });
    } catch (error) {
        console.error('Student task proof read error:', error.message);
        return res.status(502).json({ success: false, message: 'Could not load this proof file.' });
    }
});

router.post('/api/teacher-tasks/:taskId/request-revisions', requireAppSession('teacher'), async (req, res) => {
    const taskId = String(req.params.taskId || '').trim();
    const comments = String(req.body.comments || '').trim();
    if (!taskId) return res.status(400).json({ success: false, message: 'A task ID is required.' });
    if (comments.length > 500) {
        return res.status(400).json({ success: false, message: 'Feedback must be 500 characters or fewer.' });
    }

    try {
        const task = await readTask(taskId);
        if (!task) return res.status(404).json({ success: false, message: 'Task not found.' });
        if (!isTaskOwnedByTeacher(task, req.session.email)) {
            return res.status(403).json({ success: false, message: 'You can only review your own tasks.' });
        }
        if (String(task.status || '').trim().toLowerCase() !== 'under_review') {
            return res.status(409).json({ success: false, message: 'This task is no longer under review.' });
        }

        const applicationSources = [
            ['applicants_by_student', task.applicants_by_student],
            ['student_applications', task.student_applications],
            ['studentApplications', task.studentApplications],
            ['applicants', task.applicants],
            ['applications', task.applications]
        ];
        const requestedAt = new Date().toISOString();
        const rootApplications = [];
        let updatedApplicationCount = 0;
        const updatedTask = {
            ...task,
            status: 'ongoing',
            progress: 10,
            revision_requested_at: requestedAt,
            revision_comments: comments,
            revision_pending: true,
            ...(task.submission && typeof task.submission === 'object'
                ? {
                    submission: {
                        ...task.submission,
                        revision_requested_at: requestedAt,
                        revision_comments: comments
                    }
                }
                : {}),
            updated_at: requestedAt
        };

        for (const [sourceName, source] of applicationSources) {
            if (!source || typeof source !== 'object') continue;
            const entries = Array.isArray(source)
                ? source.map((application, index) => [String(index), application])
                : Object.entries(source);
            const updatedSource = Array.isArray(source) ? [...source] : { ...source };
            let sourceChanged = false;

            for (const [key, application] of entries) {
                if (!application || typeof application !== 'object'
                    || String(application.status || application.application_status
                        || application.approval_status || '').trim().toLowerCase() !== 'under_review') {
                    continue;
                }
                const submission = application.submission && typeof application.submission === 'object'
                    ? application.submission
                    : task.submission && typeof task.submission === 'object'
                        ? task.submission
                        : {};
                const updatedApplication = {
                    ...application,
                    status: 'in_progress',
                    revision_requested_at: requestedAt,
                    revision_comments: comments,
                    revision_pending: true,
                    submission: {
                        ...submission,
                        revision_requested_at: requestedAt,
                        revision_comments: comments
                    }
                };
                updatedSource[key] = updatedApplication;
                sourceChanged = true;
                updatedApplicationCount += 1;

                const studentEmail = application.student_email || application.studentEmail
                    || application.applicant_email || application.applicantEmail || application.email;
                const applicationKey = /^[a-f\d]{64}$/i.test(key)
                    ? key
                    : studentEmail ? getStudentApplicationKey(studentEmail) : '';
                if (applicationKey) {
                    rootApplications.push([applicationKey, updatedApplication]);
                }
            }

            if (sourceChanged) updatedTask[sourceName] = updatedSource;
        }

        if (!updatedApplicationCount) {
            return res.status(409).json({ success: false, message: 'No submission is currently waiting for review.' });
        }

        const credentialPath = process.env.FIREBASE_SERVICE_ACCOUNT_PATH || process.env.GOOGLE_APPLICATION_CREDENTIALS;
        if (credentialPath) {
            const updates = {
                [`tasks/${taskId}`]: updatedTask,
                ...Object.fromEntries(rootApplications.map(([applicationKey, application]) => [
                    `student_applications/${applicationKey}/${taskId}`,
                    application
                ]))
            };
            await getTaskDatabase().ref().update(updates);
        } else {
            const writeFirebaseJson = async (url, value) => {
                const options = {
                    method: 'PUT',
                    headers: getFirebaseHeaders(req, { 'Content-Type': 'application/json' }),
                    body: JSON.stringify(value)
                };
                let response = await fetch(url, options);
                if (response.status === 401) {
                    await refreshFirebaseSessionToken(req);
                    options.headers = getFirebaseHeaders(req, { 'Content-Type': 'application/json' });
                    response = await fetch(url, options);
                }
                if (!response.ok) {
                    throw new Error(`Firebase returned ${response.status} while saving requested revisions.`);
                }
            };
            await writeFirebaseJson(`${FIREBASE_DB_URL}/tasks/${encodeURIComponent(taskId)}.json`, updatedTask);
            for (const [applicationKey, application] of rootApplications) {
                await writeFirebaseJson(
                    `${FIREBASE_DB_URL}/student_applications/${applicationKey}/${encodeURIComponent(taskId)}.json`,
                    application
                );
            }
        }

        return res.json({ success: true, task: updatedTask });
    } catch (error) {
        console.error('Teacher task revision request error:', error.message);
        return res.status(502).json({ success: false, message: 'Could not request revisions for this task.' });
    }
});

router.get('/api/student-teacher-profile', requireAppSession('student'), async (req, res) => {
    const email = String(req.query.email || '').trim().toLowerCase();
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        return res.status(400).json({ success: false, message: 'A valid teacher email is required.' });
    }

    try {
        const [teachersData, tasksData] = await Promise.all([
            readFirebaseJson(`${FIREBASE_DB_URL}/teachers.json`),
            readAllTasks()
        ]);
        const teacherEntry = Object.entries(teachersData || {}).find(([, teacher]) =>
            String(teacher?.email || '').trim().toLowerCase() === email
        );
        if (!teacherEntry) {
            return res.status(404).json({ success: false, message: 'This teacher profile is unavailable.' });
        }

        const [, teacher] = teacherEntry;
        const postedTasks = Object.entries(tasksData || {})
            .filter(([, task]) => String(task?.teacher_email || task?.teacherEmail || '').trim().toLowerCase() === email)
            .map(([id, task]) => {
                const applications = task.applications
                    ?? task.student_applications
                    ?? task.studentApplications
                    ?? task.applicants_by_student
                    ?? task.applicants
                    ?? task.application_count
                    ?? task.applicationCount
                    ?? task.participants
                    ?? task.student_count
                    ?? 0;
                const applicationCount = Array.isArray(applications)
                    ? applications.length
                    : applications && typeof applications === 'object'
                        ? Object.keys(applications).length
                        : Number.isFinite(Number(applications)) && Number(applications) > 0
                            ? Number(applications)
                            : 0;

                return {
                    id,
                    title: String(task.title || task.task_title || task.taskName || '').trim(),
                    room: task.room || '',
                    amount: task.amount ?? task.budget ?? task.total_amount ?? null,
                    application_count: applicationCount,
                    status: task.status || 'pending',
                    is_completed: Boolean(task.is_completed || task.completed),
                    created_at: task.created_at || task.createdAt || ''
                };
            })
            .filter((task) => task.title)
            .sort((first, second) => new Date(second.created_at || 0) - new Date(first.created_at || 0));

        res.json({
            success: true,
            teacher: {
                first_name: teacher.first_name || '',
                middle_name: teacher.middle_name || '',
                last_name: teacher.last_name || '',
                email: teacher.email || email,
                department: teacher.department || '',
                about: teacher.about || teacher.bio || teacher.introduction || '',
                photo: teacher.photo || teacher.teacher_photo || '',
                created_at: teacher.created_at || ''
            },
            tasks: postedTasks
        });
    } catch (error) {
        console.error('Student teacher profile read error:', error.message);
        res.status(502).json({ success: false, message: 'Could not load this teacher profile.' });
    }
});

router.post(
    '/api/student-tasks/:taskId/submissions',
    requireAppSession('student'),
    handleSubmissionUpload,
    async (req, res) => {
        const taskId = String(req.params.taskId || '').trim();
        if (!taskId) return res.status(400).json({ success: false, message: 'A task ID is required.' });

        const submittedLink = String(req.body.link || '').trim();
        const notes = String(req.body.notes || '').trim();
        if (notes.length > 500) {
            return res.status(400).json({ success: false, message: 'Additional notes must be 500 characters or fewer.' });
        }
        let safeLink = '';
        if (submittedLink) {
            try {
                const parsedLink = new URL(submittedLink);
                if (!['http:', 'https:'].includes(parsedLink.protocol)) throw new Error('Invalid protocol');
                safeLink = parsedLink.href;
            } catch {
                return res.status(400).json({ success: false, message: 'Enter a valid http or https link.' });
            }
        }
        if (!req.files?.length && !safeLink) {
            return res.status(400).json({ success: false, message: 'Upload a proof file or provide a link.' });
        }

        const uploadedFilePaths = [];
        let taskSubmissionSaved = false;
        try {
            const studentApplicationKey = getStudentApplicationKey(req.session.email);
            const [task, studentApplications] = await Promise.all([
                readTask(taskId),
                readFirebaseJson(
                    `${FIREBASE_DB_URL}/student_applications/${studentApplicationKey}.json`,
                    { allowPermissionDenied: true, requestHeaders: getFirebaseHeaders(req) }
                )
            ]);
            if (!task) return res.status(404).json({ success: false, message: 'Task not found.' });

            const applicationSources = [
                ['applicants_by_student', task.applicants_by_student],
                ['student_applications', task.student_applications],
                ['studentApplications', task.studentApplications],
                ['applicants', task.applicants],
                ['applications', task.applications]
            ];
            let sourceName = '';
            let applicationSource = null;
            let applicationPathKey = '';
            let application = null;
            for (const [candidateSourceName, candidateSource] of applicationSources) {
                if (!candidateSource || typeof candidateSource !== 'object') continue;
                const entry = Object.entries(candidateSource).find(([, candidate]) =>
                    matchesCurrentStudent(candidate, req.session)
                );
                if (!entry) continue;
                [applicationPathKey, application] = entry;
                sourceName = candidateSourceName;
                applicationSource = candidateSource;
                break;
            }

            const rootApplication = studentApplications?.[taskId];
            if (!application && rootApplication && typeof rootApplication === 'object') {
                application = rootApplication;
            }
            if (!application) {
                return res.status(403).json({ success: false, message: 'You do not have an approved application for this task.' });
            }
            const currentStatus = String(application.status || application.application_status
                || application.approval_status || '').trim().toLowerCase().replace(/[\s-]+/g, '_');
            if (currentStatus === 'under_review') {
                return res.status(409).json({ success: false, message: 'This task has already been submitted for review.' });
            }
            if (!['approved', 'accepted', 'assigned', 'in_progress'].includes(currentStatus)) {
                return res.status(409).json({ success: false, message: 'Only an approved task can be submitted.' });
            }

            const submittedAt = new Date().toISOString();
            const proofs = [];
            const submissionTaskDirectory = path.join(
                taskSubmissionDirectory,
                createHash('sha256').update(taskId).digest('hex')
            );
            await mkdir(submissionTaskDirectory, { recursive: true });
            for (const proofFile of req.files || []) {
                const extension = path.extname(proofFile.originalname).toLowerCase();
                const contentTypes = {
                    '.pdf': 'application/pdf',
                    '.doc': 'application/msword',
                    '.docx': 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    '.png': 'image/png',
                    '.jpg': 'image/jpeg',
                    '.jpeg': 'image/jpeg'
                };
                const originalName = path.basename(proofFile.originalname).replace(/[^\w.-]+/g, '_').slice(0, 120)
                    || `proof${extension}`;
                const fileId = randomUUID();
                const filePath = path.join(submissionTaskDirectory, `${fileId}${extension}`);
                await writeFile(filePath, proofFile.buffer, { flag: 'wx' });
                uploadedFilePaths.push(filePath);
                proofs.push({
                    id: fileId,
                    name: originalName,
                    content_type: contentTypes[extension],
                    size: proofFile.size,
                    url: `/api/student-tasks/${encodeURIComponent(taskId)}/submissions/files/${encodeURIComponent(fileId)}`
                });
            }

            const submission = {
                submitted_at: submittedAt,
                file: proofs[0] || null,
                files: proofs,
                link: safeLink,
                notes,
                student_name: application.student_name || application.studentName
                    || application.full_name || application.fullName || application.name
                    || application.student?.name || application.student?.full_name
                    || [application.student?.first_name || application.student?.firstName,
                        application.student?.last_name || application.student?.lastName]
                        .filter(Boolean)
                        .join(' ')
            };
            const updatedApplication = {
                ...application,
                status: 'under_review',
                marked_done_at: submittedAt,
                revision_pending: false,
                submission
            };
            const updatedTask = {
                ...task,
                status: 'under_review',
                marked_done_at: submittedAt,
                progress: 80,
                revision_pending: false,
                submission
            };
            if (sourceName && applicationSource) {
                const updatedApplicationSource = Array.isArray(applicationSource)
                    ? [...applicationSource]
                    : { ...applicationSource };
                updatedApplicationSource[applicationPathKey] = updatedApplication;
                updatedTask[sourceName] = updatedApplicationSource;
            }

            const credentialPath = process.env.FIREBASE_SERVICE_ACCOUNT_PATH || process.env.GOOGLE_APPLICATION_CREDENTIALS;
            if (credentialPath) {
                const updates = { [`tasks/${taskId}`]: updatedTask };
                if (rootApplication && typeof rootApplication === 'object') {
                    updates[`student_applications/${studentApplicationKey}/${taskId}`] = updatedApplication;
                }
                await getTaskDatabase().ref().update(updates);
            } else {
                const taskResponse = await fetch(`${FIREBASE_DB_URL}/tasks/${encodeURIComponent(taskId)}.json`, {
                    method: 'PUT',
                    headers: getFirebaseHeaders(req, { 'Content-Type': 'application/json' }),
                    body: JSON.stringify(updatedTask)
                });
                if (!taskResponse.ok) {
                    throw new Error(`Firebase returned ${taskResponse.status} while saving the task submission.`);
                }
            }
            taskSubmissionSaved = true;
            if (!credentialPath && rootApplication && typeof rootApplication === 'object') {
                const applicationResponse = await fetch(
                    `${FIREBASE_DB_URL}/student_applications/${studentApplicationKey}/${encodeURIComponent(taskId)}.json`,
                    {
                        method: 'PUT',
                        headers: getFirebaseHeaders(req, { 'Content-Type': 'application/json' }),
                        body: JSON.stringify(updatedApplication)
                    }
                );
                if (!applicationResponse.ok) {
                    throw new Error(`Firebase returned ${applicationResponse.status} while updating the student application.`);
                }
            }

            return res.json({ success: true, submission, application: updatedApplication, task: updatedTask });
        } catch (error) {
            if (uploadedFilePaths.length && !taskSubmissionSaved) {
                await Promise.all(uploadedFilePaths.map((filePath) => unlink(filePath).catch((deleteError) => {
                    console.error('Could not clean up an unreferenced task proof:', deleteError.message);
                })));
            }
            console.error('Student task submission error:', error.message);
            return res.status(502).json({ success: false, message: 'Could not submit this task. Please try again.' });
        }
    }
);

router.post('/api/student-tasks/:taskId/complete', requireAppSession('student'), async (req, res) => {
    const taskId = String(req.params.taskId || '').trim();
    if (!taskId) return res.status(400).json({ success: false, message: 'A task ID is required.' });

    try {
        const studentApplicationKey = getStudentApplicationKey(req.session.email);
        const [task, studentApplications] = await Promise.all([
            readTask(taskId),
            readFirebaseJson(
                `${FIREBASE_DB_URL}/student_applications/${studentApplicationKey}.json`,
                { allowPermissionDenied: true, requestHeaders: getFirebaseHeaders(req) }
            )
        ]);
        if (!task) return res.status(404).json({ success: false, message: 'Task not found.' });

        const applicationSources = [
            ['applicants_by_student', task.applicants_by_student],
            ['student_applications', task.student_applications],
            ['studentApplications', task.studentApplications],
            ['applicants', task.applicants],
            ['applications', task.applications]
        ];
        let sourceName = '';
        let applicationSource = null;
        let applicationPathKey = '';
        let application = null;

        for (const [candidateSourceName, candidateSource] of applicationSources) {
            if (!candidateSource || typeof candidateSource !== 'object') continue;
            const entry = Object.entries(candidateSource).find(([, candidate]) =>
                matchesCurrentStudent(candidate, req.session)
            );
            if (!entry) continue;
            [applicationPathKey, application] = entry;
            sourceName = candidateSourceName;
            applicationSource = candidateSource;
            break;
        }

        const rootApplication = studentApplications?.[taskId];
        if (!application && rootApplication && typeof rootApplication === 'object') {
            application = rootApplication;
        }
        if (!application) {
            return res.status(403).json({ success: false, message: 'You do not have an approved application for this task.' });
        }

        const currentStatus = String(application.status || application.application_status
            || application.approval_status || '').trim().toLowerCase();
        if (currentStatus === 'under_review') {
            return res.json({ success: true, already_submitted: true, task, application });
        }
        if (currentStatus === 'completed') {
            return res.json({ success: true, already_completed: true, task });
        }
        if (!['approved', 'accepted', 'assigned', 'in_progress'].includes(currentStatus)) {
            return res.status(409).json({ success: false, message: 'Only an approved task can be marked complete.' });
        }

        const markedDoneAt = new Date().toISOString();
        const updatedApplication = { ...application, status: 'under_review', marked_done_at: markedDoneAt };
        const updatedTask = {
            ...task,
            status: 'under_review',
            marked_done_at: markedDoneAt,
            progress: 80
        };
        if (sourceName && applicationSource) {
            const updatedApplicationSource = Array.isArray(applicationSource)
                ? [...applicationSource]
                : { ...applicationSource };
            updatedApplicationSource[applicationPathKey] = updatedApplication;
            updatedTask[sourceName] = updatedApplicationSource;
        }

        const updateResponse = await fetch(
            `${FIREBASE_DB_URL}/tasks/${encodeURIComponent(taskId)}.json`,
            {
                method: 'PUT',
                headers: getFirebaseHeaders(req, { 'Content-Type': 'application/json' }),
                body: JSON.stringify(updatedTask)
            }
        );
        if (!updateResponse.ok) {
            throw new Error(`Firebase returned ${updateResponse.status} while submitting the task for review.`);
        }

        if (rootApplication && typeof rootApplication === 'object') {
            const applicationResponse = await fetch(
                `${FIREBASE_DB_URL}/student_applications/${studentApplicationKey}/${encodeURIComponent(taskId)}.json`,
                {
                    method: 'PUT',
                    headers: getFirebaseHeaders(req, { 'Content-Type': 'application/json' }),
                    body: JSON.stringify(updatedApplication)
                }
            );
            if (!applicationResponse.ok) {
                throw new Error(`Firebase returned ${applicationResponse.status} while updating the student application.`);
            }
        }

        res.json({ success: true, application: updatedApplication, task: updatedTask });
    } catch (error) {
        console.error('Student task completion error:', error.message);
        res.status(502).json({ success: false, message: 'Could not mark this task as complete. Please try again.' });
    }
});

router.post('/api/student-tasks/:taskId/applications', requireAppSession('student'), async (req, res) => {
    const requiredConfirmations = ['requirements', 'availability', 'attachments', 'payment', 'responsibility'];
    if (!requiredConfirmations.every((name) => req.body?.confirmations?.[name] === true)) {
        return res.status(400).json({ success: false, message: 'Confirm all task requirements before applying.' });
    }

    const taskId = String(req.params.taskId || '').trim();
    if (!taskId) return res.status(400).json({ success: false, message: 'A task ID is required.' });

    const studentApplicationKey = getStudentApplicationKey(req.session.email);
    const taskApplicationUrl = `${FIREBASE_DB_URL}/tasks/${encodeURIComponent(taskId)}/applicants_by_student/${studentApplicationKey}.json`;

    try {
        const task = await readTask(taskId);
        if (!task) return res.status(404).json({ success: false, message: 'This task is no longer available.' });

        const [existingApplication, taskApplications] = await Promise.all([
            Promise.resolve(task.applicants_by_student?.[studentApplicationKey] || null),
            Promise.resolve(task.applicants_by_student || {})
        ]);
        const matchingTaskApplication = Object.entries(taskApplications || {})
            .find(([, candidate]) => matchesCurrentStudent(candidate, req.session))?.[1];
        if (existingApplication || taskApplications?.[studentApplicationKey] || matchingTaskApplication) {
            return res.json({
                success: true,
                already_applied: true,
                application: existingApplication || taskApplications[studentApplicationKey] || matchingTaskApplication
            });
        }

        const taskStatus = String(task.status || '').trim().toLowerCase();
        if (['assigned', 'ongoing', 'in_progress', 'completed'].includes(taskStatus)
            || task.is_completed || task.completed) {
            return res.status(409).json({ success: false, message: 'This task is no longer accepting applications.' });
        }

        const application = {
            task_id: taskId,
            task_title: String(task.title || task.task_title || task.taskName || '').trim(),
            student_name: [
                req.session.first_name,
                req.session.middle_name,
                req.session.last_name
            ].filter(Boolean).join(' ').trim(),
            student_email: req.session.email,
            student_id: req.session.student_id || '',
            firebase_uid: req.session.firebase_uid || '',
            status: 'pending',
            confirmations: Object.fromEntries(requiredConfirmations.map((name) => [name, true])),
            created_at: new Date().toISOString()
        };

        const saveResponse = await fetch(taskApplicationUrl, {
            method: 'PUT',
            headers: getFirebaseHeaders(req, { 'Content-Type': 'application/json' }),
            body: JSON.stringify(application)
        });
        if (!saveResponse.ok) {
            const responseText = await saveResponse.text();
            throw new Error(`Firebase returned ${saveResponse.status} while saving the task application: ${responseText}`);
        }

        res.status(201).json({ success: true, application });
    } catch (error) {
        console.error('Student Firebase task application error:', error.message);
        res.status(502).json({ success: false, message: 'Could not submit your application. Please try again.' });
    }
});

router.post('/api/teacher-tasks', requireAppSession('teacher'), async (req, res) => {
    try {
        const paymentMethod = String(req.body.payment_method || '').trim();
        if (!['cash', 'e_wallet_bank_transfer'].includes(paymentMethod)) {
            return res.status(400).json({ success: false, message: 'A valid payment method is required.' });
        }

        const task = {
            ...req.body,
            title: String(req.body.title || '').trim(),
            payment_method: paymentMethod,
            teacher_email: req.session.email,
            teacher_name: `${req.session.first_name || ''} ${req.session.last_name || ''}`.trim(),
            teacher_photo: req.body.teacher_photo || req.session.photo || '',
            created_at: new Date().toISOString()
        };
        if (!task.title) return res.status(400).json({ success: false, message: 'Task title is required.' });

        const taskId = await saveTaskWithTitleKey(task);
        res.json({ success: true, task_id: taskId });
    } catch (error) {
        console.error('Teacher Firebase task write error:', error.message);
        res.status(502).json({ success: false, message: `Could not save the task to Firebase: ${error.message}` });
    }
});

router.post('/api/teacher-tasks/:taskId/repost', requireAppSession('teacher'), async (req, res) => {
    try {
        const sourceTask = await readTask(req.params.taskId);
        if (!sourceTask) return res.status(404).json({ success: false, message: 'Task not found.' });
        if (!isTaskOwnedByTeacher(sourceTask, req.session.email)) {
            return res.status(403).json({ success: false, message: 'You can only repost your own tasks.' });
        }

        const {
            id,
            task_id,
            taskId,
            student_id,
            studentId,
            student_email,
            studentEmail,
            student_name,
            studentName,
            assigned_to,
            assignedTo,
            assignee,
            completed_at,
            completedAt,
            updated_at,
            ...taskDetails
        } = sourceTask;
        const repostedTask = {
            ...taskDetails,
            teacher_email: req.session.email,
            teacher_name: `${req.session.first_name || ''} ${req.session.last_name || ''}`.trim(),
            status: 'pending',
            progress: 0,
            applications: 0,
            created_at: new Date().toISOString()
        };

        const repostedTaskId = await saveTaskWithTitleKey(repostedTask);
        res.json({ success: true, task_id: repostedTaskId, task: repostedTask });
    } catch (error) {
        console.error('Teacher Firebase task repost error:', error.message);
        res.status(502).json({ success: false, message: `Could not repost the task: ${error.message}` });
    }
});

router.get('/api/teacher-tasks', requireAppSession('teacher'), async (req, res) => {
    try {
        const [tasksData, transactionsData] = await Promise.all([
            readAllTasks(),
            readFirebaseJson(`${FIREBASE_DB_URL}/transactions.json`, {
                allowPermissionDenied: true,
                requestHeaders: getFirebaseHeaders(req)
            })
        ]);
        const transactionsByTaskId = new Map(
            Object.values(transactionsData || {})
                .filter((transaction) => transaction && typeof transaction === 'object' && transaction.task_id)
                .map((transaction) => [String(transaction.task_id), transaction])
        );
        const tasks = Object.entries(tasksData || {})
            .map(([id, task]) => {
                const hasApprovedApplicant = [
                    task?.applicants_by_student,
                    task?.student_applications,
                    task?.studentApplications,
                    task?.applicants,
                    task?.applications
                ].some((applications) => applications && Object.values(applications).some((application) => {
                    if (!application || typeof application !== 'object') return false;
                    const applicationStatus = String(application.status || application.application_status
                        || application.approval_status || '').trim().toLowerCase();
                    return applicationStatus === 'approved' || applicationStatus === 'accepted';
                }));
                const transactionKey = task?.transaction_id
                    || `task_${createHash('sha256').update(id).digest('hex')}`;
                const transaction = transactionsData?.[transactionKey] || transactionsByTaskId.get(id);
                return {
                    ...task,
                    id,
                    title: String(task?.title || '').trim(),
                    payment_status: transaction?.status || task?.payment_status
                        || (['completed', 'complete', 'done'].includes(String(task?.status || '').toLowerCase())
                            || task?.is_completed === true || task?.completed === true
                            ? 'pending'
                            : ''),
                    status: String(task?.status || '').toLowerCase() === 'pending' && hasApprovedApplicant
                        ? 'ongoing'
                        : task?.status,
                    progress: (
                        (String(task?.status || '').toLowerCase() === 'pending' && hasApprovedApplicant)
                        || ['ongoing', 'in_progress'].includes(String(task?.status || '').toLowerCase())
                    ) && !(Number(task?.progress) > 0)
                        ? 10
                        : task?.progress
                };
            })
                .filter((task) => task.title)
                .filter((task) => isTaskOwnedByTeacher(task, req.session.email))
                .sort((first, second) => new Date(second.created_at || 0) - new Date(first.created_at || 0));
            res.json({ success: true, tasks });
        } catch (error) {
            console.error('Teacher Firebase task read error:', error.message);
            res.status(502).json({ success: false, message: `Could not load tasks from Firebase: ${error.message}` });
        }
    });

    router.get('/api/teacher-tasks/:taskId/applicants/:applicantKey', requireAppSession('teacher'), async (req, res) => {
        try {
            const taskId = String(req.params.taskId || '');
            const applicantKey = String(req.params.applicantKey || '');
            const task = await readTask(taskId);
            if (!task) return res.status(404).json({ success: false, message: 'Task not found.' });
            if (String(task.teacher_email || task.teacherEmail || '').trim().toLowerCase() !== req.session.email.toLowerCase()) {
                return res.status(403).json({ success: false, message: 'You do not have access to this applicant.' });
            }

            const applicationSource = task.applicants_by_student
                ?? task.student_applications
                ?? task.studentApplications
                ?? task.applicants
                ?? task.applications;
            const application = Array.isArray(applicationSource)
                ? applicationSource[Number.parseInt(applicantKey, 10)]
                : applicationSource?.[applicantKey];
            if (typeof application === 'string') {
                return res.json({
                    success: true,
                    student: {
                        full_name: application,
                        first_name: application.trim().split(/\s+/)[0] || '',
                        application: {
                            task_title: String(task.title || task.task_title || task.taskName || '').trim(),
                            status: 'pending',
                            applied_at: '',
                            message: ''
                        }
                    }
                });
            }
            if (!application || typeof application !== 'object') {
                return res.status(404).json({ success: false, message: 'Applicant not found.' });
            }

            const embeddedStudent = application.student && typeof application.student === 'object'
                ? application.student
                : {};
            const studentEmail = normalizeIdentity(application.student_email || application.studentEmail
                || application.email || application.user_email || application.userEmail
                || embeddedStudent.email);
            const studentId = normalizeIdentity(application.student_id || application.studentId
                || embeddedStudent.student_id || embeddedStudent.studentId);
            const firebaseUid = normalizeIdentity(application.firebase_uid || application.firebaseUid
                || application.uid || embeddedStudent.firebase_uid || embeddedStudent.firebaseUid);
            const applicantName = normalizeIdentity(application.student_name || application.studentName
                || application.full_name || application.fullName || application.name
                || embeddedStudent.name || embeddedStudent.full_name);
            const students = await readFirebaseJson(`${FIREBASE_DB_URL}/students.json`);
            const studentEntries = Object.entries(students || {});
            const studentEntry = studentEntries.find(([key, student]) => {
                const recordEmail = normalizeIdentity(student?.email);
                const recordEmailHash = recordEmail
                    ? createHash('sha256').update(recordEmail).digest('hex')
                    : '';
                const recordId = normalizeIdentity(student?.student_id || student?.studentId);
                const recordUid = normalizeIdentity(student?.firebase_uid || student?.firebaseUid || student?.uid);
                return (studentEmail && recordEmail === studentEmail)
                    || recordEmailHash === applicantKey
                    || (studentId && recordId === studentId)
                    || (firebaseUid && recordUid === firebaseUid)
                    || (studentEmail && key === createHash('sha256').update(studentEmail).digest('hex'))
                    || key === applicantKey;
            }) || studentEntries.find(([, student]) => {
                const recordName = normalizeIdentity(student?.full_name || student?.fullName || student?.name
                    || [student?.first_name || student?.firstName, student?.middle_name || student?.middleName,
                        student?.last_name || student?.lastName].filter(Boolean).join(' '));
                return applicantName && recordName === applicantName;
            });
            const studentRecord = studentEntry?.[1] || {};
            const firstName = studentRecord.first_name || studentRecord.firstName
                || embeddedStudent.first_name || embeddedStudent.firstName || '';
            const middleName = studentRecord.middle_name || studentRecord.middleName
                || embeddedStudent.middle_name || embeddedStudent.middleName || '';
            const lastName = studentRecord.last_name || studentRecord.lastName
                || embeddedStudent.last_name || embeddedStudent.lastName || '';
            const fullName = [firstName, middleName, lastName].filter(Boolean).join(' ')
                || application.student_name || application.studentName || application.full_name
                || application.fullName || application.name || embeddedStudent.name || 'Student';
            const email = studentRecord.email || studentEmail || embeddedStudent.email || '';
            res.json({
                success: true,
                student: {
                    full_name: fullName,
                    first_name: firstName,
                    student_id: studentRecord.student_id || studentRecord.studentId
                        || application.student_id || application.studentId || '',
                    gender: studentRecord.gender || embeddedStudent.gender || application.gender || '',
                    phone: studentRecord.phone || studentRecord.phone_number || studentRecord.phoneNumber
                        || embeddedStudent.phone || embeddedStudent.phone_number || application.phone || '',
                    email,
                    email_verified: true,
                    course: studentRecord.course || studentRecord.program || embeddedStudent.course
                        || embeddedStudent.program || application.course || application.program || '',
                    year_level: studentRecord.year_level || studentRecord.yearLevel || studentRecord.level
                        || embeddedStudent.year_level || embeddedStudent.yearLevel || embeddedStudent.level || '',
                    address: studentRecord.address || embeddedStudent.address || '',
                    photo: studentRecord.photo || studentRecord.profile_photo || studentRecord.profilePhoto
                        || embeddedStudent.photo || '',
                    account_created: studentRecord.created_at || studentRecord.createdAt
                        || studentRecord.account_created || '',
                    application: {
                        task_title: String(task.title || task.task_title || task.taskName || '').trim(),
                        status: String(application.status || application.application_status
                            || application.approval_status || 'pending'),
                        applied_at: application.applied_at || application.appliedAt || application.created_at
                            || application.createdAt || application.application_date || application.date || '',
                        message: application.application_message || application.cover_letter
                            || application.message || application.note || ''
                    }
                }
            });
        } catch (error) {
            console.error('Teacher applicant details read error:', error.message);
            res.status(502).json({ success: false, message: 'Could not load student details.' });
        }
    });

    router.patch('/api/teacher-tasks/:taskId/applicants/:applicantKey', requireAppSession('teacher'), async (req, res) => {
        try {
            const rawRequestedStatus = String(req.body.status || '').trim().toLowerCase();
            const requestedStatus = rawRequestedStatus === 'accepted' ? 'approved'
                : rawRequestedStatus === 'declined' ? 'rejected'
                : rawRequestedStatus;
            if (!['approved', 'rejected'].includes(requestedStatus)) {
                return res.status(400).json({ success: false, message: 'Application status must be approved or rejected.' });
            }
            const taskId = String(req.params.taskId || '');
            const applicantKey = String(req.params.applicantKey || '');
            const task = await readTask(taskId);
            if (!task) return res.status(404).json({ success: false, message: 'Task not found.' });
            if (String(task.teacher_email || task.teacherEmail || '').trim().toLowerCase() !== req.session.email.toLowerCase()) {
                return res.status(403).json({ success: false, message: 'You do not have access to this applicant.' });
            }

            const applicationSources = [
                ['applicants_by_student', task.applicants_by_student],
                ['student_applications', task.student_applications],
                ['studentApplications', task.studentApplications],
                ['applicants', task.applicants],
                ['applications', task.applications]
            ];

            let sourceName = '';
            let applicationSource = null;
            let applicationPathKey = '';
            let application = null;

            for (const [candidateSourceName, candidateSource] of applicationSources) {
                if (!candidateSource || typeof candidateSource !== 'object') continue;
                const entries = Array.isArray(candidateSource)
                    ? candidateSource.map((value, index) => [String(index), value])
                    : Object.entries(candidateSource);

                const matchedEntry = entries.find(([key, candidate]) => {
                    if (candidate === null || candidate === undefined || typeof candidate !== 'object') {
                        return String(key) === applicantKey && typeof candidate === 'string';
                    }
                    return key === applicantKey
                        || String(candidate.student_email || candidate.studentEmail || candidate.email || '').trim().toLowerCase() === applicantKey.toLowerCase()
                        || String(candidate.student_id || candidate.studentId || '').trim() === applicantKey
                        || String(candidate.firebase_uid || candidate.firebaseUid || candidate.uid || '').trim() === applicantKey;
                });

                if (!matchedEntry) continue;
                [applicationPathKey, application] = matchedEntry;
                sourceName = candidateSourceName;
                applicationSource = candidateSource;
                break;
            }

            if (!sourceName || !applicationSource || !application || typeof application !== 'object') {
                return res.status(404).json({ success: false, message: 'Applicant not found.' });
            }

            const currentStatus = String(application.status || application.application_status
                || application.approval_status || 'pending').trim().toLowerCase();
            if (currentStatus !== 'pending') {
                return res.status(409).json({ success: false, message: 'This application has already been processed.' });
            }

            const approvedAt = new Date().toISOString();
            const updatedApplication = {
                ...application,
                status: requestedStatus,
                [`${requestedStatus}_at`]: approvedAt,
                ...(requestedStatus === 'approved'
                    ? {
                        acceptance_message: {
                            id: `task-acceptance-${taskId}-${applicationPathKey}`,
                            sender: 'teacher',
                            text: `I accept you to this task: ${String(task.title || task.task_title || task.taskName || '').trim()}. Please complete it before the deadline. Thank you.`,
                            createdAt: approvedAt
                        }
                    }
                    : {})
            };

            const updatedTask = { ...task };
            if (requestedStatus === 'approved') {
                updatedTask.status = 'ongoing';
                updatedTask.progress = Math.max(10, Number(task.progress) || 0);
            }
            updatedTask.updated_at = new Date().toISOString();

            for (const [candidateSourceName, candidateSource] of applicationSources) {
                if (!candidateSource || typeof candidateSource !== 'object') continue;
                const updatedSource = Array.isArray(candidateSource)
                    ? [...candidateSource]
                    : { ...candidateSource };
                const entries = Array.isArray(updatedSource)
                    ? updatedSource.map((value, index) => [String(index), value])
                    : Object.entries(updatedSource);

                let didUpdateSource = false;
                for (const [key, candidate] of entries) {
                    if (!candidate || typeof candidate !== 'object') continue;
                    if (key === applicationPathKey || candidate === application) {
                        updatedSource[key] = updatedApplication;
                        didUpdateSource = true;
                    }
                }

                if (didUpdateSource) {
                    updatedTask[candidateSourceName] = updatedSource;
                }
            }

            const credentialPath = process.env.FIREBASE_SERVICE_ACCOUNT_PATH || process.env.GOOGLE_APPLICATION_CREDENTIALS;
            if (credentialPath) {
                await getTaskDatabase().ref(`tasks/${taskId}`).set(updatedTask);
            } else {
                const updateUrl = `${FIREBASE_DB_URL}/tasks/${encodeURIComponent(taskId)}.json`;
                const updateOptions = {
                    method: 'PUT',
                    headers: getFirebaseHeaders(req, { 'Content-Type': 'application/json' }),
                    body: JSON.stringify(updatedTask)
                };
                let updateResponse = await fetch(updateUrl, updateOptions);
                if (updateResponse.status === 401) {
                    try {
                        await refreshFirebaseSessionToken(req);
                    } catch (refreshError) {
                        return res.status(401).json({
                            success: false,
                            message: 'Firebase rejected the teacher session. Please verify the Firebase service account configuration or sign in again.'
                        });
                    }
                    updateOptions.headers = getFirebaseHeaders(req, { 'Content-Type': 'application/json' });
                    updateResponse = await fetch(updateUrl, updateOptions);
                }
                if (!updateResponse.ok) {
                    if (updateResponse.status === 401) {
                        return res.status(401).json({
                            success: false,
                            message: 'Firebase rejected the teacher session. Please verify the Firebase service account configuration or sign in again.'
                        });
                    }
                    throw new Error(`Firebase returned ${updateResponse.status} while updating applicant status.`);
                }
            }

            res.json({ success: true, application: updatedApplication, task: updatedTask });
        } catch (error) {
            console.error('Teacher applicant status update error:', error);
            res.status(502).json({ success: false, message: 'Could not update this application.' });
        }
    });

    router.get('/api/teacher-tasks/:taskId/payment', requireAppSession('teacher'), async (req, res) => {
        const taskId = String(req.params.taskId || '').trim();
        if (!taskId) return res.status(400).json({ success: false, message: 'A task ID is required.' });

        try {
            const task = await readTask(taskId);
            if (!task) return res.status(404).json({ success: false, message: 'Task not found.' });
            if (!isTaskOwnedByTeacher(task, req.session.email)) {
                return res.status(403).json({ success: false, message: 'You can only view payment details for your own tasks.' });
            }
            if (String(task.status || '').trim().toLowerCase() !== 'completed'
                && task.is_completed !== true && task.completed !== true) {
                return res.status(409).json({ success: false, message: 'Payment is available only after task completion is approved.' });
            }

            const application = getCompletedTaskApplicant(task);
            if (!application) {
                return res.status(409).json({ success: false, message: 'No completed student submission is linked to this task.' });
            }
            const paymentBreakdown = getTaskPaymentBreakdown(task);
            const paymentAmount = paymentBreakdown.totalAmount;
            if (!Number.isFinite(paymentAmount) || paymentAmount <= 0) {
                return res.status(409).json({ success: false, message: 'This task does not have a valid agreed payment amount.' });
            }

            const transactionKey = `task_${createHash('sha256').update(taskId).digest('hex')}`;
            const transaction = await readFirebaseJson(
                `${FIREBASE_DB_URL}/transactions/${transactionKey}.json`,
                { allowPermissionDenied: true, requestHeaders: getFirebaseHeaders(req) }
            );
            const payMongoConfig = getPayMongoConfig();
            return res.json({
                success: true,
                pass_on_fees: payMongoConfig.passOnFees,
                payment_methods: Object.fromEntries(
                    Object.entries(payMongoConfig.methodTypes).map(([category, methodTypes]) => [
                        category,
                        methodTypes.length > 0
                    ])
                ),
                task: {
                    id: taskId,
                    title: String(task.title || task.task_title || task.taskName || 'Untitled task'),
                    status: 'completed',
                    amount: paymentAmount,
                    task_amount: paymentBreakdown.taskAmount,
                    platform_fee: paymentBreakdown.platformFee,
                    total_amount: paymentAmount
                },
                student: {
                    name: getPaymentStudentName(application),
                    email: getPaymentStudentEmail(application)
                        || String(transaction?.student_email || '').trim()
                },
                transaction: transaction && typeof transaction === 'object'
                    ? {
                        id: transaction.transaction_id || transactionKey,
                        payment_method: transaction.payment_method || '',
                        status: transaction.status || 'pending',
                        status_label: transaction.status_label || 'Payment Pending',
                        checkout_url: transaction.paymongo_checkout_url || '',
                        reference_number: transaction.paymongo_reference_number || '',
                        created_at: transaction.created_at || ''
                    }
                    : null
            });
        } catch (error) {
            console.error('Teacher task payment details read error:', error.message);
            return res.status(502).json({ success: false, message: 'Could not load payment details for this task.' });
        }
    });

    router.post('/api/teacher-tasks/:taskId/payment', requireAppSession('teacher'), async (req, res) => {
        const taskId = String(req.params.taskId || '').trim();
        const paymentMethod = String(req.body.payment_method || '').trim();
        if (!taskId) return res.status(400).json({ success: false, message: 'A task ID is required.' });
        if (!['e_wallet', 'bank_transfer', 'card'].includes(paymentMethod)) {
            return res.status(400).json({ success: false, message: 'Choose a supported payment category.' });
        }

        let config;
        try {
            config = getPayMongoConfig();
            if (!config.secretKey) {
                return res.status(503).json({
                    success: false,
                    message: 'PayMongo is not configured. Add a test secret key to the server environment.'
                });
            }
            if (!config.webhookSecret) {
                return res.status(503).json({
                    success: false,
                    message: 'PayMongo webhook verification is not configured. Add the webhook signing secret before accepting payments.'
                });
            }
            if (!config.methodTypes[paymentMethod]?.length) {
                return res.status(400).json({
                    success: false,
                    message: 'This payment category has no enabled PayMongo methods.'
                });
            }

            const task = await readTask(taskId);
            if (!task) return res.status(404).json({ success: false, message: 'Task not found.' });
            if (!isTaskOwnedByTeacher(task, req.session.email)) {
                return res.status(403).json({ success: false, message: 'You can only pay for your own tasks.' });
            }
            if (String(task.status || '').trim().toLowerCase() !== 'completed'
                && task.is_completed !== true && task.completed !== true) {
                return res.status(409).json({ success: false, message: 'Approve task completion before paying.' });
            }

            const application = getCompletedTaskApplicant(task);
            if (!application) {
                return res.status(409).json({ success: false, message: 'No completed student submission is linked to this task.' });
            }
            const breakdown = getTaskPaymentBreakdown(task);
            const paymentAmount = breakdown.totalAmount;
            if (!Number.isFinite(paymentAmount) || paymentAmount <= 0) {
                return res.status(409).json({ success: false, message: 'This task does not have a valid agreed payment amount.' });
            }
            const amountInCentavos = amountToCentavos(paymentAmount);

            const transactionKey = `task_${createHash('sha256').update(taskId).digest('hex')}`;
            const transactionRef = getTaskDatabase().ref(`transactions/${transactionKey}`);
            const existingSnapshot = await transactionRef.get();
            const existing = existingSnapshot.val();
            const existingStatus = String(existing?.status || '').trim().toLowerCase().replace(/[\s-]+/g, '_');
            if (existingStatus === 'paid') {
                return res.status(409).json({
                    success: false,
                    message: 'PayMongo has already confirmed this transaction. No additional payment is needed.'
                });
            }

            const timestamp = new Date().toISOString();
            if (existing?.paymongo_checkout_session_id && existing?.paymongo_checkout_url
                && existingStatus === 'awaiting_payment') {
                const checkoutSession = await retrievePayMongoCheckoutSession(
                    config,
                    existing.paymongo_checkout_session_id
                );
                const sessionStatus = String(checkoutSession?.attributes?.status || '').toLowerCase();
                if (sessionStatus === 'active') {
                    return res.json({
                        success: true,
                        checkout_url: existing.paymongo_checkout_url,
                        transaction: {
                            id: transactionKey,
                            status: existing.status,
                            status_label: existing.status_label,
                            reference_number: existing.paymongo_reference_number
                        }
                    });
                }
                if (sessionStatus === 'completed') {
                    return res.json({
                        success: true,
                        awaiting_webhook: true,
                        result_url: `/teacher-payment-result.html?transactionId=${encodeURIComponent(transactionKey)}`,
                        transaction: {
                            id: transactionKey,
                            status: existing.status,
                            status_label: existing.status_label,
                            reference_number: existing.paymongo_reference_number
                        }
                    });
                }
                if (sessionStatus !== 'expired') {
                    return res.status(409).json({
                        success: false,
                        message: 'The existing PayMongo checkout is still being processed. Check its status before retrying.'
                    });
                }
                const expiredCheckout = await transactionRef.transaction((current) => {
                    const currentStatus = String(current?.status || '').toLowerCase().replace(/[\s-]+/g, '_');
                    if (current?.paymongo_checkout_session_id !== existing.paymongo_checkout_session_id
                        || currentStatus !== 'awaiting_payment') return;
                    return {
                        ...current,
                        status: 'payment_pending',
                        status_label: 'Payment Pending',
                        paymongo_checkout_session_id: null,
                        paymongo_checkout_url: null,
                        paymongo_reference_number: null,
                        checkout_creation_state: null,
                        checkout_creation_error: null,
                        updated_at: new Date().toISOString()
                    };
                });
                if (!expiredCheckout.committed) {
                    const current = expiredCheckout.snapshot.val();
                    if (current?.paymongo_checkout_url
                        && String(current.status || '').toLowerCase() === 'awaiting_payment') {
                        return res.json({
                            success: true,
                            checkout_url: current.paymongo_checkout_url,
                            transaction: {
                                id: transactionKey,
                                status: current.status,
                                status_label: current.status_label,
                                reference_number: current.paymongo_reference_number
                            }
                        });
                    }
                    return res.status(409).json({
                        success: false,
                        message: 'The checkout status changed while it was being refreshed. Check the transaction before trying again.'
                    });
                }
            }

            const timestampMs = Date.now();
            const newReferenceNumber = `UTOS-${randomUUID().replace(/-/g, '').slice(0, 24).toUpperCase()}`;
            const taskTitle = String(task.title || task.task_title || task.taskName || 'Untitled task').slice(0, 100);
            const paymentMethodTypes = config.methodTypes[paymentMethod];
            const reservation = await transactionRef.transaction((current) => {
                const status = String(current?.status || '').toLowerCase().replace(/[\s-]+/g, '_');
                if (status === 'paid') return;
                if (current?.paymongo_checkout_url && status === 'awaiting_payment') return;
                const startedAt = Date.parse(current?.checkout_creation_started_at || '');
                if (current?.checkout_creation_state === 'creating'
                    && Number.isFinite(startedAt) && timestampMs - startedAt < 120000) return;
                const sameCheckoutRequest = current?.paymongo_reference_number
                    && current.payment_method === paymentMethod
                    && Number(current.amount_centavos) === amountInCentavos
                    && current.task_title === taskTitle
                    && current.paymongo_pass_on_fees === config.passOnFees
                    && JSON.stringify(current.paymongo_payment_method_types) === JSON.stringify(paymentMethodTypes);
                return {
                    ...(current && typeof current === 'object' ? current : {}),
                    transaction_id: transactionKey,
                    task_id: taskId,
                    task_title: taskTitle,
                    teacher_id: String(req.session.firebase_uid || req.session.uid || req.session.email),
                    teacher_email: req.session.email,
                    student_id: String(application.student_id || application.studentId
                        || application.applicant_id || application.applicantId
                        || getPaymentStudentEmail(application)),
                    student_email: getPaymentStudentEmail(application),
                    student_name: getPaymentStudentName(application),
                    task_amount: breakdown.taskAmount,
                    platform_fee: breakdown.platformFee,
                    amount: paymentAmount,
                    total_amount: paymentAmount,
                    amount_centavos: amountInCentavos,
                    currency: 'PHP',
                    payment_method: paymentMethod,
                    paymongo_payment_method_types: paymentMethodTypes,
                    paymongo_pass_on_fees: config.passOnFees,
                    student_payment_status: 'awaiting_payout',
                    status: 'payment_pending',
                    status_label: 'Payment Pending',
                    paymongo_reference_number: sameCheckoutRequest
                        ? current.paymongo_reference_number
                        : newReferenceNumber,
                    paymongo_checkout_session_id: null,
                    paymongo_checkout_url: null,
                    checkout_creation_state: 'creating',
                    checkout_creation_started_at: timestamp,
                    created_at: current?.created_at || timestamp,
                    updated_at: timestamp
                };
            });
            const reservedTransaction = reservation.snapshot.val();
            if (!reservation.committed) {
                if (reservedTransaction?.paymongo_checkout_url) {
                    return res.json({
                        success: true,
                        checkout_url: reservedTransaction.paymongo_checkout_url,
                        transaction: {
                            id: transactionKey,
                            status: reservedTransaction.status,
                            status_label: reservedTransaction.status_label,
                            reference_number: reservedTransaction.paymongo_reference_number
                        }
                    });
                }
                return res.status(409).json({
                    success: false,
                    message: 'A checkout request is already being created. Wait a moment and try again.'
                });
            }
            if (reservedTransaction?.paymongo_checkout_url) {
                return res.json({
                    success: true,
                    checkout_url: reservedTransaction.paymongo_checkout_url,
                    transaction: {
                        id: transactionKey,
                        status: reservedTransaction.status,
                        status_label: reservedTransaction.status_label,
                        reference_number: reservedTransaction.paymongo_reference_number
                    }
                });
            }

            const checkoutAttributes = {
                line_items: [{
                    name: reservedTransaction.task_title,
                    amount: amountInCentavos,
                    currency: 'PHP',
                    quantity: 1
                }],
                payment_method_types: reservedTransaction.paymongo_payment_method_types,
                success_url: `${config.baseUrl}/teacher-payment-result.html?transactionId=${encodeURIComponent(transactionKey)}`,
                cancel_url: `${config.baseUrl}/teacher-payment-cancelled.html?taskId=${encodeURIComponent(taskId)}&transactionId=${encodeURIComponent(transactionKey)}`,
                reference_number: reservedTransaction.paymongo_reference_number,
                pass_on_fees: reservedTransaction.paymongo_pass_on_fees,
                metadata: {
                    utosapp_transaction_id: transactionKey,
                    utosapp_task_id: taskId
                }
            };
            let checkout;
            try {
                checkout = await createPayMongoCheckoutSession(
                    config,
                    checkoutAttributes,
                    reservedTransaction.paymongo_reference_number
                );
            } catch (error) {
                await transactionRef.update({
                    checkout_creation_state: 'failed',
                    checkout_creation_error: error.providerCode || 'checkout_creation_failed',
                    updated_at: new Date().toISOString()
                });
                console.error('PayMongo checkout creation failed:', error.providerStatus || 'network error', error.providerCode || '');
                return res.status(502).json({
                    success: false,
                    message: 'Could not start PayMongo checkout. Verify the test key and enabled payment methods, then try again.'
                });
            }
            const checkoutUrl = checkout?.attributes?.checkout_url;
            const checkoutSessionId = checkout?.id;
            let parsedCheckoutUrl;
            try {
                parsedCheckoutUrl = new URL(checkoutUrl);
            } catch {
                parsedCheckoutUrl = null;
            }
            if (!checkoutSessionId
                || !parsedCheckoutUrl
                || parsedCheckoutUrl.protocol !== 'https:'
                || parsedCheckoutUrl.hostname !== 'checkout.paymongo.com') {
                await transactionRef.update({
                    checkout_creation_state: 'failed',
                    updated_at: new Date().toISOString()
                });
                console.error('PayMongo checkout response did not include a valid hosted checkout URL.');
                return res.status(502).json({
                    success: false,
                    message: 'PayMongo returned an invalid checkout response. No redirect was started.'
                });
            }
            const now = new Date().toISOString();
            const transaction = {
                ...reservedTransaction,
                status: 'awaiting_payment',
                status_label: 'Awaiting Payment',
                paymongo_checkout_session_id: checkoutSessionId,
                paymongo_checkout_url: checkoutUrl,
                paymongo_livemode: checkout.attributes.livemode === true,
                checkout_creation_state: 'created',
                checkout_creation_error: null,
                updated_at: now
            };
            await getTaskDatabase().ref().update({
                [`transactions/${transactionKey}`]: transaction,
                [`tasks/${taskId}`]: {
                    ...task,
                    payment_status: 'awaiting_payout',
                    transaction_id: transactionKey,
                    payment_method_selected: paymentMethod,
                    updated_at: now
                }
            });
            return res.json({
                success: true,
                checkout_url: checkoutUrl,
                transaction: {
                    id: transactionKey,
                    payment_method: transaction.payment_method,
                    status: transaction.status,
                    status_label: transaction.status_label,
                    amount: transaction.amount,
                    reference_number: transaction.paymongo_reference_number,
                    created_at: transaction.created_at
                }
            });
        } catch (error) {
            console.error('Teacher task PayMongo checkout error:', error.message);
            return res.status(502).json({ success: false, message: error.message || 'Could not start PayMongo checkout.' });
        }
    });

    router.get('/api/payment-transactions/:transactionId', async (req, res) => {
        const email = normalizeIdentity(req.session?.email);
        const role = String(req.session?.user_role || '').toLowerCase();
        const transactionId = String(req.params.transactionId || '');
        if (!email || !['teacher', 'student', 'admin'].includes(role)) {
            return res.status(401).json({ success: false, message: 'Sign in to view this payment transaction.' });
        }
        if (!/^task_[a-f\d]{64}$/i.test(transactionId)) {
            return res.status(400).json({ success: false, message: 'A valid transaction ID is required.' });
        }

        try {
            const snapshot = await getTaskDatabase().ref(`transactions/${transactionId}`).get();
            const transaction = snapshot.val();
            if (!transaction) return res.status(404).json({ success: false, message: 'Payment transaction not found.' });
            const isAuthorized = role === 'admin'
                || (role === 'teacher' && normalizeIdentity(transaction.teacher_email) === email)
                || (role === 'student' && normalizeIdentity(transaction.student_email) === email);
            if (!isAuthorized) {
                return res.status(403).json({ success: false, message: 'You are not allowed to view this payment transaction.' });
            }
            return res.json({
                success: true,
                transaction: {
                    id: transaction.transaction_id || transactionId,
                    task_id: transaction.task_id || '',
                    task_title: transaction.task_title || 'Task payment',
                    reference_number: transaction.paymongo_reference_number || '',
                    amount: Number(transaction.amount) || 0,
                    task_amount: Number(transaction.task_amount) || 0,
                    platform_fee: Number(transaction.platform_fee) || 0,
                    currency: transaction.currency || 'PHP',
                    payment_method: transaction.payment_method || '',
                    payment_method_type: transaction.paymongo_payment_method_type || '',
                    status: transaction.status || 'payment_pending',
                    status_label: transaction.status_label || 'Payment Pending',
                    student_payment_status: transaction.student_payment_status || 'awaiting_payout',
                    payout_status_label: transaction.payout_status_label || 'Student payout has not been initiated',
                    paymongo_fee: Number(transaction.paymongo_fee) || 0,
                    charged_amount: Number(transaction.charged_amount) || Number(transaction.amount) || 0,
                    created_at: transaction.created_at || '',
                    paid_at: transaction.paid_at || '',
                    teacher_email: role === 'admin' ? transaction.teacher_email || '' : undefined,
                    student_email: role === 'admin' ? transaction.student_email || '' : undefined,
                    checkout_url: role === 'teacher' ? transaction.paymongo_checkout_url || '' : undefined
                }
            });
        } catch (error) {
            console.error('Payment transaction read error:', error.message);
            return res.status(502).json({ success: false, message: 'Could not load this payment transaction.' });
        }
    });

    router.post('/api/webhooks/paymongo', async (req, res) => {
        let config;
        try {
            config = getPayMongoConfig();
        } catch (error) {
            console.error('PayMongo webhook configuration error:', error.message);
            return res.status(503).json({ success: false });
        }
        const rawBody = req.rawBody;
        const signature = req.get('paymongo-signature');
        if (!verifyPayMongoWebhookSignature(signature, rawBody, config.webhookSecret, config.mode)) {
            console.warn('Rejected PayMongo webhook with an invalid signature.');
            return res.status(400).json({ success: false });
        }

        try {
            const payload = JSON.parse(rawBody.toString('utf8'));
            const event = payload?.data?.type ? payload.data : payload;
            const eventType = String(event?.type || '').trim();
            if (eventType !== 'checkout_session.payment.paid') return res.sendStatus(200);

            const checkout = event?.data?.id ? event.data : event?.data?.data;
            const attributes = checkout?.attributes;
            const referenceNumber = String(attributes?.reference_number || '').trim();
            const transactionId = String(attributes?.metadata?.utosapp_transaction_id || '');
            const checkoutSessionId = String(checkout?.id || '');
            if (!/^task_[a-f\d]{64}$/i.test(transactionId)
                || !checkoutSessionId
                || !referenceNumber
                || event.livemode !== (config.mode === 'live')) {
                console.warn('Rejected PayMongo paid event with mismatched transaction metadata or mode.');
                return res.status(400).json({ success: false });
            }

            const transactionRef = getTaskDatabase().ref(`transactions/${transactionId}`);
            const transactionSnapshot = await transactionRef.get();
            const transaction = transactionSnapshot.val();
            if (!transaction
                || transaction.paymongo_checkout_session_id !== checkoutSessionId
                || transaction.paymongo_reference_number !== referenceNumber
                || transaction.task_id !== attributes?.metadata?.utosapp_task_id) {
                console.warn('Rejected PayMongo paid event that did not match the stored checkout transaction.');
                return res.status(400).json({ success: false });
            }

            const paidPayment = (Array.isArray(attributes.payments) ? attributes.payments : [])
                .find((payment) => String(payment?.attributes?.status || '').toLowerCase() === 'paid');
            const paymentAttributes = paidPayment?.attributes;
            const expectedCentavos = amountToCentavos(transaction.total_amount ?? transaction.amount);
            const merchantAmount = Number(paymentAttributes?.net_amount ?? paymentAttributes?.amount);
            const chargedCentavos = Number(paymentAttributes?.amount);
            const feeCentavos = Number(paymentAttributes?.fee || 0);
            if (!paymentAttributes
                || String(paymentAttributes.currency || '').toUpperCase() !== 'PHP'
                || !isExpectedPayMongoPaymentAmount({
                    expectedCentavos,
                    merchantAmount,
                    chargedCentavos,
                    feeCentavos,
                    passOnFees: config.passOnFees
                })) {
                console.warn('Rejected PayMongo paid event with unexpected currency or amount.');
                return res.status(400).json({ success: false });
            }

            const eventId = String(event.id || event.event_id || createHash('sha256').update(rawBody).digest('hex'));
            const paidAt = new Date().toISOString();
            const paidUpdate = await transactionRef.transaction((current) => {
                if (!current
                    || current.paymongo_checkout_session_id !== checkoutSessionId
                    || current.paymongo_reference_number !== referenceNumber
                    || ['refunded', 'partially_refunded'].includes(String(current.status || '').toLowerCase())) return;
                if (current.paymongo_paid_webhook_event_id === eventId && current.status === 'paid') return current;
                return {
                    ...current,
                    status: 'paid',
                    status_label: 'Payment Received from Teacher',
                    student_payment_status: 'awaiting_payout',
                    payout_status: 'not_initiated',
                    payout_status_label: 'Student payout has not been initiated',
                    paymongo_paid_webhook_event_id: eventId,
                    paymongo_payment_id: paidPayment.id || '',
                    paymongo_payment_method_type: paidPayment?.attributes?.source?.type
                        || paidPayment?.attributes?.payment_method?.type || '',
                    paymongo_fee: feeCentavos / 100,
                    charged_amount: chargedCentavos / 100,
                    paid_at: paidAt,
                    updated_at: paidAt
                };
            });
            const confirmedTransaction = paidUpdate.snapshot.val();
            if (!confirmedTransaction || confirmedTransaction.status !== 'paid') {
                console.error('PayMongo paid event transaction could not be safely applied.');
                return res.status(409).json({ success: false });
            }

            const task = await readTask(confirmedTransaction.task_id);
            if (!task) {
                console.error('PayMongo payment was confirmed but the associated task record is missing.');
                return res.status(500).json({ success: false });
            }
            await getTaskDatabase().ref().update({
                [`tasks/${confirmedTransaction.task_id}`]: {
                    ...task,
                    payment_status: 'awaiting_payout',
                    payment_status_label: 'Payment collected; student payout pending',
                    payment_collected_at: confirmedTransaction.paid_at,
                    transaction_id: transactionId,
                    updated_at: confirmedTransaction.updated_at
                }
            });
            return res.sendStatus(200);
        } catch (error) {
            console.error('PayMongo webhook processing error:', error.message);
            return res.status(500).json({ success: false });
        }
    });

    router.post('/api/teacher-tasks/:taskId/complete', requireAppSession('teacher'), async (req, res) => {
        const taskId = String(req.params.taskId || '').trim();
        if (!taskId) return res.status(400).json({ success: false, message: 'A task ID is required.' });

        try {
            const task = await readTask(taskId);
            if (!task) return res.status(404).json({ success: false, message: 'Task not found.' });
            if (!isTaskOwnedByTeacher(task, req.session.email)) {
                return res.status(403).json({ success: false, message: 'You can only complete your own tasks.' });
            }

            const completedAt = new Date().toISOString();
            const applicationSources = [
                ['applicants_by_student', task.applicants_by_student],
                ['student_applications', task.student_applications],
                ['studentApplications', task.studentApplications],
                ['applicants', task.applicants],
                ['applications', task.applications]
            ];
            const rootApplications = [];
            const updatedTask = {
                ...task,
                status: 'completed',
                is_completed: true,
                completed: true,
                payment_status: task.payment_status || 'pending',
                completed_at: completedAt,
                progress: 100,
                updated_at: new Date().toISOString()
            };
            for (const [sourceName, source] of applicationSources) {
                if (!source || typeof source !== 'object') continue;
                const entries = Array.isArray(source)
                    ? source.map((application, index) => [String(index), application])
                    : Object.entries(source);
                const updatedSource = Array.isArray(source) ? [...source] : { ...source };
                let sourceChanged = false;

                for (const [key, application] of entries) {
                    if (!application || typeof application !== 'object') continue;
                    const applicationStatus = String(application.status || application.application_status
                        || application.approval_status || '').trim().toLowerCase();
                    if (applicationStatus !== 'under_review') continue;

                    const updatedApplication = { ...application, status: 'completed', completed_at: completedAt };
                    updatedSource[key] = updatedApplication;
                    sourceChanged = true;

                    const studentEmail = application.student_email || application.studentEmail
                        || application.applicant_email || application.applicantEmail || application.email;
                    const applicationKey = String(key).match(/^[a-f\d]{64}$/i)
                        ? String(key)
                        : studentEmail
                            ? getStudentApplicationKey(studentEmail)
                            : '';
                    if (applicationKey) rootApplications.push([applicationKey, updatedApplication]);
                }

                if (sourceChanged) updatedTask[sourceName] = updatedSource;
            }

            const credentialPath = process.env.FIREBASE_SERVICE_ACCOUNT_PATH || process.env.GOOGLE_APPLICATION_CREDENTIALS;
            if (credentialPath) {
                const updates = {
                    [`tasks/${taskId}`]: updatedTask,
                    ...Object.fromEntries(rootApplications.map(([applicationKey, application]) => [
                        `student_applications/${applicationKey}/${taskId}`,
                        application
                    ]))
                };
                await getTaskDatabase().ref().update(updates);
            } else {
                const writeFirebaseJson = async (url, value) => {
                    const options = {
                        method: 'PUT',
                        headers: getFirebaseHeaders(req, { 'Content-Type': 'application/json' }),
                        body: JSON.stringify(value)
                    };
                    let response = await fetch(url, options);
                    if (response.status === 401) {
                        await refreshFirebaseSessionToken(req);
                        options.headers = getFirebaseHeaders(req, { 'Content-Type': 'application/json' });
                        response = await fetch(url, options);
                    }
                    if (!response.ok) {
                        throw new Error(`Firebase returned ${response.status} while completing the task.`);
                    }
                };

                await writeFirebaseJson(`${FIREBASE_DB_URL}/tasks/${encodeURIComponent(taskId)}.json`, updatedTask);
                for (const [applicationKey, application] of rootApplications) {
                    await writeFirebaseJson(
                        `${FIREBASE_DB_URL}/student_applications/${applicationKey}/${encodeURIComponent(taskId)}.json`,
                        application
                    );
                }
            }

            res.json({ success: true, task: { ...updatedTask, id: taskId } });
        } catch (error) {
            console.error('Teacher Firebase task completion error:', error.message);
            res.status(502).json({ success: false, message: `Could not complete the task: ${error.message}` });
        }
    });

    router.patch('/api/teacher-tasks/:taskId', requireAppSession('teacher'), async (req, res) => {
        try {
            let taskKey = req.params.taskId;
            let currentTask = await readTask(taskKey);

            if (!currentTask) {
                const tasks = await readAllTasks();

                const matches = Object.entries(tasks || {}).filter(([key, task]) =>
                    key === req.params.taskId ||
                    task?.id === req.params.taskId ||
                    getTaskKey(task?.title) === req.params.taskId
                );
                if (matches.length > 1) {
                    return res.status(409).json({ success: false, message: 'Task could not be uniquely identified.' });
                }
                if (matches.length === 0) return res.status(404).json({ success: false, message: 'Task not found.' });

                [taskKey, currentTask] = matches[0];
            }

            if (!isTaskOwnedByTeacher(currentTask, req.session.email)) {
                return res.status(403).json({ success: false, message: 'You can only edit your own tasks.' });
            }

            const title = String(req.body.title || '').trim();
            if (!title) return res.status(400).json({ success: false, message: 'Task title is required.' });
            const amount = req.body.amount === null || req.body.amount === '' ? null : Number(req.body.amount);
            if (amount !== null && (!Number.isFinite(amount) || amount < 0)) {
                return res.status(400).json({ success: false, message: 'Budget must be a non-negative number.' });
            }

            const updatedTask = {
                ...currentTask,
                title,
                description: String(req.body.description || '').trim(),
                room: String(req.body.room || '').trim(),
                task_mode: req.body.task_mode === 'online' ? 'online' : 'in_person',
                due_date_type: 'on_date',
                due_date_start: null,
                due_date: String(req.body.due_date || ''),
                due_time: String(req.body.due_time || ''),
                time_preference: 'specific_time',
                due_time_start: null,
                time_range_preset: null,
                is_urgent: req.body.is_urgent === true,
                amount,
                platform_fee: amount === null ? 0 : amount < 100 ? 5 : amount <= 500 ? 10 : amount <= 1000 ? 20 : 20 + Math.ceil((amount - 1000) / 500) * 10,
                total_amount: amount === null ? 0 : amount + (amount < 100 ? 5 : amount <= 500 ? 10 : amount <= 1000 ? 20 : 20 + Math.ceil((amount - 1000) / 500) * 10),
                payment_method: amount === null ? null : String(req.body.payment_method || ''),
                updated_at: new Date().toISOString()
            };

            const updateResponse = await fetch(`${FIREBASE_DB_URL}/tasks/${encodeURIComponent(taskKey)}.json`, {
                method: 'PUT',
                headers: getFirebaseHeaders(req, { 'Content-Type': 'application/json' }),
                body: JSON.stringify(updatedTask)
            });
            if (!updateResponse.ok) throw new Error(`Firebase returned ${updateResponse.status} while updating the task.`);
            res.json({ success: true, task: updatedTask });
        } catch (error) {
            console.error('Teacher Firebase task update error:', error.message);
            res.status(502).json({ success: false, message: `Could not update the task: ${error.message}` });
        }
    });

    router.delete('/api/teacher-tasks/:taskId', requireAppSession('teacher'), async (req, res) => {
        try {
            const task = await readTask(req.params.taskId);
            if (!task) {
                return res.status(404).json({ success: false, message: 'Task not found.' });
            }
            if (!isTaskOwnedByTeacher(task, req.session.email)) {
                return res.status(403).json({ success: false, message: 'You can only delete your own tasks.' });
            }
            const deleteResponse = await fetch(
                `${FIREBASE_DB_URL}/tasks/${encodeURIComponent(req.params.taskId)}.json`,
                { method: 'DELETE', headers: getFirebaseHeaders(req) }
            );
            if (!deleteResponse.ok) throw new Error(`Firebase returned ${deleteResponse.status} while deleting the task.`);
            res.json({ success: true });
        } catch (error) {
            console.error('Teacher Firebase task delete error:', error.message);
            res.status(502).json({ success: false, message: `Could not delete the task: ${error.message}` });
        }
    });

export default router;
