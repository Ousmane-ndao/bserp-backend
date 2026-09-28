<?php

namespace App\Support;

use Illuminate\Support\Facades\Mail;

class OutboundMail
{
    /**
     * True when Mail::send() can reach a real inbox (or a test fake).
     */
    public static function canDeliver(): bool
    {
        if (Mail::isFake()) {
            return true;
        }

        return self::mailerDelivers((string) config('mail.default'));
    }

    public static function failureMessage(): string
    {
        $name = (string) config('mail.default');
        $host = strtolower((string) config("mail.mailers.{$name}.host", config('mail.mailers.smtp.host', '')));

        if ($name === 'log' || (config("mail.mailers.{$name}.transport") === 'log')) {
            return 'L’e-mail n’a pas été remis : le serveur écrit seulement dans les journaux (MAIL_MAILER=log).';
        }

        if ($name === 'array' || (config("mail.mailers.{$name}.transport") === 'array')) {
            return 'L’e-mail n’a pas été remis : le mailer « array » ne sort pas du serveur.';
        }

        if (in_array($host, ['mailpit', 'mailhog', 'localhost', '127.0.0.1', '::1'], true)) {
            return 'L’e-mail n’a pas été remis : le serveur SMTP est local (Mailpit/Mailhog).';
        }

        if ($name === 'brevo' && trim((string) config('mail.mailers.brevo.api_key', '')) === '') {
            return 'L’e-mail n’a pas été remis : la clé API Brevo est manquante. Ajoutez BREVO_API_KEY dans les variables d’environnement.';
        }

        return 'L’e-mail n’a pas été remis : aucun service d’envoi n’est configuré. Vérifiez MAIL_MAILER.';
    }

    private static function mailerDelivers(string $name, int $depth = 0): bool
    {
        if ($name === '' || $depth > 4) {
            return false;
        }

        $cfg = config('mail.mailers.'.$name);
        if (! is_array($cfg)) {
            return false;
        }

        $transport = (string) ($cfg['transport'] ?? $name);

        if (in_array($transport, ['log', 'array'], true)) {
            return false;
        }

        if (in_array($transport, ['failover', 'roundrobin'], true)) {
            $children = $cfg['mailers'] ?? [];
            if (! is_array($children) || $children === []) {
                return false;
            }
            foreach ($children as $child) {
                if (! is_string($child) || ! self::mailerDelivers($child, $depth + 1)) {
                    return false;
                }
            }
            return true;
        }

        if ($transport === 'smtp') {
            $host = strtolower(trim((string) ($cfg['host'] ?? '')));
            $url = trim((string) ($cfg['url'] ?? ''));
            if ($url !== '') {
                return true;
            }
            if ($host === '' || in_array($host, ['mailpit', 'mailhog', 'localhost', '127.0.0.1', '::1'], true)) {
                return false;
            }
            $user = strtolower(trim((string) ($cfg['username'] ?? '')));
            return $user !== '' && $user !== 'null';
        }

        if ($transport === 'resend') {
            return trim((string) config('services.resend.key')) !== '';
        }

        if ($transport === 'postmark') {
            return trim((string) config('services.postmark.key')) !== '';
        }

        if ($transport === 'brevo') {
            return trim((string) config('mail.mailers.brevo.api_key')) !== '';
        }

        return in_array($transport, ['ses', 'ses-v2', 'mailgun', 'sendmail', 'smtp', 'brevo'], true);
    }
}