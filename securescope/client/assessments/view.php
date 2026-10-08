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
$assessmentId = filter_input(
    INPUT_GET,
    'assessment_id',
    FILTER_VALIDATE_INT
);
if (
    $assessmentId === false
    || $assessmentId === null
    || $assessmentId <= 0
) {
    set_flash('error', 'معرف التقييم غير صالح.');
    redirect('client/assessments/index.php');
}
$statement = db()->prepare(
    "SELECT
        a.assessment_id,
        a.assessment_name,
        a.description AS assessment_description,
        a.status,
        a.started_at,
        a.completed_at,
        a.created_at,
        a.project_id,
        p.project_name,
        p.description AS project_description,
        p.status AS project_status,
        at.name AS assessment_type_name,
        CONCAT(u.first_name, ' ', u.last_name) AS analyst_name
     FROM assessments AS a
     INNER JOIN projects AS p
        ON p.project_id = a.project_id
     LEFT JOIN assessment_types AS at
        ON at.assessment_type_id = p.assessment_type_id
     LEFT JOIN users AS u
        ON u.user_id = a.assigned_to
     WHERE a.assessment_id = :assessment_id
       AND p.client_id = :client_id
     LIMIT 1"
);
$statement->execute([
    'assessment_id' => $assessmentId,
    'client_id' => $clientId,
]);
$assessment = $statement->fetch();
if ($assessment === false) {
    set_flash(
        'error',
        'التقييم غير موجود أو لا تملك صلاحية الوصول إليه.'
    );
    redirect('client/assessments/index.php');
}
$findingStatement = db()->prepare(
    "SELECT
        f.finding_id,
        f.title,
        f.status,
        f.discovered_at,
        rl.name AS risk_level_name,
        rl.severity_score,
        ass.asset_name
     FROM findings AS f
     INNER JOIN risk_levels AS rl
        ON rl.risk_level_id = f.risk_level_id
     INNER JOIN assets AS ass
        ON ass.asset_id = f.asset_id
     WHERE f.assessment_id = :assessment_id
       AND f.status IN (
           'confirmed',
           'reported',
           'remediation',
           'resolved',
           'verified'
       )
     ORDER BY
        rl.severity_score DESC,
        f.discovered_at DESC"
);
$findingStatement->execute([
    'assessment_id' => $assessmentId,
]);
$findings = $findingStatement->fetchAll();
$statusLabels = [
    'pending' => 'بانتظار البدء',
    'in_progress' => 'قيد التنفيذ',
    'under_review' => 'قيد المراجعة',
    'completed' => 'مكتمل',
    'cancelled' => 'ملغى',
];
$findingStatusLabels = [
    'confirmed' => 'مؤكدة',
    'reported' => 'تم الإبلاغ عنها',
    'remediation' => 'قيد المعالجة',
    'resolved' => 'تم حلها',
    'verified' => 'تم التحقق منها',
];
render_header('تفاصيل التقييم الأمني');
?>
<section class="page-heading">
    <div>
        <p class="eyebrow">بوابة العميل</p>
        <h1><?= e((string) $assessment['assessment_name']) ?></h1>
        <p class="muted">
            تفاصيل التقييم الأمني والنتائج المعتمدة المرتبطة به.
        </p>
    </div>
    <div class="page-actions">
        <a
            href="<?= e(url('client/assessments/index.php')) ?>"
            class="button button-primary"
        >
            العودة إلى التقييمات
        </a>
    </div>
</section>
<section class="details-card">
    <div class="section-heading compact">
        <div>
            <h2>معلومات التقييم</h2>
        </div>
        <span class="status-badge status-<?= e((string) $assessment['status']) ?>">
            <?= e(
                $statusLabels[(string) $assessment['status']]
                ?? (string) $assessment['status']
            ) ?>
        </span>
    </div>
    <div class="detail-grid">
        <div>
            <span class="detail-label">التقييم</span>
            <strong>
                <?= e((string) $assessment['assessment_name']) ?>
            </strong>
        </div>
        <div>
            <span class="detail-label">المشروع</span>
            <strong>
                <?= e((string) $assessment['project_name']) ?>
            </strong>
        </div>
        <div>
            <span class="detail-label">نوع التقييم</span>
            <strong>
                <?= e(
                    (string) (
                        $assessment['assessment_type_name']
                        ?? '—'
                    )
                ) ?>
            </strong>
        </div>
        <div>
            <span class="detail-label">المحلل المسؤول</span>
            <strong>
                <?= e(
                    (string) (
                        $assessment['analyst_name']
                        ?? 'غير مسند'
                    )
                ) ?>
            </strong>
        </div>
        <div>
            <span class="detail-label">تاريخ الإنشاء</span>
            <strong>
                <?= e((string) $assessment['created_at']) ?>
            </strong>
        </div>
        <div>
            <span class="detail-label">حالة المشروع</span>
            <strong>
                <?= e((string) $assessment['project_status']) ?>
            </strong>
        </div>
    </div>
</section>
<section class="details-card">
    <div class="section-heading compact">
        <div>
            <h2>وصف المشروع</h2>
        </div>
    </div>
    <div class="detail-section">
        <p>
            <?= nl2br(
                e(
                    (string) (
                        $assessment['project_description']
                        ?? 'لا يوجد وصف.'
                    )
                )
            ) ?>
        </p>
    </div>
</section>
<section class="details-card">
    <div class="section-heading compact">
        <div>
            <h2>النتائج الأمنية</h2>
            <p class="muted">
                النتائج التي اعتمدها فريق SecureScope لهذا التقييم.
            </p>
        </div>
    </div>
    <?php if ($findings === []): ?>
            <div class="empty-state">
                <h3>لا توجد نتائج معتمدة</h3>
                <p class="muted">
                    لم يتم اعتماد أي نتائج أمنية متاحة للعميل ضمن هذا التقييم.
                </p>
            </div>
    <?php else: ?>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Finding</th>
                            <th>الأصل</th>
                            <th>الخطورة</th>
                            <th>الحالة</th>
                            <th>تاريخ الاكتشاف</th>
                            <th>فتح</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($findings as $finding): ?>
                                <tr>
                                    <td>
                                        <strong>
                                            <?= e((string) $finding['title']) ?>
                                        </strong>
                                    </td>
                                    <td>
                                        <?= e((string) $finding['asset_name']) ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?= e(
                                            strtolower(
                                                (string) $finding['risk_level_name']
                                            )
                                        ) ?>">
                                            <?= e((string) $finding['risk_level_name']) ?>
                                            —
                                            <?= e((string) $finding['severity_score']) ?>/5
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?= e((string) $finding['status']) ?>">
                                            <?= e(
                                                $findingStatusLabels[(string) $finding['status']]
                                                ?? (string) $finding['status']
                                            ) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?= e((string) $finding['discovered_at']) ?>
                                    </td>
                                    <td>
                                        <a
                                            href="<?= e(
                                                url(
                                                    'client/findings/view.php?finding_id='
                                                    . (int) $finding['finding_id']
                                                )
                                            ) ?>"
                                            class="button button-small"
                                        >
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