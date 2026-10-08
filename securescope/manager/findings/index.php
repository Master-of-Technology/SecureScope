<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_role('Security Manager');

$statement = db()->query(
    "SELECT
        f.finding_id,
        f.assessment_id,
        f.asset_id,
        f.title,
        f.status,
        f.discovered_at,
        a.assessment_name,
        p.project_name,
        c.company_name,
        ass.asset_name,
        ass.asset_type,
        rl.name AS risk_level_name,
        rl.severity_score,
        analyst.first_name AS analyst_first_name,
        analyst.last_name AS analyst_last_name
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
     ORDER BY f.discovered_at DESC"
);

$findings = $statement->fetchAll();

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

render_header('الثغرات الأمنية');
?>

<section class="page-heading">
    <div>
        <p class="eyebrow">
            إدارة الثغرات
        </p>
        <h1>
            الثغرات الأمنية
        </h1>
        <p class="muted">
            مراجعة النتائج الأمنية التي اكتشفها المحللون ضمن المشاريع والتقييمات.
        </p>
    </div>
</section>

<section class="details-card">

    <div class="section-heading compact">
        <div>
            <h2>
                جميع النتائج الأمنية
            </h2>
            <p class="muted">
                راجع الثغرات قبل اعتمادها والانتقال إلى مرحلة المعالجة.
            </p>
        </div>
    </div>

    <?php if ($findings === []): ?>

            <div class="empty-state">
                <h2>
                    لا توجد Findings
                </h2>
                <p class="muted">
                    لم يتم تسجيل أي نتائج أمنية حتى الآن.
                </p>
            </div>

    <?php else: ?>

            <div class="table-wrapper">

                <table class="data-table">

                    <thead>
                        <tr>
                            <th>Finding</th>
                            <th>التقييم</th>
                            <th>المشروع</th>
                            <th>العميل</th>
                            <th>الأصل</th>
                            <th>الخطورة</th>
                            <th>المحلل</th>
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
                                            <?= e(
                                                (string) $finding['title']
                                            ) ?>
                                        </strong>
                                    </td>

                                    <td>
                                        <?= e(
                                            (string) $finding['assessment_name']
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= e(
                                            (string) $finding['project_name']
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= e(
                                            (string) $finding['company_name']
                                        ) ?>
                                    </td>

                                    <td>
                                        <strong>
                                            <?= e(
                                                (string) $finding['asset_name']
                                            ) ?>
                                        </strong>
                                        <small>
                                            <?= e(
                                                (string) $finding['asset_type']
                                            ) ?>
                                        </small>
                                    </td>

                                    <td>
                                        <span class="status-badge status-<?= e(
                                            strtolower(
                                                (string) $finding['risk_level_name']
                                            )
                                        ) ?>">
                                            <?= e(
                                                (string) $finding['risk_level_name']
                                            ) ?>
                                            —
                                            <?= e(
                                                (string) $finding['severity_score']
                                            ) ?>/5
                                        </span>
                                    </td>

                                    <td>
                                        <?= e(
                                            trim(
                                                (string) $finding['analyst_first_name']
                                                . ' '
                                                . (string) $finding['analyst_last_name']
                                            )
                                        ) ?>
                                    </td>

                                    <td>
                                        <span class="status-badge status-<?= e(
                                            (string) $finding['status']
                                        ) ?>">
                                            <?= e(
                                                $statusLabels[
                                                    (string) $finding['status']
                                                ]
                                                ?? (string) $finding['status']
                                            ) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?= e(
                                            (string) $finding['discovered_at']
                                        ) ?>
                                    </td>

                                    <td>
                                        <a
                                            href="<?= e(
                                                url(
                                                    'manager/findings/view.php?finding_id='
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