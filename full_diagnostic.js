// Complete Firebase diagnostic
const FIREBASE_DB_URL = 'https://utosapp-fd977-default-rtdb.firebaseio.com';
const FIREBASE_WEB_API_KEY = 'AIzaSyDRUJHWP0mnZNepGMgZU8ESu_Pq2KPR-2U';
const FIREBASE_AUTH_URL = 'https://identitytoolkit.googleapis.com/v1/accounts:signInWithPassword';

async function fullDiagnostic(email, password) {
    console.log('\n🔍 COMPLETE FIREBASE DIAGNOSTIC');
    console.log('================================\n');

    // Check 1: Is email in Realtime Database?
    console.log('📋 Step 1: Checking Firebase Realtime Database...');
    const dbResponse = await fetch(`${FIREBASE_DB_URL}/.json`);
    const allData = await dbResponse.json();
    
    let foundInDB = false;
    let userLocation = null;
    
    if (allData.teachers) {
        for (const [key, teacher] of Object.entries(allData.teachers)) {
            if (teacher.email === email) {
                foundInDB = true;
                userLocation = `teachers/${key}`;
                console.log(`   ✅ Found in Database: ${userLocation}`);
                console.log('   Data:', teacher);
                break;
            }
        }
    }
    
    if (!foundInDB && allData.students) {
        for (const [key, student] of Object.entries(allData.students)) {
            if (student.email === email) {
                foundInDB = true;
                userLocation = `students/${key}`;
                console.log(`   ✅ Found in Database: ${userLocation}`);
                console.log('   Data:', student);
                break;
            }
        }
    }
    
    if (!foundInDB) {
        console.log(`   ❌ NOT found in Database`);
        return;
    }

    // Check 2: Can we authenticate with Firebase Auth?
    console.log('\n🔐 Step 2: Testing Firebase Authentication...');
    console.log(`   Trying to authenticate: ${email} / ${password}`);
    
    const authResponse = await fetch(`${FIREBASE_AUTH_URL}?key=${FIREBASE_WEB_API_KEY}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            email,
            password,
            returnSecureToken: true
        })
    });

    console.log(`   Response Status: ${authResponse.status}`);
    const authData = await authResponse.json();

    if (authResponse.ok) {
        console.log(`   ✅ Authentication SUCCESS!`);
        console.log(`   Firebase UID: ${authData.localId}`);
        console.log(`   Token: ${authData.idToken.substring(0, 50)}...`);
    } else {
        console.log(`   ❌ Authentication FAILED!`);
        console.log(`   Error: ${authData.error?.message}`);
        console.log(`   Code: ${authData.error?.code}`);
    }

    console.log('\n🎯 SUMMARY:');
    console.log(`   In Firebase Realtime DB: ${foundInDB ? '✅ YES' : '❌ NO'}`);
    console.log(`   Can Authenticate: ${authResponse.ok ? '✅ YES' : '❌ NO'}`);
    console.log('\n');
}

// Test with email from form
fullDiagnostic('del@gmail.com', 'Test123!');
