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
                'transport_message' => 'SMTP de cobrança não configurado em Configurações.',
                'imap_status' => 'not_configured',
                'imap_message' => 'Espelhamento IMAP não configurado.',
            ];
        }

        try {
            $email = (new Email())
                ->from(new Address(
                    (string) ($smtp['from_address'] ?? ''),
                    (string) ($smtp['from_name'] ?? 'Âncora Cobrança')
                ))
                ->subject((string) ($payload['subject'] ?? 'Solicitação de boleto'))
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
                'transport_message' => 'E-mail enviado com sucesso pelo SMTP de cobrança.',
                'imap_status' => $imapResult['status'],
                'imap_message' => $imapResult['message'],
            ];
        } catch (TransportExceptionInterface $e) {
            return [
                'send_status' => 'failed',
                'transport_message' => $e->getMessage(),
                'imap_status' => 'not_attempted',
                'imap_message' => 'O espelhamento IMAP não foi tentado porque o envio SMTP falhou.',
            ];
        } catch (\Throwable $e) {
            return [
                'send_status' => 'failed',
                'transport_message' => $e->getMessage(),
                'imap_status' => 'not_attempted',
                'imap_message' => 'O espelhamento IMAP não foi tentado porque o envio SMTP falhou.',
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
                'message' => 'Espelhamento IMAP não configurado.',
            ];
        }

        if (!function_exists('imap_open') || !defined('OP_HALFOPEN')) {
            return [
                'status' => 'unavailable',
                'message' => 'Extensão IMAP indisponível no servidor.',
            ];
        }

        $mailbox = static::imapMailbox($imap);
        $username = (string) ($imap['username'] ?? '');
        $password = (string) ($imap['password'] ?? '');

        $stream = null;
        $previousErrors = imap_errors() ?: [];
        if ($previousErrors !== []) {
            // Limpa o buffer interno da extensão.
        }

        try {
            $stream = @imap_open($mailbox, $username, $password, OP_HALFOPEN, 1, [
                'DISABLE_AUTHENTICATOR' => 'GSSAPI',
            ]);

            if (!$stream) {
                $error = static::imapErrorMessage();

                return [
                    'status' => 'failed',
                    'message' => $error ?: 'Não foi possível abrir a caixa IMAP para espelhamento.',
                ];
            }

            $appended = @imap_append($stream, $mailbox, $email->toString(), '\\Seen');
            if (!$appended) {
                $error = static::imapErrorMessage();

                return [
                    'status' => 'failed',
                    'message' => $error ?: 'Não foi possível anexar a mensagem em Itens enviados.',
                ];
            }

            return [
                'status' => 'mirrored',
                'message' => 'Mensagem espelhada na pasta de enviados via IMAP.',
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'failed',
                'message' => $e->getMessage(),
            ];
        } finally {
            if (is_resource($stream)) {
                @imap_close($stream);
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

    private static function imapMailbox(array $imap): string
    {
        $host = trim((string) ($imap['host'] ?? ''));
        $port = (int) ($imap['port'] ?? 993);
        $encryption = trim((string) ($imap['encryption'] ?? 'ssl'));
        $sentFolder = trim((string) ($imap['sent_folder'] ?? 'Sent'));
        $validateCert = (bool) ($imap['validate_cert'] ?? false);

        $flags = ['imap'];
        if ($encryption === 'ssl') {
            $flags[] = 'ssl';
        } elseif ($encryption === 'tls') {
            $flags[] = 'tls';
        }
        if (!$validateCert) {
            $flags[] = 'novalidate-cert';
        }

        return sprintf('{%s:%d/%s}%s', $host, $port, implode('/', $flags), $sentFolder);
    }

    private static function imapErrorMessage(): ?string
    {
        $errors = imap_errors() ?: [];
        if ($errors === []) {
            return null;
        }

        return trim((string) end($errors)) ?: null;
    }
}
