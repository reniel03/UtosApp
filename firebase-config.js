// Firebase Configuration
// For Firebase JS SDK v10+
const firebaseConfig = {
    apiKey: "AIzaSyDRUJHWP0mnZNepGMgZU8ESu_Pq2KPR-2U",
    authDomain: "utosapp-fd977.firebaseapp.com",
    databaseURL: "https://utosapp-fd977-default-rtdb.firebaseio.com",
    projectId: "utosapp-fd977",
    storageBucket: "utosapp-fd977.firebasestorage.app",
    messagingSenderId: "391607668763",
    appId: "1:391607668763:web:af43f174b7123ee1c4e8be",
    measurementId: "G-5BQHXS9KC1"
};

// Initialize Firebase with retry logic
let database = null;
let firebaseReady = false;
let firebaseApp = null;

const initializeFirebase = async () => {
    console.log('🔥 Firebase Config: Initializing Firebase...');
    
    // Check for modular SDK (firebase.initializeApp exists)
    if (typeof firebase !== 'undefined' && firebase.initializeApp) {
        try {
            firebaseApp = firebase.initializeApp(firebaseConfig);
            database = firebase.database();
            firebaseReady = true;
            console.log('✅ Firebase initialized successfully (modular SDK)');
            console.log('✅ Database reference obtained');
            return;
        } catch (error) {
            if (error.code !== 'app/duplicate-app') {
                console.error('❌ Firebase initialization error:', error);
            } else {
                console.log('✅ Firebase already initialized');
                database = firebase.database();
                firebaseReady = true;
            }
        }
    }
    
    // Check for compat SDK (firebase.initializeApp global)
    if (typeof window !== 'undefined' && window.firebase && window.firebase.initializeApp) {
        try {
            window.firebaseApp = window.firebase.initializeApp(firebaseConfig);
            database = window.firebase.database();
            firebaseReady = true;
            console.log('✅ Firebase initialized successfully (compat SDK)');
            console.log('✅ Database reference obtained');
            return;
        } catch (error) {
            if (error.code !== 'app/duplicate-app') {
                console.error('❌ Firebase initialization error:', error);
            } else {
                console.log('✅ Firebase already initialized');
                database = window.firebase.database();
                firebaseReady = true;
            }
        }
    }
    
    console.warn('⚠️ Firebase SDK not loaded yet, retrying in 500ms...');
    setTimeout(initializeFirebase, 500);
};

// Start initialization
initializeFirebase();

// Export for use in other modules
window.firebaseModule = {
    getDatabase: () => database,
    
    /**
     * Wait for Firebase to be ready
     */
    waitForFirebase: async () => {
        let attempts = 0;
        while (!firebaseReady && attempts < 50) {
            console.log('⏳ Waiting for Firebase to be ready... (attempt', attempts + 1, ')');
            await new Promise(resolve => setTimeout(resolve, 100));
            attempts++;
        }
        if (!firebaseReady) {
            throw new Error('Firebase failed to initialize after 5 seconds');
        }
        console.log('✅ Firebase is ready');
        return database;
    },
    
    /**
     * Save teacher data to Firebase
     */
    saveTeacherData: async (formData) => {
        try {
            console.log('📝 Saving teacher data:', formData);
            
            // Wait for Firebase to be ready
            await window.firebaseModule.waitForFirebase();
            
            const teachersRef = database.ref('teachers');
            console.log('📂 Teachers reference:', teachersRef);
            
            // Generate unique ID or use email as key
            const teacherId = formData.email.replace(/[.@]/g, '_') + '_' + Date.now();
            console.log('🔑 Generated teacherId:', teacherId);
            
            // Data to save (password will be hashed on the frontend before sending)
            const dataToSave = {
                first_name: formData.first_name,
                middle_name: formData.middle_name || '',
                last_name: formData.last_name,
                gender: formData.gender,
                email: formData.email,
                password: formData.password, // NOTE: Hash this in production!
                created_at: new Date().toISOString(),
                updated_at: new Date().toISOString()
            };
            
            console.log('💾 Data to save:', dataToSave);
            
            // Push data to Firebase
            await teachersRef.child(teacherId).set(dataToSave);
            console.log('✅ Data saved successfully to Firebase');
            
            return {
                success: true,
                teacherId: teacherId,
                message: 'Teacher data saved successfully'
            };
        } catch (error) {
            console.error('❌ Firebase save error:', error);
            console.error('Error code:', error.code);
            console.error('Error message:', error.message);
            return {
                success: false,
                message: error.message
            };
        }
    },
    
    /**
     * Check if email already exists in Firebase
     */
    checkEmailExists: async (email) => {
        try {
            // Wait for Firebase to be ready
            await window.firebaseModule.waitForFirebase();
            
            const teachersRef = database.ref('teachers');
            // Get all teachers and check manually (simpler, no index needed)
            const snapshot = await teachersRef.once('value');
            if (!snapshot.exists()) return false;
            
            const teachers = snapshot.val();
            for (let key in teachers) {
                if (teachers[key].email === email) {
                    return true;
                }
            }
            return false;
        } catch (error) {
            console.error('Firebase check email error:', error);
            // Don't throw, just skip the check and let user proceed
            return false;
        }
    },
    
    /**
     * Get teacher data by email
     */
    getTeacherByEmail: async (email) => {
        try {
            // Wait for Firebase to be ready
            await window.firebaseModule.waitForFirebase();
            
            const teachersRef = database.ref('teachers');
            const snapshot = await teachersRef.once('value');
            if (!snapshot.exists()) return null;
            
            const teachers = snapshot.val();
            for (let key in teachers) {
                if (teachers[key].email === email) {
                    return teachers[key];
                }
            }
            return null;
        } catch (error) {
            console.error('Firebase get teacher error:', error);
            throw error;
        }
    },
    
    /**
     * Save student data to Firebase
     */
    saveStudentData: async (formData) => {
        try {
            console.log('📝 Saving student data:', formData);
            
            // Wait for Firebase to be ready
            await window.firebaseModule.waitForFirebase();
            
            const studentsRef = database.ref('students');
            console.log('📂 Students reference:', studentsRef);
            
            // Generate unique ID or use email as key
            const studentId = formData.email.replace(/[.@]/g, '_') + '_' + Date.now();
            console.log('🔑 Generated studentId:', studentId);
            
            // Data to save
            const dataToSave = {
                first_name: formData.first_name,
                middle_name: formData.middle_name || '',
                last_name: formData.last_name,
                gender: formData.gender,
                email: formData.email,
                course: formData.course || '',
                password: formData.password, // NOTE: Hash this in production!
                created_at: new Date().toISOString(),
                updated_at: new Date().toISOString()
            };
            
            console.log('💾 Data to save:', dataToSave);
            
            // Push data to Firebase
            await studentsRef.child(studentId).set(dataToSave);
            console.log('✅ Data saved successfully to Firebase');
            
            return {
                success: true,
                studentId: studentId,
                message: 'Student data saved successfully'
            };
        } catch (error) {
            console.error('❌ Firebase save error:', error);
            console.error('Error code:', error.code);
            console.error('Error message:', error.message);
            return {
                success: false,
                message: error.message
            };
        }
    },
    
    /**
     * Check if email already exists in Firebase (Teachers or Students)
     */
    checkEmailExistsAnyCollection: async (email) => {
        try {
            // Wait for Firebase to be ready
            await window.firebaseModule.waitForFirebase();
            
            // Check teachers
            const teachersRef = database.ref('teachers');
            const teacherSnapshot = await teachersRef.once('value');
            if (teacherSnapshot.exists()) {
                const teachers = teacherSnapshot.val();
                for (let key in teachers) {
                    if (teachers[key].email === email) {
                        return true;
                    }
                }
            }
            
            // Check students
            const studentsRef = database.ref('students');
            const studentSnapshot = await studentsRef.once('value');
            if (studentSnapshot.exists()) {
                const students = studentSnapshot.val();
                for (let key in students) {
                    if (students[key].email === email) {
                        return true;
                    }
                }
            }
            
            return false;
        } catch (error) {
            console.error('Firebase check email error:', error);
            // Don't throw, just skip the check and let user proceed
            return false;
        }
    }
};

