<?php
/**
 * Sending mail — the first the application ever sends (e-mail
 * confirmation, 23.09.2026).
 *
 * THROUGH PHP'S mail(), because the host brings it: shared hosting hands
 * mail() to its own sendmail, and a second channel (SMTP credentials in
 * the config, a library) would be one more secret and one more thing to
 * break. The sender is config 'mail_from', or noreply@ the site's host.
 *
 * LOCALLY NOTHING LEAVES THE MACHINE: MAMP has no mail server, and a
 * development box that mails real people is a trap. The message goes to
 * data/mail.log instead — under data/, never served — where the link can
 * be copied out.
 */

declare(strict_types=1);

/** The absolute address of a path of ours — for links that leave the site. */
function absolute_url(string $path): string
{
    $forwarded = strtolower(trim(explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));
    $secure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $forwarded === 'https' || !IS_LOCAL;
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    // Only what a host name may contain — the header comes from the request.
    $host = preg_replace('/[^a-z0-9.\-:\[\]]/i', '', $host) ?? 'localhost';
    return ($secure ? 'https://' : 'http://') . $host . url($path);
}

/**
 * Send one plain-text mail. Returns whether it was handed on — which is
 * all mail() can know; whether it arrives is the mail server's business.
 *
 * @param array $options  all optional (the MykIS delivery, 25.09.2026):
 *        'from_name'   the name shown as sender — „Anna Musterfrau über
 *                      YASAIMAPP" — while the address stays ours
 *        'cc'          one address that gets a copy
 *        'reply_to'    where an answer goes instead of the sender
 *        'attachments' list of ['name' => file name, 'type' => mime type,
 *                      'content' => the bytes]
 */
function mail_send(string $to, string $subject, string $body, array $options = []): bool
{
    $mail = mail_compose($to, $subject, $body, $options);
    if ($mail === null) {
        return false;
    }

    if (IS_LOCAL) {
        $line = "---- " . date('Y-m-d H:i:s') . "\n" . implode("\n", array_filter($mail['headers'],
                static fn(string $h): bool => (bool) preg_match('/^(From|Cc|Reply-To):/', $h)))
            . "\nTo: {$mail['to']}\nSubject: $subject\n\n$body\n";
        /* THE ATTACHMENTS LAND BESIDE THE LOG, under data/ like it, so what
           would have gone out can be opened and checked. */
        foreach ($mail['attachments'] as $a) {
            $file = date('Ymd-His') . '-' . preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) $a['name']);
            @mkdir(PROJECT_PATH . '/data/mail-attachments', 0775, true);
            @file_put_contents(PROJECT_PATH . '/data/mail-attachments/' . $file, (string) $a['content']);
            $line .= "Attachment: data/mail-attachments/$file (" . strlen((string) $a['content']) . " bytes)\n";
        }
        return @file_put_contents(PROJECT_PATH . '/data/mail.log', $line, FILE_APPEND | LOCK_EX) !== false;
    }

    return @mail($mail['to'], $mail['subject'], $mail['body'], implode("\r\n", $mail['headers']), '-f' . $mail['from']);
}

/**
 * The same mail as a file one can open — To, Subject and Date on top, then
 * exactly what mail() would hand on. For looking at a mail before anybody
 * gets it: the MykIS sample (25.09.2026: „damit ich mir das
 * erstmal anschauen kann, wie der Text aussieht, wie der Aufbau ist").
 */
function mail_eml(string $to, string $subject, string $body, array $options = []): ?string
{
    $mail = mail_compose($to, $subject, $body, $options);
    if ($mail === null) {
        return null;
    }
    return implode("\r\n", array_merge(
        ['Date: ' . date(DATE_RFC2822), 'To: ' . $mail['to'], 'Subject: ' . $mail['subject']],
        $mail['headers'])) . "\r\n\r\n" . $mail['body'];
}

/**
 * The mail put together — headers and body as mail() takes them, or null
 * for an address that is none.
 */
function mail_compose(string $to, string $subject, string $body, array $options = []): ?array
{
    global $config;
    $to = trim($to);
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return null;
    }
    $host = preg_replace('/[^a-z0-9.\-]/i', '', explode(':', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'))[0]) ?: 'localhost';
    $from = (string) ($config['mail_from'] ?? '');
    if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
        $from = 'noreply@' . preg_replace('/^www\./', '', $host);
    }
    // Only real addresses reach a header — a value with a line break in it
    // would be a header of its own, and that is how mail forms get abused.
    $cc      = filter_var(trim((string) ($options['cc'] ?? '')), FILTER_VALIDATE_EMAIL) ?: '';
    $replyTo = filter_var(trim((string) ($options['reply_to'] ?? '')), FILTER_VALIDATE_EMAIL) ?: '';
    $attachments = array_values(array_filter((array) ($options['attachments'] ?? []),
        static fn($a): bool => is_array($a) && isset($a['name'], $a['content'])));
    // The same guard for the shown name: no line break, no quote, no angle
    // bracket — nothing that could end the header or fake an address.
    $fromName = trim((string) preg_replace('/[\r\n"<>]+/', ' ', (string) ($options['from_name'] ?? ''))) ?: 'YASAIMAPP';

    /* EVERY MAIL HERE IS WRITTEN BY A MACHINE, and says so (RFC 3834):
       an out-of-office reply to a noreply address, or to a coordinator's
       twice-yearly sheet, helps nobody, and a sender that marks its
       automatic mail is one a provider's spam watch trusts more. */
    $headers = ['From: ' . mb_encode_mimeheader($fromName, 'UTF-8') . ' <' . $from . '>', 'MIME-Version: 1.0',
                'Auto-Submitted: auto-generated', 'X-Auto-Response-Suppress: All'];
    if ($cc !== '') {
        $headers[] = 'Cc: ' . $cc;
    }
    if ($replyTo !== '') {
        $headers[] = 'Reply-To: ' . $replyTo;
    }
    $text = str_replace("\n", "\r\n", $body);

    if ($attachments === []) {
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        $headers[] = 'Content-Transfer-Encoding: 8bit';
        $mime = $text;
    } else {
        /* WITH ATTACHMENTS: multipart/mixed — the text first, then each
           file in base64, which survives every mail server between here
           and there. */
        $boundary = '=_' . bin2hex(random_bytes(12));
        $headers[] = 'Content-Type: multipart/mixed; boundary="' . $boundary . '"';
        $mime = "--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n$text\r\n";
        foreach ($attachments as $a) {
            $name = preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) $a['name']);
            $type = preg_replace('/[^a-z0-9.\/+;=_ -]/i', '', (string) ($a['type'] ?? 'application/octet-stream'));
            $mime .= "--$boundary\r\nContent-Type: $type; name=\"$name\"\r\n"
                . "Content-Transfer-Encoding: base64\r\n"
                . "Content-Disposition: attachment; filename=\"$name\"\r\n\r\n"
                . chunk_split(base64_encode((string) $a['content']), 76, "\r\n");
        }
        $mime .= "--$boundary--\r\n";
    }

    return [
        'to'          => $to,
        'from'        => $from,
        'subject'     => mb_encode_mimeheader($subject, 'UTF-8'),
        'headers'     => $headers,
        'body'        => $mime,
        'attachments' => $attachments,
    ];
}
