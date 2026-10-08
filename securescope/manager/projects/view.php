<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

require_role('Security Manager');

$projectId = filter_input(
    INPUT_GET,
    'project_id',
    FILTER_VALIDATE_INT
);

if (
    $projectId === false
    || $projectId === null
    || $projectId <= 0
) {
    set_flash(
        'error',
        'معرف المشروع غير صالح.'
    );

    redirect(
        'manager/projects/index.php'
    );
}

$statement = db()->prepare(
    "SELECT
        p.*,
        c.company_name,
        c.company_email,
        at.name AS assessment_type_name,
        creator.first_name AS creator_first_name,
        creator.last_name AS creator_last_name
     FROM projects AS p
     INNER JOIN clients AS c
        ON c.client_id = p.client_id
     INNER JOIN assessment_types AS at
        ON at.assessment_type_id = p.assessment_type_id
     INNER JOIN users AS creator
        ON creator.user_id = p.created_by
     WHERE p.project_id = :project_id
     LIMIT 1"
);

$statement->execute([
    'project_id' => $projectId,
]);

$project = $statement->fetch();

if ($project === false) {
    set_flash(
        'error',
        'لم يتم العثور على المشروع.'
    );

    redirect(
        'manager/projects/index.php'
    );
}

$assignmentsStatement = db()->prepare(
    "SELECT
        pa.assignment_id,
        pa.analyst_id,
        pa.assigned_by,
        pa.assigned_at,
        pa.status,
        analyst.first_name AS analyst_first_name,
        analyst.last_name AS analyst_last_name,
        analyst.email AS analyst_email,
        assigner.first_name AS assigner_first_name,
        assigner.last_name AS assigner_last_name
     FROM project_assignments AS pa
     INNER JOIN users AS analyst
        ON analyst.user_id = pa.analyst_id
     INNER JOIN users AS assigner
        ON assigner.user_id = pa.assigned_by
     WHERE pa.project_id = :project_id
     ORDER BY pa.assigned_at DESC"
);

$assignmentsStatement->execute([
    'project_id' => $projectId,
]);

$assignments = $assignmentsStatement->fetchAll();

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

$assignmentStatusLabels = [
    'active' => 'نشط',
    'completed' => 'مكتمل',
    'cancelled' => 'ملغى',
];

render_header('تفاصيل المشروع');
?>

<section class="page-heading">
    <div>
        <p class="eyebrow">إدارة المشاريع</p>
        <h1><?= e((string) $project['project_name']) ?></h1>
        <p class="muted">
            عرض تفاصيل المشروع والمحللين المسندين إليه.
        </p>
    </div>

    <div class="page-actions">
        <a
            href="<?= e(url('manager/projects/index.php')) ?>"
            class="button button-primary"
        >
            العودة إلى المشاريع
        </a>
    </div>
</section>

<section class="details-card">
    <div class="section-heading compact">
        <div>
            <h2>معلومات المشروع</h2>
        </div>

        <span class="status-badge status-<?= e((string) $project['status']) ?>">
            <?= e(
                $statusLabels[(string) $project['status']]
                ?? (string) $project['status']
            ) ?>
        </span>
    </div>

    <div class="detail-grid">
        <div>
            <span class="detail-label">اسم المشروع</span>
            <strong>
                <?= e((string) $project['project_name']) ?>
            </strong>
        </div>

        <div>
            <span class="detail-label">العميل</span>
            <strong>
                <?= e((string) $project['company_name']) ?>
            </strong>
        </div>

        <div>
            <span class="detail-label">البريد الإلكتروني للعميل</span>
            <strong>
                <?= e((string) ($project['company_email'] ?? '—')) ?>
            </strong>
        </div>

        <div>
            <span class="detail-label">نوع التقييم</span>
            <strong>
                <?= e((string) $project['assessment_type_name']) ?>
            </strong>
        </div>

        <div>
            <span class="detail-label">تاريخ البداية</span>
            <strong>
                <?= e((string) ($project['start_date'] ?? '—')) ?>
            </strong>
        </div>

        <div>
            <span class="detail-label">تاريخ النهاية</span>
            <strong>
                <?= e((string) ($project['end_date'] ?? '—')) ?>
            </strong>
        </div>

        <div>
            <span class="detail-label">أنشئ بواسطة</span>
            <strong>
                <?= e(
                    trim(
                        (string) $project['creator_first_name']
                        . ' '
                        . (string) $project['creator_last_name']
                    )
                ) ?>
            </strong>
        </div>

        <div>
            <span class="detail-label">تاريخ الإنشاء</span>
            <strong>
                <?= e((string) $project['created_at']) ?>
            </strong>
        </div>
    </div>

    <div class="detail-section">
        <h3>وصف المشروع</h3>

        <p>
            <?= nl2br(
                e(
                    (string) (
                        $project['description']
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
            <h2>المحللون المسندون</h2>
            <p class="muted">
                المحللون المسؤولون عن تنفيذ التقييم الأمني للمشروع.
            </p>
        </div>

        <div class="page-actions">
            <a
                href="<?= e(
                    url(
                        'manager/projects/assign.php?project_id='
                        . $projectId
                    )
                ) ?>"
                class="button button-primary"
            >
                إسناد محلل
            </a>
        </div>
    </div>

    <?php if ($assignments === []): ?>
            <div class="empty-state">
                <h3>لا يوجد محللون مسندون</h3>
                <p class="muted">
                    لم يتم إسناد أي محلل أمني إلى هذا المشروع حتى الآن.
                </p>

                <a
                    href="<?= e(
                        url(
                            'manager/projects/assign.php?project_id='
                            . $projectId
                        )
                    ) ?>"
                    class="button button-primary"
                >
                    إسناد أول محلل
                </a>
            </div>
    <?php else: ?>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>المحلل</th>
                            <th>البريد الإلكتروني</th>
                            <th>تاريخ الإسناد</th>
                            <th>تم الإسناد بواسطة</th>
                            <th>الحالة</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($assignments as $assignment): ?>
                                <tr>
                                    <td>
                                        <strong>
                                            <?= e(
                                                trim(
                                                    (string) $assignment['analyst_first_name']
                                                    . ' '
                                                    . (string) $assignment['analyst_last_name']
                                                )
                                            ) ?>
                                        </strong>
                                    </td>

                                    <td>
                                        <?= e((string) $assignment['analyst_email']) ?>
                                    </td>

                                    <td>
                                        <?= e((string) $assignment['assigned_at']) ?>
                                    </td>

                                    <td>
                                        <?= e(
                                            trim(
                                                (string) $assignment['assigner_first_name']
                                                . ' '
                                                . (string) $assignment['assigner_last_name']
                                            )
                                        ) ?>
                                    </td>

                                    <td>
                                        <span class="status-badge status-<?= e((string) $assignment['status']) ?>">
                                            <?= e(
                                                $assignmentStatusLabels[(string) $assignment['status']]
                                                ?? (string) $assignment['status']
                                            ) ?>
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
            <h2>التقييمات والتقارير</h2>
            <p class="muted">
                ستظهر هنا مراحل التقييم الأمني والتقارير بعد تنفيذها.
            </p>
        </div>
    </div>

    <div class="empty-state">
        <p class="muted">
            لم يتم إنشاء تقييمات أو تقارير لهذا المشروع بعد.
        </p>
    </div>
</section>

<?php render_footer(); ?>