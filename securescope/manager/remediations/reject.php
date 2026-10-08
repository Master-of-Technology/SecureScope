<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_role('Security Manager');

$remediationId = filter_input(
    INPUT_GET,
    'remediation_id',
    FILTER_VALIDATE_INT
);

if (
    $remediationId === false
    || $remediationId === null
    || $remediationId <= 0
) {
    set_flash(
        'error',
        'معرف المعالجة غير صالح.'
    );
    redirect('manager/remediations/index.php');
}

$user = current_user();

if ($user === null) {
    logout_user();
    redirect('login.php');
}

$statement = db()->prepare(
    "SELECT
        r.remediation_id,
        r.status,
        r.description,
        f.title AS finding_title,
        c.company_name
     FROM remediations AS r
     INNER JOIN findings AS f
        ON f.finding_id = r.finding_id
     INNER JOIN clients AS c
        ON c.client_id = r.client_id
     WHERE r.remediation_id = :remediation_id
     LIMIT 1"
);

$statement->execute([
    'remediation_id' => $remediationId,
]);

$remediation = $statement->fetch();

if ($remediation === false) {
    set_flash(
        'error',
        'المعالجة غير موجودة.'
    );
    redirect('manager/remediations/index.php');
}

if ((string) $remediation['status'] !== 'submitted') {
    set_flash(
        'error',
        'لا يمكن رفض المعالجة إلا عندما تكون بانتظار التحقق.'
    );

    redirect(
        'manager/remediations/view.php?remediation_id='
        . $remediationId
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
            'يجب إدخال سبب رفض المعالجة.';
    }

    if (strlen($reason) > 1000) {
        $errors[] =
            'سبب الرفض يجب ألا يتجاوز 1000 حرف.';
    }

    if ($errors === []) {
        try {
            db()->beginTransaction();

            $updateStatement = db()->prepare(
                "UPDATE remediations
                 SET
                    status = 'rejected',
                    updated_at = NOW()
                 WHERE remediation_id = :remediation_id
                   AND status = 'submitted'"
            );

            $updateStatement->execute([
                'remediation_id' => $remediationId,
            ]);

            if ($updateStatement->rowCount() !== 1) {
                throw new RuntimeException(
                    'تعذر رفض المعالجة.'
                );
            }

            record_audit(
                'REJECT_REMEDIATION',
                'remediations',
                $remediationId,
                [
                    'status' => 'submitted',
                ],
                [
                    'status' => 'rejected',
                    'rejection_reason' => $reason,
                    'reviewed_by' => (int) $user['user_id'],
                ]
            );

            db()->commit();

            set_flash(
                'success',
                'تم رفض المعالجة وإعادتها للعميل.'
            );

            redirect(
                'manager/remediations/view.php?remediation_id='
                . $remediationId
            );
        } catch (Throwable $exception) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }

            error_log(
                'SecureScope remediation rejection error: '
                . $exception->getMessage()
            );

            $errors[] =
                'حدث خطأ أثناء رفض المعالجة. حاول مرة أخرى.';
        }
    }
}

render_header('رفض المعالجة');
?>

<section class="page-heading">
    <div>
        <p class="eyebrow">مدير الأمن</p>

        <h1>
            رفض المعالجة
        </h1>

        <p class="muted">
            اكتب سببًا واضحًا لإعادة المعالجة إلى العميل.
        </p>
    </div>

    <div class="page-actions">
        <a href="<?= e(
            url(
                'manager/remediations/view.php?remediation_id='
                . $remediationId
            )
        ) ?>" class="button button-primary">
            العودة إلى المعالجة
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
                <?= e(
                    (string) $remediation['finding_title']
                ) ?>
            </strong>
        </div>

        <div>
            <span class="detail-label">
                العميل
            </span>

            <strong>
                <?= e(
                    (string) $remediation['company_name']
                ) ?>
            </strong>
        </div>

    </div>

</section>

<section class="details-card">

    <?php if ($errors !== []): ?>

        <div class="alert alert-error" role="alert">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li>
                        <?= e($error) ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

    <?php endif; ?>

    <form method="post">

        <?= csrf_input() ?>

        <div>
            <label for="rejection_reason">
                سبب رفض المعالجة
            </label>

            <textarea id="rejection_reason" name="rejection_reason" rows="8" maxlength="1000" required
                placeholder="وضح للعميل سبب عدم قبول المعالجة وما الذي يجب تصحيحه..."><?= e($reason) ?></textarea>
        </div>

        <div class="form-actions">

            <a href="<?= e(
                url(
                    'manager/remediations/view.php?remediation_id='
                    . $remediationId
                )
            ) ?>" class="button button-secondary">
                إلغاء
            </a>

            <button type="submit" class="button button-danger">
                تأكيد رفض المعالجة
            </button>

        </div>

    </form>

</section>

<?php render_footer(); ?>