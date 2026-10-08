<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_role('Security Analyst');
$user = current_user();
if ($user === null) {
    logout_user();
    redirect('login.php');
}
$findingId = filter_input(
    INPUT_GET,
    'finding_id',
    FILTER_VALIDATE_INT
);
if (
    $findingId === false
    || $findingId === null
    || $findingId <= 0
) {
    set_flash(
        'error',
        'معرف Finding غير صالح.'
    );
    redirect('analyst/findings/index.php');
}
$statement = db()->prepare(
    "SELECT
        f.finding_id,
        f.title,
        f.description,
        f.status,
        a.assessment_name,
        p.project_name
     FROM findings AS f
     INNER JOIN assessments AS a
        ON a.assessment_id = f.assessment_id
     INNER JOIN projects AS p
        ON p.project_id = a.project_id
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
    set_flash(
        'error',
        'Finding غير موجودة أو لا تملك صلاحية الوصول إليها.'
    );
    redirect('analyst/findings/index.php');
}
$errors = [];
$description = '';
$allowedMimes = [
    'application/pdf' => 'pdf',
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
];
$maxFileSize = 5 * 1024 * 1024;
if (is_post_request()) {
    require_valid_csrf();
    $description = trim(
        (string) ($_POST['description'] ?? '')
    );
    if ($description !== '' && strlen($description) > 5000) {
        $errors[] =
            'وصف الدليل طويل جدًا.';
    }
    if (
        !isset($_FILES['evidence'])
        || !is_array($_FILES['evidence'])
    ) {
        $errors[] =
            'اختر ملف الدليل.';
    } else {
        $file = $_FILES['evidence'];
        $uploadError = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        $tmpName = (string) ($file['tmp_name'] ?? '');
        $fileSize = (int) ($file['size'] ?? 0);
        $originalName = basename(
            (string) ($file['name'] ?? '')
        );
        if ($uploadError !== UPLOAD_ERR_OK) {
            $errors[] =
                'حدث خطأ أثناء رفع الملف.';
        } elseif (
            $tmpName === ''
            || !is_uploaded_file($tmpName)
        ) {
            $errors[] =
                'ملف الرفع غير صالح.';
        } elseif ($fileSize <= 0) {
            $errors[] =
                'ملف الدليل فارغ.';
        } elseif ($fileSize > $maxFileSize) {
            $errors[] =
                'حجم الملف يتجاوز 5 ميجابايت.';
        } elseif ($originalName === '') {
            $errors[] =
                'اسم الملف غير صالح.';
        } else {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($tmpName);
            if (
                $mimeType === false
                || !isset($allowedMimes[$mimeType])
            ) {
                $errors[] =
                    'نوع الملف غير مسموح به. المسموح: PDF وJPG وPNG.';
            }
        }
    }
    if ($errors === []) {
        $extension = $allowedMimes[$mimeType];
        $storageRoot = __DIR__
            . '/../../storage/evidence';
        $findingDirectory = $storageRoot
            . DIRECTORY_SEPARATOR
            . (string) $findingId;
        try {
            if (!is_dir($storageRoot)) {
                if (!mkdir($storageRoot, 0750, true)) {
                    throw new RuntimeException(
                        'تعذر إنشاء مجلد الأدلة.'
                    );
                }
            }
            if (!is_dir($findingDirectory)) {
                if (!mkdir($findingDirectory, 0750, true)) {
                    throw new RuntimeException(
                        'تعذر إنشاء مجلد Finding.'
                    );
                }
            }
            $storedName =
                bin2hex(random_bytes(32))
                . '.'
                . $extension;
            $destination =
                $findingDirectory
                . DIRECTORY_SEPARATOR
                . $storedName;
            if (!move_uploaded_file($tmpName, $destination)) {
                throw new RuntimeException(
                    'تعذر حفظ ملف الدليل.'
                );
            }
            $relativePath =
                'storage/evidence/'
                . $findingId
                . '/'
                . $storedName;
            db()->beginTransaction();
            $insertStatement = db()->prepare(
                "INSERT INTO evidence (
                    finding_id,
                    uploaded_by,
                    file_name,
                    file_path,
                    file_type,
                    description
                 ) VALUES (
                    :finding_id,
                    :uploaded_by,
                    :file_name,
                    :file_path,
                    :file_type,
                    :description
                 )"
            );
            $insertStatement->execute([
                'finding_id' => $findingId,
                'uploaded_by' => (int) $user['user_id'],
                'file_name' => $originalName,
                'file_path' => $relativePath,
                'file_type' => $mimeType,
                'description' => $description !== ''
                    ? $description
                    : null,
            ]);
            $evidenceId = (int) db()->lastInsertId();
            record_audit(
                'UPLOAD_EVIDENCE',
                'evidence',
                $evidenceId,
                [],
                [
                    'finding_id' => $findingId,
                    'file_name' => $originalName,
                    'file_type' => $mimeType,
                    'file_size' => $fileSize,
                ]
            );
            db()->commit();
            set_flash(
                'success',
                'تم رفع دليل الفحص بنجاح.'
            );
            redirect(
                'analyst/findings/view.php?finding_id='
                . $findingId
            );
        } catch (Throwable $exception) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            if (
                isset($destination)
                && is_file($destination)
            ) {
                @unlink($destination);
            }
            error_log(
                'SecureScope evidence upload error: '
                . $exception->getMessage()
            );
            $errors[] =
                'حدث خطأ أثناء حفظ الدليل. حاول مرة أخرى.';
        }
    }
}
render_header('إضافة دليل فحص');
?>
<section class="page-heading">
    <div>
        <p class="eyebrow">المحلل الأمني</p>
        <h1>إضافة دليل فحص</h1>
        <p class="muted">
            إرفاق دليل يدعم النتيجة الأمنية المكتشفة.
        </p>
    </div>
    <div class="page-actions">
        <a
            href="<?= e(
                url(
                    'analyst/findings/view.php?finding_id='
                    . $findingId
                )
            ) ?>"
            class="button button-primary"
        >
            العودة إلى Finding
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
            <h2>Finding</h2>
            <p class="muted">
                الدليل سيُربط بهذه النتيجة الأمنية.
            </p>
        </div>
    </div>
    <div class="detail-grid">
        <div>
            <span class="detail-label">Finding</span>
            <strong>
                <?= e((string) $finding['title']) ?>
            </strong>
        </div>
        <div>
            <span class="detail-label">التقييم</span>
            <strong>
                <?= e((string) $finding['assessment_name']) ?>
            </strong>
        </div>
        <div>
            <span class="detail-label">المشروع</span>
            <strong>
                <?= e((string) $finding['project_name']) ?>
            </strong>
        </div>
        <div>
            <span class="detail-label">الحالة</span>
            <strong>
                <?= e((string) $finding['status']) ?>
            </strong>
        </div>
    </div>
</section>
<section class="details-card">
    <div class="section-heading compact">
        <div>
            <h2>ملف الدليل</h2>
            <p class="muted">
                المسموح به PDF أو JPG أو PNG، وبحد أقصى 5 ميجابايت.
            </p>
        </div>
    </div>
    <form method="post" enctype="multipart/form-data">
        <?= csrf_input() ?>
        <div class="form-group">
            <label for="evidence">
                ملف الدليل
            </label>
            <input
                id="evidence"
                type="file"
                name="evidence"
                accept=".pdf,.jpg,.jpeg,.png"
                required
            >
        </div>
        <div class="form-group">
            <label for="description">
                وصف الدليل
            </label>
            <textarea
                id="description"
                name="description"
                rows="6"
                maxlength="5000"
                placeholder="اشرح باختصار ماذا يثبت هذا الدليل..."
            ><?= e($description) ?></textarea>
        </div>
        <div class="form-actions">
            <a
                href="<?= e(
                    url(
                        'analyst/findings/view.php?finding_id='
                        . $findingId
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
                رفع الدليل
            </button>
        </div>
    </form>
</section>
<?php render_footer(); ?>