<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('Security Analyst');
$user = current_user();
$userId = (int) $user['user_id'];
$assignedProjects = db()->prepare(
    "SELECT COUNT(DISTINCT project_id) FROM project_assignments
     WHERE analyst_id = :user_id AND status = 'active'"
);
$assignedProjects->execute(['user_id' => $userId]);
$assessments = db()->prepare(
    "SELECT COUNT(*) FROM assessments
     WHERE assigned_to = :user_id
       AND status IN ('pending', 'in_progress', 'under_review')"
);
$assessments->execute(['user_id' => $userId]);
$findings = db()->prepare(
    "SELECT COUNT(*) FROM findings
     WHERE discovered_by = :user_id
       AND status IN ('open', 'under_review')"
);
$findings->execute(['user_id' => $userId]);
$retestRequests = db()->prepare(
    "SELECT COUNT(*)
     FROM remediations AS r
     INNER JOIN findings AS f
        ON f.finding_id = r.finding_id
     INNER JOIN assessments AS a
        ON a.assessment_id = f.assessment_id
     WHERE a.assigned_to = :user_id
       AND r.status = 'submitted'"
);
$retestRequests->execute(['user_id' => $userId]);
$recentAssessmentsStatement = db()->prepare(
    "SELECT
        a.assessment_id,
        a.assessment_name,
        a.status,
        a.created_at,
        p.project_name,
        c.company_name
     FROM assessments AS a
     INNER JOIN projects AS p
        ON p.project_id = a.project_id
     INNER JOIN clients AS c
        ON c.client_id = p.client_id
     WHERE a.assigned_to = :user_id
     ORDER BY a.created_at DESC
     LIMIT 5"
);
$recentAssessmentsStatement->execute([
    'user_id' => $userId,
]);
$recentAssessments = $recentAssessmentsStatement->fetchAll();
$assessmentStatusLabels = [
    'pending' => 'بانتظار البدء',
    'in_progress' => 'قيد التنفيذ',
    'under_review' => 'قيد المراجعة',
    'completed' => 'مكتمل',
    'cancelled' => 'ملغى',
];
$metrics = [
    'المشاريع المسندة إليّ' => (int) $assignedProjects->fetchColumn(),
    'التقييمات المفتوحة' => (int) $assessments->fetchColumn(),
    'الثغرات قيد العمل' => (int) $findings->fetchColumn(),
    'طلبات إعادة الاختبار' => (int) $retestRequests->fetchColumn(),
];
render_header('لوحة تحكم المحلل');
?>
<section class="page-heading">
    <div>
        <p class="eyebrow">محلل الأمن</p>
        <h1>مهامي</h1>
        <p class="muted">تظهر هنا الأعمال المسندة إلى حسابك فقط.</p>
    </div>
</section>
<section class="metric-grid" aria-label="ملخص المحلل">
    <article class="metric-card">
        <span>المشاريع المسندة إليّ</span>
        <strong><?= e((string) $metrics['المشاريع المسندة إليّ']) ?></strong>
    </article>
    <article class="metric-card">
        <span>التقييمات المفتوحة</span>
        <strong><?= e((string) $metrics['التقييمات المفتوحة']) ?></strong>
    </article>
    <a class="metric-card" href="<?= e(url('analyst/findings/index.php')) ?>">
        <span>الثغرات قيد العمل</span>
        <strong><?= e((string) $metrics['الثغرات قيد العمل']) ?></strong>
    </a>
    <a class="metric-card" href="<?= e(url('analyst/findings/index.php')) ?>">
        <span>طلبات إعادة الاختبار</span>
        <strong><?= e((string) $metrics['طلبات إعادة الاختبار']) ?></strong>
    </a>
</section>
<section class="details-card">
    <div class="section-heading compact">
        <div>
            <h2>التقييمات المسندة إليّ</h2>
            <p class="muted">
                التقييمات الأمنية التي يمكنك العمل عليها.
            </p>
        </div>
        <div class="page-actions">
            <a href="<?= e(url('analyst/assessments/index.php')) ?>" class="button button-primary">
                عرض جميع التقييمات
            </a>
        </div>
    </div>
    <?php if ($recentAssessments === []): ?>
        <div class="empty-state">
            <h3>لا توجد تقييمات مسندة</h3>
            <p class="muted">
                لا توجد حاليًا تقييمات أمنية مسندة إلى حسابك.
            </p>
        </div>
    <?php else: ?>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>التقييم</th>
                        <th>المشروع</th>
                        <th>العميل</th>
                        <th>الحالة</th>
                        <th>الإجراء</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentAssessments as $assessment): ?>
                        <tr>
                            <td>
                                <strong>
                                    <?= e((string) $assessment['assessment_name']) ?>
                                </strong>
                            </td>
                            <td>
                                <?= e((string) $assessment['project_name']) ?>
                            </td>
                            <td>
                                <?= e((string) $assessment['company_name']) ?>
                            </td>
                            <td>
                                <span class="status-badge status-<?= e((string) $assessment['status']) ?>">
                                    <?= e(
                                        $assessmentStatusLabels[(string) $assessment['status']]
                                        ?? (string) $assessment['status']
                                    ) ?>
                                </span>
                            </td>
                            <td>
                                <a href="<?= e(
                                    url(
                                        'analyst/assessments/view.php?assessment_id='
                                        . (int) $assessment['assessment_id']
                                    )
                                ) ?>" class="button button-small">
                                    فتح التقييم
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