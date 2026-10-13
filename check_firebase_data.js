const FIREBASE_DB_URL = 'https://utosapp-fd977-default-rtdb.firebaseio.com';
const FIREBASE_WEB_API_KEY = 'AIzaSyDRUJHWP0mnZNepGMgZU8ESu_Pq2KPR-2U';
const FIREBASE_AUTH_URL = 'https://identitytoolkit.googleapis.com/v1/accounts:signInWithPassword';

async function checkFirebaseData() {
    console.log('\n🔥 ======================');
    console.log('🔥 FIREBASE DATA CHECK');
    console.log('🔥 ======================\n');

    try {
        // Check Teachers
        console.log('📚 Checking Teachers...');
        const teachersResponse = await fetch(`${FIREBASE_DB_URL}/teachers.json`);
        const teachers = await teachersResponse.json();
        console.log('Teachers in database:', Object.keys(teachers || {}).length);
        if (teachers) {
            for (const [id, teacher] of Object.entries(teachers)) {
                console.log('\n  Teacher:', id);
                console.log('    Email:', teacher.email);
                console.log('    Name:', teacher.first_name, teacher.last_name);
                console.log('    Firebase UID:', teacher.firebase_uid);
            }
        }

        // Check Students
        console.log('\n\n👨‍🎓 Checking Students...');
        const studentsResponse = await fetch(`${FIREBASE_DB_URL}/students.json`);
        const students = await studentsResponse.json();
        console.log('Students in database:', Object.keys(students || {}).length);
        if (students) {
            for (const [id, student] of Object.entries(students)) {
                console.log('\n  Student:', id);
                console.log('    Email:', student.email);
                console.log('    Name:', student.first_name, student.last_name);
                console.log('    Firebase UID:', student.firebase_uid);
            }
        }

        // Test login with first teacher
        if (teachers && Object.keys(teachers).length > 0) {
            const firstTeacherId = Object.keys(teachers)[0];
            const firstTeacher = teachers[firstTeacherId];
            console.log('\n\n🔐 Testing Firebase Auth with first teacher:');
            console.log('   Email:', firstTeacher.email);
            console.log('   Firebase UID from DB:', firstTeacher.firebase_uid);

            // Try to authenticate (note: this will fail with wrong password, but we're testing connection)
            console.log('\n   (Note: We can\'t test password without knowing it, but this verifies the email exists)');
        }

    } catch (error) {
        console.error('❌ Error:', error.message);
    }

    console.log('\n🔥 ======================\n');
}

checkFirebaseData();
