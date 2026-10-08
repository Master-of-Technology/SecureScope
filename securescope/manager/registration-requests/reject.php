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
    'SELECT *
     FROM registration_requests
     WHERE request_id = :request_id
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

if ($request['status'] !== 'pending') {
    set_flash('error', 'هذا الطلب تمت معالجته مسبقًا.');
    redirect(
        'manager/registration-requests/view.php?request_id=' . $requestId
    );
}

if (is_post_request()) {

    require_valid_csrf();

    $reason = trim((string) ($_POST['rejection_reason'] ?? ''));

    if ($reason === '') {
        set_flash('error', 'يجب إدخال سبب رفض الطلب.');
    } elseif (mb_strlen($reason) > 500) {
        set_flash('error', 'سبب الرفض طويل جدًا.');
    } else {

        $updateStatement = db()->prepare(
            'UPDATE registration_requests
             SET
                status = \'rejected\',
                reviewed_by = :reviewed_by,
                reviewed_at = NOW(),
                rejection_reason = :rejection_reason,
                updated_at = CURRENT_TIMESTAMP
             WHERE request_id = :request_id
               AND status = \'pending\''
        );

        $updateStatement->execute([
            'reviewed_by' => current_user()['user_id'],
            'rejection_reason' => $reason,
            'request_id' => $requestId,
        ]);

        record_audit(
            'REJECT_REGISTRATION_REQUEST',
            'registration_requests',
            $requestId,
            [
                'status' => 'pending',
            ],
            [
                'status' => 'rejected',
                'rejection_reason' => $reason,
            ]
        );

        set_flash(
            'success',
            'تم رفض طلب التسجيل وتسجيل سبب الرفض.'
        );

        redirect(
            'manager/registration-requests/view.php?request_id=' . $requestId
        );
    }
}

render_header('رفض طلب التسجيل');
?>

<section class="page-heading">
    <div>
        <p class="eyebrow">طلبات التسجيل</p>
        <h1>رفض طلب التسجيل</h1>
        <p class="muted">
            يجب توضيح سبب رفض الطلب.
        </p>
    </div>
</section>

<section class="details-card">

    <div class="detail-section">
        <h2>بيانات الطلب</h2>

        <p>
            <strong>المتقدم:</strong>
            <?= e(
                trim(
                    (string) $request['first_name']
                    . ' '
                    . (string) $request['last_name']
                )
            ) ?>
        </p>

        <?php if (!empty($request['company_name'])): ?>
                <p>
                    <strong>الشركة:</strong>
                    <?= e((string) $request['company_name']) ?>
                </p>
        <?php endif; ?>
    </div>

    <form method="post">

        <?= csrf_input() ?>

        <input
            type="hidden"
            name="request_id"
            value="<?= e((string) $requestId) ?>"
        >

        <div class="form-field">
            <label for="rejection_reason">
                سبب الرفض
            </label>

            <textarea
                id="rejection_reason"
                name="rejection_reason"
                rows="6"
                maxlength="500"
                required
                placeholder="اكتب سبب رفض طلب التسجيل..."
            ></textarea>
        </div>

        <div class="form-actions">

            <a
                href="<?= e(
                    url(
                        'manager/registration-requests/view.php?request_id='
                        . $requestId
                    )
                ) ?>"
                class="button button-secondary"
            >
                إلغاء
            </a>

            <button
                type="submit"
                class="button button-danger"
            >
                تأكيد رفض الطلب
            </button>

        </div>

    </form>

</section>

<?php render_footer(); ?>