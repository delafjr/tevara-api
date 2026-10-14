<?php

namespace Database\Seeders;

use App\Models\ProposalCategory;
use App\Models\ProposalStatus;
use App\Models\ProjectRole;
use App\Models\TaskCategory;
use Illuminate\Database\Seeder;

class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        // Kategori proposal
        $categories = ['Web', 'Mobile', 'Desktop', 'IoT', 'Other'];
        foreach ($categories as $cat) {
            ProposalCategory::firstOrCreate(['name' => $cat]);
        }

        // Status proposal — urutan WAJIB benar (id 1–10)
        $statuses = [
            'Draft',
            'Submitted',
            'Under Review',
            'Revision Required',
            'Approved',
            'Rejected',
            'Active',
            'Testing',
            'Handover',
            'Completed',
        ];
        foreach ($statuses as $status) {
            ProposalStatus::firstOrCreate(['name' => $status]);
        }

        // Role teknis mahasiswa dalam tim
        $projectRoles = ['UI/UX', 'Frontend', 'Backend', 'Fullstack', 'QA Tester'];
        foreach ($projectRoles as $role) {
            ProjectRole::firstOrCreate(['name' => $role]);
        }

        // Kategori task
        $taskCategories = ['Frontend', 'Backend', 'UI/UX', 'Testing', 'Documentation', 'Research'];
        foreach ($taskCategories as $cat) {
            TaskCategory::firstOrCreate(['name' => $cat]);
        }
    }
}