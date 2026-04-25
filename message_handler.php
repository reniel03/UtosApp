<?php
session_start();
include 'db_connect.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_role']) || !isset($_SESSION['email'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$user_email = $_SESSION['email'];
$user_role = $_SESSION['user_role'];

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'get_contacts':
        // Get approved students/teachers that can be messaged
        getContacts($conn, $user_email, $user_role);
        break;
    
    case 'get_messages':
        // Get conversation with a specific contact
        $contact_email = $_GET['contact_email'] ?? '';
        getMessages($conn, $user_email, $contact_email);
        break;
    
    case 'send_message':
        // Send a new message
        $receiver_email = $_POST['receiver_email'] ?? '';
        $receiver_role = $_POST['receiver_role'] ?? '';
        $message = $_POST['message'] ?? '';
        sendMessage($conn, $user_email, $user_role, $receiver_email, $receiver_role, $message);
        break;
    
    case 'mark_read':
        // Mark messages as read
        $contact_email = $_POST['contact_email'] ?? '';
        markMessagesRead($conn, $user_email, $contact_email);
        break;
    
    case 'get_unread_count':
        // Get total unread message count
        getUnreadCount($conn, $user_email);
        break;
    
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function getContacts($conn, $user_email, $user_role) {
    $contacts = [];
    
    if ($user_role === 'student') {
        // Student can only message teachers who approved their task applications
        $query = "SELECT DISTINCT t.teacher_email, 
                         tr.first_name, tr.middle_name, tr.last_name, tr.photo,
                         (SELECT COUNT(*) FROM messages m 
                          WHERE m.sender_email = t.teacher_email 
                          AND m.receiver_email = ? 
                          AND m.is_read = 0) as unread_count,
                         (SELECT message FROM messages m2 
                          WHERE ((m2.sender_email = ? AND m2.receiver_email = t.teacher_email) 
                              OR (m2.sender_email = t.teacher_email AND m2.receiver_email = ?))
                          ORDER BY m2.created_at DESC LIMIT 1) as last_message,
                         (SELECT created_at FROM messages m3 
                          WHERE ((m3.sender_email = ? AND m3.receiver_email = t.teacher_email) 
                              OR (m3.sender_email = t.teacher_email AND m3.receiver_email = ?))
                          ORDER BY m3.created_at DESC LIMIT 1) as last_message_time
                  FROM student_todos st
                  INNER JOIN tasks t ON st.task_id = t.id
                  LEFT JOIN teachers tr ON t.teacher_email = tr.email
                  WHERE st.student_email = ? 
                  AND st.status IN ('ongoing', 'completed')
                  ORDER BY last_message_time DESC";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param('ssssss', $user_email, $user_email, $user_email, $user_email, $user_email, $user_email);
    } else {
        // Teacher can only message students whose applications they approved
        $query = "SELECT DISTINCT st.student_email,
                         s.first_name, s.middle_name, s.last_name, s.photo,
                         (SELECT COUNT(*) FROM messages m 
                          WHERE m.sender_email = st.student_email 
                          AND m.receiver_email = ? 
                          AND m.is_read = 0) as unread_count,
                         (SELECT message FROM messages m2 
                          WHERE ((m2.sender_email = ? AND m2.receiver_email = st.student_email) 
                              OR (m2.sender_email = st.student_email AND m2.receiver_email = ?))
                          ORDER BY m2.created_at DESC LIMIT 1) as last_message,
                         (SELECT created_at FROM messages m3 
                          WHERE ((m3.sender_email = ? AND m3.receiver_email = st.student_email) 
                              OR (m3.sender_email = st.student_email AND m3.receiver_email = ?))
                          ORDER BY m3.created_at DESC LIMIT 1) as last_message_time
                  FROM student_todos st
                  INNER JOIN tasks t ON st.task_id = t.id
                  LEFT JOIN students s ON st.student_email = s.email
                  WHERE t.teacher_email = ? 
                  AND st.status IN ('ongoing', 'completed')
                  ORDER BY last_message_time DESC";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param('ssssss', $user_email, $user_email, $user_email, $user_email, $user_email, $user_email);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $email = $user_role === 'student' ? $row['teacher_email'] : $row['student_email'];
        $full_name = trim($row['first_name'] . ' ' . ($row['middle_name'] ? $row['middle_name'] . ' ' : '') . $row['last_name']);
        
        $contacts[] = [
            'email' => $email,
            'name' => $full_name ?: 'Unknown',
            'photo' => $row['photo'] ?: 'profile-default.png',
            'unread_count' => (int)$row['unread_count'],
            'last_message' => $row['last_message'] ?: '',
            'last_message_time' => $row['last_message_time'] ?: '',
            'role' => $user_role === 'student' ? 'teacher' : 'student'
        ];
    }
    
    $stmt->close();
    echo json_encode(['success' => true, 'contacts' => $contacts]);
}

function getMessages($conn, $user_email, $contact_email) {
    if (empty($contact_email)) {
        echo json_encode(['success' => false, 'message' => 'Contact email required']);
        return;
    }
    
    $query = "SELECT * FROM messages 
              WHERE (sender_email = ? AND receiver_email = ?) 
                 OR (sender_email = ? AND receiver_email = ?)
              ORDER BY created_at ASC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ssss', $user_email, $contact_email, $contact_email, $user_email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $messages = [];
    while ($row = $result->fetch_assoc()) {
        $messages[] = [
            'id' => $row['id'],
            'sender_email' => $row['sender_email'],
            'receiver_email' => $row['receiver_email'],
            'message' => $row['message'],
            'is_read' => (int)$row['is_read'],
            'created_at' => $row['created_at'],
            'is_mine' => $row['sender_email'] === $user_email
        ];
    }
    
    $stmt->close();
    echo json_encode(['success' => true, 'messages' => $messages]);
}

function sendMessage($conn, $sender_email, $sender_role, $receiver_email, $receiver_role, $message) {
    if (empty($receiver_email) || empty($message)) {
        echo json_encode(['success' => false, 'message' => 'Receiver and message required']);
        return;
    }
    
    // Verify that this is an approved connection
    $is_valid = false;
    
    if ($sender_role === 'student') {
        $check_query = "SELECT 1 FROM student_todos st
                        INNER JOIN tasks t ON st.task_id = t.id
                        WHERE st.student_email = ? 
                        AND t.teacher_email = ?
                        AND st.status IN ('ongoing', 'completed')
                        LIMIT 1";
    } else {
        $check_query = "SELECT 1 FROM student_todos st
                        INNER JOIN tasks t ON st.task_id = t.id
                        WHERE st.student_email = ? 
                        AND t.teacher_email = ?
                        AND st.status IN ('ongoing', 'completed')
                        LIMIT 1";
        // Swap the order for teacher
        $temp = $sender_email;
        $sender_check = $receiver_email;
        $receiver_check = $sender_email;
    }
    
    if ($sender_role === 'student') {
        $stmt = $conn->prepare($check_query);
        $stmt->bind_param('ss', $sender_email, $receiver_email);
    } else {
        $stmt = $conn->prepare($check_query);
        $stmt->bind_param('ss', $receiver_email, $sender_email);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $is_valid = $result->num_rows > 0;
    $stmt->close();
    
    if (!$is_valid) {
        echo json_encode(['success' => false, 'message' => 'Not authorized to message this person']);
        return;
    }
    
    // Insert the message
    $insert_query = "INSERT INTO messages (sender_email, sender_role, receiver_email, receiver_role, message) 
                     VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($insert_query);
    $stmt->bind_param('sssss', $sender_email, $sender_role, $receiver_email, $receiver_role, $message);
    
    if ($stmt->execute()) {
        $message_id = $conn->insert_id;
        echo json_encode([
            'success' => true, 
            'message_id' => $message_id,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to send message']);
    }
    
    $stmt->close();
}

function markMessagesRead($conn, $user_email, $contact_email) {
    if (empty($contact_email)) {
        echo json_encode(['success' => false, 'message' => 'Contact email required']);
        return;
    }
    
    $query = "UPDATE messages SET is_read = 1 
              WHERE sender_email = ? AND receiver_email = ? AND is_read = 0";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ss', $contact_email, $user_email);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    
    echo json_encode(['success' => true, 'marked_read' => $affected]);
}

function getUnreadCount($conn, $user_email) {
    $query = "SELECT COUNT(*) as count FROM messages WHERE receiver_email = ? AND is_read = 0";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('s', $user_email);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    echo json_encode(['success' => true, 'unread_count' => (int)$row['count']]);
}

$conn->close();
?>
