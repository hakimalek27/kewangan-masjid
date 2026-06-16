@props(['eksport' => true])

<button type="submit" class="btn btn-primary">{{ __('Papar') }}</button>
<button type="button" class="btn btn-outline-primary" onclick="window.print()">
    <i class="bi bi-printer me-1"></i>{{ __('Cetak') }}
</button>
@if ($eksport)
    <a href="{{ request()->fullUrlWithQuery(['format' => 'pdf']) }}" class="btn btn-outline-danger">
        <i class="bi bi-file-earmark-pdf me-1"></i>{{ __('PDF') }}
    </a>
    <a href="{{ request()->fullUrlWithQuery(['format' => 'xls']) }}" class="btn btn-outline-success">
        <i class="bi bi-file-earmark-excel me-1"></i>{{ __('Excel') }}
    </a>
@endif
