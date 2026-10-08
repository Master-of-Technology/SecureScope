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
$currentUser = current_user();
if ((int) $currentUser['user_id'] === $userId) {
    set_flash('error', 'لا يمكنك حذف حسابك الحالي.');
    redirect('manager/users/index.php');
}
$statement = db()->prepare(
    "SELECT
        u.user_id,
        u.client_id,
        u.first_name,
        u.last_name,
        u.email,
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
if ((string) $user['role_name'] !== 'Client') {
    set_flash(
        'error',
        'لا يمكن حذف حسابات المدراء أو المحللين.'
    );
    redirect('manager/users/view.php?user_id=' . $userId);
}
if ($user['client_id'] === null) {
    set_flash(
        'error',
        'حساب العميل غير مرتبط بملف عميل صالح.'
    );
    redirect('manager/users/view.php?user_id=' . $userId);
}
$projectCheck = db()->prepare(
    "SELECT COUNT(*)
     FROM projects
     WHERE client_id = :client_id"
);
$projectCheck->execute([
    'client_id' => $user['client_id'],
]);
$projectCount = (int) $projectCheck->fetchColumn();
if ($projectCount > 0) {
    set_flash(
        'error',
        'لا يمكن حذف حساب العميل لأنه مرتبط بمشاريع. يمكنك تعطيل الحساب بدلًا من حذفه.'
    );
    redirect('manager/users/view.php?user_id=' . $userId);
}
$delete = db()->prepare(
    "DELETE FROM users
     WHERE user_id = :user_id
     LIMIT 1"
);
$delete->execute([
    'user_id' => $userId,
]);
record_audit(
    'DELETE_USER',
    'users',
    $userId,
    [
        'first_name' => $user['first_name'],
        'last_name' => $user['last_name'],
        'email' => $user['email'],
        'role_name' => $user['role_name'],
    ],
    null
);
set_flash(
    'success',
    'تم حذف حساب العميل بنجاح.'
);
redirect('manager/users/index.php');