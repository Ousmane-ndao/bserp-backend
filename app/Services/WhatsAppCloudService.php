<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppCloudService
{
    public function isConfigured(): bool
    {
        $token = trim((string) config('whatsapp.token'));
        $phoneId = trim((string) config('whatsapp.phone_number_id'));

        return $token !== '' && $phoneId !== '';
    }

    /**
     * @return array{ok: bool, not_on_whatsapp: bool, message: string}
     */
    public function sendPdfDocument(string $whatsappId, string $pdfBinary, string $filename, string $caption): array
    {
        if (! $this->isConfigured()) {
            return [
                'ok' => false,
                'not_on_whatsapp' => false,
                'message' => 'Le service WhatsApp n’est pas configuré sur le serveur. Impossible de vérifier ni d’envoyer le PDF.',
            ];
        }

        $version = (string) config('whatsapp.graph_version', 'v21.0');
        $phoneId = (string) config('whatsapp.phone_number_id');
        $token = (string) config('whatsapp.token');
        $base = 'https://graph.facebook.com/'.$version.'/'.$phoneId;

        try {
            $media = Http::withToken($token)
                ->timeout(60)
                ->attach('file', $pdfBinary, $filename, ['Content-Type' => 'application/pdf'])
                ->post($base.'/media', [
                    'messaging_product' => 'whatsapp',
                    'type' => 'application/pdf',
                ]);

            if (! $media->successful()) {
                return $this->interpretError($media->json(), $media->body());
            }

            $mediaId = (string) ($media->json('id') ?? '');
            if ($mediaId === '') {
                return [
                    'ok' => false,
                    'not_on_whatsapp' => false,
                    'message' => 'WhatsApp n’a pas accepté le fichier PDF.',
                ];
            }

            $sent = Http::withToken($token)
                ->timeout(60)
                ->post($base.'/messages', [
                    'messaging_product' => 'whatsapp',
                    'to' => $whatsappId,
                    'type' => 'document',
                    'document' => [
                        'id' => $mediaId,
                        'filename' => $filename,
                        'caption' => $caption,
                    ],
                ]);

            if (! $sent->successful()) {
                return $this->interpretError($sent->json(), $sent->body());
            }

            return [
                'ok' => true,
                'not_on_whatsapp' => false,
                'message' => 'Facture envoyée par WhatsApp',
            ];
        } catch (\Throwable $e) {
            Log::error('WhatsApp invoice send failed', ['error' => $e->getMessage()]);

            return [
                'ok' => false,
                'not_on_whatsapp' => false,
                'message' => 'Échec de l’envoi WhatsApp : '.$e->getMessage(),
            ];
        }
    }

    /**
     * @param  array<string, mixed>|null  $json
     * @return array{ok: bool, not_on_whatsapp: bool, message: string}
     */
    private function interpretError(?array $json, string $rawBody): array
    {
        $code = (int) data_get($json, 'error.code', 0);
        $apiMessage = (string) (data_get($json, 'error.message') ?? $rawBody);
        $notOnWhatsapp = in_array($code, [131026, 131047, 133010], true)
            || str_contains(strtolower($apiMessage), 'not a whatsapp')
            || str_contains(strtolower($apiMessage), 'undeliverable');

        if ($notOnWhatsapp) {
            return [
                'ok' => false,
                'not_on_whatsapp' => true,
                'message' => 'le numéro n’est pas associé à WhatsApp',
            ];
        }

        return [
            'ok' => false,
            'not_on_whatsapp' => false,
            'message' => $apiMessage !== '' ? $apiMessage : 'Échec de l’envoi WhatsApp.',
        ];
    }
}
