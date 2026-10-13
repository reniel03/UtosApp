// Direct test login via API
async function testLogin() {
    const email = 'testuser@test.com';
    const password = 'Password123!';

    console.log('🔐 DIRECT LOGIN TEST');
    console.log('====================\n');
    console.log('Submitting login request...');
    console.log('  Email:', email);
    console.log('  Password:', password);
    console.log('');

    const response = await fetch('http://localhost:3000/login/auth', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            email,
            password
        })
    });

    console.log('Response Status:', response.status);
    const data = await response.json();
    console.log('Response Data:', JSON.stringify(data, null, 2));

    if (response.ok) {
        console.log('\n✅ LOGIN SUCCESSFUL!');
        console.log('User:', data.user);
        console.log('Role:', data.role);
    } else {
        console.log('\n❌ LOGIN FAILED!');
        console.log('Error:', data.message);
    }
}

testLogin();
