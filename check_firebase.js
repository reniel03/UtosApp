// Quick Firebase Realtime Database checker
const FIREBASE_DB_URL = 'https://utosapp-fd977-default-rtdb.firebaseio.com';

async function checkFirebaseData() {
    console.log('🔍 Checking Firebase Realtime Database...\n');

    // Check teachers
    const teachersResponse = await fetch(`${FIREBASE_DB_URL}/teachers.json`);
    const teachers = await teachersResponse.json();
    
    console.log('📚 TEACHERS IN FIREBASE:');
    if (teachers) {
        Object.entries(teachers).forEach(([key, teacher]) => {
            console.log(`   - ${teacher.email} (${teacher.first_name} ${teacher.last_name})`);
        });
    } else {
        console.log('   (No teachers found)');
    }

    console.log('\n👨‍🎓 STUDENTS IN FIREBASE:');
    const studentsResponse = await fetch(`${FIREBASE_DB_URL}/students.json`);
    const students = await studentsResponse.json();
    
    if (students) {
        Object.entries(students).forEach(([key, student]) => {
            console.log(`   - ${student.email} (${student.first_name} ${student.last_name})`);
        });
    } else {
        console.log('   (No students found)');
    }

    console.log('\n💡 If your email is NOT in this list, you need to:');
    console.log('   1. Sign up with the NEW signup form at http://localhost:3000/signup');
    console.log('   2. This will save your data to Firebase Realtime Database');
    console.log('   3. Then you can login\n');
}

checkFirebaseData();
