<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_role('Security Manager');
$clientsStatement = db()->query(
    "SELECT
        client_id,
        company_name
     FROM clients
     WHERE status = 'active'
     ORDER BY company_name"
);
$clients = $clientsStatement->fetchAll();
$errors = [];
if (is_post_request()) {
    require_valid_csrf();
    $clientId = filter_input(
        INPUT_POST,
        'client_id',
        FILTER_VALIDATE_INT
    );
    $assetName = trim(
        (string) ($_POST['asset_name'] ?? '')
    );
    $assetType = trim(
        (string) ($_POST['asset_type'] ?? '')
    );
    $identifier = trim(
        (string) ($_POST['identifier'] ?? '')
    );
    $description = trim(
        (string) ($_POST['description'] ?? '')
    );
    if (
        $clientId === false
        || $clientId === null
        || $clientId <= 0
    ) {
        $errors[] = 'يجب اختيار العميل.';
    }
    if ($assetName === '') {
        $errors[] = 'اسم الأصل مطلوب.';
    } elseif (strlen($assetName) > 200) {
        $errors[] = 'اسم الأصل يجب ألا يتجاوز 200 حرف.';
    }
    if ($assetType === '') {
        $errors[] = 'نوع الأصل مطلوب.';
    } elseif (strlen($assetType) > 100) {
        $errors[] = 'نوع الأصل يجب ألا يتجاوز 100 حرف.';
    }
    if ($identifier !== '' && strlen($identifier) > 255) {
        $errors[] = 'المعرّف يجب ألا يتجاوز 255 حرفًا.';
    }
    if ($errors === []) {
        try {
            $clientStatement = db()->prepare(
                "SELECT client_id
                 FROM clients
                 WHERE client_id = :client_id
                   AND status = 'active'
                 LIMIT 1"
            );
            $clientStatement->execute([
                'client_id' => $clientId,
            ]);
            if ($clientStatement->fetch() === false) {
                $errors[] = 'العميل المحدد غير موجود أو غير نشط.';
            }
        } catch (Throwable $exception) {
            error_log(
                'SecureScope asset client validation error: '
                . $exception->getMessage()
            );
            $errors[] = 'تعذر التحقق من العميل.';
        }
    }
    if ($errors === []) {
        try {
            db()->beginTransaction();
            $insertStatement = db()->prepare(
                "INSERT INTO assets (
                    client_id,
                    asset_name,
                    asset_type,
                    identifier,
                    description,
                    status
                )
                VALUES (
                    :client_id,
                    :asset_name,
                    :asset_type,
                    :identifier,
                    :description,
                    'active'
                )"
            );
            $insertStatement->execute([
                'client_id' => $clientId,
                'asset_name' => $assetName,
                'asset_type' => $assetType,
                'identifier' => $identifier !== ''
                    ? $identifier
                    : null,
                'description' => $description !== ''
                    ? $description
                    : null,
            ]);
            $assetId = (int) db()->lastInsertId();
            if ($assetId <= 0) {
                throw new RuntimeException(
                    'تعذر إنشاء الأصل.'
                );
            }
            record_audit(
                'CREATE_ASSET',
                'assets',
                $assetId,
                null,
                [
                    'asset_id' => $assetId,
                    'client_id' => $clientId,
                    'asset_name' => $assetName,
                    'asset_type' => $assetType,
                    'status' => 'active',
                ]
            );
            db()->commit();
            set_flash(
                'success',
                'تمت إضافة الأصل بنجاح.'
            );
            redirect(
                'manager/assets/index.php'
            );
        } catch (Throwable $exception) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            error_log(
                'SecureScope asset creation error: '
                . $exception->getMessage()
            );
            $errors[] =
                'حدث خطأ أثناء إضافة الأصل. حاول مرة أخرى.';
        }
    }
}
render_header('إضافة أصل');
?>
<section class="page-heading">
    <div>
        <p class="eyebrow">إدارة الأصول</p>
        <h1>إضافة أصل</h1>
        <p class="muted">
            أضف نظامًا أو خدمة أو أصلًا تابعًا لأحد العملاء.
        </p>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('manager/assets/index.php')) ?>" class="button button-primary">
            العودة إلى الأصول
        </a>
    </div>
</section>
<section class="details-card">
    <?php if ($errors !== []): ?>
        <div class="alert alert-error" role="alert">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li>
                        <?= e($error) ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    <?php if ($clients === []): ?>
        <div class="empty-state">
            <h2>لا يوجد عملاء نشطون</h2>
            <p class="muted">
                يجب وجود عميل نشط قبل إضافة أصل.
            </p>
        </div>
    <?php else: ?>
        <form method="post">
            <?= csrf_input() ?>
            <div class="form-grid">
                <div class="form-field">
                    <label for="client_id">
                        العميل
                    </label>
                    <select id="client_id" name="client_id" required>
                        <option value="">
                            اختر العميل
                        </option>
                        <?php foreach ($clients as $client): ?>
                            <option value="<?= e((string) $client['client_id']) ?>" <?= (
                                   (string) ($_POST['client_id'] ?? '')
                                   === (string) $client['client_id']
                               ) ? 'selected' : '' ?>
                                >
                                <?= e((string) $client['company_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-field">
                    <label for="asset_name">
                        اسم الأصل
                    </label>
                    <input type="text" id="asset_name" name="asset_name" maxlength="200" required value="<?= e(
                        (string) (
                            $_POST['asset_name']
                            ?? ''
                        )
                    ) ?>" placeholder="مثال: الموقع الإلكتروني">
                </div>
                <div class="form-field">
                    <label for="asset_type">
                        نوع الأصل
                    </label>
                    <input type="text" id="asset_type" name="asset_type" maxlength="100" required value="<?= e(
                        (string) (
                            $_POST['asset_type']
                            ?? ''
                        )
                    ) ?>" placeholder="مثال: Web Application">
                </div>
                <div class="form-field">
                    <label for="identifier">
                        المعرّف
                    </label>
                    <input type="text" id="identifier" name="identifier" maxlength="255" value="<?= e(
                        (string) (
                            $_POST['identifier']
                            ?? ''
                        )
                    ) ?>" placeholder="مثال: example.com">
                </div>
                <div class="form-field">
                    <label for="description">
                        الوصف
                    </label>
                    <textarea id="description" name="description" rows="6"><?= e(
                        (string) (
                            $_POST['description']
                            ?? ''
                        )
                    ) ?></textarea>
                </div>
            </div>
            <div class="form-actions">
                <a href="<?= e(url('manager/assets/index.php')) ?>" class="button button-primary    ">
                    إلغاء
                </a>
                <button type="submit" class="button button-primary">
                    إضافة الأصل
                </button>
            </div>
        </form>
    <?php endif; ?>
</section>
<?php render_footer(); ?>