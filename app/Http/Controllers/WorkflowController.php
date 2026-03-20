<?php

namespace App\Http\Controllers;

use App\Models\Workflow;
use App\Services\WorkflowService;
use Illuminate\Http\Request;
use App\Http\Requests\Workflow\StoreWorkflowRequest;
use App\Http\Requests\Workflow\UpdateWorkflowRequest;
use App\Http\Requests\Workflow\AddWorkflowStepRequest;
use App\Http\Requests\Workflow\UpdateWorkflowStepRequest;

class WorkflowController extends Controller
{
    protected WorkflowService $workflowService;

    public function __construct(WorkflowService $workflowService)
    {
        $this->workflowService = $workflowService;
    }

    public function index(Request $request)
    {
        $workflows = $this->workflowService->listWorkflows($request->user());

        return response()->json([
            'success' => true,
            'data' => $workflows
        ]);
    }

    public function show(Request $request, $id)
    {
        $workflow = Workflow::findOrFail($id);
        $workflow = $this->workflowService->showWorkflow($workflow, $request->user());

        return response()->json([
            'success' => true,
            'data' => $workflow
        ]);
    }

    public function store(StoreWorkflowRequest $request)
    {
        $user = $request->user();
        abort_if(
            $user->unit?->category !== 'FAKULTAS' && $user->role?->slug !== 'admin',
            403,
            'Hanya admin yang dapat membuat workflow'
        );

        $workflow = $this->workflowService->createWorkflow($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Workflow berhasil dibuat',
            'data' => $workflow
        ], 201);
    }

    public function update(UpdateWorkflowRequest $request, $id)
    {
        $user = $request->user();
        abort_if(
            $user->unit?->category !== 'FAKULTAS' && $user->role?->slug !== 'admin',
            403,
            'Hanya admin yang dapat mengubah workflow'
        );

        $workflow = Workflow::findOrFail($id);
        $workflow = $this->workflowService->updateWorkflow($workflow, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Workflow berhasil diupdate',
            'data' => $workflow
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        abort_if(
            $user->unit?->category !== 'FAKULTAS' && $user->role?->slug !== 'admin',
            403,
            'Hanya admin yang dapat menghapus workflow'
        );

        $workflow = Workflow::findOrFail($id);
        $this->workflowService->deleteWorkflow($workflow);

        return response()->json([
            'success' => true,
            'message' => 'Workflow berhasil dihapus'
        ]);
    }

    public function addStep(AddWorkflowStepRequest $request, $id)
    {
        $user = $request->user();
        abort_if(
            $user->unit?->category !== 'FAKULTAS' && $user->role?->slug !== 'admin',
            403,
            'Hanya admin yang dapat menambahkan step'
        );

        $workflow = Workflow::findOrFail($id);
        $step = $this->workflowService->addStep($workflow, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Step berhasil ditambahkan',
            'data' => $step
        ], 201);
    }

    public function updateStep(UpdateWorkflowStepRequest $request, $workflowId, $stepId)
    {
        $user = $request->user();
        abort_if(
            $user->unit?->category !== 'FAKULTAS' && $user->role?->slug !== 'admin',
            403,
            'Hanya admin yang dapat mengupdate step'
        );

        $workflow = Workflow::findOrFail($workflowId);
        $step = $this->workflowService->updateStep($workflow, $stepId, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Step berhasil diupdate',
            'data' => $step
        ]);
    }

    public function deleteStep(Request $request, $workflowId, $stepId)
    {
        $user = $request->user();
        abort_if(
            $user->unit?->category !== 'FAKULTAS' && $user->role?->slug !== 'admin',
            403,
            'Hanya admin yang dapat menghapus step'
        );

        $workflow = Workflow::findOrFail($workflowId);
        $this->workflowService->deleteStep($workflow, $stepId);

        return response()->json([
            'success' => true,
            'message' => 'Step berhasil dihapus'
        ]);
    }
}
