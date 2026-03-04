<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Fetch necessary data
        $roleAdmin = Role::where('slug', 'admin')->first();
        $roleWadek1 = Role::where('slug', 'wadek1')->first();
        $roleKemahasiswaan = Role::where('slug', 'kemahasiswaan')->first();
        $roleSumberDaya = Role::where('slug', 'sumber-daya')->first();
        $roleKetuaDept = Role::where('slug', 'ketua-departemen')->first();
        $roleDosenPendamping = Role::where('slug', 'dosen-pendamping')->first();
        $roleKetuaOrmawa = Role::where('slug', 'ketua-ormawa')->first();
        $roleSekretaris = Role::where('slug', 'sekretaris')->first();
        $roleSenat = Role::where('slug', 'senat')->first();
        $this->command->info('👤 Seeding Users...');

        $fakultas = Unit::where('code', 'FSM')->first();
        $deptStat = Unit::where('code', 'STAT')->first();
        $deptMath = Unit::where('code', 'MATH')->first();
        $deptIF = Unit::where('code', 'IF')->first();
        $deptFis = Unit::where('code', 'FIS')->first();
        $deptKim = Unit::where('code', 'KIM')->first();
        $deptBio = Unit::where('code', 'BIO')->first();

        $himasta = Unit::where('code', 'HIMASTA')->first();
        $hmmath = Unit::where('code', 'HMMATH')->first();
        $hmf = Unit::where('code', 'HMF')->first();
        $hmif = Unit::where('code', 'HMIF')->first();
        $hmk = Unit::where('code', 'HMK')->first();
        $hmb = Unit::where('code', 'HMB')->first();

        $bem = Unit::where('code', 'BEM-FSM')->first();
        $senat = Unit::where('code', 'SENAT-FSM')->first();

        $madani = Unit::where('code', 'MADANI')->first();
        $pkm = Unit::where('code', 'PKM')->first();
        $prmk = Unit::where('code', 'PRMK')->first();
        $ric = Unit::where('code', 'RIC')->first();
        $potlot = Unit::where('code', 'POTLOT')->first();
        $vosc = Unit::where('code', 'VOSC')->first();

        $userData = [
            // Admin
            ['name' => 'Admin', 'email' => 'superadmin@fsm.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleAdmin->id, 'unit_id' => $fakultas->id, 'nim_nip' => null],
            
            // Wadek & Tendik
            ['name' => 'Prof. Dr. Budi Santoso', 'email' => 'wadek1@fsm.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleWadek1->id, 'unit_id' => $fakultas->id, 'nim_nip' => '197001011995031001'],
            ['name' => 'Ibu Sari Dewi', 'email' => 'kemahasiswaan@fsm.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleKemahasiswaan->id, 'unit_id' => $fakultas->id, 'nim_nip' => '198203152008122001'],
            ['name' => 'Bapak Ahmad', 'email' => 'sumberdaya@fsm.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleSumberDaya->id, 'unit_id' => $fakultas->id, 'nim_nip' => '198505202010121002'],

            // Kadept
            ['name' => 'Dr. Siti Rahmawati', 'email' => 'kadept.stat@fsm.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleKetuaDept->id, 'unit_id' => $deptStat->id, 'nim_nip' => '197803201999032001'],
            ['name' => 'Dr. Bambang Suryadi', 'email' => 'kadept.math@fsm.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleKetuaDept->id, 'unit_id' => $deptMath->id, 'nim_nip' => '198001152000031001'],
            ['name' => 'Dr. Agus Prasetyo', 'email' => 'kadept.if@fsm.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleKetuaDept->id, 'unit_id' => $deptIF->id, 'nim_nip' => '197512102002121001'],
            ['name' => 'Dr. Wahyu Hidayat', 'email' => 'kadept.fis@fsm.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleKetuaDept->id, 'unit_id' => $deptFis->id, 'nim_nip' => '197610202001121001'],
            ['name' => 'Dr. Nurul Huda', 'email' => 'kadept.kim@fsm.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleKetuaDept->id, 'unit_id' => $deptKim->id, 'nim_nip' => '197905152003121002'],
            ['name' => 'Dr. Imam Santoso', 'email' => 'kadept.bio@fsm.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleKetuaDept->id, 'unit_id' => $deptBio->id, 'nim_nip' => '198112252005011001'],

            // Dosen Pendamping HMD
            ['name' => 'Dr. Rina Kartika', 'email' => 'dospend.himasta@fsm.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleDosenPendamping->id, 'unit_id' => $himasta->id, 'nim_nip' => '198106152006042001'],
            ['name' => 'Dr. Fajar Nugroho', 'email' => 'dospend.hmmath@fsm.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleDosenPendamping->id, 'unit_id' => $hmmath->id, 'nim_nip' => '197909102005011001'],
            ['name' => 'Dr. Indra Wijaya', 'email' => 'dospend.hmf@fsm.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleDosenPendamping->id, 'unit_id' => $hmf->id, 'nim_nip' => '198204252008121002'],
            ['name' => 'Dr. Lestari Putri', 'email' => 'dospend.hmif@fsm.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleDosenPendamping->id, 'unit_id' => $hmif->id, 'nim_nip' => '198503122010121001'],
            ['name' => 'Dr. Hendra Kusuma', 'email' => 'dospend.hmk@fsm.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleDosenPendamping->id, 'unit_id' => $hmk->id, 'nim_nip' => '197708152003121001'],
            ['name' => 'Dr. Maya Sari', 'email' => 'dospend.hmb@fsm.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleDosenPendamping->id, 'unit_id' => $hmb->id, 'nim_nip' => '198012202006042002'],

            // Dosen Pendamping UKM
            ['name' => 'Dr. Lesti Wulandari', 'email' => 'dospend.senat@fsm.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleDosenPendamping->id, 'unit_id' => $senat->id, 'nim_nip' => '198208102008121010'],
            ['name' => 'Dr. Budi Petrus', 'email' => 'dospend.bemf@fsm.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleDosenPendamping->id, 'unit_id' => $bem->id, 'nim_nip' => '198208102008121004'],
            ['name' => 'Dr. Abdullah Aziz', 'email' => 'dospend.madani@fsm.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleDosenPendamping->id, 'unit_id' => $madani->id, 'nim_nip' => '198208102008121005'],
            ['name' => 'Dr. Petrus Santoso', 'email' => 'dospend.pkm@fsm.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleDosenPendamping->id, 'unit_id' => $pkm->id, 'nim_nip' => '197905152005011006'],
            ['name' => 'Dr. Maria Kristina', 'email' => 'dospend.prmk@fsm.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleDosenPendamping->id, 'unit_id' => $prmk->id, 'nim_nip' => '198306252009122007'],
            ['name' => 'Dr. Bambang Rianto', 'email' => 'dospend.ric@fsm.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleDosenPendamping->id, 'unit_id' => $ric->id, 'nim_nip' => '198007102007011008'],
            ['name' => 'Dr. Susi Purnama', 'email' => 'dospend.potlot@fsm.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleDosenPendamping->id, 'unit_id' => $potlot->id, 'nim_nip' => '198511202011122009'],
            ['name' => 'Dr. Yohanes Surya', 'email' => 'dospend.vosc@fsm.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleDosenPendamping->id, 'unit_id' => $vosc->id, 'nim_nip' => '197802152002031002'],

            // Ketua Ormawa HMD
            ['name' => 'Andi Wijaya', 'email' => 'ketua.himasta@student.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleKetuaOrmawa->id, 'unit_id' => $himasta->id, 'nim_nip' => '24060121120001'],
            ['name' => 'Budi Setiawan', 'email' => 'ketua.hmmath@student.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleKetuaOrmawa->id, 'unit_id' => $hmmath->id, 'nim_nip' => '24060121120002'],
            ['name' => 'Citra Dewi', 'email' => 'ketua.hmf@student.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleKetuaOrmawa->id, 'unit_id' => $hmf->id, 'nim_nip' => '24060121120003'],
            ['name' => 'Doni Prasetyo', 'email' => 'ketua.hmif@student.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleKetuaOrmawa->id, 'unit_id' => $hmif->id, 'nim_nip' => '24060121120004'],
            ['name' => 'Eka Putri', 'email' => 'ketua.hmk@student.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleKetuaOrmawa->id, 'unit_id' => $hmk->id, 'nim_nip' => '24060121120005'],
            ['name' => 'Faisal Rahman', 'email' => 'ketua.hmb@student.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleKetuaOrmawa->id, 'unit_id' => $hmb->id, 'nim_nip' => '24060121120006'],

            // Ketua BEM & Senat
            ['name' => 'Ahmad Rizki', 'email' => 'ketua.bem@student.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleKetuaOrmawa->id, 'unit_id' => $bem->id, 'nim_nip' => '24060121120007'],
            ['name' => 'Dewi Lestari', 'email' => 'ketua.senat@student.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleKetuaOrmawa->id, 'unit_id' => $senat->id, 'nim_nip' => '24060121120008'],

            // Ketua UKM
            ['name' => 'Gilang Ramadhan', 'email' => 'ketua.madani@student.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleKetuaOrmawa->id, 'unit_id' => $madani->id, 'nim_nip' => '24060121110001'],
            ['name' => 'Hanna Wijaya', 'email' => 'ketua.pkm@student.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleKetuaOrmawa->id, 'unit_id' => $pkm->id, 'nim_nip' => '24060121110002'],
            ['name' => 'Ignatius Budi', 'email' => 'ketua.prmk@student.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleKetuaOrmawa->id, 'unit_id' => $prmk->id, 'nim_nip' => '24060121110003'],
            ['name' => 'Jessica Tan', 'email' => 'ketua.ric@student.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleKetuaOrmawa->id, 'unit_id' => $ric->id, 'nim_nip' => '24060121110004'],
            ['name' => 'Kevin Pratama', 'email' => 'ketua.potlot@student.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleKetuaOrmawa->id, 'unit_id' => $potlot->id, 'nim_nip' => '24060121110005'],
            ['name' => 'Laura Angelina', 'email' => 'ketua.vosc@student.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleKetuaOrmawa->id, 'unit_id' => $vosc->id, 'nim_nip' => '24060121110006'],

            // Sekretaris HMD
            ['name' => 'Sinta Kusuma', 'email' => 'sekretaris.himasta@student.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleSekretaris->id, 'unit_id' => $himasta->id, 'nim_nip' => '24060121130001'],
            ['name' => 'Tari Anggraini', 'email' => 'sekretaris.hmmath@student.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleSekretaris->id, 'unit_id' => $hmmath->id, 'nim_nip' => '24060121130002'],
            ['name' => 'Umar Hakim', 'email' => 'sekretaris.hmf@student.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleSekretaris->id, 'unit_id' => $hmf->id, 'nim_nip' => '24060121130003'],
            ['name' => 'Vina Melati', 'email' => 'sekretaris.hmif@student.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleSekretaris->id, 'unit_id' => $hmif->id, 'nim_nip' => '24060121130004'],
            ['name' => 'Wawan Setiadi', 'email' => 'sekretaris.hmk@student.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleSekretaris->id, 'unit_id' => $hmk->id, 'nim_nip' => '24060121130005'],
            ['name' => 'Yuni Astuti', 'email' => 'sekretaris.hmb@student.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleSekretaris->id, 'unit_id' => $hmb->id, 'nim_nip' => '24060121130006'],

            // Sekretaris BEM, Senat & UKM
            ['name' => 'Putri Maharani', 'email' => 'sekretaris.bem@student.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleSekretaris->id, 'unit_id' => $bem->id, 'nim_nip' => '24060121130007'],
            ['name' => 'Zahra Amelia', 'email' => 'sekretaris.senat@student.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleSekretaris->id, 'unit_id' => $senat->id, 'nim_nip' => '24060121130008'],
            ['name' => 'Aisyah Putri', 'email' => 'sekretaris.madani@student.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleSekretaris->id, 'unit_id' => $madani->id, 'nim_nip' => '24060121130009'],
            ['name' => 'Bella Safitri', 'email' => 'sekretaris.pkm@student.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleSekretaris->id, 'unit_id' => $pkm->id, 'nim_nip' => '24060121130010'],
            ['name' => 'Cinta Ramadhani', 'email' => 'sekretaris.prmk@student.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleSekretaris->id, 'unit_id' => $prmk->id, 'nim_nip' => '24060121130011'],
            ['name' => 'Diana Sari', 'email' => 'sekretaris.ric@student.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleSekretaris->id, 'unit_id' => $ric->id, 'nim_nip' => '24060121130012'],
            ['name' => 'Eva Nurhasanah', 'email' => 'sekretaris.potlot@student.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleSekretaris->id, 'unit_id' => $potlot->id, 'nim_nip' => '24060121130013'],
            ['name' => 'Fania Dewi', 'email' => 'sekretaris.vosc@student.undip.ac.id', 'password' => Hash::make('password'), 'role_id' => $roleSekretaris->id, 'unit_id' => $vosc->id, 'nim_nip' => '24060121130014'],
        ];

        foreach ($userData as $user) {
            User::updateOrCreate(['email' => $user['email']], $user);
        }

        $this->command->info('✅ User seeder berhasil dijalankan!');
    }
}
