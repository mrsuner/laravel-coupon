<?php

declare(strict_types=1);

namespace Mrsuner\Coupon\Http\Controllers;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

/**
 * Minimal self-contained base controller.
 *
 * Mirrors the response envelope used by the boilerplate's base controller so
 * the package's admin endpoints stay visually consistent with the host API,
 * while remaining installable and testable in isolation.
 */
abstract class Controller
{
    protected function respondOk(mixed $data = null, ?string $message = null, int $code = 200): JsonResponse
    {
        return $this->envelope($data, $message, $code);
    }

    protected function respondCreated(mixed $data = null, ?string $message = null): JsonResponse
    {
        return $this->envelope($data, $message, 201);
    }

    protected function respondPaginated(LengthAwarePaginator $paginator, ?string $message = null): JsonResponse
    {
        $payload = [
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'from'         => $paginator->firstItem(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'to'           => $paginator->lastItem(),
                'total'        => $paginator->total(),
                'path'         => $paginator->path(),
            ],
            'links' => [
                'first' => $paginator->url(1),
                'last'  => $paginator->url($paginator->lastPage()),
                'prev'  => $paginator->previousPageUrl(),
                'next'  => $paginator->nextPageUrl(),
            ],
        ];

        if ($message !== null) {
            $payload['message'] = $message;
        }

        return response()->json($payload, 200);
    }

    protected function respondError(int $code, ?string $message = null, ?array $errors = null): JsonResponse
    {
        $payload = ['message' => $message ?? 'Error.'];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $code);
    }

    private function envelope(mixed $data, ?string $message, int $code): JsonResponse
    {
        $payload = [];

        if ($data !== null) {
            $payload['data'] = $data;
        }

        if ($message !== null) {
            $payload['message'] = $message;
        }

        return response()->json($payload, $code);
    }

    /**
     * Resolve a bounded per-page value (default 20, max 100).
     */
    protected function resolvePerPage(\Illuminate\Http\Request $request): int
    {
        $perPage = (int) $request->integer('per_page', 20);

        return min($perPage < 1 ? 20 : $perPage, 100);
    }

    /**
     * Emit an audit log entry when the host application provides the helper.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function audit(string $event, mixed $auditable = null, array $payload = []): void
    {
        if (function_exists('audit_log')) {
            audit_log($event, $auditable, $payload);
        }
    }
}
