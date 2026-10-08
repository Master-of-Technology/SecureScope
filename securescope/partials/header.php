<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="نظام SecureScope لإدارة تقييمات الأمن السيبراني">
    <title><?= e($title) ?></title>
    <link rel="stylesheet" href="<?= e(url('assets/css/app.css')) ?>">
</head>
<body>
<?php if ($navigationMode === 'app'): ?>
    <header class="site-header">
        <div class="header-content">
            <a class="brand" href="<?= e(url(role_home_path($currentUser['role_name']))) ?>">SecureScope</a>
            <nav class="main-nav" aria-label="التنقل الرئيسي">
                <a href="<?= e(url(role_home_path($currentUser['role_name']))) ?>">لوحة التحكم</a>
                <?php if ($currentUser['role_name'] === 'Security Manager'): ?>
                    <a href="<?= e(url('manager/clients/index.php')) ?>">العملاء</a>
                    <a href="<?= e(url('manager/projects/index.php')) ?>">المشاريع</a>
                    <a href="<?= e(url('manager/assessments/index.php')) ?>">التقييمات</a>
                    <a href="<?= e(url('manager/findings/index.php')) ?>">الثغرات</a>
                    <a href="<?= e(url('manager/users/index.php')) ?>">المستخدمون</a>
                    <a href="<?= e(url('manager/analyst-applications/index.php')) ?>">طلبات المحللين</a>
                    <a href="<?= e(url('manager/assets/index.php')) ?>">الأصول</a>
                <?php elseif ($currentUser['role_name'] === 'Security Analyst'): ?>
                    <a href="<?= e(url('analyst/assessments/index.php')) ?>">التقييمات</a>
                    <a href="<?= e(url('analyst/findings/index.php')) ?>">الثغرات</a>
                <?php elseif ($currentUser['role_name'] === 'Client'): ?>
                <?php elseif ($currentUser['role_name'] === 'Client'): ?>
                    <a href="<?= e(url('client/projects/index.php')) ?>">المشاريع</a>
                <?php endif; ?>
            </nav>
            <div class="user-menu">
                <a href="<?= e(url('profile.php')) ?>"><?= e($currentUser['full_name']) ?></a>
                <form method="post" action="<?= e(url('logout.php')) ?>">
                    <?= csrf_input() ?>
                    <button class="button button-ghost" type="submit">تسجيل الخروج</button>
                </form>
            </div>
        </div>
    </header>
<?php elseif ($navigationMode === 'public'): ?>
    <header class="public-header">
        <div class="public-header-content">
            <a class="public-brand" href="<?= e(url('index.php')) ?>">SecureScope</a>
            <nav class="public-nav" aria-label="التنقل الرئيسي">
                <a href="<?= e(url('index.php')) ?>">الرئيسية</a>
                <a href="<?= e(url('about.php')) ?>">من نحن</a>
                <a href="<?= e(url('services.php')) ?>">خدماتنا</a>
                <a href="<?= e(url('contact.php')) ?>">تواصل معنا</a>
            </nav>
            <div class="public-header-action">
                <?php if ($currentUser !== null): ?>
                    <a class="button button-primary" href="<?= e(url(role_home_path($currentUser['role_name']))) ?>">لوحة التحكم</a>
                <?php else: ?>
                    <a class="button button-primary" href="<?= e(url('login.php')) ?>">تسجيل الدخول</a>
                <?php endif; ?>
            </div>
        </div>
    </header>
<?php endif; ?>

<main class="page-shell">
    <?php foreach ($flashes as $flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></div>
    <?php endforeach; ?>