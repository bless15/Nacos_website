<?php
/**
 * Public Partner Interest Form
 * - GET: show form
 * - POST: validate input and insert into PARTNER_REQUESTS
 */
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../config/database.php';

$db = getDB();
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $company_name = trim($_POST['company_name'] ?? '');
    $contact_name = trim($_POST['contact_name'] ?? '');
    $contact_email = trim($_POST['contact_email'] ?? '');
    $website_url = trim($_POST['website_url'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if ($company_name === '') {
        $errors[] = 'Company / Organisation name is required.';
    }
    if ($contact_name === '') {
        $errors[] = 'Contact name is required.';
    }
    if ($contact_email === '' || !filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid contact email is required.';
    }

    if (empty($errors)) {
        try {
            // The schema defines contact_person and created_at (auto-filled). Insert only the columns that exist.
            $db->query("INSERT INTO PARTNER_REQUESTS (company_name, contact_person, contact_email, website_url, message)
                        VALUES (:company_name, :contact_person, :contact_email, :website_url, :message)", [
                ':company_name' => $company_name,
                ':contact_person' => $contact_name,
                ':contact_email' => $contact_email,
                ':website_url' => $website_url,
                ':message' => $message,
            ]);

            $success = true;
        } catch (Exception $e) {
            $errors[] = 'Unable to submit your request at this time. Please try again later.';
            if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
                $errors[] = $e->getMessage();
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Partner with Us - NACOS</title>
    <link rel="stylesheet" href="../assets/css/public.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body>
    <?php include __DIR__ . '/../includes/public_navbar.php'; ?>

    <main class="py-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <h1 class="mb-3">Partner with NACOS</h1>
                    <p class="lead">We're excited to hear from organisations interested in partnering with us. Please fill in the form below and our partnerships team will be in touch.</p>

                    <?php if ($success): ?>
                        <div class="alert alert-success">Thank you — your request has been received. We'll contact you shortly.</div>
                        <p><a href="index.php" class="btn btn-secondary">Back to homepage</a></p>
                    <?php else: ?>
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    <?php foreach ($errors as $err): ?>
                                        <li><?php echo htmlspecialchars($err); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <form method="post" novalidate>
                            <div class="mb-3">
                                <label for="company_name" class="form-label">Company / Organisation</label>
                                <input type="text" id="company_name" name="company_name" class="form-control" value="<?php echo htmlspecialchars($_POST['company_name'] ?? ''); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="contact_name" class="form-label">Contact Name</label>
                                <input type="text" id="contact_name" name="contact_name" class="form-control" value="<?php echo htmlspecialchars($_POST['contact_name'] ?? ''); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="contact_email" class="form-label">Contact Email</label>
                                <input type="email" id="contact_email" name="contact_email" class="form-control" value="<?php echo htmlspecialchars($_POST['contact_email'] ?? ''); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="website_url" class="form-label">Website (optional)</label>
                                <input type="url" id="website_url" name="website_url" class="form-control" value="<?php echo htmlspecialchars($_POST['website_url'] ?? ''); ?>">
                            </div>
                            <div class="mb-3">
                                <label for="message" class="form-label">Message / What you'd like to collaborate on</label>
                                <textarea id="message" name="message" rows="5" class="form-control"><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                            </div>
                            <div class="d-grid">
                                <button class="btn btn-primary">Submit Partnership Request</button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <?php include __DIR__ . '/../includes/public_footer.php'; ?>
</body>
</html>
