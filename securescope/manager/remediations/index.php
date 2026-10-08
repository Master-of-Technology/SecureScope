<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_role('Security Manager');
$statement = db()->query(
    "SELECT
        r.remediation_id,
        r.finding_id,
        r.client_id,
        r.description,
        r.retest_notes,
        r.status,
        r.submitted_at,
        r.resolved_at,
        r.verified_at,
        f.title AS finding_title,
        rl.name AS risk_level_name,
        rl.severity_score,
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
     INNER JOIN clients AS c
        ON c.client_id = r.client_id
     ORDER BY
        CASE
            WHEN r.status = 'resolved' THEN 0
            WHEN r.status = 'submitted' THEN 1
            WHEN r.status = 'rejected' THEN 2
            WHEN r.status = 'in_progress' THEN 3
            WHEN r.status = 'pending' THEN 4
            WHEN r.status = 'verified' THEN 5
            ELSE 6
        END,
        r.created_at DESC"
);
$remediations = $statement->fetchAll();
$statusLabels = [
    'pending' => 'بانتظار المعالجة',
    'in_progress' => 'قيد التنفيذ',
    'submitted' => 'بانتظار إعادة الاختبار',
    'resolved' => 'بانتظار اعتماد المدير',
    'verified' => 'تم التحقق',
    'rejected' => 'تحتاج إلى معالجة إضافية',
];
render_header('المعالجات');
?>
<section class="page-heading">
    <div>
        <p class="eyebrow">إدارة المعالجات</p>
        <h1>المعالجات الأمنية</h1>
        <p class="muted">
            متابعة الإجراءات التصحيحية ونتائج إعادة الاختبار واعتمادها.
        </p>
    </div>
</section>
<section class="details-card">
    <div class="section-heading compact">
        <div>
            <h2>جميع المعالجات</h2>
            <p class="muted">
                تظهر هنا المعالجات المرتبطة بالثغرات الأمنية ونتائج إعادة الاختبار.
            </p>
        </div>
    </div>
    <?php if ($remediations === []): ?>
        <div class="empty-state">
            <h2>لا توجد معالجات</h2>
            <p class="muted">
                لم يتم إنشاء أي معالجة أمنية حتى الآن.
            </p>
        </div>
    <?php else: ?>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Finding</th>
                        <th>المشروع</th>
                        <th>العميل</th>
                        <th>الخطورة</th>
                        <th>الحالة</th>
                        <th>تاريخ الإرسال</th>
                        <th>الإجراء</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($remediations as $remediation): ?>
                        <tr>
                            <td>
                                <strong>
                                    <?= e((string) $remediation['finding_title']) ?>
                                </strong>
                            </td>
                            <td>
                                <?= e((string) $remediation['project_name']) ?>
                            </td>
                            <td>
                                <?= e((string) $remediation['company_name']) ?>
                            </td>
                            <td>
                                <span class="status-badge status-<?= e(
                                    strtolower(
                                        (string) $remediation['risk_level_name']
                                    )
                                ) ?>">
                                    <?= e((string) $remediation['risk_level_name']) ?>
                                    —
                                    <?= e((string) $remediation['severity_score']) ?>/5
                                </span>
                            </td>
                            <td>
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
                            </td>
                            <td>
                                <?= e(
                                    (string) (
                                        $remediation['submitted_at']
                                        ?? '—'
                                    )
                                ) ?>
                            </td>
                            <td>
                                <a href="<?= e(
                                    url(
                                        'manager/remediations/view.php?remediation_id='
                                        . $remediation['remediation_id']
                                    )
                                ) ?>" class="button button-small">
                                    <?php if ($remediation['status'] === 'resolved'): ?>
                                        مراجعة واعتماد
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