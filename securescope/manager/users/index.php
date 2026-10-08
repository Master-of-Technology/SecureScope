<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

require_role('Security Manager');

$search = trim((string) ($_GET['search'] ?? ''));
$roleFilter = trim((string) ($_GET['role_id'] ?? ''));
$statusFilter = trim((string) ($_GET['status'] ?? ''));

$roles = db()
    ->query(
        "SELECT role_id, role_name
         FROM roles
         ORDER BY role_name"
    )
    ->fetchAll();

$allowedStatuses = [
    'active',
    'inactive',
    'suspended',
];

$sql = "
    SELECT
        u.user_id,
        u.first_name,
        u.last_name,
        u.email,
        u.phone,
        u.status,
        u.created_at,
        u.last_login_at,
        r.role_id,
        r.role_name,
        c.client_id,
        c.company_name
    FROM users AS u

    INNER JOIN roles AS r
        ON r.role_id = u.role_id

    LEFT JOIN clients AS c
        ON c.client_id = u.client_id

    WHERE 1 = 1
";

$params = [];

if ($search !== '') {
    $sql .= "
        AND (
            u.first_name LIKE :search_first_name
            OR u.last_name LIKE :search_last_name
            OR u.email LIKE :search_email
            OR c.company_name LIKE :search_company
        )
    ";

    $searchValue = '%' . $search . '%';

    $params['search_first_name'] = $searchValue;
    $params['search_last_name'] = $searchValue;
    $params['search_email'] = $searchValue;
    $params['search_company'] = $searchValue;
}

if ($roleFilter !== '' && ctype_digit($roleFilter)) {
    $sql .= " AND u.role_id = :role_id";
    $params['role_id'] = (int) $roleFilter;
}

if (in_array($statusFilter, $allowedStatuses, true)) {
    $sql .= " AND u.status = :status";
    $params['status'] = $statusFilter;
}

$sql .= " ORDER BY u.created_at DESC";

$statement = db()->prepare($sql);
$statement->execute($params);

$users = $statement->fetchAll();

render_header('المستخدمون');
?>

<section class="page-heading">

    <div>
        <p class="eyebrow">إدارة النظام</p>

        <h1>المستخدمون</h1>

        <p class="muted">
            إدارة حسابات المستخدمين والأدوار والحالات.
        </p>
    </div>

    <div class="page-actions">

        <a
            href="<?= e(url('manager/users/create.php')) ?>"
            class="button button-primary"
        >
            إضافة مستخدم
        </a>

    </div>

</section>


<section class="details-card">

    <form method="get" class="filter-bar">

        <div class="form-field">

            <label for="search">
                البحث
            </label>

            <input
                type="search"
                id="search"
                name="search"
                value="<?= e($search) ?>"
                placeholder="اسم المستخدم أو البريد أو الشركة"
            >

        </div>


        <div class="form-field">

            <label for="role_id">
                الدور
            </label>

            <select id="role_id" name="role_id">

                <option value="">
                    جميع الأدوار
                </option>

                <?php foreach ($roles as $role): ?>

                        <option
                            value="<?= e((string) $role['role_id']) ?>"
                            <?= $roleFilter === (string) $role['role_id'] ? 'selected' : '' ?>
                        >
                            <?= e((string) $role['role_name']) ?>
                        </option>

                <?php endforeach; ?>

            </select>

        </div>


        <div class="form-field">

            <label for="status">
                الحالة
            </label>

            <select id="status" name="status">

                <option value="">
                    جميع الحالات
                </option>

                <?php foreach ($allowedStatuses as $status): ?>

                        <option
                            value="<?= e($status) ?>"
                            <?= $statusFilter === $status ? 'selected' : '' ?>
                        >
                            <?= e(match ($status) {
                                'active' => 'نشط',
                                'inactive' => 'غير نشط',
                                'suspended' => 'موقوف',
                                default => $status,
                            }) ?>
                        </option>

                <?php endforeach; ?>

            </select>

        </div>


        <div class="form-actions">

            <button
                type="submit"
                class="button button-primary"
            >
                بحث
            </button>

            <a
                href="<?= e(url('manager/users/index.php')) ?>"
                class="button button-primary"
            >
                مسح
            </a>

        </div>

    </form>

</section>


<section class="details-card">

    <div class="table-wrapper">

        <table class="data-table">

            <thead>

                <tr>
                    <th>المستخدم</th>
                    <th>الدور</th>
                    <th>الشركة</th>
                    <th>الحالة</th>
                    <th>آخر دخول</th>
                    <th>الإجراءات</th>
                </tr>

            </thead>


            <tbody>

                <?php if ($users === []): ?>

                        <tr>

                            <td
                                colspan="6"
                                class="empty-state"
                            >
                                لا توجد حسابات مستخدمين.
                            </td>

                        </tr>

                <?php else: ?>

                        <?php foreach ($users as $user): ?>

                                <tr>

                                    <td>

                                        <strong>
                                            <?= e(
                                                trim(
                                                    (string) $user['first_name']
                                                    . ' '
                                                    . (string) $user['last_name']
                                                )
                                            ) ?>
                                        </strong>

                                        <small>
                                            <?= e((string) $user['email']) ?>
                                        </small>

                                    </td>


                                    <td>

                                        <span>
                                            <?= e((string) $user['role_name']) ?>
                                        </span>

                                    </td>


                                    <td>

                                        <?= e(
                                            (string) (
                                                $user['company_name']
                                                ?? '—'
                                            )
                                        ) ?>

                                    </td>


                                    <td>

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

                                    </td>


                                    <td>

                                        <?= e(
                                            (string) (
                                                $user['last_login_at']
                                                ?? 'لم يسجل الدخول'
                                            )
                                        ) ?>

                                    </td>


                                    <td>
                                        <div class="table-actions">
    <a
        href="<?= e(
            url(
                'manager/users/view.php?user_id='
                . (int) $user['user_id']
            )
        ) ?>"
        class="button button-small"
    >
        عرض
    </a>
    <a
        href="<?= e(
            url(
                'manager/users/edit.php?user_id='
                . (int) $user['user_id']
            )
        ) ?>"
        class="button button-small"
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
                value="<?= e((string) $user['user_id']) ?>"
            >
            <button
                type="submit"
                class="button button-small"
            >
                <?= (string) $user['status'] === 'active'
                    ? 'تعطيل'
                    : 'تفعيل' ?>
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
                        value="<?= e((string) $user['user_id']) ?>"
                    >
                    <button
                        type="submit"
                        class="button button-small"
                    >
                        حذف
                    </button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
            </div>
                                       
                                    </td>

                                </tr>

                        <?php endforeach; ?>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</section>

<?php render_footer(); ?>