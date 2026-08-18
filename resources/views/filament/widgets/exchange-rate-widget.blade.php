<x-filament-widgets::widget class="zs-exchange-rate-widget">
    <x-filament::section>
        <div class="zs-exchange-rate-widget__layout">
            <div class="zs-exchange-rate-widget__summary">
                <div class="zs-exchange-rate-widget__icon" aria-hidden="true">
                    <x-filament::icon icon="heroicon-o-arrows-right-left" />
                </div>

                <div class="zs-exchange-rate-widget__copy">
                    <p class="zs-exchange-rate-widget__eyebrow">تسعير المنتجات</p>
                    <h2>سعر صرف الدولار</h2>
                    <p>يتغير السعر السوري لجميع المنتجات تلقائياً عند حفظ سعر صرف جديد.</p>
                </div>

                <div class="zs-exchange-rate-widget__current" aria-live="polite">
                    <span>السعر الحالي</span>

                    @if ($savedRate !== null)
                        <strong dir="ltr">
                            1 USD = {{ number_format($savedRate, 2) }} SYP
                        </strong>
                    @else
                        <strong>غير محدد بعد</strong>
                    @endif
                </div>
            </div>

            <form wire:submit="save" class="zs-exchange-rate-widget__form">
                {{ $this->form }}

                <x-filament::button
                    type="submit"
                    icon="heroicon-o-check-circle"
                    wire:loading.attr="disabled"
                    wire:target="save"
                >
                    <span wire:loading.remove wire:target="save">حفظ وتحديث الأسعار</span>
                    <span wire:loading wire:target="save">جارٍ التحديث...</span>
                </x-filament::button>
            </form>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
