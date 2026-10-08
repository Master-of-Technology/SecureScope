<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
start_secure_session();
/*
|--------------------------------------------------------------------------
| Analyst Registration - Multi Step Form
|--------------------------------------------------------------------------
|
| No database record is created until the final step.
| All temporary form data is stored in $_SESSION.
|
*/
const ANALYST_FORM_SESSION = 'analyst_registration';
/*
|--------------------------------------------------------------------------
| Initialize registration session
|--------------------------------------------------------------------------
*/
if (!isset($_SESSION[ANALYST_FORM_SESSION])) {
    $_SESSION[ANALYST_FORM_SESSION] = [
        'step' => 1,
        'data' => [],
    ];
}
$registration =& $_SESSION[ANALYST_FORM_SESSION];
$currentStep = (int) ($registration['step'] ?? 1);
if ($currentStep < 1 || $currentStep > 7) {
    $currentStep = 1;
    $registration['step'] = 1;
}
/*
|--------------------------------------------------------------------------
| Steps
|--------------------------------------------------------------------------
*/
$steps = [
    1 => 'البيانات الشخصية',
    2 => 'التعليم والخبرة',
    3 => 'التخصص والأدوات',
    4 => 'المشاريع والشهادات',
    5 => 'الأسئلة التقنية',
    6 => 'المستندات',
    7 => 'المراجعة والإقرار',
];
$totalSteps = count($steps);
$errors = [];
/*
|--------------------------------------------------------------------------
| File Upload Configuration
|--------------------------------------------------------------------------
*/
const ANALYST_UPLOAD_ROOT =
    __DIR__ . '/../storage/analyst-applications';
const ANALYST_MAX_FILE_SIZE =
    5 * 1024 * 1024; // 5 MB
const ANALYST_ALLOWED_MIMES = [
    'application/pdf',
    'image/jpeg',
    'image/png',
];
const ANALYST_FILE_TYPES = [
    'cv' => 'السيرة الذاتية',
    'certificates' => 'الشهادات',
    'recommendations' => 'خطابات التوصية',
    'experience' => 'شهادات الخبرة',
    'additional' => 'مستند إضافي',
];
/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/
$getPostString = static function (string $key): string {
    return trim((string) ($_POST[$key] ?? ''));
};

$getPostArray = static function (string $key): array {
    $value = $_POST[$key] ?? [];
    if (!is_array($value)) {
        return [];
    }
    return array_values(
        array_filter(
            array_map(
                static fn($item): string => trim((string) $item),
                $value
            ),
            static fn(string $item): bool => $item !== ''
        )
    );
};

/*
|--------------------------------------------------------------------------
| File Upload Handler
|--------------------------------------------------------------------------
*/
$handleUploadedFiles = static function (string $fieldName, string $fileType, string $directory): array {
    if (
        !isset($_FILES[$fieldName])
        || !is_array($_FILES[$fieldName])
    ) {
        return [];
    }
    $files = $_FILES[$fieldName];
    $names = $files['name'] ?? [];
    $tmpNames = $files['tmp_name'] ?? [];
    $errors = $files['error'] ?? [];
    $sizes = $files['size'] ?? [];
    /*
     * Normalize single/multiple uploads.
     */
    if (!is_array($names)) {
        $names = [$names];
        $tmpNames = [$tmpNames];
        $errors = [$errors];
        $sizes = [$sizes];
    }
    $uploaded = [];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $extensions = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];
    foreach ($names as $index => $originalName) {
        $uploadError =
            (int) ($errors[$index] ?? UPLOAD_ERR_NO_FILE);
        /*
         * Optional files can simply be skipped.
         */
        if ($uploadError === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($uploadError !== UPLOAD_ERR_OK) {
            throw new RuntimeException(
                'تعذر رفع ملف '
                . ANALYST_FILE_TYPES[$fileType]
                . '.'
            );
        }
        $tmpName =
            (string) ($tmpNames[$index] ?? '');
        $fileSize =
            (int) ($sizes[$index] ?? 0);
        if (
            $tmpName === ''
            || !is_uploaded_file($tmpName)
        ) {
            throw new RuntimeException(
                'ملف الرفع غير صالح.'
            );
        }
        if ($fileSize <= 0) {
            throw new RuntimeException(
                'الملف '
                . ANALYST_FILE_TYPES[$fileType]
                . ' فارغ.'
            );
        }
        if (
            $fileSize >
            ANALYST_MAX_FILE_SIZE
        ) {
            throw new RuntimeException(
                'حجم ملف '
                . ANALYST_FILE_TYPES[$fileType]
                . ' يتجاوز 5 ميجابايت.'
            );
        }
        /*
         * Detect the real MIME type from
         * the file contents.
         */
        $mimeType =
            $finfo->file($tmpName);
        if (
            $mimeType === false
            || !isset(
            $extensions[$mimeType]
        )
        ) {
            throw new RuntimeException(
                'نوع ملف غير مسموح به في '
                . ANALYST_FILE_TYPES[$fileType]
                . '.'
            );
        }
        $extension =
            $extensions[$mimeType];
        /*
         * Never trust the original filename
         * as the stored filename.
         */
        $storedName =
            $fileType
            . '_'
            . bin2hex(random_bytes(16))
            . '.'
            . $extension;
        $destination =
            $directory
            . DIRECTORY_SEPARATOR
            . $storedName;
        if (
            !move_uploaded_file(
                $tmpName,
                $destination
            )
        ) {
            throw new RuntimeException(
                'تعذر حفظ الملف على الخادم.'
            );
        }
        $uploaded[] = [
            'file_type' =>
                $fileType,
            'original_name' =>
                basename(
                    (string) $originalName
                ),
            'stored_name' =>
                $storedName,
            'temporary_path' =>
                $destination,
            'mime_type' =>
                $mimeType,
            'file_size' =>
                $fileSize,
        ];
    }
    return $uploaded;
};

$createAnalystUploadDirectory = static function (): string {
    if (!is_dir(ANALYST_UPLOAD_ROOT)) {
        if (
            !mkdir(
                ANALYST_UPLOAD_ROOT,
                0750,
                true
            )
        ) {
            throw new RuntimeException(
                'تعذر إنشاء مجلد تخزين الملفات.'
            );
        }
    }
    $sessionDirectory =
        ANALYST_UPLOAD_ROOT
        . DIRECTORY_SEPARATOR
        . hash(
            'sha256',
            session_id()
        );
    if (!is_dir($sessionDirectory)) {
        if (
            !mkdir(
                $sessionDirectory,
                0750,
                true
            )
        ) {
            throw new RuntimeException(
                'تعذر إنشاء مجلد الطلب المؤقت.'
            );
        }
    }
    return $sessionDirectory;
};
/*
|--------------------------------------------------------------------------
| POST handling
|--------------------------------------------------------------------------
*/
if (is_post_request()) {
    require_valid_csrf();
    $action = (string) ($_POST['action'] ?? 'next');
    /*
    |--------------------------------------------------------------------------
    | Previous
    |--------------------------------------------------------------------------
    */
    if ($action === 'previous') {
        if ($currentStep > 1) {
            $registration['step'] = $currentStep - 1;
        }
        redirect('register/analyst.php');
    }
    /*
    |--------------------------------------------------------------------------
    | Next
    |--------------------------------------------------------------------------
    */
    if ($action === 'next') {
        /*
         * STEP 1
         */
        if ($currentStep === 1) {
            $data = [
                'first_name' =>
                    $getPostString('first_name'),
                'last_name' =>
                    $getPostString('last_name'),
                'email' =>
                    $getPostString('email'),
                'phone' =>
                    $getPostString('phone'),
                'city' =>
                    $getPostString('city'),
            ];
            if ($data['first_name'] === '') {
                $errors[] = 'أدخل الاسم الأول.';
            }
            if ($data['last_name'] === '') {
                $errors[] = 'أدخل اسم العائلة.';
            }
            if (
                $data['email'] === ''
                || !filter_var(
                    $data['email'],
                    FILTER_VALIDATE_EMAIL
                )
            ) {
                $errors[] = 'أدخل بريدًا إلكترونيًا صحيحًا.';
            }
            if ($data['phone'] === '') {
                $errors[] = 'أدخل رقم الهاتف.';
            }
            if ($data['city'] === '') {
                $errors[] = 'أدخل الدولة / المدينة.';
            }
            if ($errors === []) {
                $registration['data'] =
                    array_merge(
                        $registration['data'],
                        $data
                    );
                $registration['step'] = 2;
                redirect('register/analyst.php');
            }
        }
        /*
         * STEP 2
         */ elseif ($currentStep === 2) {
            $data = [
                'education' =>
                    $getPostString('education'),
                'academic_major' =>
                    $getPostString('academic_major'),
                'current_job' =>
                    $getPostString('current_job'),
                'experience_level' =>
                    $getPostString('experience_level'),
            ];
            if ($data['education'] === '') {
                $errors[] = 'حدد المؤهل العلمي.';
            }
            if ($data['academic_major'] === '') {
                $errors[] = 'أدخل التخصص الأكاديمي.';
            }
            if ($data['experience_level'] === '') {
                $errors[] = 'حدد مستوى الخبرة.';
            }
            if ($errors === []) {
                $registration['data'] =
                    array_merge(
                        $registration['data'],
                        $data
                    );
                $registration['step'] = 3;
                redirect('register/analyst.php');
            }
        }
        /*
         * STEP 3
         */ elseif ($currentStep === 3) {
            $data = [
                'specialization' =>
                    $getPostArray('specialization'),
                'technical_tools' =>
                    $getPostArray('technical_tools'),
                'assessment_types' =>
                    $getPostArray('assessment_types'),
                'security_experience' =>
                    $getPostString('security_experience'),
            ];
            if ($data['specialization'] === []) {
                $errors[] =
                    'حدد مجال تخصص واحد على الأقل.';
            }
            if ($data['technical_tools'] === []) {
                $errors[] =
                    'حدد أداة أو تقنية واحدة على الأقل.';
            }
            if ($data['assessment_types'] === []) {
                $errors[] =
                    'حدد نوع اختبار واحد على الأقل.';
            }
            if ($data['security_experience'] === '') {
                $errors[] =
                    'اكتب نبذة عن خبرتك في الأمن السيبراني.';
            }
            if ($errors === []) {
                $registration['data'] =
                    array_merge(
                        $registration['data'],
                        $data
                    );
                $registration['step'] = 4;
                redirect('register/analyst.php');
            }
        }
        /*
         * STEP 4
         */ elseif ($currentStep === 4) {
            $data = [
                'certifications' =>
                    $getPostString('certifications'),
                'specialized_training' =>
                    $getPostString('specialized_training'),
                'main_project' =>
                    $getPostString('main_project'),
                'project_role' =>
                    $getPostString('project_role'),
                'github_url' =>
                    $getPostString('github_url'),
                'linkedin_url' =>
                    $getPostString('linkedin_url'),
                'portfolio_url' =>
                    $getPostString('portfolio_url'),
            ];
            if (
                $data['github_url'] !== ''
                && !filter_var(
                    $data['github_url'],
                    FILTER_VALIDATE_URL
                )
            ) {
                $errors[] =
                    'رابط GitHub غير صحيح.';
            }
            if (
                $data['linkedin_url'] !== ''
                && !filter_var(
                    $data['linkedin_url'],
                    FILTER_VALIDATE_URL
                )
            ) {
                $errors[] =
                    'رابط LinkedIn غير صحيح.';
            }
            if (
                $data['portfolio_url'] !== ''
                && !filter_var(
                    $data['portfolio_url'],
                    FILTER_VALIDATE_URL
                )
            ) {
                $errors[] =
                    'رابط Portfolio غير صحيح.';
            }
            if ($errors === []) {
                $registration['data'] =
                    array_merge(
                        $registration['data'],
                        $data
                    );
                $registration['step'] = 5;
                redirect('register/analyst.php');
            }
        }
        /*
         * STEP 5
         */ elseif ($currentStep === 5) {
            $data = [
                'web_security_answer' =>
                    $getPostString(
                        'web_security_answer'
                    ),
                'vulnerability_risk_finding_answer' =>
                    $getPostString(
                        'vulnerability_risk_finding_answer'
                    ),
                'authorized_testing_answer' =>
                    $getPostString(
                        'authorized_testing_answer'
                    ),
                'why_securescope' =>
                    $getPostString(
                        'why_securescope'
                    ),
                'contribution' =>
                    $getPostString(
                        'contribution'
                    ),
            ];
            foreach ($data as $value) {
                if ($value === '') {
                    $errors[] =
                        'يرجى الإجابة عن جميع الأسئلة.';
                    break;
                }
            }
            if ($errors === []) {
                $registration['data'] =
                    array_merge(
                        $registration['data'],
                        $data
                    );
                $registration['step'] = 6;
                redirect('register/analyst.php');
            }
        }
        /*
  |--------------------------------------------------------------------------
  | STEP 6 - File Upload
  |--------------------------------------------------------------------------
  */ elseif ($currentStep === 6) {
            try {
                $uploadDirectory =
                    $createAnalystUploadDirectory();
                $uploadedFiles = [];
                /*
                 * CV
                 */
                $uploadedFiles = array_merge(
                    $uploadedFiles,
                    $handleUploadedFiles(
                        'cv',
                        'cv',
                        $uploadDirectory
                    )
                );
                /*
                 * Certificates
                 */
                $uploadedFiles = array_merge(
                    $uploadedFiles,
                    $handleUploadedFiles(
                        'certificates',
                        'certificates',
                        $uploadDirectory
                    )
                );
                /*
                 * Recommendations
                 */
                $uploadedFiles = array_merge(
                    $uploadedFiles,
                    $handleUploadedFiles(
                        'recommendations',
                        'recommendations',
                        $uploadDirectory
                    )
                );
                /*
                 * Experience
                 */
                $uploadedFiles = array_merge(
                    $uploadedFiles,
                    $handleUploadedFiles(
                        'experience',
                        'experience',
                        $uploadDirectory
                    )
                );
                /*
                 * Additional documents
                 */
                $uploadedFiles = array_merge(
                    $uploadedFiles,
                    $handleUploadedFiles(
                        'additional',
                        'additional',
                        $uploadDirectory
                    )
                );
                /*
                 * CV is mandatory.
                 */
                $hasCv = false;
                foreach ($uploadedFiles as $file) {
                    if ($file['file_type'] === 'cv') {
                        $hasCv = true;
                        break;
                    }
                }
                if (!$hasCv) {
                    throw new RuntimeException(
                        'يجب رفع السيرة الذاتية.'
                    );
                }
                /*
                 * Store file information in session
                 * until application_id is created.
                 */
                $registration['files'] =
                    array_merge(
                        $registration['files'] ?? [],
                        $uploadedFiles
                    );
                $registration['step'] = 7;
                redirect(
                    'register/analyst.php'
                );
            } catch (Throwable $exception) {
                error_log(
                    'Analyst file upload error: '
                    . $exception->getMessage()
                );
                $errors[] =
                    $exception->getMessage();
            }
        }
        /*
         * STEP 7
         */ elseif ($currentStep === 7) {
            $reviewAgreement =
                isset($_POST['review_agreement'])
                && $_POST['review_agreement'] === '1';
            $declaration =
                $getPostString('declaration');
            if (!$reviewAgreement) {
                $errors[] =
                    'يجب الموافقة على مراجعة الطلب.';
            }
            if ($declaration === '') {
                $errors[] =
                    'يجب تأكيد الإقرار.';
            }
            if ($errors === []) {
                $registration['data'] =
                    array_merge(
                        $registration['data'],
                        [
                            'review_agreement' => 1,
                            'declaration' =>
                                $declaration,
                        ]
                    );
                /*
                 * Final validation.
                 */
                $data = $registration['data'];
                /*
                 * Find Security Analyst role.
                 */
                $roleStatement = db()->query(
                    "SELECT role_id
                     FROM roles
                     WHERE role_name = 'Security Analyst'
                     LIMIT 1"
                );
                $roleId =
                    $roleStatement->fetchColumn();
                if ($roleId === false) {
                    $errors[] =
                        'تعذر العثور على دور المحلل الأمني.';
                }
                /*
                 * Prevent duplicate applications.
                 */
                $duplicateStatement = db()->prepare(
                    "SELECT application_id
     FROM analyst_applications
     WHERE active_email = :email
     LIMIT 1"
                );

                $duplicateStatement->execute([
                    'email' => $data['email'],
                ]);

                if ($duplicateStatement->fetch() !== false) {
                    $errors[] =
                        'يوجد طلب سابق بهذا البريد الإلكتروني '
                        . 'وهو ما زال قيد المراجعة أو تم قبوله.';
                }
                /*
                 * Insert final application.
                 */
                if ($errors === []) {
                    try {
                        db()->beginTransaction();
                        $statement = db()->prepare(
                            "INSERT INTO analyst_applications (
                                role_id,
                                first_name,
                                last_name,
                                email,
                                phone,
                                city,
                                current_job,
                                experience_level,
                                specialization,
                                technical_tools,
                                assessment_types,
                                security_experience,
                                education,
                                academic_major,
                                certifications,
                                specialized_training,
                                main_project,
                                project_role,
                                github_url,
                                linkedin_url,
                                portfolio_url,
                                web_security_answer,
                                vulnerability_risk_finding_answer,
                                authorized_testing_answer,
                                why_securescope,
                                contribution,
                                review_agreement,
                                declaration,
                                status
                            )
                            VALUES (
                                :role_id,
                                :first_name,
                                :last_name,
                                :email,
                                :phone,
                                :city,
                                :current_job,
                                :experience_level,
                                :specialization,
                                :technical_tools,
                                :assessment_types,
                                :security_experience,
                                :education,
                                :academic_major,
                                :certifications,
                                :specialized_training,
                                :main_project,
                                :project_role,
                                :github_url,
                                :linkedin_url,
                                :portfolio_url,
                                :web_security_answer,
                                :vulnerability_risk_finding_answer,
                                :authorized_testing_answer,
                                :why_securescope,
                                :contribution,
                                :review_agreement,
                                :declaration,
                                'pending'
                            )"
                        );
                        $statement->execute([
                            'role_id' =>
                                (int) $roleId,
                            'first_name' =>
                                $data['first_name'],
                            'last_name' =>
                                $data['last_name'],
                            'email' =>
                                $data['email'],
                            'phone' =>
                                $data['phone'],
                            'city' =>
                                $data['city'],
                            'current_job' =>
                                $data['current_job']
                                !== ''
                                ? $data['current_job']
                                : null,
                            'experience_level' =>
                                $data['experience_level'],
                            'specialization' =>
                                json_encode(
                                    $data['specialization'],
                                    JSON_UNESCAPED_UNICODE
                                    | JSON_THROW_ON_ERROR
                                ),
                            'technical_tools' =>
                                json_encode(
                                    $data['technical_tools'],
                                    JSON_UNESCAPED_UNICODE
                                    | JSON_THROW_ON_ERROR
                                ),
                            'assessment_types' =>
                                json_encode(
                                    $data['assessment_types'],
                                    JSON_UNESCAPED_UNICODE
                                    | JSON_THROW_ON_ERROR
                                ),
                            'security_experience' =>
                                $data['security_experience'],
                            'education' =>
                                $data['education'],
                            'academic_major' =>
                                $data['academic_major'],
                            'certifications' =>
                                $data['certifications'],
                            'specialized_training' =>
                                $data['specialized_training'],
                            'main_project' =>
                                $data['main_project'],
                            'project_role' =>
                                $data['project_role'],
                            'github_url' =>
                                $data['github_url'] !== ''
                                ? $data['github_url']
                                : null,
                            'linkedin_url' =>
                                $data['linkedin_url'] !== ''
                                ? $data['linkedin_url']
                                : null,
                            'portfolio_url' =>
                                $data['portfolio_url'] !== ''
                                ? $data['portfolio_url']
                                : null,
                            'web_security_answer' =>
                                $data['web_security_answer'],
                            'vulnerability_risk_finding_answer' =>
                                $data[
                                    'vulnerability_risk_finding_answer'
                                ],
                            'authorized_testing_answer' =>
                                $data[
                                    'authorized_testing_answer'
                                ],
                            'why_securescope' =>
                                $data['why_securescope'],
                            'contribution' =>
                                $data['contribution'],
                            'review_agreement' =>
                                1,
                            'declaration' =>
                                $data['declaration'],
                        ]);
                        $applicationId =
                            (int) db()->lastInsertId();
                        /*
                    |--------------------------------------------------------------------------
                    | Finalize Uploaded Files
                    |--------------------------------------------------------------------------
                    */
                        $uploadedFiles =
                            $registration['files'] ?? [];
                        if ($uploadedFiles !== []) {
                            $finalDirectory =
                                ANALYST_UPLOAD_ROOT
                                . DIRECTORY_SEPARATOR
                                . (string) $applicationId;
                            if (!is_dir($finalDirectory)) {
                                if (
                                    !mkdir(
                                        $finalDirectory,
                                        0750,
                                        true
                                    )
                                ) {
                                    throw new RuntimeException(
                                        'تعذر إنشاء مجلد ملفات الطلب.'
                                    );
                                }
                            }
                            foreach ($uploadedFiles as $file) {
                                $temporaryPath =
                                    (string) $file['temporary_path'];
                                $finalPath =
                                    $finalDirectory
                                    . DIRECTORY_SEPARATOR
                                    . $file['stored_name'];
                                if (!is_file($temporaryPath)) {
                                    throw new RuntimeException(
                                        'تعذر العثور على أحد الملفات المرفوعة.'
                                    );
                                }
                                if (
                                    !rename(
                                        $temporaryPath,
                                        $finalPath
                                    )
                                ) {
                                    throw new RuntimeException(
                                        'تعذر نقل أحد الملفات إلى مجلد الطلب.'
                                    );
                                }
                                $fileStatement = db()->prepare(
                                    "INSERT INTO analyst_application_files (
                application_id,
                file_type,
                original_name,
                stored_name,
                file_path,
                mime_type,
                file_size
            )
            VALUES (
                :application_id,
                :file_type,
                :original_name,
                :stored_name,
                :file_path,
                :mime_type,
                :file_size
            )"
                                );
                                $fileStatement->execute([
                                    'application_id' =>
                                        $applicationId,
                                    'file_type' =>
                                        $file['file_type'],
                                    'original_name' =>
                                        $file['original_name'],
                                    'stored_name' =>
                                        $file['stored_name'],
                                    'file_path' =>
                                        'storage/analyst-applications/'
                                        . $applicationId
                                        . '/'
                                        . $file['stored_name'],
                                    'mime_type' =>
                                        $file['mime_type'],
                                    'file_size' =>
                                        $file['file_size'],
                                ]);
                            }
                        }
                        db()->commit();
                        /*
                         * Clear temporary registration data.
                         */
                        unset(
                            $_SESSION[
                                ANALYST_FORM_SESSION
                            ]
                        );
                        set_flash(
                            'success',
                            'تم إرسال طلبك بنجاح، '
                            . 'وسيتم مراجعته من قبل الإدارة.'
                        );
                        redirect(
                            'register/analyst.php?submitted=1'
                        );
                    } catch (Throwable $exception) {
                        if (db()->inTransaction()) {
                            db()->rollBack();
                        }
                        error_log(
                            'Analyst registration error: '
                            . $exception->getMessage()
                        );
                        $errors[] =
                            'تعذر إرسال الطلب حاليًا. '
                            . 'حاول مرة أخرى.';
                    }
                }
            }
        }
    }
}
/*
|--------------------------------------------------------------------------
| Current form data
|--------------------------------------------------------------------------
*/
$data = $registration['data'] ?? [];
/*
|--------------------------------------------------------------------------
| Render
|--------------------------------------------------------------------------
*/
render_header(
    'التقدم للعمل كمحلل أمني',
    false
);
?>
<section class="auth-page">
    <div class="auth-card analyst-registration-card">
        <div class="auth-header">
            <div>
                <p class="eyebrow">
                    SecureScope
                </p>
                <h1>
                    التقدم للعمل كمحلل أمني
                </h1>
            </div>
            <a class="login-home-link" href="<?= e(url('index.php')) ?>">
                الرئيسية
            </a>
        </div>
        <p class="muted">
            قدم بياناتك للانضمام إلى فريق المحللين الأمنيين.
            تخضع جميع الطلبات لمراجعة الإدارة قبل إنشاء الحساب.
        </p>
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
        <?php if (
            isset($_GET['submitted'])
            && $_GET['submitted'] === '1'
        ): ?>
            <div class="alert alert-success" role="status">
                تم إرسال طلبك بنجاح.
                ستقوم الإدارة بمراجعته قبل إنشاء الحساب.
            </div>
        <?php else: ?>
            <!-- Progress -->
            <div class="analyst-progress">
                <div class="analyst-progress-header">
                    <strong>
                        الخطوة <?= e((string) $currentStep) ?>
                        من
                        <?= e((string) $totalSteps) ?>
                    </strong>
                    <span>
                        <?= e(
                            $steps[$currentStep]
                        ) ?>
                    </span>
                </div>
                <div class="analyst-progress-bar" aria-label="تقدم التسجيل" role="progressbar" aria-valuemin="1"
                    aria-valuemax="<?= e(
                        (string) $totalSteps
                    ) ?>" aria-valuenow="<?= e(
                         (string) $currentStep
                     ) ?>">
                    <span style="width: <?= e(
                        (string) (
                            ($currentStep / $totalSteps)
                            * 100
                        )
                    ) ?>%;"></span>
                </div>
            </div>
            <form method="post" action="<?= e(
                url('register/analyst.php')
            ) ?>" class="form-stack" enctype="multipart/form-data" novalidate>
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="next">
                <?php if ($currentStep === 1): ?>
                    <h2>
                        البيانات الشخصية
                    </h2>
                    <p class="muted">
                        أدخل معلومات التواصل الأساسية.
                    </p>
                    <div>
                        <label for="first_name">
                            الاسم الأول
                        </label>
                        <input id="first_name" name="first_name" type="text" value="<?= e(
                            (string) (
                                $data['first_name']
                                ?? ''
                            )
                        ) ?>" autocomplete="given-name" required>
                    </div>
                    <div>
                        <label for="last_name">
                            اسم العائلة
                        </label>
                        <input id="last_name" name="last_name" type="text" value="<?= e(
                            (string) (
                                $data['last_name']
                                ?? ''
                            )
                        ) ?>" autocomplete="family-name" required>
                    </div>
                    <div>
                        <label for="email">
                            البريد الإلكتروني
                        </label>
                        <input id="email" name="email" type="email" value="<?= e(
                            (string) (
                                $data['email']
                                ?? ''
                            )
                        ) ?>" autocomplete="email" required>
                    </div>
                    <div>
                        <label for="phone">
                            رقم الهاتف
                        </label>
                        <input id="phone" name="phone" type="tel" value="<?= e(
                            (string) (
                                $data['phone']
                                ?? ''
                            )
                        ) ?>" autocomplete="tel" required>
                    </div>
                    <div>
                        <label for="city">
                            الدولة / المدينة
                        </label>
                        <input id="city" name="city" type="text" value="<?= e(
                            (string) (
                                $data['city']
                                ?? ''
                            )
                        ) ?>" autocomplete="address-level2" required>
                    </div>
                <?php elseif ($currentStep === 2): ?>
                    <h2>
                        التعليم والخبرة
                    </h2>
                    <p class="muted">
                        معلوماتك الأكاديمية والمهنية.
                    </p>
                    <div>
                        <label for="education">
                            المؤهل العلمي
                        </label>
                        <select id="education" name="education" required>
                            <option value="">
                                اختر المؤهل
                            </option>
                            <?php
                            $educationOptions = [
                                'ثانوية عامة',
                                'دبلوم',
                                'بكالوريوس',
                                'ماجستير',
                                'دكتوراه',
                            ];
                            ?>
                            <?php foreach (
                                $educationOptions
                                as $option
                            ): ?>
                                <option value="<?= e($option) ?>" <?= (
                                      ($data['education'] ?? '')
                                      === $option
                                  )
                                      ? 'selected'
                                      : '' ?>>
                                    <?= e($option) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="academic_major">
                            التخصص الأكاديمي
                        </label>
                        <input id="academic_major" name="academic_major" type="text" value="<?= e(
                            (string) (
                                $data[
                                    'academic_major'
                                ] ?? ''
                            )
                        ) ?>" required>
                    </div>
                    <div>
                        <label for="current_job">
                            المسمى الوظيفي الحالي
                        </label>
                        <input id="current_job" name="current_job" type="text" value="<?= e(
                            (string) (
                                $data[
                                    'current_job'
                                ] ?? ''
                            )
                        ) ?>">
                    </div>
                    <div>
                        <label for="experience_level">
                            سنوات الخبرة
                        </label>
                        <select id="experience_level" name="experience_level" required>
                            <option value="">
                                اختر مستوى الخبرة
                            </option>
                            <?php
                            $experienceOptions = [
                                'أقل من سنة',
                                '1 - 2 سنة',
                                '3 - 5 سنوات',
                                'أكثر من 5 سنوات',
                            ];
                            ?>
                            <?php foreach (
                                $experienceOptions
                                as $option
                            ): ?>
                                <option value="<?= e($option) ?>" <?= (
                                      ($data[
                                          'experience_level'
                                      ] ?? '')
                                      === $option
                                  )
                                      ? 'selected'
                                      : '' ?>>
                                    <?= e($option) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php elseif ($currentStep === 3): ?>
    <div class="form-step-header">
        <span class="form-step-number">03</span>
        <div>
            <h2>التخصص والأدوات</h2>
            <p class="muted">
                حدد مجالات التخصص والأدوات وأنواع الاختبارات التي لديك خبرة بها.
            </p>
        </div>
    </div>
    <!-- =====================================================
         مجالات التخصص
         ===================================================== -->
    <div class="form-section">
        <div class="form-section-title">
            <h3>مجالات التخصص</h3>
            <span class="form-required">
                مطلوب
            </span>
        </div>
        <p class="form-help">
            اختر جميع المجالات التي تمتلك فيها معرفة أو خبرة عملية.
        </p>
        <?php
        $specializations = [
            'Web Application Security',
            'Network Security',
            'Penetration Testing',
            'Vulnerability Assessment',
            'API Security',
            'Cloud Security',
            'Digital Forensics',
        ];
        ?>
        <div class="selection-grid">
            <?php foreach ($specializations as $option): ?>
                <?php
                $isSelected = in_array(
                    $option,
                    $data['specialization'] ?? [],
                    true
                );
                ?>
                <label
                    class="selection-card <?= $isSelected ? 'selected' : '' ?>"
                >
                    <input
                        type="checkbox"
                        name="specialization[]"
                        value="<?= e($option) ?>"
                        <?= $isSelected ? 'checked' : '' ?>
                    >
                    <span class="selection-content">
                        <span class="selection-check">
                            <?= $isSelected ? '✓' : '' ?>
                        </span>
                        <span class="selection-text">
                            <?= e($option) ?>
                        </span>
                    </span>
                </label>
            <?php endforeach; ?>
        </div>
    </div>
    <!-- =====================================================
         الأدوات والتقنيات
         ===================================================== -->
    <div class="form-section">
        <div class="form-section-title">
            <h3>الأدوات والتقنيات</h3>
            <span class="form-required">
                مطلوب
            </span>
        </div>
        <p class="form-help">
            حدد الأدوات والتقنيات التي تستطيع استخدامها في أعمال الاختبار الأمني.
        </p>
        <?php
        $tools = [
            'Burp Suite',
            'Nmap',
            'Wireshark',
            'Metasploit',
            'Linux',
            'OWASP ZAP',
            'Nessus',
        ];
        ?>
        <div class="selection-grid">
            <?php foreach ($tools as $option): ?>
                <?php
                $isSelected = in_array(
                    $option,
                    $data['technical_tools'] ?? [],
                    true
                );
                ?>
                <label
                    class="selection-card <?= $isSelected ? 'selected' : '' ?>"
                >
                    <input
                        type="checkbox"
                        name="technical_tools[]"
                        value="<?= e($option) ?>"
                        <?= $isSelected ? 'checked' : '' ?>
                    >
                    <span class="selection-content">
                        <span class="selection-check">
                            <?= $isSelected ? '✓' : '' ?>
                        </span>
                        <span class="selection-text">
                            <?= e($option) ?>
                        </span>
                    </span>
                </label>
            <?php endforeach; ?>
        </div>
    </div>
    <!-- =====================================================
         أنواع الاختبارات الأمنية
         ===================================================== -->
    <div class="form-section">
        <div class="form-section-title">
            <h3>أنواع الاختبارات الأمنية</h3>
            <span class="form-required">
                مطلوب
            </span>
        </div>
        <p class="form-help">
            حدد أنواع التقييمات والاختبارات الأمنية التي تستطيع تنفيذها.
        </p>
        <?php
        $assessmentTypes = [
            'Web Applications',
            'APIs',
            'Networks',
            'Mobile Applications',
            'Cloud Infrastructure',
        ];
        ?>
        <div class="selection-grid">
            <?php foreach ($assessmentTypes as $option): ?>
                <?php
                $isSelected = in_array(
                    $option,
                    $data['assessment_types'] ?? [],
                    true
                );
                ?>
                <label
                    class="selection-card <?= $isSelected ? 'selected' : '' ?>"
                >
                    <input
                        type="checkbox"
                        name="assessment_types[]"
                        value="<?= e($option) ?>"
                        <?= $isSelected ? 'checked' : '' ?>
                    >
                    <span class="selection-content">
                        <span class="selection-check">
                            <?= $isSelected ? '✓' : '' ?>
                        </span>
                        <span class="selection-text">
                            <?= e($option) ?>
                        </span>
                    </span>
                </label>
            <?php endforeach; ?>
        </div>
    </div>
    <!-- =====================================================
         الخبرة في الأمن السيبراني
         ===================================================== -->
    <div class="form-section">
        <div class="form-section-title">
            <h3>الخبرة في الأمن السيبراني</h3>
            <span class="form-required">
                مطلوب
            </span>
        </div>
        <p class="form-help">
            اذكر خبرتك العملية والمشاريع أو الأنشطة الأمنية التي شاركت فيها.
        </p>
        <textarea
            id="security_experience"
            name="security_experience"
            rows="6"
            required
            placeholder="اكتب نبذة عن خبرتك في الأمن السيبراني..."
        ><?= e(
            (string) (
                $data['security_experience'] ?? ''
            )
        ) ?></textarea>
    </div>
                <?php elseif ($currentStep === 4): ?>
                    <h2>
                        المشاريع والشهادات
                    </h2>
                    <p class="muted">
                        أضف المعلومات التي تساعد الإدارة على تقييم خبرتك.
                    </p>
                    <div>
                        <label for="certifications">
                            الشهادات الأمنية
                        </label>
                        <textarea id="certifications" name="certifications" rows="3"><?= e(
                            (string) (
                                $data[
                                    'certifications'
                                ] ?? ''
                            )
                        ) ?></textarea>
                    </div>
                    <div>
                        <label for="specialized_training">
                            التدريب المتخصص
                        </label>
                        <textarea id="specialized_training" name="specialized_training" rows="3"><?= e(
                            (string) (
                                $data[
                                    'specialized_training'
                                ] ?? ''
                            )
                        ) ?></textarea>
                    </div>
                    <div>
                        <label for="main_project">
                            أهم مشروع أمني عملت عليه
                        </label>
                        <textarea id="main_project" name="main_project" rows="4"><?= e(
                            (string) (
                                $data[
                                    'main_project'
                                ] ?? ''
                            )
                        ) ?></textarea>
                    </div>
                    <div>
                        <label for="project_role">
                            الدور الذي قمت به في المشروع
                        </label>
                        <textarea id="project_role" name="project_role" rows="3"><?= e(
                            (string) (
                                $data[
                                    'project_role'
                                ] ?? ''
                            )
                        ) ?></textarea>
                    </div>
                    <div>
                        <label for="github_url">
                            رابط GitHub
                        </label>
                        <input id="github_url" name="github_url" type="url" value="<?= e(
                            (string) (
                                $data[
                                    'github_url'
                                ] ?? ''
                            )
                        ) ?>">
                    </div>
                    <div>
                        <label for="linkedin_url">
                            رابط LinkedIn
                        </label>
                        <input id="linkedin_url" name="linkedin_url" type="url" value="<?= e(
                            (string) (
                                $data[
                                    'linkedin_url'
                                ] ?? ''
                            )
                        ) ?>">
                    </div>
                    <div>
                        <label for="portfolio_url">
                            رابط Portfolio
                        </label>
                        <input id="portfolio_url" name="portfolio_url" type="url" value="<?= e(
                            (string) (
                                $data[
                                    'portfolio_url'
                                ] ?? ''
                            )
                        ) ?>">
                    </div>
                <?php elseif ($currentStep === 5): ?>
                    <h2>
                        الأسئلة التقنية
                    </h2>
                    <p class="muted">
                        تستخدم هذه الإجابات لمساعدة الإدارة في تقييم مستواك.
                    </p>
                    <div>
                        <label for="web_security_answer">
                            كيف تتعامل مع اختبار أمان تطبيق ويب؟
                        </label>
                        <textarea id="web_security_answer" name="web_security_answer" rows="5" required><?= e(
                            (string) (
                                $data[
                                    'web_security_answer'
                                ] ?? ''
                            )
                        ) ?></textarea>
                    </div>
                    <div>
                        <label for="vulnerability_risk_finding_answer">
                            ما الفرق بين Vulnerability و Risk و Finding؟
                        </label>
                        <textarea id="vulnerability_risk_finding_answer" name="vulnerability_risk_finding_answer" rows="5"
                            required><?= e(
                                (string) (
                                    $data[
                                        'vulnerability_risk_finding_answer'
                                    ] ?? ''
                                )
                            ) ?></textarea>
                    </div>
                    <div>
                        <label for="authorized_testing_answer">
                            كيف تتعامل مع ثغرة أثناء اختبار مصرح به؟
                        </label>
                        <textarea id="authorized_testing_answer" name="authorized_testing_answer" rows="5" required><?= e(
                            (string) (
                                $data[
                                    'authorized_testing_answer'
                                ] ?? ''
                            )
                        ) ?></textarea>
                    </div>
                    <div>
                        <label for="why_securescope">
                            لماذا تريد الانضمام إلى SecureScope؟
                        </label>
                        <textarea id="why_securescope" name="why_securescope" rows="4" required><?= e(
                            (string) (
                                $data[
                                    'why_securescope'
                                ] ?? ''
                            )
                        ) ?></textarea>
                    </div>
                    <div>
                        <label for="contribution">
                            ماذا يمكنك أن تقدم للفريق؟
                        </label>
                        <textarea id="contribution" name="contribution" rows="4" required><?= e(
                            (string) (
                                $data[
                                    'contribution'
                                ] ?? ''
                            )
                        ) ?></textarea>
                    </div>
                <?php elseif ($currentStep === 6): ?>
                <div class="form-step-header">
                    <span class="form-step-number">
                        06
                    </span>
                    <div>
                        <h2>
                            المستندات
                        </h2>
                        <p class="muted">
                            ارفع المستندات التي تساعد الإدارة على التحقق من مؤهلاتك وخبرتك.
                        </p>
                    </div>
                </div>
                <div class="alert alert-success">
                    <strong>
                        حماية الملفات:
                    </strong>
                    يتم تخزين الملفات بشكل منفصل عن قاعدة البيانات،
                    وتُحفظ معلوماتها فقط داخل النظام.
                </div>
                <!-- CV -->
                <div class="form-section">
                    <div class="form-section-title">
                        <h3>
                            السيرة الذاتية
                        </h3>
                        <span class="form-required">
                            مطلوب
                        </span>
                    </div>
                    <p class="form-help">
                        ارفع أحدث نسخة من سيرتك الذاتية.
                    </p>
                    <input type="file" name="cv" accept=".pdf,.jpg,.jpeg,.png" required>
                </div>
                <!-- Certificates -->
                <div class="form-section">
                    <div class="form-section-title">
                        <h3>
                            الشهادات
                        </h3>
                        <span class="form-required">
                            اختياري
                        </span>
                    </div>
                    <p class="form-help">
                        يمكنك رفع شهاداتك الأكاديمية أو الأمنية.
                    </p>
                    <input type="file" name="certificates[]" accept=".pdf,.jpg,.jpeg,.png" multiple>
                </div>
                <!-- Recommendations -->
                <div class="form-section">
                    <div class="form-section-title">
                        <h3>
                            خطابات التوصية
                        </h3>
                        <span class="form-required">
                            اختياري
                        </span>
                    </div>
                    <p class="form-help">
                        يمكنك رفع خطاب أو أكثر من خطابات التوصية.
                    </p>
                    <input type="file" name="recommendations[]" accept=".pdf,.jpg,.jpeg,.png" multiple>
                </div>
                <!-- Experience -->
                <div class="form-section">
                    <div class="form-section-title">
                        <h3>
                            شهادات الخبرة
                        </h3>
                        <span class="form-required">
                            اختياري
                        </span>
                    </div>
                    <p class="form-help">
                        أرفق المستندات التي تثبت خبرتك المهنية إن وجدت.
                    </p>
                    <input type="file" name="experience[]" accept=".pdf,.jpg,.jpeg,.png" multiple>
                </div>
                <!-- Additional -->
                <div class="form-section">
                    <div class="form-section-title">
                        <h3>
                            مستندات إضافية
                        </h3>
                        <span class="form-required">
                            اختياري
                        </span>
                    </div>
                    <p class="form-help">
                        يمكنك إرفاق أي مستند آخر يدعم طلبك.
                    </p>
                    <input type="file" name="additional[]" accept=".pdf,.jpg,.jpeg,.png" multiple>
                </div>
                <div class="alert">
                    <strong>
                        الملفات المسموحة:
                    </strong>
                    PDF، JPG، PNG
                    <br>
                    الحد الأقصى لحجم الملف الواحد:
                    5 ميجابايت.
                </div>
                <?php elseif ($currentStep === 7): ?>
                    <h2>
                        المراجعة والإقرار
                    </h2>
                    <p class="muted">
                        راجع بياناتك ثم أكد إرسال الطلب.
                    </p>
                    <div class="review-summary">
                        <p>
                            <strong>الاسم:</strong>
                            <?= e(
                                (string) (
                                    ($data['first_name'] ?? '')
                                    . ' '
                                    . ($data['last_name'] ?? '')
                                )
                            ) ?>
                        </p>
                        <p>
                            <strong>البريد:</strong>
                            <?= e(
                                (string) (
                                    $data['email'] ?? ''
                                )
                            ) ?>
                        </p>
                        <p>
                            <strong>المؤهل:</strong>
                            <?= e(
                                (string) (
                                    $data['education'] ?? ''
                                )
                            ) ?>
                        </p>
                        <p>
                            <strong>التخصص:</strong>
                            <?= e(
                                (string) (
                                    $data[
                                        'academic_major'
                                    ] ?? ''
                                )
                            ) ?>
                        </p>
                        <p>
                            <strong>الخبرة:</strong>
                            <?= e(
                                (string) (
                                    $data[
                                        'experience_level'
                                    ] ?? ''
                                )
                            ) ?>
                        </p>
                    </div>
                    <label class="checkbox-single">
                        <input type="checkbox" name="review_agreement" value="1" required>
                        <span>
                            أوافق على مراجعة بياناتي من قبل إدارة
                            SecureScope.
                        </span>
                    </label>
                    <div>
                        <label for="declaration">
                            الإقرار
                        </label>
                        <textarea id="declaration" name="declaration" rows="4" required><?= e(
                            (string) (
                                $data[
                                    'declaration'
                                ] ?? ''
                            )
                        ) ?></textarea>
                    </div>
                <?php endif; ?>
                <div class="form-actions">
                    <?php if ($currentStep > 1): ?>
                        <button type="submit" name="action" value="previous" class="button" formnovalidate>
                            السابق
                        </button>
                    <?php else: ?>
                        <a href="<?= e(
                            url('login.php')
                        ) ?>" class="button">
                            تسجيل الدخول
                        </a>
                    <?php endif; ?>
                    <button type="submit" name="action" value="next" class="button button-primary">
                        <?php if (
                            $currentStep === $totalSteps
                        ): ?>
                            إرسال الطلب
                        <?php else: ?>
                            التالي
                        <?php endif; ?>
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</section>
<?php render_footer(); ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const cards = document.querySelectorAll('.selection-card');
    cards.forEach(function (card) {
        const checkbox = card.querySelector(
            'input[type="checkbox"]'
        );
        const check = card.querySelector(
            '.selection-check'
        );
        if (!checkbox || !check) {
            return;
        }
        function updateSelection() {
            if (checkbox.checked) {
                card.classList.add('selected');
                check.textContent = '✓';
            } else {
                card.classList.remove('selected');
                check.textContent = '';
            }
        }
        checkbox.addEventListener(
            'change',
            updateSelection
        );
        updateSelection();
    });
});
</script>