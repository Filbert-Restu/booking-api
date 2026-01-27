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
            ['name' => 'Admin', 'slug' => 'admin'],
            ['name' => 'Wakil Dekan 1', 'slug' => 'wadek1'],
            ['name' => 'Kemahasiswaan Fakultas', 'slug' => 'kemahasiswaan-fakultas'],
            ['name' => 'Sumber Daya Fakultas', 'slug' => 'sumber-daya-fakultas'],
            ['name' => 'Ketua Prodi', 'slug' => 'ketua-prodi'],
            ['name' => 'Pembimbing Ormawa', 'slug' => 'pembimbing-ormawa'],
            ['name' => 'Ketua Senat', 'slug' => 'ketua-senat'],
            ['name' => 'Ketua BEM', 'slug' => 'ketua-bem'],
            ['name' => 'Ketua HIMA', 'slug' => 'ketua-hima'],
            ['name' => 'Ketua UKM', 'slug' => 'ketua-ukm'],
            ['name' => 'Sekretaris Senat', 'slug' => 'sekretaris-senat'],
            ['name' => 'Sekretaris BEM', 'slug' => 'sekretaris-bem'],
            ['name' => 'Sekretaris HIMA', 'slug' => 'sekretaris-hima'],
            ['name' => 'Sekretaris UKM', 'slug' => 'sekretaris-ukm'],
            ['name' => 'Mahasiswa', 'slug' => 'mahasiswa'],
        ];

        foreach ($roles as $role) {
            Role::create($role);
        }

        // 2. UNITS
        $fakultas = Unit::create(['name' => 'Fakultas Teknik', 'code' => 'FT', 'category' => 'FAKULTAS', 'parent_id' => null]);
        $senat = Unit::create(['name' => 'Senat Fakultas Teknik', 'code' => 'SENAT-FT', 'category' => 'SENAT', 'parent_id' => $fakultas->id]);
        $prodiIF = Unit::create(['name' => 'Program Studi Informatika', 'code' => 'PRODI-IF', 'category' => 'PRODI', 'parent_id' => $fakultas->id]);
        $prodiTE = Unit::create(['name' => 'Program Studi Teknik Elektro', 'code' => 'PRODI-TE', 'category' => 'PRODI', 'parent_id' => $fakultas->id]);
        $prodiTS = Unit::create(['name' => 'Program Studi Teknik Sipil', 'code' => 'PRODI-TS', 'category' => 'PRODI', 'parent_id' => $fakultas->id]);
        $bemFT = Unit::create(['name' => 'BEM Fakultas Teknik', 'code' => 'BEM-FT', 'category' => 'BEM', 'parent_id' => $senat->id]);
        $himaIF = Unit::create(['name' => 'HIMA Informatika', 'code' => 'HIMA-IF', 'category' => 'HIMA', 'parent_id' => $prodiIF->id]);
        $himaTE = Unit::create(['name' => 'HIMA Teknik Elektro', 'code' => 'HIMA-TE', 'category' => 'HIMA', 'parent_id' => $prodiTE->id]);
        $himaTS = Unit::create(['name' => 'HIMA Teknik Sipil', 'code' => 'HIMA-TS', 'category' => 'HIMA', 'parent_id' => $prodiTS->id]);
        $ukmOlahraga = Unit::create(['name' => 'UKM Olahraga', 'code' => 'UKM-OR', 'category' => 'UKM', 'parent_id' => $fakultas->id]);

        // 3. USERS
        $roleadmin = Role::where('slug', 'admin')->first();
        $roleWadek1 = Role::where('slug', 'wadek1')->first();
        $roleKemahasiswaanFakultas = Role::where('slug', 'kemahasiswaan-fakultas')->first();
        $roleSumberDayaFakultas = Role::where('slug', 'sumber-daya-fakultas')->first();
        $roleKaprodi = Role::where('slug', 'ketua-prodi')->first();
        $rolePembimbingOrmawa = Role::where('slug', 'pembimbing-ormawa')->first();
        $roleKetuaSenat = Role::where('slug', 'ketua-senat')->first();
        $roleSekretarisSenat = Role::where('slug', 'sekretaris-senat')->first();
        $roleKetuaBEM = Role::where('slug', 'ketua-bem')->first();
        $roleSekretarisBEM = Role::where('slug', 'sekretaris-bem')->first();
        $roleKetuaHima = Role::where('slug', 'ketua-hima')->first();
        $roleSekretarisHima = Role::where('slug', 'sekretaris-hima')->first();
        $roleKetuaUKM = Role::where('slug', 'ketua-ukm')->first();
        $roleSekretarisUKM = Role::where('slug', 'sekretaris-ukm')->first();
        $roleMahasiswa = Role::where('slug', 'mahasiswa')->first();

        // Users diurutkan berdasarkan role
        // 1. Admin
        User::create(['name' => 'Mr. Adming', 'email' => 'admin@ft.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleadmin->id, 'unit_id' => $fakultas->id]);

        // 2. Wakil Dekan 1
        User::create(['name' => 'Prof. Dr. Budi Santoso', 'email' => 'wadek1@ft.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleWadek1->id, 'unit_id' => $fakultas->id]);

        // 3. Kemahasiswaan Fakultas
        User::create(['name' => 'Ibu Sari Dewi', 'email' => 'kemahasiswaan.fakultas@ft.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleKemahasiswaanFakultas->id, 'unit_id' => $fakultas->id]);

        // 4. Sumber Daya Fakultas
        User::create(['name' => 'Bapak Bapak', 'email' => 'sumberdaya.fakultas@ft.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleSumberDayaFakultas->id, 'unit_id' => $fakultas->id]);

        // 5. Ketua Prodi
        User::create(['name' => 'Dr. Siti Rahmawati', 'email' => 'kaprodi.if@ft.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleKaprodi->id, 'unit_id' => $prodiIF->id]);
        User::create(['name' => 'Dr. Bambang Suryadi', 'email' => 'kaprodi.te@ft.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleKaprodi->id, 'unit_id' => $prodiTE->id]);
        User::create(['name' => 'Dr. Agus Prasetyo', 'email' => 'kaprodi.ts@ft.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleKaprodi->id, 'unit_id' => $prodiTS->id]);

        // 6. Pembimbing Ormawa
        User::create(['name' => 'Bu Siapa', 'email' => 'pembimbingormawa@ft.ac.id', 'password' => Hash::make('password'), 'role_id' => $rolePembimbingOrmawa->id, 'unit_id' => $prodiIF->id]);

        // 7. Ketua Senat
        User::create(['name' => 'Andi Wijaya', 'email' => 'senat@students.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleKetuaSenat->id, 'unit_id' => $senat->id]);

        // 8. Ketua BEM
        User::create(['name' => 'Budi Setiawan', 'email' => 'ketua.bem@student.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleKetuaBEM->id, 'unit_id' => $bemFT->id]);

        // 9. Ketua HIMA
        User::create(['name' => 'Ahmad Rizki', 'email' => 'ketua.hima.if@student.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleKetuaHima->id, 'unit_id' => $himaIF->id]);
        User::create(['name' => 'Rudi Hartono', 'email' => 'ketua.hima.te@student.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleKetuaHima->id, 'unit_id' => $himaTE->id]);
        User::create(['name' => 'Fitri Handayani', 'email' => 'ketua.hima.ts@student.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleKetuaHima->id, 'unit_id' => $himaTS->id]);

        // 10. Ketua UKM
        User::create(['name' => 'Fajar Nugroho', 'email' => 'ketua.ukm@student.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleKetuaUKM->id, 'unit_id' => $ukmOlahraga->id]);

        // 11. Sekretaris Senat
        User::create(['name' => 'Dr. Putri Maharani', 'email' => 'sekretaris.senat@students.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleSekretarisSenat->id, 'unit_id' => $senat->id]);

        // 12. Sekretaris BEM
        User::create(['name' => 'Sinta Kusuma', 'email' => 'sekretaris.bem@student.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleSekretarisBEM->id, 'unit_id' => $bemFT->id]);

        // 13. Sekretaris HIMA
        User::create(['name' => 'Dewi Lestari', 'email' => 'sekretaris.hima.if@student.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleSekretarisHima->id, 'unit_id' => $himaIF->id]);

        // 14. Sekretaris UKM
        User::create(['name' => 'Sari Melati', 'email' => 'sekretaris.ukm@student.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleSekretarisUKM->id, 'unit_id' => $ukmOlahraga->id]);

        // 15. Mahasiswa
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
