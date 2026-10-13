const FIREBASE_WEB_API_KEY = 'AIzaSyDRUJHWP0mnZNepGMgZU8ESu_Pq2KPR-2U';
const FIREBASE_AUTH_URL = 'https://identitytoolkit.googleapis.com/v1/accounts:signInWithPassword';
const FIREBASE_DB_URL = 'https://utosapp-fd977-default-rtdb.firebaseio.com';

async function testLogin(email, password) {
    console.log('\n🔥 ======================');
    console.log('🔥 TEST LOGIN');
    console.log('🔥 ======================\n');

    try {
        console.log('📝 Testing login with:');
        console.log('   Email:', email);
        console.log('   Password:', password);

        // Normalize email like the backend does
        const normalizedEmail = email.trim().toLowerCase();
        const trimmedPassword = password.trim();

        // Step 1: Check if user exists in Firebase Realtime Database
        console.log('\n🔍 Step 1: Checking Firebase Realtime Database...');
        
        let userRole = null;
        let userData = null;

        // Check teachers
        const teachersResponse = await fetch(`${FIREBASE_DB_URL}/teachers.json`);
        if (teachersResponse.ok) {
            const teachers = await teachersResponse.json();
            if (teachers) {
                for (const [key, teacher] of Object.entries(teachers)) {
                    if ((teacher.email || '').toLowerCase() === normalizedEmail) {
                        userRole = 'teacher';
                        userData = teacher;
                        console.log('✅ Teacher found in Firebase:', normalizedEmail);
                        console.log('   Key:', key);
                        console.log('   Stored email:', teacher.email);
                        break;
                    }
                }
            }
        }

        // Check students if not found in teachers
        if (!userRole) {
            const studentsResponse = await fetch(`${FIREBASE_DB_URL}/students.json`);
            if (studentsResponse.ok) {
                const students = await studentsResponse.json();
                if (students) {
                    for (const [key, student] of Object.entries(students)) {
                        if ((student.email || '').toLowerCase() === normalizedEmail) {
                            userRole = 'student';
                            userData = student;
                            console.log('✅ Student found in Firebase:', normalizedEmail);
                            break;
                        }
                    }
                }
            }
        }

        if (!userRole || !userData) {
            console.log('❌ User not found in Firebase Realtime Database');
            return;
        }

        // Step 2: Authenticate password with Firebase Authentication
        console.log('\n🔐 Step 2: Authenticating password with Firebase...');
        
        const firebaseResponse = await fetch(`${FIREBASE_AUTH_URL}?key=${FIREBASE_WEB_API_KEY}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                email: normalizedEmail,
                password: trimmedPassword,
                returnSecureToken: true
            })
        });

        if (!firebaseResponse.ok) {
            const error = await firebaseResponse.json();
            console.error('❌ Firebase Auth failed:');
            console.error('   Error:', error.error?.message);
            console.error('   Code:', error.error?.code);
            return;
        }

        const firebaseUser = await firebaseResponse.json();
        console.log('✅ Password authenticated successfully');
        console.log('   Firebase UID from Auth:', firebaseUser.localId);
        console.log('   Firebase UID from DB:', userData.firebase_uid);

        console.log('\n🔥 ======================');
        console.log('✅ LOGIN TEST PASSED!');
        console.log('\nLogin would succeed for:');
        console.log('   Email:', normalizedEmail);
        console.log('   Role:', userRole);
        console.log('   User:', userData.first_name, userData.last_name);
        console.log('\n');

    } catch (error) {
        console.error('❌ Error:', error.message);
    }
}

// Test with the new account created above
testLogin('test_1780382857252@gmail.com', 'Test123!@');
