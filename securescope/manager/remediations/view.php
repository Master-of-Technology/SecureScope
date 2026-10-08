<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_role('Security Manager');
$user = current_user();
if ($user === null) {
    logout_user();
    redirect('login.php');
}
$remediationId = filter_input(
    INPUT_GET,
    'remediation_id',
    FILTER_VALIDATE_INT
);
if (
    $remediationId === false
    || $remediationId === null
    || $remediationId <= 0
) {
    set_flash(
        'error',
        'معرف المعالجة غير صالح.'
    );
    redirect('manager/remediations/index.php');
}
$statement = db()->prepare(
    "SELECT
        r.*,
        f.title AS finding_title,
        f.description AS finding_description,
        f.technical_details,
        f.status AS finding_status,
        rl.name AS risk_level_name,
        rl.severity_score,
        a.assessment_name,
        a.status AS assessment_status,
        p.project_name,
        p.description AS project_description,
        p.start_date,
        p.end_date,
        p.status AS project_status,
        c.company_name,
        c.company_email,
        c.phone AS company_phone,
        c.address AS company_address,
        ass.asset_name,
        ass.asset_type,
        ass.identifier,
        ass.description AS asset_description
     FROM remediations AS r
     INNER JOIN findings AS f
        ON f.finding_id = r.finding_id
     INNER JOIN risk_levels AS rl
        ON rl.risk_level_id = f.risk_level_id
     INNER JOIN assessments AS a
        ON a.assessment_id = f.assessment_id
     INNER JOIN projects AS p
        ON p.project_id = a.project_id
     INNER JOIN clients AS c
        ON c.client_id = r.client_id
     INNER JOIN assets AS ass
        ON ass.asset_id = f.asset_id
     WHERE r.remediation_id = :remediation_id
     LIMIT 1"
);
$statement->execute([
    'remediation_id' => $remediationId,
]);
$remediation = $statement->fetch();
if ($remediation === false) {
    set_flash(
        'error',
        'المعالجة غير موجودة.'
    );
    redirect('manager/remediations/index.php');
}
$statusLabels = [
    'pending' => 'بانتظار المعالجة',
    'in_progress' => 'قيد التنفيذ',
    'submitted' => 'بانتظار إعادة الاختبار',
    'resolved' => 'بانتظار اعتماد المدير',
    'verified' => 'تم التحقق',
    'rejected' => 'تحتاج إلى معالجة إضافية',
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
    if (
        $action !== 'verify'
        && $action !== 'reject'
    ) {
        $errors[] = 'الإجراء المطلوب غير صالح.';
    }
    if (
        $errors === []
        && (string) $remediation['status'] !== 'resolved'
    ) {
        $errors[] =
            'لا يمكن اعتماد أو رفض المعالجة إلا بعد نجاح إعادة الاختبار.';
    }
    if ($errors === []) {
        try {
            db()->beginTransaction();
            if ($action === 'verify') {
                $updateRemediation = db()->prepare(
                    "UPDATE remediations
                     SET
                        status = 'verified',
                        verified_by = :verified_by,
                        verified_at = NOW(),
                        updated_at = NOW()
                     WHERE remediation_id = :remediation_id
                       AND status = 'resolved'"
                );
                $updateRemediation->execute([
                    'verified_by' => (int) $user['user_id'],
                    'remediation_id' => $remediationId,
                ]);
                if ($updateRemediation->rowCount() !== 1) {
                    throw new RuntimeException(
                        'تعذر اعتماد المعالجة.'
                    );
                }
                $updateFinding = db()->prepare(
                    "UPDATE findings
                     SET
                        status = 'verified',
                        updated_at = NOW()
                     WHERE finding_id = :finding_id"
                );
                $updateFinding->execute([
                    'finding_id' => (int) $remediation['finding_id'],
                ]);
                record_audit(
                    'VERIFY_REMEDIATION',
                    'remediations',
                    $remediationId,
                    [
                        'status' => 'resolved',
                    ],
                    [
                        'status' => 'verified',
                        'verified_by' => (int) $user['user_id'],
                    ]
                );
                db()->commit();
                set_flash(
                    'success',
                    'تم اعتماد المعالجة والتحقق من إغلاق الثغرة بنجاح.'
                );
            } else {
                $updateRemediation = db()->prepare(
                    "UPDATE remediations
                     SET
                        status = 'rejected',
                        updated_at = NOW()
                     WHERE remediation_id = :remediation_id
                       AND status = 'resolved'"
                );
                $updateRemediation->execute([
                    'remediation_id' => $remediationId,
                ]);
                if ($updateRemediation->rowCount() !== 1) {
                    throw new RuntimeException(
                        'تعذر رفض نتيجة المعالجة.'
                    );
                }
                $updateFinding = db()->prepare(
                    "UPDATE findings
                     SET
                        status = 'remediation',
                        updated_at = NOW()
                     WHERE finding_id = :finding_id"
                );
                $updateFinding->execute([
                    'finding_id' => (int) $remediation['finding_id'],
                ]);
                record_audit(
                    'REJECT_REMEDIATION',
                    'remediations',
                    $remediationId,
                    [
                        'status' => 'resolved',
                    ],
                    [
                        'status' => 'rejected',
                        'rejected_by' => (int) $user['user_id'],
                    ]
                );
                db()->commit();
                set_flash(
                    'error',
                    'تم رفض نتيجة المعالجة وإعادتها للمراجعة.'
                );
            }
            redirect(
                'manager/remediations/view.php?remediation_id='
                . $remediationId
            );
        } catch (Throwable $exception) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            error_log(
                'SecureScope remediation verification error: '
                . $exception->getMessage()
            );
            $errors[] =
                'حدث خطأ أثناء تنفيذ العملية. حاول مرة أخرى.';
        }
    }
}
render_header('تفاصيل المعالجة');
?>
<section class="page-heading">
    <div>
        <p class="eyebrow">مدير الأمن</p>
        <h1>تفاصيل المعالجة الأمنية</h1>
        <p class="muted">
            مراجعة الإجراء التصحيحي ونتيجة إعادة الاختبار واعتمادها.
        </p>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('manager/remediations/index.php')) ?>" class="button button-primary">
            العودة إلى المعالجات
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
            <h2>حالة المعالجة</h2>
        </div>
        <span class="status-badge status-<?= e(
            (string) $remediation['status']
        ) ?>">
            <?= e(
                $statusLabels[
                    (string) $remediation['status']
                ]
                ?? (string) $remediation['status']
            ) ?>
        </span>
    </div>
    <div class="detail-grid">
        <div>
            <span class="detail-label">رقم المعالجة</span>
            <strong>
                #<?= e((string) $remediation['remediation_id']) ?>
            </strong>
        </div>
        <div>
            <span class="detail-label">تاريخ الإنشاء</span>
            <strong><?= e((string) $remediation['created_at']) ?></strong>
        </div>
        <div>
            <span class="detail-label">تاريخ الإرسال</span>
            <strong>
                <?= e((string) ($remediation['submitted_at'] ?? '—')) ?>
            </strong>
        </div>
        <div>
            <span class="detail-label">تاريخ نجاح إعادة الاختبار</span>
            <strong>
                <?= e((string) ($remediation['resolved_at'] ?? '—')) ?>
            </strong>
        </div>
        <div>
            <span class="detail-label">تاريخ التحقق النهائي</span>
            <strong>
                <?= e((string) ($remediation['verified_at'] ?? '—')) ?>
            </strong>
        </div>
    </div>
</section>
<section class="details-card">
    <div class="section-heading compact">
        <div>
            <h2>Finding المرتبطة</h2>
            <p class="muted">
                النتيجة الأمنية التي تتعامل معها هذه المعالجة.
            </p>
        </div>
        <span class="status-badge status-<?= e(
            (string) $remediation['finding_status']
        ) ?>">
            <?= e((string) $remediation['finding_status']) ?>
        </span>
    </div>
    <div class="detail-grid">
        <div>
            <span class="detail-label">Finding</span>
            <strong><?= e((string) $remediation['finding_title']) ?></strong>
        </div>
        <div>
            <span class="detail-label">مستوى الخطورة</span>
            <strong>
                <?= e((string) $remediation['risk_level_name']) ?>
                —
                <?= e((string) $remediation['severity_score']) ?>/5
            </strong>
        </div>
    </div>
    <div class="detail-section">
        <h3>وصف Finding</h3>
        <p>
            <?= nl2br(e((string) ($remediation['finding_description'] ?? 'لا يوجد وصف.'))) ?>
        </p>
    </div>
    <?php if (!empty($remediation['technical_details'])): ?>
        <div class="detail-section">
            <h3>التفاصيل الفنية</h3>
            <p>
                <?= nl2br(e((string) $remediation['technical_details'])) ?>
            </p>
        </div>
    <?php endif; ?>
</section>
<section class="details-card">
    <div class="section-heading compact">
        <div>
            <h2>الأصل المتأثر</h2>
        </div>
    </div>
    <div class="detail-grid">
        <div>
            <span class="detail-label">الأصل</span>
            <strong><?= e((string) $remediation['asset_name']) ?></strong>
        </div>
        <div>
            <span class="detail-label">النوع</span>
            <strong><?= e((string) $remediation['asset_type']) ?></strong>
        </div>
        <div>
            <span class="detail-label">المعرّف</span>
            <strong><?= e((string) ($remediation['identifier'] ?? '—')) ?></strong>
        </div>
    </div>
    <div class="detail-section">
        <h3>وصف الأصل</h3>
        <p>
            <?= nl2br(e((string) ($remediation['asset_description'] ?? 'لا يوجد وصف.'))) ?>
        </p>
    </div>
</section>
<section class="details-card">
    <div class="section-heading compact">
        <div>
            <h2>المشروع والعميل</h2>
        </div>
    </div>
    <div class="detail-grid">
        <div>
            <span class="detail-label">المشروع</span>
            <strong><?= e((string) $remediation['project_name']) ?></strong>
        </div>
        <div>
            <span class="detail-label">التقييم</span>
            <strong><?= e((string) $remediation['assessment_name']) ?></strong>
        </div>
        <div>
            <span class="detail-label">العميل</span>
            <strong><?= e((string) $remediation['company_name']) ?></strong>
        </div>
        <div>
            <span class="detail-label">البريد الإلكتروني</span>
            <strong><?= e((string) ($remediation['company_email'] ?? '—')) ?></strong>
        </div>
        <div>
            <span class="detail-label">الهاتف</span>
            <strong><?= e((string) ($remediation['company_phone'] ?? '—')) ?></strong>
        </div>
        <div>
            <span class="detail-label">حالة المشروع</span>
            <span class="status-badge status-<?= e((string) $remediation['project_status']) ?>">
                <?= e(
                    $projectStatusLabels[
                        (string) $remediation['project_status']
                    ]
                    ?? (string) $remediation['project_status']
                ) ?>
            </span>
        </div>
    </div>
</section>
<section class="details-card">
    <div class="section-heading compact">
        <div>
            <h2>الإجراء التصحيحي</h2>
            <p class="muted">
                ما قام العميل بتسجيله كإجراء لمعالجة Finding.
            </p>
        </div>
    </div>
    <div class="detail-section">
        <p>
            <?= nl2br(e((string) $remediation['description'])) ?>
        </p>
    </div>
</section>
<?php if (!empty($remediation['retest_notes'])): ?>
    <section class="details-card">
        <div class="section-heading compact">
            <div>
                <h2>نتيجة إعادة الاختبار</h2>
                <p class="muted">
                    الملاحظات التي سجلها المحلل بعد إعادة فحص النظام.
                </p>
            </div>
        </div>
        <div class="detail-section">
            <p>
                <?= nl2br(e((string) $remediation['retest_notes'])) ?>
            </p>
        </div>
    </section>
<?php endif; ?>
<section class="details-card">
    <div class="section-heading compact">
        <div>
            <h2>التحقق النهائي</h2>
            <p class="muted">
                يعتمد مدير الأمن نتيجة إعادة الاختبار بعد مراجعة الأدلة والملاحظات.
            </p>
        </div>
    </div>
    <?php if ($remediation['status'] === 'resolved'): ?>
        <div class="alert alert-success">
            نجح المحلل في إعادة الاختبار. هذه النتيجة بانتظار اعتمادك النهائي.
        </div>
        <form method="post">
            <?= csrf_input() ?>
            <div class="form-actions">
                <button type="submit" name="action" value="verify" class="button button-primary">
                    اعتماد المعالجة
                </button>
                <button type="submit" name="action" value="reject" class="button button-danger">
                    رفض النتيجة
                </button>
            </div>
        </form>
    <?php elseif ($remediation['status'] === 'verified'): ?>
        <div class="alert alert-success">
            تم التحقق من المعالجة واعتمادها نهائيًا.
        </div>
    <?php elseif ($remediation['status'] === 'rejected'): ?>
        <div class="alert alert-error">
            تم رفض نتيجة المعالجة وتحتاج الثغرة إلى معالجة إضافية.
        </div>
    <?php elseif ($remediation['status'] === 'submitted'): ?>
        <div class="alert alert-success">
            المعالجة أُرسلت من العميل وهي بانتظار إعادة الاختبار من المحلل.
        </div>
    <?php elseif ($remediation['status'] === 'pending'): ?>
        <div class="alert">
            لم يبدأ العميل تنفيذ المعالجة بعد.
        </div>
    <?php elseif ($remediation['status'] === 'in_progress'): ?>
        <div class="alert">
            العميل يعمل حاليًا على تنفيذ المعالجة.
        </div>
    <?php endif; ?>
</section>
<?php render_footer(); ?>