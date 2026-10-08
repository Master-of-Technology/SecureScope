<?php
declare(strict_types=1);

function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? null) === '443');

    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');
    session_name('securescope_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => APP_BASE_URL === '' ? '/' : APP_BASE_URL . '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/** @return array{user_id: int, role_name: string, client_id: ?int, full_name: string, email: string, must_change_password: int}|null */
function current_user(): ?array
{
    $user = $_SESSION['user'] ?? null;

    return is_array($user) ? $user : null;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

/**
 * Refreshes the session identity on every request so a suspended account or a
 * changed role loses its previous access immediately.
 */
function synchronize_authenticated_user(): void
{
    $sessionUser = current_user();

    if ($sessionUser === null) {
        return;
    }

    $statement = db()->prepare(
        'SELECT u.user_id, u.client_id, u.first_name, u.last_name, u.email,u.must_change_password , r.role_name
         FROM users AS u
         INNER JOIN roles AS r ON r.role_id = u.role_id
         WHERE u.user_id = :user_id AND u.status = \'active\'
         LIMIT 1'
    );
    $statement->execute(['user_id' => $sessionUser['user_id']]);
    $user = $statement->fetch();

    if ($user === false) {
        logout_user();
        return;
    }

    $_SESSION['user'] = session_user_payload($user);
}

/** @param array<string, mixed> $user */
function session_user_payload(array $user): array
{
    return [
        'user_id' => (int) $user['user_id'],
        'role_name' => (string) $user['role_name'],
        'client_id' => $user['client_id'] === null ? null : (int) $user['client_id'],
        'full_name' => trim((string) $user['first_name'] . ' ' . (string) $user['last_name']),
        'email' => (string) $user['email'],
        'must_change_password' => (int) $user['must_change_password'],
    ];
}

function require_login(): void
{
    if (!is_logged_in()) {
        set_flash('error', 'يرجى تسجيل الدخول للمتابعة.');
        redirect('login.php');
    }
}

/** @param string|array<int, string> $allowedRoles */
function require_role(string|array $allowedRoles): void
{
    require_login();
    $allowedRoles = (array) $allowedRoles;
    $user = current_user();

    if ($user === null || !in_array($user['role_name'], $allowedRoles, true)) {
        redirect('access-denied.php');
    }
}

function logout_user(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $parameters = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $parameters['path'] ?? '/',
            'domain' => $parameters['domain'] ?? '',
            'secure' => (bool) ($parameters['secure'] ?? false),
            'httponly' => (bool) ($parameters['httponly'] ?? true),
            'samesite' => $parameters['samesite'] ?? 'Lax',
        ]);
    }

    session_destroy();
}

function require_password_change_completed(): void
{
    require_login();

    $user = current_user();

    if (
        $user !== null
        && (int) ($user['must_change_password'] ?? 0) === 1
    ) {
        redirect('change-password.php');
    }
}