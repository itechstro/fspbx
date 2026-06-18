@php
    use App\Services\Provisioning\IntradeKeyXml;
@endphp
    <Type>{{ IntradeKeyXml::typeCode($row['device_key_type'] ?? null) }}</Type>
    <Value>{{ IntradeKeyXml::value($row) }}</Value>
    <Title>{{ $row['device_key_label'] ?? '' }}</Title>
@if (!empty($withIcon))
    <ICON>{{ $row['device_key_icon'] ?? 'Green' }}</ICON>
@endif
