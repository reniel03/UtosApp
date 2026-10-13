const FIREBASE_DB_URL = 'https://utosapp-fd977-default-rtdb.firebaseio.com';

async function migrateUsers() {
    console.log('\n🔥 ======================');
    console.log('🔥 MIGRATE USERS - ADD FIREBASE_UID');
    console.log('🔥 ======================\n');

    try {
        // Migrate Teachers
        console.log('📚 Migrating Teachers...');
        const teachersResponse = await fetch(`${FIREBASE_DB_URL}/teachers.json`);
        if (teachersResponse.ok) {
            const teachers = await teachersResponse.json();
            if (teachers) {
                let updated = 0;
                for (const [key, teacher] of Object.entries(teachers)) {
                    // Use the key as the firebase_uid if not set
                    if (!teacher.firebase_uid) {
                        const updatedTeacher = {
                            ...teacher,
                            firebase_uid: key
                        };
                        
                        const updateResponse = await fetch(`${FIREBASE_DB_URL}/teachers/${key}.json`, {
                            method: 'PUT',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(updatedTeacher)
                        });

                        if (updateResponse.ok) {
                            console.log(`  ✅ Updated teacher: ${teacher.email}`);
                            updated++;
                        } else {
                            console.log(`  ❌ Failed to update teacher: ${teacher.email}`);
                        }
                    }
                }
                console.log(`\n📚 Teachers: ${updated} updated, ${Object.keys(teachers).length - updated} already had firebase_uid`);
            }
        }

        // Migrate Students
        console.log('\n👨‍🎓 Migrating Students...');
        const studentsResponse = await fetch(`${FIREBASE_DB_URL}/students.json`);
        if (studentsResponse.ok) {
            const students = await studentsResponse.json();
            if (students) {
                let updated = 0;
                for (const [key, student] of Object.entries(students)) {
                    // Use the key as the firebase_uid if not set
                    if (!student.firebase_uid) {
                        const updatedStudent = {
                            ...student,
                            firebase_uid: key
                        };
                        
                        const updateResponse = await fetch(`${FIREBASE_DB_URL}/students/${key}.json`, {
                            method: 'PUT',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(updatedStudent)
                        });

                        if (updateResponse.ok) {
                            console.log(`  ✅ Updated student: ${student.email}`);
                            updated++;
                        } else {
                            console.log(`  ❌ Failed to update student: ${student.email}`);
                        }
                    }
                }
                console.log(`\n👨‍🎓 Students: ${updated} updated, ${Object.keys(students).length - updated} already had firebase_uid`);
            }
        }

        console.log('\n🔥 ======================');
        console.log('✅ Migration completed!\n');

    } catch (error) {
        console.error('❌ Error:', error.message);
    }
}

migrateUsers();
