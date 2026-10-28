<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\ClientPic;
use App\Models\Proposal;
use App\Models\ProposalCategory;
use App\Models\ProposalStatus;
use App\Models\ProjectRole;
use App\Models\ProjectTeam;
use App\Models\TaskCategory;
use App\Models\Task;
use App\Models\UatResult;
use App\Models\UatItem;
use App\Models\ProjectMaintenance;
use App\Models\Payment;
use App\Models\Module;
use Illuminate\Database\Seeder;

class ProposalSeeder extends Seeder
{
    public function run(): void
    {
        // ── Helpers ──────────────────────────────────────────
        $status  = fn(string $name) => ProposalStatus::where('name', $name)->first()->id;
        $cat     = fn(string $name) => ProposalCategory::where('name', $name)->first()->id;
        $projRole= fn(string $name) => ProjectRole::where('name', $name)->first()->id;
        $taskCat = fn(string $name) => TaskCategory::where('name', $name)->first()->id;

        $pic     = fn(string $email) => User::whereHas('clientPic')->where('email', $email)->first();
        $dosen   = fn(string $email) => User::where('email', $email)->first();
        $mhs     = fn(string $email) => User::where('email', $email)->first();

        // ── Referensi user ────────────────────────────────────
        $picBudi     = $pic('budi.santoso@majubersamasentosa.co.id');
        $picDewi     = $pic('dewi.rahayu@teknologinusantara.id');
        $picAgus     = $pic('agus.widodo@diskominfo.semarangkota.go.id');
        $picSusanto  = $pic('susanto.nugroho@polines.ac.id');
        $picHendra   = $pic('hendra.kusuma@digitalsolusi.id');
        $pengurus    = User::where('email', 'pengurus@tevara.id')->first();

        $dEri        = $dosen('erielilavindi@polines.ac.id');       // PM proyek 1
        $dLiliek     = $dosen('liliek.triyono@gmail.com');           // PM proyek 2
        $dAngga      = $dosen('anggawahyuwibowo@polines.ac.id');     // PM proyek 3
        $dAisya      = $dosen('aisya.karima@polines.ac.id');         // PM proyek 4

        // Anggota tim proyek 1 (Eri Eli Lavindi sebagai PM)
        $mDela    = $mhs('dela.43325207@mhs.polines.ac.id');        // Leader
        $mAang    = $mhs('aang.43325201@mhs.polines.ac.id');
        $mAlvian  = $mhs('alvian.43325202@mhs.polines.ac.id');
        $mAmanda  = $mhs('amanda.43325203@mhs.polines.ac.id');
        $mBagas   = $mhs('bagas.43325205@mhs.polines.ac.id');
        $mEvan    = $mhs('evan.43325208@mhs.polines.ac.id');
        $mIrgi    = $mhs('irgi.43325210@mhs.polines.ac.id');
        $mHadziq  = $mhs('hadziq.43325209@mhs.polines.ac.id');

        // Tim proyek lain (ambil beberapa mahasiswa lain)
        $mJatmiko  = $mhs('miko.43325211@mhs.polines.ac.id');
        $mLaksa    = $mhs('aksa.43325212@mhs.polines.ac.id');
        $mMilad    = $mhs('arin.43325213@mhs.polines.ac.id');
        $mNazifa   = $mhs('citra.43325217@mhs.polines.ac.id');
        $mRizky    = $mhs('rizky.43325220@mhs.polines.ac.id');
        $mSatria   = $mhs('nito.43325221@mhs.polines.ac.id');
        $mAhmad    = $mhs('ahmad.43325101@mhs.polines.ac.id');
        $mAlifandi = $mhs('alifandi.43325102@mhs.polines.ac.id');
        $mAnisa    = $mhs('anisa.43325103@mhs.polines.ac.id');
        $mArinda   = $mhs('arinda.43325104@mhs.polines.ac.id');

        // ════════════════════════════════════════════════════════
        // PROPOSAL 1 — COMPLETED
        // Sistem Informasi Manajemen TeFa
        // PM: Eri Eli Lavindi | Klien: PT. Maju Bersama Sentosa
        // ════════════════════════════════════════════════════════
        $p1 = Proposal::firstOrCreate(
            ['proposal_code' => 'PRSL-2025-001'],
            [
                'client_id'                   => $picBudi->id,
                'title'                       => 'Sistem Informasi Manajemen Teaching Factory',
                'background'                  => 'PT. Maju Bersama Sentosa membutuhkan sistem digital untuk mengelola alur pengajuan proyek kolaborasi dengan institusi pendidikan. Selama ini proses dilakukan secara manual menggunakan spreadsheet sehingga sulit dipantau dan rawan kehilangan data. Sistem ini diharapkan mampu meningkatkan efisiensi koordinasi antara perusahaan dan tim pengembang dari Teaching Factory Polines.',
                'target_audience'             => 'Staf IT perusahaan, manajemen pengurus TeFa, dan mahasiswa pengembang',
                'category_id'                 => $cat('Web'),
                'desired_deadline'            => '2025-12-31',
                'estimated_budget'            => 45000000.00,
                'reviewer_id'                 => $pengurus->id,
                'complexity_level'            => 'HIGH',
                'risk_analysis'               => 'Risiko utama adalah keterbatasan waktu mahasiswa di luar jam kuliah. Mitigasi: jadwal sprint mingguan yang fleksibel dan monitoring progress real-time.',
                'estimated_cost'              => 42000000.00,
                'review_notes'               => 'Proyek sesuai kompetensi D3 TI. Tim diwajibkan melakukan demo setiap 2 minggu.',
                'reviewed_at'                 => '2025-03-15 10:00:00',
                'spk_number'                  => 'SPK/TEFA/2025/001',
                'scope_of_work'               => 'Pengembangan sistem web manajemen proyek TeFa mencakup modul proposal, manajemen tim, task tracking, UAT, BAST, dan pembayaran. Stack: Laravel + Vue.js.',
                'total_amount'                => 42000000.00,
                'payment_terms_description'   => 'Termin 1: 40% (Rp 16.800.000) setelah SPK ditandatangani. Termin 2: 60% (Rp 25.200.000) setelah BAST disetujui.',
                'contract_file_url'           => 'contracts/SPK-TEFA-2025-001.pdf',
                'is_signed'                   => true,
                'signed_at'                   => '2025-03-20 09:00:00',
                'project_code'                => 'PRJ-2025/001',
                'pm_id'                       => $dEri->id,
                'start_date'                  => '2025-03-25',
                'target_completion_date'      => '2025-12-15',
                'actual_completion_date'      => '2025-12-10',
                'bast_number'                 => 'BAST/TEFA/2025/001',
                'repository_url'              => 'https://github.com/polines-tefa/sim-tefa-2025',
                'documentation_url'           => 'https://docs.sim-tefa.polines.ac.id',
                'user_manual_url'             => 'https://docs.sim-tefa.polines.ac.id/manual',
                'handover_notes'              => 'Seluruh source code, dokumentasi API, dan user manual telah diserahkan. Termasuk akses server staging dan credentials database.',
                'handover_date'               => '2025-12-10',
                'client_signoff_name'         => 'Budi Santoso',
                'client_approved'             => true,
                'bast_approved_at'            => '2025-12-12 14:00:00',
                'status_id'                   => $status('Completed'),
            ]
        );

        // Modules P1
        Module::firstOrCreate(['proposal_id' => $p1->id, 'title' => 'Manajemen Proposal'],
            ['description' => 'Modul pengajuan, review, dan persetujuan proposal dari klien hingga SPK']);
        Module::firstOrCreate(['proposal_id' => $p1->id, 'title' => 'Manajemen Tim & Task'],
            ['description' => 'Penugasan tim mahasiswa, Kanban board task, dan progress report sprint']);
        Module::firstOrCreate(['proposal_id' => $p1->id, 'title' => 'UAT & BAST'],
            ['description' => 'Berita acara pengujian dan serah terima produk kepada klien']);
        Module::firstOrCreate(['proposal_id' => $p1->id, 'title' => 'Keuangan & Pembayaran'],
            ['description' => 'Invoice, konfirmasi pembayaran, dan verifikasi bukti transfer']);

        // Tim P1
        $teamP1 = [
            [$mDela->id,   $projRole('Fullstack'),  '2025-03-25'], // Leader
            [$mAang->id,   $projRole('Backend'),    '2025-03-25'],
            [$mAlvian->id, $projRole('Frontend'),   '2025-03-25'],
            [$mAmanda->id, $projRole('UI/UX'),      '2025-03-25'],
            [$mBagas->id,  $projRole('Backend'),    '2025-03-25'],
            [$mEvan->id,   $projRole('Frontend'),   '2025-03-25'],
            [$mIrgi->id,   $projRole('QA Tester'),  '2025-03-25'],
            [$mHadziq->id, $projRole('UI/UX'),      '2025-03-25'],
        ];

        $teamMapP1 = [];
        foreach ($teamP1 as [$userId, $roleId, $joinedAt]) {
            $member = ProjectTeam::firstOrCreate(
                ['proposal_id' => $p1->id, 'user_id' => $userId],
                ['role_id' => $roleId, 'joined_at' => $joinedAt]
            );
            $teamMapP1[$userId] = $member->id;
        }

        // Tasks P1 — semua DONE
        $tasksP1 = [
            ['title' => 'Setup project Laravel & Vue.js',          'cat' => 'Backend',       'user' => $mDela,   'priority' => 'HIGH',     'pct' => 100, 'achieved' => 'Project scaffolding selesai, environment development sudah bisa dijalankan.'],
            ['title' => 'Desain database ERD schema',              'cat' => 'Documentation', 'user' => $mAang,   'priority' => 'HIGH',     'pct' => 100, 'achieved' => 'ERD lengkap 15 tabel sudah diverifikasi PM.'],
            ['title' => 'UI/UX wireframe dashboard klien',         'cat' => 'UI/UX',         'user' => $mAmanda, 'priority' => 'MEDIUM',   'pct' => 100, 'achieved' => 'Wireframe 12 halaman sudah di-approve klien.'],
            ['title' => 'Implementasi auth & role management',     'cat' => 'Backend',       'user' => $mBagas,  'priority' => 'HIGH',     'pct' => 100, 'achieved' => 'Spatie permission terintegrasi, 5 role berjalan normal.'],
            ['title' => 'Modul pengajuan proposal (F-01)',         'cat' => 'Frontend',      'user' => $mAlvian, 'priority' => 'HIGH',     'pct' => 100, 'achieved' => 'Form pengajuan proposal klien selesai dengan validasi lengkap.'],
            ['title' => 'Modul review kelayakan (F-02)',           'cat' => 'Backend',       'user' => $mAang,   'priority' => 'HIGH',     'pct' => 100, 'achieved' => 'Endpoint review kelayakan dan notifikasi ke klien sudah berfungsi.'],
            ['title' => 'Modul kontrak SPK (F-03)',                'cat' => 'Backend',       'user' => $mDela,   'priority' => 'HIGH',     'pct' => 100, 'achieved' => 'Generate SPK PDF dan upload file kontrak berfungsi.'],
            ['title' => 'Kanban board task & progress report',     'cat' => 'Frontend',      'user' => $mEvan,   'priority' => 'HIGH',     'pct' => 100, 'achieved' => 'Kanban 4 kolom dengan drag-drop dan form progress report selesai.'],
            ['title' => 'Modul UAT digital (F-06)',                'cat' => 'Frontend',      'user' => $mAlvian, 'priority' => 'MEDIUM',   'pct' => 100, 'achieved' => 'Form UAT dinamis dengan sign-off klien sudah berfungsi.'],
            ['title' => 'Generate invoice PDF (F-08)',             'cat' => 'Backend',       'user' => $mBagas,  'priority' => 'MEDIUM',   'pct' => 100, 'achieved' => 'DomPDF terintegrasi, invoice tergenerate dengan benar.'],
            ['title' => 'Testing end-to-end semua alur',           'cat' => 'Testing',       'user' => $mIrgi,   'priority' => 'HIGH',     'pct' => 100, 'achieved' => '87 test case dijalankan, 84 passed, 3 minor bug diperbaiki.'],
            ['title' => 'Dokumentasi API Postman',                 'cat' => 'Documentation', 'user' => $mHadziq, 'priority' => 'LOW',      'pct' => 100, 'achieved' => 'Postman collection 45 endpoint sudah diexport.'],
        ];

        foreach ($tasksP1 as $t) {
            Task::firstOrCreate(
                ['proposal_id' => $p1->id, 'title' => $t['title']],
                [
                    'project_team_id'      => $teamMapP1[$t['user']->id] ?? null,
                    'category_id'          => $taskCat($t['cat']),
                    'priority'             => $t['priority'],
                    'due_date'             => '2025-11-30',
                    'status'               => 'DONE',
                    'achieved_summary'     => $t['achieved'],
                    'progress_percentage'  => $t['pct'],
                    'pm_status'            => 'APPROVED',
                    'pm_notes'             => 'Pekerjaan sesuai spesifikasi.',
                    'pm_reviewed_at'       => '2025-12-01 10:00:00',
                ]
            );
        }

        // UAT P1
        $uat1 = UatResult::firstOrCreate(
            ['project_team_id' => $teamMapP1[$mDela->id]],
            [
                'session_date'       => '2025-12-05',
                'overall_result'     => 'PASSED',
                'client_notes'       => 'Semua fitur berjalan sesuai kebutuhan. Tampilan responsif di mobile.',
                'client_signoff_name'=> 'Budi Santoso',
                'signed_off_at'      => '2025-12-05 15:30:00',
            ]
        );
        $uatItemsP1 = [
            ['feature' => 'Login multi-role',          'result' => 'PASSED', 'feedback' => 'Semua role bisa login dengan benar.'],
            ['feature' => 'Pengajuan proposal',        'result' => 'PASSED', 'feedback' => 'Form mudah digunakan.'],
            ['feature' => 'Review kelayakan',          'result' => 'PASSED', 'feedback' => 'Notifikasi email diterima.'],
            ['feature' => 'Kanban task board',         'result' => 'PASSED', 'feedback' => 'Drag-drop responsif.'],
            ['feature' => 'Upload bukti pembayaran',   'result' => 'PASSED', 'feedback' => 'Upload berhasil, preview tampil.'],
            ['feature' => 'Generate invoice PDF',      'result' => 'PASSED', 'feedback' => 'Format invoice profesional.'],
            ['feature' => 'Dashboard jurusan',         'result' => 'PASSED', 'feedback' => 'Data rekap akurat.'],
        ];
        foreach ($uatItemsP1 as $item) {
            UatItem::firstOrCreate(
                ['uat_result_id' => $uat1->id, 'feature_name' => $item['feature']],
                ['result' => $item['result'], 'test_scenario' => 'Uji fungsional standar', 'client_feedback' => $item['feedback']]
            );
        }

        // Maintenance P1
        ProjectMaintenance::firstOrCreate(
            ['proposal_id' => $p1->id],
            [
                'maintenance_warranty_months' => 3,
                'maintenance_start_date'      => '2025-12-10',
                'maintenance_end_date'        => '2026-03-10',
                'maintenance_notes'           => 'Masa garansi aktif. Bug fix gratis, penambahan fitur dikenakan biaya.',
            ]
        );

        // Payments P1 — 2 termin, keduanya VERIFIED
        Payment::firstOrCreate(
            ['proposal_id' => $p1->id, 'term_sequence' => 1],
            [
                'amount'             => 16800000.00,
                'payment_date'       => '2025-03-22',
                'payment_method'     => 'BANK_TRANSFER',
                'transfer_reference' => 'TRF20250322001',
                'proof_url'          => 'payments/proofs/P1-T1-bukti.jpg',
                'verified_by'        => $pengurus->id,
                'verified_at'        => '2025-03-23 09:00:00',
                'verification_notes' => 'Termin 1 (DP 40%) terverifikasi.',
                'status'             => 'VERIFIED',
            ]
        );
        Payment::firstOrCreate(
            ['proposal_id' => $p1->id, 'term_sequence' => 2],
            [
                'amount'             => 25200000.00,
                'payment_date'       => '2025-12-13',
                'payment_method'     => 'BANK_TRANSFER',
                'transfer_reference' => 'TRF20251213001',
                'proof_url'          => 'payments/proofs/P1-T2-bukti.jpg',
                'verified_by'        => $pengurus->id,
                'verified_at'        => '2025-12-14 10:00:00',
                'verification_notes' => 'Termin 2 (pelunasan 60%) terverifikasi.',
                'status'             => 'VERIFIED',
            ]
        );

        // ════════════════════════════════════════════════════════
        // PROPOSAL 2 — ACTIVE (sedang berjalan, progress ~60%)
        // Aplikasi Monitoring IoT Pertanian
        // PM: Liliek Triyono | Klien: CV. Teknologi Nusantara
        // ════════════════════════════════════════════════════════
        $p2 = Proposal::firstOrCreate(
            ['proposal_code' => 'PRSL-2026-001'],
            [
                'client_id'                   => $picDewi->id,
                'title'                       => 'Aplikasi Monitoring IoT Pertanian Berbasis Web',
                'background'                  => 'CV. Teknologi Nusantara mengembangkan solusi pertanian cerdas dan membutuhkan dashboard web untuk memantau sensor suhu, kelembaban, dan pH tanah secara real-time dari perangkat IoT yang sudah terpasang di lahan pertanian mitra. Data sensor saat ini hanya tersimpan di local storage perangkat tanpa visualisasi yang memadai.',
                'target_audience'             => 'Petani mitra dan teknisi lapangan CV. Teknologi Nusantara',
                'category_id'                 => $cat('IoT'),
                'desired_deadline'            => '2026-10-01',
                'estimated_budget'            => 28000000.00,
                'reviewer_id'                 => $pengurus->id,
                'complexity_level'            => 'MEDIUM',
                'risk_analysis'               => 'Ketergantungan pada hardware IoT yang belum sepenuhnya stabil. Mitigasi: mock data untuk development, integrasi hardware di fase akhir.',
                'estimated_cost'              => 26000000.00,
                'review_notes'               => 'Layak dikerjakan. Pastikan koordinasi dengan vendor hardware IoT dari awal.',
                'reviewed_at'                 => '2026-02-10 10:00:00',
                'spk_number'                  => 'SPK/TEFA/2026/001',
                'scope_of_work'               => 'Dashboard web real-time monitoring sensor IoT, manajemen perangkat, alert threshold, dan laporan historis. Stack: Laravel + React.',
                'total_amount'                => 26000000.00,
                'payment_terms_description'   => 'Termin 1: 30% (Rp 7.800.000) setelah SPK. Termin 2: 70% (Rp 18.200.000) setelah BAST.',
                'contract_file_url'           => 'contracts/SPK-TEFA-2026-001.pdf',
                'is_signed'                   => true,
                'signed_at'                   => '2026-02-18 09:00:00',
                'project_code'                => 'PRJ-2026/001',
                'pm_id'                       => $dLiliek->id,
                'start_date'                  => '2026-02-20',
                'target_completion_date'      => '2026-09-30',
                'status_id'                   => $status('Active'),
            ]
        );

        Module::firstOrCreate(['proposal_id' => $p2->id, 'title' => 'Dashboard Real-time'],
            ['description' => 'Visualisasi data sensor suhu, kelembaban, dan pH secara real-time']);
        Module::firstOrCreate(['proposal_id' => $p2->id, 'title' => 'Manajemen Perangkat IoT'],
            ['description' => 'CRUD perangkat sensor, konfigurasi threshold alert']);
        Module::firstOrCreate(['proposal_id' => $p2->id, 'title' => 'Laporan & Historis'],
            ['description' => 'Grafik historis data sensor dan export laporan PDF/Excel']);

        // Tim P2
        $teamP2members = [
            [$mJatmiko->id,  $projRole('Fullstack'), '2026-02-20'],
            [$mLaksa->id,    $projRole('Backend'),   '2026-02-20'],
            [$mMilad->id,    $projRole('Frontend'),  '2026-02-20'],
            [$mNazifa->id,   $projRole('UI/UX'),     '2026-02-20'],
            [$mRizky->id,    $projRole('QA Tester'), '2026-02-20'],
        ];
        $teamMapP2 = [];
        foreach ($teamP2members as [$uid, $rid, $jat]) {
            $m = ProjectTeam::firstOrCreate(
                ['proposal_id' => $p2->id, 'user_id' => $uid],
                ['role_id' => $rid, 'joined_at' => $jat]
            );
            $teamMapP2[$uid] = $m->id;
        }

        // Tasks P2 — mix status
        $tasksP2 = [
            ['title' => 'Setup project & konfigurasi CI/CD',       'cat' => 'Backend',  'user' => $mJatmiko, 'priority' => 'HIGH',   'status' => 'DONE',        'pct' => 100, 'pm_status' => 'APPROVED',           'achieved' => 'Repository setup, pipeline CI/CD aktif di GitHub Actions.'],
            ['title' => 'Desain UI/UX dashboard monitoring',       'cat' => 'UI/UX',    'user' => $mNazifa,  'priority' => 'HIGH',   'status' => 'DONE',        'pct' => 100, 'pm_status' => 'APPROVED',           'achieved' => 'Figma mockup 8 halaman sudah di-approve PM.'],
            ['title' => 'API integrasi sensor data (MQTT)',        'cat' => 'Backend',  'user' => $mLaksa,   'priority' => 'HIGH',   'status' => 'IN_PROGRESS', 'pct' => 70,  'pm_status' => 'NEEDS_IMPROVEMENT',  'achieved' => 'MQTT subscriber berjalan, parsing data masih perlu diperbaiki untuk multi-sensor.', 'blocker' => 'Dokumentasi protokol dari vendor IoT belum lengkap.'],
            ['title' => 'Komponen chart real-time (WebSocket)',    'cat' => 'Frontend', 'user' => $mMilad,   'priority' => 'HIGH',   'status' => 'IN_PROGRESS', 'pct' => 55,  'pm_status' => 'PENDING',             'achieved' => 'WebSocket connection berhasil, chart update setiap 5 detik.', 'blocker' => 'Perlu optimasi untuk data burst dari banyak sensor sekaligus.'],
            ['title' => 'Sistem alert threshold otomatis',        'cat' => 'Backend',  'user' => $mJatmiko, 'priority' => 'MEDIUM', 'status' => 'TODO',        'pct' => 0,   'pm_status' => 'PENDING',             'achieved' => null],
            ['title' => 'Modul laporan dan export Excel',         'cat' => 'Frontend', 'user' => $mMilad,   'priority' => 'MEDIUM', 'status' => 'TODO',        'pct' => 0,   'pm_status' => 'PENDING',             'achieved' => null],
            ['title' => 'Testing integrasi hardware IoT',         'cat' => 'Testing',  'user' => $mRizky,   'priority' => 'HIGH',   'status' => 'TODO',        'pct' => 0,   'pm_status' => 'PENDING',             'achieved' => null],
        ];
        foreach ($tasksP2 as $t) {
            Task::firstOrCreate(
                ['proposal_id' => $p2->id, 'title' => $t['title']],
                [
                    'project_team_id'     => $teamMapP2[$t['user']->id] ?? null,
                    'category_id'         => $taskCat($t['cat']),
                    'priority'            => $t['priority'],
                    'due_date'            => '2026-09-15',
                    'status'              => $t['status'],
                    'achieved_summary'    => $t['achieved'] ?? null,
                    'blockers_summary'    => $t['blocker'] ?? null,
                    'progress_percentage' => $t['pct'],
                    'pm_status'           => $t['pm_status'],
                    'pm_notes'            => $t['pm_status'] === 'NEEDS_IMPROVEMENT' ? 'Perlu perbaikan sebelum lanjut ke task berikutnya.' : null,
                    'pm_reviewed_at'      => in_array($t['pm_status'], ['APPROVED','NEEDS_IMPROVEMENT']) ? '2026-08-01 10:00:00' : null,
                ]
            );
        }

        // Payment P2 — termin 1 VERIFIED
        Payment::firstOrCreate(
            ['proposal_id' => $p2->id, 'term_sequence' => 1],
            [
                'amount'             => 7800000.00,
                'payment_date'       => '2026-02-19',
                'payment_method'     => 'BANK_TRANSFER',
                'transfer_reference' => 'TRF20260219001',
                'proof_url'          => 'payments/proofs/P2-T1-bukti.jpg',
                'verified_by'        => $pengurus->id,
                'verified_at'        => '2026-02-20 08:00:00',
                'verification_notes' => 'DP 30% terverifikasi.',
                'status'             => 'VERIFIED',
            ]
        );
        // Termin 2 belum dibuat (proyek masih berjalan)

        // ════════════════════════════════════════════════════════
        // PROPOSAL 3 — TESTING (menunggu UAT)
        // Website Portal Berita Kota Semarang
        // PM: Angga Wahyu Wibowo | Klien: Diskominfo Semarang
        // ════════════════════════════════════════════════════════
        $p3 = Proposal::firstOrCreate(
            ['proposal_code' => 'PRSL-2026-002'],
            [
                'client_id'                   => $picAgus->id,
                'title'                       => 'Website Portal Berita dan Informasi Kota Semarang',
                'background'                  => 'Dinas Komunikasi dan Informatika Kota Semarang ingin memperbarui portal berita kota yang sudah usang. Portal baru diharapkan memiliki CMS yang mudah digunakan oleh staff redaksi tanpa kemampuan teknis, mendukung SEO, dan memiliki fitur komentar termoderasi.',
                'target_audience'             => 'Warga Kota Semarang dan staf redaksi Diskominfo',
                'category_id'                 => $cat('Web'),
                'desired_deadline'            => '2026-08-01',
                'estimated_budget'            => 35000000.00,
                'reviewer_id'                 => $pengurus->id,
                'complexity_level'            => 'MEDIUM',
                'risk_analysis'               => 'Perlu koordinasi ketat dengan tim redaksi untuk alur publish konten. Risiko perubahan requirement di tengah jalan.',
                'estimated_cost'              => 32000000.00,
                'review_notes'               => 'Disetujui. Wajib ada fitur multi-level approval konten.',
                'reviewed_at'                 => '2026-01-20 10:00:00',
                'spk_number'                  => 'SPK/TEFA/2026/002',
                'scope_of_work'               => 'Portal berita dengan CMS, kategori artikel, galeri foto, komentar termoderasi, dan halaman profil Kota Semarang. Stack: Laravel + Inertia.js.',
                'total_amount'                => 32000000.00,
                'payment_terms_description'   => 'Termin 1: 35% (Rp 11.200.000) saat kontrak. Termin 2: 65% (Rp 20.800.000) saat BAST.',
                'contract_file_url'           => 'contracts/SPK-TEFA-2026-002.pdf',
                'is_signed'                   => true,
                'signed_at'                   => '2026-01-25 09:00:00',
                'project_code'                => 'PRJ-2026/002',
                'pm_id'                       => $dAngga->id,
                'start_date'                  => '2026-01-27',
                'target_completion_date'      => '2026-07-31',
                'status_id'                   => $status('Testing'),
            ]
        );

        Module::firstOrCreate(['proposal_id' => $p3->id, 'title' => 'CMS Artikel & Berita'],
            ['description' => 'CRUD artikel dengan multi-level approval: penulis → editor → admin']);
        Module::firstOrCreate(['proposal_id' => $p3->id, 'title' => 'Portal Publik'],
            ['description' => 'Halaman depan, kategori berita, pencarian, dan galeri foto']);
        Module::firstOrCreate(['proposal_id' => $p3->id, 'title' => 'Moderasi Komentar'],
            ['description' => 'Sistem komentar dengan auto-filter kata terlarang dan moderasi manual']);

        // Tim P3
        $teamP3members = [
            [$mSatria->id,   $projRole('Fullstack'), '2026-01-27'],
            [$mAhmad->id,    $projRole('Backend'),   '2026-01-27'],
            [$mAlifandi->id, $projRole('Frontend'),  '2026-01-27'],
            [$mAnisa->id,    $projRole('UI/UX'),     '2026-01-27'],
            [$mArinda->id,   $projRole('QA Tester'), '2026-01-27'],
        ];
        $teamMapP3 = [];
        foreach ($teamP3members as [$uid, $rid, $jat]) {
            $m = ProjectTeam::firstOrCreate(
                ['proposal_id' => $p3->id, 'user_id' => $uid],
                ['role_id' => $rid, 'joined_at' => $jat]
            );
            $teamMapP3[$uid] = $m->id;
        }

        // Tasks P3 — semua DONE, siap UAT
        $tasksP3 = [
            ['title' => 'Setup Laravel + Inertia.js + Vue 3',  'cat' => 'Backend',  'user' => $mSatria,   'pct' => 100],
            ['title' => 'Desain wireframe portal publik',       'cat' => 'UI/UX',    'user' => $mAnisa,    'pct' => 100],
            ['title' => 'Modul CMS artikel multi-approval',    'cat' => 'Backend',  'user' => $mAhmad,    'pct' => 100],
            ['title' => 'Frontend halaman publik responsif',   'cat' => 'Frontend', 'user' => $mAlifandi, 'pct' => 100],
            ['title' => 'Sistem komentar + moderasi',          'cat' => 'Backend',  'user' => $mSatria,   'pct' => 100],
            ['title' => 'SEO meta tags dan sitemap',           'cat' => 'Frontend', 'user' => $mAlifandi, 'pct' => 100],
            ['title' => 'Testing fungsional & bug fixing',     'cat' => 'Testing',  'user' => $mArinda,   'pct' => 100],
        ];
        foreach ($tasksP3 as $t) {
            Task::firstOrCreate(
                ['proposal_id' => $p3->id, 'title' => $t['title']],
                [
                    'project_team_id'     => $teamMapP3[$t['user']->id] ?? null,
                    'category_id'         => $taskCat($t['cat']),
                    'priority'            => 'HIGH',
                    'due_date'            => '2026-07-15',
                    'status'              => 'DONE',
                    'achieved_summary'    => 'Task diselesaikan sesuai spesifikasi.',
                    'progress_percentage' => $t['pct'],
                    'pm_status'           => 'APPROVED',
                    'pm_reviewed_at'      => '2026-07-20 10:00:00',
                ]
            );
        }

        // Payment P3 — termin 1 VERIFIED
        Payment::firstOrCreate(
            ['proposal_id' => $p3->id, 'term_sequence' => 1],
            [
                'amount'             => 11200000.00,
                'payment_date'       => '2026-01-26',
                'payment_method'     => 'BANK_TRANSFER',
                'transfer_reference' => 'TRF20260126001',
                'proof_url'          => 'payments/proofs/P3-T1-bukti.jpg',
                'verified_by'        => $pengurus->id,
                'verified_at'        => '2026-01-27 08:00:00',
                'verification_notes' => 'Termin 1 terverifikasi.',
                'status'             => 'VERIFIED',
            ]
        );

        // ════════════════════════════════════════════════════════
        // PROPOSAL 4 — UNDER REVIEW
        // Sistem ERP Sederhana untuk UMKM
        // Klien: Unit Bisnis Polines | PM: belum assign
        // ════════════════════════════════════════════════════════
        $p4 = Proposal::firstOrCreate(
            ['proposal_code' => 'PRSL-2026-003'],
            [
                'client_id'       => $picSusanto->id,
                'title'           => 'Sistem ERP Sederhana untuk UMKM Binaan Polines',
                'background'      => 'Unit Bisnis Polines mengelola puluhan UMKM binaan yang membutuhkan pencatatan keuangan, stok barang, dan laporan sederhana. Saat ini masih menggunakan Excel yang tidak terhubung satu sama lain. Dibutuhkan sistem ERP ringan berbasis web yang bisa diakses dari HP.',
                'target_audience' => 'Pemilik UMKM binaan Polines dan staf Unit Bisnis',
                'category_id'     => $cat('Web'),
                'desired_deadline'=> '2027-03-01',
                'estimated_budget'=> 38000000.00,
                'reviewer_id'     => $pengurus->id,
                'complexity_level'=> 'HIGH',
                'risk_analysis'   => 'Kompleksitas tinggi karena mencakup banyak modul (keuangan, stok, UMKM profiling). Perlu pemecahan modul yang jelas.',
                'estimated_cost'  => null, // masih dalam review
                'review_notes'    => 'Dalam proses evaluasi. Perlu klarifikasi jumlah UMKM yang akan diakomodasi dan batas kompleksitas modul.',
                'reviewed_at'     => null,
                'status_id'       => $status('Under Review'),
            ]
        );

        // ════════════════════════════════════════════════════════
        // PROPOSAL 5 — SUBMITTED (baru masuk, belum di-review)
        // Aplikasi Mobile Company Profile
        // Klien: PT. Digital Solusi Indonesia
        // ════════════════════════════════════════════════════════
        Proposal::firstOrCreate(
            ['proposal_code' => 'PRSL-2026-004'],
            [
                'client_id'        => $picHendra->id,
                'title'            => 'Aplikasi Mobile Company Profile PT. Digital Solusi Indonesia',
                'background'       => 'PT. Digital Solusi Indonesia ingin memperluas jangkauan pemasaran melalui aplikasi mobile company profile yang menampilkan portofolio, layanan, kontak, dan blog perusahaan. Aplikasi diharapkan tersedia di Android dan iOS.',
                'target_audience'  => 'Calon klien dan mitra bisnis PT. Digital Solusi Indonesia',
                'category_id'      => $cat('Mobile'),
                'desired_deadline' => '2026-12-01',
                'estimated_budget' => 22000000.00,
                'status_id'        => $status('Submitted'),
            ]
        );

        // ════════════════════════════════════════════════════════
        // PROPOSAL 6 — REJECTED
        // Klien: Unit Bisnis Polines (PIC: Retno)
        // ════════════════════════════════════════════════════════
        $picRetno = User::where('email', 'retno.wulandari@polines.ac.id')->first();
        Proposal::firstOrCreate(
            ['proposal_code' => 'PRSL-2025-002'],
            [
                'client_id'        => $picRetno->id,
                'title'            => 'Platform E-Learning Interaktif Polines',
                'background'       => 'Dibutuhkan platform e-learning lengkap dengan fitur video streaming, kuis interaktif, sertifikat digital, dan integrasi payment gateway untuk kursus berbayar.',
                'target_audience'  => 'Mahasiswa dan masyarakat umum',
                'category_id'      => $cat('Web'),
                'desired_deadline' => '2025-08-01',
                'estimated_budget' => 150000000.00,
                'reviewer_id'      => $pengurus->id,
                'complexity_level' => 'HIGH',
                'risk_analysis'    => 'Scope terlalu besar untuk tim mahasiswa D3. Video streaming dan payment gateway membutuhkan infrastruktur yang jauh melampaui kapasitas TeFa.',
                'estimated_cost'   => null,
                'review_notes'     => 'Ditolak. Scope melebihi kapasitas tim TeFa. Disarankan untuk memecah menjadi beberapa proyek terpisah atau bekerjasama dengan vendor eksternal.',
                'reviewed_at'      => '2025-06-15 14:00:00',
                'status_id'        => $status('Rejected'),
            ]
        );

        // ════════════════════════════════════════════════════════
        // PROPOSAL 7 — REVISION REQUIRED (Butuh Revisi)
        // Sistem Manajemen Inventaris & Logistik Gudang Terpadu
        // Klien: PT. Maju Bersama Sentosa (PIC: Budi Santoso)
        // ════════════════════════════════════════════════════════
        $pRev = Proposal::firstOrCreate(
            ['proposal_code' => 'PRSL-2026-005'],
            [
                'client_id'        => $picBudi->id,
                'title'            => 'Sistem Manajemen Inventaris & Logistik Gudang Terpadu',
                'background'       => 'PT. Maju Bersama Sentosa membutuhkan sistem manajemen pergudangan otomatis untuk melacak pergerakan inventaris barang keluar dan masuk secara real-time. Sistem saat ini masih manual menggunakan pencatatan buku dan spreadsheet sehingga sering terjadi selisih stok fisik dan keterlambatan laporan ke manajemen.',
                'target_audience'  => 'Staff gudang, staf purchasing, operator logistik, dan manajemen inventaris',
                'category_id'      => $cat('Web'),
                'desired_deadline' => '2027-04-30',
                'estimated_budget' => 55000000.00,
                'reviewer_id'      => $pengurus->id,
                'complexity_level' => 'MEDIUM',
                'risk_analysis'    => 'Integrasi barcode scanner fisik dan audit opname stok berkala memerlukan optimasi query database.',
                'estimated_cost'   => 38000000.00,
                'review_notes'     => 'Estimasi anggaran diajukan (Rp 55.000.000) melebihi standar biaya proyek sejenis di TeFa. Mohon sesuaikan harga/anggaran menjadi maksimal Rp 38.000.000 – Rp 40.000.000. Selain itu, mohon lengkapi deskripsi fitur pada modul audit stok opname.',
                'reviewed_at'      => '2026-10-26 14:00:00',
                'status_id'        => $status('Revision Required'),
            ]
        );

        Module::firstOrCreate(
            ['proposal_id' => $pRev->id, 'title' => 'Pencatatan Barang Masuk & Keluar'],
            ['description' => 'Pencatatan penerimaan barang dari supplier, integrasi barcode scan SKU, dan surat jalan pengiriman cabang.']
        );
        Module::firstOrCreate(
            ['proposal_id' => $pRev->id, 'title' => 'Manajemen Stok & Lokasi Rak Gudang'],
            ['description' => 'Penempatan multi-warehouse, bin location per rak gudang, dan notifikasi peringatan batas minimum stok (safety stock).']
        );
        Module::firstOrCreate(
            ['proposal_id' => $pRev->id, 'title' => 'Pelaporan & Audit Stok Opname'],
            ['description' => 'Laporan mutasi pergerakan barang, rekonsiliasi selisih stok fisik dan sistem, serta ekspor data ke format spreadsheet dan PDF.']
        );

        $pRev->statusHistories()->updateOrCreate(
            ['proposal_id' => $pRev->id, 'status_id' => $status('Revision Required')],
            [
                'note'       => 'Estimasi anggaran diajukan (Rp 55.000.000) melebihi standar biaya proyek sejenis di TeFa. Mohon sesuaikan harga/anggaran menjadi maksimal Rp 38.000.000 – Rp 40.000.000. Selain itu, mohon lengkapi deskripsi fitur pada modul audit stok opname.',
                'changed_by' => $pengurus->id,
            ]
        );
    }
}