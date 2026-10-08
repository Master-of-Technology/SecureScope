<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

require_role('Security Manager');

/*
|--------------------------------------------------------------------------
| Get projects
|--------------------------------------------------------------------------
*/

$statement = db()->query(
    "SELECT
        p.project_id,
        p.project_name,
        p.description,
        p.start_date,
        p.end_date,
        p.status,
        p.created_at,
        c.company_name,
        at.name AS assessment_type_name
     FROM projects AS p
     INNER JOIN clients AS c
        ON c.client_id = p.client_id
     INNER JOIN assessment_types AS at
        ON at.assessment_type_id = p.assessment_type_id
     ORDER BY p.created_at DESC"
);

$projects = $statement->fetchAll();

/*
|--------------------------------------------------------------------------
| Status labels
|--------------------------------------------------------------------------
*/

$statusLabels = [
    'draft' => 'مسودة',
    'pending_approval' => 'بانتظار الموافقة',
    'approved' => 'مقبول',
    'assigned' => 'تم إسناده',
    'in_progress' => 'قيد التنفيذ',
    'under_review' => 'قيد المراجعة',
    'report_generated' => 'تم إنشاء التقرير',
    'client_review' => 'مراجعة العميل',
    'remediation' => 'المعالجة',
    'completed' => 'مكتمل',
    'closed' => 'مغلق',
    'cancelled' => 'ملغى',
];

render_header('المشاريع');
?>

<section class="page-heading">
    <div>
        <p class="eyebrow">إدارة المشاريع</p>
        <h1>المشاريع</h1>
        <p class="muted">
            إدارة مشاريع العملاء ومتابعة مراحل التقييم الأمني.
        </p>
    </div>

    <div class="page-actions">
        <a href="<?= e(url('manager/projects/create.php')) ?>" class="button button-primary">
            إضافة مشروع
        </a>
    </div>
</section>

<section class="details-card">
    <?php if ($projects === []): ?>
        <div class="empty-state">
            <h2>لا توجد مشاريع</h2>
            <p class="muted">
                لم يتم إنشاء أي مشروع حتى الآن.
            </p>
        </div>
    <?php else: ?>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>المشروع</th>
                        <th>العميل</th>
                        <th>نوع التقييم</th>
                        <th>تاريخ البداية</th>
                        <th>تاريخ النهاية</th>
                        <th>الحالة</th>
                        <th>الإجراء</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($projects as $project): ?>
                        <tr>
                            <td>
                                <strong>
                                    <?= e((string) $project['project_name']) ?>
                                </strong>
                            </td>
                            <td>
                                <?= e((string) $project['company_name']) ?>
                            </td>
                            <td>
                                <?= e((string) $project['assessment_type_name']) ?>
                            </td>
                            <td>
                                <?= e((string) ($project['start_date'] ?? '—')) ?>
                            </td>
                            <td>
                                <?= e((string) ($project['end_date'] ?? '—')) ?>
                            </td>
                            <td>
                                <span class="status-badge status-<?= e((string) $project['status']) ?>">
                                    <?= e(
                                        $statusLabels[(string) $project['status']]
                                        ?? (string) $project['status']
                                    ) ?>
                                </span>
                            </td>
                            <td>
                                <a href="<?= e(
                                    url(
                                        'manager/projects/view.php?project_id='
                                        . (int) $project['project_id']
                                    )
                                ) ?>" class="button button-small">
                                    عرض المشروع
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