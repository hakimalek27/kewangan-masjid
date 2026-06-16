<?php

namespace App\Http\Requests\Ai;

use App\Http\Requests\BaseFormRequest;

/** Tetapan bot Telegram — token ke vault SAHAJA, jadual simpan ref. */
class TelegramConfigRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            // bot_token pilihan semasa edit — kosong = kekalkan token lama
            'bot_token' => ['nullable', 'string', 'max:200'],
            'chat_id' => ['required', 'integer'],
            'default_jenis' => ['required', 'in:BAYARAN,KUTIPAN'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return ['bot_token' => 'token bot', 'chat_id' => 'ID chat', 'default_jenis' => 'jenis lalai'];
    }
}
