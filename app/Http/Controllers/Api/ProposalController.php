<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Proposal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProposalController extends Controller
{
    // GET /proposals (pengurus_tefa, jurusan)
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

        $perPage = min((int) $request->get('per_page', 15), 100); // default 15

        return response()->json($query->paginate($perPage));
    }

    // GET /proposals/mine (klien)
    public function mine(Request $request)
    {
        $proposals = Proposal::with(['status', 'statusHistories'])
            ->where('client_id', $request->user()->id)
            ->latest()
            ->get();

        return response()->json($proposals);
    }

    // POST /proposals (klien)
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title'            => 'required|string|max:255',
            'background'       => 'required|string|min:100',
            'category_id'      => 'required|exists:proposal_categories,id',
            'desired_deadline' => 'required|date|after_or_equal:today +30 days', // min H+30
            'target_audience'  => 'nullable|string',
            'estimated_budget' => 'nullable|numeric',
            'attachment'       => 'nullable|file|mimes:pdf,docx|max:10240', // max 10MB
        ]);

        if ($request->hasFile('attachment')) {
            $validated['attachment_url'] = $request->file('attachment')
                ->store('proposals/attachments', 'public');
        }
        unset($validated['attachment']); // file object tidak boleh masuk mass assignment

        $validated['client_id'] = $request->user()->id;

        // proposal_code & status_id diisi oleh ProposalObserver
        $proposal = DB::transaction(fn () => Proposal::create($validated));

        return response()->json($proposal->load('status'), 201);
    }

    // GET /proposals/{id} (klien, pengurus_tefa, dosen, jurusan)
    public function show(Request $request, $id)
    {
        $proposal = Proposal::with([
            'status', 'category', 'client.clientProfile',
            'reviewer', 'pm.lecturerProfile',
            'projectTeams.student.studentProfile', 'projectTeams.role',
        ])->findOrFail($id);

        $user = $request->user();

        // Policy: klien hanya proposal miliknya
        if ($user->hasRole('klien') && $proposal->client_id !== $user->id) {
            abort(403, 'Unauthorized access to this proposal.');
        }
        // Policy: dosen hanya proposal yang ia jadi PM-nya
        if ($user->hasRole('dosen') && $proposal->pm_id !== $user->id) {
            abort(403, 'Unauthorized access to this proposal.');
        }

        // Analisis risiko bersifat internal TeFa, tidak ditampilkan ke klien
        // (hapus baris ini kalau klien memang boleh melihatnya)
        if ($user->hasRole('klien')) {
            $proposal->makeHidden(['risk_analysis']);
        }

        return response()->json($proposal);
    }
}