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

$projectStatement = db()->prepare(
    "SELECT
        p.project_id,
        p.project_name,
        p.status,
        c.company_name
     FROM projects AS p
     INNER JOIN clients AS c
        ON c.client_id = p.client_id
     WHERE p.project_id = :project_id
     LIMIT 1"
);

$projectStatement->execute([
    'project_id' => $projectId,
]);

$project = $projectStatement->fetch();

if ($project === false) {
    set_flash(
        'error',
        'لم يتم العثور على المشروع.'
    );

    redirect(
        'manager/projects/index.php'
    );
}

$blockedStatuses = [
    'completed',
    'closed',
    'cancelled',
];

if (in_array((string) $project['status'], $blockedStatuses, true)) {
    set_flash(
        'error',
        'لا يمكن إسناد محلل إلى مشروع مكتمل أو مغلق أو ملغى.'
    );

    redirect(
        'manager/projects/view.php?project_id='
        . $projectId
    );
}

$analystsStatement = db()->query(
    "SELECT
        u.user_id,
        u.first_name,
        u.last_name,
        u.email
     FROM users AS u
     INNER JOIN roles AS r
        ON r.role_id = u.role_id
     WHERE r.role_name = 'Security Analyst'
       AND u.status = 'active'
     ORDER BY u.first_name, u.last_name"
);

$analysts = $analystsStatement->fetchAll();

$errors = [];

if (is_post_request()) {
    require_valid_csrf();

    $analystId = filter_input(
        INPUT_POST,
        'analyst_id',
        FILTER_VALIDATE_INT
    );

    if (
        $analystId === false
        || $analystId === null
        || $analystId <= 0
    ) {
        $errors[] = 'يجب اختيار محلل أمني.';
    }

    $currentUser = current_user();

    if ($currentUser === null) {
        $errors[] = 'تعذر تحديد المستخدم الحالي.';
    }

    if ($errors === []) {
        try {
            $analystStatement = db()->prepare(
                "SELECT
                    u.user_id,
                    u.first_name,
                    u.last_name,
                    u.email
                 FROM users AS u
                 INNER JOIN roles AS r
                    ON r.role_id = u.role_id
                 WHERE u.user_id = :user_id
                   AND r.role_name = 'Security Analyst'
                   AND u.status = 'active'
                 LIMIT 1"
            );

            $analystStatement->execute([
                'user_id' => $analystId,
            ]);

            $analyst = $analystStatement->fetch();

            if ($analyst === false) {
                $errors[] = 'المحلل المحدد غير موجود أو غير نشط.';
            }
        } catch (Throwable $exception) {
            error_log(
                'SecureScope analyst validation error: '
                . $exception->getMessage()
            );

            $errors[] = 'تعذر التحقق من المحلل المحدد.';
        }
    }

    if ($errors === []) {
        try {
            $existingAssignmentStatement = db()->prepare(
                "SELECT assignment_id
                 FROM project_assignments
                 WHERE project_id = :project_id
                   AND analyst_id = :analyst_id
                   AND status = 'active'
                 LIMIT 1"
            );

            $existingAssignmentStatement->execute([
                'project_id' => $projectId,
                'analyst_id' => $analystId,
            ]);

            if ($existingAssignmentStatement->fetchColumn() !== false) {
                $errors[] = 'هذا المحلل مسند بالفعل إلى المشروع.';
            }
        } catch (Throwable $exception) {
            error_log(
                'SecureScope assignment validation error: '
                . $exception->getMessage()
            );

            $errors[] = 'تعذر التحقق من الإسناد الحالي.';
        }
    }

    if ($errors === []) {
        try {
            db()->beginTransaction();

            $insertStatement = db()->prepare(
                "INSERT INTO project_assignments (
                    project_id,
                    analyst_id,
                    assigned_by,
                    status
                )
                VALUES (
                    :project_id,
                    :analyst_id,
                    :assigned_by,
                    'active'
                )"
            );

            $insertStatement->execute([
                'project_id' => $projectId,
                'analyst_id' => $analystId,
                'assigned_by' => (int) $currentUser['user_id'],
            ]);

            $assignmentId = (int) db()->lastInsertId();

            if ($assignmentId <= 0) {
                throw new RuntimeException(
                    'تعذر إنشاء عملية الإسناد.'
                );
            }

            $projectUpdateStatement = db()->prepare(
                "UPDATE projects
                 SET
                    status = 'assigned',
                    updated_at = CURRENT_TIMESTAMP
                 WHERE project_id = :project_id
                   AND status NOT IN ('completed', 'closed', 'cancelled')"
            );

            $projectUpdateStatement->execute([
                'project_id' => $projectId,
            ]);

            if ($projectUpdateStatement->rowCount() !== 1) {
                throw new RuntimeException(
                    'تعذر تحديث حالة المشروع.'
                );
            }

            record_audit(
                'ASSIGN_ANALYST_TO_PROJECT',
                'project_assignments',
                $assignmentId,
                null,
                [
                    'assignment_id' => $assignmentId,
                    'project_id' => $projectId,
                    'analyst_id' => $analystId,
                    'assigned_by' => (int) $currentUser['user_id'],
                    'status' => 'active',
                ]
            );

            db()->commit();

            set_flash(
                'success',
                'تم إسناد المحلل إلى المشروع بنجاح.'
            );

            redirect(
                'manager/projects/view.php?project_id='
                . $projectId
            );
        } catch (Throwable $exception) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }

            error_log(
                'SecureScope project assignment error: '
                . $exception->getMessage()
            );

            $errors[] =
                'حدث خطأ أثناء إسناد المحلل. حاول مرة أخرى.';
        }
    }
}

render_header('إسناد محلل للمشروع');
?>

<section class="page-heading">
    <div>
        <p class="eyebrow">إدارة المشاريع</p>
        <h1>إسناد محلل للمشروع</h1>
        <p class="muted">
            اختر المحلل الأمني المسؤول عن تنفيذ التقييم لهذا المشروع.
        </p>
    </div>

    <div class="page-actions">
        <a
            href="<?= e(
                url(
                    'manager/projects/view.php?project_id='
                    . $projectId
                )
            ) ?>"
            class="button button-primary"
        >
            العودة إلى المشروع
        </a>
    </div>
</section>

<section class="details-card">
    <div class="detail-grid">
        <div>
            <span class="detail-label">المشروع</span>
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
            <span class="detail-label">الحالة الحالية</span>
            <span class="status-badge status-<?= e((string) $project['status']) ?>">
                <?= e((string) $project['status']) ?>
            </span>
        </div>
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

    <?php if ($analysts === []): ?>
            <div class="empty-state">
                <h2>لا يوجد محللون أمنيون متاحون</h2>
                <p class="muted">
                    لا يوجد حاليًا أي حساب Security Analyst نشط يمكن إسناده إلى المشروع.
                </p>
            </div>
    <?php else: ?>
            <form method="post">
                <?= csrf_input() ?>

                <div class="form-field">
                    <label for="analyst_id">
                        المحلل الأمني
                    </label>

                    <select
                        id="analyst_id"
                        name="analyst_id"
                        required
                    >
                        <option value="">
                            اختر المحلل
                        </option>

                        <?php foreach ($analysts as $analyst): ?>
                                <option
                                    value="<?= e((string) $analyst['user_id']) ?>"
                                    <?= (
                                        (string) ($_POST['analyst_id'] ?? '')
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
                </div>

                <div class="form-actions">
                    <a
                        href="<?= e(
                            url(
                                'manager/projects/view.php?project_id='
                                . $projectId
                            )
                        ) ?>"
                        class="button button-primary"
                    >
                        إلغاء
                    </a>

                    <button
                        type="submit"
                        class="button button-primary"
                    >
                        إسناد المحلل
                    </button>
                </div>
            </form>
    <?php endif; ?>
</section>

<?php render_footer(); ?>