# Architecture: email

## Purpose

A FuelPHP 1.x Email Package providing multi-driver email sending (SMTP, Sendmail, mail(), Mailgun, Mandrill, etc.) with support for HTML/plain-text bodies, attachments, CC/BCC, and inline images.

## Directory Structure

```
classes/
  email.php                        — Facade class: static factory for email instances
  email/
    driver.php                     — Abstract driver with shared composition logic
    driver/
      mail.php                     — Driver: PHP mail() function
      smtp.php                     — Driver: SMTP via sockets
      sendmail.php                 — Driver: sendmail binary
      mailgun.php                  — Driver: Mailgun HTTP API
      mandrill.php                 — Driver: Mandrill/Mailchimp Transactional API

config/
  email.php                        — Default configuration (SMTP host, port, auth, from, etc.)

vendor/
  composer/installers/             — Composer installer for fuel-package type
```

Note: PHP source files were installed via FuelPHP's package system and may reside in the FuelPHP application directory rather than this repository root.

## Key Design Decisions

- **Driver pattern** — all sending backends extend the same abstract driver, making it easy to swap transport without changing application code.
- **FuelPHP package type** — declared as `fuel-package` in composer.json; `composer/installers` places it in `fuel/packages/email/` within the FuelPHP application.

## Extension Points

- Create a custom driver class extending `Email_Driver` and register it via the `driver` config key.
- Override `config/email.php` in your FuelPHP app to supply environment-specific SMTP credentials.
