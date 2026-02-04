<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Update user yang sudah ada di WorkflowSeeder dengan menambahkan NIM/NIP

        // Update Dosen (dengan NIP) - Wakil Dekan 1
        User::where('email', 'wadek1@fsm.undip.ac.id')->update(['nip' => '197001011995031001']);

        // Update Tendik (dengan NIP) - Kemahasiswaan & Sumber Daya
        User::where('email', 'kemahasiswaan@fsm.undip.ac.id')->update(['nip' => '198203152008122001']);
        User::where('email', 'sumberdaya@fsm.undip.ac.id')->update(['nip' => '198505202010121002']);

        // Update Ketua Departemen (dengan NIP) - semua 6 departemen
        User::where('email', 'kadept.stat@fsm.undip.ac.id')->update(['nip' => '197803201999032001']);
        User::where('email', 'kadept.math@fsm.undip.ac.id')->update(['nip' => '198001152000031001']);
        User::where('email', 'kadept.if@fsm.undip.ac.id')->update(['nip' => '197512102002121001']);
        User::where('email', 'kadept.fis@fsm.undip.ac.id')->update(['nip' => '197610202001121001']);
        User::where('email', 'kadept.kim@fsm.undip.ac.id')->update(['nip' => '197905152003121002']);
        User::where('email', 'kadept.bio@fsm.undip.ac.id')->update(['nip' => '198112252005011001']);

        // Update Dosen Pendamping HMD (dengan NIP)
        User::where('email', 'dospend.himasta@fsm.undip.ac.id')->update(['nip' => '198106152006042001']);
        User::where('email', 'dospend.hmmath@fsm.undip.ac.id')->update(['nip' => '197909102005011001']);
        User::where('email', 'dospend.hmf@fsm.undip.ac.id')->update(['nip' => '198204252008121002']);
        User::where('email', 'dospend.hmif@fsm.undip.ac.id')->update(['nip' => '198503122010121001']);
        User::where('email', 'dospend.hmk@fsm.undip.ac.id')->update(['nip' => '197708152003121001']);
        User::where('email', 'dospend.hmb@fsm.undip.ac.id')->update(['nip' => '198012202006042002']);

        // Update Dosen Pendamping UKM (dengan NIP)
        User::where('email', 'dospend.madani@fsm.undip.ac.id')->update(['nip' => '198208102008121003']);
        User::where('email', 'dospend.pkm@fsm.undip.ac.id')->update(['nip' => '197905152005011002']);
        User::where('email', 'dospend.prmk@fsm.undip.ac.id')->update(['nip' => '198306252009122001']);
        User::where('email', 'dospend.ric@fsm.undip.ac.id')->update(['nip' => '198007102007011001']);
        User::where('email', 'dospend.potlot@fsm.undip.ac.id')->update(['nip' => '198511202011122001']);
        User::where('email', 'dospend.vosc@fsm.undip.ac.id')->update(['nip' => '197802152002031002']);

        // Update Mahasiswa Ketua Ormawa HMD (dengan NIM)
        User::where('email', 'ketua.himasta@student.undip.ac.id')->update(['nim' => '24060121120001']);
        User::where('email', 'ketua.hmmath@student.undip.ac.id')->update(['nim' => '24060121120002']);
        User::where('email', 'ketua.hmf@student.undip.ac.id')->update(['nim' => '24060121120003']);
        User::where('email', 'ketua.hmif@student.undip.ac.id')->update(['nim' => '24060121120004']);
        User::where('email', 'ketua.hmk@student.undip.ac.id')->update(['nim' => '24060121120005']);
        User::where('email', 'ketua.hmb@student.undip.ac.id')->update(['nim' => '24060121120006']);

        // Update Ketua BEM (dengan NIM)
        User::where('email', 'ketua.bem@student.undip.ac.id')->update(['nim' => '24060121120007']);

        // Update Senat (dengan NIM)
        User::where('email', 'senat@student.undip.ac.id')->update(['nim' => '24060121120008']);

        // Update Sekretaris HMD (dengan NIM)
        User::where('email', 'sekretaris.himasta@student.undip.ac.id')->update(['nim' => '24060121130001']);
        User::where('email', 'sekretaris.hmmath@student.undip.ac.id')->update(['nim' => '24060121130002']);
        User::where('email', 'sekretaris.hmf@student.undip.ac.id')->update(['nim' => '24060121130003']);
        User::where('email', 'sekretaris.hmif@student.undip.ac.id')->update(['nim' => '24060121130004']);
        User::where('email', 'sekretaris.hmk@student.undip.ac.id')->update(['nim' => '24060121130005']);
        User::where('email', 'sekretaris.hmb@student.undip.ac.id')->update(['nim' => '24060121130006']);

        // Update Sekretaris BEM (dengan NIM)
        User::where('email', 'sekretaris.bem@student.undip.ac.id')->update(['nim' => '24060121130007']);

        // Update Mahasiswa Peminjam (dengan NIM)
        User::where('email', 'mahasiswa1@student.undip.ac.id')->update(['nim' => '24060122140001']);
        User::where('email', 'mahasiswa2@student.undip.ac.id')->update(['nim' => '24060122140002']);
        User::where('email', 'mahasiswa3@student.undip.ac.id')->update(['nim' => '24060122140003']);
        User::where('email', 'mahasiswa4@student.undip.ac.id')->update(['nim' => '24060122140004']);
        User::where('email', 'mahasiswa5@student.undip.ac.id')->update(['nim' => '24060122140005']);

        // Update Ketua UKM (dengan NIM)
        User::where('email', 'ketua.madani@student.undip.ac.id')->update(['nim' => '24060121110001']);
        User::where('email', 'ketua.pkm@student.undip.ac.id')->update(['nim' => '24060121110002']);
        User::where('email', 'ketua.prmk@student.undip.ac.id')->update(['nim' => '24060121110003']);
        User::where('email', 'ketua.ric@student.undip.ac.id')->update(['nim' => '24060121110004']);
        User::where('email', 'ketua.potlot@student.undip.ac.id')->update(['nim' => '24060121110005']);
        User::where('email', 'ketua.vosc@student.undip.ac.id')->update(['nim' => '24060121110006']);

        $this->command->info('✅ User seeder berhasil dijalankan!');
        $this->command->info('📊 Total users: ' . User::count());
        $this->command->info('👨‍🎓 Mahasiswa (NIM): ' . User::whereNotNull('nim')->count());
        $this->command->info('👨‍🏫 Dosen (NIP): ' . User::whereNotNull('nip')->count());
    }
}
