<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

require_login();

$user = current_user();

if ($user === null) {
    redirect('login.php');
}

/*
 * If the user does not have a temporary password,
 * there is no reason to access this page.
 */
if (
    (int) ($user['must_change_password'] ?? 0) !== 1
) {
    redirect(
        role_home_path($user['role_name'])
    );
}

$errors = [];

if (is_post_request()) {

    require_valid_csrf();

    $currentPassword = (string) (
        $_POST['current_password'] ?? ''
    );

    $newPassword = (string) (
        $_POST['new_password'] ?? ''
    );

    $confirmPassword = (string) (
        $_POST['confirm_password'] ?? ''
    );


    /*
     * Basic validation.
     */
    if ($currentPassword === '') {
        $errors[] =
            'أدخل كلمة المرور المؤقتة الحالية.';
    }

    if ($newPassword === '') {
        $errors[] =
            'أدخل كلمة المرور الجديدة.';
    }

    if ($confirmPassword === '') {
        $errors[] =
            'أدخل تأكيد كلمة المرور الجديدة.';
    }


    /*
     * Password length.
     */
    if (
        $newPassword !== ''
        && strlen($newPassword) < 8
    ) {
        $errors[] =
            'كلمة المرور الجديدة يجب أن تكون 8 أحرف على الأقل.';
    }


    /*
     * Confirm new password.
     */
    if (
        $newPassword !== ''
        && $confirmPassword !== ''
        && $newPassword !== $confirmPassword
    ) {
        $errors[] =
            'تأكيد كلمة المرور الجديدة غير مطابق.';
    }


    /*
     * Do not allow the new password
     * to be identical to the temporary password.
     */
    if (
        $currentPassword !== ''
        && $newPassword !== ''
        && hash_equals(
            $currentPassword,
            $newPassword
        )
    ) {
        $errors[] =
            'يجب أن تكون كلمة المرور الجديدة مختلفة عن كلمة المرور المؤقتة.';
    }


    if ($errors === []) {

        try {

            /*
             * Retrieve the current password hash
             * directly from the database.
             */
            $statement = db()->prepare(
                'SELECT
                    user_id,
                    password_hash,
                    must_change_password,
                    status
                 FROM users
                 WHERE user_id = :user_id
                 LIMIT 1'
            );

            $statement->execute([
                'user_id' => $user['user_id'],
            ]);

            $databaseUser =
                $statement->fetch();


            if ($databaseUser === false) {

                $errors[] =
                    'لم يتم العثور على حساب المستخدم.';

            } elseif (
                $databaseUser['status'] !== 'active'
            ) {

                logout_user();

                set_flash(
                    'error',
                    'هذا الحساب غير نشط.'
                );

                redirect('login.php');

            } elseif (
                (int) $databaseUser[
                    'must_change_password'
                ] !== 1
            ) {

                redirect(
                    role_home_path(
                        $user['role_name']
                    )
                );

            } elseif (
                !password_verify(
                    $currentPassword,
                    $databaseUser['password_hash']
                )
            ) {

                $errors[] =
                    'كلمة المرور المؤقتة غير صحيحة.';
            }


            if ($errors === []) {

                /*
                 * Hash the new password.
                 */
                $newPasswordHash =
                    password_hash(
                        $newPassword,
                        PASSWORD_DEFAULT
                    );


                if ($newPasswordHash === false) {
                    throw new RuntimeException(
                        'تعذر إنشاء كلمة المرور الجديدة.'
                    );
                }


                db()->beginTransaction();

                try {

                    /*
                     * Replace the temporary password
                     * and remove the forced-change flag.
                     */
                    $updateStatement =
                        db()->prepare(
                            'UPDATE users
                             SET
                                password_hash = :password_hash,
                                must_change_password = 0,
                                updated_at = CURRENT_TIMESTAMP
                             WHERE user_id = :user_id
                               AND status = \'active\'
                               AND must_change_password = 1'
                        );

                    $updateStatement->execute([
                        'password_hash' =>
                            $newPasswordHash,

                        'user_id' =>
                            $user['user_id'],
                    ]);


                    if (
                        $updateStatement->rowCount() !== 1
                    ) {
                        throw new RuntimeException(
                            'لم يتم تحديث كلمة المرور.'
                        );
                    }


                    /*
                     * Audit the password change.
                     *
                     * IMPORTANT:
                     * Never store the password itself
                     * or its hash in the audit log.
                     */
                    record_audit(
                        'CHANGE_TEMPORARY_PASSWORD',
                        'users',
                        (int) $user['user_id'],
                        [
                            'must_change_password' => 1,
                        ],
                        [
                            'must_change_password' => 0,
                        ]
                    );


                    db()->commit();


                    /*
                     * Update the current session.
                     */
                    $_SESSION['user'][
                        'must_change_password'
                    ] = 0;


                    set_flash(
                        'success',
                        'تم تغيير كلمة المرور بنجاح.'
                    );


                    redirect(
                        role_home_path(
                            $user['role_name']
                        )
                    );

                } catch (Throwable $exception) {

                    if (
                        db()->inTransaction()
                    ) {
                        db()->rollBack();
                    }

                    throw $exception;
                }
            }

        } catch (Throwable $exception) {

            error_log(
                'SecureScope password change error: '
                . $exception->getMessage()
            );

            $errors[] =
                'حدث خطأ أثناء تغيير كلمة المرور. حاول مرة أخرى.';
        }
    }
}

render_header(
    'تغيير كلمة المرور',
    false
);
?>

<section
    class="auth-page"
    aria-labelledby="change-password-heading"
>

    <div class="auth-card">

        <div class="auth-header">

            <div>

                <p class="eyebrow">
                    SecureScope
                </p>

                <h1 id="change-password-heading">
                    تغيير كلمة المرور
                </h1>

            </div>

        </div>


        <p class="muted">
            كلمة المرور الحالية مؤقتة. يجب إنشاء كلمة مرور جديدة
            قبل متابعة استخدام النظام.
        </p>


        <?php if ($errors !== []): ?>

            <div
                class="alert alert-error"
                role="alert"
            >

                <ul>

                    <?php foreach ($errors as $error): ?>

                        <li>
                            <?= e($error) ?>
                        </li>

                    <?php endforeach; ?>

                </ul>

            </div>

        <?php endif; ?>


        <form
            method="post"
            action="<?= e(url('change-password.php')) ?>"
            class="form-stack"
            novalidate
        >

            <?= csrf_input() ?>


            <div>

                <label for="current_password">
                    كلمة المرور المؤقتة
                </label>

                <input
                    id="current_password"
                    name="current_password"
                    type="password"
                    autocomplete="current-password"
                    required
                >

            </div>


            <div>

                <label for="new_password">
                    كلمة المرور الجديدة
                </label>

                <input
                    id="new_password"
                    name="new_password"
                    type="password"
                    minlength="8"
                    autocomplete="new-password"
                    required
                >

                <small>
                    يجب أن تكون 8 أحرف على الأقل.
                </small>

            </div>


            <div>

                <label for="confirm_password">
                    تأكيد كلمة المرور الجديدة
                </label>

                <input
                    id="confirm_password"
                    name="confirm_password"
                    type="password"
                    minlength="8"
                    autocomplete="new-password"
                    required
                >

            </div>


            <button
                class="button button-primary"
                type="submit"
            >
                تغيير كلمة المرور
            </button>

        </form>

    </div>

</section>

<?php render_footer(); ?>