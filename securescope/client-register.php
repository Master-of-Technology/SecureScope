<?php
declare(strict_types=1);

// Registration contains account information; prevent browsers from serving a cached form.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/includes/bootstrap.php';

if (is_logged_in()) {
    $user = current_user();
    redirect(role_home_path($user['role_name']));
}

$company = [
    'company_name' => '',
    'company_email' => '',
    'phone' => '',
    'address' => '',
    'industry' => '',
];

$contact = [
    'first_name' => '',
    'last_name' => '',
    'email' => '',
    'phone' => '',
];

$errors = [];

if (is_post_request()) {
    require_valid_csrf();

    foreach ($company as $field => $value) {
        $company[$field] = trim((string) ($_POST[$field] ?? ''));
    }

    foreach ($contact as $field => $value) {
        $inputName = $field === 'phone' ? 'contact_phone' : $field;
        $contact[$field] = trim((string) ($_POST[$inputName] ?? ''));
    }

    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirmation = (string) ($_POST['password_confirmation'] ?? '');

    if ($company['company_name'] === '') {
        $errors[] = 'أدخل اسم الشركة.';
    }

    if ($company['company_email'] !== '' && !filter_var($company['company_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'أدخل بريدًا إلكترونيًا صحيحًا للشركة.';
    }

    if ($company['industry'] === '') {
        $errors[] = 'أدخل مجال عمل الشركة.';
    }

    if ($contact['first_name'] === '' || $contact['last_name'] === '') {
        $errors[] = 'أدخل اسم ممثل الشركة كاملًا.';
    }

    if (!filter_var($contact['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'أدخل بريدًا إلكترونيًا صحيحًا لممثل الشركة.';
    }

    if ($password === '' || strlen($password) < 8) {
        $errors[] = 'يجب أن تتكون كلمة المرور من 8 أحرف على الأقل.';
    }

    if ($password !== $passwordConfirmation) {
        $errors[] = 'تأكيد كلمة المرور غير مطابق.';
    }

    if ($errors === []) {
        try {
            $pdo = db();

            // Resolve the requested role from the reference table instead of hard-coding its ID.
            $roleStatement = $pdo->prepare(
                'SELECT role_id FROM roles WHERE role_name = :role_name LIMIT 1'
            );
            $roleStatement->execute(['role_name' => 'Client']);
            $roleId = $roleStatement->fetchColumn();

            if ($roleId === false) {
                throw new RuntimeException('Client role is missing.');
            }

            // Do not create a real client or user yet. The request remains pending until management approves it.
            $existingUserStatement = $pdo->prepare(
                'SELECT user_id FROM users WHERE email = :email LIMIT 1'
            );
            $existingUserStatement->execute(['email' => $contact['email']]);

            if ($existingUserStatement->fetchColumn() !== false) {
                $errors[] = 'البريد الإلكتروني لممثل الشركة مستخدم بالفعل.';
            }

            $pendingUserRequestStatement = $pdo->prepare(
                'SELECT request_id
                 FROM registration_requests
                 WHERE email = :email AND status = \'pending\'
                 LIMIT 1'
            );
            $pendingUserRequestStatement->execute(['email' => $contact['email']]);

            if ($pendingUserRequestStatement->fetchColumn() !== false) {
                $errors[] = 'يوجد طلب تسجيل قيد المراجعة لهذا البريد الإلكتروني.';
            }

            if ($company['company_email'] !== '') {
                $existingCompanyStatement = $pdo->prepare(
                    'SELECT client_id FROM clients WHERE company_email = :company_email LIMIT 1'
                );
                $existingCompanyStatement->execute(['company_email' => $company['company_email']]);

                if ($existingCompanyStatement->fetchColumn() !== false) {
                    $errors[] = 'البريد الإلكتروني للشركة مستخدم بالفعل.';
                }

                $pendingCompanyRequestStatement = $pdo->prepare(
                    'SELECT request_id
                     FROM registration_requests
                     WHERE company_email = :company_email AND status = \'pending\'
                     LIMIT 1'
                );
                $pendingCompanyRequestStatement->execute(['company_email' => $company['company_email']]);

                if ($pendingCompanyRequestStatement->fetchColumn() !== false) {
                    $errors[] = 'يوجد طلب تسجيل قيد المراجعة لهذه الشركة.';
                }
            }

            if ($errors === []) {
                $requestStatement = $pdo->prepare(
                    'INSERT INTO registration_requests
                        (
                            registration_type,
                            role_id,
                            client_id,
                            first_name,
                            last_name,
                            email,
                            password_hash,
                            phone,
                            company_name,
                            company_email,
                            company_phone,
                            company_address,
                            industry,
                            status
                        )
                     VALUES
                        (
                            \'client\',
                            :role_id,
                            NULL,
                            :first_name,
                            :last_name,
                            :email,
                            :password_hash,
                            :phone,
                            :company_name,
                            :company_email,
                            :company_phone,
                            :company_address,
                            :industry,
                            \'pending\'
                        )'
                );

                $requestStatement->execute([
                    'role_id' => (int) $roleId,
                    'first_name' => $contact['first_name'],
                    'last_name' => $contact['last_name'],
                    'email' => $contact['email'],
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'phone' => $contact['phone'] !== '' ? $contact['phone'] : null,
                    'company_name' => $company['company_name'],
                    'company_email' => $company['company_email'] !== '' ? $company['company_email'] : null,
                    'company_phone' => $company['phone'] !== '' ? $company['phone'] : null,
                    'company_address' => $company['address'] !== '' ? $company['address'] : null,
                    'industry' => $company['industry'],
                ]);

                set_flash(
                    'success',
                    'تم إرسال طلب تسجيل الشركة بنجاح. سيبقى الطلب قيد المراجعة حتى توافق الإدارة عليه.'
                );
                redirect('login.php');
            }
        } catch (Throwable $exception) {
            error_log('SecureScope client registration request error: ' . $exception->getMessage());
            $errors[] = 'تعذر إرسال طلب التسجيل مؤقتًا. تأكد من إنشاء جدول طلبات التسجيل ثم حاول مرة أخرى.';
        }
    }
}

render_public_header('تسجيل عميل جديد');
?>
<section class="auth-page registration-page" aria-labelledby="registration-heading">
    <div class="auth-card registration-card">
        <div class="auth-header">
            <div>
                <p class="eyebrow">بوابة SecureScope</p>
                <h1 id="registration-heading">تسجيل عميل جديد</h1>
            </div>
            <a class="login-home-link" href="<?= e(url('login.php')) ?>">تسجيل الدخول</a>
        </div>

        <p class="muted">أرسل بيانات شركتك وممثلها. سيبقى الطلب قيد المراجعة ولن يتم إنشاء حساب فعلي حتى توافق الإدارة.</p>

        <?php if ($errors !== []): ?>
            <div class="alert alert-error" role="alert">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= e(url('client-register.php')) ?>" class="form-stack registration-form" autocomplete="off" novalidate>
            <?= csrf_input() ?>

            <div class="form-section-title">بيانات الشركة</div>

            <div>
                <label for="company_name">اسم الشركة</label>
                <input id="company_name" name="company_name" type="text" value="<?= e($company['company_name']) ?>" autocomplete="organization" required>
            </div>

            <div class="form-grid-two">
                <div>
                    <label for="company_email">البريد الإلكتروني للشركة</label>
                    <input id="company_email" name="company_email" type="email" value="<?= e($company['company_email']) ?>" autocomplete="organization">
                </div>
                <div>
                    <label for="company_phone">هاتف الشركة</label>
                    <input id="company_phone" name="phone" type="tel" value="<?= e($company['phone']) ?>" autocomplete="tel">
                </div>
            </div>

            <div>
                <label for="address">عنوان الشركة</label>
                <input id="address" name="address" type="text" value="<?= e($company['address']) ?>" autocomplete="street-address">
            </div>

            <div>
                <label for="industry">مجال عمل الشركة</label>
                <input id="industry" name="industry" type="text" value="<?= e($company['industry']) ?>" required>
            </div>

            <div class="form-section-title">بيانات ممثل الشركة</div>

            <div class="form-grid-two">
                <div>
                    <label for="first_name">الاسم الأول</label>
                    <input id="first_name" name="first_name" type="text" value="<?= e($contact['first_name']) ?>" autocomplete="given-name" required>
                </div>
                <div>
                    <label for="last_name">اسم العائلة</label>
                    <input id="last_name" name="last_name" type="text" value="<?= e($contact['last_name']) ?>" autocomplete="family-name" required>
                </div>
            </div>

            <div class="form-grid-two">
                <div>
                    <label for="email">بريد ممثل الشركة</label>
                    <input id="email" name="email" type="email" value="<?= e($contact['email']) ?>" autocomplete="email" required>
                </div>
                <div>
                    <label for="contact_phone">هاتف ممثل الشركة</label>
                    <input id="contact_phone" name="contact_phone" type="tel" value="<?= e($contact['phone']) ?>" autocomplete="tel">
                </div>
            </div>

            <div class="form-grid-two">
                <div>
                    <label for="password">كلمة المرور</label>
                    <input id="password" name="password" type="password" value="" autocomplete="new-password" minlength="8" required>
                </div>
                <div>
                    <label for="password_confirmation">تأكيد كلمة المرور</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" value="" autocomplete="new-password" minlength="8" required>
                </div>
            </div>

            <div class="registration-notice">
                <strong>مراجعة الإدارة:</strong>
                بعد إرسال الطلب، لن يتم إنشاء حساب أو تفعيله حتى تراجع الإدارة الطلب وتوافق عليه.
            </div>

            <button class="button button-primary" type="submit">إرسال طلب التسجيل</button>
            <a class="button button-secondary registration-cancel" href="<?= e(url('login.php')) ?>">العودة إلى تسجيل الدخول</a>
        </form>
    </div>
</section>
<?php render_footer(); ?>
