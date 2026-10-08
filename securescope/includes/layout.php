<?php
declare(strict_types=1);

function render_header(string $pageTitle, bool $showNavigation = true): void
{
    $title = $pageTitle . ' | ' . APP_NAME;
    $currentUser = current_user();
    $flashes = pull_flashes();
    $navigationMode = $showNavigation && $currentUser !== null ? 'app' : 'none';
    require ROOT_PATH . '/partials/header.php';
}

function render_public_header(string $pageTitle): void
{
    $title = $pageTitle . ' | ' . APP_NAME;
    $currentUser = current_user();
    $flashes = pull_flashes();
    $navigationMode = 'public';
    require ROOT_PATH . '/partials/header.php';
}

function render_footer(): void
{
    require ROOT_PATH . '/partials/footer.php';
}
