<?php

use App\Http\Controllers\Webhook\TelegramWebhookController;
use Illuminate\Support\Facades\Route;

/*
 | Route webhook — TIADA middleware 'web' (tiada sesi, tiada CSRF, tiada auth).
 | Keselamatan: header X-Telegram-Bot-Api-Secret-Token + whitelist chat_id
 | dalam tg_bot_config (disemak dalam controller).
 */
Route::post('/webhook/telegram', [TelegramWebhookController::class, 'handle'])
    ->name('webhook.telegram');
