<?php
/** Lightweight SMTP client for the password-recovery email. */
if (!defined('POS_APP')) {
    die('Direct access not permitted.');
}

class SmtpMailer
{
    private $socket;
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge(['host' => MAIL_SMTP_HOST, 'port' => MAIL_SMTP_PORT, 'username' => MAIL_SMTP_USERNAME, 'password' => MAIL_SMTP_PASSWORD], $config);
    }

    public function send(string $to, string $subject, string $html): void
    {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid recipient email.');
        }

        $this->socket = @stream_socket_client('tcp://' . $this->config['host'] . ':' . (int) $this->config['port'], $errno, $error, 15, STREAM_CLIENT_CONNECT);
        if (!$this->socket) {
            throw new RuntimeException('Could not connect to the email server.');
        }
        stream_set_timeout($this->socket, 15);

        try {
            $this->expect([220]);
            $this->command('EHLO ' . $this->localHost(), [250]);
            $this->command('STARTTLS', [220]);
            if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('Could not secure the email connection.');
            }
            $this->command('EHLO ' . $this->localHost(), [250]);
            $this->command('AUTH LOGIN', [334]);
            $this->command(base64_encode($this->config['username']), [334]);
            $this->command(base64_encode($this->config['password']), [235]);
            $this->command('MAIL FROM:<' . $this->config['username'] . '>', [250]);
            $this->command('RCPT TO:<' . $to . '>', [250, 251]);
            $this->command('DATA', [354]);

            $plain = trim(html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html)), ENT_QUOTES, 'UTF-8'));
            $boundary = '=_POS_' . bin2hex(random_bytes(12));
            $message = 'From: ' . $this->header(MAIL_FROM_NAME) . ' <' . $this->config['username'] . ">\r\n"
                . 'To: <' . $to . ">\r\n"
                . 'Subject: ' . $this->header($subject) . "\r\n"
                . "MIME-Version: 1.0\r\n"
                . 'Content-Type: multipart/alternative; boundary="' . $boundary . "\"\r\n\r\n"
                . '--' . $boundary . "\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n"
                . $this->dotStuff($plain) . "\r\n"
                . '--' . $boundary . "\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n"
                . $this->dotStuff($html) . "\r\n"
                . '--' . $boundary . "--\r\n.\r\n";
            fwrite($this->socket, $message);
            $this->expect([250]);
            $this->command('QUIT', [221]);
        } finally {
            if (is_resource($this->socket)) fclose($this->socket);
        }
    }

    private function command(string $command, array $expected): void { fwrite($this->socket, $command . "\r\n"); $this->expect($expected); }
    private function expect(array $expected): void {
        $response = '';
        do { $line = fgets($this->socket, 1024); if ($line === false) throw new RuntimeException('Email server did not respond.'); $response .= $line; } while (isset($line[3]) && $line[3] === '-');
        $code = (int) substr($response, 0, 3);
        if (!in_array($code, $expected, true)) { error_log('SMTP error ' . $code); throw new RuntimeException('Email server rejected the request.'); }
    }
    private function localHost(): string { return preg_replace('/[^A-Za-z0-9.-]/', '', $_SERVER['SERVER_NAME'] ?? 'localhost') ?: 'localhost'; }
    private function header(string $value): string { return '=?UTF-8?B?' . base64_encode(str_replace(["\r", "\n"], '', $value)) . '?='; }
    private function dotStuff(string $value): string { return preg_replace('/(^|\r?\n)\./', '$1..', str_replace("\n", "\r\n", $value)); }
}
