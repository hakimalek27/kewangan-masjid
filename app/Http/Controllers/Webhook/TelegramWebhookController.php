<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessDocInbox;
use App\Models\DocInbox;
use App\Models\TgBotConfig;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Webhook Telegram (TANPA sesi/auth/CSRF — didaftar dalam routes/webhooks.php).
 *
 * Keselamatan berlapis:
 *  1. Header X-Telegram-Bot-Api-Secret-Token mesti sepadan TELEGRAM_WEBHOOK_SECRET.
 *  2. chat_id mesti wujud & aktif dalam tg_bot_config (whitelist group).
 *  3. AI TIDAK pos jurnal — hanya cipta doc_inbox → job → draf PENDING_REVIEW.
 */
class TelegramWebhookController extends Controller
{
    public function handle(Request $request): Response
    {
        $secret = (string) config('services.telegram.webhook_secret');
        if ($secret === '' || !hash_equals($secret, (string) $request->header('X-Telegram-Bot-Api-Secret-Token'))) {
            return response('', 403); // 403 senyap — jangan dedah maklumat
        }

        $msg = $request->input('message');
        if (!is_array($msg) || !isset($msg['chat']['id'], $msg['message_id'])) {
            return response('', 200); // bukan mesej — abai
        }

        $bot = TgBotConfig::withoutMasjidScope()
            ->where('chat_id', (int) $msg['chat']['id'])
            ->where('is_active', 1)
            ->first();
        if (!$bot) {
            return response('', 403); // chat tidak diiktiraf — 403 senyap
        }

        [$fileId, $fileType] = $this->kesanFail($msg);
        if (!$fileId) {
            return response('', 200); // bukan resit/dokumen — abai
        }

        // Anti-pendua peringkat mesej (tg_message_id unik per chat)
        $sudahAda = DocInbox::withoutMasjidScope()
            ->where('tg_chat_id', (int) $msg['chat']['id'])
            ->where('tg_message_id', (int) $msg['message_id'])
            ->exists();
        if ($sudahAda) {
            return response('', 200);
        }

        $inbox = DocInbox::create([
            'masjid_id' => $bot->masjid_id,
            'tg_chat_id' => (int) $msg['chat']['id'],
            'tg_message_id' => (int) $msg['message_id'],
            'tg_file_id' => $fileId,
            'tg_sender_id' => $msg['from']['id'] ?? null,
            'tg_sender_name' => trim(($msg['from']['first_name'] ?? '').' '.($msg['from']['last_name'] ?? '')) ?: null,
            'file_type' => $fileType,
            'caption' => isset($msg['caption']) ? mb_substr((string) $msg['caption'], 0, 500) : null,
            'status' => 'RECEIVED',
        ]);

        ProcessDocInbox::dispatch($inbox->id);

        return response('', 200);
    }

    /** @return array{0: ?string, 1: ?string} [file_id, IMAGE|PDF] */
    private function kesanFail(array $msg): array
    {
        if (!empty($msg['photo']) && is_array($msg['photo'])) {
            $terbesar = end($msg['photo']); // Telegram susun kecil → besar

            return [$terbesar['file_id'] ?? null, 'IMAGE'];
        }

        $mime = (string) ($msg['document']['mime_type'] ?? '');
        if (!empty($msg['document']['file_id']) && preg_match('/pdf|image/i', $mime)) {
            return [$msg['document']['file_id'], str_contains($mime, 'pdf') ? 'PDF' : 'IMAGE'];
        }

        return [null, null];
    }
}
