// Test login via the Express backend (same as frontend would do)
async function testLoginViaBackend() {
    console.log('\n🔥 ======================');
    console.log('🔥 TEST LOGIN VIA BACKEND');
    console.log('🔥 ======================\n');

    const email = 'niel@gmail.com';
    const password = 'Teacher1@';

    try {
        console.log('📝 Sending login request to backend:');
        console.log('   Email:', email);
        console.log('   Password:', password);
        console.log('   To: POST http://localhost:3000/login/auth\n');

        const response = await fetch('http://localhost:3000/login/auth', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ email, password })
        });

        console.log('📝 Response status:', response.status);
        console.log('📝 Response headers:', Object.fromEntries(response.headers));

        const data = await response.json();
        
        console.log('\n📝 Response body:');
        console.log(JSON.stringify(data, null, 2));

        if (response.ok) {
            console.log('\n✅ LOGIN SUCCESSFUL!');
        } else {
            console.log('\n❌ LOGIN FAILED');
        }

        console.log('\n🔥 ======================\n');

    } catch (error) {
        console.error('❌ Request error:', error.message);
    }
}

testLoginViaBackend();
