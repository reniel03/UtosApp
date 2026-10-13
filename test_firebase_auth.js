// Test Firebase Authentication directly
const FIREBASE_WEB_API_KEY = 'AIzaSyDRUJHWP0mnZNepGMgZU8ESu_Pq2KPR-2U';
const FIREBASE_AUTH_URL = 'https://identitytoolkit.googleapis.com/v1/accounts:signInWithPassword';

async function testLogin(email, password) {
    console.log(`\n🔐 Testing Firebase Auth with:`);
    console.log(`   Email: ${email}`);
    console.log(`   Password: ${password}`);
    console.log(`   Password length: ${password.length}\n`);

    try {
        const response = await fetch(`${FIREBASE_AUTH_URL}?key=${FIREBASE_WEB_API_KEY}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                email,
                password,
                returnSecureToken: true
            })
        });

        console.log('Response Status:', response.status);
        const data = await response.json();

        if (response.ok) {
            console.log('✅ SUCCESS! Authentication worked');
            console.log('   Firebase UID:', data.localId);
            console.log('   Token:', data.idToken.substring(0, 50) + '...');
        } else {
            console.log('❌ FAILED! Error from Firebase:');
            console.log('   Error message:', data.error?.message);
            console.log('   Error code:', data.error?.code);
        }
    } catch (error) {
        console.log('❌ Network error:', error.message);
    }
}

// Test with the user you're trying to login with
testLogin('delacasa@gmail.com', 'Teacher1@');
