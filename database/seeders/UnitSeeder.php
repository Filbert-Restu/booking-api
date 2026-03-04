<?php

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Fakultas
        $fakultas = Unit::firstOrCreate(['code' => 'FSM'], [
            'name' => 'Fakultas Sains dan Matematika',
            'category' => 'FAKULTAS',
            'parent_id' => null
        ]);

        // Departemen (6)
        $deptStat = Unit::firstOrCreate(['code' => 'STAT'], ['name' => 'Departemen Statistika', 'category' => 'DEPARTEMEN', 'parent_id' => $fakultas->id]);
        $deptMath = Unit::firstOrCreate(['code' => 'MATH'], ['name' => 'Departemen Matematika', 'category' => 'DEPARTEMEN', 'parent_id' => $fakultas->id]);
        $deptFis = Unit::firstOrCreate(['code' => 'FIS'], ['name' => 'Departemen Fisika', 'category' => 'DEPARTEMEN', 'parent_id' => $fakultas->id]);
        $deptIF = Unit::firstOrCreate(['code' => 'IF'], ['name' => 'Departemen Informatika', 'category' => 'DEPARTEMEN', 'parent_id' => $fakultas->id]);
        $deptKim = Unit::firstOrCreate(['code' => 'KIM'], ['name' => 'Departemen Kimia', 'category' => 'DEPARTEMEN', 'parent_id' => $fakultas->id]);
        $deptBio = Unit::firstOrCreate(['code' => 'BIO'], ['name' => 'Departemen Biologi', 'category' => 'DEPARTEMEN', 'parent_id' => $fakultas->id]);

        // HMD - Himpunan Mahasiswa Departemen (6)
        Unit::firstOrCreate(['code' => 'HIMASTA'], ['name' => 'Himpunan Mahasiswa Statistika', 'category' => 'HMD', 'parent_id' => $deptStat->id]);
        Unit::firstOrCreate(['code' => 'HMMATH'], ['name' => 'Himpunan Mahasiswa Matematika', 'category' => 'HMD', 'parent_id' => $deptMath->id]);
        Unit::firstOrCreate(['code' => 'HMF'], ['name' => 'Himpunan Mahasiswa Fisika', 'category' => 'HMD', 'parent_id' => $deptFis->id]);
        Unit::firstOrCreate(['code' => 'HMIF'], ['name' => 'Himpunan Mahasiswa Informatika', 'category' => 'HMD', 'parent_id' => $deptIF->id]);
        Unit::firstOrCreate(['code' => 'HMK'], ['name' => 'Himpunan Mahasiswa Kimia', 'category' => 'HMD', 'parent_id' => $deptKim->id]);
        Unit::firstOrCreate(['code' => 'HMB'], ['name' => 'Himpunan Mahasiswa Biologi', 'category' => 'HMD', 'parent_id' => $deptBio->id]);

        // BEM
        Unit::firstOrCreate(['code' => 'BEM-FSM'], ['name' => 'Badan Eksekutif Mahasiswa', 'category' => 'BEM', 'parent_id' => $fakultas->id]);

        // Senat
        Unit::firstOrCreate(['code' => 'SENAT-FSM'], ['name' => 'Senat Mahasiswa', 'category' => 'SENAT', 'parent_id' => $fakultas->id]);

        // UKM - Unit Kegiatan Mahasiswa (6)
        Unit::firstOrCreate(['code' => 'MADANI'], ['name' => 'MADANI', 'category' => 'UKM', 'parent_id' => $fakultas->id]);
        Unit::firstOrCreate(['code' => 'PKM'], ['name' => 'PKM', 'category' => 'UKM', 'parent_id' => $fakultas->id]);
        Unit::firstOrCreate(['code' => 'PRMK'], ['name' => 'PRMK', 'category' => 'UKM', 'parent_id' => $fakultas->id]);
        Unit::firstOrCreate(['code' => 'RIC'], ['name' => 'RIC', 'category' => 'UKM', 'parent_id' => $fakultas->id]);
        Unit::firstOrCreate(['code' => 'POTLOT'], ['name' => 'POTLOT', 'category' => 'UKM', 'parent_id' => $fakultas->id]);
        Unit::firstOrCreate(['code' => 'VOSC'], ['name' => 'VOSC', 'category' => 'UKM', 'parent_id' => $fakultas->id]);

        $this->command->info('✅ Unit seeder berhasil dijalankan!');
    }
}
