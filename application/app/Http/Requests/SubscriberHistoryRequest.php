<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubscriberHistoryRequest extends FormRequest
{
    public const DEFAULT_PER_PAGE = 25;

    public const MIN_PER_PAGE = 1;

    public const MAX_PER_PAGE = 100;

    public function rules(): array
    {
        return [
            'per_page' => ['sometimes', 'integer', 'min:'.self::MIN_PER_PAGE, 'max:'.self::MAX_PER_PAGE],
            'cursor' => ['sometimes', 'string'],
        ];
    }

    public function perPage(): int
    {
        return $this->integer('per_page', self::DEFAULT_PER_PAGE);
    }
}
