<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\DeliveryReceiptController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Middleware\VerifyProviderSignature;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('notifications/bulk', [NotificationController::class, 'store']);

    Route::get('notifications/subscriber/{subscriber_id}', [NotificationController::class, 'history']);

    Route::post('notifications/{notification}/delivery-receipt', DeliveryReceiptController::class)
        ->middleware(VerifyProviderSignature::class);
});
