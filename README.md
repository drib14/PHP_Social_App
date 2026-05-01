# Plain PHP Auth Form (XAMPP)

Features:
- Register / Login
- Forgot password (email code)
- Reset password
- Modern dark UI

## Setup
1. Put project in `htdocs/PHP_Social_App`.
2. Create database and tables:
   ```sql
   SOURCE /path/to/schema.sql;
   ```
3. Update DB credentials in `config.php`.
4. Start Apache + MySQL in XAMPP.
5. Open `http://localhost/PHP_Social_App`.

## Email notes
This project uses PHP `mail()` in `src/Mailer.php`.
For Gmail SMTP in XAMPP, configure `sendmail.ini`/`php.ini` or replace Mailer with PHPMailer SMTP.
