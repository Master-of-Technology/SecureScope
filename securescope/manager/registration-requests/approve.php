<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

require_role('Security Manager');

if (!is_post_request()) {
    redirect('manager/registration-requests/index.php');
}

require_valid_csrf();

$requestId = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);

if ($requestId === false || $requestId === null || $requestId <= 0) {
    set_flash('error', 'طلب التسجيل غير صالح.');
    redirect('manager/registration-requests/index.php');
}

$pdo = db();

try {
    $pdo->beginTransaction();

    /*
     * Lock the request while it is being processed.
     * This prevents two administrators from approving it simultaneously.
     */
    $statement = $pdo->prepare(
        'SELECT *
         FROM registration_requests
         WHERE request_id = :request_id
         FOR UPDATE'
    );

    $statement->execute([
        'request_id' => $requestId,
    ]);

    $request = $statement->fetch();

    if ($request === false) {
        throw new RuntimeException('طلب التسجيل غير موجود.');
    }

    if ($request['status'] !== 'pending') {
        throw new RuntimeException('هذا الطلب تمت معالجته مسبقًا.');
    }

    if ($request['registration_type'] !== 'client') {
        throw new RuntimeException(
            'طلبات المحللين الأمنيين لا تتم معالجتها من خلال تسجيل العملاء.'
        );
    }

    /*
     * Get the Client role from roles table.
     * We do not hard-code role_id.
     */
    $roleStatement = $pdo->prepare(
        'SELECT role_id
         FROM roles
         WHERE role_name = :role_name
         LIMIT 1'
    );

    $roleStatement->execute([
        'role_name' => 'Client',
    ]);

    $clientRoleId = $roleStatement->fetchColumn();

    if ($clientRoleId === false) {
        throw new RuntimeException('دور Client غير موجود في قاعدة البيانات.');
    }

    /*
     * Make sure the email has not already been used.
     */
    $existingUserStatement = $pdo->prepare(
        'SELECT user_id
         FROM users
         WHERE email = :email
         LIMIT 1'
    );

    $existingUserStatement->execute([
        'email' => $request['email'],
    ]);

    if ($existingUserStatement->fetchColumn() !== false) {
        throw new RuntimeException(
            'لا يمكن الموافقة: البريد الإلكتروني مستخدم بالفعل.'
        );
    }

    /*
     * Make sure the company email is not already registered.
     */
    if (!empty($request['company_email'])) {
        $existingClientStatement = $pdo->prepare(
            'SELECT client_id
             FROM clients
             WHERE company_email = :company_email
             LIMIT 1'
        );

        $existingClientStatement->execute([
            'company_email' => $request['company_email'],
        ]);

        if ($existingClientStatement->fetchColumn() !== false) {
            throw new RuntimeException(
                'لا يمكن الموافقة: البريد الإلكتروني للشركة مستخدم بالفعل.'
            );
        }
    }

    /*
     * Create the company.
     */
    $clientStatement = $pdo->prepare(
        'INSERT INTO clients
            (
                company_name,
                company_email,
                phone,
                address,
                industry,
                status
            )
         VALUES
            (
                :company_name,
                :company_email,
                :phone,
                :address,
                :industry,
                \'active\'
            )'
    );

    $clientStatement->execute([
        'company_name' => $request['company_name'],
        'company_email' => $request['company_email'],
        'phone' => $request['company_phone'],
        'address' => $request['company_address'],
        'industry' => $request['industry'],
    ]);

    $clientId = (int) $pdo->lastInsertId();

    /*
     * Create the client's user account.
     *
     * The password is already hashed in registration_requests.
     * We never put the password hash into audit_logs.
     */
    $userStatement = $pdo->prepare(
        'INSERT INTO users
            (
                role_id,
                client_id,
                first_name,
                last_name,
                email,
                password_hash,
                phone,
                status
            )
         VALUES
            (
                :role_id,
                :client_id,
                :first_name,
                :last_name,
                :email,
                :password_hash,
                :phone,
                \'active\'
            )'
    );

    $userStatement->execute([
        'role_id' => $clientRoleId,
        'client_id' => $clientId,
        'first_name' => $request['first_name'],
        'last_name' => $request['last_name'],
        'email' => $request['email'],
        'password_hash' => $request['password_hash'],
        'phone' => $request['phone'],
    ]);

    $userId = (int) $pdo->lastInsertId();

    /*
     * Mark the registration request as approved.
     */
    $updateStatement = $pdo->prepare(
        'UPDATE registration_requests
         SET
            status = \'approved\',
            reviewed_by = :reviewed_by,
            reviewed_at = NOW(),
            created_user_id = :created_user_id,
            created_client_id = :created_client_id,
            updated_at = CURRENT_TIMESTAMP
         WHERE request_id = :request_id'
    );

    $updateStatement->execute([
        'reviewed_by' => current_user()['user_id'],
        'created_user_id' => $userId,
        'created_client_id' => $clientId,
        'request_id' => $requestId,
    ]);

    /*
     * Audit log.
     * Never include password_hash.
     */
    record_audit(
        'APPROVE_REGISTRATION_REQUEST',
        'registration_requests',
        $requestId,
        [
            'status' => 'pending',
        ],
        [
            'status' => 'approved',
            'created_client_id' => $clientId,
            'created_user_id' => $userId,
        ]
    );

    $pdo->commit();

    set_flash(
        'success',
        'تمت الموافقة على الطلب وإنشاء حساب العميل والشركة بنجاح.'
    );

    redirect(
        'manager/registration-requests/view.php?request_id=' . $requestId
    );

} catch (Throwable $exception) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    set_flash(
        'error',
        $exception->getMessage()
    );

    redirect(
        'manager/registration-requests/view.php?request_id=' . $requestId
    );
}