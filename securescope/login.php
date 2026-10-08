<?php
declare(strict_types=1);

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/includes/bootstrap.php';

if (is_logged_in()) {
    $user = current_user();
    redirect(role_home_path($user['role_name']));
}

$email = '';
$errors = [];

if (is_post_request()) {
    require_valid_csrf();

    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'أدخل عنوان بريد إلكتروني صحيحًا.';
    }

    if ($password === '') {
        $errors[] = 'أدخل كلمة المرور.';
    }

    if ($errors === []) {
        try {
            $statement = db()->prepare(
                'SELECT u.user_id, u.client_id, u.first_name, u.last_name, u.email,
                        u.password_hash, u.must_change_password ,u.status, r.role_name
                 FROM users AS u
                 INNER JOIN roles AS r ON r.role_id = u.role_id
                 WHERE u.email = :email
                 LIMIT 1'
            );
            $statement->execute(['email' => $email]);
            $user = $statement->fetch();

            $isValidLogin = $user !== false
                && $user['status'] === 'active'
                && password_verify($password, $user['password_hash']);

            if (!$isValidLogin) {
                $errors[] = 'البريد الإلكتروني أو كلمة المرور غير صحيحة.';
            } else {
                session_regenerate_id(true);
                unset($_SESSION['csrf_token']);
                $_SESSION['user'] = session_user_payload($user);

                db()->prepare(
                    'UPDATE users SET last_login_at = NOW() WHERE user_id = :user_id'
                )->execute(['user_id' => $user['user_id']]);

                record_audit(
                    'login',
                    'users',
                    (int) $user['user_id']
                );

                if (
                    (int) $user['must_change_password'] === 1
                ) {

                    set_flash(
                        'success',
                        'يجب تغيير كلمة المرور المؤقتة قبل متابعة استخدام النظام.'
                    );

                    redirect(
                        'change-password.php'
                    );
                }

                set_flash(
                    'success',
                    'مرحبًا بعودتك، '
                    . $_SESSION['user']['full_name']
                    . '.'
                );

                redirect(
                    role_home_path(
                        $_SESSION['user']['role_name']
                    )
                );
            }
        } catch (Throwable $exception) {
            error_log('SecureScope login error: ' . $exception->getMessage());
            $errors[] = 'تسجيل الدخول غير متاح مؤقتًا. حاول مرة أخرى.';
        }
    }
}

render_header('تسجيل الدخول', false);
?>
<section class="auth-page" aria-labelledby="login-heading">
    <div class="auth-card">
        <div class="auth-header">
            <div>
                <p class="eyebrow">إدارة تقييمات الأمن السيبراني</p>
                <h1 id="login-heading">تسجيل الدخول إلى SecureScope</h1>
            </div>
            <a class="login-home-link" href="<?= e(url('index.php')) ?>">الرئيسية</a>
        </div>
        <p class="muted">استخدم الحساب المنشأ للدور المخصص لك.</p>

        <?php if ($errors !== []): ?>
            <div class="alert alert-error" role="alert">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= e(url('login.php')) ?>" class="form-stack" novalidate>
            <?= csrf_input() ?>
            <div>
                <label for="email">البريد الإلكتروني</label>
                <input id="email" name="email" type="email" value="" autocomplete="new-email" required>
            </div>
            <div>
                <label for="password">كلمة المرور</label>
                <input id="password" name="password" type="password" value="" autocomplete="new-password" required>
            </div>
            <button class="button button-primary" type="submit">تسجيل الدخول</button>
        </form>
     <br> 
        <div class="login-register-prompt">
    <p>ليس لديك حساب؟</p>

    <div class="login-register-options">

        <a
            href="<?= e(url('client-register.php')) ?>"
            class="login-register-option"
        >
            <strong>التسجيل كعميل</strong>
            <span>لأصحاب الشركات والعملاء الجدد</span>
        </a>
       
        <a
        href="<?= e(url('register/analyst.php')) ?>" class="login-register-option">
        <strong>التقدم كمحلل أمني</strong>
        <span>تقديم طلب للانضمام إلى فريق SecureScope</span>
    </a>
</div>
</div> 
    
</section>
<?php render_footer(); ?>
