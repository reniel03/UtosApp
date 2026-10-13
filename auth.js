import express from 'express';

const router = express.Router();

// Firebase credentials
const FIREBASE_WEB_API_KEY = 'AIzaSyDRUJHWP0mnZNepGMgZU8ESu_Pq2KPR-2U';
const FIREBASE_AUTH_URL = 'https://identitytoolkit.googleapis.com/v1/accounts:signInWithPassword';
const FIREBASE_SIGNUP_URL = 'https://identitytoolkit.googleapis.com/v1/accounts:signUp';
const FIREBASE_DB_URL = 'https://utosapp-fd977-default-rtdb.firebaseio.com';
const ADMIN_LOGIN_NAMES = new Set(['admin', 'admin@utosapp.com']);
const ADMIN_PASSWORD = 'utosapp2026';

const saveSession = (req) => new Promise((resolve, reject) => {
    req.session.save((error) => error ? reject(error) : resolve());
});

/**
 * GET /login - Serve login page
 */
router.get('/', (req, res) => {
    res.sendFile(new URL('../public/login.html', import.meta.url).pathname);
});

/**
 * POST /login/auth - Handle login authentication using Firebase completely
 * Body: { email, password }
 * Response: { success: boolean, role: 'student'|'teacher', user: {...}, message: string }
 */
router.post('/auth', async (req, res) => {
    try {
        let { email, password } = req.body;
        
        // Normalize the email or username entered at login.
        email = (email || '').trim().toLowerCase();
        password = (password || '').trim();

        console.log('\n🔥 ======================');
        console.log('🔥 LOGIN ATTEMPT');
        console.log('🔥 ======================');
        console.log('📝 Received:', { 
            email: `"${email}"`,
            email_length: email.length,
            password_length: password.length
        });

        if (!email || !password) {
            console.log('❌ Missing fields');
            return res.status(400).json({
                success: false,
                message: 'Email or username and password are required'
            });
        }

        if (ADMIN_LOGIN_NAMES.has(email) && password === ADMIN_PASSWORD) {
            req.session.email = 'admin@utosapp.com';
            req.session.user_role = 'admin';
            req.session.first_name = 'Admin';
            req.session.last_name = '';
            await saveSession(req);

            return res.json({
                success: true,
                role: 'admin',
                user: {
                    email: 'admin@utosapp.com',
                    first_name: 'Admin',
                    last_name: ''
                },
                message: 'Login successful'
            });
        }

        // Step 1: Check if user exists in Firebase Realtime Database
        console.log('\n🔍 Step 1: Checking Firebase Realtime Database...');
        
        let userRole = null;
        let userData = null;
        let dbKey = null;

        // Check teachers
        console.log('   Fetching teachers...');
        const teachersResponse = await fetch(`${FIREBASE_DB_URL}/teachers.json`);
        console.log('   Teachers response status:', teachersResponse.status);
        
        if (teachersResponse.ok) {
            const teachers = await teachersResponse.json();
            console.log('   Total teachers:', Object.keys(teachers || {}).length);
            
            if (teachers) {
                for (const [key, teacher] of Object.entries(teachers)) {
                    const teacherEmail = (teacher.email || '').toLowerCase();
                    const teacherUsername = String(teacher.username || '').trim().toLowerCase();
                    const matchesLogin = teacherEmail === email || (!email.includes('@') && teacherUsername === email);
                    console.log(`   Comparing teacher login identifier with account: ${matchesLogin}`);
                    
                    if (matchesLogin) {
                        userRole = 'teacher';
                        userData = teacher;
                        dbKey = key;
                        console.log('✅ Teacher found!');
                        console.log('   DB Key:', key);
                        console.log('   Email:', teacher.email);
                        console.log('   Name:', teacher.first_name, teacher.last_name);
                        break;
                    }
                }
            }
        }

        // Check students if not found in teachers
        if (!userRole) {
            console.log('   Fetching students...');
            const studentsResponse = await fetch(`${FIREBASE_DB_URL}/students.json`);
            console.log('   Students response status:', studentsResponse.status);
            
            if (studentsResponse.ok) {
                const students = await studentsResponse.json();
                console.log('   Total students:', Object.keys(students || {}).length);
                
                if (students) {
                    for (const [key, student] of Object.entries(students)) {
                        const studentEmail = (student.email || '').toLowerCase();
                        const studentUsername = String(student.username || '').trim().toLowerCase();
                        const matchesLogin = studentEmail === email || (!email.includes('@') && studentUsername === email);
                        console.log(`   Comparing student login identifier with account: ${matchesLogin}`);

                        if (matchesLogin) {
                            userRole = 'student';
                            userData = student;
                            dbKey = key;
                            console.log('✅ Student found!');
                            console.log('   DB Key:', key);
                            console.log('   Email:', student.email);
                            break;
                        }
                    }
                }
            }
        }

        // If user not found in Firebase Realtime Database
        if (!userRole || !userData) {
            console.log('❌ User not found in Firebase Realtime Database');
            console.log('🔥 ======================\n');
            return res.status(401).json({
                success: false,
                message: 'User not found. Please check your email or username, or sign up.'
            });
        }

        email = String(userData.email || '').trim().toLowerCase();
        if (!email) {
            return res.status(401).json({ success: false, message: 'This account does not have a valid email address.' });
        }

        if (userData.is_suspended === true || userData.isSuspended === true || String(userData.status || '').toLowerCase() === 'suspended') {
            return res.status(403).json({
                success: false,
                message: 'This account is suspended. Contact an administrator.'
            });
        }

        // Step 2: Authenticate password with Firebase Authentication
        console.log('\n🔐 Step 2: Authenticating password with Firebase Auth...');
        console.log('   Email:', email);
        console.log('   Password length:', password.length);
        
        const firebaseResponse = await fetch(`${FIREBASE_AUTH_URL}?key=${FIREBASE_WEB_API_KEY}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                email,
                password,
                returnSecureToken: true
            })
        });

        console.log('   Firebase response status:', firebaseResponse.status);
        let firebaseData = await firebaseResponse.json();
        
        if (!firebaseResponse.ok) {
            console.error('❌ Firebase Auth failed');
            console.error('   Error:', firebaseData.error?.message);
            console.error('   Code:', firebaseData.error?.code);

            const isLegacyPasswordMatch = !userData.firebase_uid && typeof userData.password === 'string' && userData.password === password;
            const isFirebaseAuthError = ['INVALID_PASSWORD', 'INVALID_LOGIN_CREDENTIALS'].includes(firebaseData.error?.code)
                || firebaseData.error?.code === 400
                || firebaseResponse.status === 400;

            if (isLegacyPasswordMatch && isFirebaseAuthError) {
                const migrationResponse = await fetch(`${FIREBASE_SIGNUP_URL}?key=${FIREBASE_WEB_API_KEY}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email, password, returnSecureToken: true })
                });
                const migrationData = await migrationResponse.json();

                if (migrationResponse.ok && migrationData.localId) {
                    const updatedUserData = { ...userData, firebase_uid: migrationData.localId };
                    delete updatedUserData.password;
                    const updateResponse = await fetch(`${FIREBASE_DB_URL}/${userRole}s/${dbKey}.json`, {
                        method: 'PUT',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(updatedUserData)
                    });

                    if (updateResponse.ok) {
                        userData = updatedUserData;
                        firebaseData = migrationData;
                        console.log('✅ Legacy account migrated successfully');
                    }
                }
            }

            if (firebaseData.localId) {
                console.log('✅ Legacy account authenticated during migration');
            } else if (isLegacyPasswordMatch) {
                const createdAuth = await fetch(`${FIREBASE_SIGNUP_URL}?key=${FIREBASE_WEB_API_KEY}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email, password, returnSecureToken: true })
                });
                const createdAuthData = await createdAuth.json();

                if (createdAuth.ok && createdAuthData.localId) {
                    const updatedUserData = { ...userData, firebase_uid: createdAuthData.localId };
                    delete updatedUserData.password;
                    const updateResponse = await fetch(`${FIREBASE_DB_URL}/${userRole}s/${dbKey}.json`, {
                        method: 'PUT',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(updatedUserData)
                    });

                    if (updateResponse.ok) {
                        userData = updatedUserData;
                        firebaseData = createdAuthData;
                        console.log('✅ Legacy account created in Firebase Auth for future database access');
                    }
                }

                if (!firebaseData.localId) {
                    firebaseData = { localId: userData.firebase_uid || null, idToken: null };
                    console.log('✅ Recovered account authenticated with its Realtime Database password');
                }
            } else {
                console.log('🔥 ======================\n');

                let errorMsg = 'Invalid email or password';
                if (firebaseData.error?.code === 'INVALID_PASSWORD' || firebaseData.error?.code === 400) {
                    errorMsg = 'Invalid password';
                } else if (firebaseData.error?.code === 'EMAIL_NOT_FOUND') {
                    errorMsg = 'Email not found in Firebase Auth';
                }

                return res.status(401).json({
                    success: false,
                    message: errorMsg
                });
            }
        }

        const firebaseUser = firebaseData;
        console.log('✅ Password authenticated successfully');
        console.log('   Firebase UID from Auth:', firebaseUser.localId);
        console.log('   Firebase UID from DB:', userData.firebase_uid);

        const loginTimestamp = Date.now();
        const updatedUserData = {
            ...userData,
            last_login: loginTimestamp,
            lastLogin: loginTimestamp,
            last_seen: loginTimestamp,
            lastSeen: loginTimestamp,
            updated_at: loginTimestamp
        };

        const userRecordUpdate = await fetch(`${FIREBASE_DB_URL}/${userRole}s/${dbKey}.json`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(updatedUserData)
        });

        if (!userRecordUpdate.ok) {
            console.warn('⚠️ Failed to persist login timestamp to user record.');
        } else {
            console.log('✅ Login timestamp saved to user record:', loginTimestamp);
        }

        // Step 3: Store user data in session
        console.log('\n💾 Step 3: Storing session data...');
        req.session.email = email;
        req.session.user_role = userRole;
        req.session.firebase_uid = firebaseUser.localId;
        req.session.firebase_id_token = firebaseUser.idToken || null;
        req.session.firebase_refresh_token = firebaseUser.refreshToken || null;
        req.session.firebase_password = password;
        req.session.last_login = loginTimestamp;
        
        // Store all user data from Firebase DB
        if (userData.first_name) req.session.first_name = userData.first_name;
        if (userData.last_name) req.session.last_name = userData.last_name;
        if (userData.middle_name) req.session.middle_name = userData.middle_name;
        if (userData.gender) req.session.gender = userData.gender;
        req.session.username = userData.username || '';
        if (userData.department) req.session.department = userData.department;
        if (userData.course) req.session.course = userData.course;
        if (userData.photo) req.session.photo = userData.photo;
        req.session.student_id = userData.student_id || userData.studentId || (userRole === 'student' ? dbKey : '');
        req.session.address = userData.address || '';
        req.session.level = userData.level || userData.year_level || '';
        req.session.year_level = userData.level || userData.year_level || '';
        req.session.phone = userData.phone || userData.phone_number || '';

        await saveSession(req);

        console.log('✅ Session stored');
        console.log('✅ Login successful for:', email);
        console.log('🔥 ======================\n');
        
        return res.json({
            success: true,
            role: userRole,
            user: {
                email,
                username: userData.username || '',
                first_name: userData.first_name || '',
                last_name: userData.last_name || '',
                middle_name: userData.middle_name || '',
                department: userData.department || '',
                course: userData.course || '',
                photo: userData.photo || '',
                student_id: userData.student_id || userData.studentId || (userRole === 'student' ? dbKey : ''),
                address: userData.address || '',
                level: userData.level || userData.year_level || '',
                phone: userData.phone || userData.phone_number || ''
            },
            message: 'Login successful'
        });

    } catch (error) {
        console.error('❌ Login error:', error.message);
        console.error('   Stack:', error.stack);
        console.log('🔥 ======================\n');
        res.status(500).json({
            success: false,
            message: 'Server error during login: ' + error.message
        });
    }
});

export default router;
