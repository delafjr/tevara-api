<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProjectTeam;
use App\Models\Proposal;
use App\Models\Task;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'F-05: Tasks & Progress', description: 'Manajemen task + laporan sprint oleh mahasiswa, review oleh dosen PM')]
final class TaskController extends Controller
{
    use ApiResponse;

    // ──────────────────────────────────────────────────────
    //  1. GET /proposals/{id}/tasks  — List semua task di proyek
    // ──────────────────────────────────────────────────────
    #[OA\Get(
        path: '/proposals/{id}/tasks',
        summary: 'List semua task di proyek',
        description: 'Mengambil daftar task berdasarkan proposal. Mahasiswa auto-scope ke task miliknya saja.',
        security: [['bearerAuth' => []]],
        tags: ['F-05: Tasks & Progress'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['TODO', 'IN_PROGRESS', 'TESTING', 'DONE'])),
            new OA\Parameter(name: 'assigned_to', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'category_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Daftar task'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Proposal tidak ditemukan'),
        ]
    )]
    public function index(Request $request, int|string $id): JsonResponse
    {
        $proposal = Proposal::findOrFail($id);
        $user     = $request->user();

        $this->authorizeAccess($proposal, $user);

        $query = $proposal->tasks()->with(['projectTeam.user.studentProfile', 'category']);

        // Mahasiswa: auto-scope ke task miliknya saja
        if ($user->hasRole('mahasiswa') && !$user->hasRole('pengurus_tefa') && !$user->hasRole('dosen')) {
            $teamIds = ProjectTeam::where('proposal_id', $proposal->id)
                ->where('user_id', $user->id)
                ->pluck('id');
            $query->whereIn('project_team_id', $teamIds);
        }

        // Filter opsional
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('assigned_to')) {
            $teamIds = ProjectTeam::where('proposal_id', $proposal->id)
                ->where('user_id', (int) $request->input('assigned_to'))
                ->pluck('id');
            $query->whereIn('project_team_id', $teamIds);
        }
        if ($request->filled('category_id')) {
            $query->where('category_id', (int) $request->input('category_id'));
        }

        $tasks = $query->orderByDesc('updated_at')->get();

        return $this->ok('Daftar task proyek', $tasks->map(fn (Task $t) => $this->formatTask($t)));
    }

    // ──────────────────────────────────────────────────────
    //  2. POST /proposals/{id}/tasks  — Buat task baru
    // ──────────────────────────────────────────────────────
    #[OA\Post(
        path: '/proposals/{id}/tasks',
        summary: 'Buat task baru',
        description: 'Membuat task baru untuk proyek. Status awal: TODO, Progress: 0%, PM status: PENDING.',
        security: [['bearerAuth' => []]],
        tags: ['F-05: Tasks & Progress'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'title', type: 'string', example: 'Implementasi Payment Gateway'),
                    new OA\Property(property: 'description', type: 'string', nullable: true),
                    new OA\Property(property: 'category_id', type: 'integer', nullable: true),
                    new OA\Property(property: 'assigned_student_id', type: 'integer', nullable: true, description: 'User ID mahasiswa (harus anggota tim)'),
                    new OA\Property(property: 'priority', type: 'string', enum: ['LOW', 'MEDIUM', 'HIGH', 'CRITICAL']),
                    new OA\Property(property: 'due_date', type: 'string', format: 'date', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Task berhasil dibuat'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Proposal tidak ditemukan'),
            new OA\Response(response: 422, description: 'Validasi gagal'),
        ]
    )]
    public function store(Request $request, int|string $id): JsonResponse
    {
        $proposal = Proposal::findOrFail($id);
        $user     = $request->user();

        // Hanya dosen (PM) & pengurus_tefa yang bisa buat task
        if (!$user->hasRole('dosen') && !$user->hasRole('pengurus_tefa')) {
            abort(403, 'Akses ditolak: Hanya Dosen PM atau Pengurus TeFa yang bisa membuat task.');
        }

        // Jika dosen, harus jadi PM proyek ini
        if ($user->hasRole('dosen') && !$user->hasRole('pengurus_tefa')) {
            if ($proposal->pm_id !== $user->id) {
                abort(403, 'Akses ditolak: Anda bukan PM untuk proyek ini.');
            }
        }

        $validated = $request->validate([
            'title'               => ['required', 'string', 'max:255'],
            'description'         => ['nullable', 'string'],
            'category_id'         => ['nullable', 'integer', 'exists:task_categories,id'],
            'assigned_student_id' => ['nullable', 'integer', 'exists:users,id'],
            'priority'            => ['nullable', 'string', Rule::in(['LOW', 'MEDIUM', 'HIGH', 'CRITICAL'])],
            'due_date'            => ['nullable', 'date'],
        ]);

        // Resolve project_team_id dari assigned_student_id
        $projectTeamId = null;
        if (!empty($validated['assigned_student_id'])) {
            $teamMember = ProjectTeam::where('proposal_id', $proposal->id)
                ->where('user_id', (int) $validated['assigned_student_id'])
                ->first();

            if (!$teamMember) {
                return $this->error(
                    'Mahasiswa yang ditunjuk bukan anggota tim proyek ini.',
                    422
                );
            }
            $projectTeamId = $teamMember->id;
        }

        $task = Task::create([
            'proposal_id'         => $proposal->id,
            'project_team_id'     => $projectTeamId,
            'category_id'         => $validated['category_id'] ?? null,
            'title'               => $validated['title'],
            'description'         => $validated['description'] ?? null,
            'priority'            => $validated['priority'] ?? 'MEDIUM',
            'due_date'            => $validated['due_date'] ?? null,
            'status'              => 'TODO',
            'progress_percentage' => 0,
            'pm_status'           => 'PENDING',
        ]);

        $task->load(['projectTeam.user.studentProfile', 'category']);

        return $this->created('Task berhasil dibuat', $this->formatTask($task));
    }

    // ──────────────────────────────────────────────────────
    //  3. GET /proposals/{id}/tasks/{taskId}  — Detail task
    // ──────────────────────────────────────────────────────
    #[OA\Get(
        path: '/proposals/{id}/tasks/{taskId}',
        summary: 'Detail satu task',
        description: 'Mahasiswa hanya bisa akses task miliknya. Include: assignee, category, pm_notes, pm_status.',
        security: [['bearerAuth' => []]],
        tags: ['F-05: Tasks & Progress'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'taskId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Detail task'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Task tidak ditemukan'),
        ]
    )]
    public function show(Request $request, int|string $id, int|string $taskId): JsonResponse
    {
        $proposal = Proposal::findOrFail($id);
        $user     = $request->user();

        $this->authorizeAccess($proposal, $user);

        $task = Task::where('proposal_id', $proposal->id)
            ->with(['projectTeam.user.studentProfile', 'category'])
            ->findOrFail($taskId);

        // Mahasiswa: hanya bisa akses task miliknya
        if ($user->hasRole('mahasiswa') && !$user->hasRole('pengurus_tefa') && !$user->hasRole('dosen')) {
            $isOwner = $task->projectTeam && $task->projectTeam->user_id === $user->id;
            if (!$isOwner) {
                abort(403, 'Akses ditolak: Anda hanya bisa mengakses task milik Anda.');
            }
        }

        return $this->ok('Detail task', $this->formatTask($task));
    }

    // ──────────────────────────────────────────────────────
    //  4. PATCH /proposals/{id}/tasks/{taskId}/progress
    //     Submit progress report (F-05 Mahasiswa)
    // ──────────────────────────────────────────────────────
    #[OA\Patch(
        path: '/proposals/{id}/tasks/{taskId}/progress',
        summary: 'Submit progress report (Mahasiswa)',
        description: 'Mahasiswa submit laporan progres. Reset pm_status → PENDING setiap update.',
        security: [['bearerAuth' => []]],
        tags: ['F-05: Tasks & Progress'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'taskId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: 'achieved_summary', type: 'string', description: 'Pencapaian minggu ini'),
                        new OA\Property(property: 'blockers_summary', type: 'string', nullable: true, description: 'Kendala / blocker'),
                        new OA\Property(property: 'progress_percentage', type: 'number', description: '0.00 - 100.00'),
                        new OA\Property(property: 'status', type: 'string', enum: ['TODO', 'IN_PROGRESS', 'TESTING', 'DONE']),
                        new OA\Property(property: 'report_attachment', type: 'string', format: 'binary', description: 'Max 10MB'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Progress berhasil diperbarui'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Task tidak ditemukan'),
            new OA\Response(response: 422, description: 'Validasi gagal'),
        ]
    )]
    public function updateProgress(Request $request, int|string $id, int|string $taskId): JsonResponse
    {
        $proposal = Proposal::findOrFail($id);
        $user     = $request->user();

        // Hanya mahasiswa
        if (!$user->hasRole('mahasiswa')) {
            abort(403, 'Akses ditolak: Hanya mahasiswa yang bisa submit progress report.');
        }

        $task = Task::where('proposal_id', $proposal->id)->findOrFail($taskId);

        // Hanya bisa update task miliknya
        $isOwner = $task->projectTeam && $task->projectTeam->user_id === $user->id;
        if (!$isOwner) {
            abort(403, 'Akses ditolak: Anda hanya bisa mengupdate task milik Anda.');
        }

        $validated = $request->validate([
            'achieved_summary'    => ['required', 'string'],
            'blockers_summary'    => ['nullable', 'string'],
            'progress_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'status'              => ['required', 'string', Rule::in(['TODO', 'IN_PROGRESS', 'TESTING', 'DONE'])],
            'report_attachment'   => ['nullable', 'file', 'max:10240'], // Max 10MB
        ]);

        // Handle file upload
        $attachmentUrl = $task->report_attachment_url;
        if ($request->hasFile('report_attachment')) {
            // Hapus file lama jika ada
            if ($attachmentUrl && Storage::disk('public')->exists($attachmentUrl)) {
                Storage::disk('public')->delete($attachmentUrl);
            }
            $attachmentUrl = $request->file('report_attachment')
                ->store('task-attachments/' . $proposal->id, 'public');
        }

        $task->update([
            'achieved_summary'      => $validated['achieved_summary'],
            'blockers_summary'      => $validated['blockers_summary'] ?? null,
            'progress_percentage'   => $validated['progress_percentage'],
            'status'                => $validated['status'],
            'report_attachment_url' => $attachmentUrl,
            'pm_status'             => 'PENDING', // Reset setiap mahasiswa update
        ]);

        $task->load(['projectTeam.user.studentProfile', 'category']);

        return $this->ok('Progress report berhasil diperbarui', $this->formatTask($task));
    }

    // ──────────────────────────────────────────────────────
    //  5. PATCH /proposals/{id}/tasks/{taskId}/review
    //     Review progress report (F-05 Dosen PM)
    // ──────────────────────────────────────────────────────
    #[OA\Patch(
        path: '/proposals/{id}/tasks/{taskId}/review',
        summary: 'Review progress report (Dosen PM)',
        description: 'Dosen PM mereview laporan progres mahasiswa. Auto-set pm_reviewed_at = now().',
        security: [['bearerAuth' => []]],
        tags: ['F-05: Tasks & Progress'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'taskId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'pm_notes', type: 'string', nullable: true, description: 'Catatan / feedback'),
                    new OA\Property(property: 'pm_status', type: 'string', enum: ['APPROVED', 'NEEDS_IMPROVEMENT'], description: 'Keputusan review'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Review berhasil disimpan'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Task tidak ditemukan'),
            new OA\Response(response: 422, description: 'Validasi gagal'),
        ]
    )]
    public function review(Request $request, int|string $id, int|string $taskId): JsonResponse
    {
        $proposal = Proposal::findOrFail($id);
        $user     = $request->user();

        // Hanya dosen yang merupakan PM proyek ini
        if (!$user->hasRole('dosen')) {
            abort(403, 'Akses ditolak: Hanya Dosen PM yang bisa mereview task.');
        }

        if ($proposal->pm_id !== $user->id) {
            abort(403, 'Akses ditolak: Anda bukan PM untuk proyek ini.');
        }

        $task = Task::where('proposal_id', $proposal->id)->findOrFail($taskId);

        $validated = $request->validate([
            'pm_notes'  => ['nullable', 'string'],
            'pm_status' => ['required', 'string', Rule::in(['APPROVED', 'NEEDS_IMPROVEMENT'])],
        ]);

        $task->update([
            'pm_notes'       => $validated['pm_notes'] ?? null,
            'pm_status'      => $validated['pm_status'],
            'pm_reviewed_at' => now(),
        ]);

        $task->load(['projectTeam.user.studentProfile', 'category']);

        return $this->ok('Review task berhasil disimpan', $this->formatTask($task));
    }

    // ──────────────────────────────────────────────────────
    //  6. PUT /proposals/{id}/tasks/{taskId}
    //     Edit metadata task (judul, assignee, due date, dll)
    // ──────────────────────────────────────────────────────
    #[OA\Put(
        path: '/proposals/{id}/tasks/{taskId}',
        summary: 'Edit metadata task',
        description: 'Dosen PM atau Pengurus TeFa mengubah metadata task (judul, assignee, due date, dll).',
        security: [['bearerAuth' => []]],
        tags: ['F-05: Tasks & Progress'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'taskId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'title', type: 'string', nullable: true),
                    new OA\Property(property: 'description', type: 'string', nullable: true),
                    new OA\Property(property: 'category_id', type: 'integer', nullable: true),
                    new OA\Property(property: 'assigned_student_id', type: 'integer', nullable: true),
                    new OA\Property(property: 'priority', type: 'string', enum: ['LOW', 'MEDIUM', 'HIGH', 'CRITICAL']),
                    new OA\Property(property: 'due_date', type: 'string', format: 'date', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Task berhasil diperbarui'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Task tidak ditemukan'),
            new OA\Response(response: 422, description: 'Validasi gagal'),
        ]
    )]
    public function update(Request $request, int|string $id, int|string $taskId): JsonResponse
    {
        $proposal = Proposal::findOrFail($id);
        $user     = $request->user();

        // Hanya dosen (PM) & pengurus_tefa
        if (!$user->hasRole('dosen') && !$user->hasRole('pengurus_tefa')) {
            abort(403, 'Akses ditolak: Hanya Dosen PM atau Pengurus TeFa yang bisa mengedit task.');
        }

        // Jika dosen, harus jadi PM proyek ini
        if ($user->hasRole('dosen') && !$user->hasRole('pengurus_tefa')) {
            if ($proposal->pm_id !== $user->id) {
                abort(403, 'Akses ditolak: Anda bukan PM untuk proyek ini.');
            }
        }

        $task = Task::where('proposal_id', $proposal->id)->findOrFail($taskId);

        $validated = $request->validate([
            'title'               => ['nullable', 'string', 'max:255'],
            'description'         => ['nullable', 'string'],
            'category_id'         => ['nullable', 'integer', 'exists:task_categories,id'],
            'assigned_student_id' => ['nullable', 'integer', 'exists:users,id'],
            'priority'            => ['nullable', 'string', Rule::in(['LOW', 'MEDIUM', 'HIGH', 'CRITICAL'])],
            'due_date'            => ['nullable', 'date'],
        ]);

        $updateData = [];

        if (array_key_exists('title', $validated) && $validated['title'] !== null) {
            $updateData['title'] = $validated['title'];
        }
        if (array_key_exists('description', $validated)) {
            $updateData['description'] = $validated['description'];
        }
        if (array_key_exists('category_id', $validated)) {
            $updateData['category_id'] = $validated['category_id'];
        }
        if (array_key_exists('priority', $validated) && $validated['priority'] !== null) {
            $updateData['priority'] = $validated['priority'];
        }
        if (array_key_exists('due_date', $validated)) {
            $updateData['due_date'] = $validated['due_date'];
        }

        // Resolve assigned_student_id ke project_team_id
        if (array_key_exists('assigned_student_id', $validated)) {
            if ($validated['assigned_student_id'] !== null) {
                $teamMember = ProjectTeam::where('proposal_id', $proposal->id)
                    ->where('user_id', (int) $validated['assigned_student_id'])
                    ->first();

                if (!$teamMember) {
                    return $this->error(
                        'Mahasiswa yang ditunjuk bukan anggota tim proyek ini.',
                        422
                    );
                }
                $updateData['project_team_id'] = $teamMember->id;
            } else {
                $updateData['project_team_id'] = null;
            }
        }

        if (!empty($updateData)) {
            $task->update($updateData);
        }

        $task->load(['projectTeam.user.studentProfile', 'category']);

        return $this->ok('Task berhasil diperbarui', $this->formatTask($task));
    }

    // ──────────────────────────────────────────────────────
    //  7. DELETE /proposals/{id}/tasks/{taskId}  — Hapus task
    // ──────────────────────────────────────────────────────
    #[OA\Delete(
        path: '/proposals/{id}/tasks/{taskId}',
        summary: 'Hapus task',
        description: 'Hanya bisa hapus task yang masih TODO atau belum punya progress report (progress_percentage = 0).',
        security: [['bearerAuth' => []]],
        tags: ['F-05: Tasks & Progress'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'taskId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Task berhasil dihapus'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Task tidak ditemukan'),
            new OA\Response(response: 422, description: 'Task sudah memiliki progress dan tidak bisa dihapus'),
        ]
    )]
    public function destroy(Request $request, int|string $id, int|string $taskId): JsonResponse
    {
        $proposal = Proposal::findOrFail($id);
        $user     = $request->user();

        // Hanya dosen (PM) & pengurus_tefa
        if (!$user->hasRole('dosen') && !$user->hasRole('pengurus_tefa')) {
            abort(403, 'Akses ditolak: Hanya Dosen PM atau Pengurus TeFa yang bisa menghapus task.');
        }

        // Jika dosen, harus jadi PM proyek ini
        if ($user->hasRole('dosen') && !$user->hasRole('pengurus_tefa')) {
            if ($proposal->pm_id !== $user->id) {
                abort(403, 'Akses ditolak: Anda bukan PM untuk proyek ini.');
            }
        }

        $task = Task::where('proposal_id', $proposal->id)->findOrFail($taskId);

        // Validasi: tidak bisa hapus task yang sudah ada progress
        if ($task->progress_percentage > 0) {
            return $this->error(
                'Tidak bisa dihapus: Task sudah memiliki progress report (progress_percentage > 0). Hanya task dengan status TODO dan belum ada progress yang bisa dihapus.',
                422
            );
        }

        // Hapus file attachment jika ada
        if ($task->report_attachment_url && Storage::disk('public')->exists($task->report_attachment_url)) {
            Storage::disk('public')->delete($task->report_attachment_url);
        }

        $task->delete();

        return $this->ok('Task berhasil dihapus');
    }

    // ──────────────────────────────────────────────────────
    //  8. GET /proposals/{id}/tasks/summary  — Ringkasan stats
    // ──────────────────────────────────────────────────────
    #[OA\Get(
        path: '/proposals/{id}/tasks/summary',
        summary: 'Ringkasan statistik task proyek',
        description: 'Mengembalikan hitungan task per status dan review untuk stat cards di frontend.',
        security: [['bearerAuth' => []]],
        tags: ['F-05: Tasks & Progress'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Ringkasan task'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Proposal tidak ditemukan'),
        ]
    )]
    public function summary(Request $request, int|string $id): JsonResponse
    {
        $proposal = Proposal::findOrFail($id);
        $user     = $request->user();

        $this->authorizeAccess($proposal, $user);

        $baseQuery = $proposal->tasks();

        // Mahasiswa: scope ke task miliknya
        if ($user->hasRole('mahasiswa') && !$user->hasRole('pengurus_tefa') && !$user->hasRole('dosen')) {
            $teamIds = ProjectTeam::where('proposal_id', $proposal->id)
                ->where('user_id', $user->id)
                ->pluck('id');
            $baseQuery->whereIn('project_team_id', $teamIds);
        }

        $tasks = $baseQuery->get();

        $summary = [
            'total'              => $tasks->count(),
            'todo'               => $tasks->where('status', 'TODO')->count(),
            'in_progress'        => $tasks->where('status', 'IN_PROGRESS')->count(),
            'testing'            => $tasks->where('status', 'TESTING')->count(),
            'done'               => $tasks->where('status', 'DONE')->count(),
            'pending_review'     => $tasks->where('pm_status', 'PENDING')->count(),
            'approved'           => $tasks->where('pm_status', 'APPROVED')->count(),
            'needs_improvement'  => $tasks->where('pm_status', 'NEEDS_IMPROVEMENT')->count(),
            'overall_progress'   => $tasks->count() > 0
                ? round($tasks->avg('progress_percentage'), 2)
                : 0,
        ];

        return $this->ok('Ringkasan task proyek', $summary);
    }

    // ──────────────────────────────────────────────────────
    //  PRIVATE: Authorization & Formatting helpers
    // ──────────────────────────────────────────────────────

    /**
     * Check that the authenticated user has access to this proposal's tasks.
     */
    private function authorizeAccess(Proposal $proposal, $user): void
    {
        // Pengurus TeFa: akses semua
        if ($user->hasRole('pengurus_tefa')) {
            return;
        }

        // Dosen: harus PM proyek ini
        if ($user->hasRole('dosen')) {
            if ($proposal->pm_id !== $user->id) {
                abort(403, 'Akses ditolak: Anda bukan PM untuk proyek ini.');
            }
            return;
        }

        // Mahasiswa: harus anggota tim proyek ini
        if ($user->hasRole('mahasiswa')) {
            $isMember = ProjectTeam::where('proposal_id', $proposal->id)
                ->where('user_id', $user->id)
                ->exists();
            if (!$isMember) {
                abort(403, 'Akses ditolak: Anda bukan anggota tim proyek ini.');
            }
            return;
        }

        abort(403, 'Akses ditolak.');
    }

    /**
     * Format a Task model into the standard API response shape.
     */
    private function formatTask(Task $task): array
    {
        $assignee = null;
        if ($task->projectTeam && $task->projectTeam->user) {
            $user    = $task->projectTeam->user;
            $profile = $user->studentProfile;
            $assignee = [
                'id'            => $user->id,
                'name'          => $user->name,
                'email'         => $user->email,
                'nim'           => $profile?->nim ?? null,
                'study_program' => $profile?->study_program ?? null,
            ];
        }

        return [
            'id'                    => $task->id,
            'proposal_id'           => $task->proposal_id,
            'project_team_id'       => $task->project_team_id,
            'title'                 => $task->title,
            'description'           => $task->description,
            'priority'              => $task->priority,
            'due_date'              => $task->due_date?->toDateString(),
            'status'                => $task->status,
            'progress_percentage'   => (float) $task->progress_percentage,
            'achieved_summary'      => $task->achieved_summary,
            'blockers_summary'      => $task->blockers_summary,
            'report_attachment_url' => $task->report_attachment_url
                ? asset('storage/' . $task->report_attachment_url)
                : null,
            'pm_notes'              => $task->pm_notes,
            'pm_status'             => $task->pm_status,
            'pm_reviewed_at'        => $task->pm_reviewed_at?->toIso8601String(),
            'category'              => $task->category ? [
                'id'   => $task->category->id,
                'name' => $task->category->name,
            ] : null,
            'assignee'              => $assignee,
            'created_at'            => $task->created_at?->toIso8601String(),
            'updated_at'            => $task->updated_at?->toIso8601String(),
        ];
    }
}
