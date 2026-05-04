<?php

use App\Models\Person;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Person::updateOrCreate(
            ['cif' => 'B26722488'],
            [
                'name' => 'SINOPERRO, S.L.',
                'address' => 'Carretera de Chile 89b A 3 A',
                'city' => 'Las Palmas de Gran Canaria',
                'postal_code' => '35010',
                'country' => 'Spain',
                'entity_type' => 'sociedad_limitada',
                'dni_nie' => null,
                'tax_regime' => 'canarias',
                'invoice_prefix' => 'SP',
                'next_invoice_number' => 1,
            ],
        );
    }

    public function down(): void
    {
        Person::where('cif', 'B26722488')->delete();
    }
};
