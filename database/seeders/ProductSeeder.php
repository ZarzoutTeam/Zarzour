<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $categoryIds = Category::query()->pluck('id', 'slug');

        $products = [
            // ملابس رياضية (sportswear)
            ['name' => 'قميص رياضي رجالي', 'slug' => 'mens-sport-shirt', 'category' => 'sportswear', 'price' => 85000, 'stock' => 120, 'description' => 'قميص رياضي رجالي بخامة قطنية مرنة، مناسب للتمارين والجري اليومي.'],
            ['name' => 'بنطال رياضي نسائي', 'slug' => 'womens-sport-pants', 'category' => 'sportswear', 'price' => 110000, 'stock' => 60, 'description' => 'بنطال رياضي نسائي مريح بخصر مطاطي، مناسب لتمارين اليوغا واللياقة.'],
            ['name' => 'جاكيت رياضي شتوي', 'slug' => 'winter-sport-jacket', 'category' => 'sportswear', 'price' => 195000, 'stock' => 25, 'description' => 'جاكيت رياضي شتوي مقاوم للرياح، مبطّن للدفء أثناء التمارين الخارجية.'],
            ['name' => 'طقم رياضي للأطفال', 'slug' => 'kids-sport-set', 'category' => 'sportswear', 'price' => 65000, 'stock' => 0, 'description' => 'طقم رياضي للأطفال يتكون من قميص وبنطال، خامة قطنية خفيفة.'],

            // أحذية رياضية (sports shoes)
            ['name' => 'حذاء جري رجالي', 'slug' => 'mens-running-shoes', 'category' => 'sports-shoes', 'price' => 320000, 'stock' => 45, 'description' => 'حذاء جري رجالي خفيف الوزن بنعل ماص للصدمات، مناسب للجري اليومي والمسافات الطويلة.'],
            ['name' => 'حذاء كرة قدم', 'slug' => 'football-shoes', 'category' => 'sports-shoes', 'price' => 285000, 'stock' => 30, 'description' => 'حذاء كرة قدم بمشابك خارجية توفر ثباتاً أفضل على الملعب.'],
            ['name' => 'حذاء تدريب نسائي', 'slug' => 'womens-training-shoes', 'category' => 'sports-shoes', 'price' => 250000, 'stock' => 8, 'description' => 'حذاء تدريب نسائي مرن مناسب لتمارين الكارديو وحصص اللياقة الجماعية.'],
            ['name' => 'حذاء مشي مريح', 'slug' => 'comfort-walking-shoes', 'category' => 'sports-shoes', 'price' => 210000, 'stock' => 0, 'description' => 'حذاء مشي مريح بنعل طبي داعم، مناسب للاستخدام اليومي الطويل.'],

            // معدات لياقة (fitness equipment)
            ['name' => 'بساط جري كهربائي', 'slug' => 'electric-treadmill', 'category' => 'fitness-equipment', 'price' => 4200000, 'stock' => 6, 'description' => 'بساط جري كهربائي منزلي بسرعات متعددة وشاشة تحكم رقمية، مناسب لتمارين الكارديو المنزلية.'],
            ['name' => 'جهاز كارديو بيضاوي (إليبتيكال)', 'slug' => 'elliptical-cardio-machine', 'category' => 'fitness-equipment', 'price' => 3800000, 'stock' => 4, 'description' => 'جهاز كارديو بيضاوي يعمل على كامل الجسم بمقاومة قابلة للتعديل وحركة سلسة قليلة الضغط على المفاصل.'],
            ['name' => 'جهاز قوة متعدد التمارين', 'slug' => 'multi-exercise-strength-machine', 'category' => 'fitness-equipment', 'price' => 5500000, 'stock' => 2, 'description' => 'جهاز قوة متعدد التمارين يغطي تمارين الصدر والظهر والأرجل بمحطة واحدة، مناسب للمنزل والصالات الصغيرة.'],
            ['name' => 'دراجة تمارين ثابتة', 'slug' => 'stationary-exercise-bike', 'category' => 'fitness-equipment', 'price' => 2100000, 'stock' => 10, 'description' => 'دراجة تمارين ثابتة بمقاومة مغناطيسية هادئة وشاشة لعرض المسافة والسعرات المحروقة.'],
            ['name' => 'بار حديد أوزان', 'slug' => 'olympic-barbell', 'category' => 'fitness-equipment', 'price' => 450000, 'stock' => 15, 'description' => 'بار حديد أولمبي قياسي لتمارين رفع الأثقال، متوافق مع أطباق الأوزان القياسية.'],
            ['name' => 'طقم أوزان دمبل قابلة للتعديل', 'slug' => 'adjustable-dumbbell-set', 'category' => 'fitness-equipment', 'price' => 980000, 'stock' => 20, 'description' => 'طقم دمبل قابل لتعديل الوزن يغطي مجموعة تمارين متنوعة بمساحة تخزين أقل.'],
            ['name' => 'جهاز مشي هوائي (إير بايك)', 'slug' => 'air-bike', 'category' => 'fitness-equipment', 'price' => 2600000, 'stock' => 0, 'description' => 'جهاز إير بايك يجمع تمارين الذراعين والأرجل معاً بمقاومة هوائية تتناسب مع شدة التمرين.'],

            // إكسسوارات (accessories)
            ['name' => 'حزام تمرين لدعم الظهر', 'slug' => 'weightlifting-back-support-belt', 'category' => 'accessories', 'price' => 55000, 'stock' => 40, 'description' => 'حزام دعم للظهر أثناء تمارين رفع الأثقال، يقلل خطر الإصابة ويحسّن الثبات.'],
            ['name' => 'قفازات رفع أثقال', 'slug' => 'weightlifting-gloves', 'category' => 'accessories', 'price' => 32000, 'stock' => 75, 'description' => 'قفازات رفع أثقال بحماية كف اليد، تمنع الاحتكاك وتحسّن قبضة اليد.'],
            ['name' => 'زجاجة مياه رياضية', 'slug' => 'sports-water-bottle', 'category' => 'accessories', 'price' => 18000, 'stock' => 200, 'description' => 'زجاجة مياه رياضية بسعة كبيرة وخامة آمنة، مزودة بعلامات لقياس الكمية المشروبة.'],
            ['name' => 'سجادة يوغا', 'slug' => 'yoga-mat', 'category' => 'accessories', 'price' => 48000, 'stock' => 55, 'description' => 'سجادة يوغا مانعة للانزلاق بسماكة مريحة، مناسبة لتمارين اليوغا والتمدد.'],
            ['name' => 'حقيبة رياضية', 'slug' => 'sports-duffel-bag', 'category' => 'accessories', 'price' => 75000, 'stock' => 3, 'description' => 'حقيبة رياضية واسعة بحجرة منفصلة للأحذية، مناسبة للتمارين والسفر القصير.'],
        ];

        $placeholderSource = $this->makePlaceholderImage();

        foreach ($products as $data) {
            $product = Product::updateOrCreate(
                ['slug' => $data['slug']],
                [
                    'name' => $data['name'],
                    'description' => $data['description'],
                    'price' => $data['price'],
                    'price_usd' => $data['price_usd'] ?? null,
                    'category_id' => $categoryIds[$data['category']],
                    'is_active' => true,
                    'extra_info' => null,
                    'stock_quantity' => $data['stock'],
                    'reserved_quantity' => 0,
                ],
            );

            if ($product->getMedia('images')->isEmpty()) {
                $productPlaceholder = tempnam(sys_get_temp_dir(), 'product-image-');
                File::copy($placeholderSource, $productPlaceholder);

                $product->addMedia($productPlaceholder)
                    ->usingFileName($data['slug'].'.png')
                    ->toMediaCollection('images');
            }
        }

        File::delete($placeholderSource);
    }

    /**
     * ينشئ صورة PNG بسيطة كصورة بديلة (placeholder) مؤقتة لكل منتج،
     * لعدم توفر صور فعلية للمنتجات المزروعة تجريبياً.
     */
    private function makePlaceholderImage(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'product-placeholder-').'.png';

        $image = imagecreatetruecolor(600, 600);
        $background = imagecolorallocate($image, 235, 235, 235);
        $text = imagecolorallocate($image, 120, 120, 120);
        imagefilledrectangle($image, 0, 0, 600, 600, $background);
        imagestring($image, 5, 220, 290, 'Zarzour Sport', $text);
        imagepng($image, $path);
        imagedestroy($image);

        return $path;
    }
}
