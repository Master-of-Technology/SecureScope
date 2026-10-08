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
    http_response_code(403);
    render_header('إعداد حساب العميل');
    ?>
    <section class="empty-state">
        <p class="eyebrow">يلزم إعداد الحساب</p>
        <h1>حسابك غير مرتبط بشركة عميلة.</h1>
        <p class="muted">
            تواصل مع مدير الأمن لإكمال إعداد الحساب.
        </p>
    </section>
    <?php
    render_footer();
    exit;
}
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
    redirect('client/findings/index.php');
}
$statement = db()->prepare(
    "SELECT
        f.*,
        a.assessment_name,
        a.status AS assessment_status,
        p.project_name,
        p.description AS project_description,
        p.start_date,
        p.end_date,
        p.status AS project_status,
        c.company_name,
        ass.asset_name,
        ass.asset_type,
        ass.identifier,
        ass.description AS asset_description,
        rl.name AS risk_level_name,
        rl.severity_score,
        r.remediation_id,
        r.description AS remediation_description,
        r.status AS remediation_status,
        r.submitted_at,
        r.resolved_at,
        r.verified_at
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
     LEFT JOIN remediations AS r
        ON r.finding_id = f.finding_id
       AND r.client_id = :remediation_client_id
     WHERE f.finding_id = :finding_id
       AND f.status = 'confirmed'
       AND p.client_id = :client_id
     LIMIT 1"
);
$statement->execute([
    'remediation_client_id' => $clientId,
    'finding_id' => $findingId,
    'client_id' => $clientId,
]);
$finding = $statement->fetch();
if ($finding === false) {
    set_flash(
        'error',
        'Finding غير موجودة أو لا تخص شركتك أو لم يتم اعتمادها.'
    );
    redirect('client/findings/index.php');
}
$remediationStatusLabels = [
    'pending' => 'بانتظار المعالجة',
    'in_progress' => 'قيد التنفيذ',
    'submitted' => 'بانتظار التحقق',
    'resolved' => 'تمت المعالجة',
    'verified' => 'تم التحقق',
    'rejected' => 'مرفوضة',
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
render_header('تفاصيل Finding');
?>
<section class="page-heading">
    <div>
        <p class="eyebrow">بوابة العميل</p>
        <h1>
            <?= e((string) $finding['title']) ?>
        </h1>
        <p class="muted">
            تفاصيل النتيجة الأمنية المعتمدة المرتبطة بأنظمة شركتك.
        </p>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('client/findings/index.php')) ?>" class="button button-primary">
            العودة إلى الثغرات
        </a>
    </div>
</section>
<section class="details-card">
    <div class="section-heading compact">
        <div>
            <h2>ملخص Finding</h2>
        </div>
        <span class="status-badge status-confirmed">
            معتمدة
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
                —
                <?= e((string) $finding['severity_score']) ?>/5
            </strong>
        </div>
        <div>
            <span class="detail-label">تاريخ الاكتشاف</span>
            <strong>
                <?= e((string) $finding['discovered_at']) ?>
            </strong>
        </div>
        <div>
            <span class="detail-label">الحالة</span>
            <span class="status-badge status-confirmed">
                معتمدة
            </span>
        </div>
    </div>
</section>
<section class="details-card">
    <div class="section-heading compact">
        <div>
            <h2>تفاصيل النتيجة الأمنية</h2>
            <p class="muted">
                المعلومات الفنية التي سجلها المحلل أثناء التقييم.
            </p>
        </div>
    </div>
    <div class="detail-section">
        <h3>الوصف</h3>
        <p>
            <?= nl2br(
                e(
                    (string) (
                        $finding['description']
                        ?? 'لا يوجد وصف.'
                    )
                )
            ) ?>
        </p>
    </div>
    <?php if (!empty($finding['technical_details'])): ?>
        <div class="detail-section">
            <h3>التفاصيل الفنية</h3>
            <p>
                <?= nl2br(
                    e(
                        (string) $finding['technical_details']
                    )
                ) ?>
            </p>
        </div>
    <?php endif; ?>
    <?php if (!empty($finding['evidence'])): ?>
        <div class="detail-section">
            <h3>الدليل</h3>
            <p>
                <?= nl2br(
                    e(
                        (string) $finding['evidence']
                    )
                ) ?>
            </p>
        </div>
    <?php endif; ?>
</section>
<section class="details-card">
    <div class="section-heading compact">
        <div>
            <h2>المشروع والتقييم</h2>
        </div>
    </div>
    <div class="detail-grid">
        <div>
            <span class="detail-label">المشروع</span>
            <strong>
                <?= e((string) $finding['project_name']) ?>
            </strong>
        </div>
        <div>
            <span class="detail-label">التقييم</span>
            <strong>
                <?= e((string) $finding['assessment_name']) ?>
            </strong>
        </div>
        <div>
            <span class="detail-label">حالة المشروع</span>
            <span class="status-badge status-<?= e(
                (string) $finding['project_status']
            ) ?>">
                <?= e(
                    $projectStatusLabels[
                        (string) $finding['project_status']
                    ]
                    ?? (string) $finding['project_status']
                ) ?>
            </span>
        </div>
        <div>
            <span class="detail-label">بداية المشروع</span>
            <strong>
                <?= e(
                    (string) (
                        $finding['start_date']
                        ?? '—'
                    )
                ) ?>
            </strong>
        </div>
        <div>
            <span class="detail-label">نهاية المشروع</span>
            <strong>
                <?= e(
                    (string) (
                        $finding['end_date']
                        ?? '—'
                    )
                ) ?>
            </strong>
        </div>
    </div>
    <div class="detail-section">
        <h3>وصف المشروع</h3>
        <p>
            <?= nl2br(
                e(
                    (string) (
                        $finding['project_description']
                        ?? 'لا يوجد وصف للمشروع.'
                    )
                )
            ) ?>
        </p>
    </div>
</section>
<section class="details-card">
    <div class="section-heading compact">
        <div>
            <h2>الأصل المتأثر</h2>
            <p class="muted">
                النظام أو الخدمة التي تم اكتشاف Finding عليها.
            </p>
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
                <?= e(
                    (string) (
                        $finding['identifier']
                        ?? '—'
                    )
                ) ?>
            </strong>
        </div>
    </div>
    <div class="detail-section">
        <h3>وصف الأصل</h3>
        <p>
            <?= nl2br(
                e(
                    (string) (
                        $finding['asset_description']
                        ?? 'لا يوجد وصف للأصل.'
                    )
                )
            ) ?>
        </p>
    </div>
</section>
<section class="details-card">
    <div class="section-heading compact">
        <div>
            <h2>المعالجة الأمنية</h2>
            <p class="muted">
                متابعة الإجراء التصحيحي المرتبط بهذه Finding.
            </p>
        </div>
    </div>
    <?php if ($finding['remediation_id'] === null): ?>
        <div class="empty-state">
            <h3>لم تبدأ المعالجة</h3>
            <p class="muted">
                يمكنك إنشاء معالجة لهذه Finding من خلال الزر أدناه.
            </p>
        </div>
        <div class="form-actions">
            <a href="<?= e(
                url(
                    'client/remediations/create.php?finding_id='
                    . $findingId
                )
            ) ?>" class="button button-primary">
                إنشاء معالجة
            </a>
        </div>
    <?php else: ?>
        <div class="detail-grid">
            <div>
                <span class="detail-label">حالة المعالجة</span>
                <span class="status-badge status-<?= e(
                    (string) $finding['remediation_status']
                ) ?>">
                    <?= e(
                        $remediationStatusLabels[
                            (string) $finding['remediation_status']
                        ]
                        ?? (string) $finding['remediation_status']
                    ) ?>
                </span>
            </div>
            <div>
                <span class="detail-label">تاريخ الإرسال</span>
                <strong>
                    <?= e(
                        (string) (
                            $finding['submitted_at']
                            ?? '—'
                        )
                    ) ?>
                </strong>
            </div>
            <div>
                <span class="detail-label">تاريخ الإتمام</span>
                <strong>
                    <?= e(
                        (string) (
                            $finding['resolved_at']
                            ?? '—'
                        )
                    ) ?>
                </strong>
            </div>
            <div>
                <span class="detail-label">تاريخ التحقق</span>
                <strong>
                    <?= e(
                        (string) (
                            $finding['verified_at']
                            ?? '—'
                        )
                    ) ?>
                </strong>
            </div>
        </div>
        <div class="form-actions">
            <a href="<?= e(
                url(
                    'client/remediations/view.php?remediation_id='
                    . $finding['remediation_id']
                )
            ) ?>" class="button button-primary">
                فتح المعالجة
            </a>
        </div>
    <?php endif; ?>
</section>
<?php render_footer(); ?>