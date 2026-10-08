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
        <h1>حسابك غير مرتبط بشركة عميلة.</h1>
        <p class="muted">
            تواصل مع مدير الأمن لإكمال إعداد الحساب.
        </p>
    </section>
    <?php
    render_footer();
    exit;
}

$statement = db()->prepare(
    "SELECT
        f.finding_id,
        f.title,
        f.description,
        f.status,
        f.discovered_at,
        a.assessment_name,
        p.project_name,
        ass.asset_name,
        ass.asset_type,
        ass.identifier,
        rl.name AS risk_level_name,
        rl.severity_score,
        r.remediation_id,
        r.status AS remediation_status
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

$statusLabels = [
    'confirmed' => 'معتمدة',
    'remediation' => 'قيد المعالجة',
    'resolved' => 'تمت المعالجة',
    'verified' => 'تم التحقق',
    'closed' => 'مغلقة',
];

$remediationStatusLabels = [
    'pending' => 'بانتظار المعالجة',
    'in_progress' => 'قيد التنفيذ',
    'submitted' => 'بانتظار التحقق',
    'resolved' => 'تمت المعالجة',
    'verified' => 'تم التحقق',
    'rejected' => 'مرفوضة',
];

render_header('الثغرات الأمنية');
?>

<section class="page-heading">
    <div>
        <p class="eyebrow">
            بوابة العميل
        </p>

        <h1>
            الثغرات الأمنية
        </h1>

        <p class="muted">
            النتائج الأمنية التي تم اعتمادها من فريق SecureScope والخاصة بأنظمة شركتك.
        </p>
    </div>

    <div class="page-actions">
        <a href="<?= e(url('client/dashboard.php')) ?>" class="button button-primary">
            لوحة التحكم
        </a>
    </div>
</section>

<section class="details-card">

    <div class="section-heading compact">

        <div>
            <h2>
                النتائج الأمنية المعتمدة
            </h2>

            <p class="muted">
                يمكنك مراجعة تفاصيل كل Finding ومتابعة المعالجة المرتبطة بها.
            </p>
        </div>

    </div>

    <?php if ($findings === []): ?>

        <div class="empty-state">

            <h2>
                لا توجد ثغرات معتمدة
            </h2>

            <p class="muted">
                لا توجد حاليًا نتائج أمنية معتمدة مرتبطة بشركتك.
            </p>

        </div>

    <?php else: ?>

        <div class="table-wrapper">

            <table class="data-table">

                <thead>

                    <tr>
                        <th>الثغرة</th>
                        <th>المشروع</th>
                        <th>التقييم</th>
                        <th>الأصل</th>
                        <th>الخطورة</th>
                        <th>المعالجة</th>
                        <th>تاريخ الاكتشاف</th>
                        <th>فتح</th>
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
                                <strong>
                                    <?= e(
                                        (string) $finding['asset_name']
                                    ) ?>
                                </strong>

                                <small>
                                    <?= e(
                                        (string) $finding['asset_type']
                                    ) ?>
                                </small>
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
                                        لم تبدأ
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
                                <?= e(
                                    (string) $finding['discovered_at']
                                ) ?>
                            </td>

                            <td>

                                <a href="<?= e(
                                    url(
                                        'client/findings/view.php?finding_id='
                                        . $finding['finding_id']
                                    )
                                ) ?>" class="button button-small">
                                    فتح
                                </a>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    <?php endif; ?>

</section>

<?php render_footer(); ?>