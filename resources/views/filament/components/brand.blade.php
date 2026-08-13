@props(['compact' => false])

@if ($compact)
    <span
        class="zs-brand zs-brand--compact"
        role="img"
        aria-label="Zarzour Sport"
    >
        <img
            src="{{ asset('brand/zarzour-logo.png') }}"
            alt=""
            aria-hidden="true"
        />
    </span>
@else
    <span
        class="zs-brand zs-brand--wordmark"
        role="img"
        aria-label="Zarzour Sport"
    >
        <img
            src="{{ asset('brand/zarzour-logo.png') }}"
            alt=""
            aria-hidden="true"
        />
    </span>
@endif
