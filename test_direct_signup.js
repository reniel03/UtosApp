// Direct test signup via API
async function testSignup() {
    const email = 'testuser@test.com';
    const password = 'Password123!';
    const firstName = 'Test';
    const lastName = 'User';

    console.log('🔥 DIRECT SIGNUP TEST');
    console.log('====================\n');
    console.log('Submitting signup request...');
    console.log('  Email:', email);
    console.log('  Password:', password);
    console.log('  Name:', firstName, lastName);
    console.log('');

    const response = await fetch('http://localhost:3000/signup-teacher', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            email,
            password,
            first_name: firstName,
            last_name: lastName,
            gender: 'Other',
            department: 'Test Department'
        })
    });

    console.log('Response Status:', response.status);
    const data = await response.json();
    console.log('Response Data:', JSON.stringify(data, null, 2));

    if (response.ok) {
        console.log('\n✅ SIGNUP SUCCESSFUL!');
        console.log('Now try logging in with:');
        console.log('  Email:', email);
        console.log('  Password:', password);
    } else {
        console.log('\n❌ SIGNUP FAILED!');
    }
}

testSignup();
