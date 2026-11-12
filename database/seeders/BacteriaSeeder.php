<?php

namespace Database\Seeders;

use App\Models\Bacteria;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BacteriaSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the foodborne bacteria commonly behind food poisoning.
     */
    public function run(): void
    {
        $bacteria = [
            [
                'name' => 'Salmonella',
                'short_name' => 'Salmonella',
                'description' => 'Found in poultry, eggs, raw meat and produce; the classic cause of foodborne outbreaks.',
                'color' => '#E63946',
                'icon' => 'flagellum',
            ],
            [
                'name' => 'Escherichia coli',
                'short_name' => 'E. coli',
                'description' => 'Found in undercooked beef, raw milk, leafy greens and sprouts; some strains (O157:H7) cause severe illness.',
                'color' => '#F4A261',
                'icon' => 'rod',
            ],
            [
                'name' => 'Listeria monocytogenes',
                'short_name' => 'Listeria',
                'description' => 'Found in soft cheeses, deli meats, smoked fish and unpasteurized dairy; grows even at fridge temperatures.',
                'color' => '#6A4C93',
                'icon' => 'rod',
            ],
            [
                'name' => 'Campylobacter',
                'short_name' => 'Campylobacter',
                'description' => 'Found in raw chicken; one of the most common bacterial causes of gastroenteritis.',
                'color' => '#2A9D8F',
                'icon' => 'spiral',
            ],
            [
                'name' => 'Staphylococcus aureus',
                'short_name' => 'S. aureus',
                'description' => 'Found in handled foods left at room temperature, such as sandwiches, cream pastries and potato salad.',
                'color' => '#E9C46A',
                'icon' => 'cluster',
            ],
            [
                'name' => 'Bacillus cereus',
                'short_name' => 'B. cereus',
                'description' => 'Found in cooked rice and pasta left out at room temperature; causes the so-called fried rice syndrome.',
                'color' => '#8D99AE',
                'icon' => 'spore',
            ],
            [
                'name' => 'Clostridium perfringens',
                'short_name' => 'C. perfringens',
                'description' => 'Found in stews, gravies and meat dishes cooled slowly in bulk.',
                'color' => '#BC4749',
                'icon' => 'rod',
            ],
            [
                'name' => 'Clostridium botulinum',
                'short_name' => 'C. botulinum',
                'description' => 'Found in home-canned low-acid foods and honey; produces a toxin that causes botulism.',
                'color' => '#3D405B',
                'icon' => 'spore',
            ],
            [
                'name' => 'Vibrio',
                'short_name' => 'Vibrio',
                'description' => 'Found in raw or undercooked seafood, especially oysters; V. vulnificus is the most dangerous.',
                'color' => '#457B9D',
                'icon' => 'comma',
            ],
            [
                'name' => 'Yersinia enterocolitica',
                'short_name' => 'Y. enterocolitica',
                'description' => 'Found in pork and unpasteurized milk; causes enterocolitis and can mimic appendicitis.',
                'color' => '#588157',
                'icon' => 'rod',
            ],
        ];

        foreach ($bacteria as $attributes) {
            Bacteria::updateOrCreate(
                ['name' => $attributes['name']],
                $attributes,
            );
        }
    }
}
