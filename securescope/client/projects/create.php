<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_role('Client');
$user = current_user();
if ($user === null) {
    logout_user();
    redirect('login.php');
}
$clientId = (int) ($user['client_id'] ?? 0);
if ($clientId <= 0) {
    set_flash('error', 'حساب العميل غير مرتبط بشركة.');
    redirect('client/dashboard.php');
}
$requestTitle = '';
$description = '';
$requestedService = '';
$priority = 'normal';
$errors = [];
$priorityLabels = [
    'low' => 'منخفضة',
    'normal' => 'عادية',
    'high' => 'عالية',
    'urgent' => 'عاجلة',
];
if (is_post_request()) {
    require_valid_csrf();
    $requestTitle = trim((string) ($_POST['request_title'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $requestedService = trim((string) ($_POST['requested_service'] ?? ''));
    $priority = trim((string) ($_POST['priority'] ?? 'normal'));
    if ($requestTitle === '') {
        $errors[] = 'عنوان الطلب مطلوب.';
    } elseif (strlen($requestTitle) > 150) {
        $errors[] = 'عنوان الطلب طويل جدًا.';
    }
    if ($requestedService === '') {
        $errors[] = 'الخدمة المطلوبة مطلوبة.';
    } elseif (strlen($requestedService) > 150) {
        $errors[] = 'اسم الخدمة طويل جدًا.';
    }
    if ($description !== '' && strlen($description) > 10000) {
        $errors[] = 'تفاصيل الطلب طويلة جدًا.';
    }
    if (!array_key_exists($priority, $priorityLabels)) {
        $errors[] = 'الأولوية غير صالحة.';
    }
    if ($errors === []) {
        $statement = db()->prepare(
            "INSERT INTO projects_requests (
                client_id,
                request_title,
                description,
                requested_service,
                priority,
                status
             ) VALUES (
                :client_id,
                :request_title,
                :description,
                :requested_service,
                :priority,
                'pending'
             )"
        );
        $statement->execute([
            'client_id' => $clientId,
            'request_title' => $requestTitle,
            'description' => $description !== '' ? $description : null,
            'requested_service' => $requestedService,
            'priority' => $priority,
        ]);
        set_flash(
            'success',
            'تم إرسال طلب المشروع إلى إدارة SecureScope بنجاح.'
        );
        redirect('client/projects/index.php');
    }
}
render_header('طلب مشروع جديد');
?>
<section class="page-heading">
    <div>
        <p class="eyebrow">بوابة العميل</p>
        <h1>طلب مشروع جديد</h1>
        <p class="muted">
            أرسل طلب الفحص إلى إدارة SecureScope لمراجعته والموافقة عليه.
        </p>
    </div>
    <div class="page-actions">
        <a
            href="<?= e(url('client/projects/index.php')) ?>"
            class="button button-primary"
        >
            العودة إلى المشاريع
        </a>
    </div>
</section>
<?php if ($errors !== []): ?>
        <section class="details-card">
            <div class="alert alert-error">
                <ul>
                    <?php foreach ($errors as $error): ?>
                            <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </section>
<?php endif; ?>
<section class="details-card">
    <div class="section-heading compact">
        <div>
            <h2>بيانات المشروع المطلوب</h2>
            <p class="muted">
                اذكر نوع الفحص والنظام الذي تريد من SecureScope فحصه.
            </p>
        </div>
    </div>
    <form method="post">
        <?= csrf_input() ?>
        <div class="form-group">
            <label for="request_title">عنوان الطلب</label>
            <input
                id="request_title"
                type="text"
                name="request_title"
                maxlength="150"
                required
                value="<?= e($requestTitle) ?>"
                placeholder="مثال: فحص أمني لتطبيق الشركة"
            >
        </div>
        <div class="form-group">
            <label for="requested_service">الخدمة المطلوبة</label>
            <input
                id="requested_service"
                type="text"
                name="requested_service"
                maxlength="150"
                required
                value="<?= e($requestedService) ?>"
                placeholder="مثال: Web Application Security Assessment"
            >
        </div>
        <div class="form-group">
            <label for="priority">الأولوية</label>
            <select id="priority" name="priority">
                <?php foreach ($priorityLabels as $value => $label): ?>
                        <option
                            value="<?= e($value) ?>"
                            <?= $priority === $value ? 'selected' : '' ?>
                        >
                            <?= e($label) ?>
                        </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="description">تفاصيل الطلب</label>
            <textarea
                id="description"
                name="description"
                rows="8"
                maxlength="10000"
                placeholder="اذكر النظام أو التطبيق المطلوب فحصه وأي معلومات مهمة..."
            ><?= e($description) ?></textarea>
        </div>
        <div class="form-actions">
            <a
                href="<?= e(url('client/projects/index.php')) ?>"
                class="button button-primary"
            >
                إلغاء
            </a>
            <button
                type="submit"
                class="button button-primary"
            >
                إرسال طلب المشروع
            </button>
        </div>
    </form>
</section>
<?php render_footer(); ?>