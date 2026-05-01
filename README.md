# Socialize Auth (Plain PHP + XAMPP)

Features:
- Register / Login / Logout
- Forgot password (email code)
- Verify code page with 6 separate digit inputs
- Separate reset-password page after successful verification
- Split-screen modern UI with banner image

## Setup
1. Put project in `htdocs/PHP_Social_App`.
2. Create database and tables with `schema.sql`.
3. Update DB credentials in `config.php`.
4. Start Apache + MySQL in XAMPP.
5. Open `http://localhost/PHP_Social_App`.

## Email delivery
- Uses direct SMTP over SSL to Gmail (`smtp.gmail.com:465`) from `src/Mailer.php`.
- Ensure OpenSSL is enabled in PHP and Gmail App Password is valid.
