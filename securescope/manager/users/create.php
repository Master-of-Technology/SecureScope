<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

require_role('Security Manager');

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

$errors = [];

if (is_post_request()) {

    require_valid_csrf();

    $firstName = trim((string) ($_POST['first_name'] ?? ''));
    $lastName = trim((string) ($_POST['last_name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirmation = (string) ($_POST['password_confirmation'] ?? '');

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

    if (strlen($password) < 8) {
        $errors[] = 'كلمة المرور يجب أن تكون 8 أحرف على الأقل.';
    }

    if ($password !== $passwordConfirmation) {
        $errors[] = 'تأكيد كلمة المرور غير مطابق.';
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


    /*
     * Only Client users may be connected to a client.
     */
    if ($roleName === 'Client') {

        if ($clientId === null) {
            $errors[] = 'يجب اختيار الشركة لمستخدم Client.';
        }

    } else {

        $clientId = null;

    }


    if ($errors === []) {

        $emailStatement = db()->prepare(
            "SELECT user_id
             FROM users
             WHERE email = :email
             LIMIT 1"
        );

        $emailStatement->execute([
            'email' => $email,
        ]);

        if ($emailStatement->fetchColumn() !== false) {
            $errors[] = 'البريد الإلكتروني مستخدم بالفعل.';
        }

    }


    if ($errors === []) {

        $passwordHash = password_hash(
            $password,
            PASSWORD_DEFAULT
        );

        $statement = db()->prepare(
            "INSERT INTO users
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
                'active'
            )"
        );

        $statement->execute([
            'role_id' => $roleId,
            'client_id' => $clientId,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'password_hash' => $passwordHash,
            'phone' => $phone !== '' ? $phone : null,
        ]);

        $userId = (int) db()->lastInsertId();

        record_audit(
            'CREATE_USER',
            'users',
            $userId,
            null,
            [
                'user_id' => $userId,
                'role_id' => $roleId,
                'client_id' => $clientId,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'phone' => $phone,
                'status' => 'active',
            ]
        );

        set_flash(
            'success',
            'تم إنشاء المستخدم بنجاح.'
        );

        redirect(
            'manager/users/view.php?user_id=' . $userId
        );
    }
}

render_header('إضافة مستخدم');
?>

<section class="page-heading">

    <div>
        <p class="eyebrow">المستخدمون</p>

        <h1>إضافة مستخدم</h1>

        <p class="muted">
            إنشاء حساب مستخدم جديد من قبل مدير الأمن.
        </p>
    </div>

    <div class="page-actions">

        <a href="<?= e(url('manager/users/index.php')) ?>" class="button button-primary">
            العودة إلى المستخدمين
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
                    (string) ($_POST['first_name'] ?? '')
                ) ?>">

            </div>


            <div class="form-field">

                <label for="last_name">
                    اسم العائلة
                </label>

                <input type="text" id="last_name" name="last_name" maxlength="100" required value="<?= e(
                    (string) ($_POST['last_name'] ?? '')
                ) ?>">

            </div>


            <div class="form-field">

                <label for="email">
                    البريد الإلكتروني
                </label>

                <input type="email" id="email" name="email" maxlength="150" required autocomplete="off" value="<?= e(
                    (string) ($_POST['email'] ?? '')
                ) ?>">

            </div>


            <div class="form-field">

                <label for="phone">
                    الهاتف
                </label>

                <input type="text" id="phone" name="phone" maxlength="30" value="<?= e(
                    (string) ($_POST['phone'] ?? '')
                ) ?>">

            </div>


            <div class="form-field">

                <label for="role_id">
                    الدور
                </label>

                <select id="role_id" name="role_id" required>

                    <option value="">
                        اختر الدور
                    </option>

                    <?php foreach ($roles as $role): ?>

                        <option value="<?= e((string) $role['role_id']) ?>" <?= (
                               (string) (
                                   $_POST['role_id'] ?? ''
                               )
                           ) === (string) $role['role_id']
                               ? 'selected'
                               : ''
                               ?>
                            >
                            <?= e((string) $role['role_name']) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

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

                        <option value="<?= e((string) $client['client_id']) ?>" <?= (
                               (string) (
                                   $_POST['client_id'] ?? ''
                               )
                           ) === (string) $client['client_id']
                               ? 'selected'
                               : ''
                               ?>
                            >
                            <?= e((string) $client['company_name']) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

                <small>
                    تستخدم لمستخدم Client فقط.
                </small>

            </div>


            <div class="form-field">

                <label for="password">
                    كلمة المرور
                </label>

                <input type="password" id="password" name="password" minlength="8" required autocomplete="new-password">

            </div>


            <div class="form-field">

                <label for="password_confirmation">
                    تأكيد كلمة المرور
                </label>

                <input type="password" id="password_confirmation" name="password_confirmation" minlength="8" required
                    autocomplete="new-password">

            </div>

        </div>


        <div class="form-actions">

            <a href="<?= e(url('manager/users/index.php')) ?>" class="button button-primary">
                إلغاء
            </a>

            <button type="submit" class="button button-primary">
                إنشاء المستخدم
            </button>

        </div>

    </form>

</section>

<?php render_footer(); ?>