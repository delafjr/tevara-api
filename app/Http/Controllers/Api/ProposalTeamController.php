<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProjectTeam;
use App\Models\Proposal;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class ProposalTeamController extends Controller
{
    #[OA\Get(
        path: '/proposals/{id}/team',
        summary: 'List anggota tim proyek (Pengurus / PM / Mahasiswa)',
        description: 'Mengambil daftar anggota tim mahasiswa pada proposal / proyek. Mahasiswa hanya bisa melihat tim proyeknya sendiri. Dosen hanya bisa melihat tim proyek yang ia bimbing sebagai PM.',
        security: [['bearerAuth' => []]],
        tags: ['F-04: Penugasan Tim'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID Proposal',
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Daftar anggota tim',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 1),
                            new OA\Property(property: 'proposal_id', type: 'integer', example: 1),
                            new OA\Property(property: 'user_id', type: 'integer', example: 5),
                            new OA\Property(property: 'role_id', type: 'integer', example: 2),
                            new OA\Property(property: 'joined_at', type: 'string', format: 'date', example: '2026-11-01'),
                            new OA\Property(
                                property: 'student',
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 5),
                                    new OA\Property(property: 'name', type: 'string', example: 'Dimas Prasetyo'),
                                    new OA\Property(property: 'email', type: 'string', example: 'dimas@mhs.polines.ac.id'),
                                    new OA\Property(property: 'nim', type: 'string', example: '22641181999'),
                                    new OA\Property(property: 'study_program', type: 'string', example: 'D3 Teknik Informatika'),
                                    new OA\Property(property: 'avatar', type: 'string', nullable: true),
                                ]
                            ),
                            new OA\Property(
                                property: 'role',
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 2),
                                    new OA\Property(property: 'name', type: 'string', example: 'Backend'),
                                ]
                            ),
                        ]
                    )
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden (bukan anggota tim / bukan PM / tidak memiliki akses)'),
            new OA\Response(response: 404, description: 'Proposal tidak ditemukan'),
        ]
    )]
    public function index(Request $request, int|string $id): JsonResponse
    {
        $proposal = Proposal::findOrFail($id);
        $user = $request->user();

        // Policy / Otorisasi per role
        if ($user->hasRole('dosen') && ! $user->hasRole('pengurus_tefa')) {
            if ($proposal->pm_id !== $user->id) {
                abort(403, 'Akses ditolak: Anda bukan PM untuk proyek ini.');
            }
        } elseif ($user->hasRole('mahasiswa') && ! $user->hasRole('pengurus_tefa')) {
            $isMember = $proposal->projectTeams()->where('user_id', $user->id)->exists();
            if (! $isMember) {
                abort(403, 'Akses ditolak: Anda bukan anggota tim proyek ini.');
            }
        }

        $teams = $proposal->projectTeams()
            ->with(['user.studentProfile', 'role'])
            ->get();

        $data = $teams->map(fn (ProjectTeam $team) => $this->formatTeamMember($team));

        return response()->json($data);
    }

    #[OA\Post(
        path: '/proposals/{id}/team',
        summary: 'Tambah anggota tim proyek (Pengurus TeFa)',
        description: 'Menugaskan mahasiswa ke dalam tim proyek dengan peran tertentu. Mendukung penambahan single (student_id & role_id) maupun batch (array members[]).',
        security: [['bearerAuth' => []]],
        tags: ['F-04: Penugasan Tim'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID Proposal',
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'student_id', type: 'integer', description: 'ID user mahasiswa (single mode)', example: 5),
                    new OA\Property(property: 'role_id', type: 'integer', description: 'ID role teknis dari project_roles (single mode)', example: 2),
                    new OA\Property(property: 'joined_at', type: 'string', format: 'date', description: 'Tanggal bergabung (default: hari ini)', example: '2026-11-01'),
                    new OA\Property(
                        property: 'members',
                        type: 'array',
                        description: 'Opsi batch: array anggota tim yang ditambahkan',
                        items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'student_id', type: 'integer', example: 5),
                                new OA\Property(property: 'role_id', type: 'integer', example: 2),
                                new OA\Property(property: 'joined_at', type: 'string', format: 'date', example: '2026-11-01'),
                            ]
                        )
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Anggota tim berhasil ditambahkan',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Anggota tim berhasil ditambahkan'),
                        new OA\Property(property: 'data', type: 'object'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden (bukan pengurus_tefa)'),
            new OA\Response(response: 404, description: 'Proposal tidak ditemukan'),
            new OA\Response(response: 422, description: 'Mahasiswa sudah terdaftar / bukan role mahasiswa / validasi gagal'),
        ]
    )]
    public function store(Request $request, int|string $id): JsonResponse
    {
        $proposal = Proposal::findOrFail($id);

        $validated = $request->validate([
            'student_id'           => 'required_without:members|integer|exists:users,id',
            'role_id'              => 'required_without:members|integer|exists:project_roles,id',
            'joined_at'            => 'nullable|date',
            'members'              => 'nullable|array|min:1',
            'members.*.student_id' => 'required_with:members|integer|exists:users,id',
            'members.*.role_id'    => 'required_with:members|integer|exists:project_roles,id',
            'members.*.joined_at'  => 'nullable|date',
        ]);

        // Cek jika mode batch
        if ($request->has('members') && is_array($request->members)) {
            $createdMembers = [];

            DB::transaction(function () use ($proposal, $validated, &$createdMembers) {
                foreach ($validated['members'] as $item) {
                    $student = User::findOrFail($item['student_id']);
                    if (! $student->hasRole('mahasiswa')) {
                        abort(422, "User {$student->name} (ID: {$item['student_id']}) bukan merupakan mahasiswa.");
                    }

                    if ($proposal->projectTeams()->where('user_id', $item['student_id'])->exists()) {
                        abort(422, "Mahasiswa {$student->name} sudah terdaftar dalam tim proyek ini.");
                    }

                    $team = $proposal->projectTeams()->create([
                        'user_id'   => $item['student_id'],
                        'role_id'   => $item['role_id'],
                        'joined_at' => $item['joined_at'] ?? now()->toDateString(),
                    ]);

                    $team->load(['user.studentProfile', 'role']);
                    $createdMembers[] = $this->formatTeamMember($team);
                }
            });

            return response()->json([
                'message' => 'Anggota tim (batch) berhasil ditambahkan',
                'data'    => $createdMembers,
            ], 201);
        }

        // Mode single
        $student = User::findOrFail($validated['student_id']);
        if (! $student->hasRole('mahasiswa')) {
            return response()->json([
                'message' => "User {$student->name} bukan merupakan mahasiswa.",
                'errors'  => [
                    'student_id' => ['User yang dipilih harus memiliki role mahasiswa.'],
                ],
            ], 422);
        }

        if ($proposal->projectTeams()->where('user_id', $validated['student_id'])->exists()) {
            return response()->json([
                'message' => "Mahasiswa {$student->name} sudah terdaftar dalam tim proyek ini.",
                'errors'  => [
                    'student_id' => ['Mahasiswa sudah terdaftar dalam tim proyek ini.'],
                ],
            ], 422);
        }

        $team = $proposal->projectTeams()->create([
            'user_id'   => $validated['student_id'],
            'role_id'   => $validated['role_id'],
            'joined_at' => $validated['joined_at'] ?? now()->toDateString(),
        ]);

        $team->load(['user.studentProfile', 'role']);

        return response()->json([
            'message' => 'Anggota tim berhasil ditambahkan',
            'data'    => $this->formatTeamMember($team),
        ], 201);
    }

    #[OA\Put(
        path: '/proposals/{id}/team/{memberId}',
        summary: 'Ganti role anggota tim (Pengurus TeFa)',
        description: 'Memperbarui peran teknis anggota tim mahasiswa (misal: UI/UX diubah menjadi Frontend).',
        security: [['bearerAuth' => []]],
        tags: ['F-04: Penugasan Tim'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID Proposal',
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
            new OA\Parameter(
                name: 'memberId',
                in: 'path',
                required: true,
                description: 'ID Project Team Member',
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['role_id'],
                properties: [
                    new OA\Property(property: 'role_id', type: 'integer', description: 'ID role teknis baru dari project_roles', example: 3),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Role anggota tim berhasil diupdate',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Role anggota tim berhasil diperbarui'),
                        new OA\Property(property: 'data', type: 'object'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden (bukan pengurus_tefa)'),
            new OA\Response(response: 404, description: 'Anggota tim / Proposal tidak ditemukan'),
            new OA\Response(response: 422, description: 'Validasi gagal'),
        ]
    )]
    public function update(Request $request, int|string $id, int|string $memberId): JsonResponse
    {
        $proposal = Proposal::findOrFail($id);
        $member   = $proposal->projectTeams()->where('id', $memberId)->firstOrFail();

        $validated = $request->validate([
            'role_id' => 'required|integer|exists:project_roles,id',
        ]);

        $member->role_id = $validated['role_id'];
        $member->save();

        $member->load(['user.studentProfile', 'role']);

        return response()->json([
            'message' => 'Role anggota tim berhasil diperbarui',
            'data'    => $this->formatTeamMember($member),
        ]);
    }

    #[OA\Delete(
        path: '/proposals/{id}/team/{memberId}',
        summary: 'Keluarkan anggota dari tim (Pengurus TeFa)',
        description: 'Menghapus mahasiswa dari keanggotaan tim proyek. Divalidasi jika mahasiswa masih memiliki task yang berstatus selain DONE, maka operasi ditolak dengan status 422.',
        security: [['bearerAuth' => []]],
        tags: ['F-04: Penugasan Tim'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID Proposal',
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
            new OA\Parameter(
                name: 'memberId',
                in: 'path',
                required: true,
                description: 'ID Project Team Member',
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Anggota tim berhasil dikeluarkan',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Anggota tim berhasil dikeluarkan dari proyek.'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden (bukan pengurus_tefa)'),
            new OA\Response(response: 404, description: 'Anggota tim / Proposal tidak ditemukan'),
            new OA\Response(response: 422, description: 'Tidak bisa dikeluarkan karena masih memiliki task yang belum selesai'),
        ]
    )]
    public function destroy(int|string $id, int|string $memberId): JsonResponse
    {
        $proposal = Proposal::findOrFail($id);
        $member   = $proposal->projectTeams()->where('id', $memberId)->firstOrFail();

        // Cek dulu apakah mahasiswa punya task yang belum selesai di proyek ini
        $hasUnfinishedTasks = $member->tasks()
            ->where('status', '!=', 'DONE')
            ->exists();

        if ($hasUnfinishedTasks) {
            return response()->json([
                'message' => 'Tidak bisa mengeluarkan anggota yang memiliki task belum selesai.',
            ], 422);
        }

        $member->delete();

        return response()->json([
            'message' => 'Anggota tim berhasil dikeluarkan dari proyek.',
        ]);
    }

    /**
     * Format standardized response structure for team member.
     *
     * @return array<string, mixed>
     */
    private function formatTeamMember(ProjectTeam $team): array
    {
        return [
            'id'          => $team->id,
            'proposal_id' => $team->proposal_id,
            'user_id'     => $team->user_id,
            'role_id'     => $team->role_id,
            'joined_at'   => $team->joined_at ? $team->joined_at->format('Y-m-d') : null,
            'student'     => [
                'id'            => $team->user?->id,
                'name'          => $team->user?->name,
                'email'         => $team->user?->email,
                'nim'           => $team->user?->studentProfile?->nim,
                'study_program' => $team->user?->studentProfile?->study_program,
                'avatar'        => $team->user?->avatar_url,
            ],
            'role'        => [
                'id'   => $team->role?->id,
                'name' => $team->role?->name,
            ],
        ];
    }
}
