<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Proposal;
use App\Models\ProposalStatus;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Proposal',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'proposal_code', type: 'string', example: 'PRO-2026-0001'),
        new OA\Property(property: 'title', type: 'string', example: 'Sistem Informasi Manajemen Proyek TeFa'),
        new OA\Property(property: 'background', type: 'string', example: 'Latar belakang proyek...'),
        new OA\Property(property: 'target_audience', type: 'string', nullable: true, example: 'Mahasiswa dan dosen'),
        new OA\Property(property: 'desired_deadline', type: 'string', format: 'date', nullable: true, example: '2026-12-31'),
        new OA\Property(property: 'estimated_budget', type: 'number', format: 'double', nullable: true, example: 15000000),
        new OA\Property(property: 'attachment_url', type: 'string', nullable: true),
        new OA\Property(property: 'complexity_level', type: 'string', enum: ['LOW', 'MEDIUM', 'HIGH'], nullable: true),
        new OA\Property(property: 'risk_analysis', type: 'string', nullable: true),
        new OA\Property(property: 'estimated_cost', type: 'number', format: 'double', nullable: true),
        new OA\Property(property: 'review_notes', type: 'string', nullable: true),
        new OA\Property(property: 'reviewed_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'spk_number', type: 'string', nullable: true, example: 'SPK-2026-001'),
        new OA\Property(property: 'scope_of_work', type: 'string', nullable: true),
        new OA\Property(property: 'total_amount', type: 'number', format: 'double', nullable: true),
        new OA\Property(property: 'payment_terms_description', type: 'string', nullable: true),
        new OA\Property(property: 'contract_file_url', type: 'string', nullable: true),
        new OA\Property(property: 'is_signed', type: 'boolean', example: false),
        new OA\Property(property: 'signed_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'project_code', type: 'string', nullable: true, example: 'PRJ-2026-0001'),
        new OA\Property(property: 'pm_id', type: 'integer', nullable: true),
        new OA\Property(property: 'start_date', type: 'string', format: 'date', nullable: true),
        new OA\Property(property: 'target_completion_date', type: 'string', format: 'date', nullable: true),
        new OA\Property(property: 'actual_completion_date', type: 'string', format: 'date', nullable: true),
        new OA\Property(property: 'bast_number', type: 'string', nullable: true),
        new OA\Property(property: 'repository_url', type: 'string', nullable: true),
        new OA\Property(property: 'documentation_url', type: 'string', nullable: true),
        new OA\Property(property: 'user_manual_url', type: 'string', nullable: true),
        new OA\Property(property: 'handover_notes', type: 'string', nullable: true),
        new OA\Property(property: 'handover_date', type: 'string', format: 'date', nullable: true),
        new OA\Property(property: 'client_signoff_name', type: 'string', nullable: true),
        new OA\Property(property: 'client_approved', type: 'boolean', example: false),
        new OA\Property(property: 'bast_approved_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'status_id', type: 'integer', example: 1),
        new OA\Property(property: 'status', ref: '#/components/schemas/ProposalStatus', nullable: true),
        new OA\Property(property: 'category', ref: '#/components/schemas/ProposalCategory', nullable: true),
        new OA\Property(property: 'client', ref: '#/components/schemas/User', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ]
)]
#[OA\Schema(
    schema: 'ProposalStatus',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Submitted'),
    ]
)]
#[OA\Schema(
    schema: 'ProposalCategory',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Web Application'),
    ]
)]
#[OA\Schema(
    schema: 'ProposalListItem',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'proposal_code', type: 'string', example: 'PRSL-2026-001'),
        new OA\Property(property: 'client_name', type: 'string', example: 'PT. Maju Bersama Sentosa'),
        new OA\Property(property: 'title', type: 'string', example: 'Sistem Informasi Inventaris'),
        new OA\Property(property: 'background', type: 'string', example: 'Latar belakang proyek...'),
        new OA\Property(property: 'target_audience', type: 'string', nullable: true, example: 'Staff gudang dan admin inventory'),
        new OA\Property(property: 'category_name', type: 'string', nullable: true, example: 'Web'),
        new OA\Property(property: 'status', type: 'string', example: 'Submitted'),
        new OA\Property(property: 'desired_deadline', type: 'string', format: 'date', nullable: true, example: '2027-02-01'),
        new OA\Property(property: 'estimated_budget', type: 'string', nullable: true, example: '10000000.00'),
    ]
)]
class ProposalController extends Controller
{
    #[OA\Get(
        path: '/proposals',
        summary: 'List semua proposal (Pengurus TeFa)',
        description: 'Mengambil seluruh proposal. Gunakan `status_id` untuk memfilter status tertentu. Hanya dapat diakses oleh pengurus TeFa.',
        security: [['bearerAuth' => []]],
        tags: ['F-02: Review & Validasi'],
        parameters: [
            new OA\Parameter(
                name: 'status_id',
                in: 'query',
                required: false,
                description: 'Filter status tertentu (mis. 2 = Submitted, 5 = Approved).',
                schema: new OA\Schema(type: 'integer', example: 2)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Daftar proposal',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/ProposalListItem')
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden (bukan pengurus_tefa)'),
        ]
    )]
    public function index(Request $request)
    {
        $validated = $request->validate([
            'status_id' => ['nullable', 'integer', 'exists:proposal_statuses,id'],
        ]);

        $query = $this->proposalListQuery();

        if (isset($validated['status_id'])) {
            $query->where('status_id', $validated['status_id']);
        }

        return response()->json($this->toProposalList($query->latest()->get()));
    }

    #[OA\Get(
        path: '/proposals/pending',
        summary: 'List proposal pending review (Pengurus TeFa)',
        description: 'Mengambil proposal yang belum disetujui, yaitu status `Submitted` dan `Revision Required`. Hanya dapat diakses oleh pengurus TeFa.',
        security: [['bearerAuth' => []]],
        tags: ['F-02: Review & Validasi'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Daftar proposal pending',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/ProposalListItem')
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden (bukan pengurus_tefa)'),
        ]
    )]
    public function pending()
    {
        $proposals = $this->proposalListQuery()
            ->whereIn(
                'status_id',
                ProposalStatus::whereIn('name', ['Submitted', 'Revision Required'])->pluck('id')
            )
            ->latest()
            ->get();

        return response()->json($this->toProposalList($proposals));
    }

    private function proposalListQuery()
    {
        return Proposal::with([
            'status:id,name',
            'category:id,name',
            'client.clientPic.clientProfile.user',
            'client.clientProfile.user',
        ]);
    }

    private function toProposalList($proposals)
    {
        return $proposals->map(fn (Proposal $proposal) => [
            'id'               => $proposal->id,
            'proposal_code'    => $proposal->proposal_code,
            'client_name'      => $proposal->client_company_name,
            'title'            => $proposal->title,
            'background'       => $proposal->background,
            'target_audience'  => $proposal->target_audience,
            'category_name'    => $proposal->category?->name,
            'status'           => $proposal->status?->name,
            'desired_deadline' => $proposal->desired_deadline?->toDateString(),
            'estimated_budget' => $proposal->estimated_budget,
        ]);
    }

    #[OA\Get(
        path: '/proposals/mine',
        summary: 'List proposal milik klien yang login',
        description: 'Mengambil daftar proposal milik klien yang sedang login beserta kategori dan statusnya.',
        security: [['bearerAuth' => []]],
        tags: ['F-01: Pengajuan Proposal'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Daftar proposal klien',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 1),
                            new OA\Property(property: 'proposal_code', type: 'string', example: 'PRSL-2025-001'),
                            new OA\Property(property: 'title', type: 'string', example: 'Sistem Informasi Manajemen Teaching Factory'),
                            new OA\Property(property: 'category', type: 'string', nullable: true, example: 'Web'),
                            new OA\Property(property: 'status', type: 'string', nullable: true, example: 'Completed'),
                            new OA\Property(property: 'start_date', type: 'string', format: 'date', nullable: true, example: '2025-03-25'),
                            new OA\Property(property: 'target_completion_date', type: 'string', format: 'date', nullable: true, example: '2025-12-15'),
                        ]
                    )
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden (bukan klien)'),
        ]
    )]
    public function mine(Request $request)
    {
        $proposals = Proposal::with(['status:id,name', 'category:id,name'])
            ->where('client_id', $request->user()->id)
            ->latest()
            ->get()
            ->map(fn (Proposal $proposal) => [
                'id'                     => $proposal->id,
                'proposal_code'          => $proposal->proposal_code,
                'title'                  => $proposal->title,
                'category'               => $proposal->category?->name,
                'status'                 => $proposal->status?->name,
                'start_date'             => $proposal->start_date?->toDateString(),
                'target_completion_date' => $proposal->target_completion_date?->toDateString(),
            ]);

        return response()->json($proposals);
    }

    #[OA\Post(
        path: '/proposals',
        summary: 'Ajukan proposal baru',
        description: 'Membuat proposal baru sekaligus modulnya dalam satu request. `client_id` harus user dengan role klien (PIC perusahaan) atau jurusan. `proposal_code` (PRSL-{tahun}-{3 digit}) dan status `Submitted` di-generate otomatis oleh server.',
        security: [['bearerAuth' => []]],
        tags: ['F-01: Pengajuan Proposal'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(
                    required: ['client_id', 'title', 'background', 'category_id', 'desired_deadline'],
                    properties: [
                        new OA\Property(property: 'client_id', type: 'integer', description: 'User ID dengan role klien (PIC) atau jurusan', example: 6),
                        new OA\Property(property: 'title', type: 'string', maxLength: 255, example: 'Sistem Informasi Inventaris'),
                        new OA\Property(property: 'background', type: 'string', description: 'Minimal 100 karakter', example: 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam.'),
                        new OA\Property(property: 'target_audience', type: 'string', nullable: true, description: 'Target pengguna/sasaran pengguna sistem', example: 'Staff gudang dan admin inventory'),
                        new OA\Property(property: 'category_id', type: 'integer', description: 'ID kategori, harus ada di tabel proposal_categories', example: 1),
                        new OA\Property(property: 'desired_deadline', type: 'string', format: 'date', description: 'Minimal H+30 dari hari ini', example: '2027-02-01'),
                        new OA\Property(property: 'estimated_budget', type: 'number', format: 'float', nullable: true, example: 10000000),
                        new OA\Property(
                            property: 'modules',
                            type: 'array',
                            nullable: true,
                            description: 'Daftar modul yang diajukan sekaligus',
                            items: new OA\Items(
                                type: 'object',
                                required: ['title'],
                                properties: [
                                    new OA\Property(property: 'title', type: 'string', example: 'Manajemen Barang'),
                                    new OA\Property(property: 'description', type: 'string', nullable: true, example: 'CRUD data barang'),
                                ]
                            )
                        ),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Proposal berhasil dibuat',
                content: new OA\JsonContent(ref: '#/components/schemas/Proposal')
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden (role tidak diizinkan)'),
            new OA\Response(response: 422, description: 'Validasi gagal'),
        ]
    )]
    public function store(Request $request)
    {
        $validated = $request->validate([
            'client_id'             => ['required', 'integer', 'exists:users,id'],
            'title'                 => ['required', 'string', 'max:255'],
            'background'            => ['required', 'string', 'min:100'],
            'target_audience'       => ['nullable', 'string'],
            'category_id'           => ['required', 'integer', 'exists:proposal_categories,id'],
            'desired_deadline'      => ['required', 'date', 'after_or_equal:' . now()->addDays(30)->toDateString()],
            'estimated_budget'      => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
            'modules'               => ['nullable', 'array'],
            'modules.*.title'       => ['required', 'string', 'max:255'],
            'modules.*.description' => ['nullable', 'string'],
        ]);

        $client = User::find($validated['client_id']);

        if (! $client->hasRole('klien') && ! $client->hasRole('jurusan')) {
            throw ValidationException::withMessages([
                'client_id' => ['client_id harus user dengan role klien (PIC) atau jurusan.'],
            ]);
        }

        $modules = $validated['modules'] ?? [];
        unset($validated['modules']);

        $proposal = DB::transaction(function () use ($validated, $modules) {
            $proposal = Proposal::create($validated);

            if (! empty($modules)) {
                $proposal->modules()->createMany($modules);
            }

            return $proposal;
        });

        return response()->json($proposal->load(['status', 'category', 'modules']), 201);
    }

    #[OA\Get(
        path: '/proposals/{id}',
        summary: 'Detail proposal',
        description: 'Mengambil detail lengkap proposal beserta relasi (status, category, client, reviewer, PM, team, dll). Klien hanya bisa lihat proposalnya sendiri. Dosen hanya bisa lihat proposal yang dia jadi PM-nya. Field risk_analysis disembunyikan untuk klien.',
        security: [['bearerAuth' => []]],
        tags: ['F-01: Pengajuan Proposal'],
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
                description: 'Detail proposal',
                content: new OA\JsonContent(ref: '#/components/schemas/Proposal')
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden (tidak punya akses ke proposal ini)'),
            new OA\Response(response: 404, description: 'Proposal tidak ditemukan'),
        ]
    )]
    public function show(Request $request, $id)
    {
        $proposal = Proposal::with([
            'status', 'category', 'client.clientProfile',
            'reviewer', 'pm.lecturerProfile',
            'projectTeams.user.studentProfile', 'projectTeams.role',
        ])->findOrFail($id);

        $user = $request->user();

        if ($user->hasRole('klien') && $proposal->client_id !== $user->id) {
            abort(403, 'Unauthorized access to this proposal.');
        }
        if ($user->hasRole('dosen') && $proposal->pm_id !== $user->id) {
            abort(403, 'Unauthorized access to this proposal.');
        }

        if ($user->hasRole('klien')) {
            $proposal->makeHidden(['risk_analysis']);
        }

        return response()->json($proposal);
    }
}
