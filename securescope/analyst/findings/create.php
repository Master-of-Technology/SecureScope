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
    redirect('analyst/assessments/index.php');
}
$assessmentStatement = db()->prepare(
    "SELECT
        a.assessment_id,
        a.assessment_name,
        a.status,
        a.assigned_to,
        p.client_id,
        p.project_name,
        c.company_name
     FROM assessments AS a
     INNER JOIN projects AS p
        ON p.project_id = a.project_id
     INNER JOIN clients AS c
        ON c.client_id = p.client_id
     WHERE a.assessment_id = :assessment_id
       AND a.assigned_to = :assigned_to
     LIMIT 1"
);
$assessmentStatement->execute([
    'assessment_id' => $assessmentId,
    'assigned_to' => (int) $user['user_id'],
]);
$assessment = $assessmentStatement->fetch();
if ($assessment === false) {
    set_flash(
        'error',
        'التقييم غير موجود أو غير مسند إلى حسابك.'
    );
    redirect('analyst/assessments/index.php');
}
if ((string) $assessment['status'] !== 'in_progress') {
    set_flash(
        'error',
        'لا يمكن إضافة Finding إلا أثناء تنفيذ التقييم.'
    );
    redirect(
        'analyst/assessments/view.php?assessment_id='
        . $assessmentId
    );
}
$assetsStatement = db()->prepare(
    "SELECT
        asset_id,
        asset_name,
        asset_type,
        identifier
     FROM assets
     WHERE client_id = :client_id
       AND status = 'active'
     ORDER BY asset_name"
);
$assetsStatement->execute([
    'client_id' => (int) $assessment['client_id'],
]);
$assets = $assetsStatement->fetchAll();
$riskLevelsStatement = db()->query(
    "SELECT
        risk_level_id,
        name,
        severity_score,
        description
     FROM risk_levels
     ORDER BY severity_score DESC"
);

$riskLevels = $riskLevelsStatement->fetchAll();
$errors = [];
if (is_post_request()) {
    require_valid_csrf();
    $assetId = filter_input(
        INPUT_POST,
        'asset_id',
        FILTER_VALIDATE_INT
    );
    $riskLevelId = filter_input(
        INPUT_POST,
        'risk_level_id',
        FILTER_VALIDATE_INT
    );
    $title = trim(
        (string) ($_POST['title'] ?? '')
    );
    $description = trim(
        (string) ($_POST['description'] ?? '')
    );
    $technicalDetails = trim(
        (string) ($_POST['technical_details'] ?? '')
    );
    if (
        $assetId === false
        || $assetId === null
        || $assetId <= 0
    ) {
        $errors[] = 'يجب اختيار الأصل.';
    }
    if (
        $riskLevelId === false
        || $riskLevelId === null
        || $riskLevelId <= 0
    ) {
        $errors[] = 'يجب اختيار مستوى الخطورة.';
    }
    if ($title === '') {
        $errors[] = 'عنوان Finding مطلوب.';
    } elseif (strlen($title) > 255) {
        $errors[] = 'عنوان Finding يجب ألا يتجاوز 255 حرفًا.';
    }
    if ($description === '') {
        $errors[] = 'وصف Finding مطلوب.';
    }
    if ($errors === []) {
        try {
            $validationStatement = db()->prepare(
                "SELECT
                    ass.asset_id,
                    rl.risk_level_id
                 FROM assets AS ass
                 CROSS JOIN risk_levels AS rl
                 WHERE ass.asset_id = :asset_id
                   AND ass.client_id = :client_id
                   AND ass.status = 'active'
                   AND rl.risk_level_id = :risk_level_id
                 LIMIT 1"
            );
            $validationStatement->execute([
                'asset_id' => $assetId,
                'client_id' => (int) $assessment['client_id'],
                'risk_level_id' => $riskLevelId,
            ]);
            if ($validationStatement->fetch() === false) {
                $errors[] =
                    'الأصل أو مستوى الخطورة المحدد غير صالح لهذا التقييم.';
            }
        } catch (Throwable $exception) {
            error_log(
                'SecureScope finding validation error: '
                . $exception->getMessage()
            );
            $errors[] =
                'تعذر التحقق من بيانات Finding.';
        }
    }
    if ($errors === []) {
        try {
            db()->beginTransaction();
            $insertStatement = db()->prepare(
                "INSERT INTO findings (
                    assessment_id,
                    asset_id,
                    risk_level_id,
                    title,
                    description,
                    technical_details,
                    status,
                    discovered_by,
                    discovered_at
                )
                VALUES (
                    :assessment_id,
                    :asset_id,
                    :risk_level_id,
                    :title,
                    :description,
                    :technical_details,
                    'open',
                    :discovered_by,
                    NOW()
                )"
            );
            $insertStatement->execute([
                'assessment_id' => $assessmentId,
                'asset_id' => $assetId,
                'risk_level_id' => $riskLevelId,
                'title' => $title,
                'description' => $description,
                'technical_details' =>
                    $technicalDetails !== ''
                    ? $technicalDetails
                    : null,
                'discovered_by' => (int) $user['user_id'],
            ]);
            $findingId = (int) db()->lastInsertId();
            if ($findingId <= 0) {
                throw new RuntimeException(
                    'تعذر إنشاء Finding.'
                );
            }
            record_audit(
                'CREATE_FINDING',
                'findings',
                $findingId,
                null,
                [
                    'finding_id' => $findingId,
                    'assessment_id' => $assessmentId,
                    'asset_id' => $assetId,
                    'risk_level_id' => $riskLevelId,
                    'status' => 'open',
                    'discovered_by' => (int) $user['user_id'],
                ]
            );
            db()->commit();
            set_flash(
                'success',
                'تم تسجيل Finding بنجاح.'
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
                'SecureScope finding creation error: '
                . $exception->getMessage()
            );
            $errors[] =
                'حدث خطأ أثناء تسجيل Finding. حاول مرة أخرى.';
        }
    }
}
render_header('إضافة Finding');
?>
<section class="page-heading">
    <div>
        <p class="eyebrow">المحلل الأمني</p>
        <h1>إضافة Finding</h1>
        <p class="muted">
            تسجيل نتيجة أمنية جديدة ضمن التقييم الحالي.
        </p>
    </div>
    <div class="page-actions">
        <a href="<?= e(
            url(
                'analyst/assessments/view.php?assessment_id='
                . $assessmentId
            )
        ) ?>" class="button button-primary">
            العودة إلى التقييم
        </a>
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
            <span class="detail-label">الحالة</span>
            <span class="status-badge status-in_progress">
                قيد التنفيذ
            </span>
        </div>
    </div>
</section>
<section class="details-card">
    <?php if ($errors !== []): ?>
        <div class="alert alert-error" role="alert">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li>
                        <?= e($error) ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    <?php if ($assets === []): ?>
        <div class="empty-state">
            <h2>لا توجد أصول نشطة</h2>
            <p class="muted">
                لا يمكن تسجيل Finding قبل إضافة أصل نشط لهذا العميل.
            </p>
        </div>
    <?php elseif ($riskLevels === []): ?>
        <div class="empty-state">
            <h2>لا توجد مستويات خطورة</h2>
            <p class="muted">
                يجب وجود مستوى خطورة نشط قبل تسجيل Finding.
            </p>
        </div>
    <?php else: ?>
        <form method="post" class="form-stack">
            <?= csrf_input() ?>
            <div>
                <label for="asset_id">
                    الأصل
                </label>
                <select id="asset_id" name="asset_id" required>
                    <option value="">
                        اختر الأصل الذي تم اكتشاف المشكلة فيه
                    </option>
                    <?php foreach ($assets as $asset): ?>
                        <option value="<?= e((string) $asset['asset_id']) ?>" <?= (
                               (string) ($_POST['asset_id'] ?? '')
                               === (string) $asset['asset_id']
                           ) ? 'selected' : '' ?>
                            >
                            <?= e((string) $asset['asset_name']) ?>
                            —
                            <?= e((string) $asset['asset_type']) ?>
                            <?php if (!empty($asset['identifier'])): ?>
                                —
                                <?= e((string) $asset['identifier']) ?>
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="risk_level_id">
                    مستوى الخطورة
                </label>
                <select id="risk_level_id" name="risk_level_id" required>
                    <option value="">
                        اختر مستوى الخطورة
                    </option>
                    <?php foreach ($riskLevels as $riskLevel): ?>
                        <option value="<?= e((string) $riskLevel['risk_level_id']) ?>" <?= (
                               (string) ($_POST['risk_level_id'] ?? '')
                               === (string) $riskLevel['risk_level_id']
                           ) ? 'selected' : '' ?>
                            >
                            <?= e((string) $riskLevel['name']) ?>
                            —
                            الدرجة
                            <?= e((string) $riskLevel['severity_score']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small>
                    Critical = 5، High = 4، Medium = 3، Low = 2، Informational = 1.
                </small>
            </div>
            <div>
                <label for="title">
                    عنوان Finding
                </label>
                <input id="title" name="title" type="text" maxlength="255" required value="<?= e(
                    (string) (
                        $_POST['title']
                        ?? ''
                    )
                ) ?>" placeholder="مثال: SQL Injection">
            </div>
            <div>
                <label for="description">
                    وصف Finding
                </label>
                <textarea id="description" name="description" rows="7" required
                    placeholder="اشرح المشكلة الأمنية وتأثيرها."><?= e(
                        (string) (
                            $_POST['description']
                            ?? ''
                        )
                    ) ?></textarea>
            </div>
            <div>
                <label for="technical_details">
                    التفاصيل الفنية
                </label>
                <textarea id="technical_details" name="technical_details" rows="8"
                    placeholder="أضف التفاصيل الفنية ونتائج الفحص والأدلة النصية المناسبة."><?= e(
                        (string) (
                            $_POST['technical_details']
                            ?? ''
                        )
                    ) ?></textarea>
            </div>
            <div class="form-actions">
                <a href="<?= e(
                    url(
                        'analyst/assessments/view.php?assessment_id='
                        . $assessmentId
                    )
                ) ?>" class="button button-primary">
                    إلغاء
                </a>
                <button type="submit" class="button button-primary">
                    تسجيل Finding
                </button>
            </div>
        </form>
    <?php endif; ?>
</section>
<?php render_footer(); ?>