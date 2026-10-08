<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

require_role('Security Manager');


$fileId = filter_input(
    INPUT_GET,
    'file_id',
    FILTER_VALIDATE_INT
);


if (
    $fileId === false
    || $fileId === null
    || $fileId <= 0
) {
    http_response_code(400);
    exit('ملف غير صالح.');
}


/*
 * Get file information.
 */
$statement = db()->prepare(
    "SELECT
        f.file_id,
        f.application_id,
        f.original_name,
        f.stored_name,
        f.file_path,
        f.mime_type,
        f.file_size
     FROM analyst_application_files AS f
     INNER JOIN analyst_applications AS a
        ON a.application_id = f.application_id
     WHERE f.file_id = :file_id
     LIMIT 1"
);

$statement->execute([
    'file_id' => $fileId,
]);

$file = $statement->fetch();


if ($file === false) {
    http_response_code(404);
    exit('لم يتم العثور على الملف.');
}


/*
 * Build the real filesystem path.
 *
 * We do NOT trust file_path from the database
 * as a filesystem path.
 */
$applicationId = (int) $file['application_id'];

$storedName = basename(
    (string) $file['stored_name']
);


$realPath =
    ROOT_PATH
    . DIRECTORY_SEPARATOR
    . 'storage'
    . DIRECTORY_SEPARATOR
    . 'analyst-applications'
    . DIRECTORY_SEPARATOR
    . $applicationId
    . DIRECTORY_SEPARATOR
    . $storedName;


/*
 * Make sure the file actually exists.
 */
if (!is_file($realPath)) {
    error_log(
        'SecureScope missing analyst document: '
        . $realPath
    );

    http_response_code(404);
    exit('الملف غير موجود على الخادم.');
}


/*
 * Make sure the file is readable.
 */
if (!is_readable($realPath)) {
    http_response_code(403);
    exit('لا يمكن الوصول إلى الملف.');
}


/*
 * Get MIME type from the actual file.
 */
$finfo = new finfo(FILEINFO_MIME_TYPE);

$mimeType = $finfo->file($realPath);

if ($mimeType === false) {
    http_response_code(415);
    exit('نوع الملف غير معروف.');
}


/*
 * Only allow the MIME types supported
 * by the analyst registration system.
 */
$allowedMimes = [
    'application/pdf',
    'image/jpeg',
    'image/png',
];


if (!in_array($mimeType, $allowedMimes, true)) {
    http_response_code(415);
    exit('نوع الملف غير مسموح به.');
}


/*
 * Prevent caching of private documents.
 */
header(
    'Cache-Control: private, no-store, no-cache, must-revalidate'
);

header('Pragma: no-cache');

header('Expires: 0');


/*
 * Tell the browser the real content type.
 */
header(
    'Content-Type: ' . $mimeType
);


/*
 * Allow the browser to open supported files
 * instead of forcing a download.
 */
header(
    'Content-Disposition: inline; filename="' .
    str_replace(
        '"',
        '',
        basename((string) $file['original_name'])
    )
    . '"'
);


/*
 * Send the correct file size.
 */
header(
    'Content-Length: ' . (string) filesize($realPath)
);


/*
 * Output the file.
 */
readfile($realPath);

exit;