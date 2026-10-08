<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('Client');
$user = current_user();
if ($user === null) {
    logout_user();
    redirect('login.php');
}
$clientId = (int) ($user['client_id'] ?? 0);
if ($clientId <= 0) {
    http_response_code(403);
    render_header('إعداد حساب العميل');
    ?>
        <section class="empty-state">
            <p class="eyebrow">يلزم إعداد الحساب</p>
            <h1>حسابك غير مرتبط بشركة عميلة.</h1>
            <p class="muted">تواصل مع مدير الأمن لإكمال إعداد الحساب.</p>
        </section>
        <?php
        render_footer();
        exit;
}
$assetCount = db()->prepare(
    "SELECT COUNT(*)
     FROM assets
     WHERE client_id = :client_id"
);
$assetCount->execute([
    'client_id' => $clientId,
]);
$findingCount = db()->prepare(
    "SELECT COUNT(*)
     FROM findings AS f
     INNER JOIN assessments AS a
        ON a.assessment_id = f.assessment_id
     INNER JOIN projects AS p
        ON p.project_id = a.project_id
     WHERE p.client_id = :client_id
       AND f.status IN ('confirmed', 'reported', 'remediation', 'resolved', 'verified')"
);
$findingCount->execute([
    'client_id' => $clientId,
]);
$remediationCount = db()->prepare(
    "SELECT COUNT(*)
     FROM remediations
     WHERE client_id = :client_id
       AND status NOT IN ('verified', 'rejected')"
);
$remediationCount->execute([
    'client_id' => $clientId,
]);
$projectCount = db()->prepare(
    "SELECT COUNT(*)
     FROM projects
     WHERE client_id = :client_id
       AND status NOT IN ('closed', 'cancelled')"
);
$projectCount->execute([
    'client_id' => $clientId,
]);
$verifiedFindingCount = db()->prepare(
    "SELECT COUNT(*)
     FROM findings AS f
     INNER JOIN assessments AS a
        ON a.assessment_id = f.assessment_id
     INNER JOIN projects AS p
        ON p.project_id = a.project_id
     WHERE p.client_id = :client_id
       AND f.status = 'verified'"
);
$verifiedFindingCount->execute([
    'client_id' => $clientId,
]);
$projectsStatement = db()->prepare(
    "SELECT
        p.project_id,
        p.project_name,
        p.description,
        p.start_date,
        p.end_date,
        p.status,
        COUNT(DISTINCT a.assessment_id) AS assessment_count,
        COUNT(DISTINCT f.finding_id) AS finding_count,
        COUNT(DISTINCT CASE
            WHEN f.status = 'verified' THEN f.finding_id
        END) AS verified_finding_count,
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
        p.description,
        p.start_date,
        p.end_date,
        p.status
     ORDER BY p.created_at DESC"
);
$projectsStatement->execute([
    'remediation_client_id' => $clientId,
    'client_id' => $clientId,
]);
$projects = $projectsStatement->fetchAll();
$recentAssessmentsStatement = db()->prepare(
    "SELECT
        a.assessment_id,
        a.assessment_name,
        a.status,
        a.created_at,
        p.project_id,
        p.project_name
     FROM assessments AS a
     INNER JOIN projects AS p
        ON p.project_id = a.project_id
     WHERE p.client_id = :client_id
     ORDER BY a.created_at DESC
     LIMIT 5"
);
$recentAssessmentsStatement->execute([
    'client_id' => $clientId,
]);
$recentAssessments = $recentAssessmentsStatement->fetchAll();
$recentFindingsStatement = db()->prepare(
    "SELECT
        f.finding_id,
        f.title,
        f.status,
        f.discovered_at,
        rl.name AS risk_level_name,
        rl.severity_score,
        p.project_name,
        a.assessment_name
     FROM findings AS f
     INNER JOIN assessments AS a
        ON a.assessment_id = f.assessment_id
     INNER JOIN projects AS p
        ON p.project_id = a.project_id
     INNER JOIN risk_levels AS rl
        ON rl.risk_level_id = f.risk_level_id
     WHERE p.client_id = :client_id
       AND f.status IN ('confirmed', 'reported', 'remediation', 'resolved', 'verified')
     ORDER BY f.discovered_at DESC
     LIMIT 5"
);
$recentFindingsStatement->execute([
    'client_id' => $clientId,
]);
$recentFindings = $recentFindingsStatement->fetchAll();
$projectStatusLabels = [
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
$assessmentStatusLabels = [
    'pending' => 'بانتظار البدء',
    'in_progress' => 'قيد التنفيذ',
    'under_review' => 'قيد المراجعة',
    'completed' => 'مكتمل',
    'cancelled' => 'ملغى',
];
$findingStatusLabels = [
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
$metrics = [
    'المشاريع النشطة' => (int) $projectCount->fetchColumn(),
    'أصول الشركة' => (int) $assetCount->fetchColumn(),
    'الثغرات المعتمدة' => (int) $findingCount->fetchColumn(),
    'المعالجات المفتوحة' => (int) $remediationCount->fetchColumn(),
];
render_header('لوحة تحكم العميل');
?>
<section class="page-heading">
    <div>
        <p class="eyebrow">بوابة العميل</p>
        <h1>نظرة عامة على أمن الشركة</h1>
        <p class="muted">
            هنا تظهر بيانات شركتك ومشاريعها ونتائج التقييمات الأمنية.
        </p>
    </div>
</section>
<section class="metric-grid" aria-label="ملخص العميل">
    <?php foreach ($metrics as $label => $value): ?>
            <article class="metric-card">
                <span><?= e($label) ?></span>
                <strong><?= e((string) $value) ?></strong>
            </article>
    <?php endforeach; ?>
</section>
<section class="details-card">
    <div class="section-heading compact">
        <div>
            <h2>مشاريعي</h2>
            <p class="muted">
                المشاريع المرتبطة بشركتك فقط.
            </p>
        </div>
        <div class="page-actions">
            <a
                href="<?= e(url('client/projects/index.php')) ?>"
                class="button button-primary"
            >
                عرض جميع المشاريع
            </a>
        </div>
    </div>
    <?php if ($projects === []): ?>
            <div class="empty-state">
                <h3>لا توجد مشاريع</h3>
                <p class="muted">
                    لا توجد مشاريع مرتبطة بحساب شركتك حاليًا.
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
                                $assessmentCount = (int) $project['assessment_count'];
                                $findingCountForProject = (int) $project['finding_count'];
                                $verifiedFindingCountForProject = (int) $project['verified_finding_count'];
                                $remediationCountForProject = (int) $project['remediation_count'];
                                $verifiedRemediationCountForProject = (int) $project['verified_remediation_count'];
                                if ($findingCountForProject > 0) {
                                    $progress = (int) round(
                                        (
                                            $verifiedFindingCountForProject
                                            / $findingCountForProject
                                        ) * 100
                                    );
                                } elseif ($assessmentCount > 0) {
                                    $progress = 25;
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
                                        <?= e((string) $assessmentCount) ?>
                                    </td>
                                    <td>
                                        <?= e((string) $findingCountForProject) ?>
                                    </td>
                                    <td>
                                        <?= e((string) $remediationCountForProject) ?>
                                        /
                                        <?= e((string) $verifiedRemediationCountForProject) ?>
                                    </td>
                                    <td>
                                        <strong><?= e((string) $progress) ?>%</strong>
                                    </td>
                                    <td>
                                        <a
                                            href="<?= e(
                                                url(
                                                    'client/projects/view.php?project_id='
                                                    . (int) $project['project_id']
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
<section class="details-card">
    <div class="section-heading compact">
        <div>
            <h2>آخر التقييمات</h2>
            <p class="muted">
                أحدث التقييمات المرتبطة بمشاريع شركتك.
            </p>
        </div>
        <div class="page-actions">
            <a
                href="<?= e(url('client/assessments/index.php')) ?>"
                class="button button-primary"
            >
                عرض التقييمات
            </a>
        </div>
    </div>
    <?php if ($recentAssessments === []): ?>
            <div class="empty-state">
                <h3>لا توجد تقييمات</h3>
                <p class="muted">
                    لم يتم إنشاء تقييمات أمنية لمشاريع شركتك حتى الآن.
                </p>
            </div>
    <?php else: ?>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>التقييم</th>
                            <th>المشروع</th>
                            <th>الحالة</th>
                            <th>التاريخ</th>
                            <th>فتح</th>
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
                                        <span class="status-badge status-<?= e((string) $assessment['status']) ?>">
                                            <?= e(
                                                $assessmentStatusLabels[(string) $assessment['status']]
                                                ?? (string) $assessment['status']
                                            ) ?>
                                        </span>
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
<section class="details-card">
    <div class="section-heading compact">
        <div>
            <h2>آخر الثغرات الأمنية</h2>
            <p class="muted">
                الثغرات التي اعتمدها فريق SecureScope ضمن مشاريع شركتك.
            </p>
        </div>
        <div class="page-actions">
            <a
                href="<?= e(url('client/findings/index.php')) ?>"
                class="button button-primary"
            >
                عرض الثغرات
            </a>
        </div>
    </div>
    <?php if ($recentFindings === []): ?>
            <div class="empty-state">
                <h3>لا توجد ثغرات معتمدة</h3>
                <p class="muted">
                    لم يتم اعتماد نتائج أمنية لمشاريع شركتك حتى الآن.
                </p>
            </div>
    <?php else: ?>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Finding</th>
                            <th>المشروع</th>
                            <th>الخطورة</th>
                            <th>الحالة</th>
                            <th>التاريخ</th>
                            <th>فتح</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentFindings as $finding): ?>
                                <tr>
                                    <td>
                                        <strong>
                                            <?= e((string) $finding['title']) ?>
                                        </strong>
                                    </td>
                                    <td>
                                        <?= e((string) $finding['project_name']) ?>
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
<section class="details-card quick-actions-card">
    <div class="section-heading compact">
        <div>
            <h2>إدارة أمن الشركة</h2>
            <p class="muted">
                الوصول إلى نتائج الفحص والمعالجات الخاصة بشركتك.
            </p>
        </div>
    </div>
    <div class="quick-actions">
        <a class="quick-action" href="<?= e(url('client/findings/index.php')) ?>">
            <strong>الثغرات الأمنية</strong>
            <span>مراجعة النتائج الأمنية التي اعتمدها فريق SecureScope.</span>
        </a>
        <a class="quick-action" href="<?= e(url('client/remediations/index.php')) ?>">
            <strong>المعالجات الأمنية</strong>
            <span>تنفيذ ومتابعة الإجراءات التصحيحية وإرسالها للتحقق.</span>
        </a>
    </div>
</section>
<section class="details-card">
    <div class="section-heading compact">
        <div>
            <h2>مسار العمل</h2>
            <p class="muted">
                دورة العمل الفعلية داخل SecureScope.
            </p>
        </div>
    </div>
    <div class="detail-grid">
        <div>
            <span class="detail-label">1. المشروع</span>
            <strong>إنشاء ومتابعة مشروع التقييم الأمني.</strong>
        </div>
        <div>
            <span class="detail-label">2. التقييم</span>
            <strong>تنفيذ التقييم الأمني بواسطة المحلل.</strong>
        </div>
        <div>
            <span class="detail-label">3. Findings</span>
            <strong>مراجعة النتائج الأمنية المعتمدة.</strong>
        </div>
        <div>
            <span class="detail-label">4. المعالجة</span>
            <strong>تنفيذ الإجراءات التصحيحية وإرسالها للتحقق.</strong>
        </div>
        <div>
            <span class="detail-label">5. التقرير</span>
            <strong>مراجعة التقرير الأمني النهائي.</strong>
        </div>
    </div>
</section>
<?php render_footer(); ?>