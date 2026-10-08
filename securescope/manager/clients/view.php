<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_role('Security Manager');

$clientId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$clientId || $clientId < 1) {
    set_flash('error', 'معرّف العميل غير صالح.');
    redirect('manager/clients/index.php');
}

$statement = db()->prepare('SELECT * FROM clients WHERE client_id = :client_id LIMIT 1');
$statement->execute(['client_id' => $clientId]);
$client = $statement->fetch();
if ($client === false) {
    set_flash('error', 'العميل غير موجود.');
    redirect('manager/clients/index.php');
}

$userStatement = db()->prepare(
    'SELECT user_id, first_name, last_name, email, status, last_login_at
     FROM users WHERE client_id = :client_id ORDER BY first_name, last_name'
);
$userStatement->execute(['client_id' => $clientId]);
$users = $userStatement->fetchAll();

$projectStatement = db()->prepare(
    'SELECT project_id, project_name, status, start_date, end_date
     FROM projects WHERE client_id = :client_id ORDER BY created_at DESC'
);
$projectStatement->execute(['client_id' => $clientId]);
$projects = $projectStatement->fetchAll();

render_header('عرض العميل');
?>
<section class="page-heading">
    <div><p class="eyebrow">تفاصيل العميل</p><h1><?= e($client['company_name']) ?></h1><p class="muted">معرّف العميل: #<?= e((string) $client['client_id']) ?></p></div>
    <div class="page-actions"><a class="button button-primary" href="<?= e(url('manager/clients/edit.php?id=' . $clientId)) ?>">تعديل</a><a class="button button-secondary-dark" href="<?= e(url('manager/clients/index.php')) ?>">العودة</a></div>
</section>

<section class="details-card detail-grid">
    <div><span class="detail-label">البريد الإلكتروني</span><strong><?= e($client['company_email']) ?: '—' ?></strong></div>
    <div><span class="detail-label">الهاتف</span><strong><?= e($client['phone']) ?: '—' ?></strong></div>
    <div><span class="detail-label">المجال</span><strong><?= e($client['industry']) ?: '—' ?></strong></div>
    <div><span class="detail-label">الحالة</span><strong><span class="status-badge status-<?= e($client['status']) ?>"><?= $client['status'] === 'active' ? 'نشط' : 'غير نشط' ?></span></strong></div>
    <div class="detail-wide"><span class="detail-label">العنوان</span><strong><?= e($client['address']) ?: '—' ?></strong></div>
</section>

<section class="details-card table-card">
    <div class="section-heading compact"><div><h2>حسابات العميل</h2><p class="muted">الحسابات المرتبطة بهذه الشركة.</p></div></div>
    <?php if ($users === []): ?><div class="empty-state"><strong>لا توجد حسابات.</strong><p>يمكن ربط مستخدمي العميل عند بناء وحدة إدارة المستخدمين.</p></div>
    <?php else: ?><div class="table-responsive"><table class="data-table"><thead><tr><th>الاسم</th><th>البريد</th><th>الحالة</th><th>آخر دخول</th></tr></thead><tbody><?php foreach ($users as $user): ?><tr><td><?= e($user['first_name'] . ' ' . $user['last_name']) ?></td><td><?= e($user['email']) ?></td><td><?= e($user['status']) ?></td><td><?= e($user['last_login_at'] ?? '—') ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
</section>

<section class="details-card table-card">
    <div class="section-heading compact"><div><h2>مشاريع العميل</h2><p class="muted">المشاريع المرتبطة بهذا العميل.</p></div></div>
    <?php if ($projects === []): ?><div class="empty-state"><strong>لا توجد مشاريع.</strong><p>ستظهر المشاريع هنا بعد تنفيذ وحدة إدارة المشاريع.</p></div>
    <?php else: ?><div class="table-responsive"><table class="data-table"><thead><tr><th>المشروع</th><th>الحالة</th><th>البداية</th><th>النهاية</th></tr></thead><tbody><?php foreach ($projects as $project): ?><tr><td><?= e($project['project_name']) ?></td><td><?= e($project['status']) ?></td><td><?= e($project['start_date'] ?? '—') ?></td><td><?= e($project['end_date'] ?? '—') ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
</section>

<section class="details-card danger-zone">
    <div><h2>إلغاء تفعيل العميل</h2><p class="muted">يفضل جعل العميل غير نشط بدل حذفه إذا كان مرتبطًا بمشاريع أو سجلات أخرى.</p></div>
    <?php if ($client['status'] === 'active'): ?><form method="post" action="<?= e(url('manager/clients/delete.php')) ?>" onsubmit="return confirm('هل تريد جعل هذا العميل غير نشط؟');"><?= csrf_input() ?><input type="hidden" name="client_id" value="<?= e((string) $clientId) ?>"><input type="hidden" name="mode" value="deactivate"><button class="button button-danger" type="submit">تعطيل العميل</button></form><?php endif; ?>
</section>
<?php render_footer(); ?>
