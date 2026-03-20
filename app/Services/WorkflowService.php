<?php

namespace App\Services;

use App\Models\User;
use App\Models\Workflow;

class WorkflowService
{
    public function listWorkflows(User $user)
    {
        $userUnitCategory = $user->unit?->category;

        if ($userUnitCategory === 'FAKULTAS' || $user->role?->slug === 'admin') {
            return Workflow::with('steps')->get();
        }

        return Workflow::with('steps')
            ->forCategory($userUnitCategory)
            ->get();
    }

    public function showWorkflow(Workflow $workflow, User $user): Workflow
    {
        $userUnitCategory = $user->unit?->category;

        if ($userUnitCategory !== 'FAKULTAS' && $user->role?->slug !== 'admin') {
            abort_if(
                $workflow->applies_to_category !== $userUnitCategory,
                403,
                'Anda tidak memiliki akses ke workflow ini'
            );
        }

        return $workflow->load('steps');
    }

    public function createWorkflow(array $data): Workflow
    {
        $workflow = Workflow::create([
            'name'                => $data['name'],
            'description'         => $data['description'],
            'applies_to_category' => $data['applies_to_category'],
        ]);

        foreach ($data['steps'] as $stepData) {
            $workflow->steps()->create($stepData);
        }

        return $workflow->load('steps');
    }

    public function updateWorkflow(Workflow $workflow, array $data): Workflow
    {
        $workflow->update($data);

        return $workflow;
    }

    public function deleteWorkflow(Workflow $workflow): void
    {
        $workflow->delete();
    }

    public function addStep(Workflow $workflow, array $data)
    {
        return $workflow->steps()->create($data);
    }

    public function updateStep(Workflow $workflow, int $stepId, array $data)
    {
        $step = $workflow->steps()->findOrFail($stepId);
        $step->update($data);

        return $step;
    }

    public function deleteStep(Workflow $workflow, int $stepId): void
    {
        $step = $workflow->steps()->findOrFail($stepId);
        $step->delete();
    }
}
