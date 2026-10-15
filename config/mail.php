<?php

define('GMAIL_ADDRESS', 'diegoanovergwapo12345@gmail.com');
define('GMAIL_APP_PASSWORD', 'pxog whhb ukbd gqse');
define('MAIL_FROM_NAME', 'Casa Vergara');

define('ADMIN_NOTIFY_EMAIL', 'casavergararesort@gmail.com');
define('ADMIN_NOTIFY_NAME', 'Casa Vergara Admin');

define('MAIL_DEBUG', false);
define('MAIL_LOGO_CID', 'casa_vergara_logo');

require_once __DIR__ . '/../includes/PHPMailer/Exception.php';
require_once __DIR__ . '/../includes/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../includes/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

function send_app_email(
    string $toEmail,
    string $toName,
    string $subject,
    string $htmlBody,
    ?string &$errorOut = null,
    ?string $replyToEmail = null,
    ?string $replyToName = null,
    array $attachments = []
): bool
{
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = GMAIL_ADDRESS;
        $mail->Password   = str_replace(' ', '', GMAIL_APP_PASSWORD);
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->Timeout    = 12;

        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ],
        ];

        if (MAIL_DEBUG) {
            $mail->SMTPDebug = 2;
            $mail->Debugoutput = function ($str) {
                error_log('SMTP DEBUG: ' . $str);
            };
        }

        $mail->setFrom(GMAIL_ADDRESS, MAIL_FROM_NAME);
        $mail->addAddress($toEmail, $toName);

        if ($replyToEmail !== null && $replyToEmail !== '') {
            $mail->addReplyTo($replyToEmail, $replyToName ?: $replyToEmail);
        }

        foreach ($attachments as $attachment) {
            $path = $attachment['path'] ?? '';
            if ($path !== '' && is_file($path)) {
                $mail->addAttachment($path, $attachment['name'] ?? basename($path));
            }
        }

        // Embed the resort logo inline (cid) so it displays in Gmail even when
        // SITE_URL is localhost / not publicly reachable.
        if (strpos($htmlBody, 'cid:' . MAIL_LOGO_CID) !== false) {
            $logoFile = null;
            foreach (['logo-email.png', 'logo.png'] as $candidate) {
                $path = __DIR__ . '/../images/' . $candidate;
                if (is_file($path)) { $logoFile = $path; break; }
            }
            if ($logoFile !== null) {
                $mail->addEmbeddedImage($logoFile, MAIL_LOGO_CID, 'logo.png', 'base64', 'image/png');
            } else {
                error_log('Mail logo not found in ' . realpath(__DIR__ . '/..') . '/images/');
            }
        }

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody  = strip_tags($htmlBody);

        $mail->send();
        return true;
    } catch (PHPMailerException $e) {
        $errorOut = $mail->ErrorInfo ?: $e->getMessage();
        error_log('Mail send failed: ' . $errorOut);
        return false;
    } catch (\Throwable $e) {
        $errorOut = $e->getMessage();
        error_log('Mail send failed (unexpected): ' . $errorOut);
        return false;
    }
}

function build_email_html(string $title, string $bodyHtml): string
{
    $logoUrl = 'cid:' . MAIL_LOGO_CID;

    return '
    <div style="font-family: Arial, sans-serif; max-width: 560px; margin: 0 auto; color:#3d4963;">
        <div style="background:#0c3b5d; padding:22px 24px; border-radius:8px 8px 0 0; text-align:center;">
            <img src="' . htmlspecialchars($logoUrl) . '" alt="Casa Vergara Resort" width="52" height="52"
                 style="display:block; margin:0 auto 10px; width:52px; height:52px; border-radius:10px; background:#ffffff; padding:4px; object-fit:cover;" />
            <h2 style="color:#ffffff; margin:0; font-family: Georgia, serif; letter-spacing:0.02em;">Casa Vergara Resort</h2>
        </div>
        <div style="border:1px solid #e5e5e5; border-top:none; padding:24px; border-radius:0 0 8px 8px;">
            <h3 style="color:#0c3b5d; margin-top:0;">' . htmlspecialchars($title) . '</h3>
            ' . $bodyHtml . '
            <p style="margin-top:24px; font-size:12px; color:#888;">This is an automated message from Casa Vergara Resort. Please do not reply directly to this email.</p>
        </div>
    </div>';
}