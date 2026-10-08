<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$user = current_user();
render_header('ملفي الشخصي');
?>
<section class="page-heading">
    <div>
        <p class="eyebrow">الحساب</p>
        <h1>ملفي الشخصي</h1>
        <p class="muted">ستتم إضافة تعديل الملف الشخصي وتغيير كلمة المرور بعد بناء عمليات CRUD الأساسية.</p>
    </div>
</section>

<section class="details-card">
    <dl class="details-list">
        <div><dt>الاسم</dt><dd><?= e($user['full_name']) ?></dd></div>
        <div><dt>البريد الإلكتروني</dt><dd><?= e($user['email']) ?></dd></div>
        <div><dt>الدور</dt><dd><?= e(role_label_ar($user['role_name'])) ?></dd></div>
    </dl>
</section>
<?php render_footer(); ?>
