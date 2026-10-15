<?php

namespace Database\Seeders;

use App\Models\Commodity;
use Illuminate\Database\Seeder;

class CommoditySeeder extends Seeder
{
    /** Product portfolio from the AFISAB executive summary. */
    private const PORTFOLIO = [
        'grains' => ['Maize', 'Soybeans', 'Sorghum', 'Millet', 'Paddy Rice', 'Rice Bran', 'Acha/Fonio'],
        'oil_seeds' => ['Groundnuts', 'Sesame Seeds', 'Groundnut Cake (GNC)', 'Castor Seeds', 'Sunflower Seeds'],
        'tree_crops' => ['Shea Nuts', 'Cashew Nuts', 'Palm Oil', 'Palm Cake (PKC)'],
        'cash_crops' => ['Ginger', 'Hibiscus Flower', 'Dried Pepper', 'Sugarcane'],
    ];

    public function run(): void
    {
        foreach (self::PORTFOLIO as $category => $names) {
            foreach ($names as $name) {
                Commodity::firstOrCreate(['name' => $name], ['category' => $category, 'is_active' => true]);
            }
        }
    }
}
