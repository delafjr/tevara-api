<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Proposal;
use App\Models\Task;

class TaskObserver
{
    public function created(Task $task): void
    {
        $this->syncProposalProgress($task->proposal_id);
    }

    public function updated(Task $task): void
    {
        if ($task->wasChanged('progress_percentage') || $task->wasChanged('proposal_id')) {
            $this->syncProposalProgress($task->proposal_id);
            if ($task->wasChanged('proposal_id') && $task->getOriginal('proposal_id')) {
                $this->syncProposalProgress((int) $task->getOriginal('proposal_id'));
            }
        }
    }

    public function deleted(Task $task): void
    {
        $this->syncProposalProgress($task->proposal_id);
    }

    private function syncProposalProgress(?int $proposalId): void
    {
        if (! $proposalId) {
            return;
        }

        $proposal = Proposal::with('status')->find($proposalId);
        $proposal?->syncOverallProgress();
    }
}
