<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_role('Security Manager');
if (!is_post_request()) {
    redirect('manager/project-requests/index.php');
}
require_valid_csrf();
$user = current_user();
if ($user === null) {
    logout_user();
    redirect('login.php');
}
$requestId = filter_input(
    INPUT_POST,
    'request_id',
    FILTER_VALIDATE_INT
);
$assessmentTypeId = filter_input(
    INPUT_POST,
    'assessment_type_id',
    FILTER_VALIDATE_INT
);
$projectName = trim((string) ($_POST['project_name'] ?? ''));
$managerNotes = trim((string) ($_POST['manager_notes'] ?? ''));
if (
    $requestId === false
    || $requestId === null
    || $requestId <= 0
) {
    set_flash('error', 'معرف الطلب غير صالح.');
    redirect('manager/project-requests/index.php');
}
if (
    $assessmentTypeId === false
    || $assessmentTypeId === null
    || $assessmentTypeId <= 0
) {
    set_flash('error', 'يجب اختيار نوع التقييم.');
    redirect(
        'manager/project-requests/view.php?request_id='
        . (int) $requestId
    );
}
if ($projectName === '') {
    set_flash('error', 'اسم المشروع مطلوب.');
    redirect(
        'manager/project-requests/view.php?request_id='
        . (int) $requestId
    );
}
if (strlen($projectName) > 200) {
    set_flash('error', 'اسم المشروع طويل جدًا.');
    redirect(
        'manager/project-requests/view.php?request_id='
        . (int) $requestId
    );
}
if (strlen($managerNotes) > 10000) {
    set_flash('error', 'ملاحظات الإدارة طويلة جدًا.');
    redirect(
        'manager/project-requests/view.php?request_id='
        . (int) $requestId
    );
}
$db = db();
try {
    $db->beginTransaction();
    $requestStatement = $db->prepare(
        "SELECT
            request_id,
            client_id,
            request_title,
            description,
            status
         FROM projects_requests
         WHERE request_id = :request_id
         FOR UPDATE"
    );
    $requestStatement->execute([
        'request_id' => $requestId,
    ]);
    $request = $requestStatement->fetch();
    if ($request === false) {
        throw new RuntimeException('طلب المشروع غير موجود.');
    }
    if ((string) $request['status'] !== 'pending') {
        throw new RuntimeException('هذا الطلب تمت معالجته مسبقًا.');
    }
    $typeStatement = $db->prepare(
        "SELECT assessment_type_id
         FROM assessment_types
         WHERE assessment_type_id = :assessment_type_id
         LIMIT 1"
    );
    $typeStatement->execute([
        'assessment_type_id' => $assessmentTypeId,
    ]);
    if ($typeStatement->fetchColumn() === false) {
        throw new RuntimeException('نوع التقييم غير موجود.');
    }
    $projectStatement = $db->prepare(
        "INSERT INTO projects (
            client_id,
            assessment_type_id,
            project_name,
            description,
            status,
            created_by
         ) VALUES (
            :client_id,
            :assessment_type_id,
            :project_name,
            :description,
            'approved',
            :created_by
         )"
    );
    $projectStatement->execute([
        'client_id' => (int) $request['client_id'],
        'assessment_type_id' => $assessmentTypeId,
        'project_name' => $projectName,
        'description' => $request['description'],
        'created_by' => (int) $user['user_id'],
    ]);
    $projectId = (int) $db->lastInsertId();
    $updateStatement = $db->prepare(
        "UPDATE projects_requests
         SET
            status = 'approved',
            reviewed_by = :reviewed_by,
            reviewed_at = NOW(),
            manager_notes = :manager_notes
         WHERE request_id = :request_id"
    );
    $updateStatement->execute([
        'reviewed_by' => (int) $user['user_id'],
        'manager_notes' => $managerNotes !== '' ? $managerNotes : null,
        'request_id' => $requestId,
    ]);
    $db->commit();
    set_flash(
        'success',
        'تمت الموافقة على الطلب وإنشاء المشروع بنجاح.'
    );
    redirect(
        'manager/projects/view.php?project_id='
        . $projectId
    );
} catch (Throwable $exception) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    set_flash(
        'error',
        $exception->getMessage()
    );
    redirect(
        'manager/project-requests/view.php?request_id='
        . (int) $requestId
    );
}