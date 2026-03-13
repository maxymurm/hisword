<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;

abstract class BaseApiController extends Controller
{
    /**
     * Return a standardized success response.
     */
    protected function success(mixed $data = null, string $message = 'OK', int $status = 200): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], $status);
    }

    /**
     * Return a standardized error response.
     */
    protected function error(string $message, int $status = 400, mixed $errors = null): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors'  => $errors,
        ], $status);
    }

    /**
     * Return a standardized paginated response.
     */
    protected function paginated($query, int $perPage = 25): \Illuminate\Http\JsonResponse
    {
        $paginator = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => $paginator->items(),
            'meta'    => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}
