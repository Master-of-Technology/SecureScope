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
$projectStatement = db()->prepare(
    "SELECT
        p.project_id,
        p.project_name,
        p.status,
        p.start_date,
        p.end_date,
        p.created_at,
        COUNT(DISTINCT a.assessment_id) AS assessment_count,
        COUNT(DISTINCT CASE
            WHEN f.status IN (
                'confirmed',
                'reported',
                'remediation',
                'resolved',
                'verified'
            ) THEN f.finding_id
        END) AS finding_count,
        COUNT(DISTINCT r.remediation_id) AS remediation_count,
        COUNT(DISTINCT CASE
            WHEN r.status = 'verified' THEN r.remediation_id
        END) AS verified_remediation_count
     FROM projects AS p
     LEFT JOIN assessments AS a
        ON a.project_id = p.project_id
     LEFT JOIN findings AS f
        ON f.assessment_id = a.assessment_id
     LEFT JOIN remediations AS r
        ON r.finding_id = f.finding_id
       AND r.client_id = :remediation_client_id
     WHERE p.client_id = :client_id
     GROUP BY
        p.project_id,
        p.project_name,
        p.status,
        p.start_date,
        p.end_date,
        p.created_at
     ORDER BY p.created_at DESC"
);
$projectStatement->execute([
    'remediation_client_id' => $clientId,
    'client_id' => $clientId,
]);
$projects = $projectStatement->fetchAll();
$requestStatement = db()->prepare(
    "SELECT
        request_id,
        request_title,
        requested_service,
        priority,
        status,
        manager_notes,
        created_at,
        reviewed_at
     FROM projects_requests
     WHERE client_id = :client_id
       AND status IN ('pending', 'rejected')
     ORDER BY created_at DESC"
);
$requestStatement->execute([
    'client_id' => $clientId,
]);
$requests = $requestStatement->fetchAll();
$projectStatusLabels = [
    'draft' => 'مسودة',
    'pending_approval' => 'بانتظار الموافقة',
    'approved' => 'تمت الموافقة',
    'assigned' => 'تم الإسناد',
    'in_progress' => 'قيد التنفيذ',
    'under_review' => 'قيد المراجعة',
    'report_generated' => 'تم إعداد التقرير',
    'client_review' => 'مراجعة العميل',
    'remediation' => 'قيد المعالجة',
    'completed' => 'مكتمل',
    'closed' => 'مغلق',
    'cancelled' => 'ملغى',
];
$requestStatusLabels = [
    'pending' => 'بانتظار موافقة الإدارة',
    'rejected' => 'مرفوض',
];
$priorityLabels = [
    'low' => 'منخفضة',
    'normal' => 'عادية',
    'high' => 'عالية',
    'urgent' => 'عاجلة',
];
render_header('مشاريع العميل');
?>
<section class="page-heading">
    <div>
        <p class="eyebrow">العميل</p>
        <h1>مشاريعي</h1>
        <p class="muted">
            متابعة مشاريع شركتك وطلبات الفحص الجديدة.
        </p>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('client/projects/create.php')) ?>" class="button button-primary">
            طلب مشروع جديد
        </a>
    </div>
</section>
<section class="details-card">
    <div class="section-heading compact">
        <div>
            <h2>المشاريع الحالية</h2>
            <p class="muted">
                المشاريع التي تمت الموافقة عليها من إدارة SecureScope ويجري العمل عليها أو تم إنجازها.
            </p>
        </div>
    </div>
    <?php if ($projects === []): ?>
        <div class="empty-state">
            <h3>لا توجد مشاريع حالية</h3>
            <p class="muted">
                يمكنك إرسال طلب مشروع جديد ليتم مراجعته من الإدارة.
            </p>
        </div>
    <?php else: ?>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>المشروع</th>
                        <th>الحالة</th>
                        <th>التقييمات</th>
                        <th>الثغرات</th>
                        <th>المعالجات</th>
                        <th>التقدم</th>
                        <th>فتح</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($projects as $project): ?>
                        <?php
                        $remediationCount = (int) $project['remediation_count'];
                        $verifiedRemediationCount = (int) $project['verified_remediation_count'];
                        if ($remediationCount > 0) {
                            $progress = (int) round(
                                ($verifiedRemediationCount / $remediationCount) * 100
                            );
                        } elseif ((string) $project['status'] === 'completed') {
                            $progress = 100;
                        } elseif (
                            in_array(
                                (string) $project['status'],
                                ['assigned', 'in_progress', 'under_review', 'report_generated', 'client_review', 'remediation'],
                                true
                            )
                        ) {
                            $progress = 50;
                        } else {
                            $progress = 0;
                        }
                        if ($progress > 100) {
                            $progress = 100;
                        }
                        ?>
                        <tr>
                            <td>
                                <strong>
                                    <?= e((string) $project['project_name']) ?>
                                </strong>
                            </td>
                            <td>
                                <span class="status-badge status-<?= e((string) $project['status']) ?>">
                                    <?= e(
                                        $projectStatusLabels[(string) $project['status']]
                                        ?? (string) $project['status']
                                    ) ?>
                                </span>
                            </td>
                            <td>
                                <?= e((string) $project['assessment_count']) ?>
                            </td>
                            <td>
                                <?= e((string) $project['finding_count']) ?>
                            </td>
                            <td>
                                <?= e((string) $verifiedRemediationCount) ?>
                                /
                                <?= e((string) $remediationCount) ?>
                            </td>
                            <td>
                                <strong><?= e((string) $progress) ?>%</strong>
                            </td>
                            <td>
                                <a href="<?= e(
                                    url(
                                        'client/projects/view.php?project_id='
                                        . (int) $project['project_id']
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
<section class="details-card">
    <div class="section-heading compact">
        <div>
            <h2>طلبات المشاريع</h2>
            <p class="muted">
                الطلبات التي لم تتحول بعد إلى مشاريع فعلية.
            </p>
        </div>
    </div>
    <?php if ($requests === []): ?>
        <div class="empty-state">
            <h3>لا توجد طلبات معلقة</h3>
            <p class="muted">
                لا توجد حاليًا طلبات بانتظار المراجعة أو طلبات مرفوضة.
            </p>
        </div>
    <?php else: ?>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>الطلب</th>
                        <th>الخدمة</th>
                        <th>الأولوية</th>
                        <th>الحالة</th>
                        <th>التاريخ</th>
                        <th>ملاحظات الإدارة</th>
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
                                        $requestStatusLabels[(string) $request['status']]
                                        ?? (string) $request['status']
                                    ) ?>
                                </span>
                            </td>
                            <td>
                                <?= e((string) $request['created_at']) ?>
                            </td>
                            <td>
                                <?= e(
                                    (string) (
                                        $request['manager_notes']
                                        ?? '—'
                                    )
                                ) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php render_footer(); ?>