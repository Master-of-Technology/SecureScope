<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_role('Security Manager');
$user = current_user();
if ($user === null) {
    logout_user();
    redirect('login.php');
}
$requestId = filter_input(INPUT_GET, 'request_id', FILTER_VALIDATE_INT);
if ($requestId === false || $requestId === null || $requestId <= 0) {
    set_flash('error', 'معرف الطلب غير صالح.');
    redirect('manager/project-requests/index.php');
}
$statement = db()->prepare(
    "SELECT
        pr.*,
        c.company_name,
        c.company_email,
        c.phone AS company_phone,
        c.address AS company_address
     FROM projects_requests AS pr
     INNER JOIN clients AS c
        ON c.client_id = pr.client_id
     WHERE pr.request_id = :request_id
     LIMIT 1"
);
$statement->execute([
    'request_id' => $requestId,
]);
$request = $statement->fetch();
if ($request === false) {
    set_flash('error', 'طلب المشروع غير موجود.');
    redirect('manager/project-requests/index.php');
}
$typeStatement = db()->query(
    "SELECT
        assessment_type_id,
        name
     FROM assessment_types
     ORDER BY name ASC"
);
$assessmentTypes = $typeStatement->fetchAll();
$statusLabels = [
    'pending' => 'بانتظار المراجعة',
    'approved' => 'تمت الموافقة',
    'rejected' => 'مرفوض',
    'cancelled' => 'ملغى',
];
$priorityLabels = [
    'low' => 'منخفضة',
    'normal' => 'عادية',
    'high' => 'عالية',
    'urgent' => 'عاجلة',
];
render_header('تفاصيل طلب المشروع');
?>
<section class="page-heading">
    <div>
        <p class="eyebrow">مدير الأمن</p>
        <h1>تفاصيل طلب المشروع</h1>
        <p class="muted">
            مراجعة طلب العميل قبل تحويله إلى مشروع أمني.
        </p>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('manager/project-requests/index.php')) ?>" class="button button-primary">
            العودة إلى الطلبات
        </a>
    </div>
</section>
<section class="details-card">
    <div class="section-heading compact">
        <div>
            <h2>
                <?= e((string) $request['request_title']) ?>
            </h2>
        </div>
        <span class="status-badge status-<?= e((string) $request['status']) ?>">
            <?= e(
                $statusLabels[(string) $request['status']]
                ?? (string) $request['status']
            ) ?>
        </span>
    </div>
    <div class="detail-grid">
        <div>
            <span class="detail-label">العميل</span>
            <strong>
                <?= e((string) $request['company_name']) ?>
            </strong>
        </div>
        <div>
            <span class="detail-label">البريد الإلكتروني</span>
            <strong>
                <?= e((string) ($request['company_email'] ?? '—')) ?>
            </strong>
        </div>
        <div>
            <span class="detail-label">الهاتف</span>
            <strong>
                <?= e((string) ($request['company_phone'] ?? '—')) ?>
            </strong>
        </div>
        <div>
            <span class="detail-label">الخدمة المطلوبة</span>
            <strong>
                <?= e((string) $request['requested_service']) ?>
            </strong>
        </div>
        <div>
            <span class="detail-label">الأولوية</span>
            <strong>
                <?= e(
                    $priorityLabels[(string) $request['priority']]
                    ?? (string) $request['priority']
                ) ?>
            </strong>
        </div>
        <div>
            <span class="detail-label">تاريخ الطلب</span>
            <strong>
                <?= e((string) $request['created_at']) ?>
            </strong>
        </div>
    </div>
    <div class="detail-section">
        <h3>تفاصيل العميل</h3>
        <p>
            <?= nl2br(e((string) ($request['description'] ?? 'لا توجد تفاصيل إضافية.'))) ?>
        </p>
    </div>
</section>
<?php if ($request['status'] === 'pending'): ?>
    <section class="details-card">
        <div class="section-heading compact">
            <div>
                <h2>الموافقة على الطلب</h2>
                <p class="muted">
                    عند الموافقة سيتم إنشاء مشروع جديد للعميل.
                </p>
            </div>
        </div>
        <form method="post" action="<?= e(url('manager/project-requests/approve.php')) ?>">
            <?= csrf_input() ?>
            <input type="hidden" name="request_id" value="<?= e((string) $requestId) ?>">
            <div class="form-group">
                <label for="assessment_type_id">نوع التقييم</label>
                <select id="assessment_type_id" name="assessment_type_id" required>
                    <option value="">اختر نوع التقييم</option>
                    <?php foreach ($assessmentTypes as $type): ?>
                        <option value="<?= e((string) $type['assessment_type_id']) ?>">
                            <?= e((string) $type['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="project_name">اسم المشروع</label>
                <input id="project_name" type="text" name="project_name" maxlength="200" required
                    value="<?= e((string) $request['request_title']) ?>">
            </div>
            <div class="form-group">
                <label for="manager_notes">ملاحظات الإدارة</label>
                <textarea id="manager_notes" name="manager_notes" rows="5" maxlength="10000"
                    placeholder="ملاحظات أو تعليمات للعميل..."></textarea>
            </div>
            <div class="form-actions">
                <button type="submit" class="button button-primary">
                    الموافقة وإنشاء المشروع
                </button>
            </div>
        </form>
    </section>
    <section class="details-card">
        <div class="section-heading compact">
            <div>
                <h2>رفض الطلب</h2>
                <p class="muted">
                    اذكر سبب الرفض ليظهر للعميل.
                </p>
            </div>
        </div>
        <form method="post" action="<?= e(url('manager/project-requests/reject.php')) ?>">
            <?= csrf_input() ?>
            <input type="hidden" name="request_id" value="<?= e((string) $requestId) ?>">
            <div class="form-group">
                <label for="manager_notes_reject">سبب الرفض</label>
                <textarea id="manager_notes_reject" name="manager_notes" rows="5" maxlength="10000" required></textarea>
            </div>
            <div class="form-actions">
                <button type="submit" class="button button-danger">
                    رفض الطلب
                </button>
            </div>
        </form>
    </section>
<?php elseif ($request['status'] === 'approved'): ?>
    <section class="details-card">
        <div class="alert alert-success">
            تمت الموافقة على هذا الطلب وتحويله إلى مشروع.
        </div>
    </section>
<?php elseif ($request['status'] === 'rejected'): ?>
    <section class="details-card">
        <div class="alert alert-error">
            تم رفض هذا الطلب.
        </div>
        <?php if (!empty($request['manager_notes'])): ?>
            <div class="detail-section">
                <h3>ملاحظات الإدارة</h3>
                <p>
                    <?= nl2br(e((string) $request['manager_notes'])) ?>
                </p>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>
<?php render_footer(); ?>