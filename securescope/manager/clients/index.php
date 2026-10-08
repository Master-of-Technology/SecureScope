<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_role('Security Manager');

$search = trim((string) ($_GET['q'] ?? ''));
$status = (string) ($_GET['status'] ?? '');

$sql = 'SELECT c.client_id, c.company_name, c.company_email, c.phone, c.industry, c.status, c.created_at,
               (SELECT COUNT(*) FROM users u WHERE u.client_id = c.client_id) AS users_count,
               (SELECT COUNT(*) FROM projects p WHERE p.client_id = c.client_id) AS projects_count
        FROM clients c
        WHERE 1=1';
$params = [];

if ($search !== '') {
    $sql .= ' AND (
        c.company_name LIKE :search_name
        OR c.company_email LIKE :search_email
        OR c.industry LIKE :search_industry
    )';

    $searchValue = '%' . $search . '%';

    $params['search_name'] = $searchValue;
    $params['search_email'] = $searchValue;
    $params['search_industry'] = $searchValue;
}

if (in_array($status, ['active', 'inactive'], true)) {
    $sql .= ' AND c.status = :status';
    $params['status'] = $status;
}

$sql .= ' ORDER BY c.created_at DESC, c.client_id DESC';
$statement = db()->prepare($sql);
$statement->execute($params);
$clients = $statement->fetchAll();

render_header('إدارة العملاء');
?>
<section class="page-heading">
    <div>
        <p class="eyebrow">إدارة العملاء</p>
        <h1>العملاء</h1>
        <p class="muted">إضافة ومراجعة الشركات التي تستفيد من خدمات SecureScope.</p>
    </div>
    <a href="<?= e(url('manager/registration-requests/index.php')) ?>"
   class="button button-primary">
    طلبات التسجيل
    </a>
</section>

<section class="details-card filter-card">
    <form class="filter-form" method="get">
        <div class="form-group">
            <label for="q">بحث</label>
            <input id="q" name="q" type="search" value="<?= e($search) ?>" placeholder="اسم الشركة أو البريد أو المجال">
        </div>
        <div class="form-group">
            <label for="status">الحالة</label>
            <select id="status" name="status">
                <option value="">الكل</option>
                <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>نشط</option>
                <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>غير نشط</option>
            </select>
        </div>
        <div class="filter-actions">
            <button class="button button-primary" type="submit">بحث</button>
            <a class="button button-secondary-dark" href="<?= e(url('manager/clients/index.php')) ?>">مسح</a>
        </div>
    </form>
</section>

<section class="details-card table-card">
    <div class="section-heading compact">
        <div>
            <h2>قائمة العملاء</h2>
            <p class="muted">عدد النتائج: <?= e((string) count($clients)) ?></p>
        </div>
    </div>

    <?php if ($clients === []): ?>
        <div class="empty-state">
            <strong>لا توجد نتائج.</strong>
            <p>ابدأ بإضافة عميل جديد أو غيّر معايير البحث.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>الشركة</th>
                        <th>البريد</th>
                        <th>المجال</th>
                        <th>المستخدمون</th>
                        <th>المشاريع</th>
                        <th>الحالة</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($clients as $client): ?>
                    <tr>
                        <td><strong><?= e($client['company_name']) ?></strong></td>
                        <td><?= e($client['company_email']) ?: '—' ?></td>
                        <td><?= e($client['industry']) ?: '—' ?></td>
                        <td><?= e((string) $client['users_count']) ?></td>
                        <td><?= e((string) $client['projects_count']) ?></td>
                        <td><span class="status-badge status-<?= e($client['status']) ?>"><?= $client['status'] === 'active' ? 'نشط' : 'غير نشط' ?></span></td>
                        <td>
                            <div class="table-actions">
                                <a href="<?= e(url('manager/clients/view.php?id=' . (int) $client['client_id'])) ?>">عرض</a>
                                <a href="<?= e(url('manager/clients/edit.php?id=' . (int) $client['client_id'])) ?>">تعديل</a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php render_footer(); ?>
