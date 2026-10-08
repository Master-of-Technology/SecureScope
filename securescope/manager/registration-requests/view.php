<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

require_role('Security Manager');

$requestId = filter_input(INPUT_GET, 'request_id', FILTER_VALIDATE_INT);

if ($requestId === false || $requestId === null || $requestId <= 0) {
    set_flash('error', 'طلب التسجيل غير صالح.');
    redirect('manager/registration-requests/index.php');
}

$statement = db()->prepare(
    'SELECT
        rr.*,
        r.role_name,
        reviewer.first_name AS reviewer_first_name,
        reviewer.last_name AS reviewer_last_name
     FROM registration_requests AS rr
     INNER JOIN roles AS r
        ON r.role_id = rr.role_id
     LEFT JOIN users AS reviewer
        ON reviewer.user_id = rr.reviewed_by
     WHERE rr.request_id = :request_id
     LIMIT 1'
);

$statement->execute([
    'request_id' => $requestId,
]);

$request = $statement->fetch();

if ($request === false) {
    set_flash('error', 'لم يتم العثور على طلب التسجيل.');
    redirect('manager/registration-requests/index.php');
}

render_header('مراجعة طلب التسجيل');
?>

<section class="page-heading">
    <div>
        <p class="eyebrow">طلبات التسجيل</p>
        <h1>مراجعة طلب التسجيل</h1>
        <p class="muted">
            راجع بيانات المتقدم قبل اتخاذ القرار.
        </p>
    </div>

    <div class="button button-primary">
        <a
            href="<?= e(url('manager/registration-requests/index.php')) ?>"
            class="button button-secondary"
        >
            العودة إلى الطلبات
        </a>
    </div>
</section>

<section class="details-card">

    <div class="detail-section">
        <h2>بيانات المتقدم</h2>

        <div class="detail-grid">

            <div>
                <span class="detail-label">الاسم الكامل</span>
                <strong>
                    <?= e(
                        trim(
                            (string) $request['first_name']
                            . ' '
                            . (string) $request['last_name']
                        )
                    ) ?>
                </strong>
            </div>

            <div>
                <span class="detail-label">البريد الإلكتروني</span>
                <strong><?= e((string) $request['email']) ?></strong>
            </div>

            <div>
                <span class="detail-label">الهاتف</span>
                <strong><?= e((string) ($request['phone'] ?? '—')) ?></strong>
            </div>

            <div>
                <span class="detail-label">نوع الحساب</span>
                <strong>
                    <?= e(
                        $request['registration_type'] === 'client'
                        ? 'عميل'
                        : 'محلل أمني'
                    ) ?>
                </strong>
            </div>

        </div>
    </div>

    <?php if ($request['registration_type'] === 'client'): ?>

            <div class="detail-section">
                <h2>بيانات الشركة</h2>

                <div class="detail-grid">

                    <div>
                        <span class="detail-label">اسم الشركة</span>
                        <strong><?= e((string) ($request['company_name'] ?? '—')) ?></strong>
                    </div>

                    <div>
                        <span class="detail-label">البريد الإلكتروني للشركة</span>
                        <strong><?= e((string) ($request['company_email'] ?? '—')) ?></strong>
                    </div>

                    <div>
                        <span class="detail-label">الهاتف</span>
                        <strong><?= e((string) ($request['company_phone'] ?? '—')) ?></strong>
                    </div>

                    <div>
                        <span class="detail-label">المجال</span>
                        <strong><?= e((string) ($request['industry'] ?? '—')) ?></strong>
                    </div>

                    <div>
                        <span class="detail-label">العنوان</span>
                        <strong><?= e((string) ($request['company_address'] ?? '—')) ?></strong>
                    </div>

                </div>
            </div>

    <?php endif; ?>

    <div class="detail-section">
        <h2>حالة الطلب</h2>

        <p>
            <span class="status-badge status-<?= e((string) $request['status']) ?>">
                <?= e(match ((string) $request['status']) {
                    'pending' => 'قيد المراجعة',
                    'approved' => 'مقبول',
                    'rejected' => 'مرفوض',
                    'cancelled' => 'ملغى',
                    default => $request['status'],
                }) ?>
            </span>
        </p>

        <?php if (!empty($request['rejection_reason'])): ?>
                <div class="alert alert-error">
                    <strong>سبب الرفض:</strong>
                    <?= e((string) $request['rejection_reason']) ?>
                </div>
        <?php endif; ?>

    </div>

    <?php if ($request['status'] === 'pending'): ?>

            <div class="form-actions request-actions">

                <form
                    method="post"
                    action="<?= e(url('manager/registration-requests/approve.php')) ?>"
                >
                    <?= csrf_input() ?>

                    <input
                        type="hidden"
                        name="request_id"
                        value="<?= e((string) $request['request_id']) ?>"
                    >

                    <button
                        type="submit"
                        class="button button-primary"
                        onclick="return confirm('هل أنت متأكد من الموافقة على هذا الطلب؟');"
                    >
                        الموافقة على الطلب
                    </button>
                </form>

                <a
                    href="<?= e(
                        url(
                            'manager/registration-requests/reject.php?request_id='
                            . (int) $request['request_id']
                        )
                    ) ?>"
                    class="button button-danger"
                >
                    رفض الطلب
                </a>

            </div>

    <?php endif; ?>

</section>

<?php render_footer(); ?>