<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

require_role('Security Manager');


/*
|--------------------------------------------------------------------------
| Get Application ID
|--------------------------------------------------------------------------
*/

$applicationId = filter_input(
    INPUT_GET,
    'application_id',
    FILTER_VALIDATE_INT
);


if (
    $applicationId === false
    || $applicationId === null
    || $applicationId <= 0
) {

    set_flash(
        'error',
        'معرف الطلب غير صالح.'
    );

    redirect(
        'manager/analyst-applications/index.php'
    );
}


/*
|--------------------------------------------------------------------------
| Get Application
|--------------------------------------------------------------------------
*/

$statement = db()->prepare(
    "SELECT
        application_id,
        first_name,
        last_name,
        email,
        status
     FROM analyst_applications
     WHERE application_id = :application_id
     LIMIT 1"
);


$statement->execute([
    'application_id' => $applicationId,
]);


$application = $statement->fetch();


if ($application === false) {

    set_flash(
        'error',
        'لم يتم العثور على الطلب.'
    );

    redirect(
        'manager/analyst-applications/index.php'
    );
}


/*
|--------------------------------------------------------------------------
| Only Pending Applications Can Be Rejected
|--------------------------------------------------------------------------
*/

if ($application['status'] !== 'pending') {

    set_flash(
        'error',
        'هذا الطلب تمت مراجعته مسبقًا.'
    );

    redirect(
        'manager/analyst-applications/view.php?application_id='
        . $applicationId
    );
}


$errors = [];


/*
|--------------------------------------------------------------------------
| POST Handling
|--------------------------------------------------------------------------
*/

if (is_post_request()) {

    require_valid_csrf();


    /*
     * Get rejection reason.
     */

    $reason = trim(
        (string) (
            $_POST['rejection_reason'] ?? ''
        )
    );


    /*
     * Validate rejection reason.
     */

    if ($reason === '') {

        $errors[] =
            'يجب إدخال سبب رفض الطلب.';
    }


    if (strlen($reason) > 500) {

        $errors[] =
            'سبب الرفض يجب ألا يتجاوز 500 حرف.';
    }


    /*
     * Get current manager.
     */

    $currentUser = current_user();

    $currentUserId = $currentUser !== null
        ? (int) $currentUser['user_id']
        : 0;


    if ($currentUserId <= 0) {

        $errors[] =
            'تعذر تحديد المستخدم الحالي.';
    }


    /*
     |--------------------------------------------------------------------------
     | Reject Application
     |--------------------------------------------------------------------------
     */

    if ($errors === []) {

        db()->beginTransaction();


        try {

            /*
             * Update application.
             */

            $updateStatement = db()->prepare(
                "UPDATE analyst_applications
                 SET
                    status = 'rejected',
                    reviewed_by = :reviewed_by,
                    reviewed_at = NOW(),
                    rejection_reason = :rejection_reason
                 WHERE application_id = :application_id
                   AND status = 'pending'"
            );


            $updateStatement->execute([
                'reviewed_by' =>
                    $currentUserId,

                'rejection_reason' =>
                    $reason,

                'application_id' =>
                    $applicationId,
            ]);


            /*
             * Make sure exactly one application
             * was rejected.
             */

            if ($updateStatement->rowCount() !== 1) {

                throw new RuntimeException(
                    'The application could not be rejected.'
                );
            }


            /*
             |--------------------------------------------------------------------------
             | Audit Log
             |--------------------------------------------------------------------------
             */

            record_audit(
                'REJECT_ANALYST_APPLICATION',
                'analyst_applications',
                $applicationId,
                [
                    'status' => 'pending',
                ],
                [
                    'status' =>
                        'rejected',

                    'reviewed_by' =>
                        $currentUserId,

                    'rejection_reason' =>
                        $reason,
                ]
            );


            /*
             |--------------------------------------------------------------------------
             | Commit Database Changes
             |--------------------------------------------------------------------------
             */

            db()->commit();


            /*
             |--------------------------------------------------------------------------
             | Send Rejection Email
             |--------------------------------------------------------------------------
             *
             * Important:
             * The database transaction has already been committed.
             *
             * Therefore, if the email fails, the application remains
             * rejected. We do not roll back the rejection.
             */

            $recipientName = trim(
                (string) $application['first_name']
                . ' '
                . (string) $application['last_name']
            );


            $subject =
                'نتيجة طلب التقدم للعمل كمحلل أمني - SecureScope';


            /*
             * Plain text version.
             */

            $plainBody =
                "مرحبًا "
                . $recipientName
                . "،\n\n"
                . "نشكر لك اهتمامك بالانضمام إلى فريق SecureScope.\n\n"
                . "بعد مراجعة طلب التقدم للعمل كمحلل أمني، "
                . "نأسف لإبلاغك بأنه لم يتم قبول طلبك في الوقت الحالي.\n\n"
                . "سبب الرفض:\n"
                . $reason
                . "\n\n"
                . "نشكر لك وقتك واهتمامك بـ SecureScope.\n\n"
                . "مع تحيات فريق SecureScope.";


            /*
             * HTML version.
             */

            $htmlBody =
                '<div style="'
                . 'font-family: Arial, sans-serif;'
                . 'direction: rtl;'
                . 'line-height: 1.8;'
                . '">'

                . '<h2>'
                . 'نتيجة طلب التقدم للعمل'
                . '</h2>'

                . '<p>'
                . 'مرحبًا '
                . e($recipientName)
                . '،'
                . '</p>'

                . '<p>'
                . 'نشكر لك اهتمامك بالانضمام إلى فريق '
                . '<strong>SecureScope</strong>.'
                . '</p>'

                . '<p>'
                . 'بعد مراجعة طلب التقدم للعمل كمحلل أمني، '
                . 'نأسف لإبلاغك بأنه لم يتم قبول طلبك في الوقت الحالي.'
                . '</p>'

                . '<div style="'
                . 'background:#f5f5f5;'
                . 'padding:15px;'
                . 'border-radius:8px;'
                . 'margin:20px 0;'
                . '">'

                . '<strong>'
                . 'سبب الرفض:'
                . '</strong>'

                . '<p>'
                . nl2br(e($reason))
                . '</p>'

                . '</div>'

                . '<p>'
                . 'نشكر لك وقتك واهتمامك بـ SecureScope.'
                . '</p>'

                . '<p>'
                . 'مع تحيات فريق SecureScope.'
                . '</p>'

                . '</div>';


            /*
             * Send Email
             */

            $emailSent = send_email(
                (string) $application['email'],
                $recipientName,
                $subject,
                $htmlBody,
                $plainBody
            );


            /*
             |--------------------------------------------------------------------------
             | Result Message
             |--------------------------------------------------------------------------
             */

            if ($emailSent) {

                set_flash(
                    'success',
                    'تم رفض طلب المحلل الأمني وإرسال رسالة الرفض إلى المتقدم بنجاح.'
                );

            } else {

                /*
                 * Rejection succeeded, but email failed.
                 */

                set_flash(
                    'warning',
                    'تم رفض طلب المحلل الأمني بنجاح، ولكن تعذر إرسال رسالة البريد الإلكتروني إلى المتقدم.'
                );
            }


            redirect(
                'manager/analyst-applications/view.php?application_id='
                . $applicationId
            );


        } catch (Throwable $exception) {

            /*
             * Roll back only if the database transaction
             * is still active.
             */

            if (db()->inTransaction()) {

                db()->rollBack();
            }


            /*
             * Log the real error on the server.
             */

            error_log(
                'SecureScope reject analyst application error: '
                . $exception->getMessage()
            );


            $errors[] =
                'حدث خطأ أثناء رفض الطلب.';
        }
    }
}


/*
|--------------------------------------------------------------------------
| Render Page
|--------------------------------------------------------------------------
*/

render_header('رفض طلب محلل أمني');

?>

<section class="page-heading">

    <div>

        <p class="eyebrow">
            طلبات المحللين الأمنيين
        </p>

        <h1>
            رفض الطلب
        </h1>

        <p class="muted">
            أدخل سببًا واضحًا ومختصرًا لرفض الطلب.
        </p>

    </div>


    <div class="page-actions">

        <a
            href="<?= e(
                url(
                    'manager/analyst-applications/view.php?application_id='
                    . $applicationId
                )
            ) ?>"
            class="button button-primary"
        >
            العودة إلى الطلب
        </a>

    </div>

</section>


<section class="details-card">

    <div class="detail-grid">

        <div>

            <span class="detail-label">
                المتقدم
            </span>

            <strong>
                <?= e(
                    trim(
                        (string) $application['first_name']
                        . ' '
                        . (string) $application['last_name']
                    )
                ) ?>
            </strong>

        </div>


        <div>

            <span class="detail-label">
                البريد الإلكتروني
            </span>

            <strong>
                <?= e(
                    (string) $application['email']
                ) ?>
            </strong>

        </div>

    </div>

</section>


<section class="details-card">

    <?php if ($errors !== []): ?>

            <div
                class="alert alert-error"
                role="alert"
            >

                <ul>

                    <?php foreach ($errors as $error): ?>

                            <li>
                                <?= e($error) ?>
                            </li>

                    <?php endforeach; ?>

                </ul>

            </div>

    <?php endif; ?>


    <form method="post">

        <?= csrf_input() ?>


        <div class="form-field">

            <label for="rejection_reason">
                سبب رفض الطلب
            </label>

            <textarea
                id="rejection_reason"
                name="rejection_reason"
                rows="7"
                maxlength="500"
                required
                placeholder="اكتب سبب رفض الطلب..."
            ><?= e(
                (string) (
                    $_POST['rejection_reason']
                    ?? ''
                )
            ) ?></textarea>

            <small>
                الحد الأقصى 500 حرف.
            </small>

        </div>


        <div class="form-actions">

            <a
                href="<?= e(
                    url(
                        'manager/analyst-applications/view.php?application_id='
                        . $applicationId
                    )
                ) ?>"
                class="button button-secondary"
            >
                إلغاء
            </a>


            <button
                type="submit"
                class="button button-danger"
            >
                تأكيد رفض الطلب
            </button>

        </div>

    </form>

</section>


<?php render_footer(); ?>