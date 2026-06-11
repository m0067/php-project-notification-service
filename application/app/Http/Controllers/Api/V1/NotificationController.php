<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Dto\BulkNotificationDto;
use App\Exceptions\RequestAlreadyProcessingException;
use App\Http\Controllers\Controller;
use App\Http\Requests\BulkNotificationRequest;
use App\Http\Requests\SubscriberHistoryRequest;
use App\Http\Resources\NotificationCollection;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationService $service,
    ) {}

    #[OA\Post(
        path: '/notifications/bulk',
        summary: 'Bulk notification dispatch',
        description: 'Accepts a request, stores notifications in the queued status and pushes '
            .'them to a priority queue. Idempotent by the Request-Id header.',
        tags: ['Notifications'],
        parameters: [
            new OA\Parameter(name: 'Request-Id', in: 'header', required: true, description: 'Request idempotency key (UUID).', schema: new OA\Schema(type: 'string', format: 'uuid'), example: '3f2504e0-4f89-41d3-9a0c-0305e82c3301'),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['channel', 'message', 'recipients'],
                properties: [
                    new OA\Property(property: 'channel', type: 'string', enum: ['sms', 'email']),
                    new OA\Property(property: 'message', type: 'string', maxLength: 1000, example: 'Your access code is 0451'),
                    new OA\Property(property: 'recipients', type: 'array', items: new OA\Items(type: 'string'), minItems: 1, example: ['1001', '1002']),
                    new OA\Property(property: 'is_transactional', type: 'boolean', default: false, description: 'true → high-priority queue'),
                ],
            ),
        ),
    )]
    #[OA\Response(
        response: 202,
        description: 'Request accepted',
        content: new OA\JsonContent(properties: [
            new OA\Property(property: 'accepted', type: 'integer', example: 2),
            new OA\Property(property: 'notification_ids', type: 'array', items: new OA\Items(type: 'integer'), example: [1, 2]),
        ]),
    )]
    #[OA\Response(response: 409, description: 'A request with this Request-Id is already being processed')]
    #[OA\Response(response: 422, description: 'Validation error')]
    public function store(BulkNotificationRequest $request): JsonResponse
    {
        try {
            $response = $this->service->processBulk(BulkNotificationDto::fromRequest($request));
        } catch (RequestAlreadyProcessingException $e) {
            return response()->json(['message' => $e->getMessage()], JsonResponse::HTTP_CONFLICT);
        }

        return response()->json($response, JsonResponse::HTTP_ACCEPTED);
    }

    #[OA\Get(
        path: '/notifications/subscriber/{subscriber_id}',
        summary: 'Subscriber notification history',
        tags: ['Notifications'],
        parameters: [
            new OA\Parameter(name: 'subscriber_id', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: '1001'),
            new OA\Parameter(name: 'cursor', in: 'query', required: false, description: 'Pagination cursor', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, description: 'Items per page (1–100)', schema: new OA\Schema(type: 'integer', minimum: SubscriberHistoryRequest::MIN_PER_PAGE, maximum: SubscriberHistoryRequest::MAX_PER_PAGE, default: SubscriberHistoryRequest::DEFAULT_PER_PAGE)),
        ],
    )]
    #[OA\Response(
        response: 200,
        description: 'Subscriber notifications',
        content: new OA\JsonContent(properties: [
            new OA\Property(property: 'data', type: 'array', items: new OA\Items(
                properties: [
                    new OA\Property(property: 'id', type: 'integer'),
                    new OA\Property(property: 'subscriber_id', type: 'string'),
                    new OA\Property(property: 'channel', type: 'string', enum: ['sms', 'email']),
                    new OA\Property(property: 'message_text', type: 'string'),
                    new OA\Property(property: 'status', type: 'string', enum: ['queued', 'sent', 'delivered', 'dropped']),
                    new OA\Property(property: 'is_transactional', type: 'boolean'),
                    new OA\Property(property: 'provider_message_id', type: 'string', nullable: true),
                    new OA\Property(property: 'failure_reason', type: 'string', nullable: true),
                    new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
                    new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
                ],
                type: 'object',
            )),
        ]),
    )]
    #[OA\Response(response: 422, description: 'Validation error')]
    public function history(SubscriberHistoryRequest $request, string $subscriberId): NotificationCollection
    {
        return $this->service->history(
            $subscriberId,
            $request->perPage(),
            $request->query('cursor'),
        );
    }
}
