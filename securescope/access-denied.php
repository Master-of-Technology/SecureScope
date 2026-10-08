<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

http_response_code(403);
render_header('الوصول مرفوض', is_logged_in());
?>
<section class="empty-state">
    <p class="eyebrow">403</p>
    <h1>الوصول مرفوض</h1>
    <p class="muted">دورك الحالي لا يملك صلاحية فتح هذه الصفحة.</p>
    <?php if (is_logged_in()): ?>
        <?php $user = current_user(); ?>
        <a class="button button-primary" href="<?= e(url(role_home_path($user['role_name']))) ?>">العودة إلى لوحة التحكم</a>
    <?php else: ?>
        <a class="button button-primary" href="<?= e(url('login.php')) ?>">تسجيل الدخول</a>
    <?php endif; ?>
</section>
<?php render_footer(); ?>
