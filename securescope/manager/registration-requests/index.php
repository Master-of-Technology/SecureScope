<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

require_role('Security Manager');

$status = $_GET['status'] ?? 'pending';
$allowedStatuses = ['pending', 'approved', 'rejected', 'cancelled'];

if (!in_array($status, $allowedStatuses, true)) {
    $status = 'pending';
}

$search = trim((string) ($_GET['search'] ?? ''));

$sql = "
    SELECT
        rr.request_id,
        rr.registration_type,
        rr.first_name,
        rr.last_name,
        rr.email,
        rr.phone,
        rr.company_name,
        rr.company_email,
        rr.industry,
        rr.status,
        rr.created_at,
        r.role_name
    FROM registration_requests AS rr
    INNER JOIN roles AS r
        ON r.role_id = rr.role_id
    WHERE rr.status = :status
";

$params = [
    'status' => $status,
];

if ($search !== '') {
    $sql .= "
        AND (
            rr.first_name LIKE :search_name
            OR rr.last_name LIKE :search_last_name
            OR rr.email LIKE :search_email
            OR rr.company_name LIKE :search_company
            OR rr.company_email LIKE :search_company_email
        )
    ";

    $searchValue = '%' . $search . '%';

    $params['search_name'] = $searchValue;
    $params['search_last_name'] = $searchValue;
    $params['search_email'] = $searchValue;
    $params['search_company'] = $searchValue;
    $params['search_company_email'] = $searchValue;
}

$sql .= " ORDER BY rr.created_at DESC";

$statement = db()->prepare($sql);
$statement->execute($params);
$requests = $statement->fetchAll();

$pendingCount = (int) db()
    ->query("SELECT COUNT(*) FROM registration_requests WHERE status = 'pending'")
    ->fetchColumn();

render_header('طلبات التسجيل');
?>

<section class="page-heading">
    <div>
        <p class="eyebrow">إدارة النظام</p>
        <h1>طلبات التسجيل</h1>
        <p class="muted">
            مراجعة طلبات العملاء قبل إنشاء حساباتهم في SecureScope.
        </p>
    </div>

    <div class="button button-primary">
        <a href="<?= e(url('manager/clients/index.php')) ?>" class="button button-primary">
            العودة إلى العملاء
        </a>
    </div>
</section>

<section class="metric-grid">
    <article class="metric-card">
        <span>طلبات بانتظار المراجعة</span>
        <strong><?= e((string) $pendingCount) ?></strong>
    </article>
</section>

<section class="details-card">

    <form method="get" class="filter-bar">
        <div class="form-field">
            <label for="search">بحث</label>
            <input
                type="search"
                id="search"
                name="search"
                value="<?= e($search) ?>"
                placeholder="اسم المتقدم أو الشركة أو البريد"
            >
        </div>

        <div class="form-field">
            <label for="status">الحالة</label>
            <select id="status" name="status">
                <?php foreach ($allowedStatuses as $option): ?>
                    <option
                        value="<?= e($option) ?>"
                        <?= $status === $option ? 'selected' : '' ?>
                    >
                        <?= e(match ($option) {
                            'pending' => 'قيد المراجعة',
                            'approved' => 'مقبول',
                            'rejected' => 'مرفوض',
                            'cancelled' => 'ملغى',
                        }) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-actions">
            <button type="submit" class="button button-primary">
                بحث
            </button>

            <a
                href="<?= e(url('manager/registration-requests/index.php')) ?>"
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
                    <th>المتقدم</th>
                    <th>الشركة</th>
                    <th>نوع الحساب</th>
                    <th>تاريخ الطلب</th>
                    <th>الحالة</th>
                    <th>الإجراء</th>
                </tr>
            </thead>

            <tbody>
                <?php if ($requests === []): ?>
                    <tr>
                        <td colspan="6" class="empty-state">
                            لا توجد طلبات تسجيل في هذه الحالة.
                        </td>
                    </tr>
                <?php else: ?>

                    <?php foreach ($requests as $request): ?>

                        <tr>
                            <td>
                                <strong>
                                    <?= e(
                                        trim(
                                            (string) $request['first_name']
                                            . ' '
                                            . (string) $request['last_name']
                                        )
                                    ) ?>
                                </strong>

                                <small>
                                    <?= e((string) $request['email']) ?>
                                </small>
                            </td>

                            <td>
                                <?= e((string) ($request['company_name'] ?? '—')) ?>
                            </td>

                            <td>
                                <?= e(
                                    $request['registration_type'] === 'client'
                                        ? 'عميل'
                                        : 'محلل أمني'
                                ) ?>
                            </td>

                            <td>
                                <?= e((string) $request['created_at']) ?>
                            </td>

                            <td>
                                <span class="status-badge status-<?= e((string) $request['status']) ?>">
                                    <?= e(match ((string) $request['status']) {
                                        'pending' => 'قيد المراجعة',
                                        'approved' => 'مقبول',
                                        'rejected' => 'مرفوض',
                                        'cancelled' => 'ملغى',
                                        default => $request['status'],
                                    }) ?>
                                </span>
                            </td>

                            <td>
                                <a
                                    href="<?= e(
                                        url(
                                            'manager/registration-requests/view.php?request_id='
                                            . (int) $request['request_id']
                                        )
                                    ) ?>"
                                    class="button button-small"
                                >
                                    مراجعة
                                </a>
                            </td>
                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>
            </tbody>
        </table>
    </div>

</section>

<?php render_footer(); ?>