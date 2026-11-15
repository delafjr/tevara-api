<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

final class NotificationController extends Controller
{
    use ApiResponse;

    #[OA\Get(
        path: '/notifications',
        summary: 'List notifikasi user',
        description: 'Mengambil daftar notifikasi milik user yang sedang login (maksimal 100 terbaru). Gunakan `unread=1` untuk hanya menampilkan yang belum dibaca.',
        security: [['bearerAuth' => []]],
        tags: ['Notifications'],
        parameters: [
            new OA\Parameter(name: 'unread', in: 'query', required: false, description: 'Filter hanya yang belum dibaca (1/true)', schema: new OA\Schema(type: 'boolean', example: true)),
            new OA\Parameter(name: 'proposal_id', in: 'query', required: false, description: 'Filter berdasarkan proposal', schema: new OA\Schema(type: 'integer', example: 9)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Daftar notifikasi',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Daftar notifikasi'),
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 12),
                                    new OA\Property(property: 'type', type: 'string', example: 'payment.proof_uploaded'),
                                    new OA\Property(property: 'title', type: 'string', example: 'Bukti Pembayaran Baru'),
                                    new OA\Property(property: 'message', type: 'string', example: 'PT. Maju Bersama Sentosa mengunggah bukti Termin 1 (Rp 7.800.000) untuk proposal PRSL-2026-372.'),
                                    new OA\Property(property: 'proposal_id', type: 'integer', nullable: true, example: 9),
                                    new OA\Property(property: 'is_read', type: 'boolean', example: false),
                                    new OA\Property(property: 'read_at', type: 'string', format: 'date-time', nullable: true, example: null),
                                    new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2026-11-08T10:00:00+07:00'),
                                ]
                            )
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $query = Notification::where('user_id', $request->user()->id);

        if ($request->boolean('unread')) {
            $query->whereNull('read_at');
        }

        if ($request->filled('proposal_id')) {
            $query->where('proposal_id', (int) $request->input('proposal_id'));
        }

        $notifications = $query->latest()->limit(100)->get();

        return $this->ok(
            'Daftar notifikasi',
            $notifications->map(fn (Notification $notification) => $this->format($notification))
        );
    }

    #[OA\Get(
        path: '/notifications/unread-count',
        summary: 'Jumlah notifikasi belum dibaca',
        description: 'Mengambil jumlah notifikasi milik user yang belum dibaca. Cocok untuk badge di navbar.',
        security: [['bearerAuth' => []]],
        tags: ['Notifications'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Jumlah notifikasi belum dibaca',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Jumlah notifikasi belum dibaca'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'unread_count', type: 'integer', example: 3),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function unreadCount(Request $request): JsonResponse
    {
        $count = Notification::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->count();

        return $this->ok('Jumlah notifikasi belum dibaca', ['unread_count' => $count]);
    }

    #[OA\Patch(
        path: '/notifications/{id}/read',
        summary: 'Tandai satu notifikasi sudah dibaca',
        description: 'Menandai notifikasi milik user sebagai sudah dibaca. Idempotent: notifikasi yang sudah dibaca tidak berubah.',
        security: [['bearerAuth' => []]],
        tags: ['Notifications'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 12)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Notifikasi ditandai sudah dibaca',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Notifikasi ditandai sudah dibaca'),
                        new OA\Property(property: 'data', type: 'object'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Notifikasi tidak ditemukan'),
        ]
    )]
    public function markAsRead(Request $request, int|string $id): JsonResponse
    {
        $notification = Notification::where('user_id', $request->user()->id)->findOrFail($id);

        if ($notification->read_at === null) {
            $notification->update(['read_at' => now()]);
        }

        return $this->ok('Notifikasi ditandai sudah dibaca', $this->format($notification));
    }

    #[OA\Patch(
        path: '/notifications/read-all',
        summary: 'Tandai semua notifikasi sudah dibaca',
        description: 'Menandai seluruh notifikasi user yang belum dibaca sebagai sudah dibaca.',
        security: [['bearerAuth' => []]],
        tags: ['Notifications'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Semua notifikasi ditandai sudah dibaca',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Semua notifikasi ditandai sudah dibaca'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'updated', type: 'integer', example: 3),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function markAllAsRead(Request $request): JsonResponse
    {
        $updated = Notification::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return $this->ok('Semua notifikasi ditandai sudah dibaca', ['updated' => $updated]);
    }

    /**
     * @return array<string, mixed>
     */
    private function format(Notification $notification): array
    {
        return [
            'id'          => $notification->id,
            'type'        => $notification->type,
            'title'       => $notification->title,
            'message'     => $notification->message,
            'proposal_id' => $notification->proposal_id,
            'is_read'     => $notification->isRead(),
            'read_at'     => $notification->read_at?->toIso8601String(),
            'created_at'  => $notification->created_at?->toIso8601String(),
        ];
    }
}
