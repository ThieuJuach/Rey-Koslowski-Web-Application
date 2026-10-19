<?php
/**
 * Minimal outbound mailer.
 *
 * Sends via authenticated SMTP when SMTP credentials are configured, and falls back
 * to PHP's mail() otherwise. Written against raw sockets deliberately: this host has
 * no Composer/PHPMailer, and pulling in a dependency for one transactional email is
 * not worth the deployment burden.
 *
 * Why SMTP is preferred: mail() from shared hosting is unauthenticated, so it
 * routinely fails SPF/DKIM and is silently dropped or filed as spam. For a login
 * code that means being locked out of your own admin panel. SMTP through a real
 * mailbox on the domain is authenticated and SPF/DKIM-aligned.
 *
 * Everything here returns a structured result rather than throwing, so callers can
 * decide what to tell the user and what to log.
 */

/**
 * Send an email.
 *
 * @param string $to        Recipient address (must already be validated)
 * @param string $subject   Plain-text subject (header injection is stripped)
 * @param string $textBody  Plain-text alternative
 * @param string $htmlBody  HTML alternative; omit for text-only
 * @return array{sent:bool, transport:string, error:string}
 */
function sendMail(string $to, string $subject, string $textBody, string $htmlBody = ''): array {
    // Reject anything that is not a clean address before it reaches a header.
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return ['sent' => false, 'transport' => 'none', 'error' => 'Invalid recipient address'];
    }

    // Strip CR/LF from header-bound values (OWASP: email header injection).
    $subject = trim(str_replace(["\r", "\n"], '', $subject));

    $fromAddress = MAIL_FROM_ADDRESS;
    $fromName    = str_replace(['"', "\r", "\n"], '', MAIL_FROM_NAME);

    // A stable Message-ID and a correct Date header both help deliverability.
    $host      = parse_url(SITE_URL, PHP_URL_HOST) ?: 'localhost';
    $messageId = '<' . bin2hex(random_bytes(16)) . '@' . $host . '>';
    $boundary  = 'bnd_' . bin2hex(random_bytes(12));

    // RFC 2047 encode the display name so non-ASCII names survive.
    $fromHeader = '=?UTF-8?B?' . base64_encode($fromName) . '?= <' . $fromAddress . '>';

    $headers = [
        'From'                      => $fromHeader,
        'Reply-To'                  => $fromAddress,
        'Date'                      => date('r'),
        'Message-ID'                => $messageId,
        'MIME-Version'              => '1.0',
        'X-Auto-Response-Suppress'  => 'All',   // Stop Exchange auto-replies
        'Auto-Submitted'            => 'auto-generated',
    ];

    if ($htmlBody !== '') {
        $headers['Content-Type'] = 'multipart/alternative; boundary="' . $boundary . '"';
        $body = buildMultipartBody($boundary, $textBody, $htmlBody);
    } else {
        $headers['Content-Type']              = 'text/plain; charset=UTF-8';
        $headers['Content-Transfer-Encoding'] = 'base64';
        $body = chunk_split(base64_encode($textBody));
    }

    // 1. Preferred transport: authenticated SMTP.
    if (SMTP_CONFIGURED) {
        $result = sendMailSmtp($to, $subject, $headers, $body);
        if ($result['sent']) return $result;

        // Log and fall through — a temporary SMTP outage should not block login
        // entirely if mail() happens to work on this host.
        error_log('[mail] SMTP failed, falling back to mail(): ' . $result['error']);
    }

    // 2. Fallback transport: PHP mail().
    return sendMailNative($to, $subject, $headers, $body);
}

/** Assemble a multipart/alternative body (plain text first, HTML second). */
function buildMultipartBody(string $boundary, string $text, string $html): string {
    $crlf = "\r\n";
    return '--' . $boundary . $crlf
         . 'Content-Type: text/plain; charset=UTF-8' . $crlf
         . 'Content-Transfer-Encoding: base64' . $crlf . $crlf
         . chunk_split(base64_encode($text)) . $crlf
         . '--' . $boundary . $crlf
         . 'Content-Type: text/html; charset=UTF-8' . $crlf
         . 'Content-Transfer-Encoding: base64' . $crlf . $crlf
         . chunk_split(base64_encode($html)) . $crlf
         . '--' . $boundary . '--' . $crlf;
}

/** Send through PHP's mail(). Returns the same shape as the SMTP path. */
function sendMailNative(string $to, string $subject, array $headers, string $body): array {
    if (!function_exists('mail')) {
        return ['sent' => false, 'transport' => 'mail()', 'error' => 'mail() is disabled on this server'];
    }

    $headerLines = [];
    foreach ($headers as $name => $value) {
        $headerLines[] = $name . ': ' . $value;
    }

    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $ok = @mail($to, $encodedSubject, $body, implode("\r\n", $headerLines));

    return [
        'sent'      => (bool) $ok,
        'transport' => 'mail()',
        'error'     => $ok ? '' : 'mail() returned false (message rejected by the local MTA)',
    ];
}

/**
 * Send through authenticated SMTP.
 *
 * Supports implicit TLS (port 465, ssl://) and STARTTLS (port 587). Every step
 * checks the server's reply code, so a failure is reported rather than assumed to
 * have worked.
 */
function sendMailSmtp(string $to, string $subject, array $headers, string $body): array {
    $host = SMTP_HOST;
    $port = SMTP_PORT;

    // Kept deliberately short. A healthy SMTP server answers in well under a second,
    // and this runs inside the login request: if the mail host is unreachable the
    // user should reach the mail() fallback quickly rather than watch a spinner.
    $timeout = 8;

    // Port 465 speaks TLS from the first byte; 587 upgrades with STARTTLS.
    $implicitTls = ($port === 465);
    $transport   = $implicitTls ? "ssl://{$host}:{$port}" : "tcp://{$host}:{$port}";

    $context = stream_context_create([
        'ssl' => [
            'verify_peer'       => true,
            'verify_peer_name'  => true,
            'allow_self_signed' => false,
        ],
    ]);

    $socket = @stream_socket_client(
        $transport, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context
    );

    if (!$socket) {
        return ['sent' => false, 'transport' => 'smtp', 'error' => "Connect failed: {$errstr} ({$errno})"];
    }
    stream_set_timeout($socket, $timeout);

    // Local helpers scoped to this connection.
    $read = function () use ($socket): string {
        $data = '';
        while (($line = fgets($socket, 515)) !== false) {
            $data .= $line;
            // A multi-line reply has a '-' in the 4th column; the last line has ' '.
            if (strlen($line) < 4 || $line[3] !== '-') break;
        }
        return $data;
    };
    $write = function (string $cmd) use ($socket): void {
        fwrite($socket, $cmd . "\r\n");
    };
    $code = fn(string $reply): int => (int) substr(trim($reply), 0, 3);

    $fail = function (string $stage, string $reply) use ($socket): array {
        @fwrite($socket, "QUIT\r\n");
        @fclose($socket);
        return [
            'sent'      => false,
            'transport' => 'smtp',
            'error'     => $stage . ': ' . trim(preg_replace('/\s+/', ' ', $reply)),
        ];
    };

    // ── Greeting ──
    $reply = $read();
    if ($code($reply) !== 220) return $fail('Greeting', $reply);

    $ehloName = parse_url(SITE_URL, PHP_URL_HOST) ?: 'localhost';

    $write('EHLO ' . $ehloName);
    $reply = $read();
    if ($code($reply) !== 250) return $fail('EHLO', $reply);

    // ── STARTTLS upgrade for port 587 ──
    if (!$implicitTls) {
        $write('STARTTLS');
        $reply = $read();
        if ($code($reply) !== 220) return $fail('STARTTLS', $reply);

        $crypto = @stream_socket_enable_crypto(
            $socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT
        );
        if (!$crypto) return $fail('TLS handshake', 'stream_socket_enable_crypto failed');

        // The session resets after the upgrade, so EHLO must be repeated.
        $write('EHLO ' . $ehloName);
        $reply = $read();
        if ($code($reply) !== 250) return $fail('EHLO (post-TLS)', $reply);
    }

    // ── AUTH LOGIN ──
    $write('AUTH LOGIN');
    $reply = $read();
    if ($code($reply) !== 334) return $fail('AUTH', $reply);

    $write(base64_encode(SMTP_USER));
    $reply = $read();
    if ($code($reply) !== 334) return $fail('AUTH username', $reply);

    $write(base64_encode(SMTP_PASS));
    $reply = $read();
    if ($code($reply) !== 235) return $fail('AUTH password', $reply);

    // ── Envelope ──
    $write('MAIL FROM:<' . MAIL_FROM_ADDRESS . '>');
    $reply = $read();
    if ($code($reply) !== 250) return $fail('MAIL FROM', $reply);

    $write('RCPT TO:<' . $to . '>');
    $reply = $read();
    // 250 accepted, 251 will forward.
    if (!in_array($code($reply), [250, 251], true)) return $fail('RCPT TO', $reply);

    // ── Data ──
    $write('DATA');
    $reply = $read();
    if ($code($reply) !== 354) return $fail('DATA', $reply);

    $message = 'To: ' . $to . "\r\n"
             . 'Subject: =?UTF-8?B?' . base64_encode($subject) . "?=\r\n";
    foreach ($headers as $name => $value) {
        $message .= $name . ': ' . $value . "\r\n";
    }
    $message .= "\r\n" . $body;

    // Dot-stuffing: a line consisting of a single '.' would end the DATA section.
    $message = preg_replace('/^\./m', '..', $message);

    fwrite($socket, $message . "\r\n.\r\n");
    $reply = $read();
    if ($code($reply) !== 250) return $fail('Message body', $reply);

    $write('QUIT');
    @fclose($socket);

    return ['sent' => true, 'transport' => 'smtp', 'error' => ''];
}
