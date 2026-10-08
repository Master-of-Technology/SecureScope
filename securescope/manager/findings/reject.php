<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_role('Security Manager');
$findingId = filter_input(
    INPUT_GET,
    'finding_id',
    FILTER_VALIDATE_INT
);
if (
    $findingId === false
    || $findingId === null
    || $findingId <= 0
) {
    set_flash(
        'error',
        'معرف Finding غير صالح.'
    );
    redirect('manager/findings/index.php');
}
$statement = db()->prepare(
    "SELECT
        f.finding_id,
        f.title,
        f.status,
        a.assessment_name,
        p.project_name,
        c.company_name,
        ass.asset_name,
        rl.name AS risk_level_name
     FROM findings AS f
     INNER JOIN assessments AS a
        ON a.assessment_id = f.assessment_id
     INNER JOIN projects AS p
        ON p.project_id = a.project_id
     INNER JOIN clients AS c
        ON c.client_id = p.client_id
     INNER JOIN assets AS ass
        ON ass.asset_id = f.asset_id
     INNER JOIN risk_levels AS rl
        ON rl.risk_level_id = f.risk_level_id
     WHERE f.finding_id = :finding_id
     LIMIT 1"
);
$statement->execute([
    'finding_id' => $findingId,
]);
$finding = $statement->fetch();
if ($finding === false) {
    set_flash(
        'error',
        'لم يتم العثور على Finding.'
    );
    redirect('manager/findings/index.php');
}
if ((string) $finding['status'] !== 'open') {
    set_flash(
        'error',
        'لا يمكن رفض Finding بعد مراجعتها.'
    );
    redirect(
        'manager/findings/view.php?finding_id='
        . $findingId
    );
}
$errors = [];
$reason = '';
if (is_post_request()) {
    require_valid_csrf();
    $reason = trim(
        (string) (
            $_POST['rejection_reason']
            ?? ''
        )
    );
    if ($reason === '') {
        $errors[] =
            'يجب إدخال سبب رفض Finding.';
    }
    if (strlen($reason) > 1000) {
        $errors[] =
            'سبب الرفض يجب ألا يتجاوز 1000 حرف.';
    }
    $user = current_user();
    $userId = $user !== null
        ? (int) $user['user_id']
        : 0;
    if ($userId <= 0) {
        $errors[] =
            'تعذر تحديد المستخدم الحالي.';
    }
    if ($errors === []) {
        try {
            db()->beginTransaction();
            $updateStatement = db()->prepare(
                "UPDATE findings
                 SET
                    status = 'rejected',
                    reviewed_by = :reviewed_by,
                    reviewed_at = NOW(),
                    updated_at = NOW()
                 WHERE finding_id = :finding_id
                   AND status = 'open'"
            );
            $updateStatement->execute([
                'reviewed_by' => $userId,
                'finding_id' => $findingId,
            ]);
            if ($updateStatement->rowCount() !== 1) {
                throw new RuntimeException(
                    'تعذر رفض Finding.'
                );
            }
            record_audit(
                'REJECT_FINDING',
                'findings',
                $findingId,
                [
                    'status' => 'open',
                ],
                [
                    'status' => 'rejected',
                    'reviewed_by' => $userId,
                    'rejection_reason' => $reason,
                ]
            );
            db()->commit();
            set_flash(
                'success',
                'تم رفض Finding بنجاح.'
            );
            redirect(
                'manager/findings/view.php?finding_id='
                . $findingId
            );
        } catch (Throwable $exception) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            error_log(
                'SecureScope finding rejection error: '
                . $exception->getMessage()
            );
            $errors[] =
                'حدث خطأ أثناء رفض Finding. حاول مرة أخرى.';
        }
    }
}
render_header('رفض Finding');
?>
<section class="page-heading">
    <div>
        <p class="eyebrow">
            إدارة الثغرات
        </p>
        <h1>
            رفض Finding
        </h1>
        <p class="muted">
            أدخل سببًا واضحًا لرفض النتيجة الأمنية.
        </p>
    </div>
    <div class="page-actions">
        <a
            href="<?= e(
                url(
                    'manager/findings/view.php?finding_id='
                    . $findingId
                )
            ) ?>"
            class="button button-primary"
        >
            العودة إلى Finding
        </a>
    </div>
</section>
<section class="details-card">
    <div class="detail-grid">
        <div>
            <span class="detail-label">
                Finding
            </span>
            <strong>
                <?= e((string) $finding['title']) ?>
            </strong>
        </div>
        <div>
            <span class="detail-label">
                التقييم
            </span>
            <strong>
                <?= e((string) $finding['assessment_name']) ?>
            </strong>
        </div>
        <div>
            <span class="detail-label">
                المشروع
            </span>
            <strong>
                <?= e((string) $finding['project_name']) ?>
            </strong>
        </div>
        <div>
            <span class="detail-label">
                العميل
            </span>
            <strong>
                <?= e((string) $finding['company_name']) ?>
            </strong>
        </div>
        <div>
            <span class="detail-label">
                الأصل
            </span>
            <strong>
                <?= e((string) $finding['asset_name']) ?>
            </strong>
        </div>
        <div>
            <span class="detail-label">
                مستوى الخطورة
            </span>
            <strong>
                <?= e((string) $finding['risk_level_name']) ?>
            </strong>
        </div>
    </div>
</section>
<section class="details-card">
    <?php if ($errors !== []): ?>
        <div
            class="alert alert-error"
            role="alert"
        >
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li>
                        <?= e($error) ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    <form method="post" class="form-stack">
        <?= csrf_input() ?>
        <div>
            <label for="rejection_reason">
                سبب رفض Finding
            </label>
            <textarea
                id="rejection_reason"
                name="rejection_reason"
                rows="8"
                maxlength="1000"
                required
                placeholder="اكتب سبب رفض النتيجة الأمنية ووضح ما الذي يجعلها غير قابلة للاعتماد."
            ><?= e($reason) ?></textarea>
            <small>
                الحد الأقصى 1000 حرف.
            </small>
        </div>
        <div class="form-actions">
            <a
                href="<?= e(
                    url(
                        'manager/findings/view.php?finding_id='
                        . $findingId
                    )
                ) ?>"
                class="button button-praimary"
            >
                إلغاء
            </a>
            <button
                type="submit"
                class="button button-danger"
            >
                تأكيد رفض Finding
            </button>
        </div>
    </form>
</section>
<?php render_footer(); ?>