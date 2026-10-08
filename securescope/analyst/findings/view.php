<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_role('Security Analyst');
$user = current_user();
if ($user === null) {
    logout_user();
    redirect('login.php');
}
$findingId = filter_input(INPUT_GET, 'finding_id', FILTER_VALIDATE_INT);
if ($findingId === false || $findingId === null || $findingId <= 0) {
    set_flash('error', 'معرف Finding غير صالح.');
    redirect('analyst/findings/index.php');
}
$statement = db()->prepare(
    "SELECT
        f.finding_id,
        f.assessment_id,
        f.asset_id,
        f.risk_level_id,
        f.title,
        f.description,
        f.technical_details,
        f.status,
        f.discovered_by,
        f.discovered_at,
        f.reviewed_by,
        f.reviewed_at,
        f.created_at,
        f.updated_at,
        a.assessment_name,
        a.status AS assessment_status,
        p.project_id,
        p.project_name,
        p.status AS project_status,
        c.client_id,
        c.company_name,
        ass.asset_name,
        ass.asset_type,
        ass.identifier,
        ass.description AS asset_description,
        rl.name AS risk_level_name,
        rl.severity_score,
        rl.description AS risk_level_description,
        analyst.first_name AS analyst_first_name,
        analyst.last_name AS analyst_last_name,
        r.remediation_id,
        r.description AS remediation_description,
        r.retest_notes,
        r.status AS remediation_status,
        r.submitted_at,
        r.resolved_at,
        r.verified_by,
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
     INNER JOIN users AS analyst
        ON analyst.user_id = f.discovered_by
     LEFT JOIN remediations AS r
        ON r.finding_id = f.finding_id
     WHERE f.finding_id = :finding_id
       AND a.assigned_to = :assigned_to
     LIMIT 1"
);
$statement->execute([
    'finding_id' => $findingId,
    'assigned_to' => (int) $user['user_id'],
]);
$finding = $statement->fetch();
if ($finding === false) {
    set_flash('error', 'Finding غير موجودة أو لا تملك صلاحية الوصول إليها.');
    redirect('analyst/findings/index.php');
}
$evidenceStatement = db()->prepare(
    "SELECT
        e.evidence_id,
        e.file_name,
        e.file_type,
        e.description,
        e.uploaded_by,
        e.created_at,
        u.first_name,
        u.last_name
     FROM evidence AS e
     INNER JOIN users AS u
        ON u.user_id = e.uploaded_by
     WHERE e.finding_id = :finding_id
     ORDER BY e.created_at DESC"
);
$evidenceStatement->execute([
    'finding_id' => $findingId,
]);
$evidence = $evidenceStatement->fetchAll();
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
$errors = [];
if (is_post_request()) {
    require_valid_csrf();
    $action = trim((string) ($_POST['action'] ?? ''));
    $retestNotes = trim((string) ($_POST['retest_notes'] ?? ''));
    if ($finding['remediation_id'] === null) {
        $errors[] = 'لا توجد معالجة مرتبطة بهذه الثغرة.';
    }
    if (
        $finding['remediation_id'] !== null
        && (string) $finding['remediation_status'] !== 'submitted'
    ) {
        $errors[] = 'لا توجد حاليًا معالجة بانتظار إعادة الاختبار.';
    }
    if ($retestNotes === '') {
        $errors[] = 'يجب كتابة ملاحظات إعادة الاختبار.';
    }
    if (strlen($retestNotes) > 10000) {
        $errors[] = 'ملاحظات إعادة الاختبار طويلة جدًا.';
    }
    if ($action !== 'retest_resolved' && $action !== 'retest_rejected') {
        $errors[] = 'نتيجة إعادة الاختبار غير صالحة.';
    }
    if ($errors === []) {
        try {
            db()->beginTransaction();
            if ($action === 'retest_resolved') {
                $updateStatement = db()->prepare(
                    "UPDATE remediations
                     SET
                        retest_notes = :retest_notes,
                        status = 'resolved',
                        resolved_at = NOW(),
                        updated_at = NOW()
                     WHERE remediation_id = :remediation_id
                       AND status = 'submitted'"
                );
                $updateStatement->execute([
                    'retest_notes' => $retestNotes,
                    'remediation_id' => (int) $finding['remediation_id'],
                ]);
                if ($updateStatement->rowCount() !== 1) {
                    throw new RuntimeException('تعذر تسجيل نجاح إعادة الاختبار.');
                }
                $findingUpdate = db()->prepare(
                    "UPDATE findings
                     SET
                        status = 'resolved',
                        updated_at = NOW()
                     WHERE finding_id = :finding_id"
                );
                $findingUpdate->execute([
                    'finding_id' => $findingId,
                ]);
                record_audit(
                    'RETEST_RESOLVED',
                    'remediations',
                    (int) $finding['remediation_id'],
                    ['status' => 'submitted'],
                    [
                        'status' => 'resolved',
                        'finding_id' => $findingId,
                        'tested_by' => (int) $user['user_id'],
                    ]
                );
                db()->commit();
                set_flash(
                    'success',
                    'تم تسجيل نجاح إعادة الاختبار. الثغرة بانتظار الاعتماد النهائي.'
                );
            } else {
                $updateStatement = db()->prepare(
                    "UPDATE remediations
                     SET
                        retest_notes = :retest_notes,
                        status = 'rejected',
                        updated_at = NOW()
                     WHERE remediation_id = :remediation_id
                       AND status = 'submitted'"
                );
                $updateStatement->execute([
                    'retest_notes' => $retestNotes,
                    'remediation_id' => (int) $finding['remediation_id'],
                ]);
                if ($updateStatement->rowCount() !== 1) {
                    throw new RuntimeException('تعذر تسجيل فشل إعادة الاختبار.');
                }
                $findingUpdate = db()->prepare(
                    "UPDATE findings
                     SET
                        status = 'remediation',
                        updated_at = NOW()
                     WHERE finding_id = :finding_id"
                );
                $findingUpdate->execute([
                    'finding_id' => $findingId,
                ]);
                record_audit(
                    'RETEST_REJECTED',
                    'remediations',
                    (int) $finding['remediation_id'],
                    ['status' => 'submitted'],
                    [
                        'status' => 'rejected',
                        'finding_id' => $findingId,
                        'tested_by' => (int) $user['user_id'],
                    ]
                );
                db()->commit();
                set_flash(
                    'error',
                    'لم تنجح إعادة الاختبار. تحتاج الثغرة إلى معالجة إضافية.'
                );
            }
            redirect('analyst/findings/view.php?finding_id=' . $findingId);
        } catch (Throwable $exception) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            error_log(
                'SecureScope finding retest error: '
                . $exception->getMessage()
            );
            $errors[] = 'حدث خطأ أثناء تسجيل نتيجة إعادة الاختبار.';
        }
    }
}
render_header('تفاصيل Finding');
?>
<section class="page-heading">
    <div>
        <p class="eyebrow">المحلل الأمني</p>
        <h1><?= e((string) $finding['title']) ?></h1>
        <p class="muted">تفاصيل النتيجة الأمنية المسجلة ضمن التقييم.</p>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('analyst/findings/index.php')) ?>" class="button button-primary">
            العودة إلى Findings
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
            <span class="detail-label">عنوان Finding</span>
            <strong><?= e((string) $finding['title']) ?></strong>
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
            <strong><?= e((string) $finding['discovered_at']) ?></strong>
        </div>
        <div>
            <span class="detail-label">اكتشف بواسطة</span>
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
            <h2>التقييم</h2>
        </div>
    </div>
    <div class="detail-grid">
        <div>
            <span class="detail-label">اسم التقييم</span>
            <strong><?= e((string) $finding['assessment_name']) ?></strong>
        </div>
        <div>
            <span class="detail-label">حالة التقييم</span>
            <span class="status-badge status-<?= e((string) $finding['assessment_status']) ?>">
                <?= e(
                    $assessmentStatusLabels[(string) $finding['assessment_status']]
                    ?? (string) $finding['assessment_status']
                ) ?>
            </span>
        </div>
        <div>
            <span class="detail-label">المشروع</span>
            <strong><?= e((string) $finding['project_name']) ?></strong>
        </div>
        <div>
            <span class="detail-label">العميل</span>
            <strong><?= e((string) $finding['company_name']) ?></strong>
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
            <strong><?= e((string) $finding['asset_name']) ?></strong>
        </div>
        <div>
            <span class="detail-label">نوع الأصل</span>
            <strong><?= e((string) $finding['asset_type']) ?></strong>
        </div>
        <div>
            <span class="detail-label">المعرّف</span>
            <strong><?= e((string) ($finding['identifier'] ?? '—')) ?></strong>
        </div>
    </div>
    <?php if (!empty($finding['asset_description'])): ?>
        <div class="detail-section">
            <h3>وصف الأصل</h3>
            <p><?= nl2br(e((string) $finding['asset_description'])) ?></p>
        </div>
    <?php endif; ?>
</section>
<section class="details-card">
    <div class="section-heading compact">
        <div>
            <h2>التفاصيل الأمنية</h2>
        </div>
    </div>
    <div class="detail-section">
        <h3>الوصف</h3>
        <p><?= nl2br(e((string) $finding['description'])) ?></p>
    </div>
    <div class="detail-section">
        <h3>التفاصيل الفنية</h3>
        <?php if (!empty($finding['technical_details'])): ?>
            <p><?= nl2br(e((string) $finding['technical_details'])) ?></p>
        <?php else: ?>
            <p class="muted">لم تتم إضافة تفاصيل فنية.</p>
        <?php endif; ?>
    </div>
</section>
<section class="details-card">
    <div class="section-heading compact">
        <div>
            <h2>معلومات الخطورة</h2>
        </div>
    </div>
    <div class="detail-grid">
        <div>
            <span class="detail-label">مستوى الخطورة</span>
            <strong><?= e((string) $finding['risk_level_name']) ?></strong>
        </div>
        <div>
            <span class="detail-label">درجة الخطورة</span>
            <strong><?= e((string) $finding['severity_score']) ?>/5</strong>
        </div>
    </div>
    <div class="detail-section">
        <h3>وصف مستوى الخطورة</h3>
        <p><?= nl2br(e((string) $finding['risk_level_description'])) ?></p>
    </div>
</section>
<section class="details-card">
    <div class="section-heading compact">
        <div>
            <h2>أدلة الفحص</h2>
            <p class="muted">
                الأدلة التي تدعم نتيجة الفحص الأمني.
            </p>
        </div>
        <div class="page-actions">
            <a href="<?= e(
                url(
                    'analyst/evidence/create.php?finding_id='
                    . $findingId
                )
            ) ?>" class="button button-primary">
                إضافة دليل
            </a>
        </div>
    </div>
    <?php if ($evidence === []): ?>
        <div class="empty-state">
            <h3>لا توجد أدلة</h3>
            <p class="muted">
                لم يتم إرفاق أي دليل بهذه Finding حتى الآن.
            </p>
        </div>
    <?php else: ?>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>الملف</th>
                        <th>النوع</th>
                        <th>الوصف</th>
                        <th>بواسطة</th>
                        <th>التاريخ</th>
                        <th>الإجراء</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($evidence as $item): ?>
                        <tr>
                            <td>
                                <strong>
                                    <?= e((string) $item['file_name']) ?>
                                </strong>
                            </td>
                            <td>
                                <?= e((string) $item['file_type']) ?>
                            </td>
                            <td>
                                <?= e(
                                    (string) (
                                        $item['description']
                                        ?? '—'
                                    )
                                ) ?>
                            </td>
                            <td>
                                <?= e(
                                    trim(
                                        (string) $item['first_name']
                                        . ' '
                                        . (string) $item['last_name']
                                    )
                                ) ?>
                            </td>
                            <td>
                                <?= e((string) $item['created_at']) ?>
                            </td>
                            <td>
                                <a href="<?= e(
                                    url(
                                        'analyst/evidence/download.php?evidence_id='
                                        . (int) $item['evidence_id']
                                    )
                                ) ?>" class="button button-small" target="_blank" rel="noopener">
                                    فتح الدليل
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php if ($finding['remediation_id'] !== null): ?>
    <section class="details-card">
        <div class="section-heading compact">
            <div>
                <h2>معالجة الثغرة</h2>
                <p class="muted">
                    المعلومات التي أرسلها العميل حول معالجة الثغرة.
                </p>
            </div>
            <span class="status-badge status-<?= e((string) $finding['remediation_status']) ?>">
                <?= e(
                    match ((string) $finding['remediation_status']) {
                        'pending' => 'بانتظار الإجراء',
                        'in_progress' => 'قيد المعالجة',
                        'submitted' => 'بانتظار إعادة الاختبار',
                        'resolved' => 'تمت المعالجة',
                        'verified' => 'تم التحقق',
                        'rejected' => 'تحتاج إلى إجراء إضافي',
                        default => (string) $finding['remediation_status'],
                    }
                ) ?>
            </span>
        </div>
        <div class="detail-section">
            <h3>وصف المعالجة</h3>
            <p>
                <?= nl2br(
                    e(
                        (string) (
                            $finding['remediation_description']
                            ?? 'لا يوجد وصف.'
                        )
                    )
                ) ?>
            </p>
        </div>
        <?php if (!empty($finding['submitted_at'])): ?>
            <div class="detail-grid">
                <div>
                    <span class="detail-label">تاريخ طلب إعادة الاختبار</span>
                    <strong><?= e((string) $finding['submitted_at']) ?></strong>
                </div>
                <?php if (!empty($finding['resolved_at'])): ?>
                    <div>
                        <span class="detail-label">تاريخ نجاح إعادة الاختبار</span>
                        <strong><?= e((string) $finding['resolved_at']) ?></strong>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($finding['retest_notes'])): ?>
            <div class="detail-section">
                <h3>ملاحظات إعادة الاختبار السابقة</h3>
                <p><?= nl2br(e((string) $finding['retest_notes'])) ?></p>
            </div>
        <?php endif; ?>
        <?php if ((string) $finding['remediation_status'] === 'submitted'): ?>
            <div class="detail-section">
                <h3>تنفيذ إعادة الاختبار</h3>
                <p class="muted">
                    قم بإعادة فحص النظام فعليًا، ثم سجّل نتيجة الاختبار وملاحظاتك.
                </p>
            </div>
            <form method="post">
                <?= csrf_input() ?>
                <div class="form-group">
                    <label for="retest_notes">ملاحظات إعادة الاختبار</label>
                    <textarea id="retest_notes" name="retest_notes" rows="8" maxlength="10000" required
                        placeholder="اكتب ما تم فحصه، وكيف تم التحقق من نجاح أو فشل معالجة الثغرة..."></textarea>
                </div>
                <div class="form-actions">
                    <button type="submit" name="action" value="retest_resolved" class="button button-primary">
                        تمت معالجة الثغرة
                    </button>
                    <button type="submit" name="action" value="retest_rejected" class="button button-danger">
                        الثغرة ما زالت موجودة
                    </button>
                </div>
            </form>
        <?php elseif ((string) $finding['remediation_status'] === 'resolved'): ?>
            <div class="alert alert-success">
                تم تنفيذ إعادة الاختبار، وأثبت المحلل أن المعالجة نجحت.
                النتيجة بانتظار الاعتماد النهائي.
            </div>
        <?php elseif ((string) $finding['remediation_status'] === 'rejected'): ?>
            <div class="alert alert-error">
                لم تنجح إعادة الاختبار الحالية.
                يمكن للعميل إجراء إصلاح إضافي ثم إرسال طلب إعادة اختبار جديد.
            </div>
        <?php elseif ((string) $finding['remediation_status'] === 'verified'): ?>
            <div class="alert alert-success">
                تم التحقق من نجاح المعالجة واعتمادها نهائيًا.
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>
<section class="details-card">
    <div class="section-heading compact">
        <div>
            <h2>سجل المراجعة</h2>
        </div>
    </div>
    <div class="detail-grid">
        <div>
            <span class="detail-label">حالة المراجعة</span>
            <span class="status-badge status-<?= e((string) $finding['status']) ?>">
                <?= e(
                    $statusLabels[(string) $finding['status']]
                    ?? (string) $finding['status']
                ) ?>
            </span>
        </div>
        <div>
            <span class="detail-label">تاريخ المراجعة</span>
            <strong><?= e((string) ($finding['reviewed_at'] ?? '—')) ?></strong>
        </div>
        <div>
            <span class="detail-label">آخر تحديث</span>
            <strong><?= e((string) $finding['updated_at']) ?></strong>
        </div>
    </div>
</section>
<?php render_footer(); ?>