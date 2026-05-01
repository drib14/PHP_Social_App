<?php
class Mailer
{
    private static function smtpSend(string $to, string $subject, string $html, string $text): bool
    {
        $config = require __DIR__ . '/../config.php';
        $socket = fsockopen('ssl://' . $config['mail']['host'], (int)$config['mail']['port'], $errno, $errstr, 20);
        if (!$socket) { error_log("SMTP failed: $errno $errstr"); return false; }
        $read=function()use($socket){$r='';while($l=fgets($socket,515)){$r.=$l;if(isset($l[3])&&$l[3]===' ')break;}return $r;};
        $send=function($c)use($socket){fwrite($socket,$c."\r\n");};
        $boundary='bnd_'.bin2hex(random_bytes(8)); $from=$config['mail']['from'];
        $read();$send('EHLO localhost');$read();$send('AUTH LOGIN');$read();$send(base64_encode($config['mail']['user']));$read();$send(base64_encode($config['mail']['pass']));$read();$send('MAIL FROM:<'.$from.'>');$read();$send('RCPT TO:<'.$to.'>');$read();$send('DATA');$read();
        $headers="From: Socialize <{$from}>\r\nTo:<{$to}>\r\nSubject: {$subject}\r\nMIME-Version: 1.0\r\nContent-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n\r\n";
        $msg="--{$boundary}\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n{$text}\r\n\r\n--{$boundary}\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n{$html}\r\n\r\n--{$boundary}--";
        $send($headers.$msg."\r\n.");$res=$read();$send('QUIT');fclose($socket); return str_starts_with($res,'250');
    }
    public static function sendResetCode(string $to,string $code): bool { $subject='Socialize Password Reset Code'; $text="Your reset code is {$code}. Expires in 15 minutes."; $html='<div style="font-family:Arial;background:#f0fdf4;padding:24px"><div style="max-width:620px;margin:auto;background:#fff;border:1px solid #d1fae5;border-radius:14px"><div style="padding:18px;background:linear-gradient(90deg,#059669,#10b981);color:#fff"><h2>Socialize Security</h2></div><div style="padding:22px"><p>Use this code to reset your password:</p><div style="font-size:34px;letter-spacing:10px;font-weight:800;color:#065f46">'.htmlspecialchars($code,ENT_QUOTES,'UTF-8').'</div></div></div></div>'; return self::smtpSend($to,$subject,$html,$text); }
    public static function sendWelcome(string $to,string $name): bool { $subject='Welcome to Socialize 🎉'; $text="Welcome {$name}! Your Socialize account is ready. Start posting and connecting today."; $html='<div style="font-family:Arial;background:#ecfdf5;padding:24px"><table width="100%"><tr><td align="center"><table width="620" style="background:#fff;border-radius:16px;border:1px solid #a7f3d0"><tr><td style="padding:24px;background:linear-gradient(90deg,#047857,#10b981);color:#fff"><h1 style="margin:0">Welcome to Socialize, '.htmlspecialchars($name,ENT_QUOTES,'UTF-8').'!</h1><p style="margin-top:8px">Your community is waiting.</p></td></tr><tr><td style="padding:24px;color:#064e3b"><p style="line-height:1.6">Thanks for joining Socialize. Share updates, react to posts, and connect in real-time with people who matter.</p><p style="line-height:1.6">✨ Create your first post, explore the feed, and start conversations.</p><a href="#" style="display:inline-block;padding:12px 18px;background:#10b981;color:#03291f;border-radius:10px;text-decoration:none;font-weight:700">Open Socialize</a><p style="margin-top:18px;color:#065f46;font-size:13px">Need help? Reply to this email and our team will assist you.</p></td></tr></table></td></tr></table></div>'; return self::smtpSend($to,$subject,$html,$text); }
}
