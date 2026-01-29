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
            ['name' => 'Dosen Pendamping Himpunan', 'slug' => 'dosen-pendamping'],
            ['name' => 'Ketua Departemen', 'slug' => 'ketua-departemen'],
            ['name' => 'Ketua Ormawa', 'slug' => 'ketua-ormawa'],
            ['name' => 'Sekretaris', 'slug' => 'sekretaris'],
            ['name' => 'Senat', 'slug' => 'senat'],
            ['name' => 'Kemahasiswaan', 'slug' => 'kemahasiswaan'],
            ['name' => 'Sumber Daya', 'slug' => 'sumber-daya'],
            ['name' => 'Wakil Dekan 1', 'slug' => 'wadek1'],
            ['name' => 'Peminjam', 'slug' => 'peminjam'],
        ];

        foreach ($roles as $role) {
            Role::create($role);
        }

        // 2. UNITS
        // Fakultas
        $fakultas = Unit::create([
            'name' => 'Fakultas Sains dan Matematika',
            'code' => 'FSM',
            'category' => 'FAKULTAS',
            'parent_id' => null
        ]);

        // Departemen (6)
        $deptStat = Unit::create(['name' => 'Departemenra Statistika', 'code' => 'STAT', 'category' => 'DEPARTEMEN', 'parent_id' => $fakultas->id]);
        $deptMath = Unit::create(['name' => 'Departemen Matematika', 'code' => 'MATH', 'category' => 'DEPARTEMEN', 'parent_id' => $fakultas->id]);
        $deptFis = Unit::create(['name' => 'Departemen Fisika', 'code' => 'FIS', 'category' => 'DEPARTEMEN', 'parent_id' => $fakultas->id]);
        $deptIF = Unit::create(['name' => 'Departemen Informatika', 'code' => 'IF', 'category' => 'DEPARTEMEN', 'parent_id' => $fakultas->id]);
        $deptKim = Unit::create(['name' => 'Departemen Kimia', 'code' => 'KIM', 'category' => 'DEPARTEMEN', 'parent_id' => $fakultas->id]);
        $deptBio = Unit::create(['name' => 'Departemen Biologi', 'code' => 'BIO', 'category' => 'DEPARTEMEN', 'parent_id' => $fakultas->id]);

        // HMD - Himpunan Mahasiswa Departemen (6)
        $himasta = Unit::create(['name' => 'Himpunan Mahasiswa Statistika', 'code' => 'HIMASTA', 'category' => 'HMD', 'parent_id' => $deptStat->id]);
        $hmmath = Unit::create(['name' => 'Himpunan Mahasiswa Matematika', 'code' => 'HMMATH', 'category' => 'HMD', 'parent_id' => $deptMath->id]);
        $hmf = Unit::create(['name' => 'Himpunan Mahasiswa Fisika', 'code' => 'HMF', 'category' => 'HMD', 'parent_id' => $deptFis->id]);
        $hmif = Unit::create(['name' => 'Himpunan Mahasiswa Informatika', 'code' => 'HMIF', 'category' => 'HMD', 'parent_id' => $deptIF->id]);
        $hmk = Unit::create(['name' => 'Himpunan Mahasiswa Kimia', 'code' => 'HMK', 'category' => 'HMD', 'parent_id' => $deptKim->id]);
        $hmb = Unit::create(['name' => 'Himpunan Mahasiswa Biologi', 'code' => 'HMB', 'category' => 'HMD', 'parent_id' => $deptBio->id]);

        // BEM
        $bem = Unit::create(['name' => 'BEM FSM', 'code' => 'BEM-FSM', 'category' => 'BEM', 'parent_id' => $fakultas->id]);

        // Senat
        $senat = Unit::create(['name' => 'Senat FSM', 'code' => 'SENAT-FSM', 'category' => 'SENAT', 'parent_id' => $fakultas->id]);

        // UKM - Unit Kegiatan Mahasiswa (6)
        $madani = Unit::create(['name' => 'MADANI', 'code' => 'MADANI', 'category' => 'UKM', 'parent_id' => $fakultas->id]);
        $pkm = Unit::create(['name' => 'PKM', 'code' => 'PKM', 'category' => 'UKM', 'parent_id' => $fakultas->id]);
        $prmk = Unit::create(['name' => 'PRMK', 'code' => 'PRMK', 'category' => 'UKM', 'parent_id' => $fakultas->id]);
        $ric = Unit::create(['name' => 'RIC', 'code' => 'RIC', 'category' => 'UKM', 'parent_id' => $fakultas->id]);
        $potlot = Unit::create(['name' => 'POTLOT', 'code' => 'POTLOT', 'category' => 'UKM', 'parent_id' => $fakultas->id]);
        $vosc = Unit::create(['name' => 'VOSC', 'code' => 'VOSC', 'category' => 'UKM', 'parent_id' => $fakultas->id]);

        // 3. USERS
        $roleAdmin = Role::where('slug', 'admin')->first();
        $roleWadek1 = Role::where('slug', 'wadek1')->first();
        $roleKemahasiswaan = Role::where('slug', 'kemahasiswaan')->first();
        $roleSumberDaya = Role::where('slug', 'sumber-daya')->first();
        $roleKetuaDept = Role::where('slug', 'ketua-departemen')->first();
        $roleDosenPendamping = Role::where('slug', 'dosen-pendamping')->first();
        $roleKetuaOrmawa = Role::where('slug', 'ketua-ormawa')->first();
        $roleSekretaris = Role::where('slug', 'sekretaris')->first();
        $roleSenat = Role::where('slug', 'senat')->first();
        $rolePeminjam = Role::where('slug', 'peminjam')->first();

        // Admin
        User::create(['name' => 'Admin FSM', 'email' => 'admin@fsm.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleAdmin->id, 'unit_id' => $fakultas->id]);

        // Wakil Dekan 1
        User::create(['name' => 'Prof. Dr. Budi Santoso', 'email' => 'wadek1@fsm.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleWadek1->id, 'unit_id' => $fakultas->id]);

        // Kemahasiswaan
        User::create(['name' => 'Ibu Sari Dewi', 'email' => 'kemahasiswaan@fsm.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleKemahasiswaan->id, 'unit_id' => $fakultas->id]);

        // Sumber Daya
        User::create(['name' => 'Bapak Ahmad', 'email' => 'sumberdaya@fsm.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleSumberDaya->id, 'unit_id' => $fakultas->id]);

        // Ketua Departemen (3 sample)
        User::create(['name' => 'Dr. Siti Rahmawati', 'email' => 'kadept.stat@fsm.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleKetuaDept->id, 'unit_id' => $deptStat->id]);
        User::create(['name' => 'Dr. Bambang Suryadi', 'email' => 'kadept.math@fsm.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleKetuaDept->id, 'unit_id' => $deptMath->id]);
        User::create(['name' => 'Dr. Agus Prasetyo', 'email' => 'kadept.if@fsm.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleKetuaDept->id, 'unit_id' => $deptIF->id]);

        // Dosen Pendamping untuk setiap HMD (6)
        User::create(['name' => 'Dr. Rina Kartika', 'email' => 'dospend.himasta@fsm.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleDosenPendamping->id, 'unit_id' => $himasta->id]);
        User::create(['name' => 'Dr. Fajar Nugroho', 'email' => 'dospend.hmmath@fsm.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleDosenPendamping->id, 'unit_id' => $hmmath->id]);
        User::create(['name' => 'Dr. Indra Wijaya', 'email' => 'dospend.hmf@fsm.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleDosenPendamping->id, 'unit_id' => $hmf->id]);
        User::create(['name' => 'Dr. Lestari Putri', 'email' => 'dospend.hmif@fsm.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleDosenPendamping->id, 'unit_id' => $hmif->id]);
        User::create(['name' => 'Dr. Hendra Kusuma', 'email' => 'dospend.hmk@fsm.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleDosenPendamping->id, 'unit_id' => $hmk->id]);
        User::create(['name' => 'Dr. Maya Sari', 'email' => 'dospend.hmb@fsm.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleDosenPendamping->id, 'unit_id' => $hmb->id]);

        // Ketua Ormawa untuk setiap HMD (6)
        User::create(['name' => 'Andi Wijaya', 'email' => 'ketua.himasta@student.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleKetuaOrmawa->id, 'unit_id' => $himasta->id]);
        User::create(['name' => 'Budi Setiawan', 'email' => 'ketua.hmmath@student.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleKetuaOrmawa->id, 'unit_id' => $hmmath->id]);
        User::create(['name' => 'Citra Dewi', 'email' => 'ketua.hmf@student.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleKetuaOrmawa->id, 'unit_id' => $hmf->id]);
        User::create(['name' => 'Doni Prasetyo', 'email' => 'ketua.hmif@student.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleKetuaOrmawa->id, 'unit_id' => $hmif->id]);
        User::create(['name' => 'Eka Putri', 'email' => 'ketua.hmk@student.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleKetuaOrmawa->id, 'unit_id' => $hmk->id]);
        User::create(['name' => 'Faisal Rahman', 'email' => 'ketua.hmb@student.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleKetuaOrmawa->id, 'unit_id' => $hmb->id]);

        // Ketua BEM
        User::create(['name' => 'Ahmad Rizki', 'email' => 'ketua.bem@student.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleKetuaOrmawa->id, 'unit_id' => $bem->id]);

        // Senat
        User::create(['name' => 'Dewi Lestari', 'email' => 'senat@student.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleSenat->id, 'unit_id' => $senat->id]);

        // Sekretaris untuk setiap HMD (6)
        User::create(['name' => 'Sinta Kusuma', 'email' => 'sekretaris.himasta@student.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleSekretaris->id, 'unit_id' => $himasta->id]);
        User::create(['name' => 'Tari Anggraini', 'email' => 'sekretaris.hmmath@student.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleSekretaris->id, 'unit_id' => $hmmath->id]);
        User::create(['name' => 'Umar Hakim', 'email' => 'sekretaris.hmf@student.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleSekretaris->id, 'unit_id' => $hmf->id]);
        User::create(['name' => 'Vina Melati', 'email' => 'sekretaris.hmif@student.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleSekretaris->id, 'unit_id' => $hmif->id]);
        User::create(['name' => 'Wawan Setiadi', 'email' => 'sekretaris.hmk@student.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleSekretaris->id, 'unit_id' => $hmk->id]);
        User::create(['name' => 'Yuni Astuti', 'email' => 'sekretaris.hmb@student.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleSekretaris->id, 'unit_id' => $hmb->id]);

        // Sekretaris BEM
        User::create(['name' => 'Putri Maharani', 'email' => 'sekretaris.bem@student.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleSekretaris->id, 'unit_id' => $bem->id]);

        // Peminjam (Mahasiswa) - 2 sample
        User::create(['name' => 'Rudi Hartono', 'email' => 'mahasiswa1@student.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $rolePeminjam->id, 'unit_id' => $hmif->id]);
        User::create(['name' => 'Fitri Handayani', 'email' => 'mahasiswa2@student.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $rolePeminjam->id, 'unit_id' => $himasta->id]);

        // === UKM USERS ===
        // Dosen Pendamping UKM (6)
        User::create(['name' => 'Dr. Abdullah Aziz', 'email' => 'dospend.madani@fsm.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleDosenPendamping->id, 'unit_id' => $madani->id]);
        User::create(['name' => 'Dr. Petrus Santoso', 'email' => 'dospend.pkm@fsm.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleDosenPendamping->id, 'unit_id' => $pkm->id]);
        User::create(['name' => 'Dr. Maria Kristina', 'email' => 'dospend.prmk@fsm.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleDosenPendamping->id, 'unit_id' => $prmk->id]);
        User::create(['name' => 'Dr. Bambang Rianto', 'email' => 'dospend.ric@fsm.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleDosenPendamping->id, 'unit_id' => $ric->id]);
        User::create(['name' => 'Dr. Susi Purnama', 'email' => 'dospend.potlot@fsm.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleDosenPendamping->id, 'unit_id' => $potlot->id]);
        User::create(['name' => 'Dr. Yohanes Surya', 'email' => 'dospend.vosc@fsm.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleDosenPendamping->id, 'unit_id' => $vosc->id]);

        // Ketua UKM (6)
        User::create(['name' => 'Gilang Ramadhan', 'email' => 'ketua.madani@student.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleKetuaOrmawa->id, 'unit_id' => $madani->id]);
        User::create(['name' => 'Hanna Wijaya', 'email' => 'ketua.pkm@student.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleKetuaOrmawa->id, 'unit_id' => $pkm->id]);
        User::create(['name' => 'Ignatius Budi', 'email' => 'ketua.prmk@student.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleKetuaOrmawa->id, 'unit_id' => $prmk->id]);
        User::create(['name' => 'Jessica Tan', 'email' => 'ketua.ric@student.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleKetuaOrmawa->id, 'unit_id' => $ric->id]);
        User::create(['name' => 'Kevin Pratama', 'email' => 'ketua.potlot@student.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleKetuaOrmawa->id, 'unit_id' => $potlot->id]);
        User::create(['name' => 'Laura Angelina', 'email' => 'ketua.vosc@student.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleKetuaOrmawa->id, 'unit_id' => $vosc->id]);

        // Sekretaris UKM (6)
        User::create(['name' => 'Muhammad Faisal', 'email' => 'sekretaris.madani@student.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleSekretaris->id, 'unit_id' => $madani->id]);
        User::create(['name' => 'Natalia Grace', 'email' => 'sekretaris.pkm@student.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleSekretaris->id, 'unit_id' => $pkm->id]);
        User::create(['name' => 'Olivia Theresia', 'email' => 'sekretaris.prmk@student.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleSekretaris->id, 'unit_id' => $prmk->id]);
        User::create(['name' => 'Patricia Lim', 'email' => 'sekretaris.ric@student.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleSekretaris->id, 'unit_id' => $ric->id]);
        User::create(['name' => 'Qori Aisyah', 'email' => 'sekretaris.potlot@student.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleSekretaris->id, 'unit_id' => $potlot->id]);
        User::create(['name' => 'Rizky Aditya', 'email' => 'sekretaris.vosc@student.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleSekretaris->id, 'unit_id' => $vosc->id]);

        // 4. WORKFLOWS
        // Workflow untuk HMD (Himpunan Mahasiswa Departemen)
        // Alur: Ketua HMD -> Senat -> Dospen -> Ketua Dept -> Kemahasiswaan -> Wadek 1 -> Sumber Daya
        $workflowHMD = Workflow::create([
            'name' => 'Peminjaman Ruang HMD',
            'description' => 'Alur persetujuan peminjaman ruang untuk kegiatan HMD',
            'applies_to_category' => 'HMD'
        ]);
        WorkflowStep::create(['workflow_id' => $workflowHMD->id, 'step_order' => 1, 'step_name' => 'Review Ketua HMD', 'target_role_slug' => 'ketua-ormawa', 'scope_type' => 'SELF', 'target_category_lookup' => null]);
        WorkflowStep::create(['workflow_id' => $workflowHMD->id, 'step_order' => 2, 'step_name' => 'Kajian Senat FSM', 'target_role_slug' => 'senat', 'scope_type' => 'SPECIFIC_CATEGORY', 'target_category_lookup' => 'Senat']);
        WorkflowStep::create(['workflow_id' => $workflowHMD->id, 'step_order' => 3, 'step_name' => 'Persetujuan Dosen Pendamping', 'target_role_slug' => 'dosen-pendamping', 'scope_type' => 'SELF', 'target_category_lookup' => null]);
        WorkflowStep::create(['workflow_id' => $workflowHMD->id, 'step_order' => 4, 'step_name' => 'Review Ketua Departemen', 'target_role_slug' => 'ketua-departemen', 'scope_type' => 'PARENT', 'target_category_lookup' => null]);
        WorkflowStep::create(['workflow_id' => $workflowHMD->id, 'step_order' => 5, 'step_name' => 'Persetujuan Kemahasiswaan', 'target_role_slug' => 'kemahasiswaan', 'scope_type' => 'FACULTY_LEADER', 'target_category_lookup' => null]);
        WorkflowStep::create(['workflow_id' => $workflowHMD->id, 'step_order' => 6, 'step_name' => 'Persetujuan Wadek 1', 'target_role_slug' => 'wadek1', 'scope_type' => 'FACULTY_LEADER', 'target_category_lookup' => null]);
        WorkflowStep::create(['workflow_id' => $workflowHMD->id, 'step_order' => 7, 'step_name' => 'Konfirmasi Sumber Daya', 'target_role_slug' => 'sumber-daya', 'scope_type' => 'FACULTY_LEADER', 'target_category_lookup' => null]);

        // Workflow untuk BEM
        // Alur: Ketua BEM -> Senat -> Dospen -> Kemahasiswaan -> Wadek 1 -> Sumber Daya
        $workflowBEM = Workflow::create([
            'name' => 'Peminjaman Ruang BEM',
            'description' => 'Alur persetujuan peminjaman ruang untuk kegiatan BEM FSM',
            'applies_to_category' => 'BEM'
        ]);
        WorkflowStep::create(['workflow_id' => $workflowBEM->id, 'step_order' => 1, 'step_name' => 'Review Ketua BEM', 'target_role_slug' => 'ketua-ormawa', 'scope_type' => 'SELF', 'target_category_lookup' => null]);
        WorkflowStep::create(['workflow_id' => $workflowBEM->id, 'step_order' => 2, 'step_name' => 'Kajian Senat FSM', 'target_role_slug' => 'senat', 'scope_type' => 'SPECIFIC_CATEGORY', 'target_category_lookup' => 'Senat']);
        WorkflowStep::create(['workflow_id' => $workflowBEM->id, 'step_order' => 3, 'step_name' => 'Persetujuan Dosen Pendamping', 'target_role_slug' => 'dosen-pendamping', 'scope_type' => 'SELF', 'target_category_lookup' => null]);
        WorkflowStep::create(['workflow_id' => $workflowBEM->id, 'step_order' => 4, 'step_name' => 'Persetujuan Kemahasiswaan', 'target_role_slug' => 'kemahasiswaan', 'scope_type' => 'FACULTY_LEADER', 'target_category_lookup' => null]);
        WorkflowStep::create(['workflow_id' => $workflowBEM->id, 'step_order' => 5, 'step_name' => 'Persetujuan Wadek 1', 'target_role_slug' => 'wadek1', 'scope_type' => 'FACULTY_LEADER', 'target_category_lookup' => null]);
        WorkflowStep::create(['workflow_id' => $workflowBEM->id, 'step_order' => 6, 'step_name' => 'Konfirmasi Sumber Daya', 'target_role_slug' => 'sumber-daya', 'scope_type' => 'FACULTY_LEADER', 'target_category_lookup' => null]);

        // Workflow untuk Senat
        // Alur: Ketua Senat -> Dospen -> Kemahasiswaan -> Wadek 1 -> Sumber Daya
        $workflowSenat = Workflow::create([
            'name' => 'Peminjaman Ruang Senat',
            'description' => 'Alur persetujuan peminjaman ruang untuk kegiatan Senat FSM',
            'applies_to_category' => 'Senat'
        ]);
        WorkflowStep::create(['workflow_id' => $workflowSenat->id, 'step_order' => 1, 'step_name' => 'Review Senat', 'target_role_slug' => 'senat', 'scope_type' => 'SELF', 'target_category_lookup' => null]);
        WorkflowStep::create(['workflow_id' => $workflowSenat->id, 'step_order' => 2, 'step_name' => 'Persetujuan Dosen Pendamping', 'target_role_slug' => 'dosen-pendamping', 'scope_type' => 'SELF', 'target_category_lookup' => null]);
        WorkflowStep::create(['workflow_id' => $workflowSenat->id, 'step_order' => 3, 'step_name' => 'Persetujuan Kemahasiswaan', 'target_role_slug' => 'kemahasiswaan', 'scope_type' => 'FACULTY_LEADER', 'target_category_lookup' => null]);
        WorkflowStep::create(['workflow_id' => $workflowSenat->id, 'step_order' => 4, 'step_name' => 'Persetujuan Wadek 1', 'target_role_slug' => 'wadek1', 'scope_type' => 'FACULTY_LEADER', 'target_category_lookup' => null]);
        WorkflowStep::create(['workflow_id' => $workflowSenat->id, 'step_order' => 5, 'step_name' => 'Konfirmasi Sumber Daya', 'target_role_slug' => 'sumber-daya', 'scope_type' => 'FACULTY_LEADER', 'target_category_lookup' => null]);

        // Workflow untuk UKM
        // Alur: Ketua UKM -> Senat -> Dospen -> Kemahasiswaan -> Wadek 1 -> Sumber Daya
        $workflowUKM = Workflow::create([
            'name' => 'Peminjaman Ruang UKM',
            'description' => 'Alur persetujuan peminjaman ruang untuk kegiatan UKM',
            'applies_to_category' => 'UKM'
        ]);
        WorkflowStep::create(['workflow_id' => $workflowUKM->id, 'step_order' => 1, 'step_name' => 'Review Ketua UKM', 'target_role_slug' => 'ketua-ormawa', 'scope_type' => 'SELF', 'target_category_lookup' => null]);
        WorkflowStep::create(['workflow_id' => $workflowUKM->id, 'step_order' => 2, 'step_name' => 'Kajian Senat FSM', 'target_role_slug' => 'senat', 'scope_type' => 'SPECIFIC_CATEGORY', 'target_category_lookup' => 'Senat']);
        WorkflowStep::create(['workflow_id' => $workflowUKM->id, 'step_order' => 3, 'step_name' => 'Persetujuan Dosen Pendamping', 'target_role_slug' => 'dosen-pendamping', 'scope_type' => 'SELF', 'target_category_lookup' => null]);
        WorkflowStep::create(['workflow_id' => $workflowUKM->id, 'step_order' => 4, 'step_name' => 'Persetujuan Kemahasiswaan', 'target_role_slug' => 'kemahasiswaan', 'scope_type' => 'FACULTY_LEADER', 'target_category_lookup' => null]);
        WorkflowStep::create(['workflow_id' => $workflowUKM->id, 'step_order' => 5, 'step_name' => 'Persetujuan Wadek 1', 'target_role_slug' => 'wadek1', 'scope_type' => 'FACULTY_LEADER', 'target_category_lookup' => null]);
        WorkflowStep::create(['workflow_id' => $workflowUKM->id, 'step_order' => 6, 'step_name' => 'Konfirmasi Sumber Daya', 'target_role_slug' => 'sumber-daya', 'scope_type' => 'FACULTY_LEADER', 'target_category_lookup' => null]);

        $this->command->info('✅ Seeder berhasil!');
        $this->command->info('📊 10 Roles | 21 Units | 48 Users | 4 Workflows (HMD, BEM, Senat, UKM)');
        $this->command->info('📧 Login credentials (password: password):');
        $this->command->info('   - Admin: admin@fsm.undip.ac.id');
        $this->command->info('   - Wadek1: wadek1@fsm.undip.ac.id');
        $this->command->info('   - Kemahasiswaan: kemahasiswaan@fsm.undip.ac.id');
        $this->command->info('   - Sumber Daya: sumberdaya@fsm.undip.ac.id');
        $this->command->info('   - Senat: senat@student.undip.ac.id');
        $this->command->info('   - Ketua HMIF: ketua.hmif@student.undip.ac.id');
        $this->command->info('   - Ketua MADANI: ketua.madani@student.undip.ac.id');
    }
}
