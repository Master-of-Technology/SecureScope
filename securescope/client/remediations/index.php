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

$statement = db()->prepare(
    "SELECT
        f.finding_id,
        f.title,
        f.status AS finding_status,
        f.discovered_at,

        rl.name AS risk_level_name,
        rl.severity_score,

        ass.asset_name,

        p.project_name,

        a.assessment_name,

        r.remediation_id,
        r.description AS remediation_description,
        r.status AS remediation_status,
        r.submitted_at,
        r.resolved_at,
        r.verified_at

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
       AND r.client_id = :remediation_client_id

     WHERE f.status = 'confirmed'
       AND p.client_id = :client_id

     ORDER BY f.discovered_at DESC"
);

$statement->execute([
    'remediation_client_id' => $clientId,
    'client_id' => $clientId,
]);

$findings = $statement->fetchAll();

$remediationStatusLabels = [
    'pending' => 'بانتظار الإجراء',
    'in_progress' => 'قيد المعالجة',
    'submitted' => 'بانتظار إعادة الاختبار',
    'resolved' => 'تمت المعالجة',
    'verified' => 'تم التحقق',
    'rejected' => 'تحتاج إلى إجراء إضافي',
];

render_header('متابعة معالجة الثغرات');
?>

<section class="page-heading">

    <div>

        <p class="eyebrow">
            بوابة العميل
        </p>

        <h1>
            متابعة معالجة الثغرات
        </h1>

        <p class="muted">
            تابع حالة معالجة الثغرات الأمنية وطلبات إعادة الاختبار.
        </p>

    </div>

</section>


<section class="details-card">

    <div class="section-heading compact">

        <div>

            <h2>
                الثغرات الأمنية المعتمدة
            </h2>

            <p class="muted">
                تظهر هنا الثغرات التي اعتمدها فريق SecureScope
                ويمكن لفريق شركتك معالجتها.
            </p>

        </div>

    </div>


    <?php if ($findings === []): ?>

        <div class="empty-state">

            <h2>
                لا توجد ثغرات معتمدة
            </h2>

            <p class="muted">
                لا توجد حاليًا ثغرات أمنية معتمدة مرتبطة بشركتك.
            </p>

        </div>

    <?php else: ?>

        <div class="table-wrapper">

            <table class="data-table">

                <thead>

                    <tr>

                        <th>
                            Finding
                        </th>

                        <th>
                            المشروع
                        </th>

                        <th>
                            التقييم
                        </th>

                        <th>
                            الأصل
                        </th>

                        <th>
                            الخطورة
                        </th>

                        <th>
                            حالة المعالجة
                        </th>

                        <th>
                            الإجراء
                        </th>

                    </tr>

                </thead>


                <tbody>

                    <?php foreach ($findings as $finding): ?>

                        <tr>

                            <td>

                                <strong>
                                    <?= e(
                                        (string) $finding['title']
                                    ) ?>
                                </strong>

                            </td>


                            <td>

                                <?= e(
                                    (string) $finding['project_name']
                                ) ?>

                            </td>


                            <td>

                                <?= e(
                                    (string) $finding['assessment_name']
                                ) ?>

                            </td>


                            <td>

                                <?= e(
                                    (string) $finding['asset_name']
                                ) ?>

                            </td>


                            <td>

                                <span class="status-badge status-<?= e(
                                    strtolower(
                                        (string) $finding['risk_level_name']
                                    )
                                ) ?>">

                                    <?= e(
                                        (string) $finding['risk_level_name']
                                    ) ?>

                                    —

                                    <?= e(
                                        (string) $finding['severity_score']
                                    ) ?>/5

                                </span>

                            </td>


                            <td>

                                <?php if ($finding['remediation_id'] === null): ?>

                                    <span class="status-badge status-pending">
                                        لم يتم الإبلاغ عن المعالجة
                                    </span>

                                <?php else: ?>

                                    <span class="status-badge status-<?= e(
                                        (string) $finding['remediation_status']
                                    ) ?>">

                                        <?= e(
                                            $remediationStatusLabels[
                                                (string) $finding['remediation_status']
                                            ]
                                            ?? (string) $finding['remediation_status']
                                        ) ?>

                                    </span>

                                <?php endif; ?>

                            </td>


                            <td>

                                <?php if ($finding['remediation_id'] === null): ?>

                                    <a href="<?= e(
                                        url(
                                            'client/remediations/create.php?finding_id='
                                            . $finding['finding_id']
                                        )
                                    ) ?>" class="button button-small button-primary">
                                        الإبلاغ عن المعالجة
                                    </a>


                                <?php elseif (
                                    $finding['remediation_status'] === 'rejected'
                                ): ?>

                                    <a href="<?= e(
                                        url(
                                            'client/remediations/view.php?remediation_id='
                                            . $finding['remediation_id']
                                        )
                                    ) ?>" class="button button-small button-primary">
                                        إعادة إرسال
                                    </a>


                                <?php else: ?>

                                    <a href="<?= e(
                                        url(
                                            'client/remediations/view.php?remediation_id='
                                            . $finding['remediation_id']
                                        )
                                    ) ?>" class="button button-small">
                                        متابعة
                                    </a>

                                <?php endif; ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    <?php endif; ?>

</section>


<section class="details-card">

    <div class="section-heading compact">

        <div>

            <h2>
                كيف تتم المعالجة؟
            </h2>

            <p class="muted">
                تنفيذ الإصلاح يتم من قبل فريق شركتك خارج SecureScope،
                ثم يتم إرسال طلب إعادة الاختبار إلى فريق SecureScope.
            </p>

        </div>

    </div>


    <div class="detail-grid">

        <div>

            <span class="detail-label">
                1. التقرير
            </span>

            <strong>
                تستلم شركتك Finding والتوصية الأمنية.
            </strong>

        </div>


        <div>

            <span class="detail-label">
                2. الإصلاح
            </span>

            <strong>
                يقوم فريق شركتك بإصلاح النظام أو البرنامج.
            </strong>

        </div>


        <div>

            <span class="detail-label">
                3. الإبلاغ
            </span>

            <strong>
                يتم تسجيل ما تم تنفيذه في SecureScope.
            </strong>

        </div>


        <div>

            <span class="detail-label">
                4. إعادة الاختبار
            </span>

            <strong>
                يقوم فريق SecureScope بإعادة فحص الثغرة.
            </strong>

        </div>

    </div>

</section>


<?php render_footer(); ?>