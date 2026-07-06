<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'ملابس رياضية', 'slug' => 'sportswear', 'sort_order' => 1],
            ['name' => 'أحذية رياضية', 'slug' => 'sports-shoes', 'sort_order' => 2],
            ['name' => 'معدات لياقة', 'slug' => 'fitness-equipment', 'sort_order' => 3],
            ['name' => 'إكسسوارات', 'slug' => 'accessories', 'sort_order' => 4],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(
                ['slug' => $category['slug']],
                $category,
            );
        }
    }
}
