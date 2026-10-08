<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_role('Security Manager');
$statement = db()->query(
    "SELECT
        a.asset_id,
        a.client_id,
        a.asset_name,
        a.asset_type,
        a.identifier,
        a.description,
        a.status,
        a.created_at,
        c.company_name
     FROM assets AS a
     INNER JOIN clients AS c
        ON c.client_id = a.client_id
     ORDER BY a.created_at DESC"
);
$assets = $statement->fetchAll();
$statusLabels = [
    'active' => 'نشط',
    'inactive' => 'غير نشط',
    'retired' => 'متقاعد',
];
render_header('الأصول');
?>
<section class="page-heading">
    <div>
        <p class="eyebrow">إدارة الأصول</p>
        <h1>أصول العملاء</h1>
        <p class="muted">
            إدارة الأنظمة والأصول التي سيتم تقييمها أمنيًا.
        </p>
    </div>
    <div class="page-actions">
        <a
            href="<?= e(url('manager/assets/create.php')) ?>"
            class="button button-primary"
        >
            إضافة أصل
        </a>
    </div>
</section>
<section class="details-card">
    <?php if ($assets === []): ?>
            <div class="empty-state">
                <h2>لا توجد أصول</h2>
                <p class="muted">
                    لم تتم إضافة أي أصول للعملاء حتى الآن.
                </p>
            </div>
    <?php else: ?>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>الأصل</th>
                            <th>العميل</th>
                            <th>النوع</th>
                            <th>المعرّف</th>
                            <th>الحالة</th>
                            <th>تاريخ الإضافة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($assets as $asset): ?>
                                <tr>
                                    <td>
                                        <strong>
                                            <?= e((string) $asset['asset_name']) ?>
                                        </strong>
                                    </td>
                                    <td>
                                        <?= e((string) $asset['company_name']) ?>
                                    </td>
                                    <td>
                                        <?= e((string) $asset['asset_type']) ?>
                                    </td>
                                    <td>
                                        <?= e((string) ($asset['identifier'] ?? '—')) ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?= e((string) $asset['status']) ?>">
                                            <?= e(
                                                $statusLabels[(string) $asset['status']]
                                                ?? (string) $asset['status']
                                            ) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?= e((string) $asset['created_at']) ?>
                                    </td>
                                </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
    <?php endif; ?>
</section>
<?php render_footer(); ?>