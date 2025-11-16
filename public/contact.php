<?php
/**
 * ============================================
 * NACOS DASHBOARD - CONTACT US PAGE
 * ============================================
 * Purpose: Allow visitors to contact NACOS
 * Access: Public
 * Created: November 3, 2025
 * ============================================
 */

// Security gate
require_once __DIR__ . '/../includes/security.php';

// Include required files
require_once '../config/database.php';
require_once '../includes/auth.php';

// Initialize database
$db = getDB();

$errors = [];
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitizeInput($_POST['name'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $subject = sanitizeInput($_POST['subject'] ?? '');
    $message = sanitizeInput($_POST['message'] ?? '');

    // Validation
    if (empty($name)) {
        $errors[] = "Name is required.";
    }
    if (empty($email)) {
        $errors[] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }
    if (empty($subject)) {
        $errors[] = "Subject is required.";
    }
    if (empty($message)) {
        $errors[] = "Message is required.";
    }

    // If no errors, save to database (you can create a CONTACT_MESSAGES table) or send email
    if (empty($errors)) {
        // For now, we'll just show a success message
        // In a real application, you would save this to a database or send an email
        $success = "Thank you for contacting us! We'll get back to you as soon as possible.";
        
        // Clear form fields after successful submission
        $name = $email = $subject = $message = '';
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - NACOS</title>
    <link rel="icon" href="../assets/images/favicon.png" type="image/png">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/public.css">
    <style>
        body {
            padding-top: 80px;
        }
        .page-header {
            background: var(--gradient);
            color: #fff;
            padding: 80px 0;
            text-align: center;
            margin-bottom: 60px;
        }
        .page-header h1 {
            color: #fff;
            font-size: 3rem;
            margin-bottom: 15px;
        }
        .page-header p {
            color: rgba(255,255,255,0.9);
            font-size: 1.2rem;
        }
        .contact-section {
            padding: 40px 0 80px;
        }
        .contact-info-card {
            background: #fff;
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 5px 25px rgba(0,0,0,0.1);
            height: 100%;
            transition: all 0.3s ease;
        }
        .contact-info-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.15);
        }
        .contact-info-card i {
            font-size: 3rem;
            color: var(--primary-color);
            margin-bottom: 20px;
        }
        .contact-info-card h4 {
            color: var(--dark-gray);
            margin-bottom: 15px;
            font-weight: 600;
        }
        .contact-info-card p {
            color: var(--text-color);
            margin: 0;
        }
        .contact-form-card {
            background: #fff;
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 5px 25px rgba(0,0,0,0.1);
        }
        .contact-form-card h3 {
            color: var(--primary-color);
            margin-bottom: 30px;
            font-weight: 600;
        }
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(var(--primary-color-rgb), 0.25);
        }
        .map-section {
            margin-top: 60px;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 25px rgba(0,0,0,0.1);
        }
        .social-contact {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }
        .social-contact a {
            width: 45px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--light-gray);
            color: var(--primary-color);
            border-radius: 50%;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        .social-contact a:hover {
            background: var(--primary-color);
            color: #fff;
            transform: scale(1.1);
        }
    </style>
</head>
<body>
    <!-- Header (shared) -->
    <?php include __DIR__ . '/../includes/public_navbar.php'; ?>

    <!-- Page Header -->
    <section class="page-header">
        <div class="container">
            <h1>Get In Touch</h1>
            <p>We'd love to hear from you! Reach out with any questions or feedback.</p>
        </div>
    </section>

    <!-- Contact Section -->
    <section class="contact-section">
        <div class="container">
            <div class="row mb-5">
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="contact-info-card text-center">
                        <i class="fas fa-map-marker-alt"></i>
                        <h4>Find Us</h4>
                        <p>Computer Science Department<br>
                        University Campus<br>
                        Student Lounge, CS Building</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="contact-info-card text-center">
                        <i class="fas fa-envelope"></i>
                        <h4>Email Us</h4>
                        <p>info@nacos.edu.ng<br>
                        support@nacos.edu.ng<br>
                        admin@nacos.edu.ng</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="contact-info-card text-center">
                        <i class="fas fa-phone"></i>
                        <h4>Call Us</h4>
                        <p>+234 (0) 123 456 7890<br>
                        +234 (0) 987 654 3210<br>
                        Mon - Fri, 9:00 AM - 5:00 PM</p>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-6 mb-4">
                    <div class="contact-form-card">
                        <h3><i class="fas fa-paper-plane me-2"></i>Send Us a Message</h3>
                        
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?php echo $error; ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($success)): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                            </div>
                        <?php endif; ?>

                        <form action="contact.php" method="POST">
                            <div class="mb-3">
                                <label for="name" class="form-label">Your Name</label>
                                <input type="text" class="form-control" id="name" name="name" value="<?php echo isset($name) ? htmlspecialchars($name) : ''; ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Your Email</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="subject" class="form-label">Subject</label>
                                <input type="text" class="form-control" id="subject" name="subject" value="<?php echo isset($subject) ? htmlspecialchars($subject) : ''; ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="message" class="form-label">Message</label>
                                <textarea class="form-control" id="message" name="message" rows="5" required><?php echo isset($message) ? htmlspecialchars($message) : ''; ?></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary btn-lg w-100">
                                <i class="fas fa-paper-plane me-2"></i>Send Message
                            </button>
                        </form>
                    </div>
                </div>

                <div class="col-lg-6 mb-4">
                    <div class="contact-info-card">
                        <h3><i class="fas fa-info-circle me-2"></i>About NACOS</h3>
                        <p class="mb-4">NACOS is a vibrant student association for Computer Science students. We meet regularly at the Computer Science Department building. Feel free to reach out to us anytime!</p>
                        
                        <h5 class="mt-4"><i class="fas fa-share-alt me-2"></i>Connect With Us</h5>
                        <div class="social-contact">
                            <a href="#" title="Facebook"><i class="fab fa-facebook-f"></i></a>
                            <a href="#" title="Twitter"><i class="fab fa-twitter"></i></a>
                            <a href="#" title="Instagram"><i class="fab fa-instagram"></i></a>
                            <a href="#" title="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                            <a href="#" title="GitHub"><i class="fab fa-github"></i></a>
                            <a href="#" title="WhatsApp"><i class="fab fa-whatsapp"></i></a>
                        </div>

                        <div class="mt-4 p-3 bg-light rounded">
                            <h6><i class="fas fa-users me-2"></i>Join Our Community</h6>
                            <p class="mb-0 small">Interested in becoming a member? Visit our <a href="register.php" class="text-decoration-none">registration page</a> to join the NACOS family and access exclusive events, workshops, and networking opportunities!</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Map Section -->
            <div class="map-section">
                <iframe 
                    src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3963.952912260219!2d3.375295414770757!3d6.5276316955576355!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x103b8b2ae68280c1%3A0xdc9e87a367c3d9cb!2sLagos!5e0!3m2!1sen!2sng!4v1234567890123!5m2!1sen!2sng" 
                    width="100%" 
                    height="400" 
                    style="border:0;" 
                    allowfullscreen="" 
                    loading="lazy" 
                    referrerpolicy="no-referrer-when-downgrade">
                </iframe>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 mb-4">
                    <h5 class="footer-brand"><i class="fas fa-graduation-cap"></i> NACOS</h5>
                    <p>Fostering a community of innovators and leaders in computer science.</p>
                    <div class="social-links">
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-facebook"></i></a>
                        <a href="#"><i class="fab fa-linkedin"></i></a>
                        <a href="#"><i class="fab fa-github"></i></a>
                    </div>
                </div>
                <div class="col-lg-2 col-6 mb-4">
                    <h5>Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="about.php">About Us</a></li>
                        <li><a href="events.php">Events</a></li>
                        <li><a href="projects.php">Projects</a></li>
                        <li><a href="contact.php">Contact</a></li>
                    </ul>
                </div>
                <div class="col-lg-3 col-6 mb-4">
                    <h5>Get Involved</h5>
                    <ul class="list-unstyled">
                        <li><a href="register.php">Become a Member</a></li>
                        <li><a href="#">Volunteer</a></li>
                        <li><a href="#">Sponsor an Event</a></li>
                    </ul>
                </div>
                <div class="col-lg-3 mb-4">
                    <h5>Contact Us</h5>
                    <ul class="list-unstyled">
                        <li><i class="fas fa-map-marker-alt"></i> CS Department</li>
                        <li><i class="fas fa-envelope"></i> info@nacos.edu.ng</li>
                        <li><i class="fas fa-phone"></i> +234 (0) 123 456 7890</li>
                    </ul>
                </div>
            </div>
            <hr>
            <div class="text-center">
                <p>&copy; <?php echo date('Y'); ?> NACOSAU. All Rights Reserved.</p>
                <p class="mt-2"><small>Developed by <a href="https://johnicity.com.ng/portfolio" target="_blank" style="color: #fff; text-decoration: underline;">Johnicity</a></small></p>
            </div>
        </div>
    <?php include __DIR__ . '/../includes/public_footer.php'; ?>
</body>
</html>
