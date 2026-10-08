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
        a.assessment_id,
        a.project_id,
        a.assessment_name,
        a.description,
        a.status,
        a.started_at,
        a.completed_at,
        a.created_at,
        p.project_name,
        c.company_name
     FROM assessments AS a
     INNER JOIN projects AS p
        ON p.project_id = a.project_id
     INNER JOIN clients AS c
        ON c.client_id = p.client_id
     WHERE a.assigned_to = :assigned_to
     ORDER BY a.created_at DESC"
);
$statement->execute([
    'assigned_to' => (int) $user['user_id'],
]);
$assessments = $statement->fetchAll();
$statusLabels = [
    'pending' => 'بانتظار البدء',
    'in_progress' => 'قيد التنفيذ',
    'under_review' => 'قيد المراجعة',
    'completed' => 'مكتمل',
    'cancelled' => 'ملغى',
];
render_header('التقييمات المسندة');
?>
<section class="page-heading">
    <div>
        <p class="eyebrow">المحلل الأمني</p>
        <h1>التقييمات المسندة إليّ</h1>
        <p class="muted">
            التقييمات الأمنية التي تم إسنادها إليك من إدارة SecureScope.
        </p>
    </div>
</section>
<section class="details-card">
    <?php if ($assessments === []): ?>
            <div class="empty-state">
                <h2>لا توجد تقييمات مسندة</h2>
                <p class="muted">
                    لا توجد حاليًا أي تقييمات أمنية مسندة إلى حسابك.
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
                            <th>تاريخ البدء</th>
                            <th>الإجراء</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($assessments as $assessment): ?>
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
                                                $statusLabels[(string) $assessment['status']]
                                                ?? (string) $assessment['status']
                                            ) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?= e(
                                            (string) (
                                                $assessment['started_at']
                                                ?? '—'
                                            )
                                        ) ?>
                                    </td>
                                    <td>
                                        <a
                                            href="<?= e(
                                                url(
                                                    'analyst/assessments/view.php?assessment_id='
                                                    . (int) $assessment['assessment_id']
                                                )
                                            ) ?>"
                                            class="button button-small"
                                        >
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