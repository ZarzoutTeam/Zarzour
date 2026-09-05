<?php

namespace Tests\Feature;

use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Category;
use App\Models\HomepageSetting;
use App\Models\Product;
use App\Models\User;
use App\Support\CatalogImageUpload;
use Filament\Facades\Filament;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ProductEditingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Queue::fake();

        HomepageSetting::create([
            'usd_to_syp_rate' => 13520,
            'hero_media_type' => 'image',
            'hero_enabled' => false,
            'payment_methods' => ['cod'],
        ]);

        $user = User::factory()->create();
        $user->assignRole('super-admin');
        $this->actingAs($user);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::bootCurrentPanel();
    }

    public function test_product_media_reordering_is_scoped_to_the_edited_record(): void
    {
        $product = Product::factory()->create();
        $first = $product->addMedia(UploadedFile::fake()->image('first.jpg'))->toMediaCollection('images');
        $second = $product->addMedia(UploadedFile::fake()->image('second.jpg'))->toMediaCollection('images');
        $replacement = $product->addMedia(UploadedFile::fake()->image('replacement.jpg'))->toMediaCollection('images');

        $otherProduct = Product::factory()->create();
        $foreign = $otherProduct->addMedia(UploadedFile::fake()->image('foreign.jpg'))->toMediaCollection('images');

        CatalogImageUpload::reorderMedia($product, 'images', [
            $second->uuid,
            $replacement->uuid,
            $first->uuid,
            $foreign->uuid,
        ]);

        $this->assertSame(
            [$second->id, $replacement->id, $first->id],
            $product->fresh()->getMedia('images')->pluck('id')->all(),
        );
        $this->assertSame(1, $foreign->fresh()->order_column);
    }

    public function test_product_edit_saves_category_and_replacement_image_in_one_submission_and_returns_to_previous_page(): void
    {
        $originalCategory = Category::factory()->create();
        $newCategory = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $originalCategory->id]);
        $oldPrimary = $product->addMedia(UploadedFile::fake()->image('old-primary.jpg'))->toMediaCollection('images');
        $secondary = $product->addMedia(UploadedFile::fake()->image('secondary.jpg'))->toMediaCollection('images');
        $previousUrl = ProductResource::getUrl('index', [
            'page' => 3,
            'search' => 'جهاز',
        ]);

        $component = Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()]);
        $imagesFieldKey = $component->instance()->form
            ->getFlatFields(withHidden: true)['images']
            ->getKey();

        $component
            ->call('callSchemaComponentMethod', $imagesFieldKey, 'removeUploadedFile', [
                'fileKey' => $oldPrimary->uuid,
            ])
            ->set('previousUrl', $previousUrl)
            ->fillForm([
                'category_id' => $newCategory->id,
                'images' => [
                    'temporary-replacement-key' => UploadedFile::fake()->image('replacement.jpg', 1200, 800),
                    $secondary->uuid => $secondary->uuid,
                ],
            ])
            ->call('callSchemaComponentMethod', $imagesFieldKey, 'reorderUploadedFiles', [
                'fileKeys' => ['temporary-replacement-key', $secondary->uuid],
            ]);

        $component
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertRedirect($previousUrl);

        $product->refresh();
        $images = $product->getMedia('images');

        $this->assertSame($newCategory->id, $product->category_id);
        $this->assertCount(2, $images);
        $this->assertSame(['replacement', 'secondary'], $images->pluck('name')->all());
        $this->assertDatabaseMissing('media', ['id' => $oldPrimary->id]);
    }

    public function test_product_image_editor_uses_the_large_conversion(): void
    {
        $product = Product::factory()->create();

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->assertFormFieldExists(
                'images',
                static fn ($field): bool => $field instanceof SpatieMediaLibraryFileUpload
                    && $field->getConversion() === 'large',
            );
    }
}
