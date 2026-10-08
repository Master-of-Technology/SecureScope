<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_role('Security Manager');
if (!is_post_request()) {
    redirect('manager/findings/index.php');
}
require_valid_csrf();
$findingId = filter_input(
    INPUT_POST,
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
    redirect('manager/findings/index.php');
}
$user = current_user();
if ($user === null) {
    logout_user();
    redirect('login.php');
}
$userId = (int) $user['user_id'];
try {
    db()->beginTransaction();
    $statement = db()->prepare(
        "UPDATE findings
         SET
            status = 'confirmed',
            reviewed_by = :reviewed_by,
            reviewed_at = NOW(),
            updated_at = NOW()
         WHERE finding_id = :finding_id
           AND status = 'open'"
    );
    $statement->execute([
        'reviewed_by' => $userId,
        'finding_id' => $findingId,
    ]);
    if ($statement->rowCount() !== 1) {
        throw new RuntimeException(
            'لا يمكن اعتماد Finding في حالتها الحالية.'
        );
    }
    record_audit(
        'CONFIRM_FINDING',
        'findings',
        $findingId,
        [
            'status' => 'open',
        ],
        [
            'status' => 'confirmed',
            'reviewed_by' => $userId,
        ]
    );
    db()->commit();
    set_flash(
        'success',
        'تم اعتماد Finding بنجاح.'
    );
    redirect(
        'manager/findings/view.php?finding_id='
        . $findingId
    );
} catch (Throwable $exception) {
    if (db()->inTransaction()) {
        db()->rollBack();
    }
    error_log(
        'SecureScope finding approval error: '
        . $exception->getMessage()
    );
    set_flash(
        'error',
        'حدث خطأ أثناء اعتماد Finding.'
    );
    redirect(
        'manager/findings/view.php?finding_id='
        . $findingId
    );
}