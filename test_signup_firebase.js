const FIREBASE_WEB_API_KEY = 'AIzaSyDRUJHWP0mnZNepGMgZU8ESu_Pq2KPR-2U';
const FIREBASE_AUTH_URL = 'https://identitytoolkit.googleapis.com/v1/accounts:signUp';
const FIREBASE_DB_URL = 'https://utosapp-fd977-default-rtdb.firebaseio.com';

async function testSignup() {
    console.log('\n🔥 ======================');
    console.log('🔥 TEST SIGNUP');
    console.log('🔥 ======================\n');

    const testEmail = `test_${Date.now()}@gmail.com`;
    const testPassword = 'Test123!@';

    try {
        console.log('📝 Creating new test user with:');
        console.log('   Email:', testEmail);
        console.log('   Password:', testPassword);

        // Step 1: Create Firebase Auth account
        console.log('\n🔐 Step 1: Creating Firebase Authentication account...');
        const firebaseAuthResponse = await fetch(`${FIREBASE_AUTH_URL}?key=${FIREBASE_WEB_API_KEY}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                email: testEmail,
                password: testPassword,
                returnSecureToken: true
            })
        });

        const authData = await firebaseAuthResponse.json();
        
        if (!firebaseAuthResponse.ok) {
            console.error('❌ Firebase Auth failed:', authData.error?.message);
            return;
        }

        const firebaseUser = authData;
        console.log('✅ Firebase Auth account created');
        console.log('   Firebase UID:', firebaseUser.localId);

        // Step 2: Save to Firebase Realtime Database
        console.log('\n💾 Step 2: Saving to Firebase Realtime Database...');
        const testData = {
            email: testEmail,
            first_name: 'Test',
            middle_name: 'User',
            last_name: 'Tester',
            gender: 'Male',
            department: 'Testing',
            firebase_uid: firebaseUser.localId,
            created_at: new Date().toISOString()
        };

        console.log('   Data to save:', testData);

        const dbResponse = await fetch(`${FIREBASE_DB_URL}/teachers/${firebaseUser.localId}.json`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(testData)
        });

        if (!dbResponse.ok) {
            const dbError = await dbResponse.json();
            console.error('❌ Database save failed:', dbError);
            return;
        }

        console.log('✅ Data saved to Firebase DB at key:', firebaseUser.localId);

        // Step 3: Verify the data was saved
        console.log('\n🔍 Step 3: Verifying saved data...');
        const verifyResponse = await fetch(`${FIREBASE_DB_URL}/teachers/${firebaseUser.localId}.json`);
        const savedData = await verifyResponse.json();
        
        console.log('✅ Saved data verified:');
        console.log('   Email:', savedData.email);
        console.log('   Firebase UID:', savedData.firebase_uid);
        console.log('   Name:', savedData.first_name, savedData.last_name);

        console.log('\n🔥 ======================');
        console.log('\n✅ Test signup completed successfully!');
        console.log('\n🔐 Now test login with:');
        console.log('   Email:', testEmail);
        console.log('   Password:', testPassword);
        console.log('\n');

    } catch (error) {
        console.error('❌ Error:', error.message);
    }
}

testSignup();
