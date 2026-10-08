<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_role('Security Manager');

if (!is_post_request()) {
    redirect('manager/clients/index.php');
}

require_valid_csrf();
$clientId = filter_var($_POST['client_id'] ?? null, FILTER_VALIDATE_INT);
$mode = (string) ($_POST['mode'] ?? 'deactivate');

if (!$clientId || $clientId < 1 || $mode !== 'deactivate') {
    set_flash('error', 'طلب غير صالح.');
    redirect('manager/clients/index.php');
}

$statement = db()->prepare('SELECT * FROM clients WHERE client_id = :client_id LIMIT 1');
$statement->execute(['client_id' => $clientId]);
$client = $statement->fetch();

if ($client === false) {
    set_flash('error', 'العميل غير موجود.');
    redirect('manager/clients/index.php');
}

$update = db()->prepare("UPDATE clients SET status = 'inactive' WHERE client_id = :client_id");
$update->execute(['client_id' => $clientId]);

record_audit('deactivate', 'clients', $clientId, ['status' => $client['status']], ['status' => 'inactive']);
set_flash('success', 'تم تعطيل العميل بدل حذفه للحفاظ على السجلات المرتبطة به.');
redirect('manager/clients/view.php?id=' . $clientId);
