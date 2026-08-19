<?php

namespace Tests\Feature;

use App\Filament\Resources\Products\Pages\EditProduct;
use App\Models\Discount;
use App\Models\Offer;
use App\Models\OfferGift;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_page_halts_deletion_and_explains_why_when_product_is_referenced(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super-admin');
        $this->actingAs($user);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::bootCurrentPanel();

        $product = Product::factory()->create();
        Discount::factory()->create(['product_id' => $product->id]);

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->callAction(DeleteAction::class)
            ->assertActionHalted()
            ->assertNotified('لا يمكن حذف المنتج');

        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    public function test_product_without_restricting_relations_can_be_deleted(): void
    {
        $product = Product::factory()->create();

        $this->assertFalse($product->hasDeletionBlockingRelations());
        $this->assertTrue($product->delete());
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_every_restricting_relation_is_detected_before_product_deletion(): void
    {
        $discountedProduct = Product::factory()->create();
        Discount::factory()->create(['product_id' => $discountedProduct->id]);

        $offeredProduct = Product::factory()->create();
        Offer::factory()->create(['product_id' => $offeredProduct->id]);

        $giftProduct = Product::factory()->create();
        $giftOffer = Offer::factory()->withGift()->create();
        OfferGift::create([
            'offer_id' => $giftOffer->id,
            'gift_product_id' => $giftProduct->id,
        ]);

        $orderedProduct = Product::factory()->create();
        OrderItem::create([
            'order_id' => Order::factory()->create()->id,
            'product_id' => $orderedProduct->id,
            'product_name_snapshot' => $orderedProduct->name,
            'unit_price_snapshot' => 10,
            'quantity' => 1,
            'line_total' => 10,
            'is_gift' => false,
        ]);

        $stockProduct = Product::factory()->create();
        StockMovement::create([
            'product_id' => $stockProduct->id,
            'type' => 'manual_adjustment',
            'quantity' => 1,
        ]);

        foreach ([
            $discountedProduct,
            $offeredProduct,
            $giftProduct,
            $orderedProduct,
            $stockProduct,
        ] as $product) {
            $this->assertTrue($product->hasDeletionBlockingRelations());
        }
    }
}
