<?php
/**
 * ============================================
 * NACOS DASHBOARD - EMAIL CONFIGURATION
 * ============================================
 * Purpose: Email sending functionality
 * Created: November 3, 2025
 * ============================================
 */

// Prevent direct access
if (!defined('NACOS_ACCESS')) {
    die('Direct access not permitted');
}

/**
 * Email Configuration
 * For production: Configure with your SMTP settings
 * For development: Uses PHP mail() function
 */
define('EMAIL_FROM', 'noreply@nacos.edu.ng');
define('EMAIL_FROM_NAME', 'NACOS Admin');
define('ADMIN_EMAIL', 'admin@nacos.edu.ng'); // Change to actual admin email

// Use SMTP or PHP mail()
define('USE_SMTP', false); // Set to true for production

// SMTP Settings (if USE_SMTP is true)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-password');
define('SMTP_ENCRYPTION', 'tls'); // tls or ssl

/**
 * Send email function
 */
function sendEmail($to, $subject, $message, $from_name = EMAIL_FROM_NAME, $from_email = EMAIL_FROM) {
    if (USE_SMTP) {
        return sendEmailSMTP($to, $subject, $message, $from_name, $from_email);
    } else {
        return sendEmailPHP($to, $subject, $message, $from_name, $from_email);
    }
}

/**
 * Send email using PHP mail() function
 */
function sendEmailPHP($to, $subject, $message, $from_name, $from_email) {
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: $from_name <$from_email>" . "\r\n";
    $headers .= "Reply-To: $from_email" . "\r\n";
    
    return mail($to, $subject, $message, $headers);
}

/**
 * Send email using SMTP (requires PHPMailer or similar)
 */
function sendEmailSMTP($to, $subject, $message, $from_name, $from_email) {
    // For production, implement PHPMailer here
    // This is a placeholder
    return false;
}

/**
 * Send member approval notification
 */
function sendApprovalEmail($member_email, $member_name) {
    $subject = "NACOS Membership Approved!";
    
    $message = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; }
            .button { display: inline-block; background: #667eea; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
            .footer { text-align: center; color: #6c757d; font-size: 12px; margin-top: 20px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🎉 Welcome to NACOS!</h1>
            </div>
            <div class='content'>
                <p>Dear <strong>" . htmlspecialchars($member_name) . "</strong>,</p>
                
                <p>Great news! Your NACOS membership application has been <strong>approved</strong>.</p>
                
                <p>You can now log in to your member dashboard and access all NACOS resources, events, and activities.</p>
                
                <p style='text-align: center;'>
                    <a href='" . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . "://" . $_SERVER['HTTP_HOST'] . "/nacos/public/login.php' class='button'>
                        Login to Dashboard
                    </a>
                </p>
                
                <p>If you have any questions, feel free to contact our admin team.</p>
                
                <p>Best regards,<br><strong>NACOS Executive Team</strong></p>
            </div>
            <div class='footer'>
                <p>© " . date('Y') . " NACOS - National Association of Computer Science Students</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendEmail($member_email, $subject, $message);
}

/**
 * Send member rejection notification
 */
function sendRejectionEmail($member_email, $member_name) {
    $subject = "NACOS Membership Application Update";
    
    $message = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; }
            .button { display: inline-block; background: #667eea; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
            .footer { text-align: center; color: #6c757d; font-size: 12px; margin-top: 20px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Membership Application Update</h1>
            </div>
            <div class='content'>
                <p>Dear <strong>" . htmlspecialchars($member_name) . "</strong>,</p>
                
                <p>Thank you for your interest in joining NACOS.</p>
                
                <p>After reviewing your application, we regret to inform you that we were unable to approve your membership at this time. This may be due to incomplete or incorrect information provided during registration.</p>
                
                <p>You're welcome to register again with correct details:</p>
                
                <p style='text-align: center;'>
                    <a href='" . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . "://" . $_SERVER['HTTP_HOST'] . "/nacos/public/register.php' class='button'>
                        Register Again
                    </a>
                </p>
                
                <p>If you believe this is a mistake or have any questions, please contact our admin team.</p>
                
                <p>Best regards,<br><strong>NACOS Executive Team</strong></p>
            </div>
            <div class='footer'>
                <p>© " . date('Y') . " NACOS - National Association of Computer Science Students</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendEmail($member_email, $subject, $message);
}

/**
 * Send new registration notification to admins
 */
function sendNewRegistrationNotification($member_name, $member_matric, $member_email) {
    $subject = "New Member Registration - Pending Approval";
    
    $message = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; }
            .info-box { background: white; padding: 15px; border-left: 4px solid #667eea; margin: 15px 0; }
            .button { display: inline-block; background: #667eea; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
            .footer { text-align: center; color: #6c757d; font-size: 12px; margin-top: 20px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🔔 New Registration Alert</h1>
            </div>
            <div class='content'>
                <p><strong>A new member has registered and is pending approval.</strong></p>
                
                <div class='info-box'>
                    <p><strong>Name:</strong> " . htmlspecialchars($member_name) . "</p>
                    <p><strong>Matric Number:</strong> " . htmlspecialchars($member_matric) . "</p>
                    <p><strong>Email:</strong> " . htmlspecialchars($member_email) . "</p>
                </div>
                
                <p>Please review this registration and approve or reject it from the admin dashboard.</p>
                
                <p style='text-align: center;'>
                    <a href='" . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . "://" . $_SERVER['HTTP_HOST'] . "/nacos/admin/members.php?approval=pending' class='button'>
                        Review in Dashboard
                    </a>
                </p>
            </div>
            <div class='footer'>
                <p>© " . date('Y') . " NACOS Dashboard - Automated Notification</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendEmail(ADMIN_EMAIL, $subject, $message);
}
