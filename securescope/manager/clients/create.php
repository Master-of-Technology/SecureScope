<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_role('Security Manager');

$errors = [];
$data = [
    'company_name' => '',
    'company_email' => '',
    'phone' => '',
    'address' => '',
    'industry' => '',
    'status' => 'active',
];

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
        $statement = db()->prepare(
            'INSERT INTO clients (company_name, company_email, phone, address, industry, status)
             VALUES (:company_name, :company_email, :phone, :address, :industry, :status)'
        );

        try {
            $statement->execute([
                'company_name' => $data['company_name'],
                'company_email' => $data['company_email'] !== '' ? $data['company_email'] : null,
                'phone' => $data['phone'] !== '' ? $data['phone'] : null,
                'address' => $data['address'] !== '' ? $data['address'] : null,
                'industry' => $data['industry'] !== '' ? $data['industry'] : null,
                'status' => $data['status'],
            ]);

            $clientId = (int) db()->lastInsertId();
            record_audit('create', 'clients', $clientId, null, [
                'company_name' => $data['company_name'],
                'company_email' => $data['company_email'],
                'phone' => $data['phone'],
                'address' => $data['address'],
                'industry' => $data['industry'],
                'status' => $data['status'],
            ]);

            set_flash('success', 'تمت إضافة العميل بنجاح.');
            redirect('manager/clients/view.php?id=' . $clientId);
        } catch (PDOException $exception) {
            if ((int) $exception->errorInfo[1] === 1062) {
                $errors[] = 'البريد الإلكتروني مستخدم لعميل آخر.';
            } else {
                $errors[] = 'تعذر حفظ العميل حاليًا.';
            }
        }
    }
}

render_header('إضافة عميل');
?>
<section class="page-heading">
    <div>
        <p class="eyebrow">العملاء</p>
        <h1>إضافة عميل</h1>
        <p class="muted">أدخل بيانات الشركة الأساسية لإنشاء سجل العميل.</p>
    </div>
</section>

<?php if ($errors !== []): ?>
    <div class="alert alert-error"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

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
        <div class="form-actions"><button class="button button-primary" type="submit">حفظ العميل</button><a class="button button-secondary-dark" href="<?= e(url('manager/clients/index.php')) ?>">إلغاء</a></div>
    </form>
</section>
<?php render_footer(); ?>
