@php
    use App\Services\Provisioning\IntradeKeyXml;

    $row = is_array($row ?? null) ? $row : IntradeKeyXml::clearedRow();
@endphp
<Fkey index="{{ $index }}">
@include('provisioning.intrade.partials.key-fields', ['row' => $row, 'withIcon' => $withIcon ?? false])
</Fkey>
