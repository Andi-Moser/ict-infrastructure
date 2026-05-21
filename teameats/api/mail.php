<?php

function send_registration_email(array $idea, array $registration): void
{
    $host = getenv('SMTP_HOST') ?: '';
    $port = (int)(getenv('SMTP_PORT') ?: 25);

    if (!$host) {
        error_log("TeamEats: SMTP_HOST not set, skipping email notification");
        return;
    }

    $subject = "TeamEats: {$registration['name']} joined \"{$idea['idea']}\" on {$idea['date']}";

    $body  = "Hi {$idea['proposed_by']},\n\n";
    $body .= "{$registration['name']} has registered for \"{$idea['idea']}\" on {$idea['date']}.\n";
    if (!empty($registration['comment'])) {
        $body .= "\nComment: {$registration['comment']}\n";
    }
    $body .= "\n-- TeamEats";

    smtp_send(
        $host, $port,
        $registration['name'],  $registration['email'],
        $idea['proposed_by'],   $idea['email'],
        $subject, $body
    );
}

function smtp_send(
    string $host, int $port,
    string $from_name, string $from_email,
    string $to_name, string $to_email,
    string $subject, string $body
): void {
    $socket = @fsockopen($host, $port, $errno, $errstr, 5);
    if (!$socket) {
        error_log("TeamEats SMTP: cannot connect to $host:$port — $errstr ($errno)");
        return;
    }

    stream_set_timeout($socket, 5);

    $recv = fn() => fgets($socket, 512);
    $send = fn(string $line) => fputs($socket, $line . "\r\n");

    $recv(); // 220 greeting
    $send("HELO teameats");   $recv();
    $send("MAIL FROM:<{$from_email}>");  $recv();
    $send("RCPT TO:<{$to_email}>");      $recv();
    $send("DATA");            $recv();

    $send("From: {$from_name} <{$from_email}>");
    $send("To: {$to_name} <{$to_email}>");
    $send("Subject: {$subject}");
    $send("MIME-Version: 1.0");
    $send("Content-Type: text/plain; charset=UTF-8");
    $send("");

    foreach (explode("\n", str_replace("\r\n", "\n", $body)) as $line) {
        // RFC 5321 dot-stuffing
        $send(str_starts_with($line, '.') ? '.' . $line : $line);
    }

    $send(".");  $recv();
    $send("QUIT");
    fclose($socket);
}
