<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

require_role('Security Manager');


$applicationId = filter_input(
    INPUT_GET,
    'application_id',
    FILTER_VALIDATE_INT
);


if (
    $applicationId === false
    || $applicationId === null
    || $applicationId <= 0
) {

    set_flash(
        'error',
        'معرف الطلب غير صالح.'
    );

    redirect(
        'manager/analyst-applications/index.php'
    );
}


$statement = db()->prepare(
    "SELECT
        a.*,
        r.role_name,
        reviewer.first_name AS reviewer_first_name,
        reviewer.last_name AS reviewer_last_name
     FROM analyst_applications AS a

     INNER JOIN roles AS r
        ON r.role_id = a.role_id

     LEFT JOIN users AS reviewer
        ON reviewer.user_id = a.reviewed_by

     WHERE a.application_id = :application_id

     LIMIT 1"
);


$statement->execute([
    'application_id' => $applicationId,
]);


$application = $statement->fetch();


if ($application === false) {

    set_flash(
        'error',
        'لم يتم العثور على الطلب.'
    );

    redirect(
        'manager/analyst-applications/index.php'
    );
}


/*
 * Decode JSON fields.
 */
$specialization = json_decode(
    (string) (
        $application['specialization'] ?? '[]'
    ),
    true
);

$technicalTools = json_decode(
    (string) (
        $application['technical_tools'] ?? '[]'
    ),
    true
);

$assessmentTypes = json_decode(
    (string) (
        $application['assessment_types'] ?? '[]'
    ),
    true
);


if (!is_array($specialization)) {
    $specialization = [];
}

if (!is_array($technicalTools)) {
    $technicalTools = [];
}

if (!is_array($assessmentTypes)) {
    $assessmentTypes = [];
}


/*
 * Get uploaded documents.
 */
$documentsStatement = db()->prepare(
    "SELECT
        file_id,
        file_type,
        original_name,
        file_path,
        uploaded_at
     FROM analyst_application_files
     WHERE application_id = :application_id
     ORDER BY uploaded_at DESC"
);


$documentsStatement->execute([
    'application_id' => $applicationId,
]);


$documents = $documentsStatement->fetchAll();


render_header('مراجعة طلب محلل أمني');
?>

<section class="page-heading">

    <div>

        <p class="eyebrow">
            طلبات المحللين الأمنيين
        </p>

        <h1>
            مراجعة طلب المحلل الأمني
        </h1>

        <p class="muted">
            راجع بيانات المتقدم ومستنداته وإجاباته قبل اتخاذ القرار.
        </p>

    </div>


    <div class="page-actions">

        <a href="<?= e(
            url(
                'manager/analyst-applications/index.php'
            )
        ) ?>" class="button button-primary">
            العودة إلى الطلبات
        </a>

    </div>

</section>


<section class="details-card">

    <div class="section-heading compact">

        <div>

            <h2>
                حالة الطلب
            </h2>

        </div>

        <span class="status-badge status-<?= e(
            (string) $application['status']
        ) ?>">
            <?= e(match (
            (string) $application['status']
            ) {
                'pending' => 'قيد المراجعة',
                'approved' => 'مقبول',
                'rejected' => 'مرفوض',
                'cancelled' => 'ملغى',
                default => $application['status'],
            }) ?>
        </span>

    </div>

</section>


<section class="details-card">

    <div class="section-heading compact">

        <div>

            <h2>
                المعلومات الشخصية
            </h2>

        </div>

    </div>


    <div class="detail-grid">

        <div>

            <span class="detail-label">
                الاسم الكامل
            </span>

            <strong>
                <?= e(
                    trim(
                        (string) $application['first_name']
                        . ' '
                        . (string) $application['last_name']
                    )
                ) ?>
            </strong>

        </div>


        <div>

            <span class="detail-label">
                البريد الإلكتروني
            </span>

            <strong>
                <?= e(
                    (string) $application['email']
                ) ?>
            </strong>

        </div>


        <div>

            <span class="detail-label">
                الهاتف
            </span>

            <strong>
                <?= e(
                    (string) (
                        $application['phone']
                        ?? '—'
                    )
                ) ?>
            </strong>

        </div>


        <div>

            <span class="detail-label">
                المدينة / الدولة
            </span>

            <strong>
                <?= e(
                    (string) (
                        $application['city']
                        ?? '—'
                    )
                ) ?>
            </strong>

        </div>


        <div>

            <span class="detail-label">
                تاريخ التقديم
            </span>

            <strong>
                <?= e(
                    (string) $application['created_at']
                ) ?>
            </strong>

        </div>


        <div>

            <span class="detail-label">
                الدور المطلوب
            </span>

            <strong>
                <?= e(
                    (string) $application['role_name']
                ) ?>
            </strong>

        </div>

    </div>

</section>


<section class="details-card">

    <div class="section-heading compact">

        <div>

            <h2>
                الخلفية المهنية
            </h2>

        </div>

    </div>


    <div class="detail-grid">

        <div>

            <span class="detail-label">
                المسمى الوظيفي الحالي
            </span>

            <strong>
                <?= e(
                    (string) (
                        $application['current_job']
                        ?? '—'
                    )
                ) ?>
            </strong>

        </div>


        <div>

            <span class="detail-label">
                سنوات الخبرة
            </span>

            <strong>
                <?= e(
                    (string) (
                        $application['experience_level']
                        ?? '—'
                    )
                ) ?>
            </strong>

        </div>


        <div>

            <span class="detail-label">
                المؤهل العلمي
            </span>

            <strong>
                <?= e(
                    (string) (
                        $application['education']
                        ?? '—'
                    )
                ) ?>
            </strong>

        </div>


        <div>

            <span class="detail-label">
                التخصص الأكاديمي
            </span>

            <strong>
                <?= e(
                    (string) (
                        $application['academic_major']
                        ?? '—'
                    )
                ) ?>
            </strong>

        </div>

    </div>

</section>


<section class="details-card">

    <div class="section-heading compact">

        <div>

            <h2>
                التخصصات والأدوات
            </h2>

        </div>

    </div>


    <div class="detail-section">

        <h3>
            مجالات التخصص
        </h3>

        <?php if ($specialization === []): ?>

            <p class="muted">
                لم يحدد المتقدم تخصصات.
            </p>

        <?php else: ?>

            <ul>

                <?php foreach ($specialization as $item): ?>

                    <li>
                        <?= e((string) $item) ?>
                    </li>

                <?php endforeach; ?>

            </ul>

        <?php endif; ?>

    </div>


    <div class="detail-section">

        <h3>
            الأدوات والتقنيات
        </h3>

        <?php if ($technicalTools === []): ?>

            <p class="muted">
                لم يحدد المتقدم أدوات.
            </p>

        <?php else: ?>

            <ul>

                <?php foreach ($technicalTools as $item): ?>

                    <li>
                        <?= e((string) $item) ?>
                    </li>

                <?php endforeach; ?>

            </ul>

        <?php endif; ?>

    </div>


    <div class="detail-section">

        <h3>
            أنواع الاختبارات الأمنية
        </h3>

        <?php if ($assessmentTypes === []): ?>

            <p class="muted">
                لم يحدد المتقدم أنواع الاختبارات.
            </p>

        <?php else: ?>

            <ul>

                <?php foreach ($assessmentTypes as $item): ?>

                    <li>
                        <?= e((string) $item) ?>
                    </li>

                <?php endforeach; ?>

            </ul>

        <?php endif; ?>

    </div>


    <div class="detail-section">

        <h3>
            الخبرة الأمنية
        </h3>

        <p>
            <?= nl2br(
                e(
                    (string) (
                        $application['security_experience']
                        ?? '—'
                    )
                )
            ) ?>
        </p>

    </div>

</section>


<section class="details-card">

    <div class="section-heading compact">

        <div>

            <h2>
                الشهادات والخبرة
            </h2>

        </div>

    </div>


    <div class="detail-section">

        <h3>
            الشهادات
        </h3>

        <p>
            <?= nl2br(
                e(
                    (string) (
                        $application['certifications']
                        ?? '—'
                    )
                )
            ) ?>
        </p>

    </div>


    <div class="detail-section">

        <h3>
            التدريب المتخصص
        </h3>

        <p>
            <?= nl2br(
                e(
                    (string) (
                        $application['specialized_training']
                        ?? '—'
                    )
                )
            ) ?>
        </p>

    </div>


    <div class="detail-section">

        <h3>
            أهم مشروع أمني
        </h3>

        <p>
            <?= nl2br(
                e(
                    (string) (
                        $application['main_project']
                        ?? '—'
                    )
                )
            ) ?>
        </p>

    </div>


    <div class="detail-section">

        <h3>
            الدور في المشروع
        </h3>

        <p>
            <?= nl2br(
                e(
                    (string) (
                        $application['project_role']
                        ?? '—'
                    )
                )
            ) ?>
        </p>

    </div>

</section>


<section class="details-card">

    <div class="section-heading compact">

        <div>

            <h2>
                الروابط
            </h2>

        </div>

    </div>


    <div class="detail-grid">

        <div>

            <span class="detail-label">
                GitHub
            </span>

            <?php if (
                !empty($application['github_url'])
            ): ?>

                <a href="<?= e(
                    (string) $application['github_url']
                ) ?>" target="_blank" rel="noopener noreferrer">
                    فتح الرابط
                </a>

            <?php else: ?>

                <strong>—</strong>

            <?php endif; ?>

        </div>


        <div>

            <span class="detail-label">
                LinkedIn
            </span>

            <?php if (
                !empty($application['linkedin_url'])
            ): ?>

                <a href="<?= e(
                    (string) $application['linkedin_url']
                ) ?>" target="_blank" rel="noopener noreferrer">
                    فتح الرابط
                </a>

            <?php else: ?>

                <strong>—</strong>

            <?php endif; ?>

        </div>


        <div>

            <span class="detail-label">
                Portfolio
            </span>

            <?php if (
                !empty($application['portfolio_url'])
            ): ?>

                <a href="<?= e(
                    (string) $application['portfolio_url']
                ) ?>" target="_blank" rel="noopener noreferrer">
                    فتح الرابط
                </a>

            <?php else: ?>

                <strong>—</strong>

            <?php endif; ?>

        </div>

    </div>

</section>


<section class="details-card">

    <div class="section-heading compact">

        <div>

            <h2>
                المستندات المرفوعة
            </h2>

            <p class="muted">
                افتح المستندات للتحقق من صحة المعلومات المقدمة.
            </p>

        </div>

    </div>


    <?php if ($documents === []): ?>

        <p class="muted">
            لا توجد مستندات مرتبطة بهذا الطلب.
        </p>

    <?php else: ?>

        <div class="table-wrapper">

            <table class="data-table">

                <thead>

                    <tr>

                        <th>
                            نوع المستند
                        </th>

                        <th>
                            اسم الملف
                        </th>

                        <th>
                            تاريخ الرفع
                        </th>

                        <th>
                            فتح
                        </th>

                    </tr>

                </thead>


                <tbody>

                    <?php foreach ($documents as $document): ?>

                        <tr>

                            <td>

                                <?= e(match (
                                (string) $document['file_type']
                                ) {
                                    'cv' => 'السيرة الذاتية',
                                    'certificates' => 'الشهادات',
                                    'recommendations' => 'خطابات التوصية',
                                    'experience' => 'شهادات الخبرة',
                                    'additional' => 'مستند إضافي',
                                    default => (string) $document['file_type'],
                                }) ?>
                            </td>


                            <td>
                                <?= e(
                                    (string) $document['original_name']
                                ) ?>
                            </td>


                            <td>
                                <?= e(
                                    (string) $document['uploaded_at']
                                ) ?>
                            </td>


                            <td>

                               <a href="<?= e(
                                url(
                                    'manager/analyst-applications/download.php?file_id='
                                    . (int) $document['file_id']
                                )
                            ) ?>" target="_blank" rel="noopener noreferrer" class="button button-small">
                                فتح المستند
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

            <h2>
                التقييم المبدئي
            </h2>

        </div>

    </div>


    <div class="detail-section">

        <h3>
            سيناريو Web Application
        </h3>

        <p>
            <?= nl2br(
                e(
                    (string) (
                        $application['web_security_answer']
                        ?? '—'
                    )
                )
            ) ?>
        </p>

    </div>


    <div class="detail-section">

        <h3>
            الفرق بين Vulnerability و Risk و Finding
        </h3>

        <p>
            <?= nl2br(
                e(
                    (string) (
                        $application[
                            'vulnerability_risk_finding_answer'
                        ]
                        ?? '—'
                    )
                )
            ) ?>
        </p>

    </div>


    <div class="detail-section">

        <h3>
            التعامل مع الثغرات أثناء اختبار مصرح به
        </h3>

        <p>
            <?= nl2br(
                e(
                    (string) (
                        $application[
                            'authorized_testing_answer'
                        ]
                        ?? '—'
                    )
                )
            ) ?>
        </p>

    </div>

</section>


<section class="details-card">

    <div class="section-heading compact">

        <div>

            <h2>
                معلومات إضافية
            </h2>

        </div>

    </div>


    <div class="detail-section">

        <h3>
            لماذا SecureScope؟
        </h3>

        <p>
            <?= nl2br(
                e(
                    (string) (
                        $application['why_securescope']
                        ?? '—'
                    )
                )
            ) ?>
        </p>

    </div>


    <div class="detail-section">

        <h3>
            ماذا سيقدم للفريق؟
        </h3>

        <p>
            <?= nl2br(
                e(
                    (string) (
                        $application['contribution']
                        ?? '—'
                    )
                )
            ) ?>
        </p>

    </div>
    <div class="detail-section">

    <h3>
        الموافقة على مراجعة الطلب
    </h3>

    <?php if (
        (int) ($application['review_agreement'] ?? 0) === 1
    ): ?>

        <span class="status-badge status-approved">
            تمت الموافقة
        </span>

    <?php else: ?>

        <span class="status-badge status-rejected">
            لم تتم الموافقة
        </span>

    <?php endif; ?>

</div>


<div class="detail-section">

    <h3>
        إقرار المتقدم
    </h3>

    <p>
        <?= nl2br(
            e(
                (string) (
                    $application['declaration']
                    ?? '—'
                )
            )
        ) ?>
    </p>

</div>
</section>


<?php if (
    $application['status'] === 'pending'
): ?>

    <section class="details-card">

        <div class="section-heading compact">

            <div>

                <h2>
                    قرار الإدارة
                </h2>

                <p class="muted">
                    تأكد من مراجعة جميع البيانات والمستندات قبل اتخاذ القرار.
                </p>

            </div>

        </div>


        <div class="form-actions">

            <form method="post" action="<?= e(
                url(
                    'manager/analyst-applications/approve.php'
                )
            ) ?>">

                <?= csrf_input() ?>

                <input type="hidden" name="application_id" value="<?= e(
                    (string) $applicationId
                ) ?>">

                <button type="submit" class="button button-primary">
                    قبول الطلب
                </button>

            </form>


            <a href="<?= e(
                url(
                    'manager/analyst-applications/reject.php?application_id='
                    . $applicationId
                )
            ) ?>" class="button button-danger">
                رفض الطلب
            </a>

        </div>

    </section>

<?php endif; ?>


<?php if (
    $application['status'] !== 'pending'
): ?>

    <section class="details-card">

        <div class="detail-grid">

            <div>

                <span class="detail-label">
                    تمت المراجعة بواسطة
                </span>

                <strong>
                    <?= e(
                        trim(
                            (string) (
                                $application[
                                    'reviewer_first_name'
                                ] ?? ''
                            )
                            . ' '
                            . (string) (
                                $application[
                                    'reviewer_last_name'
                                ] ?? ''
                            )
                        )
                        ?: '—'
                    ) ?>
                </strong>

            </div>


            <div>

                <span class="detail-label">
                    تاريخ المراجعة
                </span>

                <strong>
                    <?= e(
                        (string) (
                            $application['reviewed_at']
                            ?? '—'
                        )
                    ) ?>
                </strong>

            </div>


            <?php if (
                !empty($application['rejection_reason'])
            ): ?>

                <div>

                    <span class="detail-label">
                        سبب الرفض
                    </span>

                    <strong>
                        <?= e(
                            (string) $application[
                                'rejection_reason'
                            ]
                        ) ?>
                    </strong>

                </div>

            <?php endif; ?>

        </div>

    </section>

<?php endif; ?>


<?php render_footer(); ?>