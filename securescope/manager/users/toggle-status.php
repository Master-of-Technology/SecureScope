<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_role('Security Manager');
if (!is_post_request()) {
    redirect('manager/users/index.php');
}
require_valid_csrf();
$userId = filter_input(
    INPUT_POST,
    'user_id',
    FILTER_VALIDATE_INT
);
if ($userId === false || $userId === null || $userId <= 0) {
    set_flash('error', 'معرف المستخدم غير صالح.');
    redirect('manager/users/index.php');
}
$statement = db()->prepare(
    "SELECT
        u.user_id,
        u.status,
        u.first_name,
        u.last_name,
        r.role_name
     FROM users AS u
     INNER JOIN roles AS r
        ON r.role_id = u.role_id
     WHERE u.user_id = :user_id
     LIMIT 1"
);
$statement->execute([
    'user_id' => $userId,
]);
$user = $statement->fetch();
if ($user === false) {
    set_flash('error', 'لم يتم العثور على المستخدم.');
    redirect('manager/users/index.php');
}
if ((string) $user['role_name'] === 'Security Manager') {
    set_flash('error', 'لا يمكن تعطيل أو تفعيل حسابات المدراء.');
    redirect('manager/users/view.php?user_id=' . $userId);
}
$newStatus = (string) $user['status'] === 'active'
    ? 'inactive'
    : 'active';
$update = db()->prepare(
    "UPDATE users
     SET status = :status
     WHERE user_id = :user_id"
);
$update->execute([
    'status' => $newStatus,
    'user_id' => $userId,
]);
record_audit(
    $newStatus === 'active'
    ? 'ACTIVATE_USER'
    : 'DEACTIVATE_USER',
    'users',
    $userId,
    [
        'status' => $user['status'],
    ],
    [
        'status' => $newStatus,
    ]
);
set_flash(
    'success',
    $newStatus === 'active'
    ? 'تم تفعيل الحساب.'
    : 'تم تعطيل الحساب.'
);
redirect('manager/users/view.php?user_id=' . $userId);