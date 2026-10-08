<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

require_role('Security Manager');

$projectsStatement = db()->query(
    "SELECT
        p.project_id,
        p.project_name,
        c.company_name
     FROM projects AS p
     INNER JOIN clients AS c
        ON c.client_id = p.client_id
     WHERE p.status NOT IN ('completed', 'closed', 'cancelled')
     ORDER BY p.created_at DESC"
);

$projects = $projectsStatement->fetchAll();

$errors = [];

$selectedProjectId = filter_input(
    INPUT_POST,
    'project_id',
    FILTER_VALIDATE_INT
);

$selectedProjectId = (
    $selectedProjectId !== false
    && $selectedProjectId !== null
    && $selectedProjectId > 0
) ? $selectedProjectId : null;

$analysts = [];

if ($selectedProjectId !== null) {
    $analystsStatement = db()->prepare(
        "SELECT
            u.user_id,
            u.first_name,
            u.last_name,
            u.email
         FROM project_assignments AS pa
         INNER JOIN users AS u
            ON u.user_id = pa.analyst_id
         INNER JOIN roles AS r
            ON r.role_id = u.role_id
         WHERE pa.project_id = :project_id
           AND pa.status = 'active'
           AND u.status = 'active'
           AND r.role_name = 'Security Analyst'
         ORDER BY u.first_name, u.last_name"
    );

    $analystsStatement->execute([
        'project_id' => $selectedProjectId,
    ]);

    $analysts = $analystsStatement->fetchAll();
}

if (is_post_request()) {
    require_valid_csrf();

    $assessmentName = trim(
        (string) ($_POST['assessment_name'] ?? '')
    );

    $description = trim(
        (string) ($_POST['description'] ?? '')
    );

    $assignedTo = filter_input(
        INPUT_POST,
        'assigned_to',
        FILTER_VALIDATE_INT
    );

    if ($selectedProjectId === null) {
        $errors[] = 'يجب اختيار المشروع.';
    }

    if ($assessmentName === '') {
        $errors[] = 'اسم التقييم مطلوب.';
    } elseif (strlen($assessmentName) > 200) {
        $errors[] = 'اسم التقييم يجب ألا يتجاوز 200 حرف.';
    }

    if (
        $assignedTo === false
        || $assignedTo === null
        || $assignedTo <= 0
    ) {
        $errors[] = 'يجب اختيار المحلل الأمني.';
    }

    $currentUser = current_user();

    if ($currentUser === null) {
        $errors[] = 'تعذر تحديد المستخدم الحالي.';
    }

    if ($errors === [] && $selectedProjectId !== null) {
        $projectStatement = db()->prepare(
            "SELECT
                project_id,
                project_name,
                status
             FROM projects
             WHERE project_id = :project_id
             LIMIT 1"
        );

        $projectStatement->execute([
            'project_id' => $selectedProjectId,
        ]);

        $project = $projectStatement->fetch();

        if ($project === false) {
            $errors[] = 'المشروع المحدد غير موجود.';
        } elseif (
            in_array(
                (string) $project['status'],
                ['completed', 'closed', 'cancelled'],
                true
            )
        ) {
            $errors[] = 'لا يمكن إنشاء تقييم لهذا المشروع.';
        }
    }

    if (
        $errors === []
        && $selectedProjectId !== null
        && $assignedTo !== false
        && $assignedTo !== null
    ) {
        $assignmentStatement = db()->prepare(
            "SELECT
                pa.assignment_id
             FROM project_assignments AS pa
             INNER JOIN users AS u
                ON u.user_id = pa.analyst_id
             INNER JOIN roles AS r
                ON r.role_id = u.role_id
             WHERE pa.project_id = :project_id
               AND pa.analyst_id = :analyst_id
               AND pa.status = 'active'
               AND u.status = 'active'
               AND r.role_name = 'Security Analyst'
             LIMIT 1"
        );

        $assignmentStatement->execute([
            'project_id' => $selectedProjectId,
            'analyst_id' => $assignedTo,
        ]);

        if ($assignmentStatement->fetch() === false) {
            $errors[] =
                'المحلل المحدد غير مسند إلى هذا المشروع.';
        }
    }

    if ($errors === []) {
        try {
            db()->beginTransaction();

            $insertStatement = db()->prepare(
                "INSERT INTO assessments (
                    project_id,
                    assessment_name,
                    description,
                    assigned_to,
                    status
                )
                VALUES (
                    :project_id,
                    :assessment_name,
                    :description,
                    :assigned_to,
                    'pending'
                )"
            );

            $insertStatement->execute([
                'project_id' => $selectedProjectId,
                'assessment_name' => $assessmentName,
                'description' => $description !== ''
                    ? $description
                    : null,
                'assigned_to' => $assignedTo,
            ]);

            $assessmentId = (int) db()->lastInsertId();

            if ($assessmentId <= 0) {
                throw new RuntimeException(
                    'تعذر إنشاء التقييم.'
                );
            }

            record_audit(
                'CREATE_ASSESSMENT',
                'assessments',
                $assessmentId,
                null,
                [
                    'assessment_id' => $assessmentId,
                    'project_id' => $selectedProjectId,
                    'assessment_name' => $assessmentName,
                    'assigned_to' => $assignedTo,
                    'status' => 'pending',
                ]
            );

            db()->commit();

            set_flash(
                'success',
                'تم إنشاء التقييم الأمني بنجاح.'
            );

            redirect(
                'manager/assessments/view.php?assessment_id='
                . $assessmentId
            );
        } catch (Throwable $exception) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }

            error_log(
                'SecureScope assessment creation error: '
                . $exception->getMessage()
            );

            $errors[] =
                'حدث خطأ أثناء إنشاء التقييم. حاول مرة أخرى.';
        }
    }
}

render_header('إضافة تقييم أمني');
?>

<section class="page-heading">
    <div>
        <p class="eyebrow">إدارة التقييمات الأمنية</p>
        <h1>إضافة تقييم أمني</h1>
        <p class="muted">
            إنشاء تقييم وربطه بمشروع ومحلل أمني مسند إليه.
        </p>
    </div>

    <div class="page-actions">
        <a
            href="<?= e(url('manager/assessments/index.php')) ?>"
            class="button button-primary"
        >
            العودة إلى التقييمات
        </a>
    </div>
</section>

<section class="details-card">
    <?php if ($errors !== []): ?>
            <div class="alert alert-error" role="alert">
                <ul>
                    <?php foreach ($errors as $error): ?>
                            <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
    <?php endif; ?>

    <?php if ($projects === []): ?>
            <div class="empty-state">
                <h2>لا توجد مشاريع متاحة</h2>
                <p class="muted">
                    يجب إنشاء مشروع أولًا قبل إنشاء تقييم أمني.
                </p>

                <a
                    href="<?= e(url('manager/projects/create.php')) ?>"
                    class="button button-primary"
                >
                    إنشاء مشروع
                </a>
            </div>
    <?php else: ?>
            <form method="post">
                <?= csrf_input() ?>

                <div class="form-grid">
                    <div class="form-field">
                        <label for="project_id">
                            المشروع
                        </label>

                        <select
                            id="project_id"
                            name="project_id"
                            required
                            onchange="this.form.submit()"
                        >
                            <option value="">
                                اختر المشروع
                            </option>

                            <?php foreach ($projects as $projectOption): ?>
                                    <option
                                        value="<?= e((string) $projectOption['project_id']) ?>"
                                        <?= (
                                            (string) ($selectedProjectId ?? '')
                                            === (string) $projectOption['project_id']
                                        ) ? 'selected' : '' ?>
                                    >
                                        <?= e((string) $projectOption['project_name']) ?>
                                        -
                                        <?= e((string) $projectOption['company_name']) ?>
                                    </option>
                            <?php endforeach; ?>
                        </select>

                        <small>
                            اختر المشروع أولًا لعرض المحللين المسندين إليه.
                        </small>
                    </div>

                    <div class="form-field">
                        <label for="assigned_to">
                            المحلل الأمني
                        </label>

                        <select
                            id="assigned_to"
                            name="assigned_to"
                            required
                            <?= $selectedProjectId === null ? 'disabled' : '' ?>
                        >
                            <option value="">
                                اختر المحلل
                            </option>

                            <?php foreach ($analysts as $analyst): ?>
                                    <option
                                        value="<?= e((string) $analyst['user_id']) ?>"
                                        <?= (
                                            (string) ($_POST['assigned_to'] ?? '')
                                            === (string) $analyst['user_id']
                                        ) ? 'selected' : '' ?>
                                    >
                                        <?= e(
                                            trim(
                                                (string) $analyst['first_name']
                                                . ' '
                                                . (string) $analyst['last_name']
                                            )
                                        ) ?>
                                        -
                                        <?= e((string) $analyst['email']) ?>
                                    </option>
                            <?php endforeach; ?>
                        </select>

                        <?php if ($selectedProjectId !== null && $analysts === []): ?>
                                <small>
                                    لا يوجد محللون نشطون مسندون إلى هذا المشروع.
                                </small>
                        <?php endif; ?>
                    </div>

                    <div class="form-field">
                        <label for="assessment_name">
                            اسم التقييم
                        </label>

                        <input
                            type="text"
                            id="assessment_name"
                            name="assessment_name"
                            maxlength="200"
                            required
                            value="<?= e(
                                (string) (
                                    $_POST['assessment_name']
                                    ?? ''
                                )
                            ) ?>"
                        >
                    </div>

                    <div class="form-field">
                        <label for="description">
                            وصف التقييم
                        </label>

                        <textarea
                            id="description"
                            name="description"
                            rows="6"
                        ><?= e(
                            (string) (
                                $_POST['description']
                                ?? ''
                            )
                        ) ?></textarea>
                    </div>
                </div>

                <div class="form-actions">
                    <a
                        href="<?= e(url('manager/assessments/index.php')) ?>"
                        class="button button-primary"
                    >
                        إلغاء
                    </a>

                    <button
                        type="submit"
                        class="button button-primary"
                        <?= (
                            $selectedProjectId === null
                            || $analysts === []
                        ) ? 'disabled' : '' ?>
                    >
                        إنشاء التقييم
                    </button>
                </div>
            </form>
    <?php endif; ?>
</section>

<?php render_footer(); ?>