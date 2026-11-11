<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Notification;
use App\Models\Proposal;
use App\Models\User;
use Illuminate\Support\Facades\Log;

final class NotificationService
{
    /**
     * Template notifikasi per `type`.
     *
     * Placeholder memakai format `:nama`, diisi dari array $vars.
     *
     * @var array<string, array{title: string, message: string}>
     */
    private const TEMPLATES = [
        'proposal.submitted' => [
            'title'   => 'Proposal Baru Masuk',
            'message' => ':client mengajukan proposal :code - :title.',
        ],
        'proposal.revision_submitted' => [
            'title'   => 'Revisi Proposal Masuk',
            'message' => ':client mengirimkan revisi proposal :code - :title.',
        ],
        'proposal.approved' => [
            'title'   => 'Proposal Disetujui',
            'message' => 'Proposal :code - :title telah disetujui.',
        ],
        'proposal.rejected' => [
            'title'   => 'Proposal Ditolak',
            'message' => 'Proposal :code - :title ditolak oleh Pengurus TeFa. Alasan: :reason',
        ],
        'proposal.revision_required' => [
            'title'   => 'Proposal Perlu Revisi',
            'message' => 'Proposal :code - :title perlu direvisi. Catatan: :notes',
        ],
        'payment.created' => [
            'title'   => 'Termin Pembayaran Baru',
            'message' => 'Termin :term sebesar :amount untuk proposal :code telah ditetapkan.',
        ],
        'payment.terms_set' => [
            'title'   => 'Termin Pembayaran Ditetapkan',
            'message' => ':count termin pembayaran untuk proposal :code telah ditetapkan.',
        ],
        'payment.proof_uploaded' => [
            'title'   => 'Bukti Pembayaran Baru',
            'message' => ':client mengunggah bukti Termin :term (:amount) untuk proposal :code.',
        ],
        'payment.verified' => [
            'title'   => 'Pembayaran Terverifikasi',
            'message' => 'Pembayaran Termin :term (:amount) untuk proposal :code telah diverifikasi.',
        ],
        'payment.rejected' => [
            'title'   => 'Pembayaran Ditolak',
            'message' => 'Pembayaran Termin :term (:amount) untuk proposal :code ditolak. Silakan cek catatan verifikasi.',
        ],
        'proposal.pm_assigned' => [
            'title'   => 'Anda Ditugaskan sebagai PM',
            'message' => 'Anda ditugaskan sebagai Project Manager untuk proposal :code - :title.',
        ],
        'project.team_assigned' => [
            'title'   => 'Anda Ditambahkan ke Proyek',
            'message' => 'Anda ditambahkan ke tim proyek :code - :title sebagai :role.',
        ],
        'client.registration_requested' => [
            'title'   => 'Pendaftaran Klien Baru',
            'message' => ':name (:email) mendaftar sebagai :role. Mohon lakukan verifikasi.',
        ],
        'task.assigned' => [
            'title'   => 'Task Baru',
            'message' => 'Anda mendapat task ":task" pada proyek :code.',
        ],
        'task.progress_submitted' => [
            'title'   => 'Laporan Progres Baru',
            'message' => ':student mengirim laporan progres task ":task" (:progress%).',
        ],
        'task.reviewed' => [
            'title'   => 'Review Task',
            'message' => 'Task ":task" telah direview: :status.',
        ],
    ];

    /**
     * Buat satu notifikasi untuk satu user.
     *
     * Kegagalan pembuatan notifikasi tidak boleh menggagalkan aksi utama,
     * jadi exception ditangkap dan dicatat ke log.
     *
     * @param  array<string, mixed>  $vars
     */
    public function notify(int $userId, string $type, array $vars = [], ?int $proposalId = null): void
    {
        try {
            $template = self::TEMPLATES[$type] ?? [
                'title'   => 'Notifikasi',
                'message' => '',
            ];

            Notification::create([
                'user_id'     => $userId,
                'type'        => $type,
                'title'       => $this->render($template['title'], $vars),
                'message'     => $this->render($template['message'], $vars),
                'proposal_id' => $proposalId,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Gagal membuat notifikasi: ' . $e->getMessage(), [
                'user_id'     => $userId,
                'type'        => $type,
                'proposal_id' => $proposalId,
            ]);
        }
    }

    /**
     * Kirim notifikasi ke semua user dengan role tertentu.
     *
     * @param  array<string, mixed>  $vars
     */
    public function notifyRole(string $roleName, string $type, array $vars = [], ?int $proposalId = null): void
    {
        User::role($roleName)->pluck('id')->each(function ($userId) use ($type, $vars, $proposalId) {
            $this->notify((int) $userId, $type, $vars, $proposalId);
        });
    }

    /**
     * Kirim notifikasi ke klien pemilik proposal (PIC).
     *
     * @param  array<string, mixed>  $vars
     */
    public function notifyClient(Proposal $proposal, string $type, array $vars = []): void
    {
        $client = $proposal->relationLoaded('client')
            ? $proposal->client
            : $proposal->client()->with(['clientPic', 'clientProfile.pics'])->first();

        $picUserId = $client?->clientPic?->user_id
            ?? $client?->clientProfile?->pics->first()?->user_id
            ?? $proposal->client_id;

        if ($type === 'proposal.rejected' && empty($vars['reason'])) {
            $vars['reason'] = $proposal->review_notes ?: 'Tidak memenuhi kriteria kelayakan.';
        }

        if ($type === 'proposal.revision_required' && empty($vars['notes'])) {
            $vars['notes'] = $proposal->review_notes ?: 'Silakan cek catatan review.';
        }

        if ($picUserId) {
            $this->notify((int) $picUserId, $type, $vars, (int) $proposal->id);
        }
    }

    /**
     * Kirim notifikasi ke dosen PM proposal.
     *
     * @param  array<string, mixed>  $vars
     */
    public function notifyPm(Proposal $proposal, string $type, array $vars = []): void
    {
        if ($proposal->pm_id) {
            $this->notify((int) $proposal->pm_id, $type, $vars, (int) $proposal->id);
        }
    }

    /**
     * @param  array<string, mixed>  $vars
     */
    private function render(string $text, array $vars): string
    {
        $replacements = [];

        foreach ($vars as $key => $value) {
            $replacements[':' . $key] = (string) $value;
        }

        return strtr($text, $replacements);
    }
}
