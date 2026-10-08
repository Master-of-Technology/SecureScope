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

$data = [
    'company_name' => (string) $client['company_name'],
    'company_email' => (string) ($client['company_email'] ?? ''),
    'phone' => (string) ($client['phone'] ?? ''),
    'address' => (string) ($client['address'] ?? ''),
    'industry' => (string) ($client['industry'] ?? ''),
    'status' => (string) $client['status'],
];
$errors = [];

if (is_post_request()) {
    require_valid_csrf();

    foreach ($data as $field => $value) {
        if (isset($_POST[$field])) {
            $data[$field] = trim((string) $_POST[$field]);
        }
    }

    if ($data['company_name'] === '') {
        $errors[] = 'اسم الشركة مطلوب.';
    } elseif (mb_strlen($data['company_name']) > 200) {
        $errors[] = 'اسم الشركة يجب ألا يتجاوز 200 حرف.';
    }
    if ($data['company_email'] !== '' && !filter_var($data['company_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'البريد الإلكتروني غير صالح.';
    }
    if (!in_array($data['status'], ['active', 'inactive'], true)) {
        $errors[] = 'حالة العميل غير صالحة.';
    }

    if ($errors === []) {
        try {
            $update = db()->prepare(
                'UPDATE clients SET company_name = :company_name, company_email = :company_email,
                    phone = :phone, address = :address, industry = :industry, status = :status
                 WHERE client_id = :client_id'
            );
            $update->execute([
                'company_name' => $data['company_name'],
                'company_email' => $data['company_email'] !== '' ? $data['company_email'] : null,
                'phone' => $data['phone'] !== '' ? $data['phone'] : null,
                'address' => $data['address'] !== '' ? $data['address'] : null,
                'industry' => $data['industry'] !== '' ? $data['industry'] : null,
                'status' => $data['status'],
                'client_id' => $clientId,
            ]);

            record_audit('update', 'clients', $clientId, [
                'company_name' => $client['company_name'], 'company_email' => $client['company_email'],
                'phone' => $client['phone'], 'address' => $client['address'], 'industry' => $client['industry'], 'status' => $client['status'],
            ], $data);

            set_flash('success', 'تم تحديث بيانات العميل بنجاح.');
            redirect('manager/clients/view.php?id=' . $clientId);
        } catch (PDOException $exception) {
            if ((int) $exception->errorInfo[1] === 1062) {
                $errors[] = 'البريد الإلكتروني مستخدم لعميل آخر.';
            } else {
                $errors[] = 'تعذر تحديث العميل حاليًا.';
            }
        }
    }
}

render_header('تعديل عميل');
?>
<section class="page-heading">
    <div><p class="eyebrow">العملاء</p><h1>تعديل العميل</h1><p class="muted"><?= e($data['company_name']) ?></p></div>
</section>
<?php if ($errors !== []): ?><div class="alert alert-error"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<section class="details-card form-card">
    <form method="post" novalidate>
        <?= csrf_input() ?>
        <div class="form-grid">
            <div class="form-group"><label for="company_name">اسم الشركة *</label><input id="company_name" name="company_name" required maxlength="200" value="<?= e($data['company_name']) ?>"></div>
            <div class="form-group"><label for="company_email">البريد الإلكتروني</label><input id="company_email" name="company_email" type="email" maxlength="150" value="<?= e($data['company_email']) ?>"></div>
            <div class="form-group"><label for="phone">الهاتف</label><input id="phone" name="phone" maxlength="30" value="<?= e($data['phone']) ?>"></div>
            <div class="form-group"><label for="industry">المجال</label><input id="industry" name="industry" maxlength="100" value="<?= e($data['industry']) ?>"></div>
            <div class="form-group form-group-wide"><label for="address">العنوان</label><input id="address" name="address" maxlength="255" value="<?= e($data['address']) ?>"></div>
            <div class="form-group"><label for="status">الحالة</label><select id="status" name="status"><option value="active" <?= $data['status'] === 'active' ? 'selected' : '' ?>>نشط</option><option value="inactive" <?= $data['status'] === 'inactive' ? 'selected' : '' ?>>غير نشط</option></select></div>
        </div>
        <div class="form-actions"><button class="button button-primary" type="submit">حفظ التعديلات</button><a class="button button-secondary-dark" href="<?= e(url('manager/clients/view.php?id=' . $clientId)) ?>">إلغاء</a></div>
    </form>
</section>
<?php render_footer(); ?>
