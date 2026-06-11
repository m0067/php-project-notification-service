<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Webhook\ProviderSignatureService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyProviderSignature
{
    public function __construct(private readonly ProviderSignatureService $signatures) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->signatures->verify($request->getContent(), $request->header('X-Provider-Signature'))) {
            abort(Response::HTTP_FORBIDDEN, 'Incorrect provider signature');
        }

        return $next($request);
    }
}
