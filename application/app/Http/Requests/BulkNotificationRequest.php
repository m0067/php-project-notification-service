<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\NotificationChannel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkNotificationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'request_id' => ['required', 'uuid'],
            'channel' => ['required', Rule::enum(NotificationChannel::class)],
            'message' => ['required', 'string', 'max:1000'],
            'recipients' => ['required', 'array', 'min:1'],
            'recipients.*' => ['required', 'string', 'max:255'],
            'is_transactional' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['request_id' => $this->header('Request-Id')]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'request_id.required' => 'The Request-Id header is required.',
            'request_id.uuid' => 'The Request-Id header must be a valid UUID.',
        ];
    }
}
