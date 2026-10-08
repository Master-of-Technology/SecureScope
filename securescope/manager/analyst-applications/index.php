<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

require_role('Security Manager');


$search = trim(
    (string) ($_GET['search'] ?? '')
);

$statusFilter = trim(
    (string) ($_GET['status'] ?? '')
);


$allowedStatuses = [
    'pending',
    'approved',
    'rejected',
    'cancelled',
];


$sql = "
    SELECT
        a.application_id,
        a.first_name,
        a.last_name,
        a.email,
        a.phone,
        a.experience_level,
        a.status,
        a.created_at,
        a.reviewed_at,
        r.role_name,
        u.first_name AS reviewer_first_name,
        u.last_name AS reviewer_last_name
    FROM analyst_applications AS a

    INNER JOIN roles AS r
        ON r.role_id = a.role_id

    LEFT JOIN users AS u
        ON u.user_id = a.reviewed_by

    WHERE 1 = 1
";


$params = [];


if ($search !== '') {

    $sql .= "
        AND (
            a.first_name LIKE :search_first_name
            OR a.last_name LIKE :search_last_name
            OR a.email LIKE :search_email
        )
    ";

    $searchValue = '%' . $search . '%';

    $params['search_first_name'] = $searchValue;
    $params['search_last_name'] = $searchValue;
    $params['search_email'] = $searchValue;
}


if (
    $statusFilter !== ''
    && in_array(
        $statusFilter,
        $allowedStatuses,
        true
    )
) {

    $sql .= "
        AND a.status = :status
    ";

    $params['status'] = $statusFilter;
}


$sql .= "
    ORDER BY
        CASE
            WHEN a.status = 'pending' THEN 0
            WHEN a.status = 'approved' THEN 1
            WHEN a.status = 'rejected' THEN 2
            ELSE 3
        END,
        a.created_at DESC
";


$statement = db()->prepare($sql);

$statement->execute($params);

$applications = $statement->fetchAll();


render_header('طلبات المحللين الأمنيين');
?>

<section class="page-heading">

    <div>

        <p class="eyebrow">
            إدارة التوظيف
        </p>

        <h1>
            طلبات المحللين الأمنيين
        </h1>

        <p class="muted">
            مراجعة طلبات الانضمام إلى فريق SecureScope الأمني.
        </p>

    </div>

</section>


<section class="details-card">

    <form method="get" class="filter-bar">

        <div class="form-field">

            <label for="search">
                البحث
            </label>

            <input type="search" id="search" name="search" value="<?= e($search) ?>"
                placeholder="اسم المتقدم أو البريد الإلكتروني">

        </div>


        <div class="form-field">

            <label for="status">
                الحالة
            </label>

            <select id="status" name="status">

                <option value="">
                    جميع الطلبات
                </option>

                <?php foreach ($allowedStatuses as $status): ?>

                    <option value="<?= e($status) ?>" <?= $statusFilter === $status
                          ? 'selected'
                          : ''
                          ?>
                        >
                        <?= e(match ($status) {
                            'pending' => 'قيد المراجعة',
                            'approved' => 'مقبول',
                            'rejected' => 'مرفوض',
                            'cancelled' => 'ملغى',
                            default => $status,
                        }) ?>
                    </option>

                <?php endforeach; ?>

            </select>

        </div>


        <div class="form-actions">

            <button type="submit" class="button button-primary">
                بحث
            </button>

            <a href="<?= e(
                url(
                    'manager/analyst-applications/index.php'
                )
            ) ?>" class="button button-primary">
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

                    <th>
                        المتقدم
                    </th>

                    <th>
                        الخبرة
                    </th>

                    <th>
                        الدور
                    </th>

                    <th>
                        الحالة
                    </th>

                    <th>
                        تاريخ التقديم
                    </th>

                    <th>
                        الإجراءات
                    </th>

                </tr>

            </thead>


            <tbody>

                <?php if ($applications === []): ?>

                    <tr>

                        <td colspan="6" class="empty-state">
                            لا توجد طلبات محللين أمنيين.
                        </td>

                    </tr>

                <?php else: ?>

                    <?php foreach ($applications as $application): ?>

                        <tr>

                            <td>

                                <strong>
                                    <?= e(
                                        trim(
                                            (string) $application['first_name']
                                            . ' '
                                            . (string) $application['last_name']
                                        )
                                    ) ?>
                                </strong>

                                <small>
                                    <?= e(
                                        (string) $application['email']
                                    ) ?>
                                </small>

                            </td>


                            <td>

                                <?= e(
                                    (string) (
                                        $application['experience_level']
                                        ?? '—'
                                    )
                                ) ?>

                            </td>


                            <td>

                                <?= e(
                                    (string) $application['role_name']
                                ) ?>

                            </td>


                            <td>

                                <span class="status-badge status-<?= e(
                                    (string) $application['status']
                                ) ?>">
                                    <?= e(match (
                                    (string) $application['status']
                                    ) {
                                        'pending' => 'قيد المراجعة',
                                        'approved' => 'مقبول',
                                        'rejected' => 'مرفوض',
                                        'cancelled' => 'ملغى',
                                        default => $application['status'],
                                    }) ?>
                                </span>

                            </td>


                            <td>

                                <?= e(
                                    (string) $application['created_at']
                                ) ?>

                            </td>


                            <td>

                                <div class="table-actions">

                                    <a href="<?= e(
                                        url(
                                            'manager/analyst-applications/view.php?application_id='
                                            . (int) $application['application_id']
                                        )
                                    ) ?>" class="button button-small">
                                        مراجعة
                                    </a>

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