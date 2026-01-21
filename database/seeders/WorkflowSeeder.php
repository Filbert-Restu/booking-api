<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class WorkflowSeeder extends Seeder
{
    public function run(): void
    {
        // 1. ROLES
        $roles = [
            ['name' => 'Admin', 'slug' => 'admin', 'description' => 'Administrator Sistem'],
            ['name' => 'Ketua HIMA', 'slug' => 'ketua-hima', 'description' => 'Ketua Himpunan Mahasiswa'],
            ['name' => 'Sekretaris HIMA', 'slug' => 'sekretaris-hima', 'description' => 'Sekretaris Himpunan Mahasiswa'],
            ['name' => 'Bendahara HIMA', 'slug' => 'bendahara-hima', 'description' => 'Bendahara Himpunan Mahasiswa'],
            ['name' => 'Ketua BEM', 'slug' => 'ketua-bem', 'description' => 'Ketua Badan Eksekutif Mahasiswa'],
            ['name' => 'Sekretaris BEM', 'slug' => 'sekretaris-bem', 'description' => 'Sekretaris Badan Eksekutif Mahasiswa'],
            ['name' => 'Bendahara BEM', 'slug' => 'bendahara-bem', 'description' => 'Bendahara Badan Eksekutif Mahasiswa'],
            ['name' => 'Ketua UKM', 'slug' => 'ketua-ukm', 'description' => 'Ketua Unit Kegiatan Mahasiswa'],
            ['name' => 'Sekretaris UKM', 'slug' => 'sekretaris-ukm', 'description' => 'Sekretaris Unit Kegiatan Mahasiswa'],
            ['name' => 'Ketua Prodi', 'slug' => 'ketua-prodi', 'description' => 'Ketua Program Studi'],
            ['name' => 'Sekretaris Prodi', 'slug' => 'sekretaris-prodi', 'description' => 'Sekretaris Program Studi'],
            ['name' => 'Wakil Dekan 1', 'slug' => 'wadek1', 'description' => 'Wakil Dekan Bidang Akademik dan Kemahasiswaan'],
            ['name' => 'Ketua Senat', 'slug' => 'ketua-senat', 'description' => 'Ketua Senat Fakultas'],
            ['name' => 'Sekretaris Senat', 'slug' => 'sekretaris-senat', 'description' => 'Sekretaris Senat Fakultas'],
            ['name' => 'Mahasiswa', 'slug' => 'mahasiswa', 'description' => 'Mahasiswa biasa'],
        ];

        foreach ($roles as $role) {
            Role::create($role);
        }

        // 2. UNITS
        $fakultas = Unit::create(['name' => 'Fakultas Teknik', 'code' => 'FT', 'category' => 'FAKULTAS', 'parent_id' => null, 'description' => 'Fakultas Teknik Universitas']);
        $senat = Unit::create(['name' => 'Senat Fakultas Teknik', 'code' => 'SENAT-FT', 'category' => 'SENAT', 'parent_id' => $fakultas->id, 'description' => 'Badan Senat Fakultas']);
        $bemFT = Unit::create(['name' => 'BEM Fakultas Teknik', 'code' => 'BEM-FT', 'category' => 'BEM', 'parent_id' => $fakultas->id, 'description' => 'Badan Eksekutif Mahasiswa Fakultas Teknik']);
        $prodiIF = Unit::create(['name' => 'Program Studi Informatika', 'code' => 'PRODI-IF', 'category' => 'PRODI', 'parent_id' => $fakultas->id, 'description' => 'Program Studi Teknik Informatika']);
        $prodiTE = Unit::create(['name' => 'Program Studi Teknik Elektro', 'code' => 'PRODI-TE', 'category' => 'PRODI', 'parent_id' => $fakultas->id, 'description' => 'Program Studi Teknik Elektro']);
        $prodiTS = Unit::create(['name' => 'Program Studi Teknik Sipil', 'code' => 'PRODI-TS', 'category' => 'PRODI', 'parent_id' => $fakultas->id, 'description' => 'Program Studi Teknik Sipil']);
        $himaIF = Unit::create(['name' => 'HIMA Informatika', 'code' => 'HIMA-IF', 'category' => 'HIMA', 'parent_id' => $prodiIF->id, 'description' => 'Himpunan Mahasiswa Informatika']);
        $himaTE = Unit::create(['name' => 'HIMA Teknik Elektro', 'code' => 'HIMA-TE', 'category' => 'HIMA', 'parent_id' => $prodiTE->id, 'description' => 'Himpunan Mahasiswa Teknik Elektro']);
        $himaTS = Unit::create(['name' => 'HIMA Teknik Sipil', 'code' => 'HIMA-TS', 'category' => 'HIMA', 'parent_id' => $prodiTS->id, 'description' => 'Himpunan Mahasiswa Teknik Sipil']);
        $ukmOlahraga = Unit::create(['name' => 'UKM Olahraga', 'code' => 'UKM-OR', 'category' => 'UKM', 'parent_id' => $fakultas->id, 'description' => 'Unit Kegiatan Mahasiswa Olahraga']);

        // 3. USERS
        $roleadmin = Role::where('slug', 'admin')->first();
        $roleWadek1 = Role::where('slug', 'wadek1')->first();
        $roleKetuaSenat = Role::where('slug', 'ketua-senat')->first();
        $roleSekretarisSenat = Role::where('slug', 'sekretaris-senat')->first();
        $roleKetuaBEM = Role::where('slug', 'ketua-bem')->first();
        $roleSekretarisBEM = Role::where('slug', 'sekretaris-bem')->first();
        $roleKaprodi = Role::where('slug', 'ketua-prodi')->first();
        $roleKetuaHima = Role::where('slug', 'ketua-hima')->first();
        $roleSekretarisHima = Role::where('slug', 'sekretaris-hima')->first();
        $roleKetuaUKM = Role::where('slug', 'ketua-ukm')->first();
        $roleMahasiswa = Role::where('slug', 'mahasiswa')->first();

        User::create(['name' => 'Mr. Adming', 'email' => 'admin@ft.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleadmin->id, 'unit_id' => $fakultas->id]);
        User::create(['name' => 'Prof. Dr. Budi Santoso', 'email' => 'wadek1@ft.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleWadek1->id, 'unit_id' => $fakultas->id]);
        User::create(['name' => 'Dr. Andi Wijaya', 'email' => 'senat@ft.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleKetuaSenat->id, 'unit_id' => $senat->id]);
        User::create(['name' => 'Dr. Putri Maharani', 'email' => 'sekretaris.senat@ft.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleSekretarisSenat->id, 'unit_id' => $senat->id]);
        User::create(['name' => 'Budi Setiawan', 'email' => 'ketua.bem@student.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleKetuaBEM->id, 'unit_id' => $bemFT->id]);
        User::create(['name' => 'Sinta Kusuma', 'email' => 'sekretaris.bem@student.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleSekretarisBEM->id, 'unit_id' => $bemFT->id]);
        User::create(['name' => 'Dr. Siti Rahmawati', 'email' => 'kaprodi.if@ft.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleKaprodi->id, 'unit_id' => $prodiIF->id]);
        User::create(['name' => 'Dr. Bambang Suryadi', 'email' => 'kaprodi.te@ft.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleKaprodi->id, 'unit_id' => $prodiTE->id]);
        User::create(['name' => 'Dr. Agus Prasetyo', 'email' => 'kaprodi.ts@ft.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleKaprodi->id, 'unit_id' => $prodiTS->id]);
        User::create(['name' => 'Ahmad Rizki', 'email' => 'ketua.hima.if@student.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleKetuaHima->id, 'unit_id' => $himaIF->id]);
        User::create(['name' => 'Dewi Lestari', 'email' => 'sekretaris.hima.if@student.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleSekretarisHima->id, 'unit_id' => $himaIF->id]);
        User::create(['name' => 'Rudi Hartono', 'email' => 'ketua.hima.te@student.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleKetuaHima->id, 'unit_id' => $himaTE->id]);
        User::create(['name' => 'Fitri Handayani', 'email' => 'ketua.hima.ts@student.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleKetuaHima->id, 'unit_id' => $himaTS->id]);
        User::create(['name' => 'Fajar Nugroho', 'email' => 'ketua.ukm@student.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleKetuaUKM->id, 'unit_id' => $ukmOlahraga->id]);
        User::create(['name' => 'Rina Kartika', 'email' => 'mahasiswa@student.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleMahasiswa->id, 'unit_id' => $himaIF->id]);

        // 4. WORKFLOWS
        $workflowHIMA = Workflow::create(['name' => 'Pengajuan Proposal Kegiatan HIMA', 'description' => 'Alur persetujuan proposal kegiatan untuk HIMA', 'applies_to_category' => 'HIMA']);
        WorkflowStep::create(['workflow_id' => $workflowHIMA->id, 'step_order' => 1, 'step_name' => 'Review Ketua HIMA', 'target_role_slug' => 'ketua-hima', 'scope_type' => 'SELF', 'target_category_lookup' => null]);
        WorkflowStep::create(['workflow_id' => $workflowHIMA->id, 'step_order' => 2, 'step_name' => 'Persetujuan Ketua Prodi', 'target_role_slug' => 'ketua-prodi', 'scope_type' => 'PARENT', 'target_category_lookup' => null]);
        WorkflowStep::create(['workflow_id' => $workflowHIMA->id, 'step_order' => 3, 'step_name' => 'Review Senat Fakultas', 'target_role_slug' => 'ketua-senat', 'scope_type' => 'SPECIFIC_CATEGORY', 'target_category_lookup' => 'SENAT']);
        WorkflowStep::create(['workflow_id' => $workflowHIMA->id, 'step_order' => 4, 'step_name' => 'Persetujuan Dekan', 'target_role_slug' => 'dekan', 'scope_type' => 'FACULTY_LEADER', 'target_category_lookup' => null]);

        $workflowUKM = Workflow::create(['name' => 'Pengajuan Kegiatan UKM', 'description' => 'Alur persetujuan kegiatan untuk UKM', 'applies_to_category' => 'UKM']);
        WorkflowStep::create(['workflow_id' => $workflowUKM->id, 'step_order' => 1, 'step_name' => 'Review Ketua UKM', 'target_role_slug' => 'ketua-ukm', 'scope_type' => 'SELF', 'target_category_lookup' => null]);
        WorkflowStep::create(['workflow_id' => $workflowUKM->id, 'step_order' => 2, 'step_name' => 'Persetujuan Dekan', 'target_role_slug' => 'dekan', 'scope_type' => 'FACULTY_LEADER', 'target_category_lookup' => null]);

        $workflowBEM = Workflow::create(['name' => 'Pengajuan Program Kerja BEM', 'description' => 'Alur persetujuan program kerja BEM tingkat fakultas', 'applies_to_category' => 'BEM']);
        WorkflowStep::create(['workflow_id' => $workflowBEM->id, 'step_order' => 1, 'step_name' => 'Review Ketua BEM', 'target_role_slug' => 'ketua-bem', 'scope_type' => 'SELF', 'target_category_lookup' => null]);
        WorkflowStep::create(['workflow_id' => $workflowBEM->id, 'step_order' => 2, 'step_name' => 'Kajian Senat Fakultas', 'target_role_slug' => 'ketua-senat', 'scope_type' => 'SPECIFIC_CATEGORY', 'target_category_lookup' => 'SENAT']);
        WorkflowStep::create(['workflow_id' => $workflowBEM->id, 'step_order' => 3, 'step_name' => 'Persetujuan Dekan', 'target_role_slug' => 'dekan', 'scope_type' => 'FACULTY_LEADER', 'target_category_lookup' => null]);

        $workflowSenat = Workflow::create(['name' => 'Pengajuan Dana Senat', 'description' => 'Alur persetujuan pengajuan dana atau kegiatan senat', 'applies_to_category' => 'SENAT']);
        WorkflowStep::create(['workflow_id' => $workflowSenat->id, 'step_order' => 1, 'step_name' => 'Review Ketua Senat', 'target_role_slug' => 'ketua-senat', 'scope_type' => 'SELF', 'target_category_lookup' => null]);
        WorkflowStep::create(['workflow_id' => $workflowSenat->id, 'step_order' => 2, 'step_name' => 'Koordinasi BEM', 'target_role_slug' => 'ketua-bem', 'scope_type' => 'SPECIFIC_CATEGORY', 'target_category_lookup' => 'BEM']);
        WorkflowStep::create(['workflow_id' => $workflowSenat->id, 'step_order' => 3, 'step_name' => 'Persetujuan Dekan', 'target_role_slug' => 'dekan', 'scope_type' => 'FACULTY_LEADER', 'target_category_lookup' => null]);

        $this->command->info('✅ Seeder berhasil! 15 Roles, 10 Units, 14 Users, 4 Workflows');
        $this->command->info('📧 Login: dekan@ft.ac.id, senat@ft.ac.id, ketua.bem@student.ac.id (password: password)');
    }
}
