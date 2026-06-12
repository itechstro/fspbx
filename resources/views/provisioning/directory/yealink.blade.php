<YealinkIPPhoneDirectory>
@foreach ($contacts as $row)
@php
    $filter = strtolower((string) ($contacts_filter ?? 'all'));
    $category = (string) ($row['category'] ?? '');
    $show = $filter === 'all'
        || ($filter === $category)
        || ($filter === 'users' && $category === 'users')
        || ($filter === 'groups' && $category === 'groups')
        || ($filter === 'extensions' && $category === 'extensions');
@endphp
@if ($show)
<DirectoryEntry>
@php
    $org = trim((string) ($row['contact_organization'] ?? ''));
    $given = trim((string) ($row['contact_name_given'] ?? ''));
    $family = trim((string) ($row['contact_name_family'] ?? ''));
@endphp
@if ($category === 'extensions')
    @if ($given !== '')
    <Name>{{ trim($given . ' ' . $family) }}</Name>
    @else
    <Name>{{ $row['effective_caller_id_name'] ?? '' }}</Name>
    @endif
    @if (!empty($row['phone_number']))
    <Telephone>{{ $row['phone_number'] }}</Telephone>
    @else
    <Telephone>{{ $row['phone_extension'] ?? '' }}</Telephone>
    @endif
@else
    @if ($org !== '' && $given !== '' && $family !== '')
    <Name>{{ $org }}, {{ $given }} {{ $family }}</Name>
    @elseif ($org !== '' && $given === '' && $family === '')
    <Name>{{ $org }}</Name>
    @elseif ($given !== '' && $family !== '' && $org === '')
    <Name>{{ $given }} {{ $family }}</Name>
    @elseif ($given !== '' && $family !== '')
    <Name>{{ $given }} {{ $family }}</Name>
    @elseif ($given !== '')
    <Name>{{ $given }}</Name>
    @elseif ($family !== '')
    <Name>{{ $family }}</Name>
    @endif
    @foreach (($row['numbers'] ?? []) as $number)
        @if (!empty($number['phone_number']))
        <Telephone>{{ $number['phone_number'] }}</Telephone>
        @else
        <Telephone>{{ $number['phone_extension'] ?? '' }}</Telephone>
        @endif
    @endforeach
@endif
</DirectoryEntry>
@endif
@endforeach
</YealinkIPPhoneDirectory>
