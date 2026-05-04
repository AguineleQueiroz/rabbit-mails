<?php

namespace App\Services;

class EmailService
{
    public function send(string $to, string $subject, string $body): bool
    {
        $host = $_ENV['MAIL_HOST'];
        $port = (int)$_ENV['MAIL_PORT'];

        $socket = @stream_socket_client(
            "tcp://{$host}:{$port}",
            $errno,
            $errstr,
            10,
            STREAM_CLIENT_CONNECT
        );

        if (!$socket) {
            throw new \RuntimeException("Falha ao conectar ao SMTP: {$errstr} ({$errno})");
        }

        stream_set_timeout($socket, 10);

        $this->expect($socket, '220');
        $this->cmd($socket, "EHLO emailqueue", '250');

        /* STARTTLS — negotiated when MAIL_TLS=true (port 587) */
        if (($_ENV['MAIL_TLS'] ?? 'false') === 'true') {
            $this->cmd($socket, 'STARTTLS', '220');
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                fclose($socket);
                throw new \RuntimeException('Falha ao iniciar TLS com o servidor SMTP.');
            }
            /* EHLO again after TLS - required by RFC 3207 */
            $this->cmd($socket, "EHLO emailqueue", '250');
        }

        /* AUTH LOGIN — triggered when MAIL_USER and MAIL_PASS are set. */
        $user = $_ENV['MAIL_USER'] ?? '';
        $pass = $_ENV['MAIL_PASS'] ?? '';

        if ($user !== '' && $pass !== '') {
            $this->cmd($socket, 'AUTH LOGIN', '334');
            $this->cmd($socket, base64_encode($user), '334');
            $this->cmd($socket, base64_encode($pass), '235');
        }

        $from = $_ENV['MAIL_FROM'];
        $this->cmd($socket, "MAIL FROM:<{$from}>", '250');
        $this->cmd($socket, "RCPT TO:<{$to}>", '250');
        $this->cmd($socket, 'DATA', '354');

        $headers = implode("\r\n", [
            "From: {$from}",
            "To: {$to}",
            "Subject: {$subject}",
            "MIME-Version: 1.0",
            "Content-Type: text/html; charset=UTF-8",
        ]);

        fwrite($socket, "{$headers}\r\n\r\n{$body}\r\n.\r\n");
        $this->expect($socket, '250');

        $this->cmd($socket, 'QUIT', '221');
        fclose($socket);

        return true;
    }

    private function cmd($socket, string $command, string $expectedCode): string
    {
        fwrite($socket, "{$command}\r\n");
        return $this->expect($socket, $expectedCode);
    }

    private function expect($socket, string $expectedCode): string
    {
        $response = '';
        while ($line = fgets($socket, 512)) {
            $response .= $line;
            /*
             * The final line of the SMTP response has a space after the code ("250 OK")
             * Intermediate lines in multi-line responses use a hyphen ("250-STARTTLS")
             * */
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }

        $code = substr($response, 0, 3);
        if ($code !== $expectedCode) {
            throw new \RuntimeException(
                "SMTP response unexpected - expected {$expectedCode}, received {$code}: " . trim($response)
            );
        }

        return $response;
    }
}
