<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

require_role('Security Manager');

$clientsStatement = db()->query(
    "SELECT
        client_id,
        company_name
     FROM clients
     WHERE status = 'active'
     ORDER BY company_name"
);

$clients = $clientsStatement->fetchAll();

$assessmentTypesStatement = db()->query(
    "SELECT
        assessment_type_id,
        name
     FROM assessment_types
     WHERE status = 'active'
     ORDER BY name"
);

$assessmentTypes = $assessmentTypesStatement->fetchAll();

$errors = [];

if (is_post_request()) {
    require_valid_csrf();

    $projectName = trim((string) ($_POST['project_name'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $startDate = trim((string) ($_POST['start_date'] ?? ''));
    $endDate = trim((string) ($_POST['end_date'] ?? ''));

    $clientId = filter_input(
        INPUT_POST,
        'client_id',
        FILTER_VALIDATE_INT
    );

    $assessmentTypeId = filter_input(
        INPUT_POST,
        'assessment_type_id',
        FILTER_VALIDATE_INT
    );

    if ($projectName === '') {
        $errors[] = 'اسم المشروع مطلوب.';
    } elseif (strlen($projectName) > 200) {
        $errors[] = 'اسم المشروع يجب ألا يتجاوز 200 حرف.';
    }

    if ($clientId === false || $clientId === null || $clientId <= 0) {
        $errors[] = 'يجب اختيار العميل.';
    }

    if (
        $assessmentTypeId === false
        || $assessmentTypeId === null
        || $assessmentTypeId <= 0
    ) {
        $errors[] = 'يجب اختيار نوع التقييم.';
    }

    $validStartDate = true;
    $validEndDate = true;

    if ($startDate !== '') {
        $startDateObject = DateTime::createFromFormat('Y-m-d', $startDate);

        if (
            $startDateObject === false
            || $startDateObject->format('Y-m-d') !== $startDate
        ) {
            $errors[] = 'تاريخ البداية غير صالح.';
            $validStartDate = false;
        }
    }

    if ($endDate !== '') {
        $endDateObject = DateTime::createFromFormat('Y-m-d', $endDate);

        if (
            $endDateObject === false
            || $endDateObject->format('Y-m-d') !== $endDate
        ) {
            $errors[] = 'تاريخ النهاية غير صالح.';
            $validEndDate = false;
        }
    }

    if (
        $validStartDate
        && $validEndDate
        && $startDate !== ''
        && $endDate !== ''
        && $endDate < $startDate
    ) {
        $errors[] = 'تاريخ النهاية يجب ألا يسبق تاريخ البداية.';
    }

    if ($errors === []) {
        $clientStatement = db()->prepare(
            "SELECT client_id
             FROM clients
             WHERE client_id = :client_id
               AND status = 'active'
             LIMIT 1"
        );

        $clientStatement->execute([
            'client_id' => $clientId,
        ]);

        if ($clientStatement->fetchColumn() === false) {
            $errors[] = 'العميل المحدد غير موجود أو غير نشط.';
        }
    }

    if ($errors === []) {
        $assessmentTypeStatement = db()->prepare(
            "SELECT assessment_type_id
             FROM assessment_types
             WHERE assessment_type_id = :assessment_type_id
               AND status = 'active'
             LIMIT 1"
        );

        $assessmentTypeStatement->execute([
            'assessment_type_id' => $assessmentTypeId,
        ]);

        if ($assessmentTypeStatement->fetchColumn() === false) {
            $errors[] = 'نوع التقييم المحدد غير موجود أو غير نشط.';
        }
    }

    $currentUser = current_user();

    if ($errors === [] && $currentUser === null) {
        $errors[] = 'تعذر تحديد المستخدم الحالي.';
    }

    if ($errors === []) {
        try {
            $statement = db()->prepare(
                "INSERT INTO projects (
                    client_id,
                    assessment_type_id,
                    project_name,
                    description,
                    start_date,
                    end_date,
                    status,
                    created_by
                )
                VALUES (
                    :client_id,
                    :assessment_type_id,
                    :project_name,
                    :description,
                    :start_date,
                    :end_date,
                    'draft',
                    :created_by
                )"
            );

            $statement->execute([
                'client_id' => $clientId,
                'assessment_type_id' => $assessmentTypeId,
                'project_name' => $projectName,
                'description' => $description !== '' ? $description : null,
                'start_date' => $startDate !== '' ? $startDate : null,
                'end_date' => $endDate !== '' ? $endDate : null,
                'created_by' => (int) $currentUser['user_id'],
            ]);

            $projectId = (int) db()->lastInsertId();

            record_audit(
                'CREATE_PROJECT',
                'projects',
                $projectId,
                null,
                [
                    'project_id' => $projectId,
                    'client_id' => $clientId,
                    'assessment_type_id' => $assessmentTypeId,
                    'project_name' => $projectName,
                    'description' => $description,
                    'start_date' => $startDate !== '' ? $startDate : null,
                    'end_date' => $endDate !== '' ? $endDate : null,
                    'status' => 'draft',
                    'created_by' => (int) $currentUser['user_id'],
                ]
            );

            set_flash(
                'success',
                'تم إنشاء المشروع بنجاح.'
            );

            redirect(
                'manager/projects/index.php'
            );
        } catch (Throwable $exception) {
            error_log(
                'SecureScope project creation error: '
                . $exception->getMessage()
            );

            $errors[] = 'حدث خطأ أثناء إنشاء المشروع. حاول مرة أخرى.';
        }
    }
}

render_header('إضافة مشروع');
?>

<section class="page-heading">
    <div>
        <p class="eyebrow">إدارة المشاريع</p>
        <h1>إضافة مشروع</h1>
        <p class="muted">
            إنشاء مشروع جديد وربطه بأحد العملاء ونوع التقييم الأمني.
        </p>
    </div>

    <div class="page-actions">
        <a href="<?= e(url('manager/projects/index.php')) ?>" class="button button-primary">
            العودة إلى المشاريع
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

    <?php if ($clients === []): ?>
            <div class="alert alert-error">
                لا يوجد عملاء نشطون حاليًا. يجب إنشاء عميل أولًا قبل إنشاء المشروع.
            </div>
    <?php endif; ?>

    <?php if ($assessmentTypes === []): ?>
            <div class="alert alert-error">
                لا توجد أنواع تقييم نشطة حاليًا.
            </div>
    <?php endif; ?>

    <form method="post">
        <?= csrf_input() ?>

        <div class="form-grid">
            <div class="form-field">
                <label for="project_name">
                    اسم المشروع
                </label>
                <input
                    type="text"
                    id="project_name"
                    name="project_name"
                    maxlength="200"
                    required
                    value="<?= e((string) ($_POST['project_name'] ?? '')) ?>"
                >
            </div>

            <div class="form-field">
                <label for="client_id">
                    العميل
                </label>
                <select id="client_id" name="client_id" required>
                    <option value="">
                        اختر العميل
                    </option>
                    <?php foreach ($clients as $client): ?>
                            <option
                                value="<?= e((string) $client['client_id']) ?>"
                                <?= (
                                    (string) ($_POST['client_id'] ?? '')
                                    === (string) $client['client_id']
                                ) ? 'selected' : '' ?>
                            >
                                <?= e((string) $client['company_name']) ?>
                            </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-field">
                <label for="assessment_type_id">
                    نوع التقييم
                </label>
                <select id="assessment_type_id" name="assessment_type_id" required>
                    <option value="">
                        اختر نوع التقييم
                    </option>
                    <?php foreach ($assessmentTypes as $assessmentType): ?>
                            <option
                                value="<?= e((string) $assessmentType['assessment_type_id']) ?>"
                                <?= (
                                    (string) ($_POST['assessment_type_id'] ?? '')
                                    === (string) $assessmentType['assessment_type_id']
                                ) ? 'selected' : '' ?>
                            >
                                <?= e((string) $assessmentType['name']) ?>
                            </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-field">
                <label for="start_date">
                    تاريخ البداية
                </label>
                <input
                    type="date"
                    id="start_date"
                    name="start_date"
                    value="<?= e((string) ($_POST['start_date'] ?? '')) ?>"
                >
            </div>

            <div class="form-field">
                <label for="end_date">
                    تاريخ النهاية
                </label>
                <input
                    type="date"
                    id="end_date"
                    name="end_date"
                    value="<?= e((string) ($_POST['end_date'] ?? '')) ?>"
                >
            </div>

            <div class="form-field">
                <label for="description">
                    وصف المشروع
                </label>
                <textarea
                    id="description"
                    name="description"
                    rows="6"
                ><?= e((string) ($_POST['description'] ?? '')) ?></textarea>
            </div>
        </div>

        <div class="form-actions">
            <a href="<?= e(url('manager/projects/index.php')) ?>" class="button button-primary">
                إلغاء
            </a>

            <button
                type="submit"
                class="button button-primary"
                <?= ($clients === [] || $assessmentTypes === []) ? 'disabled' : '' ?>
            >
                إنشاء المشروع
            </button>
        </div>
    </form>
</section>

<?php render_footer(); ?>