<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

if (!is_post_request()) {
    redirect('login.php');
}

require_valid_csrf();

$user = current_user();
if ($user !== null) {
    record_audit('logout', 'users', $user['user_id']);
}

logout_user();
redirect('login.php');
