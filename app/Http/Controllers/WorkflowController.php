<?php

namespace App\Http\Controllers;

use App\Models\Workflow;
use Illuminate\Http\Request;

class WorkflowController extends Controller
{
    /**
     * Ambil daftar workflow yang tersedia untuk user
     * (Filter berdasarkan kategori unit user)
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // Ambil kategori unit dari user yang login
        $userUnitCategory = $user->unit->category;

        // Ambil workflow yang sesuai dengan kategori unit user
        $workflows = Workflow::with('steps')
            ->forCategory($userUnitCategory)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $workflows
        ]);
    }

    /**
     * Ambil detail workflow tertentu beserta langkah-langkahnya
     */
    public function show($id)
    {
        $workflow = Workflow::with('steps')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $workflow
        ]);
    }

    /**
     * Buat workflow baru (Admin only)
     */
    public function store(Request $request)
    {
        // Pastikan hanya admin yang bisa membuat workflow
        abort_if(
            $request->user()->role->slug !== 'admin',
            403,
            'Hanya admin yang dapat membuat workflow'
        );

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'applies_to_category' => 'required|string',
            'steps' => 'required|array|min:1',
            'steps.*.step_order' => 'required|integer',
            'steps.*.step_name' => 'required|string',
            'steps.*.target_role_slug' => 'required|string',
            'steps.*.scope_type' => 'required|in:SELF,PARENT,FACULTY_LEADER,SPECIFIC_CATEGORY',
            'steps.*.target_category_lookup' => 'nullable|string',
        ]);

        $workflow = Workflow::create([
            'name' => $validated['name'],
            'description' => $validated['description'],
            'applies_to_category' => $validated['applies_to_category'],
        ]);

        // Buat langkah-langkah workflow
        foreach ($validated['steps'] as $stepData) {
            $workflow->steps()->create($stepData);
        }

        return response()->json([
            'success' => true,
            'message' => 'Workflow berhasil dibuat',
            'data' => $workflow->load('steps')
        ], 201);
    }

    /**
     * Update workflow
     */
    public function update(Request $request, $id)
    {
        // Pastikan hanya admin yang bisa mengubah workflow
        abort_if(
            $request->user()->role->slug !== 'admin',
            403,
            'Hanya admin yang dapat mengubah workflow'
        );

        $workflow = Workflow::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'applies_to_category' => 'sometimes|string',
        ]);

        $workflow->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Workflow berhasil diupdate',
            'data' => $workflow
        ]);
    }

    /**
     * Hapus workflow
     */
    public function destroy(Request $request, $id)
    {
        // Pastikan hanya admin yang bisa menghapus workflow
        abort_if(
            $request->user()->role->slug !== 'admin',
            403,
            'Hanya admin yang dapat menghapus workflow'
        );

        $workflow = Workflow::findOrFail($id);
        $workflow->delete();

        return response()->json([
            'success' => true,
            'message' => 'Workflow berhasil dihapus'
        ]);
    }
}
