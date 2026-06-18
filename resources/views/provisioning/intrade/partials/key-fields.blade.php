@php
    use App\Services\Provisioning\IntradeKeyXml;
@endphp
    <Type>{{ IntradeKeyXml::typeCode($row['device_key_type'] ?? null) }}</Type>
    <Value>{{ IntradeKeyXml::value($row) }}</Value>
    <Title>{{ $row['device_key_label'] ?? '' }}</Title>
@php
    $lineNumber = IntradeKeyXml::lineNumber($row);
@endphp
@if ($lineNumber > 0 && (string) ($row['device_key_type'] ?? '') === '1')
    <Line>{{ $lineNumber }}</Line>
@endif
@if (!empty($withIcon))
    <ICON>{{ $row['device_key_icon'] ?? 'Green' }}</ICON>
@endif
