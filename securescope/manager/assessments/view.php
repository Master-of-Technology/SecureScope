<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

require_role('Security Manager');

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
        'manager/assessments/index.php'
    );
}

$statement = db()->prepare(
    "SELECT
        a.*,
        p.project_name,
        p.status AS project_status,
        c.client_id,
        c.company_name,
        c.company_email,
        u.first_name AS analyst_first_name,
        u.last_name AS analyst_last_name,
        u.email AS analyst_email
     FROM assessments AS a
     INNER JOIN projects AS p
        ON p.project_id = a.project_id
     INNER JOIN clients AS c
        ON c.client_id = p.client_id
     INNER JOIN users AS u
        ON u.user_id = a.assigned_to
     WHERE a.assessment_id = :assessment_id
     LIMIT 1"
);

$statement->execute([
    'assessment_id' => $assessmentId,
]);

$assessment = $statement->fetch();

if ($assessment === false) {
    set_flash(
        'error',
        'لم يتم العثور على التقييم.'
    );

    redirect(
        'manager/assessments/index.php'
    );
}

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

render_header('تفاصيل التقييم');
?>

<section class="page-heading">
    <div>
        <p class="eyebrow">إدارة التقييمات الأمنية</p>
        <h1>
            <?= e((string) $assessment['assessment_name']) ?>
        </h1>
        <p class="muted">
            تفاصيل التقييم الأمني ومعلومات المشروع والمحلل المسؤول.
        </p>
    </div>

    <div class="page-actions">
        <a href="<?= e(url('manager/assessments/index.php')) ?>" class="button button-primary">
            العودة إلى التقييمات
        </a>
    </div>
</section>

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
            <span class="detail-label">المشروع</span>
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
            <span class="detail-label">البريد الإلكتروني للعميل</span>
            <strong>
                <?= e((string) ($assessment['company_email'] ?? '—')) ?>
            </strong>
        </div>

        <div>
            <span class="detail-label">المحلل المسؤول</span>
            <strong>
                <?= e(
                    trim(
                        (string) $assessment['analyst_first_name']
                        . ' '
                        . (string) $assessment['analyst_last_name']
                    )
                ) ?>
            </strong>
        </div>

        <div>
            <span class="detail-label">بريد المحلل</span>
            <strong>
                <?= e((string) $assessment['analyst_email']) ?>
            </strong>
        </div>

        <div>
            <span class="detail-label">تاريخ إنشاء التقييم</span>
            <strong>
                <?= e((string) $assessment['created_at']) ?>
            </strong>
        </div>

        <div>
            <span class="detail-label">تاريخ البدء</span>
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
                        ?? 'لا يوجد وصف لهذا التقييم.'
                    )
                )
            ) ?>
        </p>
    </div>
</section>

<section class="details-card">
    <div class="section-heading compact">
        <div>
            <h2>المشروع المرتبط</h2>
            <p class="muted">
                معلومات مختصرة عن المشروع الذي ينتمي إليه هذا التقييم.
            </p>
        </div>

        <div class="page-actions">
            <a href="<?= e(
                url(
                    'manager/projects/view.php?project_id='
                    . (int) $assessment['project_id']
                )
            ) ?>" class="button button-primary">
                عرض المشروع
            </a>
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
            <span class="detail-label">حالة المشروع</span>
            <span class="status-badge status-<?= e((string) $assessment['project_status']) ?>">
                <?= e(
                    $projectStatusLabels[(string) $assessment['project_status']]
                    ?? (string) $assessment['project_status']
                ) ?>
            </span>
        </div>
    </div>
</section>

<section class="details-card">
    <div class="section-heading compact">
        <div>
            <h2>المحلل المسؤول</h2>
            <p class="muted">
                المحلل الذي تم إسناد هذا التقييم إليه.
            </p>
        </div>
    </div>

    <div class="detail-grid">
        <div>
            <span class="detail-label">الاسم</span>
            <strong>
                <?= e(
                    trim(
                        (string) $assessment['analyst_first_name']
                        . ' '
                        . (string) $assessment['analyst_last_name']
                    )
                ) ?>
            </strong>
        </div>

        <div>
            <span class="detail-label">البريد الإلكتروني</span>
            <strong>
                <?= e((string) $assessment['analyst_email']) ?>
            </strong>
        </div>
    </div>
</section>

<section class="details-card">
    <div class="section-heading compact">
        <div>
            <h2>نتائج التقييم</h2>
            <p class="muted">
                ستظهر هنا النتائج الأمنية والـ Findings والتوصيات بعد تنفيذها.
            </p>
        </div>
    </div>

    <div class="empty-state">
        <h3>لا توجد نتائج بعد</h3>
        <p class="muted">
            لم يتم تسجيل نتائج أمنية لهذا التقييم حتى الآن.
        </p>
    </div>
</section>

<section class="details-card">
    <div class="section-heading compact">
        <div>
            <h2>التقرير النهائي</h2>
            <p class="muted">
                سيظهر التقرير النهائي هنا بعد اكتمال التقييم وإنشائه.
            </p>
        </div>
    </div>

    <div class="empty-state">
        <h3>لا يوجد تقرير</h3>
        <p class="muted">
            لم يتم رفع أو إنشاء التقرير النهائي لهذا التقييم بعد.
        </p>
    </div>
</section>

<?php render_footer(); ?>