<?php
declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    $baseUrl = APP_BASE_URL === '' ? '' : APP_BASE_URL;
    $path = ltrim($path, '/');

    return $path === '' ? $baseUrl . '/' : $baseUrl . '/' . $path;
}

function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

function is_post_request(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'][] = [
        'type' => $type,
        'message' => $message,
    ];
}

/** @return array<int, array{type: string, message: string}> */
function pull_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);

    return is_array($flashes) ? $flashes : [];
}

function client_ip_address(): ?string
{
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;

    return is_string($ipAddress) && filter_var($ipAddress, FILTER_VALIDATE_IP)
        ? $ipAddress
        : null;
}

function role_home_path(string $roleName): string
{
    return match ($roleName) {
        'Security Manager' => 'manager/dashboard.php',
        'Security Analyst' => 'analyst/dashboard.php',
        'Client' => 'client/dashboard.php',
        default => 'access-denied.php',
    };
}

function role_label_ar(string $roleName): string
{
    return match ($roleName) {
        'Security Manager' => 'مدير الأمن',
        'Security Analyst' => 'محلل الأمن',
        'Client' => 'العميل',
        default => $roleName,
    };
}
