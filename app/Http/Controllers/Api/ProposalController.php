<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Proposal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
class ProposalController extends Controller
{
    #[OA\Get(
        path: '/proposals',
        summary: 'List semua proposal (Pengurus TeFa / Jurusan)',
        description: 'Mengambil daftar proposal dengan filter status, kategori, dan search. Mendukung paginasi.',
        security: [['bearerAuth' => []]],
        tags: ['F-01: Pengajuan Proposal'],
        parameters: [
            new OA\Parameter(name: 'status_id', in: 'query', required: false, description: 'Filter berdasarkan status', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'category_id', in: 'query', required: false, description: 'Filter berdasarkan kategori', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'search', in: 'query', required: false, description: 'Cari berdasarkan proposal_code, title, atau nama klien', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, description: 'Jumlah item per halaman (default: 15, max: 100)', schema: new OA\Schema(type: 'integer', default: 15)),
            new OA\Parameter(name: 'page', in: 'query', required: false, description: 'Nomor halaman', schema: new OA\Schema(type: 'integer', default: 1)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Daftar proposal (paginated)',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'current_page', type: 'integer', example: 1),
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Proposal')),
                        new OA\Property(property: 'last_page', type: 'integer', example: 3),
                        new OA\Property(property: 'per_page', type: 'integer', example: 15),
                        new OA\Property(property: 'total', type: 'integer', example: 42),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden (bukan pengurus_tefa / jurusan)'),
        ]
    )]
    public function index(Request $request)
    {
        $query = Proposal::with(['status', 'category', 'client'])->latest();

        if ($request->filled('status_id')) {
            $query->where('status_id', $request->status_id);
        }
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('proposal_code', 'like', "%{$search}%")
                  ->orWhere('title', 'like', "%{$search}%")
                  ->orWhereHas('client', function ($qClient) use ($search) {
                      $qClient->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $perPage = min((int) $request->get('per_page', 15), 100);

        return response()->json($query->paginate($perPage));
    }

    #[OA\Get(
        path: '/proposals/mine',
        summary: 'List proposal milik klien yang login',
        description: 'Mengambil semua proposal yang diajukan oleh klien yang sedang login, beserta status dan history-nya.',
        security: [['bearerAuth' => []]],
        tags: ['F-01: Pengajuan Proposal'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Daftar proposal klien',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/Proposal')
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden (bukan klien)'),
        ]
    )]
    public function mine(Request $request)
    {
        $proposals = Proposal::with(['status', 'statusHistories'])
            ->where('client_id', $request->user()->id)
            ->latest()
            ->get();

        return response()->json($proposals);
    }

    #[OA\Post(
        path: '/proposals',
        summary: 'Ajukan proposal baru (Klien)',
        description: 'Membuat proposal proyek baru. Hanya bisa dilakukan oleh user dengan role klien. File attachment opsional (max 10MB, pdf/docx).',
        security: [['bearerAuth' => []]],
        tags: ['F-01: Pengajuan Proposal'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['title', 'background', 'category_id', 'desired_deadline'],
                    properties: [
                        new OA\Property(property: 'title', type: 'string', example: 'Sistem Informasi Inventaris'),
                        new OA\Property(property: 'background', type: 'string', description: 'Minimal 100 karakter', example: 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam.'),
                        new OA\Property(property: 'category_id', type: 'integer', example: 1),
                        new OA\Property(property: 'desired_deadline', type: 'string', format: 'date', description: 'Minimal H+30 dari hari ini', example: '2027-02-01'),
                        new OA\Property(property: 'target_audience', type: 'string', nullable: true, example: 'Staff gudang'),
                        new OA\Property(property: 'estimated_budget', type: 'number', nullable: true, example: 10000000),
                        new OA\Property(property: 'attachment', type: 'string', format: 'binary', description: 'File PDF/DOCX, max 10MB'),
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
            new OA\Response(response: 403, description: 'Forbidden (bukan klien)'),
            new OA\Response(response: 422, description: 'Validasi gagal'),
        ]
    )]
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title'            => 'required|string|max:255',
            'background'       => 'required|string|min:100',
            'category_id'      => 'required|exists:proposal_categories,id',
            'desired_deadline' => 'required|date|after_or_equal:today +30 days',
            'target_audience'  => 'nullable|string',
            'estimated_budget' => 'nullable|numeric',
            'attachment'       => 'nullable|file|mimes:pdf,docx|max:10240',
        ]);

        if ($request->hasFile('attachment')) {
            $validated['attachment_url'] = $request->file('attachment')
                ->store('proposals/attachments', 'public');
        }
        unset($validated['attachment']);

        $validated['client_id'] = $request->user()->id;

        $proposal = DB::transaction(fn () => Proposal::create($validated));

        return response()->json($proposal->load('status'), 201);
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
