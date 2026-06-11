<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\NotificationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeliveryReceiptRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([
                NotificationStatus::Delivered->value,
                NotificationStatus::Dropped->value,
            ])],
            'failure_reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
