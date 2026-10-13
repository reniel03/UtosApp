import 'dotenv/config';
import express from 'express';
import path from 'path';
import { fileURLToPath } from 'url';
import session from 'express-session';
import frontpageRoutes from './routes/frontpage.js';
import authRoutes from './routes/auth.js';
import signupRoutes from './routes/signup.js';
import taskRoutes from './routes/tasks.js';
import messageRoutes from './routes/messages.js';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const app = express();

// Middleware
app.use(express.json({
   limit: '12mb',
   verify: (req, res, buffer) => {
       if (req.originalUrl.split('?')[0] === '/api/webhooks/paymongo') {
           req.rawBody = Buffer.from(buffer);
       }
   }
}));
app.use(express.urlencoded({ extended: true, limit: '12mb' }));

// Session middleware
app.use(session({
    secret: process.env.SESSION_SECRET || 'your-secret-key',
    resave: false,
    saveUninitialized: true,
    cookie: {
        secure: process.env.NODE_ENV === 'production',
        httpOnly: true,
        maxAge: 1000 * 60 * 60 * 24 // 24 hours
    }
}));

// Routes (BEFORE static middleware)
app.get(['/teacher-payment-result.html', '/teacher-payment-cancelled.html'], (req, res, next) => {
    if (!req.session?.email || req.session.user_role !== 'teacher') return res.redirect('/login');
    return next();
});
console.log('Loading frontpage routes...');
app.use('/', frontpageRoutes);
console.log('Loading auth routes...');
app.use('/login', authRoutes);
console.log('Loading signup routes...');
app.use('/', signupRoutes);
app.use('/', taskRoutes);
app.use('/', messageRoutes);
app.get('/student-messages', (req, res) => {
    res.sendFile(path.join(__dirname, 'public', 'student-messages.html'));
});
app.get('/student-history', (req, res) => {
    if (!req.session?.email || req.session.user_role !== 'student') {
        return res.redirect('/login');
    }

    res.sendFile(path.join(__dirname, 'public', 'student-history.html'));
});
app.get('/student-history-details', (req, res) => {
    if (!req.session?.email || req.session.user_role !== 'student') {
        return res.redirect('/login');
    }

    res.sendFile(path.join(__dirname, 'public', 'student-history-details.html'));
});
app.get('/student-teacher-profile', (req, res) => {
    if (!req.session?.email || req.session.user_role !== 'student') {
        return res.redirect('/login');
    }

    res.sendFile(path.join(__dirname, 'public', 'student-teacher-profile.html'));
});
console.log('All routes loaded');

app.use(express.static(path.join(__dirname, 'public')));

// Error handling middleware
app.use((err, req, res, next) => {
    console.error(err.stack);
    if (err.type === 'entity.too.large') {
        return res.status(413).json({ success: false, message: 'The attachment is too large. Please use an image under 2 MB.' });
    }
    res.status(500).json({ success: false, message: 'Something went wrong while processing the request.' });
});

// 404 handler
app.use((req, res) => {
    res.status(404).send('Page not found');
});

const PORT = process.env.PORT || 3000;

export default app;

if (!process.env.VERCEL && process.argv[1] && path.resolve(process.argv[1]) === __filename) {
    app.listen(PORT, () => {
        console.log(`Server is running on http://localhost:${PORT}`);
    });
}
