<?php

declare(strict_types=1);

namespace Stolpersteine\Service;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as MailerException;
use Stolpersteine\Config\Config;
use Stolpersteine\Config\Logger;

class MailService
{
    /**
     * Sendet die Passwort-Reset-E-Mail an den Benutzer.
     *
     * @throws MailerException Wenn der Versand fehlschlägt
     */
    public static function sendPasswordReset(
        string $toEmail,
        string $toName,
        string $resetUrl
    ): void {
        $cfg = Config::get('mail');

        if (!is_array($cfg) || empty($cfg['from'])) {
            throw new MailerException('Mail-Konfiguration fehlt oder unvollständig (Abschnitt "mail" in config.php prüfen).');
        }

        $mailer = new PHPMailer(true);

        try {
            $secure = strtolower($cfg['smtp_secure'] ?? '');

            $mailer->isSMTP();
            $mailer->Host     = $cfg['smtp_host'] ?? 'localhost';
            $mailer->Port     = (int) ($cfg['smtp_port'] ?? 587);
            $mailer->CharSet  = 'UTF-8';

            if ($secure === 'ssl') {
                $mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($secure === 'tls') {
                $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                // Kein Encryption (z.B. lokaler Mailcatcher)
                $mailer->SMTPSecure = '';
                $mailer->SMTPAutoTLS = false;
            }

            $smtpUser = $cfg['smtp_user'] ?? '';
            if ($smtpUser !== '') {
                $mailer->SMTPAuth = true;
                $mailer->Username = $smtpUser;
                $mailer->Password = $cfg['smtp_pass'] ?? '';
            } else {
                $mailer->SMTPAuth = false;
            }

            $mailer->setFrom($cfg['from'], $cfg['from_name'] ?? 'Stolpersteine Verwaltung');
            $mailer->addAddress($toEmail, $toName);

            $mailer->isHTML(true);
            $mailer->Subject = 'Passwort zurücksetzen – Stolpersteine Verwaltung';
            $mailer->Body    = self::buildHtmlBody($toName, $resetUrl);
            $mailer->AltBody = self::buildTextBody($toName, $resetUrl);

            $mailer->send();

            Logger::get()->info('Passwort-Reset-Mail versandt', ['to' => $toEmail]);
        } catch (MailerException $e) {
            Logger::get()->error('Mailversand fehlgeschlagen', [
                'to'    => $toEmail,
                'error' => $mailer->ErrorInfo,
            ]);
            throw $e;
        }
    }

    private static function buildHtmlBody(string $name, string $resetUrl): string
    {
        $nameEsc = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $urlEsc  = htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8');

        return <<<HTML
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Passwort zurücksetzen</title>
</head>
<body style="margin:0; padding:0; background-color:#f5f5f5; font-family:'Inter', 'Helvetica Neue', Arial, sans-serif;">

<table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f5f5f5; padding:40px 20px;">
  <tr>
    <td align="center">
      <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px; width:100%; background-color:#ffffff; border-radius:8px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.08);">

        <!-- Header -->
        <tr>
          <td style="background-color:#1a6496; padding:32px 40px;">
            <p style="margin:0; font-size:20px; font-weight:600; color:#ffffff; letter-spacing:0.5px;">
              Stolpersteine Verwaltung
            </p>
          </td>
        </tr>

        <!-- Content -->
        <tr>
          <td style="padding:40px;">
            <h1 style="margin:0 0 16px 0; font-size:22px; font-weight:600; color:#1a1a1a;">
              Passwort zurücksetzen
            </h1>
            <p style="margin:0 0 16px 0; font-size:15px; color:#444444; line-height:1.6;">
              Hallo {$nameEsc},
            </p>
            <p style="margin:0 0 24px 0; font-size:15px; color:#444444; line-height:1.6;">
              wir haben eine Anfrage zum Zurücksetzen deines Passworts erhalten.
              Klicke auf den Button, um ein neues Passwort zu vergeben.
              Der Link ist <strong>30 Minuten</strong> gültig.
            </p>

            <!-- Button -->
            <table cellpadding="0" cellspacing="0" style="margin-bottom:32px; margin-left:auto; margin-right:auto;">
              <tr>
                <td style="border-radius:6px; background-color:#1a6496;">
                  <a href="{$urlEsc}"
                     style="display:inline-block; padding:14px 28px; font-size:15px; font-weight:600;
                            color:#ffffff; text-decoration:none; border-radius:6px; background-color:#1a6496;">
                    Passwort jetzt zurücksetzen
                  </a>
                </td>
              </tr>
            </table>

            <p style="margin:0 0 8px 0; font-size:13px; color:#888888; line-height:1.5;">
              Falls der Button nicht funktioniert, kopiere diesen Link in deinen Browser:
            </p>
            <p style="margin:0 0 32px 0; font-size:13px; line-height:1.5;">
              <a href="{$urlEsc}" style="color:#1a6496; word-break:break-all;">{$urlEsc}</a>
            </p>

            <p style="margin:0; font-size:13px; color:#888888; line-height:1.5;">
              Falls du kein neues Passwort angefordert hast, kannst du diese E-Mail ignorieren.
              Dein bisheriges Passwort bleibt unverändert.
            </p>
          </td>
        </tr>

        <!-- Footer -->
        <tr>
          <td style="background-color:#f9f9f9; border-top:1px solid #e8e8e8; padding:20px 40px;">
            <p style="margin:0; font-size:12px; color:#aaaaaa; line-height:1.5;">
              Diese E-Mail wurde automatisch vom Stolpersteine-Verwaltungssystem versandt.
              Bitte antworte nicht auf diese E-Mail.
            </p>
          </td>
        </tr>

      </table>
    </td>
  </tr>
</table>

</body>
</html>
HTML;
    }

    private static function buildTextBody(string $name, string $resetUrl): string
    {
        return <<<TEXT
Passwort zurücksetzen – Stolpersteine Verwaltung
================================================

Hallo {$name},

wir haben eine Anfrage zum Zurücksetzen deines Passworts erhalten.

Klicke auf folgenden Link, um ein neues Passwort zu vergeben.
Der Link ist 30 Minuten gültig:

{$resetUrl}

Falls du kein neues Passwort angefordert hast, kannst du diese E-Mail ignorieren.
Dein bisheriges Passwort bleibt unverändert.

---
Diese E-Mail wurde automatisch vom Stolpersteine-Verwaltungssystem versandt.
TEXT;
    }
}
