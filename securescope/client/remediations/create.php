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
    http_response_code(403);

    render_header('إعداد حساب العميل');
    ?>

    <section class="empty-state">
        <p class="eyebrow">يلزم إعداد الحساب</p>

        <h1>
            حسابك غير مرتبط بشركة عميلة.
        </h1>

        <p class="muted">
            تواصل مع مدير الأمن لإكمال إعداد الحساب.
        </p>
    </section>

    <?php
    render_footer();
    exit;
}

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
        'معرف الثغرة غير صالح.'
    );

    redirect('client/findings/index.php');
}

/*
 * جلب Finding مع التأكد من:
 * 1. أنها تخص شركة العميل.
 * 2. أنها معتمدة من SecureScope.
 * 3. لا توجد لها معالجة سابقة.
 */
$statement = db()->prepare(
    "SELECT
        f.finding_id,
        f.title,
        f.description,
        f.technical_details,
        f.status AS finding_status,
        f.discovered_at,

        a.assessment_name,

        p.project_name,

        ass.asset_name,
        ass.asset_type,
        ass.identifier,

        rl.name AS risk_level_name,
        rl.severity_score,

        r.remediation_id

     FROM findings AS f

     INNER JOIN assessments AS a
        ON a.assessment_id = f.assessment_id

     INNER JOIN projects AS p
        ON p.project_id = a.project_id

     INNER JOIN assets AS ass
        ON ass.asset_id = f.asset_id

     INNER JOIN risk_levels AS rl
        ON rl.risk_level_id = f.risk_level_id

     LEFT JOIN remediations AS r
        ON r.finding_id = f.finding_id

     WHERE f.finding_id = :finding_id
       AND f.status = 'confirmed'
       AND p.client_id = :client_id

     LIMIT 1"
);

$statement->execute([
    'finding_id' => $findingId,
    'client_id' => $clientId,
]);

$finding = $statement->fetch();

if ($finding === false) {
    set_flash(
        'error',
        'الثغرة غير موجودة أو غير معتمدة أو لا تخص شركتك.'
    );

    redirect('client/findings/index.php');
}

/*
 * بما أن finding_id فريد في remediations،
 * لا يمكن إنشاء معالجة ثانية لنفس Finding.
 */
if ($finding['remediation_id'] !== null) {
    set_flash(
        'error',
        'تم تسجيل معالجة لهذه الثغرة مسبقًا.'
    );

    redirect(
        'client/remediations/view.php?remediation_id='
        . (int) $finding['remediation_id']
    );
}

$errors = [];

$description = '';

/*
 * إنشاء طلب المعالجة.
 *
 * العميل هنا لا يقوم بإصلاح النظام من داخل SecureScope.
 * هو فقط يصف ما قام به فريقه لمعالجة Finding
 * ثم يرسلها لإعادة الاختبار.
 */
if (is_post_request()) {

    require_valid_csrf();

    $description = trim(
        (string) (
            $_POST['description']
            ?? ''
        )
    );

    if ($description === '') {
        $errors[] =
            'يجب وصف الإجراء الذي تم تنفيذه لمعالجة الثغرة.';
    }

    if (strlen($description) > 10000) {
        $errors[] =
            'وصف المعالجة طويل جدًا.';
    }

    if ($errors === []) {

        try {

            db()->beginTransaction();

            /*
             * نتحقق مرة أخرى داخل العملية
             * من عدم وجود معالجة سابقة.
             */
            $checkStatement = db()->prepare(
                "SELECT remediation_id
                 FROM remediations
                 WHERE finding_id = :finding_id
                 LIMIT 1"
            );

            $checkStatement->execute([
                'finding_id' => $findingId,
            ]);

            $existingRemediation =
                $checkStatement->fetchColumn();

            if ($existingRemediation !== false) {

                db()->rollBack();

                set_flash(
                    'error',
                    'تم تسجيل معالجة لهذه الثغرة مسبقًا.'
                );

                redirect(
                    'client/remediations/view.php?remediation_id='
                    . (int) $existingRemediation
                );
            }

            /*
             * بما أن العميل يبلغ عن أن الإصلاح تم،
             * يتم إرسال الطلب مباشرة للمراجعة / إعادة الاختبار.
             */
            $insertStatement = db()->prepare(
                "INSERT INTO remediations (
        finding_id,
        client_id,
        description,
        status
     ) VALUES (
        :finding_id,
        :client_id,
        :description,
        'pending'
     )"
            );

            $insertStatement->execute([
                'finding_id' => $findingId,
                'client_id' => $clientId,
                'description' => $description,
            ]);

            $remediationId =
                (int) db()->lastInsertId();

            record_audit(
                'SUBMIT_REMEDIATION',
                'remediations',
                $remediationId,
                [],
                [
                    'finding_id' => $findingId,
                    'client_id' => $clientId,
                    'status' => 'submitted',
                ]
            );

            db()->commit();

            set_flash(
                'success',
                'تم تسجيل المعالجة. بعد تنفيذ الإصلاح يمكن إرسال طلب إعادة الاختبار.'
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
                'SecureScope remediation creation error: '
                . $exception->getMessage()
            );

            $errors[] =
                'حدث خطأ أثناء تسجيل المعالجة. حاول مرة أخرى.';
        }
    }
}

render_header('الإبلاغ عن معالجة');
?>

<section class="page-heading">

    <div>

        <p class="eyebrow">
            بوابة العميل
        </p>

        <h1>
            الإبلاغ عن معالجة الثغرة
        </h1>

        <p class="muted">
            أخبر فريق SecureScope بما قام فريقك بتنفيذه لمعالجة هذه الثغرة.
        </p>

    </div>

    <div class="page-actions">

        <a href="<?= e(
            url(
                'client/findings/view.php?finding_id='
                . $findingId
            )
        ) ?>" class="button button-primary">
            العودة إلى الثغرة
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
                الثغرة الأمنية
            </h2>

            <p class="muted">
                هذه المعلومات للمرجعية ولا يمكن تعديلها من قبل العميل.
            </p>

        </div>

    </div>


    <div class="detail-grid">

        <div>

            <span class="detail-label">
                الثغرة
            </span>

            <strong>
                <?= e(
                    (string) $finding['title']
                ) ?>
            </strong>

        </div>


        <div>

            <span class="detail-label">
                مستوى الخطورة
            </span>

            <strong>
                <?= e(
                    (string) $finding['risk_level_name']
                ) ?>

                —

                <?= e(
                    (string) $finding['severity_score']
                ) ?>/5
            </strong>

        </div>


        <div>

            <span class="detail-label">
                المشروع
            </span>

            <strong>
                <?= e(
                    (string) $finding['project_name']
                ) ?>
            </strong>

        </div>


        <div>

            <span class="detail-label">
                التقييم
            </span>

            <strong>
                <?= e(
                    (string) $finding['assessment_name']
                ) ?>
            </strong>

        </div>


        <div>

            <span class="detail-label">
                الأصل المتأثر
            </span>

            <strong>
                <?= e(
                    (string) $finding['asset_name']
                ) ?>
            </strong>

        </div>


        <div>

            <span class="detail-label">
                نوع الأصل
            </span>

            <strong>
                <?= e(
                    (string) $finding['asset_type']
                ) ?>
            </strong>

        </div>

    </div>

</section>


<section class="details-card">

    <div class="section-heading compact">

        <div>

            <h2>
                وصف الثغرة
            </h2>

        </div>

    </div>

    <div class="detail-section">

        <p>
            <?= nl2br(
                e(
                    (string) (
                        $finding['description']
                        ?? 'لا يوجد وصف.'
                    )
                )
            ) ?>
        </p>

    </div>

    <?php if (!empty($finding['technical_details'])): ?>

        <div class="detail-section">

            <h3>
                التفاصيل الفنية
            </h3>

            <p>
                <?= nl2br(
                    e(
                        (string) $finding['technical_details']
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
                الإبلاغ عن الإصلاح
            </h2>

            <p class="muted">
                اكتب ما قام فريق تقنية المعلومات أو التطوير لدى شركتك
                بتنفيذه لمعالجة الثغرة.
            </p>

        </div>

    </div>


    <div class="alert alert-info">

        <strong>
            ملاحظة:
        </strong>

        SecureScope لا تقوم بتعديل نظام شركتك من خلال هذه الصفحة.
        بعد إرسال البلاغ سيقوم فريق SecureScope بإعادة اختبار الثغرة
        للتحقق من نجاح المعالجة.

    </div>


    <form method="post">

        <?= csrf_input() ?>


        <div class="form-group">

            <label for="description">
                ما الذي تم تنفيذه لمعالجة الثغرة؟
            </label>

            <textarea id="description" name="description" rows="8" maxlength="10000" required
                placeholder="اذكر باختصار ما قام فريقكم بتنفيذه لمعالجة الثغرة..."><?= e($description) ?></textarea>

        </div>


        <div class="form-actions">

            <a href="<?= e(
                url(
                    'client/findings/view.php?finding_id='
                    . $findingId
                )
            ) ?>" class="button button-primary">
                إلغاء
            </a>


            <button type="submit" class="button button-primary">
               تسجيل المعالجة
            </button>

        </div>

    </form>

</section>

<?php render_footer(); ?>