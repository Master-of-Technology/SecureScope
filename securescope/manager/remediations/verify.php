<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_role('Security Manager');

if (!is_post_request()) {
    redirect('manager/remediations/index.php');
}

require_valid_csrf();

$user = current_user();

if ($user === null) {
    logout_user();
    redirect('login.php');
}

$remediationId = filter_input(
    INPUT_POST,
    'remediation_id',
    FILTER_VALIDATE_INT
);

if (
    $remediationId === false
    || $remediationId === null
    || $remediationId <= 0
) {
    set_flash(
        'error',
        'معرف المعالجة غير صالح.'
    );
    redirect('manager/remediations/index.php');
}

$userId = (int) $user['user_id'];

try {
    db()->beginTransaction();

    $statement = db()->prepare(
        "UPDATE remediations
         SET
            status = 'verified',
            verified_by = :verified_by,
            verified_at = NOW(),
            updated_at = NOW()
         WHERE remediation_id = :remediation_id
           AND status = 'submitted'"
    );

    $statement->execute([
        'verified_by' => $userId,
        'remediation_id' => $remediationId,
    ]);

    if ($statement->rowCount() !== 1) {
        throw new RuntimeException(
            'لا يمكن اعتماد المعالجة في حالتها الحالية.'
        );
    }

    record_audit(
        'VERIFY_REMEDIATION',
        'remediations',
        $remediationId,
        [
            'status' => 'submitted',
        ],
        [
            'status' => 'verified',
            'verified_by' => $userId,
        ]
    );

    db()->commit();

    set_flash(
        'success',
        'تم التحقق من المعالجة واعتمادها بنجاح.'
    );

    redirect(
        'manager/remediations/view.php?remediation_id='
        . $remediationId
    );
} catch (Throwable $exception) {
    if (db()->inTransaction()) {
        db()->rollBack();
    }

    error_log(
        'SecureScope remediation verification error: '
        . $exception->getMessage()
    );

    set_flash(
        'error',
        'حدث خطأ أثناء التحقق من المعالجة.'
    );

    redirect(
        'manager/remediations/view.php?remediation_id='
        . $remediationId
    );
}