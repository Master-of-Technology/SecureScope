<?php
declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));

require_once ROOT_PATH . '/config/app.php';
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/includes/helpers.php';
require_once ROOT_PATH . '/includes/csrf.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/includes/audit.php';
require_once ROOT_PATH . '/includes/layout.php';
require_once ROOT_PATH . '/includes/mail.php';

start_secure_session();
synchronize_authenticated_user();
