<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SendMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        // L'autorisation fine (appartenance, attribution) est portée par la
        // ConversationPolicy, appelée dans le contrôleur.
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'type' => ['nullable', 'string', 'in:text,template'],

            // 4096 est la limite Meta pour un message texte. Valider ici
            // évite un aller-retour et un rejet côté API.
            'body' => ['required_without:template_id', 'nullable', 'string', 'max:4096'],

            'template_id'           => ['required_if:type,template', 'nullable', 'uuid'],
            'template_parameters'   => ['nullable', 'array', 'max:10'],
            'template_parameters.*' => ['string', 'max:1024'],

            'reply_to' => ['nullable', 'string', 'max:128'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $body = trim((string) $this->input('body'));

                // Un message composé uniquement d'espaces passe `required`
                // mais serait rejeté par Meta.
                if ($this->input('type') !== 'template' && $body === '') {
                    $validator->errors()->add('body', 'Le message ne peut pas être vide.');
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'body.max'              => 'Un message WhatsApp ne peut pas dépasser 4096 caractères.',
            'template_id.required_if' => 'Sélectionnez un modèle de message approuvé.',
        ];
    }
}
