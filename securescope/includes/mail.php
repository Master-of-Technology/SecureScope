<?php

declare(strict_types=1);

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;


/*
|--------------------------------------------------------------------------
| Composer Autoloader
|--------------------------------------------------------------------------
*/

require_once dirname(__DIR__) . '/vendor/autoload.php';


/*
|--------------------------------------------------------------------------
| Create configured mailer
|--------------------------------------------------------------------------
*/

function create_mailer(): PHPMailer
{
    $mail = new PHPMailer(true);

    /*
     * SMTP
     */
    $mail->isSMTP();

    $mail->Host = MAIL_HOST;

    $mail->SMTPAuth = true;

    $mail->Username = MAIL_USERNAME;

    $mail->Password = MAIL_PASSWORD;


    /*
     * TLS encryption
     *
     * Port 587 = STARTTLS
     */
    $mail->SMTPSecure =
        PHPMailer::ENCRYPTION_STARTTLS;

    $mail->Port = MAIL_PORT;


    /*
     * Sender
     */
    $mail->setFrom(
        MAIL_FROM_ADDRESS,
        MAIL_FROM_NAME
    );


    /*
     * Character encoding
     */
    $mail->CharSet = 'UTF-8';


    /*
     * Prevent PHPMailer errors
     * from being displayed to users.
     */
    $mail->SMTPDebug = 0;


    return $mail;
}


/*
|--------------------------------------------------------------------------
| Send Email
|--------------------------------------------------------------------------
*/

function send_email(
    string $to,
    string $recipientName,
    string $subject,
    string $htmlBody,
    ?string $plainBody = null
): bool {

    try {

        $mail = create_mailer();


        /*
         * Recipient
         */
        $mail->addAddress(
            $to,
            $recipientName
        );


        /*
         * HTML message
         */
        $mail->isHTML(true);

        $mail->Subject = $subject;

        $mail->Body = $htmlBody;


        /*
         * Plain-text alternative
         */
        $mail->AltBody =
            $plainBody
            ?? strip_tags($htmlBody);


        /*
         * Send
         */
        return $mail->send();

    } catch (Exception $exception) {

    error_log(
        'SecureScope mail error: '
        . $exception->getMessage()
    );

    return false;
}
}