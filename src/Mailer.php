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
        $fromName = $config['mail']['from_name'];

        $subject = 'Your Socialize password reset code';
        $body = "Your Socialize reset code is: {$code}\r\nThis code expires in 15 minutes.";

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
            "Content-Type: text/plain; charset=UTF-8\r\n\r\n";

        $send($headers . $body . "\r\n.");
        $result = $read();
        $send('QUIT');
        fclose($socket);

        return str_starts_with($result, '250');
    }
}
