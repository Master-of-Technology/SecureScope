<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_role('Security Analyst');
$user = current_user();
if ($user === null) {
    logout_user();
    redirect('login.php');
}
$statement = db()->prepare(
    "SELECT
        f.finding_id,
        f.assessment_id,
        f.asset_id,
        f.title,
        f.status,
        f.discovered_at,
        a.assessment_name,
        ass.asset_name,
        ass.asset_type,
        rl.name AS risk_level_name,
        rl.severity_score,
        r.remediation_id,
        r.status AS remediation_status,
        r.submitted_at
     FROM findings AS f
     INNER JOIN assessments AS a
        ON a.assessment_id = f.assessment_id
     INNER JOIN assets AS ass
        ON ass.asset_id = f.asset_id
     INNER JOIN risk_levels AS rl
        ON rl.risk_level_id = f.risk_level_id
     LEFT JOIN remediations AS r
        ON r.finding_id = f.finding_id
     WHERE a.assigned_to = :assigned_to
     ORDER BY
        CASE
            WHEN r.status = 'submitted' THEN 0
            ELSE 1
        END,
        f.discovered_at DESC"
);
$statement->execute([
    'assigned_to' => (int) $user['user_id'],
]);
$findings = $statement->fetchAll();
$statusLabels = [
    'open' => 'مفتوحة',
    'under_review' => 'قيد المراجعة',
    'confirmed' => 'مؤكدة',
    'reported' => 'تم الإبلاغ عنها',
    'remediation' => 'قيد المعالجة',
    'resolved' => 'تم حلها',
    'verified' => 'تم التحقق منها',
    'closed' => 'مغلقة',
    'rejected' => 'مرفوضة',
];
$remediationStatusLabels = [
    'pending' => 'بانتظار الإجراء',
    'in_progress' => 'قيد المعالجة',
    'submitted' => 'بانتظار إعادة الاختبار',
    'resolved' => 'تمت المعالجة',
    'verified' => 'تم التحقق',
    'rejected' => 'تحتاج إلى إجراء إضافي',
];
render_header('الثغرات الأمنية');
?>
<section class="page-heading">
    <div>
        <p class="eyebrow">المحلل الأمني</p>
        <h1>الثغرات والنتائج الأمنية</h1>
        <p class="muted">
            جميع النتائج التي اكتشفتها ضمن التقييمات المسندة إليك.
        </p>
    </div>
</section>
<section class="details-card">
    <?php if ($findings === []): ?>
        <div class="empty-state">
            <h2>لا توجد Findings</h2>
            <p class="muted">
                لم يتم تسجيل أي نتائج أمنية ضمن التقييمات المسندة إليك حتى الآن.
            </p>
        </div>
    <?php else: ?>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Finding</th>
                        <th>التقييم</th>
                        <th>الأصل</th>
                        <th>الخطورة</th>
                        <th>الحالة</th>
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
                                    <?= e((string) $finding['title']) ?>
                                </strong>
                            </td>
                            <td>
                                <?= e((string) $finding['assessment_name']) ?>
                            </td>
                            <td>
                                <strong>
                                    <?= e((string) $finding['asset_name']) ?>
                                </strong>
                                <small>
                                    <?= e((string) $finding['asset_type']) ?>
                                </small>
                            </td>
                            <td>
                                <span class="status-badge status-<?= e(strtolower((string) $finding['risk_level_name'])) ?>">
                                    <?= e((string) $finding['risk_level_name']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-badge status-<?= e((string) $finding['status']) ?>">
                                    <?= e(
                                        $statusLabels[(string) $finding['status']]
                                        ?? (string) $finding['status']
                                    ) ?>
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
                                <?= e((string) $finding['discovered_at']) ?>
                            </td>
                            <td>
                                <a href="<?= e(
                                    url(
                                        'analyst/findings/view.php?finding_id='
                                        . $finding['finding_id']
                                    )
                                ) ?>" class="button button-small">
                                    <?php if ($finding['remediation_status'] === 'submitted'): ?>
                                        إعادة الاختبار
                                    <?php else: ?>
                                        فتح
                                    <?php endif; ?>
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