<?php
// Add headers for better CORS and JSON handling
header('Content-Type: text/plain; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $message = strtolower(trim($_POST["message"]));
    
    // Enhanced keyword-based responses with more comprehensive answers
    $responses = [
        // Academic Features
        "attendance" => "📊 You can view your monthly attendance as a beautiful chart under the 'Attendance' tab. Track your presence and identify patterns in your attendance record.",
        
        "assignment" => "📝 Navigate to the 'Assignments' section to view all your coursework. You can submit assignments, check due dates, and track completion status.",
        
        "grade" => "🏅 Click on the 'Grades' tab to view subject-wise marks and detailed analytics. See your progress over time with interactive charts.",
        
        "announcement" => "📢 Live announcements appear instantly at the top of your dashboard. Stay updated with important news, events, and deadlines.",
        
        // Profile & Settings
        "profile" => "👤 Go to your profile and click 'Edit' to update your information, upload a picture, or change your bio.",
        
        "profile picture" => "📸 To change your profile picture: Go to Profile → Click Edit → Click the camera icon → Upload your new photo.",
        
        "edit profile" => "✏️ Click the 'Edit' button next to your name on the dashboard, or navigate to Profile → Edit to update your information.",
        
        // Technical Features
        "email" => "📧 Yes! Email notifications are automatically sent when assignments are posted, grades are updated, or important announcements are made.",
        
        "chat" => "💬 Use the real-time chat feature to communicate with classmates and teachers. Click the chat icon or go to the Chat section.",
        
        "chatbot" => "🤖 I'm your AI study assistant! I can help you with questions about the platform, your studies, and navigating Alpha-Class features.",
        
        // Visual & Analytics
        "chart" => "📈 Yes! Grades and attendance are displayed using interactive charts and graphs for easy understanding and trend analysis.",
        
        "dashboard" => "🏠 Your dashboard is your home base! It shows your profile, recent assignments, attendance overview, and quick access to all features.",
        
        // Security & Login
        "login" => "🔐 Admins, Teachers, and Students can log in with role-specific access. Each role has different permissions and features.",
        
        "captcha" => "🛡️ We use CAPTCHA during login to protect your account from bots and ensure secure access to your academic data.",
        
        "password" => "🔒 Passwords are securely hashed and encrypted. You can reset yours in Account Settings or contact support if needed.",
        
        "security" => "🔐 Your data is protected with encryption, secure authentication, and regular security updates. Your privacy is our priority.",
        
        // Additional Features
        "calendar" => "📅 Check the calendar to view upcoming exams, assignment deadlines, events, and important academic dates.",
        
        "certificate" => "🎓 Digital certificates are automatically generated upon course completion and can be downloaded from your profile.",
        
        "forum" => "💭 Join discussions in the Q&A forum where students can post questions, share knowledge, and help each other learn.",
        
        "dark mode" => "🌙 Switch between light and dark themes in Settings → Display → Theme. Choose what's comfortable for your eyes!",
        
        "language" => "🌍 Multi-language support is available. Change your language preference in Settings → Language & Region.",
        
        // Study Help
        "study" => "📚 I can help you with study tips, assignment guidance, time management, and understanding course materials. What subject are you working on?",
        
        "exam" => "📝 Prepare for exams by reviewing your grades, checking the calendar for exam dates, and using the study materials in each course section.",
        
        "homework" => "📖 Find all your homework and assignments in the Assignments tab. Sort by due date to prioritize your work effectively.",
        
        // General Help
        "help" => "🆘 I'm here to assist! Ask me about:\n• 📊 Attendance & Grades\n• 📝 Assignments & Submissions\n• 👤 Profile Management\n• 💬 Chat & Communication\n• 🔧 Settings & Features\n• 📚 Study Tips & Academic Help",
        
        "features" => "✨ Alpha-Class offers: Real-time chat, interactive dashboards, grade analytics, attendance tracking, assignment management, announcements, and much more!",
        
        "support" => "🤝 Need more help? Contact your teacher through the chat feature, check the help documentation, or reach out to technical support.",
        
        // Friendly responses
        "hello" => "👋 Hello! Welcome to Alpha-Class! I'm your AI assistant ready to help with any questions about your studies or the platform.",
        
        "hi" => "👋 Hi there! How can I assist you today? Ask me about assignments, grades, attendance, or any platform features!",
        
        "thanks" => "😊 You're welcome! Happy to help. Feel free to ask if you have any other questions about Alpha-Class!",
        
        "thank you" => "😊 My pleasure! I'm always here to help you succeed in your studies. Is there anything else you'd like to know?"
    ];
    
    // Smart matching - check for multiple keywords and partial matches
    $response = "🤔 I'm not sure about that. Try asking about:\n• 📊 Attendance or Grades\n• 📝 Assignments or Homework\n• 👤 Profile or Settings\n• 💬 Chat or Communication\n• 🆘 Type 'help' for more options";
    
    $matched = false;
    
    // First, try exact keyword matching
    foreach ($responses as $keyword => $reply) {
        if (strpos($message, $keyword) !== false) {
            $response = $reply;
            $matched = true;
            break;
        }
    }
    
    // If no match, try common variations and synonyms
    if (!$matched) {
        $synonyms = [
            "grade" => ["mark", "marks", "score", "scores"],
            "profile picture" => ["picture", "photo", "image", "avatar"],
            "assignment" => ["task", "work", "homework"],
            "attendance" => ["present", "absent", "presence"],
            "chat" => ["message", "messaging", "talk"],
            "email" => ["notification", "notify", "alert"],
            "login" => ["signin", "log in", "sign in"],
            "profile" => ["setting", "settings", "preference"],
            "chart" => ["graph", "analytics", "statistics"]
        ];
        
        foreach ($synonyms as $canonical => $variants) {
            foreach ($variants as $variant) {
                if (strpos($message, $variant) !== false && isset($responses[$canonical])) {
                    $response = $responses[$canonical];
                    $matched = true;
                    break 2;
                }
            }
        }
    }
    
    // Add some personality with random encouraging messages
    if ($matched && rand(1, 4) == 1) {
        $encouragements = [
            "\n\n💪 Keep up the great work with your studies!",
            "\n\n🌟 You're doing amazing in Alpha-Class!",
            "\n\n🚀 Learning something new every day!",
            "\n\n📚 Knowledge is power - keep exploring!"
        ];
        $response .= $encouragements[array_rand($encouragements)];
    }
    
    echo $response;
} else {
    // Handle non-POST requests
    http_response_code(405);
    echo "🚫 Method not allowed. Please use POST request.";
}
?>