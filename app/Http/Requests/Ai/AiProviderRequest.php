<?php

namespace App\Http\Requests\Ai;

use App\Http\Requests\BaseFormRequest;

/** Tambah/kemaskini provider AI — api_key plaintext ke vault SAHAJA. */
class AiProviderRequest extends BaseFormRequest
{
    public function rules(): array
    {
        $wujudId = $this->route('provider')?->id;

        return [
            'provider' => ['required', 'in:OPENAI,ANTHROPIC,GEMINI,DEEPSEEK,QWEN,CUSTOM'],
            'dialect' => ['required', 'in:openai,anthropic,gemini'],
            'base_url' => ['nullable', 'url', 'max:200'],
            'model' => ['required', 'string', 'max:80'],
            // api_key wajib semasa cipta; pilihan semasa edit (kekalkan kunci lama)
            'api_key' => [$wujudId ? 'nullable' : 'required', 'string', 'max:500'],
            'supports_vision' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return ['api_key' => 'kunci API', 'model' => 'model', 'dialect' => 'dialect'];
    }
}
