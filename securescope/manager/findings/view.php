<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_role('Security Manager');
$findingId = filter_input(
    INPUT_GET,
    'finding_id',
    FILTER_VALIDATE_INT
);
if (
    $findingId === false
    || $findingId === null
    || $findingId <= 0
) {
    set_flash(
        'error',
        'معرف Finding غير صالح.'
    );
    redirect('manager/findings/index.php');
}
$statement = db()->prepare(
    "SELECT
        f.*,
        a.assessment_name,
        a.status AS assessment_status,
        p.project_id,
        p.project_name,
        p.description AS project_description,
        p.status AS project_status,
        c.client_id,
        c.company_name,
        c.company_email,
        c.phone AS company_phone,
        c.address AS company_address,
        ass.asset_name,
        ass.asset_type,
        ass.identifier,
        ass.description AS asset_description,
        rl.name AS risk_level_name,
        rl.severity_score,
        rl.description AS risk_level_description,
        analyst.first_name AS analyst_first_name,
        analyst.last_name AS analyst_last_name,
        reviewer.first_name AS reviewer_first_name,
        reviewer.last_name AS reviewer_last_name
     FROM findings AS f
     INNER JOIN assessments AS a
        ON a.assessment_id = f.assessment_id
     INNER JOIN projects AS p
        ON p.project_id = a.project_id
     INNER JOIN clients AS c
        ON c.client_id = p.client_id
     INNER JOIN assets AS ass
        ON ass.asset_id = f.asset_id
     INNER JOIN risk_levels AS rl
        ON rl.risk_level_id = f.risk_level_id
     INNER JOIN users AS analyst
        ON analyst.user_id = f.discovered_by
     LEFT JOIN users AS reviewer
        ON reviewer.user_id = f.reviewed_by
     WHERE f.finding_id = :finding_id
     LIMIT 1"
);
$statement->execute([
    'finding_id' => $findingId,
]);
$finding = $statement->fetch();
if ($finding === false) {
    set_flash(
        'error',
        'لم يتم العثور على Finding.'
    );
    redirect('manager/findings/index.php');
}
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
$assessmentStatusLabels = [
    'pending' => 'بانتظار البدء',
    'in_progress' => 'قيد التنفيذ',
    'under_review' => 'قيد المراجعة',
    'completed' => 'مكتمل',
    'cancelled' => 'ملغى',
];
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
render_header('مراجعة Finding');
?>
<section class="page-heading">
    <div>
        <p class="eyebrow">إدارة الثغرات</p>
        <h1>
            <?= e((string) $finding['title']) ?>
        </h1>
        <p class="muted">
            مراجعة النتيجة الأمنية قبل اعتمادها والانتقال إلى المعالجة.
        </p>
    </div>
    <div class="page-actions">
        <a
            href="<?= e(url('manager/findings/index.php')) ?>"
            class="button button-primary"
        >
            العودة إلى Findings
        </a>
    </div>
</section>
<section class="details-card">
    <div class="section-heading compact">
        <div>
            <h2>حالة Finding</h2>
        </div>
        <span class="status-badge status-<?= e((string) $finding['status']) ?>">
            <?= e(
                $statusLabels[(string) $finding['status']]
                ?? (string) $finding['status']
            ) ?>
        </span>
    </div>
    <div class="detail-grid">
        <div>
            <span class="detail-label">العنوان</span>
            <strong>
                <?= e((string) $finding['title']) ?>
            </strong>
        </div>
        <div>
            <span class="detail-label">مستوى الخطورة</span>
            <strong>
                <?= e((string) $finding['risk_level_name']) ?>
                — <?= e((string) $finding['severity_score']) ?>/5
            </strong>
        </div>
        <div>
            <span class="detail-label">تاريخ الاكتشاف</span>
            <strong>
                <?= e((string) $finding['discovered_at']) ?>
            </strong>
        </div>
        <div>
            <span class="detail-label">المحلل</span>
            <strong>
                <?= e(
                    trim(
                        (string) $finding['analyst_first_name']
                        . ' '
                        . (string) $finding['analyst_last_name']
                    )
                ) ?>
            </strong>
        </div>
    </div>
</section>
<section class="details-card">
    <div class="section-heading compact">
        <div>
            <h2>التقييم والمشروع</h2>
        </div>
    </div>
    <div class="detail-grid">
        <div>
            <span class="detail-label">التقييم</span>
            <strong>
                <?= e((string) $finding['assessment_name']) ?>
            </strong>
        </div>
        <div>
            <span class="detail-label">حالة التقييم</span>
            <span class="status-badge status-<?= e((string) $finding['assessment_status']) ?>">
                <?= e(
                    $assessmentStatusLabels[
                        (string) $finding['assessment_status']
                    ]
                    ?? (string) $finding['assessment_status']
                ) ?>
            </span>
        </div>
        <div>
            <span class="detail-label">المشروع</span>
            <strong>
                <?= e((string) $finding['project_name']) ?>
            </strong>
        </div>
        <div>
            <span class="detail-label">حالة المشروع</span>
            <span class="status-badge status-<?= e((string) $finding['project_status']) ?>">
                <?= e(
                    $projectStatusLabels[
                        (string) $finding['project_status']
                    ]
                    ?? (string) $finding['project_status']
                ) ?>
            </span>
        </div>
        <div>
            <span class="detail-label">العميل</span>
            <strong>
                <?= e((string) $finding['company_name']) ?>
            </strong>
        </div>
    </div>
</section>
<section class="details-card">
    <div class="section-heading compact">
        <div>
            <h2>الأصل المتأثر</h2>
        </div>
    </div>
    <div class="detail-grid">
        <div>
            <span class="detail-label">اسم الأصل</span>
            <strong>
                <?= e((string) $finding['asset_name']) ?>
            </strong>
        </div>
        <div>
            <span class="detail-label">نوع الأصل</span>
            <strong>
                <?= e((string) $finding['asset_type']) ?>
            </strong>
        </div>
        <div>
            <span class="detail-label">المعرّف</span>
            <strong>
                <?= e((string) ($finding['identifier'] ?? '—')) ?>
            </strong>
        </div>
    </div>
    <?php if (!empty($finding['asset_description'])): ?>
            <div class="detail-section">
                <h3>وصف الأصل</h3>
                <p>
                    <?= nl2br(e((string) $finding['asset_description'])) ?>
                </p>
            </div>
    <?php endif; ?>
</section>
<section class="details-card">
    <div class="section-heading compact">
        <div>
            <h2>النتيجة الأمنية</h2>
        </div>
    </div>
    <div class="detail-section">
        <h3>الوصف</h3>
        <p>
            <?= nl2br(e((string) $finding['description'])) ?>
        </p>
    </div>
    <div class="detail-section">
        <h3>التفاصيل الفنية</h3>
        <?php if (!empty($finding['technical_details'])): ?>
                <p>
                    <?= nl2br(e((string) $finding['technical_details'])) ?>
                </p>
        <?php else: ?>
                <p class="muted">
                    لم تتم إضافة تفاصيل فنية.
                </p>
        <?php endif; ?>
    </div>
</section>
<section class="details-card">
    <div class="section-heading compact">
        <div>
            <h2>مستوى الخطورة</h2>
        </div>
    </div>
    <div class="detail-grid">
        <div>
            <span class="detail-label">المستوى</span>
            <strong>
                <?= e((string) $finding['risk_level_name']) ?>
            </strong>
        </div>
        <div>
            <span class="detail-label">الدرجة</span>
            <strong>
                <?= e((string) $finding['severity_score']) ?>/5
            </strong>
        </div>
    </div>
    <div class="detail-section">
        <h3>وصف مستوى الخطورة</h3>
        <p>
            <?= nl2br(e((string) $finding['risk_level_description'])) ?>
        </p>
    </div>
</section>
<section class="details-card">
    <div class="section-heading compact">
        <div>
            <h2>مراجعة Finding</h2>
            <p class="muted">
                راجع نتيجة الفحص قبل اعتمادها أو رفضها.
            </p>
        </div>
    </div>
    <div class="detail-grid">
        <div>
            <span class="detail-label">الحالة الحالية</span>
            <span class="status-badge status-<?= e((string) $finding['status']) ?>">
                <?= e(
                    $statusLabels[(string) $finding['status']]
                    ?? (string) $finding['status']
                ) ?>
            </span>
        </div>
        <div>
            <span class="detail-label">تمت المراجعة بواسطة</span>
            <strong>
                <?= e(
                    trim(
                        (string) ($finding['reviewer_first_name'] ?? '')
                        . ' '
                        . (string) ($finding['reviewer_last_name'] ?? '')
                    )
                    ?: '—'
                ) ?>
            </strong>
        </div>
        <div>
            <span class="detail-label">تاريخ المراجعة</span>
            <strong>
                <?= e((string) ($finding['reviewed_at'] ?? '—')) ?>
            </strong>
        </div>
    </div>
    <?php if ($finding['status'] === 'open'): ?>
        <div class="form-actions">
            <form
                method="post"
                action="<?= e(url('manager/findings/approve.php')) ?>"
            >
                <?= csrf_input() ?>
                <input
                    type="hidden"
                    name="finding_id"
                    value="<?= e((string) $findingId) ?>"
                >
                <button
                    type="submit"
                    class="button button-primary"
                >
                    اعتماد Finding
                </button>
            </form>
            <a
                href="<?= e(
                    url(
                        'manager/findings/reject.php?finding_id='
                        . $findingId
                    )
                ) ?>"
                class="button button-danger"
            >
                رفض Finding
            </a>
        </div>
    <?php elseif ($finding['status'] === 'confirmed'): ?>
        <div class="alert alert-success">
            تم اعتماد Finding، ويمكن الانتقال إلى مرحلة المعالجة.
        </div>
    <?php elseif ($finding['status'] === 'rejected'): ?>
        <div class="alert alert-error">
            تم رفض Finding.
        </div>
    <?php endif; ?>
</section>
<?php render_footer(); ?>