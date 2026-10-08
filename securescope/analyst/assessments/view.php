<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_role('Security Analyst');
$user = current_user();
if ($user === null) {
    logout_user();
    redirect('login.php');
}
$assessmentId = filter_input(
    INPUT_GET,
    'assessment_id',
    FILTER_VALIDATE_INT
);
if (
    $assessmentId === false
    || $assessmentId === null
    || $assessmentId <= 0
) {
    set_flash(
        'error',
        'معرف التقييم غير صالح.'
    );
    redirect(
        'analyst/assessments/index.php'
    );
}
$statement = db()->prepare(
    "SELECT
        a.*,
        p.client_id,
        p.project_name,
        p.description AS project_description,
        p.start_date,
        p.end_date,
        p.status AS project_status,
        c.company_name,
        c.company_email,
        c.phone AS company_phone,
        c.address AS company_address,
        at.name AS assessment_type_name
     FROM assessments AS a
     INNER JOIN projects AS p
        ON p.project_id = a.project_id
     INNER JOIN clients AS c
        ON c.client_id = p.client_id
     INNER JOIN assessment_types AS at
        ON at.assessment_type_id = p.assessment_type_id
     WHERE a.assessment_id = :assessment_id
       AND a.assigned_to = :assigned_to
     LIMIT 1"
);
$statement->execute([
    'assessment_id' => $assessmentId,
    'assigned_to' => (int) $user['user_id'],
]);
$assessment = $statement->fetch();
if ($assessment === false) {
    set_flash(
        'error',
        'التقييم غير موجود أو غير مسند إلى حسابك.'
    );
    redirect(
        'analyst/assessments/index.php'
    );
}

$assetsStatement = db()->prepare(
    "SELECT
        asset_id,
        asset_name,
        asset_type,
        identifier,
        description,
        status
     FROM assets
     WHERE client_id = :client_id
       AND status = 'active'
     ORDER BY asset_name"
);
$findingsStatement = db()->prepare(
    "SELECT
        f.finding_id,
        f.title,
        f.status,
        f.discovered_at,
        ass.asset_name,
        rl.name AS risk_level_name,
        rl.severity_score
     FROM findings AS f
     INNER JOIN assets AS ass
        ON ass.asset_id = f.asset_id
     INNER JOIN risk_levels AS rl
        ON rl.risk_level_id = f.risk_level_id
     WHERE f.assessment_id = :assessment_id
       AND f.discovered_by = :discovered_by
     ORDER BY f.discovered_at DESC"
);
$findingsStatement->execute([
    'assessment_id' => $assessmentId,
    'discovered_by' => (int) $user['user_id'],
]);
$findings = $findingsStatement->fetchAll();
$assetsStatement->execute([
    'client_id' => (int) $assessment['client_id'],
]);
$assets = $assetsStatement->fetchAll();
$statusLabels = [
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
$errors = [];
if (is_post_request()) {
    require_valid_csrf();
    $action = trim(
        (string) ($_POST['action'] ?? '')
    );
    if ($action === 'start_assessment') {
        if ((string) $assessment['status'] !== 'pending') {
            $errors[] =
                'لا يمكن بدء هذا التقييم لأن حالته الحالية لا تسمح بذلك.';
        }
        if ($errors === []) {
            try {
                db()->beginTransaction();
                $updateStatement = db()->prepare(
                    "UPDATE assessments
                     SET
                        status = 'in_progress',
                        started_at = NOW()
                     WHERE assessment_id = :assessment_id
                       AND assigned_to = :assigned_to
                       AND status = 'pending'"
                );
                $updateStatement->execute([
                    'assessment_id' => $assessmentId,
                    'assigned_to' => (int) $user['user_id'],
                ]);
                if ($updateStatement->rowCount() !== 1) {
                    throw new RuntimeException(
                        'تعذر بدء التقييم.'
                    );
                }
                record_audit(
                    'START_ASSESSMENT',
                    'assessments',
                    $assessmentId,
                    [
                        'status' => 'pending',
                    ],
                    [
                        'status' => 'in_progress',
                        'started_by' => (int) $user['user_id'],
                    ]
                );
                db()->commit();
                set_flash(
                    'success',
                    'تم بدء التقييم الأمني بنجاح.'
                );
                redirect(
                    'analyst/assessments/view.php?assessment_id='
                    . $assessmentId
                );
            } catch (Throwable $exception) {
                if (db()->inTransaction()) {
                    db()->rollBack();
                }
                error_log(
                    'SecureScope assessment start error: '
                    . $exception->getMessage()
                );
                $errors[] =
                    'حدث خطأ أثناء بدء التقييم. حاول مرة أخرى.';
            }
        }
    }
}
render_header('التقييم الأمني');
?>
<section class="page-heading">
    <div>
        <p class="eyebrow">المحلل الأمني</p>
        <h1>
            <?= e((string) $assessment['assessment_name']) ?>
        </h1>
        <p class="muted">
            تنفيذ ومتابعة التقييم الأمني المسند إليك.
        </p>
    </div>
    <div class="page-actions">
        <a
            href="<?= e(url('analyst/assessments/index.php')) ?>"
            class="button button-primary"
        >
            العودة إلى التقييمات
        </a>
    </div>
</section>
<?php if ($errors !== []): ?>
    <section class="details-card">
        <div class="alert alert-error" role="alert">
            <ul>
                <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </section>
<?php endif; ?>
<section class="details-card">
    <div class="section-heading compact">
        <div>
            <h2>حالة التقييم</h2>
        </div>
        <span class="status-badge status-<?= e((string) $assessment['status']) ?>">
            <?= e(
                $statusLabels[(string) $assessment['status']]
                ?? (string) $assessment['status']
            ) ?>
        </span>
    </div>
    <div class="detail-grid">
        <div>
            <span class="detail-label">اسم التقييم</span>
            <strong>
                <?= e((string) $assessment['assessment_name']) ?>
            </strong>
        </div>
        <div>
            <span class="detail-label">نوع التقييم</span>
            <strong>
                <?= e((string) $assessment['assessment_type_name']) ?>
            </strong>
        </div>
        <div>
            <span class="detail-label">تاريخ إنشاء التقييم</span>
            <strong>
                <?= e((string) $assessment['created_at']) ?>
            </strong>
        </div>
        <div>
            <span class="detail-label">تاريخ بدء التقييم</span>
            <strong>
                <?= e((string) ($assessment['started_at'] ?? '—')) ?>
            </strong>
        </div>
        <div>
            <span class="detail-label">تاريخ الإكمال</span>
            <strong>
                <?= e((string) ($assessment['completed_at'] ?? '—')) ?>
            </strong>
        </div>
    </div>
    <div class="detail-section">
        <h3>وصف التقييم</h3>
        <p>
            <?= nl2br(
                e(
                    (string) (
                        $assessment['description']
                        ?? 'لا يوجد وصف للتقييم.'
                    )
                )
            ) ?>
        </p>
    </div>
</section>
<section class="details-card">
    <div class="section-heading compact">
        <div>
            <h2>المشروع</h2>
            <p class="muted">
                النظام الذي سيتم تنفيذ التقييم الأمني عليه.
            </p>
        </div>
    </div>
    <div class="detail-grid">
        <div>
            <span class="detail-label">اسم المشروع</span>
            <strong>
                <?= e((string) $assessment['project_name']) ?>
            </strong>
        </div>
        <div>
            <span class="detail-label">العميل</span>
            <strong>
                <?= e((string) $assessment['company_name']) ?>
            </strong>
        </div>
        <div>
            <span class="detail-label">نوع التقييم</span>
            <strong>
                <?= e((string) $assessment['assessment_type_name']) ?>
            </strong>
        </div>
        <div>
            <span class="detail-label">حالة المشروع</span>
            <span class="status-badge status-<?= e((string) $assessment['project_status']) ?>">
                <?= e(
                    $projectStatusLabels[(string) $assessment['project_status']]
                    ?? (string) $assessment['project_status']
                ) ?>
            </span>
        </div>
        <div>
            <span class="detail-label">تاريخ بداية المشروع</span>
            <strong>
                <?= e((string) ($assessment['start_date'] ?? '—')) ?>
            </strong>
        </div>
        <div>
            <span class="detail-label">تاريخ نهاية المشروع</span>
            <strong>
                <?= e((string) ($assessment['end_date'] ?? '—')) ?>
            </strong>
        </div>
    </div>
    <div class="detail-section">
        <h3>وصف المشروع</h3>
        <p>
            <?= nl2br(
                e(
                    (string) (
                        $assessment['project_description']
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
            <h2>بيانات العميل</h2>
        </div>
    </div>
    <div class="detail-grid">
        <div>
            <span class="detail-label">الشركة</span>
            <strong>
                <?= e((string) $assessment['company_name']) ?>
            </strong>
        </div>
        <div>
            <span class="detail-label">البريد الإلكتروني</span>
            <strong>
                <?= e((string) ($assessment['company_email'] ?? '—')) ?>
            </strong>
        </div>
        <div>
            <span class="detail-label">الهاتف</span>
            <strong>
                <?= e((string) ($assessment['company_phone'] ?? '—')) ?>
            </strong>
        </div>
        <div>
            <span class="detail-label">العنوان</span>
            <strong>
                <?= e((string) ($assessment['company_address'] ?? '—')) ?>
            </strong>
        </div>
    </div>
</section>
<section class="details-card">
    <div class="section-heading compact">
        <div>
            <h2>الأصول المستهدفة</h2>
            <p class="muted">
                الأنظمة والخدمات التابعة للعميل والمتاحة لهذا التقييم.
            </p>
        </div>
    </div>
    <?php if ($assets === []): ?>
        <div class="empty-state">
            <h3>لا توجد أصول نشطة</h3>
            <p class="muted">
                لم تتم إضافة أصول نشطة لهذا العميل حتى الآن.
            </p>
        </div>
    <?php else: ?>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>الأصل</th>
                        <th>النوع</th>
                        <th>المعرّف</th>
                        <th>الوصف</th>
                        <th>الحالة</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($assets as $asset): ?>
                        <tr>
                            <td>
                                <strong>
                                    <?= e((string) $asset['asset_name']) ?>
                                </strong>
                            </td>
                            <td>
                                <?= e((string) $asset['asset_type']) ?>
                            </td>
                            <td>
                                <?= e((string) ($asset['identifier'] ?? '—')) ?>
                            </td>
                            <td>
                                <?= e((string) ($asset['description'] ?? '—')) ?>
                            </td>
                            <td>
                                <span class="status-badge status-active">
                                    نشط
                                </span>
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
            <h2>بدء العمل</h2>
            <p class="muted">
                بعد بدء التقييم يمكنك الانتقال إلى تسجيل نتائج الفحص الأمني.
            </p>
        </div>
    </div>
    <?php if ($assessment['status'] === 'pending'): ?>
            <form method="post">
                <?= csrf_input() ?>
                <input
                    type="hidden"
                    name="action"
                    value="start_assessment"
                >
                <div class="form-actions">
                    <button
                        type="submit"
                        class="button button-primary"
                    >
                        بدء التقييم
                    </button>
                </div>
            </form>
    <?php elseif ($assessment['status'] === 'in_progress'): ?>
            <div class="alert alert-success">
                التقييم قيد التنفيذ. يمكنك الآن تسجيل نتائج الفحص الأمني.
            </div>
            <div class="form-actions">
                <a
                    href="<?= e(
                        url(
                            'analyst/findings/create.php?assessment_id='
                            . $assessmentId
                        )
                    ) ?>"
                    class="button button-primary"
                >
                    إضافة Finding
                </a>
            </div>
    <?php elseif ($assessment['status'] === 'under_review'): ?>
            <div class="alert alert-success">
                تم إرسال التقييم للمراجعة.
            </div>
    <?php elseif ($assessment['status'] === 'completed'): ?>
            <div class="alert alert-success">
                تم إكمال هذا التقييم.
            </div>
    <?php elseif ($assessment['status'] === 'cancelled'): ?>
            <div class="alert alert-error">
                تم إلغاء هذا التقييم.
            </div>
    <?php endif; ?>
</section>
<section class="details-card">
    <div class="section-heading compact">
        <div>
            <h2>النتائج الأمنية</h2>
            <p class="muted">
                Findings التي تم اكتشافها وتسجيلها ضمن هذا التقييم.
            </p>
        </div>
    </div>
    <?php if ($findings === []): ?>
        <div class="empty-state">
            <h3>لم تتم إضافة نتائج بعد</h3>
            <p class="muted">
                بعد بدء التقييم يمكنك تسجيل الـ Findings المرتبطة بالأصول التي يتم فحصها.
            </p>
        </div>
    <?php else: ?>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Finding</th>
                        <th>الأصل</th>
                        <th>الخطورة</th>
                        <th>الحالة</th>
                        <th>تاريخ الاكتشاف</th>
                        <th>فتح</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($findings as $finding): ?>
                        <tr>
                            <td>
                                <strong>
                                    <?= e((string) $finding['title']) ?>
                                </strong>
                            </td>
                            <td>
                                <?= e((string) $finding['asset_name']) ?>
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
                                <span class="status-badge status-<?= e(
                                    (string) $finding['status']
                                ) ?>">
                                    <?= e((string) $finding['status']) ?>
                                </span>
                            </td>
                            <td>
                                <?= e((string) $finding['discovered_at']) ?>
                            </td>
                            <td>
                                <a
                                    href="<?= e(
                                        url(
                                            'analyst/findings/view.php?finding_id='
                                            . $finding['finding_id']
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