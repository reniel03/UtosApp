import { initializeApp } from "firebase/app";
import { getDatabase, ref, set, onValue } from "firebase/database";
import { getAuth, createUserWithEmailAndPassword } from "firebase/auth";

// 🔑 Firebase config
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


// Initialize
const app = initializeApp(firebaseConfig);
const db = getDatabase(app);
const auth = getAuth(app);

// ✅ Add user (TEST)
set(ref(db, 'users/user2'), {
  name: "Student B",
  role: "student"
});

// ✅ Add tasks
set(ref(db, 'tasks/task2'), {
  title: "English Assignment",
  status: "pending"
});

set(ref(db, 'tasks/task3'), {
  title: "Science Project",
  status: "assigned",
  assignedTo: "user1",
  createdBy: "teacher1",
  deadline: "2026-06-01"
});

// 🔥 REAL-TIME TASK LISTENER
onValue(ref(db, 'tasks'), (snapshot) => {
  const tasks = snapshot.val();

  if (!tasks) return;

  console.log("All Tasks:", tasks);

  // 🎯 Filter tasks for user1
  Object.keys(tasks).forEach((key) => {
    if (tasks[key].assignedTo === "user1") {
      console.log("My Task:", tasks[key]);
    }
  });
});

// 🔔 Notifications
set(ref(db, 'notifications/notif1'), {
  message: "New task assigned!",
  user: "user1"
});

onValue(ref(db, 'notifications'), (snapshot) => {
  console.log("Notifications:", snapshot.val());
});

// 🔐 Create user (TEST)
createUserWithEmailAndPassword(auth, "student@email.com", "123456")
  .then((userCredential) => {
    console.log("User Created:", userCredential.user.email);
  })
  .catch((error) => {
    console.log("Auth Error:", error.message);
  });