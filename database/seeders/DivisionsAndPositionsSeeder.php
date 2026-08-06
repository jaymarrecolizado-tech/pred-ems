<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DivisionsAndPositionsSeeder extends Seeder
{
    /**
     * Real DICT Regional Office 2 structure and position titles (from the
     * "Region 02 Employees Directory" tracker). Divisions cover the regional
     * office units and the five provincial offices; positions carry the
     * plantilla salary grades used in the tracker.
     */
    public function run(): void
    {
        $divisions = [
            ['code' => 'RO',   'name' => 'Regional Office 2'],
            ['code' => 'OAR',  'name' => 'Office of the Regional Director'],
            ['code' => 'AFD',  'name' => 'Administrative and Finance Division'],
            ['code' => 'TOD',  'name' => 'Technical Operations Division'],
            ['code' => 'CPO',  'name' => 'Cagayan Provincial Office'],
            ['code' => 'IPO',  'name' => 'Isabela Provincial Office'],
            ['code' => 'NVPO', 'name' => 'Nueva Vizcaya Provincial Office'],
            ['code' => 'QPO',  'name' => 'Quirino Provincial Office'],
            ['code' => 'BPO',  'name' => 'Batanes Provincial Office'],
        ];

        foreach ($divisions as $div) {
            DB::table('divisions')->updateOrInsert(
                ['code' => $div['code']],
                ['name' => $div['name'], 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]
            );
        }

        // [title, salary_grade, is_plantilla]
        $positions = [
            ['Director IV', 28, true],
            ['Information Technology Officer III', 24, true],
            ['Chief Administrative Officer', 24, true],
            ['Information Technology Officer II', 22, true],
            ['Information Technology Officer I', 19, true],
            ['Accountant III', 19, true],
            ['Engineer III', 19, true],
            ['Information Systems Analyst III', 19, true],
            ['Information Systems Analyst II', 16, true],
            ['Engineer II', 16, true],
            ['Information Officer II', 15, true],
            ['Planning Officer II', 15, true],
            ['Project Development Officer III', 18, true],
            ['Budget Officer II', 15, true],
            ['Human Resource Management Officer II', 15, true],
            ['Project Development Officer II', 15, true],
            ['Computer Maintenance Technologist II', 15, true],
            ['Cashier II', 14, true],
            ['Information Systems Analyst I', 12, true],
            ['Engineer I', 12, true],
            ['Administrative Officer II', 11, true],
            ['Administrative Officer II (HRMO I)', 11, true],
            ['Budget Officer I', 11, true],
            ['Information Officer I', 11, true],
            ['Planning Officer I', 11, true],
            ['Procurement Management Officer I', 11, true],
            ['Project Development Officer I', 11, true],
            ['Administrative Officer I (Records Officer I)', 10, true],
            ['Administrative Officer I (Cashier I)', 10, true],
            ['Administrative Officer I (Supply Officer I)', 10, true],
            ['Administrative Assistant III', 9, true],
            ['Communication Equipment Operator III', 9, true],
            ['Administrative Assistant II', 8, true],
            ['Communication Equipment Operator II', 6, true],
            ['Electronic Communication Equipment Technician I', 6, true],
            ['Media Production Officer II', 6, true],
            ['Administrative Aide IV', 4, true],
            ['Administrative Aide IV (Driver II)', 4, true],
            ['Project Assistant I', null, false],
            ['GIP - Intern', null, false],
        ];

        foreach ($positions as [$title, $grade, $isPlantilla]) {
            DB::table('positions')->updateOrInsert(
                ['title' => $title],
                [
                    'salary_grade' => $grade,
                    'level' => null,
                    'is_plantilla' => $isPlantilla,
                    'plantilla_item_no' => null,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
