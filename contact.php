<?php
require_once 'includes/config.php';
$page_title = 'Contact Us';

$sent = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // In a full build you'd store this in a `messages` table or send an email.
    // Kept simple here since the core requirement is the shop + orders database.
    $sent = true;
}

include 'includes/header.php';
?>

<section class="hero">
    <div class="container">
        <h1>Get In Touch</h1>
        <p>Questions about an order, sizing, or a product? We'd love to hear from you.</p>
    </div>
</section>

<div class="container">
    <div class="form-box">
        <?php if ($sent): ?>
            <div class="alert alert-success">Thanks for reaching out! We'll get back to you shortly.</div>
        <?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <label>Name</label>
                <input type="text" name="name" required>
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" required>
            </div>
            <div class="form-group">
                <label>Message</label>
                <textarea name="message" rows="5" required></textarea>
            </div>
            <button type="submit" class="btn btn-full">Send Message</button>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
