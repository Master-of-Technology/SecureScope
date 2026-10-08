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
$statement = db()->prepare(
    "SELECT
        a.assessment_id,
        a.assessment_name,
        a.status,
        a.created_at,
        a.project_id,
        p.project_name,
        at.name AS assessment_type_name,
        CONCAT(u.first_name, ' ', u.last_name) AS analyst_name,
        COUNT(DISTINCT CASE
            WHEN f.status IN (
                'confirmed',
                'reported',
                'remediation',
                'resolved',
                'verified'
            )
            THEN f.finding_id
        END) AS finding_count
     FROM assessments AS a
     INNER JOIN projects AS p
        ON p.project_id = a.project_id
     LEFT JOIN assessment_types AS at
        ON at.assessment_type_id = p.assessment_type_id
     LEFT JOIN users AS u
        ON u.user_id = a.assigned_to
     LEFT JOIN findings AS f
        ON f.assessment_id = a.assessment_id
     WHERE p.client_id = :client_id
     GROUP BY
        a.assessment_id,
        a.assessment_name,
        a.status,
        a.created_at,
        a.project_id,
        p.project_name,
        at.name,
        u.first_name,
        u.last_name
     ORDER BY a.created_at DESC"
);
$statement->execute([
    'client_id' => $clientId,
]);
$assessments = $statement->fetchAll();
$statusLabels = [
    'pending' => 'بانتظار البدء',
    'in_progress' => 'قيد التنفيذ',
    'under_review' => 'قيد المراجعة',
    'completed' => 'مكتمل',
    'cancelled' => 'ملغى',
];
render_header('التقييمات الأمنية');
?>
<section class="page-heading">
    <div>
        <p class="eyebrow">بوابة العميل</p>
        <h1>التقييمات الأمنية</h1>
        <p class="muted">
            متابعة التقييمات الأمنية المرتبطة بمشاريع شركتك.
        </p>
    </div>
    <div class="page-actions">
        <a
            href="<?= e(url('client/projects/index.php')) ?>"
            class="button button-primary"
        >
            المشاريع
        </a>
    </div>
</section>
<section class="details-card">
    <div class="section-heading compact">
        <div>
            <h2>التقييمات الخاصة بشركتك</h2>
            <p class="muted">
                التقييمات التي ينفذها فريق SecureScope ضمن مشاريعك.
            </p>
        </div>
    </div>
    <?php if ($assessments === []): ?>
            <div class="empty-state">
                <h2>لا توجد تقييمات</h2>
                <p class="muted">
                    لا توجد تقييمات أمنية مرتبطة بمشاريع شركتك حاليًا.
                </p>
            </div>
    <?php else: ?>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>التقييم</th>
                            <th>المشروع</th>
                            <th>نوع التقييم</th>
                            <th>المحلل</th>
                            <th>الحالة</th>
                            <th>الثغرات</th>
                            <th>التاريخ</th>
                            <th>فتح</th>
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
                                        <?= e(
                                            (string) (
                                                $assessment['assessment_type_name']
                                                ?? '—'
                                            )
                                        ) ?>
                                    </td>
                                    <td>
                                        <?= e(
                                            (string) (
                                                $assessment['analyst_name']
                                                ?? 'غير مسند'
                                            )
                                        ) ?>
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
                                        <?= e((string) $assessment['finding_count']) ?>
                                    </td>
                                    <td>
                                        <?= e((string) $assessment['created_at']) ?>
                                    </td>
                                    <td>
                                        <a
                                            href="<?= e(
                                                url(
                                                    'client/assessments/view.php?assessment_id='
                                                    . (int) $assessment['assessment_id']
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