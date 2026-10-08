<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

require_role('Security Manager');

$statement = db()->query(
    "SELECT
        a.assessment_id,
        a.project_id,
        a.assessment_name,
        a.description,
        a.assigned_to,
        a.status,
        a.started_at,
        a.completed_at,
        a.created_at,
        p.project_name,
        c.company_name,
        u.first_name AS analyst_first_name,
        u.last_name AS analyst_last_name
     FROM assessments AS a
     INNER JOIN projects AS p
        ON p.project_id = a.project_id
     INNER JOIN clients AS c
        ON c.client_id = p.client_id
     INNER JOIN users AS u
        ON u.user_id = a.assigned_to
     ORDER BY a.created_at DESC"
);

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
        <p class="eyebrow">إدارة التقييمات الأمنية</p>
        <h1>التقييمات الأمنية</h1>
        <p class="muted">
            متابعة التقييمات الأمنية المرتبطة بمشاريع SecureScope.
        </p>
    </div>

    <div class="page-actions">
        <a
            href="<?= e(url('manager/assessments/create.php')) ?>"
            class="button button-primary"
        >
            إضافة تقييم
        </a>
    </div>
</section>

<section class="details-card">
    <?php if ($assessments === []): ?>
            <div class="empty-state">
                <h2>لا توجد تقييمات</h2>
                <p class="muted">
                    لم يتم إنشاء أي تقييم أمني حتى الآن.
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
                            <th>المحلل</th>
                            <th>تاريخ البداية</th>
                            <th>الحالة</th>
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
                                        <?= e(
                                            trim(
                                                (string) $assessment['analyst_first_name']
                                                . ' '
                                                . (string) $assessment['analyst_last_name']
                                            )
                                        ) ?>
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
                                        <span class="status-badge status-<?= e((string) $assessment['status']) ?>">
                                            <?= e(
                                                $statusLabels[(string) $assessment['status']]
                                                ?? (string) $assessment['status']
                                            ) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <a
                                            href="<?= e(
                                                url(
                                                    'manager/assessments/view.php?assessment_id='
                                                    . (int) $assessment['assessment_id']
                                                )
                                            ) ?>"
                                            class="button button-small"
                                        >
                                            عرض التقييم
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