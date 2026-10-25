<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ProjectTeam;
use App\Models\Proposal;
use App\Models\Task;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

final class DashboardController extends Controller
{
    use ApiResponse;

    #[OA\Get(
        path: '/mahasiswa/dashboard',
        summary: 'Dashboard Mahasiswa',
        description: 'Endpoint ringkasan dashboard khusus role mahasiswa',
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
                                new OA\Property(property: 'active_projects', type: 'integer', example: 0),
                                new OA\Property(property: 'pending_tasks', type: 'integer', example: 0),
                                new OA\Property(property: 'completed_projects', type: 'integer', example: 1),
                                new OA\Property(
                                    property: 'tasks',
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'id', type: 'integer', example: 1),
                                            new OA\Property(property: 'title', type: 'string', example: 'Setup project Laravel & Vue.js'),
                                            new OA\Property(property: 'status', type: 'string', example: 'DONE'),
                                            new OA\Property(property: 'priority', type: 'string', example: 'HIGH'),
                                            new OA\Property(property: 'due_date', type: 'string', format: 'date', nullable: true, example: '2025-11-30'),
                                            new OA\Property(property: 'progress_percentage', type: 'number', example: 100),
                                            new OA\Property(
                                                property: 'proposal',
                                                type: 'object',
                                                properties: [
                                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                                    new OA\Property(property: 'title', type: 'string', example: 'Sistem Informasi Manajemen Teaching Factory'),
                                                ]
                                            ),
                                        ]
                                    )
                                ),
                                new OA\Property(
                                    property: 'projects',
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'id', type: 'integer', example: 1),
                                            new OA\Property(property: 'title', type: 'string', example: 'Sistem Informasi Manajemen Teaching Factory'),
                                            new OA\Property(property: 'client_name', type: 'string', example: 'PT. Maju Bersama Sentosa'),
                                            new OA\Property(property: 'role', type: 'string', example: 'Fullstack'),
                                            new OA\Property(property: 'status', type: 'string', example: 'Completed'),
                                            new OA\Property(property: 'progress', type: 'number', example: 100),
                                            new OA\Property(property: 'deadline', type: 'string', format: 'date', nullable: true, example: '2025-12-15'),
                                        ]
                                    )
                                ),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden (User bukan role mahasiswa)'),
        ]
    )]
    public function mahasiswa(Request $request): JsonResponse
    {
        $user = $request->user();

        $projectTeams = ProjectTeam::where('user_id', $user->id)
            ->with([
                'role',
                'proposal.status',
                'proposal.client.clientPic.clientProfile.user',
                'proposal.tasks',
            ])
            ->get();

        $teamIds = $projectTeams->pluck('id');
        $proposalIds = $projectTeams->pluck('proposal_id')->unique();

        $activeProjects = Proposal::whereIn('id', $proposalIds)
            ->whereHas('status', fn ($s) => $s->whereIn('name', ['Active', 'Testing']))
            ->count();

        $completedProjects = Proposal::whereIn('id', $proposalIds)
            ->whereHas('status', fn ($s) => $s->whereIn('name', ['Completed', 'Handover']))
            ->count();

        $tasksQuery = Task::whereIn('project_team_id', $teamIds);
        $pendingTasks = (clone $tasksQuery)->whereIn('status', ['TODO', 'IN_PROGRESS', 'TESTING'])->count();

        $tasks = (clone $tasksQuery)
            ->with('proposal:id,title')
            ->latest('updated_at')
            ->get()
            ->map(fn ($t) => [
                'id'                  => $t->id,
                'title'               => $t->title,
                'status'              => $t->status,
                'priority'            => $t->priority,
                'due_date'            => $t->due_date?->toDateString(),
                'progress_percentage' => (float) $t->progress_percentage,
                'proposal'            => $t->proposal ? [
                    'id'    => $t->proposal->id,
                    'title' => $t->proposal->title,
                ] : null,
            ]);

        $projects = $projectTeams->map(function ($team) {
            $proposal = $team->proposal;
            if (! $proposal) {
                return null;
            }

            $isCompleted = in_array(strtolower($proposal->status?->name ?? ''), ['completed', 'handover']);
            $taskCount = $proposal->tasks->count();

            if ($isCompleted) {
                $progress = 100;
            } elseif ($taskCount > 0) {
                $progress = (int) round((float) $proposal->tasks->avg('progress_percentage'));
            } else {
                $progress = 0;
            }

            $deadlineToUse = $proposal->target_completion_date ?? $proposal->desired_deadline;

            return [
                'id'          => $proposal->id,
                'title'       => $proposal->title,
                'client_name' => $proposal->client_company_name,
                'role'        => $team->role?->name ?? '—',
                'status'      => $proposal->status?->name ?? '—',
                'progress'    => $progress,
                'deadline'    => $deadlineToUse?->toDateString(),
            ];
        })->filter()->values();

        return $this->ok(
            message: 'Mahasiswa dashboard',
            data: [
                'active_projects'    => $activeProjects,
                'pending_tasks'      => $pendingTasks,
                'completed_projects' => $completedProjects,
                'tasks'              => $tasks,
                'projects'           => $projects,
            ]
        );
    }

    #[OA\Get(
        path: '/dosen/dashboard',
        summary: 'Dashboard Dosen',
        description: 'Endpoint ringkasan dashboard khusus role dosen',
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
                                new OA\Property(property: 'total_projects', type: 'integer', example: 1),
                                new OA\Property(property: 'active_projects', type: 'integer', example: 0),
                                new OA\Property(property: 'completed_projects', type: 'integer', example: 1),
                                new OA\Property(property: 'waiting_review', type: 'integer', example: 0),
                                new OA\Property(
                                    property: 'projects',
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'id', type: 'integer', example: 1),
                                            new OA\Property(property: 'title', type: 'string', example: 'Sistem Informasi Manajemen Teaching Factory'),
                                            new OA\Property(property: 'client_name', type: 'string', example: 'PT. Maju Bersama Sentosa'),
                                            new OA\Property(property: 'status', type: 'string', example: 'Completed'),
                                            new OA\Property(property: 'progress', type: 'number', example: 100),
                                            new OA\Property(property: 'deadline', type: 'string', format: 'date', nullable: true, example: '2025-12-15'),
                                        ]
                                    )
                                ),
                                new OA\Property(
                                    property: 'category_distribution',
                                    type: 'array',
                                    items: new OA\Items(
                                        type: 'object',
                                        additionalProperties: new OA\AdditionalProperties(type: 'integer'),
                                        example: ['web' => 1]
                                    )
                                ),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden (User bukan role dosen)'),
        ]
    )]
    public function dosen(Request $request): JsonResponse
    {
        $user = $request->user();

        $proposals = Proposal::where('pm_id', $user->id)
            ->orWhere('reviewer_id', $user->id)
            ->with(['status', 'category', 'client.clientPic.clientProfile.user', 'tasks'])
            ->get();

        $totalProjects = $proposals->count();

        $activeProjects = $proposals->filter(function ($p) {
            return in_array(strtolower($p->status?->name ?? ''), ['active', 'testing']);
        })->count();

        $completedProjects = $proposals->filter(function ($p) {
            return in_array(strtolower($p->status?->name ?? ''), ['completed', 'handover']);
        })->count();

        $proposalIds = $proposals->pluck('id');

        $waitingReview = Task::whereIn('proposal_id', $proposalIds)
            ->where(function ($q) {
                $q->where('pm_status', 'PENDING')
                  ->orWhere('status', 'TESTING');
            })
            ->count();

        $projects = $proposals->map(function ($proposal) {
            $isCompleted = in_array(strtolower($proposal->status?->name ?? ''), ['completed', 'handover']);
            $taskCount = $proposal->tasks->count();

            if ($isCompleted) {
                $progress = 100;
            } elseif ($taskCount > 0) {
                $progress = (int) round((float) $proposal->tasks->avg('progress_percentage'));
            } else {
                $progress = 0;
            }

            $deadlineToUse = $proposal->target_completion_date ?? $proposal->desired_deadline;

            return [
                'id'          => $proposal->id,
                'title'       => $proposal->title,
                'client_name' => $proposal->client_company_name,
                'status'      => $proposal->status?->name ?? '—',
                'progress'    => $progress,
                'deadline'    => $deadlineToUse?->toDateString(),
            ];
        })->values();

        $categoryDistribution = $proposals
            ->groupBy(fn ($p) => strtolower($p->category?->name ?? 'other'))
            ->map(fn ($group, $key) => [$key => $group->count()])
            ->values()
            ->toArray();

        return $this->ok(
            message: 'Dosen dashboard',
            data: [
                'total_projects'        => $totalProjects,
                'active_projects'       => $activeProjects,
                'completed_projects'    => $completedProjects,
                'waiting_review'        => $waitingReview,
                'projects'              => $projects,
                'category_distribution' => $categoryDistribution,
            ]
        );
    }
}
