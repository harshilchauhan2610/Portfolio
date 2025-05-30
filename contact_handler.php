<?php
// Set the content type to application/json for AJAX responses
header('Content-Type: application/json');

// --- Database Configuration ---
// !! IMPORTANT: Replace with your actual database credentials !!
// These should be configured for your live hosting environment, not necessarily 'root' and empty password.
define('DB_HOST', 'localhost');       // Usually 'localhost'
define('DB_NAME', 'portfolio_db');  // The database name you will create or already have
define('DB_USER', 'root');            // Your database username
define('DB_PASS', '');                // Your database password

// --- Email Configuration ---
// !! IMPORTANT: Replace with your email address to receive notifications !!
define('ADMIN_EMAIL', 'harshilchauhan3617u@gmail.com'); // <--- REPLACE THIS WITH YOUR EMAIL ADDRESS
// A "from" email, preferably from your domain. Many servers require this to match your domain.
define('EMAIL_SENDER', 'https://harshilchauhan2610.github.io/Portfolio/'); // <--- REPLACE 'yourdomain.com' with your actual website domain

// Initialize response array
$response = ['status' => 'error', 'message' => 'An unexpected error occurred.'];

// --- Database Connection ---
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false); // Good practice for security and data types
} catch (PDOException $e) {
    // For production, log this error ($e->getMessage()) for debugging
    // error_log("Database connection failed: " . $e->getMessage());
    $response['message'] = 'Database connection failed. Please try again later.';
    echo json_encode($response);
    exit;
}

// --- Handle POST Request ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get and sanitize form data
    // FILTER_SANITIZE_STRING is deprecated in PHP 8.1+.
    // For modern PHP, consider htmlspecialchars and casting or specific filter types.
    // However, for string input, it's generally safe enough if it doesn't contain HTML.
    $name = trim(filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING));
    $email_address = trim(filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL)); // VALIDATE_EMAIL filters and validates
    $subject_text = trim(filter_input(INPUT_POST, 'subject', FILTER_SANITIZE_STRING));
    $message_text = trim(filter_input(INPUT_POST, 'message', FILTER_SANITIZE_STRING));

    // --- Basic Validation ---
    if (empty($name) || empty($email_address) || empty($subject_text) || empty($message_text)) {
        $response['message'] = 'Please fill in all required fields.';
        echo json_encode($response);
        exit;
    }
    if (!$email_address) { // If FILTER_VALIDATE_EMAIL failed, $email_address will be false
        $response['message'] = 'Invalid email format. Please enter a valid email address.';
        echo json_encode($response);
        exit;
    }

    // --- Insert into Database ---
    try {
        $stmt = $pdo->prepare("INSERT INTO contact_messages (name, email, subject, message) VALUES (:name, :email, :subject, :message)");
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':email', $email_address);
        $stmt->bindParam(':subject', $subject_text);
        $stmt->bindParam(':message', $message_text);
        
        if ($stmt->execute()) {
            $response['status'] = 'success';
            $response['message'] = 'Message sent successfully! Thank you for reaching out.';

            // --- Send Email Notification (NOW UNCOMMENTED AND ACTIVE) ---
            // Note: PHP's mail() function requires a configured mail server (e.g., Sendmail, Postfix)
            // on your hosting environment. If emails don't send, this is often a server configuration issue.
            // For highly reliable email sending, especially in production, consider a dedicated
            // email sending service (like SendGrid, Mailgun) via a PHP library like PHPMailer (SMTP).
            
            $email_subject_to_admin = "New Portfolio Contact from " . $name . ": " . $subject_text;
            $email_body_to_admin = "You have received a new message from your portfolio contact form.\n\n" .
                                   "Name: $name\n" .
                                   "Email: $email_address\n" .
                                   "Subject: $subject_text\n" .
                                   "Message:\n$message_text\n\n" .
                                   "Sent from your portfolio website.";

            // Add standard email headers for better deliverability
            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-type: text/plain; charset=UTF-8\r\n"; // Specify character set
            $headers .= "From: " . EMAIL_SENDER . "\r\n"; // From constant
            $headers .= "Reply-To: " . $email_address . "\r\n"; // Allows you to reply directly to the sender
            $headers .= "X-Mailer: PHP/" . phpversion(); // Good for debugging email issues

            // Attempt to send the email
            if (!mail(ADMIN_EMAIL, $email_subject_to_admin, $email_body_to_admin, $headers)) {
                // Email sending failed - this won't stop the database save from being successful.
                // Log this failure internally for your own debugging, but don't show it to the user
                // unless it's critical.
                error_log("Failed to send contact form email to " . ADMIN_EMAIL . " from " . $email_address);
                // Optional: You could add a message to the response indicating email failure,
                // but usually, saving to DB is the primary success, email is secondary notification.
                // $response['email_status'] = 'Email notification failed, but message saved.';
            }

        } else {
            $response['message'] = 'Failed to save message to database. Please try again.';
        }
    } catch (PDOException $e) {
        // Log database errors for debugging
        error_log("Database INSERT error: " . $e->getMessage());
        $response['message'] = 'An error occurred while processing your request. Please try again.';
        // $response['debug_message'] = $e->getMessage(); // ONLY for development, remove in production!
    }
} else {
    // Not a POST request
    $response['message'] = 'Invalid request method.';
}

// --- Send JSON Response ---
echo json_encode($response);
exit;
?>