<?php

class Mailer
{
    public static function sendResetCode(string $toEmail, string $code): bool
    {
        $config = require __DIR__ . '/../config.php';
        $host = $config['mail']['host'];
        $port = (int)$config['mail']['port'];
        $user = $config['mail']['user'];
        $pass = $config['mail']['pass'];
        $from = $config['mail']['from'];
        $fromName = 'Socialize Security';

        $subject = 'Socialize Password Reset Verification Code';
        $html = self::buildTemplate($code);

        $socket = fsockopen('ssl://' . $host, $port, $errno, $errstr, 20);
        if (!$socket) {
            error_log("SMTP connection failed: {$errno} {$errstr}");
            return false;
        }

        $read = static function () use ($socket): string {
            $response = '';
            while ($line = fgets($socket, 515)) {
                $response .= $line;
                if (isset($line[3]) && $line[3] === ' ') {
                    break;
                }
            }
            return $response;
        };

        $send = static function (string $command) use ($socket): void {
            fwrite($socket, $command . "\r\n");
        };

        $boundary = 'bnd_' . bin2hex(random_bytes(8));
        $read();
        $send('EHLO localhost'); $read();
        $send('AUTH LOGIN'); $read();
        $send(base64_encode($user)); $read();
        $send(base64_encode($pass)); $read();
        $send('MAIL FROM:<' . $from . '>'); $read();
        $send('RCPT TO:<' . $toEmail . '>'); $read();
        $send('DATA'); $read();

        $headers = "From: {$fromName} <{$from}>\r\n" .
            "To: <{$toEmail}>\r\n" .
            "Subject: {$subject}\r\n" .
            "MIME-Version: 1.0\r\n" .
            "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n\r\n";

        $message = "--{$boundary}\r\n" .
            "Content-Type: text/plain; charset=UTF-8\r\n\r\n" .
            "Your Socialize verification code is {$code}. It expires in 15 minutes.\r\n\r\n" .
            "--{$boundary}\r\n" .
            "Content-Type: text/html; charset=UTF-8\r\n\r\n" .
            $html . "\r\n\r\n" .
            "--{$boundary}--";

        $send($headers . $message . "\r\n.");
        $result = $read();
        $send('QUIT');
        fclose($socket);

        return str_starts_with($result, '250');
    }

    private static function buildTemplate(string $code): string
    {
        return '<!doctype html><html><body style="margin:0;padding:0;background:#f3faf7;font-family:Arial,sans-serif;color:#0b3b30">'
            . '<table width="100%" cellpadding="0" cellspacing="0" style="padding:24px 0"><tr><td align="center">'
            . '<table width="620" cellpadding="0" cellspacing="0" style="max-width:620px;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #d8f2e8">'
            . '<tr><td style="background:linear-gradient(90deg,#059669,#10b981);padding:24px 28px;color:#fff"><h1 style="margin:0;font-size:24px">Socialize Security</h1><p style="margin:6px 0 0;opacity:.95">Password reset verification</p></td></tr>'
            . '<tr><td style="padding:28px"><p style="font-size:15px;margin:0 0 12px">Hi there,</p><p style="font-size:15px;line-height:1.6;margin:0 0 20px">Use the verification code below to reset your Socialize password.</p>'
            . '<div style="text-align:center;margin:18px 0"><span style="display:inline-block;padding:14px 22px;border-radius:12px;border:1px dashed #10b981;font-size:32px;letter-spacing:8px;font-weight:700;color:#065f46;background:#ecfdf5">' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '</span></div>'
            . '<p style="font-size:14px;line-height:1.6;margin:0;color:#375c53">This code expires in <strong>15 minutes</strong>. If you did not request this reset, you can safely ignore this email.</p>'
            . '<p style="font-size:13px;margin:22px 0 0;color:#4d7c70">— The Socialize Team</p></td></tr></table></td></tr></table></body></html>';
    }
}
