@php
    use App\Services\Provisioning\IbratroKeyXml;
@endphp
    <Type>{{ IbratroKeyXml::typeCode($row['device_key_type'] ?? null) }}</Type>
    <Value>{{ IbratroKeyXml::value($row) }}</Value>
    <Title>{{ $row['device_key_label'] ?? '' }}</Title>
@if (!empty($withIcon))
    <ICON>{{ $row['device_key_icon'] ?? 'Green' }}</ICON>
@endif
