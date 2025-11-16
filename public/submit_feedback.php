<?php
/**
 * ============================================
 * NACOS DASHBOARD - SUBMIT EVENT FEEDBACK
 * ============================================
 * Purpose: Allow members to rate and review attended events
 * Access: Logged-in members only
 * Created: November 4, 2025
 * ============================================
 */

// Security gate
require_once __DIR__ . '/../includes/security.php';

// Include required files
require_once '../config/database.php';
require_once '../includes/auth.php';

// Require member login
requireMemberLogin();

// Get current member
$member = getCurrentMember();
$member_id = $member['member_id'];

// Initialize database
$db = getDB();

// Get event ID
$event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;

if (!$event_id) {
    redirectWithMessage('my_events.php', 'Invalid event', 'error');
}

// Verify member attended this event and hasn't submitted feedback
$member_event = $db->fetchOne(
    "SELECT me.*, e.event_name, e.event_date, e.event_type, e.summary
     FROM MEMBER_EVENTS me
     JOIN EVENTS e ON me.event_id = e.event_id
     WHERE me.event_id = ? AND me.member_id = ?",
    [$event_id, $member_id]
);

if (!$member_event) {
    redirectWithMessage('my_events.php', 'Event not found or you are not registered', 'error');
}

if ($member_event['attendance_status'] !== 'attended') {
    redirectWithMessage('my_events.php', 'You can only provide feedback for events you attended', 'error');
}

if (!empty($member_event['feedback_rating']) && !empty($member_event['feedback_comment'])) {
    redirectWithMessage('my_events.php', 'You have already submitted feedback for this event', 'info');
}

// Handle form submission
$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid security token. Please try again.';
    } else {
        $rating = intval($_POST['rating'] ?? 0);
        $comment = trim(sanitizeInput($_POST['comment'] ?? ''));
        
        // Validation
        if ($rating < 1 || $rating > 5) {
            $error_message = 'Please select a rating between 1 and 5 stars';
        } elseif (empty($comment)) {
            $error_message = 'Please provide your feedback comment';
        } elseif (strlen($comment) < 10) {
            $error_message = 'Feedback comment must be at least 10 characters';
        } else {
            try {
                $query = "UPDATE MEMBER_EVENTS 
                         SET feedback_rating = ?, feedback_comment = ?
                         WHERE event_id = ? AND member_id = ?";
                
                $db->query($query, [$rating, $comment, $event_id, $member_id]);
                
                redirectWithMessage('my_events.php', 'Thank you! Your feedback has been submitted successfully', 'success');
            } catch (Exception $e) {
                $error_message = 'Error submitting feedback: ' . $e->getMessage();
            }
        }
    }
}

// Generate CSRF token
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Feedback - NACOS Dashboard</title>
    <link rel="icon" type="image/x-icon" href="../assets/images/favicon.jpg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/public.css">
    <style>
        .feedback-container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .event-info {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        
        .rating-container {
            text-align: center;
            padding: 30px;
            background: #f8f9fa;
            border-radius: 10px;
            margin-bottom: 25px;
        }
        
        .star-rating {
            font-size: 3rem;
            cursor: pointer;
            user-select: none;
        }
        
        .star-rating i {
            color: #ddd;
            transition: color 0.2s, transform 0.2s;
            margin: 0 5px;
        }
        
        .star-rating i:hover {
            transform: scale(1.1);
        }
        
        .star-rating i.active,
        .star-rating i.hover {
            color: #ffc107;
        }
        
        .rating-label {
            font-size: 1.2rem;
            font-weight: 600;
            color: #333;
            margin-top: 15px;
        }
        
        .rating-labels {
            display: flex;
            justify-content: space-between;
            margin-top: 10px;
            font-size: 0.85rem;
            color: #666;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(0, 128, 0, 0.25);
        }
        
        .char-counter {
            font-size: 0.85rem;
            color: #666;
            text-align: right;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <?php include '../includes/public_navbar.php'; ?>
    
    <div class="container my-5">
        <div class="feedback-container">
            <!-- Page Header -->
            <div class="text-center mb-4">
                <h1 class="display-5 fw-bold">
                    <i class="fas fa-star me-3"></i>Event Feedback
                </h1>
                <p class="text-muted">Share your experience and help us improve</p>
            </div>
            
            <!-- Event Info -->
            <div class="event-info">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h3 class="mb-2"><?php echo htmlspecialchars($member_event['event_name']); ?></h3>
                        <p class="mb-1">
                            <i class="fas fa-calendar me-2"></i>
                            <?php echo date('F d, Y', strtotime($member_event['event_date'])); ?>
                        </p>
                        <p class="mb-0">
                            <i class="fas fa-tag me-2"></i>
                            <?php echo ucfirst(str_replace('_', ' ', $member_event['event_type'])); ?>
                        </p>
                    </div>
                    <span class="badge bg-success fs-6">
                        <i class="fas fa-check-circle me-1"></i>Attended
                    </span>
                </div>
            </div>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                </div>
            <?php endif; ?>
            
            <!-- Feedback Form -->
            <form method="POST" id="feedbackForm">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="rating" id="ratingValue" value="0">
                
                <!-- Star Rating -->
                <div class="rating-container">
                    <h5 class="mb-3">How would you rate this event?</h5>
                    <div class="star-rating" id="starRating">
                        <i class="fas fa-star" data-rating="1"></i>
                        <i class="fas fa-star" data-rating="2"></i>
                        <i class="fas fa-star" data-rating="3"></i>
                        <i class="fas fa-star" data-rating="4"></i>
                        <i class="fas fa-star" data-rating="5"></i>
                    </div>
                    <div class="rating-label" id="ratingLabel">Click on the stars to rate</div>
                    <div class="rating-labels">
                        <span>Poor</span>
                        <span>Fair</span>
                        <span>Good</span>
                        <span>Very Good</span>
                        <span>Excellent</span>
                    </div>
                </div>
                
                <!-- Feedback Comment -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title mb-3">
                            <i class="fas fa-comment-dots me-2"></i>Share Your Experience
                        </h5>
                        <div class="mb-3">
                            <label for="comment" class="form-label">
                                Your Feedback <span class="text-danger">*</span>
                            </label>
                            <textarea class="form-control" id="comment" name="comment" rows="6" 
                                      required minlength="10" maxlength="1000"
                                      placeholder="Tell us about your experience... What did you like? What could be improved?"></textarea>
                            <div class="char-counter">
                                <span id="charCount">0</span> / 1000 characters (minimum 10)
                            </div>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Note:</strong> Your feedback helps us organize better events and will be visible to administrators.
                        </div>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="d-flex justify-content-between">
                    <a href="my_events.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to My Events
                    </a>
                    <button type="submit" class="btn btn-success btn-lg" id="submitBtn" disabled>
                        <i class="fas fa-paper-plane me-2"></i>Submit Feedback
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <?php include __DIR__ . '/../includes/public_footer.php'; ?>
    
    <script>
        // Star Rating System
        const stars = document.querySelectorAll('.star-rating i');
        const ratingValue = document.getElementById('ratingValue');
        const ratingLabel = document.getElementById('ratingLabel');
        const submitBtn = document.getElementById('submitBtn');
        const commentField = document.getElementById('comment');
        const charCount = document.getElementById('charCount');
        
        const ratingTexts = {
            1: 'Poor - Not satisfied',
            2: 'Fair - Below expectations',
            3: 'Good - Met expectations',
            4: 'Very Good - Exceeded expectations',
            5: 'Excellent - Outstanding!'
        };
        
        let currentRating = 0;
        
        // Star click event
        stars.forEach(star => {
            star.addEventListener('click', function() {
                currentRating = parseInt(this.getAttribute('data-rating'));
                ratingValue.value = currentRating;
                updateStars();
                updateRatingLabel();
                checkFormValid();
            });
            
            // Hover effect
            star.addEventListener('mouseenter', function() {
                const hoverRating = parseInt(this.getAttribute('data-rating'));
                stars.forEach((s, index) => {
                    if (index < hoverRating) {
                        s.classList.add('hover');
                    } else {
                        s.classList.remove('hover');
                    }
                });
            });
        });
        
        // Reset hover on mouse leave
        document.querySelector('.star-rating').addEventListener('mouseleave', function() {
            stars.forEach(s => s.classList.remove('hover'));
        });
        
        function updateStars() {
            stars.forEach((star, index) => {
                if (index < currentRating) {
                    star.classList.add('active');
                } else {
                    star.classList.remove('active');
                }
            });
        }
        
        function updateRatingLabel() {
            if (currentRating > 0) {
                ratingLabel.textContent = ratingTexts[currentRating];
                ratingLabel.style.color = '#28a745';
            } else {
                ratingLabel.textContent = 'Click on the stars to rate';
                ratingLabel.style.color = '#333';
            }
        }
        
        // Character counter
        commentField.addEventListener('input', function() {
            const length = this.value.length;
            charCount.textContent = length;
            
            if (length >= 10) {
                charCount.style.color = '#28a745';
            } else {
                charCount.style.color = '#dc3545';
            }
            
            checkFormValid();
        });
        
        // Check if form is valid
        function checkFormValid() {
            const hasRating = currentRating > 0;
            const hasComment = commentField.value.trim().length >= 10;
            
            submitBtn.disabled = !(hasRating && hasComment);
        }
        
        // Form submission validation
        document.getElementById('feedbackForm').addEventListener('submit', function(e) {
            if (currentRating === 0) {
                e.preventDefault();
                alert('Please select a star rating before submitting');
                return false;
            }
            
            if (commentField.value.trim().length < 10) {
                e.preventDefault();
                alert('Please provide at least 10 characters in your feedback comment');
                return false;
            }
        });
    </script>
</body>
</html>
