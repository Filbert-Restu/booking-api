<?php

namespace Database\Seeders;

use App\Models\Workflow;
use App\Models\WorkflowStep;
use Illuminate\Database\Seeder;

class WorkflowSeeder extends Seeder
{
    public function run(): void
    {
        // 4. WORKFLOWS
        // Workflow untuk HMD (Himpunan Mahasiswa Departemen)
        // Alur: Sekretaris -> Ketua HMD -> Senat -> Dospen -> Ketua Dept -> Kemahasiswaan -> Wadek 1 -> Sumber Daya
        $workflowHMD = Workflow::create([
            'name' => 'Peminjaman Ruang HMD',
            'description' => 'Alur persetujuan peminjaman ruang untuk kegiatan HMD',
            'applies_to_category' => 'HMD'
        ]);
        WorkflowStep::create(['workflow_id' => $workflowHMD->id, 'step_order' => 1, 'step_name' => 'Pengajuan Sekretaris', 'target_role_slug' => 'sekretaris', 'scope_type' => 'SELF', 'target_category_lookup' => null]);
        WorkflowStep::create(['workflow_id' => $workflowHMD->id, 'step_order' => 2, 'step_name' => 'Review Ketua HMD', 'target_role_slug' => 'ketua-ormawa', 'scope_type' => 'SELF', 'target_category_lookup' => null]);
        WorkflowStep::create(['workflow_id' => $workflowHMD->id, 'step_order' => 3, 'step_name' => 'Kajian Senat Mahasiswa', 'target_role_slug' => 'senat', 'scope_type' => 'SPECIFIC_CATEGORY', 'target_category_lookup' => 'SENAT']);
        WorkflowStep::create(['workflow_id' => $workflowHMD->id, 'step_order' => 4, 'step_name' => 'Persetujuan Dosen Pendamping', 'target_role_slug' => 'dosen-pendamping', 'scope_type' => 'SELF', 'target_category_lookup' => null]);
        WorkflowStep::create(['workflow_id' => $workflowHMD->id, 'step_order' => 5, 'step_name' => 'Review Ketua Departemen', 'target_role_slug' => 'ketua-departemen', 'scope_type' => 'PARENT', 'target_category_lookup' => null]);
        WorkflowStep::create(['workflow_id' => $workflowHMD->id, 'step_order' => 6, 'step_name' => 'Persetujuan Kemahasiswaan', 'target_role_slug' => 'kemahasiswaan', 'scope_type' => 'FACULTY_LEADER', 'target_category_lookup' => null]);
        WorkflowStep::create(['workflow_id' => $workflowHMD->id, 'step_order' => 7, 'step_name' => 'Persetujuan Wadek 1', 'target_role_slug' => 'wadek1', 'scope_type' => 'FACULTY_LEADER', 'target_category_lookup' => null]);
        WorkflowStep::create(['workflow_id' => $workflowHMD->id, 'step_order' => 8, 'step_name' => 'Konfirmasi Sumber Daya', 'target_role_slug' => 'sumber-daya', 'scope_type' => 'FACULTY_LEADER', 'target_category_lookup' => null]);

        // Workflow untuk BEM
        // Alur: Sekretaris -> Ketua BEM -> Senat -> Dospen -> Kemahasiswaan -> Wadek 1 -> Sumber Daya
        $workflowBEM = Workflow::create([
            'name' => 'Peminjaman Ruang BEM',
            'description' => 'Alur persetujuan peminjaman ruang untuk kegiatan Badan Eksekutif Mahasiswa',
            'applies_to_category' => 'BEM'
        ]);
        WorkflowStep::create(['workflow_id' => $workflowBEM->id, 'step_order' => 1, 'step_name' => 'Pengajuan Sekretaris', 'target_role_slug' => 'sekretaris', 'scope_type' => 'SELF', 'target_category_lookup' => null]);
        WorkflowStep::create(['workflow_id' => $workflowBEM->id, 'step_order' => 2, 'step_name' => 'Review Ketua BEM', 'target_role_slug' => 'ketua-ormawa', 'scope_type' => 'SELF', 'target_category_lookup' => null]);
        WorkflowStep::create(['workflow_id' => $workflowBEM->id, 'step_order' => 3, 'step_name' => 'Kajian Senat Mahasiswa', 'target_role_slug' => 'senat', 'scope_type' => 'SPECIFIC_CATEGORY', 'target_category_lookup' => 'SENAT']);
        WorkflowStep::create(['workflow_id' => $workflowBEM->id, 'step_order' => 4, 'step_name' => 'Persetujuan Dosen Pendamping', 'target_role_slug' => 'dosen-pendamping', 'scope_type' => 'SELF', 'target_category_lookup' => null]);
        WorkflowStep::create(['workflow_id' => $workflowBEM->id, 'step_order' => 5, 'step_name' => 'Persetujuan Kemahasiswaan', 'target_role_slug' => 'kemahasiswaan', 'scope_type' => 'FACULTY_LEADER', 'target_category_lookup' => null]);
        WorkflowStep::create(['workflow_id' => $workflowBEM->id, 'step_order' => 6, 'step_name' => 'Persetujuan Wadek 1', 'target_role_slug' => 'wadek1', 'scope_type' => 'FACULTY_LEADER', 'target_category_lookup' => null]);
        WorkflowStep::create(['workflow_id' => $workflowBEM->id, 'step_order' => 7, 'step_name' => 'Konfirmasi Sumber Daya', 'target_role_slug' => 'sumber-daya', 'scope_type' => 'FACULTY_LEADER', 'target_category_lookup' => null]);

        // Workflow untuk Senat
        // Alur: Sekretaris -> Review Senat -> Dospen -> Kemahasiswaan -> Wadek 1 -> Sumber Daya
        $workflowSenat = Workflow::create([
            'name' => 'Peminjaman Ruang Senat',
            'description' => 'Alur persetujuan peminjaman ruang untuk kegiatan Senat Mahasiswa',
            'applies_to_category' => 'Senat'
        ]);
        WorkflowStep::create(['workflow_id' => $workflowSenat->id, 'step_order' => 1, 'step_name' => 'Pengajuan Sekretaris', 'target_role_slug' => 'sekretaris', 'scope_type' => 'SELF', 'target_category_lookup' => null]);
        WorkflowStep::create(['workflow_id' => $workflowSenat->id, 'step_order' => 2, 'step_name' => 'Review Senat', 'target_role_slug' => 'senat', 'scope_type' => 'SELF', 'target_category_lookup' => null]);
        WorkflowStep::create(['workflow_id' => $workflowSenat->id, 'step_order' => 3, 'step_name' => 'Persetujuan Dosen Pendamping', 'target_role_slug' => 'dosen-pendamping', 'scope_type' => 'SELF', 'target_category_lookup' => null]);
        WorkflowStep::create(['workflow_id' => $workflowSenat->id, 'step_order' => 4, 'step_name' => 'Persetujuan Kemahasiswaan', 'target_role_slug' => 'kemahasiswaan', 'scope_type' => 'FACULTY_LEADER', 'target_category_lookup' => null]);
        WorkflowStep::create(['workflow_id' => $workflowSenat->id, 'step_order' => 5, 'step_name' => 'Persetujuan Wadek 1', 'target_role_slug' => 'wadek1', 'scope_type' => 'FACULTY_LEADER', 'target_category_lookup' => null]);
        WorkflowStep::create(['workflow_id' => $workflowSenat->id, 'step_order' => 6, 'step_name' => 'Konfirmasi Sumber Daya', 'target_role_slug' => 'sumber-daya', 'scope_type' => 'FACULTY_LEADER', 'target_category_lookup' => null]);

        // Workflow untuk UKM
        // Alur: Sekretaris -> Ketua UKM -> Senat -> Dospen -> Kemahasiswaan -> Wadek 1 -> Sumber Daya
        $workflowUKM = Workflow::create([
            'name' => 'Peminjaman Ruang UKM',
            'description' => 'Alur persetujuan peminjaman ruang untuk kegiatan UKM',
            'applies_to_category' => 'UKM'
        ]);
        WorkflowStep::create(['workflow_id' => $workflowUKM->id, 'step_order' => 1, 'step_name' => 'Pengajuan Sekretaris', 'target_role_slug' => 'sekretaris', 'scope_type' => 'SELF', 'target_category_lookup' => null]);
        WorkflowStep::create(['workflow_id' => $workflowUKM->id, 'step_order' => 2, 'step_name' => 'Review Ketua UKM', 'target_role_slug' => 'ketua-ormawa', 'scope_type' => 'SELF', 'target_category_lookup' => null]);
        WorkflowStep::create(['workflow_id' => $workflowUKM->id, 'step_order' => 3, 'step_name' => 'Kajian Senat Mahasiswa', 'target_role_slug' => 'senat', 'scope_type' => 'SPECIFIC_CATEGORY', 'target_category_lookup' => 'SENAT']);
        WorkflowStep::create(['workflow_id' => $workflowUKM->id, 'step_order' => 4, 'step_name' => 'Persetujuan Dosen Pendamping', 'target_role_slug' => 'dosen-pendamping', 'scope_type' => 'SELF', 'target_category_lookup' => null]);
        WorkflowStep::create(['workflow_id' => $workflowUKM->id, 'step_order' => 5, 'step_name' => 'Persetujuan Kemahasiswaan', 'target_role_slug' => 'kemahasiswaan', 'scope_type' => 'FACULTY_LEADER', 'target_category_lookup' => null]);
        WorkflowStep::create(['workflow_id' => $workflowUKM->id, 'step_order' => 6, 'step_name' => 'Persetujuan Wadek 1', 'target_role_slug' => 'wadek1', 'scope_type' => 'FACULTY_LEADER', 'target_category_lookup' => null]);
        WorkflowStep::create(['workflow_id' => $workflowUKM->id, 'step_order' => 7, 'step_name' => 'Konfirmasi Sumber Daya', 'target_role_slug' => 'sumber-daya', 'scope_type' => 'FACULTY_LEADER', 'target_category_lookup' => null]);

        $this->command->info('✅ Workflow seeder berhasil!');
    }
}
