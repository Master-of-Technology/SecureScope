<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_role('Security Analyst');
$user = current_user();
if ($user === null) {
    logout_user();
    redirect('login.php');
}
$evidenceId = filter_input(
    INPUT_GET,
    'evidence_id',
    FILTER_VALIDATE_INT
);
if (
    $evidenceId === false
    || $evidenceId === null
    || $evidenceId <= 0
) {
    set_flash(
        'error',
        'معرف الدليل غير صالح.'
    );
    redirect('analyst/findings/index.php');
}
$statement = db()->prepare(
    "SELECT
        e.evidence_id,
        e.file_name,
        e.file_path,
        e.file_type,
        e.finding_id
     FROM evidence AS e
     INNER JOIN findings AS f
        ON f.finding_id = e.finding_id
     INNER JOIN assessments AS a
        ON a.assessment_id = f.assessment_id
     WHERE e.evidence_id = :evidence_id
       AND a.assigned_to = :assigned_to
     LIMIT 1"
);
$statement->execute([
    'evidence_id' => $evidenceId,
    'assigned_to' => (int) $user['user_id'],
]);
$evidence = $statement->fetch();
if ($evidence === false) {
    http_response_code(404);
    exit('الدليل غير موجود أو لا تملك صلاحية الوصول إليه.');
}
$basePath = realpath(
    __DIR__ . '/../../storage/evidence'
);
$filePath = realpath(
    __DIR__ . '/../../' . ltrim(
        (string) $evidence['file_path'],
        '/\\'
    )
);
if (
    $basePath === false
    || $filePath === false
    || !is_file($filePath)
) {
    http_response_code(404);
    exit('ملف الدليل غير موجود.');
}
$basePath = rtrim(
    str_replace('\\', '/', $basePath),
    '/'
);
$filePathNormalized = str_replace(
    '\\',
    '/',
    $filePath
);
if (
    $filePathNormalized !== $basePath
    && strpos(
        $filePathNormalized,
        $basePath . '/'
    ) !== 0
) {
    http_response_code(403);
    exit('الوصول إلى الملف غير مسموح.');
}
$mimeType = (string) $evidence['file_type'];
$allowedTypes = [
    'application/pdf',
    'image/jpeg',
    'image/png',
];
if (!in_array($mimeType, $allowedTypes, true)) {
    http_response_code(403);
    exit('نوع الملف غير مسموح.');
}
$fileSize = filesize($filePath);
if ($fileSize === false) {
    http_response_code(404);
    exit('تعذر قراءة الملف.');
}
$safeFileName = preg_replace(
    '/[^A-Za-z0-9._-]/',
    '_',
    (string) $evidence['file_name']
);
if (
    $safeFileName === null
    || $safeFileName === ''
) {
    $safeFileName = 'evidence';
}
record_audit(
    'VIEW_EVIDENCE',
    'evidence',
    $evidenceId,
    [],
    [
        'finding_id' => (int) $evidence['finding_id'],
    ]
);
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . (string) $fileSize);
header(
    'Content-Disposition: inline; filename="'
    . $safeFileName
    . '"'
);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($filePath);
exit;