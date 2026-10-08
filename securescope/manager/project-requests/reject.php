<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_role('Security Manager');
if (!is_post_request()) {
    redirect('manager/project-requests/index.php');
}
require_valid_csrf();
$user = current_user();
if ($user === null) {
    logout_user();
    redirect('login.php');
}
$requestId = filter_input(
    INPUT_POST,
    'request_id',
    FILTER_VALIDATE_INT
);
$managerNotes = trim((string) ($_POST['manager_notes'] ?? ''));
if (
    $requestId === false
    || $requestId === null
    || $requestId <= 0
) {
    set_flash('error', 'معرف الطلب غير صالح.');
    redirect('manager/project-requests/index.php');
}
if ($managerNotes === '') {
    set_flash('error', 'يجب كتابة سبب رفض الطلب.');
    redirect(
        'manager/project-requests/view.php?request_id='
        . (int) $requestId
    );
}
if (strlen($managerNotes) > 10000) {
    set_flash('error', 'ملاحظات الإدارة طويلة جدًا.');
    redirect(
        'manager/project-requests/view.php?request_id='
        . (int) $requestId
    );
}
$statement = db()->prepare(
    "UPDATE projects_requests
     SET
        status = 'rejected',
        reviewed_by = :reviewed_by,
        reviewed_at = NOW(),
        manager_notes = :manager_notes
     WHERE request_id = :request_id
       AND status = 'pending'"
);
$statement->execute([
    'reviewed_by' => (int) $user['user_id'],
    'manager_notes' => $managerNotes,
    'request_id' => $requestId,
]);
if ($statement->rowCount() === 0) {
    set_flash(
        'error',
        'الطلب غير موجود أو تمت معالجته مسبقًا.'
    );
    redirect('manager/project-requests/index.php');
}
set_flash(
    'success',
    'تم رفض طلب المشروع وإبلاغ العميل بالسبب.'
);
redirect(
    'manager/project-requests/view.php?request_id='
    . (int) $requestId
);