<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

final class DashboardController extends Controller
{
    use ApiResponse;

    #[OA\Get(
        path: '/mahasiswa/dashboard',
        summary: 'Dashboard Mahasiswa',
        description: 'Endpoint dashboard khusus role mahasiswa',
        security: [['bearerAuth' => []]],
        tags: ['Dashboard'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Akses berhasil',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Mahasiswa dashboard'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'role', type: 'string', example: 'mahasiswa'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden (User bukan role mahasiswa)'),
        ]
    )]
    public function mahasiswa(): JsonResponse
    {
        return $this->ok(
            message: 'Mahasiswa dashboard',
            data: ['role' => 'mahasiswa']
        );
    }

    #[OA\Get(
        path: '/dosen/dashboard',
        summary: 'Dashboard Dosen',
        description: 'Endpoint dashboard khusus role dosen',
        security: [['bearerAuth' => []]],
        tags: ['Dashboard'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Akses berhasil',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Dosen dashboard'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'role', type: 'string', example: 'dosen'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden (User bukan role dosen)'),
        ]
    )]
    public function dosen(): JsonResponse
    {
        return $this->ok(
            message: 'Dosen dashboard',
            data: ['role' => 'dosen']
        );
    }
}
