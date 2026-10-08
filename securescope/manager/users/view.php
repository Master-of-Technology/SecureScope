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
    "SELECT
        u.*,
        r.role_name,
        c.company_name
     FROM users AS u

     INNER JOIN roles AS r
        ON r.role_id = u.role_id

     LEFT JOIN clients AS c
        ON c.client_id = u.client_id

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

render_header('تفاصيل المستخدم');
?>

<section class="page-heading">

    <div>

        <p class="eyebrow">
            المستخدمون
        </p>

        <h1>
            تفاصيل المستخدم
        </h1>

        <p class="muted">
            عرض بيانات حساب المستخدم وحالته.
        </p>

    </div>


   <div class="page-actions">
    <a
        href="<?= e(url('manager/users/index.php')) ?>"
        class="button button-primary"
    >
        العودة إلى المستخدمين
    </a>
    <a
        href="<?= e(
            url(
                'manager/users/edit.php?user_id='
                . $userId
            )
        ) ?>"
        class="button button-primary"
    >
        تعديل
    </a>
    <?php if ((string) $user['role_name'] !== 'Security Manager'): ?>
        <form
            method="post"
            action="<?= e(url('manager/users/toggle-status.php')) ?>"
            style="display:inline"
        >
            <?= csrf_input() ?>
            <input
                type="hidden"
                name="user_id"
                value="<?= e((string) $userId) ?>"
            >
            <button type="submit" class="button button-primary">
                <?= (string) $user['status'] === 'active'
                    ? 'تعطيل الحساب'
                    : 'تفعيل الحساب' ?>
            </button>
        </form>
        <?php if ((string) $user['role_name'] === 'Client'): ?>
            <?php
            $projectCheck = db()->prepare(
                'SELECT COUNT(*)
                 FROM projects
                 WHERE client_id = :client_id'
            );
            $projectCheck->execute([
                'client_id' => $user['client_id'],
            ]);
            $hasProjects = (int) $projectCheck->fetchColumn() > 0;
            ?>
            <?php if (!$hasProjects): ?>
                <form
                    method="post"
                    action="<?= e(url('manager/users/delete.php')) ?>"
                    style="display:inline"
                    onsubmit="return confirm('هل أنت متأكد من حذف هذا الحساب نهائيًا؟');"
                >
                    <?= csrf_input() ?>
                    <input
                        type="hidden"
                        name="user_id"
                        value="<?= e((string) $userId) ?>"
                    >
                    <button type="submit" class="button button-primary">
                        حذف الحساب
                    </button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>

</section>


<section class="details-card">

    <div class="detail-section">

        <h2>
            بيانات المستخدم
        </h2>


        <div class="detail-grid">

            <div>

                <span class="detail-label">
                    الاسم الكامل
                </span>

                <strong>
                    <?= e(
                        trim(
                            (string) $user['first_name']
                            . ' '
                            . (string) $user['last_name']
                        )
                    ) ?>
                </strong>

            </div>


            <div>

                <span class="detail-label">
                    البريد الإلكتروني
                </span>

                <strong>
                    <?= e((string) $user['email']) ?>
                </strong>

            </div>


            <div>

                <span class="detail-label">
                    الهاتف
                </span>

                <strong>
                    <?= e(
                        (string) (
                            $user['phone'] ?? '—'
                        )
                    ) ?>
                </strong>

            </div>


            <div>

                <span class="detail-label">
                    الدور
                </span>

                <strong>
                    <?= e((string) $user['role_name']) ?>
                </strong>

            </div>


            <div>

                <span class="detail-label">
                    الشركة
                </span>

                <strong>
                    <?= e(
                        (string) (
                            $user['company_name']
                            ?? '—'
                        )
                    ) ?>
                </strong>

            </div>


            <div>

                <span class="detail-label">
                    الحالة
                </span>

                <span
                    class="status-badge status-<?= e(
                        (string) $user['status']
                    ) ?>"
                >
                    <?= e(match ((string) $user['status']) {
                        'active' => 'نشط',
                        'inactive' => 'غير نشط',
                        'suspended' => 'موقوف',
                        default => $user['status'],
                    }) ?>
                </span>

            </div>


            <div>

                <span class="detail-label">
                    تاريخ إنشاء الحساب
                </span>

                <strong>
                    <?= e(
                        (string) $user['created_at']
                    ) ?>
                </strong>

            </div>


            <div>

                <span class="detail-label">
                    آخر دخول
                </span>

                <strong>
                    <?= e(
                        (string) (
                            $user['last_login_at']
                            ?? 'لم يسجل الدخول'
                        )
                    ) ?>
                </strong>

            </div>

        </div>

    </div>

</section>

<?php render_footer(); ?>