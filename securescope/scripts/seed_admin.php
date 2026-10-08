<?php
declare(strict_types=1);

/*
 * Run only from the command line to create the first Security Manager account.
 * Example:
 * C:\xampp\php\php.exe scripts\seed_admin.php manager@example.com Sara Ahmed "ChangeThisPassword123!"
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script may only run from the command line.\n");
}

require_once __DIR__ . '/../config/database.php';

if ($argc !== 5) {
    exit("Usage: php scripts/seed_admin.php <email> <first-name> <last-name> <password>\n");
}

[, $email, $firstName, $lastName, $password] = $argv;

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    exit("Error: enter a valid email address.\n");
}

if (strlen($password) < 12) {
    exit("Error: the password must contain at least 12 characters.\n");
}

$database = db();
$roleStatement = $database->prepare('SELECT role_id FROM roles WHERE role_name = :role_name LIMIT 1');
$roleStatement->execute(['role_name' => 'Security Manager']);
$roleId = $roleStatement->fetchColumn();

if ($roleId === false) {
    exit("Error: the Security Manager role was not found. Import the schema first.\n");
}

$existingUser = $database->prepare('SELECT user_id FROM users WHERE email = :email LIMIT 1');
$existingUser->execute(['email' => $email]);

if ($existingUser->fetchColumn() !== false) {
    exit("Error: a user with this email already exists.\n");
}

$statement = $database->prepare(
    'INSERT INTO users (role_id, first_name, last_name, email, password_hash)
     VALUES (:role_id, :first_name, :last_name, :email, :password_hash)'
);
$statement->execute([
    'role_id' => $roleId,
    'first_name' => $firstName,
    'last_name' => $lastName,
    'email' => $email,
    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
]);

echo "Security Manager account created successfully.\n";
