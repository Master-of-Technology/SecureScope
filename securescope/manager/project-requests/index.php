<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_role('Security Manager');
$statement = db()->query(
    "SELECT
        pr.request_id,
        pr.request_title,
        pr.requested_service,
        pr.priority,
        pr.status,
        pr.created_at,
        c.company_name
     FROM projects_requests AS pr
     INNER JOIN clients AS c
        ON c.client_id = pr.client_id
     ORDER BY
        CASE pr.status
            WHEN 'pending' THEN 1
            WHEN 'approved' THEN 2
            WHEN 'rejected' THEN 3
            WHEN 'cancelled' THEN 4
        END,
        pr.created_at DESC"
);
$requests = $statement->fetchAll();
$statusLabels = [
    'pending' => 'بانتظار المراجعة',
    'approved' => 'تمت الموافقة',
    'rejected' => 'مرفوض',
    'cancelled' => 'ملغى',
];
$priorityLabels = [
    'low' => 'منخفضة',
    'normal' => 'عادية',
    'high' => 'عالية',
    'urgent' => 'عاجلة',
];
render_header('طلبات المشاريع');
?>
<section class="page-heading">
    <div>
        <p class="eyebrow">مدير الأمن</p>
        <h1>طلبات المشاريع</h1>
        <p class="muted">
            مراجعة طلبات العملاء وتحويل الطلبات المقبولة إلى مشاريع أمنية.
        </p>
    </div>
</section>
<section class="details-card">
    <div class="section-heading compact">
        <div>
            <h2>طلبات العملاء</h2>
            <p class="muted">
                جميع طلبات المشاريع الواردة من العملاء.
            </p>
        </div>
    </div>
    <?php if ($requests === []): ?>
        <div class="empty-state">
            <h2>لا توجد طلبات</h2>
            <p class="muted">
                لم يتم إرسال أي طلب مشروع حتى الآن.
            </p>
        </div>
    <?php else: ?>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>الطلب</th>
                        <th>العميل</th>
                        <th>الخدمة</th>
                        <th>الأولوية</th>
                        <th>الحالة</th>
                        <th>التاريخ</th>
                        <th>فتح</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requests as $request): ?>
                        <tr>
                            <td>
                                <strong>
                                    <?= e((string) $request['request_title']) ?>
                                </strong>
                            </td>
                            <td>
                                <?= e((string) $request['company_name']) ?>
                            </td>
                            <td>
                                <?= e((string) $request['requested_service']) ?>
                            </td>
                            <td>
                                <?= e(
                                    $priorityLabels[(string) $request['priority']]
                                    ?? (string) $request['priority']
                                ) ?>
                            </td>
                            <td>
                                <span class="status-badge status-<?= e((string) $request['status']) ?>">
                                    <?= e(
                                        $statusLabels[(string) $request['status']]
                                        ?? (string) $request['status']
                                    ) ?>
                                </span>
                            </td>
                            <td>
                                <?= e((string) $request['created_at']) ?>
                            </td>
                            <td>
                                <a href="<?= e(
                                    url(
                                        'manager/project-requests/view.php?request_id='
                                        . (int) $request['request_id']
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