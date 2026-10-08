<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

require_role('Security Manager');

$userId = filter_input(
    INPUT_GET,
    'user_id',
    FILTER_VALIDATE_INT
);

if ($userId === false || $userId === null || $userId <= 0) {

    set_flash(
        'error',
        'معرف المستخدم غير صالح.'
    );

    redirect('manager/users/index.php');
}


$statement = db()->prepare(
    "SELECT u.*, r.role_name
     FROM users AS u
     INNER JOIN roles AS r ON r.role_id = u.role_id
     WHERE u.user_id = :user_id
     LIMIT 1"
);

$statement->execute([
    'user_id' => $userId,
]);

$user = $statement->fetch();

if ($user === false) {

    set_flash(
        'error',
        'لم يتم العثور على المستخدم.'
    );

    redirect('manager/users/index.php');
}


$roles = db()
    ->query(
        "SELECT role_id, role_name
         FROM roles
         ORDER BY role_name"
    )
    ->fetchAll();

$clients = db()
    ->query(
        "SELECT client_id, company_name
         FROM clients
         WHERE status = 'active'
         ORDER BY company_name"
    )
    ->fetchAll();

$allowedStatuses = [
    'active',
    'inactive',
    'suspended',
];

$errors = [];


if (is_post_request()) {

    require_valid_csrf();

    $firstName = trim(
        (string) ($_POST['first_name'] ?? '')
    );

    $lastName = trim(
        (string) ($_POST['last_name'] ?? '')
    );

    $email = trim(
        (string) ($_POST['email'] ?? '')
    );

    $phone = trim(
        (string) ($_POST['phone'] ?? '')
    );

    $roleId = filter_input(
        INPUT_POST,
        'role_id',
        FILTER_VALIDATE_INT
    );

    $clientIdRaw = filter_input(
        INPUT_POST,
        'client_id',
        FILTER_VALIDATE_INT
    );

    $clientId = $clientIdRaw !== false
        ? $clientIdRaw
        : null;

    $status = trim(
        (string) ($_POST['status'] ?? '')
    );

    $isProtectedManager = (string) $user['role_name'] === 'Security Manager';

    if ($isProtectedManager) {
        if ($roleId !== (int) $user['role_id']) {
            $errors[] = 'لا يمكن تغيير دور حساب مدير الأمن.';
        }

        if ($status !== (string) $user['status']) {
            $errors[] = 'لا يمكن تغيير حالة حساب مدير الأمن.';
        }
    }


    if ($firstName === '') {
        $errors[] = 'الاسم الأول مطلوب.';
    }

    if ($lastName === '') {
        $errors[] = 'اسم العائلة مطلوب.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'البريد الإلكتروني غير صالح.';
    }

    if ($roleId === false || $roleId === null) {
        $errors[] = 'يجب اختيار الدور.';
    }

    if (!in_array($status, $allowedStatuses, true)) {
        $errors[] = 'حالة المستخدم غير صالحة.';
    }


    if ($roleId !== false && $roleId !== null) {

        $roleStatement = db()->prepare(
            "SELECT role_name
             FROM roles
             WHERE role_id = :role_id
             LIMIT 1"
        );

        $roleStatement->execute([
            'role_id' => $roleId,
        ]);

        $roleName = $roleStatement->fetchColumn();

        if ($roleName === false) {
            $errors[] = 'الدور المحدد غير موجود.';
        }

    } else {
        $roleName = null;
    }


    if ($roleName === 'Client') {

        if ($clientId === null) {
            $errors[] =
                'يجب اختيار الشركة لمستخدم Client.';
        }

    } else {

        $clientId = null;

    }


    if ($errors === []) {

        $emailStatement = db()->prepare(
            "SELECT user_id
             FROM users
             WHERE email = :email
               AND user_id <> :user_id
             LIMIT 1"
        );

        $emailStatement->execute([
            'email' => $email,
            'user_id' => $userId,
        ]);

        if ($emailStatement->fetchColumn() !== false) {
            $errors[] =
                'البريد الإلكتروني مستخدم بالفعل.';
        }

    }


    if ($errors === []) {

        $updateStatement = db()->prepare(
            "UPDATE users
             SET
                role_id = :role_id,
                client_id = :client_id,
                first_name = :first_name,
                last_name = :last_name,
                email = :email,
                phone = :phone,
                status = :status
             WHERE user_id = :user_id"
        );

        $updateStatement->execute([
            'role_id' => $roleId,
            'client_id' => $clientId,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'phone' => $phone !== ''
                ? $phone
                : null,
            'status' => $status,
            'user_id' => $userId,
        ]);


        record_audit(
            'UPDATE_USER',
            'users',
            $userId,
            [
                'role_id' => $user['role_id'],
                'client_id' => $user['client_id'],
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'email' => $user['email'],
                'phone' => $user['phone'],
                'status' => $user['status'],
            ],
            [
                'role_id' => $roleId,
                'client_id' => $clientId,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'phone' => $phone,
                'status' => $status,
            ]
        );


        set_flash(
            'success',
            'تم تحديث بيانات المستخدم.'
        );

        redirect(
            'manager/users/view.php?user_id='
            . $userId
        );
    }

} else {

    $_POST['first_name'] = $user['first_name'];
    $_POST['last_name'] = $user['last_name'];
    $_POST['email'] = $user['email'];
    $_POST['phone'] = $user['phone'];
    $_POST['role_id'] = $user['role_id'];
    $_POST['client_id'] = $user['client_id'];
    $_POST['status'] = $user['status'];
}


render_header('تعديل المستخدم');
?>

<section class="page-heading">

    <div>

        <p class="eyebrow">
            المستخدمون
        </p>

        <h1>
            تعديل المستخدم
        </h1>

        <p class="muted">
            تعديل بيانات الحساب وحالته.
        </p>

    </div>


    <div class="page-actions">

        <a href="<?= e(
            url(
                'manager/users/view.php?user_id='
                . $userId
            )
        ) ?>" class="button button-primary">
            إلغاء
        </a>

    </div>

</section>


<section class="details-card">

    <?php if ($errors !== []): ?>

        <div class="alert alert-error">

            <ul>

                <?php foreach ($errors as $error): ?>

                    <li>
                        <?= e($error) ?>
                    </li>

                <?php endforeach; ?>

            </ul>

        </div>

    <?php endif; ?>


    <form method="post">

        <?= csrf_input() ?>


        <div class="form-grid">

            <div class="form-field">

                <label for="first_name">
                    الاسم الأول
                </label>

                <input type="text" id="first_name" name="first_name" maxlength="100" required value="<?= e(
                    (string) $_POST['first_name']
                ) ?>">

            </div>


            <div class="form-field">

                <label for="last_name">
                    اسم العائلة
                </label>

                <input type="text" id="last_name" name="last_name" maxlength="100" required value="<?= e(
                    (string) $_POST['last_name']
                ) ?>">

            </div>


            <div class="form-field">

                <label for="email">
                    البريد الإلكتروني
                </label>

                <input type="email" id="email" name="email" maxlength="150" required autocomplete="off" value="<?= e(
                    (string) $_POST['email']
                ) ?>">

            </div>


            <div class="form-field">

                <label for="phone">
                    الهاتف
                </label>

                <input type="text" id="phone" name="phone" maxlength="30" value="<?= e(
                    (string) $_POST['phone']
                ) ?>">

            </div>


            <div class="form-field">

                <label for="role_id">
                    الدور
                </label>

                <select id="role_id" name="role_id" required <?= (string) $user['role_name'] === 'Security Manager' ? 'disabled' : '' ?>>

                    <?php foreach ($roles as $role): ?>

                        <option value="<?= e(
                            (string) $role['role_id']
                        ) ?>" <?= (
                             (string) $_POST['role_id']
                         ) === (string) $role['role_id']
                             ? 'selected'
                             : ''
                             ?>
                            >
                            <?= e(
                                (string) $role['role_name']
                            ) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

                <?php if ((string) $user['role_name'] === 'Security Manager'): ?>
                    <input type="hidden" name="role_id" value="<?= e((string) $user['role_id']) ?>">
                <?php endif; ?>

            </div>


            <div class="form-field">

                <label for="client_id">
                    الشركة
                </label>

                <select id="client_id" name="client_id">

                    <option value="">
                        لا توجد شركة
                    </option>

                    <?php foreach ($clients as $client): ?>

                        <option value="<?= e(
                            (string) $client['client_id']
                        ) ?>" <?= (
                             (string) (
                                 $_POST['client_id']
                                 ?? ''
                             )
                         ) === (string) $client['client_id']
                             ? 'selected'
                             : ''
                             ?>
                            >
                            <?= e(
                                (string) $client['company_name']
                            ) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

                <small>
                    تستخدم لمستخدم Client فقط.
                </small>

            </div>


            <div class="form-field">

                <label for="status">
                    الحالة
                </label>

                <select id="status" name="status" required <?= (string) $user['role_name'] === 'Security Manager' ? 'disabled' : '' ?>>

                    <?php foreach ($allowedStatuses as $option): ?>

                        <option value="<?= e($option) ?>" <?= (
                              $_POST['status']
                              ?? ''
                          ) === $option
                              ? 'selected'
                              : ''
                              ?>
                            >
                            <?= e(match ($option) {
                                'active' => 'نشط',
                                'inactive' => 'غير نشط',
                                'suspended' => 'موقوف',
                                default => $option,
                            }) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

                <?php if ((string) $user['role_name'] === 'Security Manager'): ?>
                    <input type="hidden" name="status" value="<?= e((string) $user['status']) ?>">
                <?php endif; ?>

            </div>

        </div>


        <div class="form-actions">

            <a href="<?= e(
                url(
                    'manager/users/view.php?user_id='
                    . $userId
                )
            ) ?>" class="button button-primary">
                إلغاء
            </a>

            <button type="submit" class="button button-primary">
                حفظ التعديلات
            </button>

        </div>

    </form>

</section>

<?php render_footer(); ?>