<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Proposal;
use App\Models\UatResult;
use App\Models\UatItem;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'F-06: UAT', description: 'User Acceptance Testing — buat sesi, isi item, sign-off klien')]
final class UatController extends Controller
{
    use ApiResponse;

    // ──────────────────────────────────────────────────────
    //  1. GET /proposals/{id}/uat — Detail UAT + semua item
    // ──────────────────────────────────────────────────────
    #[OA\Get(
        path: '/proposals/{id}/uat',
        summary: 'Detail UAT proyek + semua item',
        description: 'Return uat_result + uat_items[]. Jika belum ada UAT, return 404.',
        security: [['bearerAuth' => []]],
        tags: ['F-06: UAT'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Data UAT'),
            new OA\Response(response: 404, description: 'Proposal atau UAT tidak ditemukan'),
        ]
    )]
    public function show(Request $request, int|string $id): JsonResponse
    {
        $proposal = Proposal::findOrFail($id);
        $user     = $request->user();

        $this->authorizeView($proposal, $user);

        $uat = UatResult::with('items')
            ->where('proposal_id', $proposal->id)
            ->first();

        if (!$uat) {
            return $this->error('UAT belum tersedia untuk proyek ini.', 404);
        }

        return $this->ok('Data UAT proyek', $this->formatUat($uat));
    }

    // ──────────────────────────────────────────────────────
    //  2. POST /proposals/{id}/uat — Buat sesi UAT + item
    // ──────────────────────────────────────────────────────
    #[OA\Post(
        path: '/proposals/{id}/uat',
        summary: 'Buat sesi UAT + item sekaligus',
        description: 'Proposal harus status Testing (id=8). One-to-one: satu proposal satu UAT. Simpan header + items dalam satu transaksi DB.',
        security: [['bearerAuth' => []]],
        tags: ['F-06: UAT'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 201, description: 'UAT berhasil dibuat'),
            new OA\Response(response: 409, description: 'UAT sudah ada'),
            new OA\Response(response: 422, description: 'Validasi gagal'),
        ]
    )]
    public function store(Request $request, int|string $id): JsonResponse
    {
        $proposal = Proposal::findOrFail($id);
        $user     = $request->user();

        $this->authorizeManage($user);

        // Proposal harus berstatus Testing (id=8)
        if ((int) $proposal->status_id !== 8) {
            return $this->error('Proposal harus berstatus Testing untuk membuat UAT.', 422);
        }

        // Cek unique constraint
        $existing = UatResult::where('proposal_id', $proposal->id)->exists();
        if ($existing) {
            return $this->error('UAT untuk proyek ini sudah ada. Gunakan endpoint PUT untuk memperbarui.', 409);
        }

        $data = $request->validate([
            'session_date'              => ['required', 'date'],
            'overall_result'            => ['required', Rule::in(['PASSED', 'FAILED', 'PARTIAL'])],
            'client_notes'              => ['nullable', 'string'],
            'items'                     => ['required', 'array', 'min:1'],
            'items.*.feature_name'      => ['required', 'string', 'max:255'],
            'items.*.test_scenario'     => ['nullable', 'string'],
            'items.*.result'            => ['required', Rule::in(['PASSED', 'FAILED', 'BUG_FOUND'])],
            'items.*.bug_severity'      => ['nullable', Rule::in(['MINOR', 'MAJOR', 'CRITICAL'])],
            'items.*.bug_description'   => ['nullable', 'string'],
            'items.*.client_feedback'   => ['nullable', 'string'],
        ]);

        $uat = DB::transaction(function () use ($data, $proposal) {
            $uat = UatResult::create([
                'proposal_id'    => $proposal->id,
                'session_date'   => $data['session_date'],
                'overall_result' => $data['overall_result'],
                'client_notes'   => $data['client_notes'] ?? null,
            ]);

            foreach ($data['items'] as $item) {
                $uat->items()->create([
                    'feature_name'    => $item['feature_name'],
                    'test_scenario'   => $item['test_scenario'] ?? null,
                    'result'          => $item['result'],
                    'bug_severity'    => $item['bug_severity'] ?? null,
                    'bug_description' => $item['bug_description'] ?? null,
                    'client_feedback' => $item['client_feedback'] ?? null,
                ]);
            }

            return $uat->load('items');
        });

        return $this->created('UAT berhasil dibuat', $this->formatUat($uat));
    }

    // ──────────────────────────────────────────────────────
    //  3. PUT /proposals/{id}/uat — Update UAT (revisi item)
    // ──────────────────────────────────────────────────────
    #[OA\Put(
        path: '/proposals/{id}/uat',
        summary: 'Update UAT (revisi item)',
        description: 'Body sama dengan POST. Replace seluruh items (delete lama, insert baru dalam satu transaksi). Hanya bisa update jika belum di-sign-off klien.',
        security: [['bearerAuth' => []]],
        tags: ['F-06: UAT'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'UAT berhasil diperbarui'),
            new OA\Response(response: 404, description: 'UAT tidak ditemukan'),
            new OA\Response(response: 409, description: 'UAT sudah di-sign-off oleh klien'),
        ]
    )]
    public function update(Request $request, int|string $id): JsonResponse
    {
        $proposal = Proposal::findOrFail($id);
        $user     = $request->user();

        $this->authorizeManage($user);

        $uat = UatResult::where('proposal_id', $proposal->id)->first();

        if (!$uat) {
            return $this->error('UAT tidak ditemukan untuk proyek ini.', 404);
        }

        // Hanya bisa update jika belum di-sign-off klien
        if ($uat->signed_off_at !== null) {
            return $this->error('UAT sudah di-sign-off oleh klien dan tidak dapat diubah.', 409);
        }

        $data = $request->validate([
            'session_date'              => ['required', 'date'],
            'overall_result'            => ['required', Rule::in(['PASSED', 'FAILED', 'PARTIAL'])],
            'client_notes'              => ['nullable', 'string'],
            'items'                     => ['required', 'array', 'min:1'],
            'items.*.feature_name'      => ['required', 'string', 'max:255'],
            'items.*.test_scenario'     => ['nullable', 'string'],
            'items.*.result'            => ['required', Rule::in(['PASSED', 'FAILED', 'BUG_FOUND'])],
            'items.*.bug_severity'      => ['nullable', Rule::in(['MINOR', 'MAJOR', 'CRITICAL'])],
            'items.*.bug_description'   => ['nullable', 'string'],
            'items.*.client_feedback'   => ['nullable', 'string'],
        ]);

        $uat = DB::transaction(function () use ($uat, $data) {
            $uat->update([
                'session_date'   => $data['session_date'],
                'overall_result' => $data['overall_result'],
                'client_notes'   => $data['client_notes'] ?? null,
            ]);

            // Hapus semua item lama, buat baru
            $uat->items()->delete();

            foreach ($data['items'] as $item) {
                $uat->items()->create([
                    'feature_name'    => $item['feature_name'],
                    'test_scenario'   => $item['test_scenario'] ?? null,
                    'result'          => $item['result'],
                    'bug_severity'    => $item['bug_severity'] ?? null,
                    'bug_description' => $item['bug_description'] ?? null,
                    'client_feedback' => $item['client_feedback'] ?? null,
                ]);
            }

            return $uat->load('items');
        });

        return $this->ok('UAT berhasil diperbarui', $this->formatUat($uat));
    }

    // ──────────────────────────────────────────────────────
    //  4. PATCH /proposals/{id}/uat/signoff — Sign-off oleh klien
    // ──────────────────────────────────────────────────────
    #[OA\Patch(
        path: '/proposals/{id}/uat/signoff',
        summary: 'Sign-off UAT oleh klien',
        description: 'Auto-set signed_off_at=now(). Jika overall_result=PASSED → update status proposal ke Handover (id=9).',
        security: [['bearerAuth' => []]],
        tags: ['F-06: UAT'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sign-off berhasil'),
            new OA\Response(response: 404, description: 'UAT tidak ditemukan'),
            new OA\Response(response: 409, description: 'UAT sudah di-sign-off sebelumnya'),
        ]
    )]
    public function signoff(Request $request, int|string $id): JsonResponse
    {
        $proposal = Proposal::findOrFail($id);
        $user     = $request->user();

        // Hanya klien pemilik proposal ini yang bisa sign-off
        if (!$user->hasRole('klien')) {
            return $this->error('Hanya klien yang dapat melakukan sign-off UAT.', 403);
        }

        if ((int) $proposal->client_id !== $user->id) {
            return $this->error('Anda bukan klien dari proyek ini.', 403);
        }

        $uat = UatResult::where('proposal_id', $proposal->id)->first();

        if (!$uat) {
            return $this->error('UAT tidak ditemukan untuk proyek ini.', 404);
        }

        if ($uat->signed_off_at !== null) {
            return $this->error('UAT sudah di-sign-off sebelumnya.', 409);
        }

        $data = $request->validate([
            'client_signoff_name' => ['required', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($uat, $data, $proposal) {
            $uat->update([
                'client_signoff_name' => $data['client_signoff_name'],
                'signed_off_at'       => now(),
            ]);

            // Jika PASSED → update proposal ke status Handover (id=9)
            if ($uat->overall_result === 'PASSED') {
                $proposal->update(['status_id' => 9]);
            }
        });

        $uat->load('items');

        return $this->ok('Sign-off UAT berhasil.', $this->formatUat($uat));
    }

    // ──────────────────────────────────────────────────────
    //  5. PATCH /proposals/{id}/status/testing — Tandai Testing
    // ──────────────────────────────────────────────────────
    #[OA\Patch(
        path: '/proposals/{id}/status/testing',
        summary: 'Tandai proyek masuk fase Testing',
        description: 'Shortcut: update status → Testing (id=8). Trigger ini supaya form UAT bisa dibuka.',
        security: [['bearerAuth' => []]],
        tags: ['F-06: UAT'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Status berhasil diperbarui ke Testing'),
        ]
    )]
    public function setTesting(Request $request, int|string $id): JsonResponse
    {
        $proposal = Proposal::findOrFail($id);
        $user     = $request->user();

        $this->authorizeManage($user);

        $proposal->update(['status_id' => 8]);

        return $this->ok('Status proyek berhasil diperbarui ke Testing.', [
            'id'        => $proposal->id,
            'status_id' => $proposal->status_id,
        ]);
    }

    // ──────────────────────────────────────────────────────
    //  Helpers
    // ──────────────────────────────────────────────────────

    /** Hanya dosen/pm yang bisa manage UAT (create/update). */
    private function authorizeManage(mixed $user): void
    {
        if (!$user->hasAnyRole(['dosen', 'pengurus_tefa'])) {
            abort(403, 'Hanya Dosen/PM yang dapat mengelola UAT.');
        }
    }

    /** Klien, dosen, dan pengurus dapat melihat UAT. */
    private function authorizeView(mixed $proposal, mixed $user): void
    {
        if ($user->hasAnyRole(['dosen', 'pengurus_tefa'])) {
            return;
        }

        if ($user->hasRole('klien') && (int) $proposal->client_id === $user->id) {
            return;
        }

        abort(403, 'Akses ditolak.');
    }

    private function formatUat(UatResult $uat): array
    {
        return [
            'id'                  => $uat->id,
            'proposal_id'         => $uat->proposal_id,
            'project_team_id'     => $uat->project_team_id,
            'session_date'        => $uat->session_date?->toDateString(),
            'overall_result'      => $uat->overall_result,
            'client_notes'        => $uat->client_notes,
            'client_signoff_name' => $uat->client_signoff_name,
            'signed_off_at'       => $uat->signed_off_at?->toIso8601String(),
            'created_at'          => $uat->created_at?->toIso8601String(),
            'updated_at'          => $uat->updated_at?->toIso8601String(),
            'uat_items'           => $uat->items->map(fn (UatItem $item) => $this->formatItem($item)),
        ];
    }

    private function formatItem(UatItem $item): array
    {
        return [
            'id'              => $item->id,
            'uat_result_id'   => $item->uat_result_id,
            'feature_name'    => $item->feature_name,
            'test_scenario'   => $item->test_scenario,
            'result'          => $item->result,
            'bug_severity'    => $item->bug_severity,
            'bug_description' => $item->bug_description,
            'client_feedback' => $item->client_feedback,
        ];
    }
}
