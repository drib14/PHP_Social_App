<?php

class Mailer
{
    public static function sendResetCode(string $toEmail, string $code): bool
    {
        $subject = 'Your Password Reset Code';
        $message = "Your password reset code is: {$code}\nThis code expires in 15 minutes.";
        $headers = "From: PHP Auth App <jhondribramirez7@gmail.com>\r\n";

        return mail($toEmail, $subject, $message, $headers);
    }
}
