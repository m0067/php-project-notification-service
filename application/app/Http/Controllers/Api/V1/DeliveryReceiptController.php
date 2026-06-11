<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Dto\DeliveryReceiptDto;
use App\Exceptions\NotificationNotSentException;
use App\Http\Controllers\Controller;
use App\Http\Requests\DeliveryReceiptRequest;
use App\Models\Notification;
use App\Services\DeliveryReceiptService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class DeliveryReceiptController extends Controller
{
    public function __construct(
        private readonly DeliveryReceiptService $service,
    ) {}

    #[OA\Post(
        path: '/notifications/{notification}/delivery-receipt',
        summary: 'Delivery receipt from the provider',
        description: 'Moves a notification sent → delivered|dropped.',
        tags: ['Webhooks'],
        parameters: [
            new OA\Parameter(name: 'notification', in: 'path', required: true, description: 'notification id', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'X-Provider-Signature', in: 'header', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['status'],
                properties: [
                    new OA\Property(property: 'status', type: 'string', enum: ['delivered', 'dropped']),
                    new OA\Property(property: 'failure_reason', type: 'string', nullable: true, maxLength: 255),
                ],
            ),
        ),
    )]
    #[OA\Response(response: 200, description: 'Status updated, or no-op for an already terminal notification')]
    #[OA\Response(response: 403, description: 'Incorrect signature (X-Provider-Signature)')]
    #[OA\Response(response: 409, description: 'Notification is not in the "sent" state')]
    #[OA\Response(response: 422, description: 'Validation error')]
    public function __invoke(DeliveryReceiptRequest $request, Notification $notification): JsonResponse
    {
        try {
            $payload = $this->service->apply(DeliveryReceiptDto::fromRequest($request, $notification));
        } catch (NotificationNotSentException $e) {
            return response()->json([
                'status' => $e->status->value,
                'message' => $e->getMessage(),
            ], JsonResponse::HTTP_CONFLICT);
        }

        return response()->json($payload);
    }
}
