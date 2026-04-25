<?php

namespace App\Support;

use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class AncoraBillingMail
{
    public static function smtp(): array
    {
        return AncoraSettings::billingSmtp();
    }

    public static function imap(): array
    {
        return AncoraSettings::billingImap();
    }

    public static function isSmtpConfigured(): bool
    {
        $smtp = static::smtp();

        return trim((string) ($smtp['host'] ?? '')) !== ''
            && trim((string) ($smtp['from_address'] ?? '')) !== '';
    }

    public static function sendHtml(array $payload): array
    {
        $smtp = static::smtp();

        if (!static::isSmtpConfigured()) {
            return [
                'send_status' => 'failed',
                'transport_message' => 'SMTP de cobranca nao configurado em Configuracoes.',
                'imap_status' => 'not_configured',
                'imap_message' => 'Espelhamento IMAP nao configurado.',
            ];
        }

        try {
            $email = (new Email())
                ->from(new Address(
                    (string) ($smtp['from_address'] ?? ''),
                    (string) ($smtp['from_name'] ?? 'Ancora Cobranca')
                ))
                ->subject((string) ($payload['subject'] ?? 'Solicitacao de boleto'))
                ->html((string) ($payload['html'] ?? ''));

            foreach ((array) ($payload['to'] ?? []) as $recipient) {
                $recipient = trim((string) $recipient);
                if ($recipient !== '') {
                    $email->addTo(new Address($recipient));
                }
            }

            $attachmentPath = trim((string) ($payload['attachment_path'] ?? ''));
            if ($attachmentPath !== '' && is_file($attachmentPath)) {
                $email->attachFromPath(
                    $attachmentPath,
                    (string) ($payload['attachment_name'] ?? basename($attachmentPath)),
                    (string) ($payload['attachment_mime'] ?? 'application/pdf')
                );
            }

            $transport = Transport::fromDsn(static::smtpDsn($smtp));
            $mailer = new Mailer($transport);
            $mailer->send($email);

            $imapResult = static::appendToSentFolder($email);

            return [
                'send_status' => 'sent',
                'transport_message' => 'E-mail enviado com sucesso pelo SMTP de cobranca.',
                'imap_status' => $imapResult['status'],
                'imap_message' => $imapResult['message'],
            ];
        } catch (TransportExceptionInterface $e) {
            return [
                'send_status' => 'failed',
                'transport_message' => $e->getMessage(),
                'imap_status' => 'not_attempted',
                'imap_message' => 'O espelhamento IMAP nao foi tentado porque o envio SMTP falhou.',
            ];
        } catch (\Throwable $e) {
            return [
                'send_status' => 'failed',
                'transport_message' => $e->getMessage(),
                'imap_status' => 'not_attempted',
                'imap_message' => 'O espelhamento IMAP nao foi tentado porque o envio SMTP falhou.',
            ];
        }
    }

    private static function smtpDsn(array $smtp): string
    {
        $scheme = (($smtp['encryption'] ?? '') === 'ssl') ? 'smtps' : 'smtp';
        $host = trim((string) ($smtp['host'] ?? ''));
        $port = (int) ($smtp['port'] ?? 587);
        $username = trim((string) ($smtp['username'] ?? ''));
        $password = (string) ($smtp['password'] ?? '');

        $credentials = '';
        if ($username !== '' || $password !== '') {
            $credentials = rawurlencode($username) . ':' . rawurlencode($password) . '@';
        }

        return $scheme . '://' . $credentials . $host . ':' . $port;
    }

    private static function appendToSentFolder(Email $email): array
    {
        $imap = static::imap();

        if (!static::isImapConfigured($imap)) {
            return [
                'status' => 'not_configured',
                'message' => 'Espelhamento IMAP nao configurado.',
            ];
        }

        $stream = null;
        $sequence = 1;

        try {
            $stream = static::imapConnect($imap);

            if (($imap['encryption'] ?? '') === 'tls') {
                static::imapStartTls($stream, $sequence);
            }

            static::imapLogin(
                $stream,
                $sequence,
                (string) ($imap['username'] ?? ''),
                (string) ($imap['password'] ?? '')
            );

            $usedMailbox = static::imapAppendWithFallbacks(
                $stream,
                $sequence,
                (string) ($imap['sent_folder'] ?? 'Sent'),
                $email->toString()
            );

            static::imapLogout($stream, $sequence);

            return [
                'status' => 'mirrored',
                'message' => 'Mensagem espelhada na pasta de enviados via IMAP (' . $usedMailbox . ').',
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'failed',
                'message' => $e->getMessage(),
            ];
        } finally {
            if (is_resource($stream)) {
                @fclose($stream);
            }
        }
    }

    private static function isImapConfigured(array $imap): bool
    {
        return trim((string) ($imap['host'] ?? '')) !== ''
            && trim((string) ($imap['username'] ?? '')) !== ''
            && trim((string) ($imap['password'] ?? '')) !== ''
            && trim((string) ($imap['sent_folder'] ?? '')) !== '';
    }

    private static function imapConnect(array $imap)
    {
        $host = trim((string) ($imap['host'] ?? ''));
        $port = (int) ($imap['port'] ?? 993);
        $encryption = trim((string) ($imap['encryption'] ?? 'ssl'));
        $validateCert = (bool) ($imap['validate_cert'] ?? false);

        $transport = $encryption === 'ssl' ? 'ssl' : 'tcp';
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => $validateCert,
                'verify_peer_name' => $validateCert,
                'allow_self_signed' => !$validateCert,
                'peer_name' => $host,
                'SNI_enabled' => true,
            ],
        ]);

        $stream = @stream_socket_client(
            sprintf('%s://%s:%d', $transport, $host, $port),
            $errorNumber,
            $errorMessage,
            20,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!is_resource($stream)) {
            $detail = trim((string) ($errorMessage ?: 'erro desconhecido'));

            throw new \RuntimeException('Nao foi possivel conectar ao servidor IMAP: ' . $detail . '.');
        }

        stream_set_timeout($stream, 20);

        $greeting = static::imapReadLine($stream);
        if ($greeting === null || !preg_match('/^\* (OK|PREAUTH)\b/i', $greeting)) {
            throw new \RuntimeException('O servidor IMAP nao retornou uma saudacao valida.');
        }

        return $stream;
    }

    private static function imapStartTls($stream, int &$sequence): void
    {
        $response = static::imapSendCommand($stream, $sequence, 'STARTTLS');
        if (($response['status'] ?? '') !== 'OK') {
            throw new \RuntimeException(static::imapResponseMessage(
                $response,
                'O servidor IMAP recusou o comando STARTTLS.'
            ));
        }

        $cryptoMethod = defined('STREAM_CRYPTO_METHOD_TLS_CLIENT')
            ? STREAM_CRYPTO_METHOD_TLS_CLIENT
            : STREAM_CRYPTO_METHOD_SSLv23_CLIENT;

        if (@stream_socket_enable_crypto($stream, true, $cryptoMethod) !== true) {
            throw new \RuntimeException('Nao foi possivel ativar a criptografia TLS na conexao IMAP.');
        }
    }

    private static function imapLogin($stream, int &$sequence, string $username, string $password): void
    {
        $response = static::imapSendCommand(
            $stream,
            $sequence,
            'LOGIN ' . static::imapQuotedString($username) . ' ' . static::imapQuotedString($password)
        );

        if (($response['status'] ?? '') !== 'OK') {
            throw new \RuntimeException(static::imapResponseMessage(
                $response,
                'Nao foi possivel autenticar na caixa IMAP de cobranca.'
            ));
        }
    }

    private static function imapAppend($stream, int &$sequence, string $mailbox, string $message): void
    {
        $tag = static::imapNextTag($sequence);
        $message = static::normalizeImapMessage($message);

        static::imapWrite(
            $stream,
            sprintf(
                "%s APPEND %s (\\Seen) {%d}\r\n",
                $tag,
                static::imapQuotedString($mailbox),
                strlen($message)
            )
        );

        static::imapAwaitContinuation($stream, $tag);
        static::imapWrite($stream, $message . "\r\n");

        $response = static::imapReadTaggedResponse($stream, $tag);
        if (($response['status'] ?? '') !== 'OK') {
            throw new \RuntimeException(static::imapResponseMessage(
                $response,
                'Nao foi possivel anexar a mensagem na pasta de enviados.'
            ));
        }
    }

    private static function imapAppendWithFallbacks($stream, int &$sequence, string $mailbox, string $message): string
    {
        $candidates = static::imapMailboxCandidates($mailbox);
        $lastException = null;

        foreach ($candidates as $candidate) {
            try {
                static::imapAppend($stream, $sequence, $candidate, $message);

                return $candidate;
            } catch (\RuntimeException $e) {
                $lastException = $e;

                if (!static::shouldRetryMailboxName($e->getMessage())) {
                    throw $e;
                }
            }
        }

        throw $lastException ?? new \RuntimeException('Nao foi possivel anexar a mensagem na pasta de enviados.');
    }

    private static function imapLogout($stream, int &$sequence): void
    {
        static::imapSendCommand($stream, $sequence, 'LOGOUT');
    }

    private static function imapSendCommand($stream, int &$sequence, string $command): array
    {
        $tag = static::imapNextTag($sequence);
        static::imapWrite($stream, $tag . ' ' . $command . "\r\n");

        return static::imapReadTaggedResponse($stream, $tag);
    }

    private static function imapReadTaggedResponse($stream, string $tag): array
    {
        $lines = [];

        while (($line = static::imapReadLine($stream)) !== null) {
            $lines[] = $line;

            if (preg_match('/^' . preg_quote($tag, '/') . ' (OK|NO|BAD)\b/i', $line, $matches)) {
                return [
                    'status' => strtoupper((string) ($matches[1] ?? '')),
                    'lines' => $lines,
                ];
            }
        }

        throw new \RuntimeException('A conexao IMAP foi encerrada antes da resposta final do servidor.');
    }

    private static function imapAwaitContinuation($stream, string $tag): void
    {
        while (($line = static::imapReadLine($stream)) !== null) {
            if (str_starts_with($line, '+')) {
                return;
            }

            if (preg_match('/^' . preg_quote($tag, '/') . ' (OK|NO|BAD)\b/i', $line)) {
                throw new \RuntimeException(static::imapResponseMessage(
                    ['lines' => [$line]],
                    'O servidor IMAP recusou o recebimento da mensagem.'
                ));
            }
        }

        throw new \RuntimeException('A conexao IMAP foi encerrada antes da confirmacao do APPEND.');
    }

    private static function imapReadLine($stream): ?string
    {
        $line = fgets($stream);
        if ($line === false) {
            $meta = stream_get_meta_data($stream);
            if (($meta['timed_out'] ?? false) === true) {
                throw new \RuntimeException('Tempo esgotado na comunicacao com o servidor IMAP.');
            }

            return null;
        }

        return rtrim($line, "\r\n");
    }

    private static function imapWrite($stream, string $payload): void
    {
        $written = 0;
        $length = strlen($payload);

        while ($written < $length) {
            $result = fwrite($stream, substr($payload, $written));
            if ($result === false || $result === 0) {
                throw new \RuntimeException('Nao foi possivel enviar dados para o servidor IMAP.');
            }

            $written += $result;
        }
    }

    private static function imapNextTag(int &$sequence): string
    {
        $tag = sprintf('A%04d', $sequence);
        $sequence++;

        return $tag;
    }

    private static function imapMailboxCandidates(string $mailbox): array
    {
        $mailbox = trim($mailbox);
        $baseMailbox = preg_replace('/^INBOX[\.\/]/i', '', $mailbox) ?? $mailbox;

        $candidates = [$mailbox];

        if ($baseMailbox !== '' && !preg_match('/^INBOX[\.\/]/i', $mailbox)) {
            $candidates[] = 'INBOX.' . $baseMailbox;
            $candidates[] = 'INBOX/' . $baseMailbox;
        }

        return array_values(array_unique(array_filter($candidates, fn ($value) => trim((string) $value) !== '')));
    }

    private static function shouldRetryMailboxName(string $message): bool
    {
        $normalized = strtolower($message);

        return str_contains($normalized, 'nonexistent namespace')
            || str_contains($normalized, 'prefixed with: inbox')
            || str_contains($normalized, '[trycreate]')
            || str_contains($normalized, 'does not exist');
    }

    private static function imapQuotedString(string $value): string
    {
        $normalized = str_replace(["\\", '"', "\r", "\n"], ["\\\\", '\\"', ' ', ' '], $value);

        return '"' . $normalized . '"';
    }

    private static function normalizeImapMessage(string $message): string
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $message);

        return str_replace("\n", "\r\n", $normalized);
    }

    private static function imapResponseMessage(array $response, string $fallback): string
    {
        $lines = $response['lines'] ?? [];
        $lastLine = trim((string) end($lines));
        if ($lastLine === '') {
            return $fallback;
        }

        $cleanLine = preg_replace('/^A\d+\s+(OK|NO|BAD)\s*/i', '', $lastLine) ?? '';
        $cleanLine = trim($cleanLine);

        return $cleanLine !== '' ? $cleanLine : $fallback;
    }
}
