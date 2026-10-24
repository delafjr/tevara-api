<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProjectRole;
use App\Models\ProposalCategory;
use App\Models\ProposalStatus;
use App\Models\TaskCategory;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class MasterDataController extends Controller
{
    #[OA\Get(
        path: '/master/project-roles',
        summary: 'List role tim mahasiswa (F-04)',
        description: 'Mengambil daftar role teknis untuk tim mahasiswa (UI/UX, Frontend, Backend, Fullstack, QA Tester).',
        security: [['bearerAuth' => []]],
        tags: ['Master Data'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Daftar project roles',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'name', type: 'string', example: 'Frontend'),
                            ]
                        )),
                    ]
                )
            ),
        ]
    )]
    public function projectRoles(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => ProjectRole::all(['id', 'name']),
        ]);
    }

    #[OA\Get(
        path: '/master/proposal-categories',
        summary: 'List kategori proposal (F-01)',
        description: 'Mengambil daftar kategori proposal (Web, Mobile, Desktop, IoT, Other).',
        security: [['bearerAuth' => []]],
        tags: ['Master Data'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Daftar kategori proposal',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'name', type: 'string', example: 'Web'),
                            ]
                        )),
                    ]
                )
            ),
        ]
    )]
    public function proposalCategories(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => ProposalCategory::all(['id', 'name']),
        ]);
    }

    #[OA\Get(
        path: '/master/proposal-statuses',
        summary: 'List status proposal (F-01 / Timeline)',
        description: 'Mengambil status proposal terurut.',
        security: [['bearerAuth' => []]],
        tags: ['Master Data'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Daftar status proposal',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'name', type: 'string', example: 'Draft'),
                            ]
                        )),
                    ]
                )
            ),
        ]
    )]
    public function proposalStatuses(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => ProposalStatus::orderBy('id')->get(['id', 'name']),
        ]);
    }

    #[OA\Get(
        path: '/master/task-categories',
        summary: 'List kategori task (F-05)',
        description: 'Mengambil kategori task.',
        security: [['bearerAuth' => []]],
        tags: ['Master Data'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Daftar kategori task',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'name', type: 'string', example: 'Frontend'),
                            ]
                        )),
                    ]
                )
            ),
        ]
    )]
    public function taskCategories(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => TaskCategory::all(['id', 'name']),
        ]);
    }
}
