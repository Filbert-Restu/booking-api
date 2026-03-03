<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
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
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['slug' => $role['slug']], $role);
        }

        $this->command->info('✅ Role seeder berhasil dijalankan!');
    }
}
