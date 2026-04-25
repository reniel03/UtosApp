# 📋 Application Management System - Complete Guide

## Overview

Your UtosApp now has a **complete Application Management System** where:
- **Teachers** can view all student applications for their tasks
- **Teachers** can approve or reject applications
- **Students** can view the status of all their applications  
- **Automatic notifications** are sent when applications are approved/rejected

---

## 🏫 FOR TEACHERS

### 1. **Manage Applications (Main Interface)**

**URL:** `http://yoursite.com/manage_applications.php`

**Features:**
- ✅ View all pending student applications
- ✅ Approve or reject applications with one click
- ✅ Filter applications by status (Pending, Approved, Rejected, All)
- ✅ See student details (name, email, year level, course)
- ✅ See task details with deadlines
- ✅ Real-time statistics dashboard

**How to Use:**

1. Click on **"Manage Applications"** link from your teacher dashboard
2. Choose a filter at the top:
   - **⏳ Pending** - Applications waiting for your decision
   - **✅ Approved** - Already approved applications
   - **❌ Rejected** - Rejected applications
   - **All** - Show all applications

3. For each application card:
   - **View Details** - See full student and task information
   - **✓ Approve** - Accept the student's application (status → "In Progress")
   - **✕ Reject** - Reject the student's application

4. After approval/rejection, the student is **automatically notified**

### 2. **Existing Interface (Assigned Tasks)**

**URL:** `http://yoursite.com/assigned_tasks.php`

The original interface still works and shows:
- All your posted tasks
- Student progress for each task
- Individual approve buttons for each student application

---

## 👨‍🎓 FOR STUDENTS

### 1. **View My Applications**

**URL:** `http://yoursite.com/view_my_applications.php`

**Features:**
- 📝 See all your task applications in one place
- 📊 Quick statistics dashboard showing:
  - Total Applications
  - Pending Review
  - In Progress
  - Completed

**Application Status Flow:**

```
┌─────────────┐
│   APPLIED   │ (⏳ Pending)
└──────┬──────┘
       │ Teacher Reviews
       ↓
   ┌───────┬──────────┐
   │       │          │
   ↓       ↓          ↓
APPROVED REJECTED  (end)
(In Progress)
   ↓
COMPLETED
(✔️ Task Done)
```

**Status Meanings:**

| Status | Icon | What it means |
|--------|------|--------------|
| ⏳ Pending | ⏳ | Teacher hasn't reviewed yet |
| ✅ In Progress | ✅ | Teacher approved! Now work on the task |
| ✔️ Completed | ✔️ | Task finished, ready for rating |
| ❌ Rejected | ❌ | Application declined |

### 2. **How to Track Your Applications**

1. Go to **"My Applications"** from student home
2. Use filters to organize:
   - **All** - Show everything
   - **⏳ Pending** - Waiting for teacher review
   - **✅ In Progress** - Approved and working on it
   - **✔️ Completed** - Finished tasks
   - **❌ Rejected** - Rejected applications

3. **For each application, you can:**
   - See the task title and teacher's name
   - See when you applied
   - See application status
   - Message the teacher (if approved)
   - View your rating (if completed)

---

## 🔄 Status Update Flow

### When a Teacher Approves:

```
Student applies for task
                ↓
Teacher views in "Manage Applications"
                ↓
Teacher clicks "✓ Approve"
                ↓
Student notification: "Your application was approved!"
                ↓
Student sees "✅ In Progress" in their applications list
                ↓
Student can now message the teacher
                ↓
Student completes task
                ↓
Teacher rates the student
                ↓
Student sees "✔️ Completed" with rating
```

---

## 📊 Statistics & Dashboard

### Teacher Dashboard (Manage Applications):
- **Total Applications** - Overall count of all applications
- **Pending Review** - Applications waiting for decision
- **Approved** - Successfully approved applications
- **Rejected** - Rejected applications

### Student Dashboard (My Applications):
- **Total Applications** - All applications submitted
- **Pending Review** - Waiting for teacher feedback
- **In Progress** - Approved and working
- **Completed** - Finished and rated

---

## 🔔 Notifications

### Automatic Notifications Sent:

**To Students:**
- ✅ "Your application was approved!"
- ❌ "Your application was rejected!"
- ⭐ "You received a rating!"

**To Teachers:**
- 📨 New student applications received
- ✅ Application status changes

---

## 🛠️ Database Tables Used

The application system uses these existing tables:

```sql
-- Student_todos table contains:
- id (primary key)
- student_email (student's email)
- task_id (task applied for)
- status (pending/approved/rejected)
- is_completed (0 or 1)
- created_at (application date)
- rating (teacher's 1-5 rating)
- rated_at (rating date)

-- Tasks table contains:
- id (task ID)
- title (task name)
- description
- teacher_email (who posted it)
- due_date & due_time
- created_at

-- Students table contains:
- email
- first_name, middle_name, last_name
- photo
- year_level
- course

-- Teachers table contains:
- email
- first_name, middle_name, last_name
- photo
```

---

## 🎯 Quick Links

### For Teachers:
- Main Dashboard: `teacher_task_page.php`
- Manage Applications: `manage_applications.php` ← **USE THIS**
- Posted Tasks: `assigned_tasks.php`

### For Students:
- Home: `student_home.php`
- My Applications: `view_my_applications.php` ← **USE THIS**
- Message Teachers: `student_message.php`
- Task History: `student_history.php`

---

## 💡 Tips & Best Practices

### For Teachers:
1. **Check Applications Regularly** - Visit "Manage Applications" to see new submissions
2. **Be Timely** - Approve/reject quickly so students know the status
3. **Use Filters** - Sort by "Pending" to see only decisions needed
4. **Review Student Details** - Click "View Details" to learn about the student

### For Students:
1. **Check Status Often** - Visit "My Applications" to see updates
2. **Message Teachers** - Once approved, use the message feature to ask questions
3. **View Ratings** - Check completed tasks to see teacher feedback
4. **Reapply** - You can apply again if rejected

---

## 🐛 Troubleshooting

### Problem: "No applications found"
**Solution:** Make sure you've applied for tasks first. Go to student home and click "Apply" on tasks.

### Problem: "Status not updating"
**Solution:** Refresh the page or close and reopen the browser.

### Problem: "Can't see approve button"
**Solution:** Only appears for pending applications. Check if status has already been decided.

### Problem: "Student not notified"
**Solution:** Notifications show in their "My Applications" page. They see it next time they log in.

---

## 📱 Mobile Responsive

Both pages are fully responsive for:
- 📱 Mobile phones
- 📱 Tablets  
- 💻 Desktop browsers

---

## ✨ Features Summary

| Feature | Teacher | Student |
|---------|---------|---------|
| View all applications | ✅ | ✅ |
| Filter by status | ✅ | ✅ |
| Approve/Reject | ✅ | ❌ |
| View statistics | ✅ | ✅ |
| Message support | ❌ | ✅ |
| See ratings | ❌ | ✅ |
| Responsive design | ✅ | ✅ |

---

## 🔐 Security

- Only teachers can access `manage_applications.php`
- Only students can access `view_my_applications.php`
- All queries use prepared statements (SQL injection protection)
- Sessions verify user role and email
- No unauthorized status updates possible

---

## 📞 Support

For issues or questions:
1. Check the troubleshooting section above
2. Verify database connection in `db_connect.php`
3. Check browser console for errors (F12)
4. Verify user role: teacher or student

---

**Last Updated:** April 15, 2026  
**Version:** 1.0  
**Status:** ✅ Production Ready
