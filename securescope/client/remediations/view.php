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
    set_flash(
        'error',
        'لم يتم ربط حساب العميل بشركة.'
    );

    redirect('client/index.php');
}

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

    redirect('client/remediations/index.php');
}

$statement = db()->prepare(
    "SELECT
        r.*,

        f.title AS finding_title,
        f.description AS finding_description,
        f.technical_details,
        f.status AS finding_status,

        rl.name AS risk_level_name,
        rl.severity_score,

        ass.asset_name,
        ass.asset_type,
        ass.identifier,

        a.assessment_name,

        p.project_name,

        c.company_name

     FROM remediations AS r

     INNER JOIN findings AS f
        ON f.finding_id = r.finding_id

     INNER JOIN risk_levels AS rl
        ON rl.risk_level_id = f.risk_level_id

     INNER JOIN assessments AS a
        ON a.assessment_id = f.assessment_id

     INNER JOIN projects AS p
        ON p.project_id = a.project_id

     INNER JOIN assets AS ass
        ON ass.asset_id = f.asset_id

     INNER JOIN clients AS c
        ON c.client_id = r.client_id

     WHERE r.remediation_id = :remediation_id
       AND r.client_id = :client_id

     LIMIT 1"
);

$statement->execute([
    'remediation_id' => $remediationId,
    'client_id' => $clientId,
]);

$remediation = $statement->fetch();

if ($remediation === false) {
    set_flash(
        'error',
        'المعالجة غير موجودة أو لا تخص شركتك.'
    );

    redirect('client/remediations/index.php');
}

$statusLabels = [
    'pending' => 'بانتظار الإجراء',
    'in_progress' => 'قيد المعالجة',
    'submitted' => 'بانتظار إعادة الاختبار',
    'resolved' => 'تمت المعالجة',
    'verified' => 'تم التحقق',
    'rejected' => 'تحتاج إلى إجراء إضافي',
];

$errors = [];

/*
 * إجراءات العميل:
 *
 * 1. submit_for_retest
 *    بعد أن يقوم فريق العميل بإصلاح الثغرة خارج SecureScope،
 *    يرسل طلب إعادة الاختبار.
 *
 * 2. resubmit_remediation
 *    إذا تم رفض الطلب، يستطيع العميل تحديث وصف الإصلاح
 *    وإعادة إرسال الطلب.
 */
if (is_post_request()) {

    require_valid_csrf();

    $action = trim(
        (string) ($_POST['action'] ?? '')
    );


    /*
     * إرسال طلب إعادة الاختبار لأول مرة.
     */
    if ($action === 'submit_for_retest') {

        if (
            (string) $remediation['status'] !== 'pending'
            && (string) $remediation['status'] !== 'in_progress'
        ) {
            $errors[] =
                'لا يمكن إرسال هذه المعالجة لإعادة الاختبار في حالتها الحالية.';
        }

        if ($errors === []) {

            try {

                db()->beginTransaction();

                $updateStatement = db()->prepare(
                    "UPDATE remediations
                     SET
                        status = 'submitted',
                        submitted_at = NOW(),
                        updated_at = NOW()
                     WHERE remediation_id = :remediation_id
                       AND client_id = :client_id
                       AND status IN ('pending', 'in_progress')"
                );

                $updateStatement->execute([
                    'remediation_id' => $remediationId,
                    'client_id' => $clientId,
                ]);

                if ($updateStatement->rowCount() !== 1) {
                    throw new RuntimeException(
                        'تعذر إرسال طلب إعادة الاختبار.'
                    );
                }

                record_audit(
                    'SUBMIT_REMEDIATION_FOR_RETEST',
                    'remediations',
                    $remediationId,
                    [
                        'status' => $remediation['status'],
                    ],
                    [
                        'status' => 'submitted',
                        'client_id' => $clientId,
                    ]
                );

                db()->commit();

                set_flash(
                    'success',
                    'تم إرسال طلب إعادة الاختبار إلى فريق SecureScope.'
                );

                redirect(
                    'client/remediations/view.php?remediation_id='
                    . $remediationId
                );

            } catch (Throwable $exception) {

                if (db()->inTransaction()) {
                    db()->rollBack();
                }

                error_log(
                    'SecureScope retest submission error: '
                    . $exception->getMessage()
                );

                $errors[] =
                    'حدث خطأ أثناء إرسال طلب إعادة الاختبار.';
            }
        }
    }


    /*
     * إعادة إرسال الطلب بعد رفضه.
     */ elseif ($action === 'resubmit_remediation') {

        if ((string) $remediation['status'] !== 'rejected') {

            $errors[] =
                'لا يمكن إعادة إرسال المعالجة إلا بعد رفضها.';
        }

        $description = trim(
            (string) (
                $_POST['description']
                ?? ''
            )
        );

        if ($description === '') {

            $errors[] =
                'يجب وصف ما قام فريقكم بتنفيذه لمعالجة الثغرة.';
        }

        if (strlen($description) > 10000) {

            $errors[] =
                'وصف المعالجة طويل جدًا.';
        }

        if ($errors === []) {

            try {

                db()->beginTransaction();

                $updateStatement = db()->prepare(
                    "UPDATE remediations
                     SET
                        description = :description,
                        status = 'submitted',
                        submitted_at = NOW(),
                        updated_at = NOW()
                     WHERE remediation_id = :remediation_id
                       AND client_id = :client_id
                       AND status = 'rejected'"
                );

                $updateStatement->execute([
                    'description' => $description,
                    'remediation_id' => $remediationId,
                    'client_id' => $clientId,
                ]);

                if ($updateStatement->rowCount() !== 1) {

                    throw new RuntimeException(
                        'تعذر إعادة إرسال المعالجة.'
                    );
                }

                record_audit(
                    'RESUBMIT_REMEDIATION',
                    'remediations',
                    $remediationId,
                    [
                        'status' => 'rejected',
                    ],
                    [
                        'status' => 'submitted',
                        'client_id' => $clientId,
                    ]
                );

                db()->commit();

                set_flash(
                    'success',
                    'تم إعادة إرسال طلب المعالجة لإعادة الاختبار.'
                );

                redirect(
                    'client/remediations/view.php?remediation_id='
                    . $remediationId
                );

            } catch (Throwable $exception) {

                if (db()->inTransaction()) {
                    db()->rollBack();
                }

                error_log(
                    'SecureScope remediation resubmission error: '
                    . $exception->getMessage()
                );

                $errors[] =
                    'حدث خطأ أثناء إعادة إرسال المعالجة.';
            }
        }
    } else {

        $errors[] =
            'الإجراء المطلوب غير صالح.';
    }
}

render_header('متابعة معالجة الثغرة');
?>


<section class="page-heading">

    <div>

        <p class="eyebrow">
            بوابة العميل
        </p>

        <h1>
            متابعة معالجة الثغرة
        </h1>

        <p class="muted">
            متابعة حالة معالجة الثغرة وطلب إعادة اختبارها من فريق SecureScope.
        </p>

    </div>

    <div class="page-actions">

        <a href="<?= e(url('client/remediations/index.php')) ?>" class="button button-primary">
            العودة إلى المعالجات
        </a>

    </div>

</section>


<?php if ($errors !== []): ?>

    <section class="details-card">

        <div class="alert alert-error" role="alert">

            <ul>

                <?php foreach ($errors as $error): ?>

                    <li>
                        <?= e($error) ?>
                    </li>

                <?php endforeach; ?>

            </ul>

        </div>

    </section>

<?php endif; ?>


<section class="details-card">

    <div class="section-heading compact">

        <div>

            <h2>
                حالة الطلب
            </h2>

        </div>

        <span class="status-badge status-<?= e(
            (string) $remediation['status']
        ) ?>">

            <?= e(
                $statusLabels[
                    (string) $remediation['status']
                ]
                ?? (string) $remediation['status']
            ) ?>

        </span>

    </div>


    <div class="detail-grid">

        <div>

            <span class="detail-label">
                رقم الطلب
            </span>

            <strong>
                #<?= e(
                    (string) $remediation['remediation_id']
                ) ?>
            </strong>

        </div>


        <div>

            <span class="detail-label">
                تاريخ التسجيل
            </span>

            <strong>
                <?= e(
                    (string) $remediation['created_at']
                ) ?>
            </strong>

        </div>


        <div>

            <span class="detail-label">
                تاريخ إرسال طلب إعادة الاختبار
            </span>

            <strong>
                <?= e(
                    (string) (
                        $remediation['submitted_at']
                        ?? '—'
                    )
                ) ?>
            </strong>

        </div>


        <div>

            <span class="detail-label">
                تاريخ التحقق
            </span>

            <strong>
                <?= e(
                    (string) (
                        $remediation['verified_at']
                        ?? '—'
                    )
                ) ?>
            </strong>

        </div>

    </div>

</section>


<section class="details-card">

    <div class="section-heading compact">

        <div>

            <h2>
                الثغرة الأمنية
            </h2>

            <p class="muted">
                الثغرة التي تم الإبلاغ عن معالجتها.
            </p>

        </div>

    </div>


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
                مستوى الخطورة
            </span>

            <strong>
                <?= e(
                    (string) $remediation['risk_level_name']
                ) ?>

                —

                <?= e(
                    (string) $remediation['severity_score']
                ) ?>/5
            </strong>

        </div>


        <div>

            <span class="detail-label">
                المشروع
            </span>

            <strong>
                <?= e(
                    (string) $remediation['project_name']
                ) ?>
            </strong>

        </div>


        <div>

            <span class="detail-label">
                الأصل
            </span>

            <strong>
                <?= e(
                    (string) $remediation['asset_name']
                ) ?>
            </strong>

        </div>

    </div>


    <div class="detail-section">

        <h3>
            وصف الثغرة
        </h3>

        <p>
            <?= nl2br(
                e(
                    (string) $remediation['finding_description']
                )
            ) ?>
        </p>

    </div>


    <?php if (!empty($remediation['technical_details'])): ?>

        <div class="detail-section">

            <h3>
                التفاصيل الفنية
            </h3>

            <p>
                <?= nl2br(
                    e(
                        (string) $remediation['technical_details']
                    )
                ) ?>
            </p>

        </div>

    <?php endif; ?>

</section>


<section class="details-card">

    <div class="section-heading compact">

        <div>

            <h2>
                ما قام فريقكم بتنفيذه
            </h2>

            <p class="muted">
                وصف الإجراء الذي تم تنفيذه لمعالجة الثغرة خارج SecureScope.
            </p>

        </div>

    </div>


    <div class="detail-section">

        <?php if (
            trim(
                (string) $remediation['description']
            ) !== ''
        ): ?>

            <p>
                <?= nl2br(
                    e(
                        (string) $remediation['description']
                    )
                ) ?>
            </p>

        <?php else: ?>

            <p class="muted">
                لم يتم تسجيل وصف للمعالجة بعد.
            </p>

        <?php endif; ?>

    </div>

</section>


<section class="details-card">

    <div class="section-heading compact">

        <div>

            <h2>
                الخطوة التالية
            </h2>

        </div>

    </div>


    <?php if ($remediation['status'] === 'pending'): ?>

        <div class="alert alert-info">

            تم تسجيل المعالجة.

            بعد أن يقوم فريق شركتك بإصلاح الثغرة في النظام،
            اضغط على الزر أدناه لإرسال طلب إعادة الاختبار إلى SecureScope.

        </div>


        <form method="post">

            <?= csrf_input() ?>

            <input type="hidden" name="action" value="submit_for_retest">

            <div class="form-actions">

                <button type="submit" class="button button-primary">
                    تمت معالجة الثغرة — طلب إعادة الاختبار
                </button>

            </div>

        </form>


    <?php elseif ($remediation['status'] === 'in_progress'): ?>

        <div class="alert alert-info">

            يقوم فريق شركتك حاليًا بإصلاح الثغرة خارج SecureScope.

            بعد الانتهاء اضغط على زر طلب إعادة الاختبار.

        </div>


        <form method="post">

            <?= csrf_input() ?>

            <input type="hidden" name="action" value="submit_for_retest">

            <div class="form-actions">

                <button type="submit" class="button button-primary">
                    تمت معالجة الثغرة — طلب إعادة الاختبار
                </button>

            </div>

        </form>


    <?php elseif ($remediation['status'] === 'submitted'): ?>

        <div class="alert alert-info">

            تم استلام طلب إعادة الاختبار.

            سيقوم فريق SecureScope بإعادة فحص النظام للتحقق
            من نجاح معالجة الثغرة.

        </div>


    <?php elseif ($remediation['status'] === 'resolved'): ?>

        <div class="alert alert-success">

            أظهرت إعادة الاختبار أن الثغرة تمت معالجتها.

            النتيجة بانتظار الاعتماد النهائي من فريق SecureScope.

        </div>


    <?php elseif ($remediation['status'] === 'verified'): ?>

        <div class="alert alert-success">

            تم إعادة اختبار الثغرة والتحقق من نجاح المعالجة
            واعتماد النتيجة من فريق SecureScope.

        </div>


    <?php elseif ($remediation['status'] === 'rejected'): ?>

        <div class="alert alert-error">

            لم يتم اعتماد المعالجة الحالية.

            يجب على فريق شركتك إجراء التعديلات المطلوبة على النظام
            ثم تحديث بيانات المعالجة وإعادة طلب الاختبار.

        </div>


        <div class="detail-section">

            <h3>
                إعادة إرسال الطلب
            </h3>

            <p class="muted">
                حدّث وصف ما قام به فريق شركتك ثم أرسل الطلب مرة أخرى.
            </p>

        </div>


        <form method="post">

            <?= csrf_input() ?>

            <input type="hidden" name="action" value="resubmit_remediation">


            <div class="form-group">

                <label for="description">
                    وصف الإصلاح
                </label>

                <textarea id="description" name="description" rows="8" maxlength="10000" required><?= e(
                    (string) $remediation['description']
                ) ?></textarea>

            </div>


            <div class="form-actions">

                <button type="submit" class="button button-primary">
                    إعادة إرسال طلب الاختبار
                </button>

            </div>

        </form>

    <?php endif; ?>

</section>


<?php render_footer(); ?>