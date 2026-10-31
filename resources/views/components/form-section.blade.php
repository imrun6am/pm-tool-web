@props(['title', 'description' => null])
{{-- Settings-style form section: title + description beside its fields (stacked on mobile). --}}
<section {{ $attributes->merge(['class' => 'form-section']) }}>
    <div>
        <h2 class="form-section-title">{{ $title }}</h2>
        @if ($description)
            <p class="form-section-desc">{{ $description }}</p>
        @endif
    </div>
    <div class="form-section-body">{{ $slot }}</div>
</section>
