<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

require_role('Security Manager');


/*
|--------------------------------------------------------------------------
| Only POST requests are allowed
|--------------------------------------------------------------------------
*/

if (!is_post_request()) {

    set_flash(
        'error',
        'طريقة الطلب غير صحيحة.'
    );

    redirect(
        'manager/analyst-applications/index.php'
    );
}


require_valid_csrf();


/*
|--------------------------------------------------------------------------
| Get application ID
|--------------------------------------------------------------------------
*/

$applicationId = filter_input(
    INPUT_POST,
    'application_id',
    FILTER_VALIDATE_INT
);

if (
    $applicationId === false
    || $applicationId === null
    || $applicationId <= 0
) {

    set_flash(
        'error',
        'معرف الطلب غير صالح.'
    );

    redirect(
        'manager/analyst-applications/index.php'
    );
}


$currentUser = current_user();

if ($currentUser === null) {

    set_flash(
        'error',
        'تعذر تحديد المستخدم الحالي.'
    );

    redirect(
        'manager/analyst-applications/index.php'
    );
}


$currentUserId = (int) $currentUser['user_id'];

if ($currentUserId <= 0) {

    set_flash(
        'error',
        'معرف المستخدم الحالي غير صالح.'
    );

    redirect(
        'manager/analyst-applications/index.php'
    );
}


$pdo = db();


try {

    /*
    |--------------------------------------------------------------------------
    | Start transaction
    |--------------------------------------------------------------------------
    */

    $pdo->beginTransaction();


    /*
    |--------------------------------------------------------------------------
    | Lock the application
    |--------------------------------------------------------------------------
    |
    | Prevent two managers from approving
    | the same application simultaneously.
    |
    */

    $statement = $pdo->prepare(
        "SELECT
            application_id,
            role_id,
            first_name,
            last_name,
            email,
            phone,
            status
         FROM analyst_applications
         WHERE application_id = :application_id
         FOR UPDATE"
    );

    $statement->execute([
        'application_id' => $applicationId,
    ]);

    $application = $statement->fetch();


    if ($application === false) {

        throw new RuntimeException(
            'لم يتم العثور على طلب المحلل الأمني.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Only pending applications can be approved
    |--------------------------------------------------------------------------
    */

    if (
        (string) $application['status']
        !== 'pending'
    ) {

        throw new RuntimeException(
            'هذا الطلب تمت معالجته مسبقًا.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Verify Security Analyst role
    |--------------------------------------------------------------------------
    */

    $roleStatement = $pdo->prepare(
        "SELECT role_id, role_name
         FROM roles
         WHERE role_id = :role_id
         LIMIT 1"
    );

    $roleStatement->execute([
        'role_id' => $application['role_id'],
    ]);

    $role = $roleStatement->fetch();


    if (
        $role === false
        || $role['role_name'] !== 'Security Analyst'
    ) {

        throw new RuntimeException(
            'دور المحلل الأمني غير صالح.'
        );
    }


    $analystRoleId = (int) $role['role_id'];


    /*
    |--------------------------------------------------------------------------
    | Check email uniqueness
    |--------------------------------------------------------------------------
    */

    $emailStatement = $pdo->prepare(
        "SELECT user_id
         FROM users
         WHERE email = :email
         LIMIT 1"
    );

    $emailStatement->execute([
        'email' => $application['email'],
    ]);


    if ($emailStatement->fetchColumn() !== false) {

        throw new RuntimeException(
            'لا يمكن قبول الطلب لأن البريد الإلكتروني مستخدم بالفعل.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Generate temporary password
    |--------------------------------------------------------------------------
    |
    | The real password is never stored in the database.
    | Only its password_hash is stored.
    |
    */

    $temporaryPassword =
        bin2hex(
            random_bytes(8)
        );


    /*
    |--------------------------------------------------------------------------
    | Hash temporary password
    |--------------------------------------------------------------------------
    */

    $passwordHash = password_hash(
        $temporaryPassword,
        PASSWORD_DEFAULT
    );


    if ($passwordHash === false) {

        throw new RuntimeException(
            'تعذر إنشاء كلمة المرور المؤقتة.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Create Security Analyst account
    |--------------------------------------------------------------------------
    */

    $userStatement = $pdo->prepare(
        "INSERT INTO users
        (
            role_id,
            client_id,
            first_name,
            last_name,
            email,
            password_hash,
            must_change_password,
            phone,
            status
        )
        VALUES
        (
            :role_id,
            NULL,
            :first_name,
            :last_name,
            :email,
            :password_hash,
            1,
            :phone,
            'active'
        )"
    );


    $userStatement->execute([
        'role_id' => $analystRoleId,

        'first_name' =>
            $application['first_name'],

        'last_name' =>
            $application['last_name'],

        'email' =>
            $application['email'],

        'password_hash' =>
            $passwordHash,

        'phone' =>
            $application['phone'],
    ]);


    $createdUserId =
        (int) $pdo->lastInsertId();


    if ($createdUserId <= 0) {

        throw new RuntimeException(
            'تعذر إنشاء حساب المحلل الأمني.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Send acceptance email
    |--------------------------------------------------------------------------
    */

    $recipientName =
        trim(
            (string) $application['first_name']
            . ' '
            . (string) $application['last_name']
        );


    $emailSubject =
        'تم قبول طلبك للانضمام إلى SecureScope';


    $emailBody = '
<div style="
    font-family: Arial, sans-serif;
    direction: rtl;
    text-align: right;
    line-height: 1.8;
">

    <h2>
        تهانينا، تم قبول طلبك
    </h2>

    <p>
        مرحبًا '
        . e($recipientName)
        . '
    </p>

    <p>
        يسعدنا إبلاغك بأنه تمت الموافقة على طلبك
        للانضمام إلى فريق المحللين الأمنيين في
        <strong>SecureScope</strong>.
    </p>

    <h3>
        بيانات الدخول
    </h3>

    <p>
        <strong>البريد الإلكتروني:</strong><br>
        '
        . e((string) $application['email'])
        . '
    </p>

    <p>
        <strong>كلمة المرور المؤقتة:</strong><br>
        <code style="
            font-size: 18px;
            background: #f1f1f1;
            padding: 8px 12px;
            display: inline-block;
            direction: ltr;
        ">'
        . e($temporaryPassword)
        . '</code>
    </p>

    <p>
        هذه كلمة مرور مؤقتة مخصصة لتسجيل الدخول الأول فقط.
    </p>

    <p>
        <strong>
            سيطلب منك النظام تغيير كلمة المرور
            عند تسجيل الدخول لأول مرة.
        </strong>
    </p>

    <p>
        يرجى عدم مشاركة بيانات الدخول مع أي شخص.
    </p>

    <hr>

    <p>
        SecureScope<br>
        نظام إدارة تقييمات الأمن السيبراني
    </p>

</div>
';


    $plainBody =
        "مرحبًا {$recipientName}\n\n"
        . "تم قبول طلبك للانضمام إلى فريق المحللين الأمنيين في SecureScope.\n\n"
        . "بيانات الدخول:\n"
        . "البريد الإلكتروني: "
        . $application['email']
        . "\n"
        . "كلمة المرور المؤقتة: "
        . $temporaryPassword
        . "\n\n"
        . "يجب تغيير كلمة المرور عند تسجيل الدخول لأول مرة.\n\n"
        . "SecureScope";


    /*
    |--------------------------------------------------------------------------
    | Send email
    |--------------------------------------------------------------------------
    */

    $emailSent = send_email(
        (string) $application['email'],
        $recipientName,
        $emailSubject,
        $emailBody,
        $plainBody
    );


    if (!$emailSent) {

        throw new RuntimeException(
            'تعذر إرسال رسالة القبول إلى البريد الإلكتروني.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Update application status
    |--------------------------------------------------------------------------
    */

    $updateStatement = $pdo->prepare(
        "UPDATE analyst_applications
         SET
            status = 'approved',
            reviewed_by = :reviewed_by,
            reviewed_at = NOW(),
            rejection_reason = NULL
         WHERE application_id = :application_id
           AND status = 'pending'"
    );


    $updateStatement->execute([
        'reviewed_by' =>
            $currentUserId,

        'application_id' =>
            $applicationId,
    ]);


    if (
        $updateStatement->rowCount() !== 1
    ) {

        throw new RuntimeException(
            'تعذر تحديث حالة طلب المحلل الأمني.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Audit log
    |--------------------------------------------------------------------------
    |
    | IMPORTANT:
    | Never store the temporary password
    | or password hash in the audit log.
    |
    */

    record_audit(
        'APPROVE_ANALYST_APPLICATION',
        'analyst_applications',
        $applicationId,
        [
            'status' => 'pending',
        ],
        [
            'status' => 'approved',
            'reviewed_by' => $currentUserId,
            'created_user_id' => $createdUserId,
        ]
    );


    /*
    |--------------------------------------------------------------------------
    | Commit
    |--------------------------------------------------------------------------
    */

    $pdo->commit();


    /*
    |--------------------------------------------------------------------------
    | Success
    |--------------------------------------------------------------------------
    */

    set_flash(
        'success',
        'تم قبول طلب المحلل الأمني وإنشاء الحساب وإرسال بيانات الدخول إلى بريده الإلكتروني.'
    );


    redirect(
        'manager/analyst-applications/view.php?application_id='
        . $applicationId
    );


} catch (Throwable $exception) {


    /*
    |--------------------------------------------------------------------------
    | Rollback
    |--------------------------------------------------------------------------
    */

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }


    /*
    |--------------------------------------------------------------------------
    | Log real error
    |--------------------------------------------------------------------------
    */

    error_log(
        'Approve analyst application error: '
        . $exception->getMessage()
    );


    /*
    |--------------------------------------------------------------------------
    | User-friendly message
    |--------------------------------------------------------------------------
    */

    set_flash(
        'error',
        $exception->getMessage()
    );


    redirect(
        'manager/analyst-applications/view.php?application_id='
        . $applicationId
    );
}