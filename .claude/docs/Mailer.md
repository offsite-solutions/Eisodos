# Mailer Class

Email sending functionality using PHPMailer.

**Namespace:** `Eisodos`
**Extends:** `Eisodos\Abstracts\Singleton`
**Source:** `src/Eisodos/Mailer.php`

## Overview

The `Mailer` class provides email sending capabilities using PHPMailer as the underlying library. It supports SMTP and sendmail, batch mailing with throttling, attachments, and address parsing.

## Methods

### init(array $options_ = []): void

Initializes the mailer.

### sendMail(string $to_, string $subject_, string $body_, string $from_, array $filesToAttach_ = [], array $fileStringsToAttach_ = [], string $cc_ = '', string $bcc_ = '', string $replyTo_ = ''): bool

Sends a UTF-8 encoded HTML email.

**Parameters:**
- `$to_` - Recipient address(es), comma or semicolon separated
- `$subject_` - Email subject
- `$body_` - HTML body content
- `$from_` - Sender address
- `$filesToAttach_` - Array of file paths to attach
- `$fileStringsToAttach_` - Array of string attachments: `[['content' => '...', 'filename' => '...']]`
- `$cc_` - CC addresses
- `$bcc_` - BCC addresses
- `$replyTo_` - Reply-to address

**Returns:** `true` on success, `false` on failure

**Example:**
```php
$result = Eisodos::$mailer->sendMail(
    'user@example.com',
    'Welcome!',
    '<html><body><h1>Welcome</h1></body></html>',
    'noreply@myapp.com'
);
```

### utf8_html_mail_attachment(...): bool

Alias for `sendMail()` - kept for backward compatibility.

### sendBatchMail(array $to_, string $subject_, string $bodyTemplate_, string $from_, ...): string

Sends emails to multiple recipients with template-based personalization.

**Parameters:**
- `$to_` - Recipients with parameters: `['email@example.com' => "name=John\nage=30"]`
- `$subject_` - Email subject
- `$bodyTemplate_` - Template ID for email body
- `$from_` - Sender address
- `$filesToAttach_` - File attachments
- `$fileStringsToAttach_` - String attachments
- `$cc_` - CC addresses
- `$bcc_` - BCC addresses
- `$replyTo_` - Reply-to address
- `$batch_loopCount_` - Emails per batch (default: 50)
- `$batch_waitBetweenLoops_` - Seconds to wait between batches (default: 60)
- `$batch_echo_` - Echo progress to output
- `$batch_skip_` - Skip first N recipients
- `$testOnly_` - Prepare but don't send

**Returns:** Log of sending results

**Example:**
```php
$recipients = [
    'john@example.com' => "name=John\nproduct=Widget",
    'jane@example.com' => "name=Jane\nproduct=Gadget"
];

$log = Eisodos::$mailer->sendBatchMail(
    $recipients,
    'Special Offer for $name',
    'email/offer_template',
    'sales@myapp.com',
    [],
    [],
    '',
    '',
    '',
    50,    // 50 emails per batch
    60,    // 60 second pause
    true   // echo progress
);
```

### parseEmailAddress(string $address_): array

Parses email address in "Name <email@domain>" format.

**Parameters:**
- `$address_` - Email address string

**Returns:** Array `[email, name]`

**Example:**
```php
[$email, $name] = Eisodos::$mailer->parseEmailAddress('John Doe <john@example.com>');
// $email = 'john@example.com'
// $name = 'John Doe'
```

## Address Formats

The mailer accepts various address formats:

```php
// Simple email
'user@example.com'

// With name
'John Doe <john@example.com>'

// Quoted name
'"John Doe" <john@example.com>'

// Multiple recipients
'user1@example.com, user2@example.com'
'user1@example.com; user2@example.com'
```

## Configuration Parameters

### SMTP Configuration

| Parameter | Description | Default |
|-----------|-------------|---------|
| `SMTP.Host` | SMTP server hostname | (uses sendmail if empty) |
| `SMTP.Port` | SMTP port | 587 |
| `SMTP.Username` | SMTP authentication username | |
| `SMTP.Password` | SMTP authentication password | |
| `SMTP.Secure` | Security protocol (`tls` or `ssl`) | |

### Logging

| Parameter | Description |
|-----------|-------------|
| `MAILLOG` | Path to mail log file |

## Usage Examples

### Simple Email

```php
Eisodos::$mailer->sendMail(
    'customer@example.com',
    'Order Confirmation',
    Eisodos::$templateEngine->getTemplate('email/order_confirmation', [], false),
    'orders@myshop.com'
);
```

### Email with Attachments

```php
Eisodos::$mailer->sendMail(
    'client@example.com',
    'Your Invoice',
    $htmlBody,
    'billing@company.com',
    ['/path/to/invoice.pdf'],  // File attachments
    [['content' => $csvData, 'filename' => 'data.csv']]  // String attachments
);
```

### Email with CC and BCC

```php
Eisodos::$mailer->sendMail(
    'primary@example.com',
    'Team Update',
    $body,
    'manager@company.com',
    [],
    [],
    'team@company.com',        // CC
    'archive@company.com',     // BCC
    'manager@company.com'      // Reply-To
);
```

### Batch Email Campaign

```php
// Build recipient list from database
$recipients = [];
foreach ($subscribers as $sub) {
    $recipients[$sub['email']] = "name={$sub['name']}\ncode={$sub['code']}";
}

// Send with throttling
$log = Eisodos::$mailer->sendBatchMail(
    $recipients,
    'Newsletter',
    'email/newsletter',
    'news@mysite.com',
    [],
    [],
    '',
    '',
    '',
    100,    // 100 emails per batch
    120,    // 2 minute pause
    true    // Show progress
);

echo $log;
```

### SMTP Configuration

```ini
# In configuration file
[Config]
SMTP.Host=smtp.example.com
SMTP.Port=587
SMTP.Username=mailer@example.com
SMTP.Password=secret
SMTP.Secure=tls
MAILLOG=/var/log/app/mail.log
```

## Batch Mail Log Format

```
OK      1       john@example.com                10:30:45
OK      2       jane@example.com                10:30:46
WAIT                    60 sec  10:30:46
OK      3       bob@example.com                 10:31:47
Error   4       invalid@bad             Error message       10:31:48
SKIP    5       skipped@example.com             10:31:48
```

## Error Handling

Mail errors are logged to `MAILLOG` or printed to console in CLI mode:

```php
if (!Eisodos::$mailer->sendMail($to, $subject, $body, $from)) {
    // Email sending failed - check MAILLOG for details
}
```

## See Also

- [Eisodos](Eisodos.md) - Main framework class
- [TemplateEngine](TemplateEngine.md) - Template processing for email bodies
- [Logger](Logger.md) - Error logging
