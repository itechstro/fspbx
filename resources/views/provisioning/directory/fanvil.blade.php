<FanvilIPPhoneDirectory>
@php
    $filter = strtolower((string) ($contacts_filter ?? 'all'));
    $titles = [
        'users' => 'Users Directory',
        'groups' => 'Groups Directory',
        'extensions' => 'Extensions Directory',
    ];
@endphp
<Title>{{ $titles[$filter] ?? 'Complete Directory' }}</Title>
@foreach ($contacts as $row)
@php
    $category = (string) ($row['category'] ?? '');
    $show = $filter === 'all'
        || $filter === $category
        || ($filter === 'users' && $category === 'users')
        || ($filter === 'groups' && $category === 'groups')
        || ($filter === 'extensions' && $category === 'extensions');

    $org = trim((string) ($row['contact_organization'] ?? ''));
    $given = trim((string) ($row['contact_name_given'] ?? ''));
    $family = trim((string) ($row['contact_name_family'] ?? ''));

    $telephone = '';
    $mobile = '';
    $other = '';

    if ($category !== 'extensions') {
        foreach (($row['numbers'] ?? []) as $number) {
            $phoneNumber = trim((string) ($number['phone_number'] ?? $number['phone_extension'] ?? ''));

            if ($phoneNumber === '') {
                continue;
            }

            $label = strtolower(trim((string) ($number['phone_label'] ?? '')));

            if ($label === 'work' && $telephone === '') {
                $telephone = $phoneNumber;
            } elseif ($label === 'mobile' && $mobile === '') {
                $mobile = $phoneNumber;
            } elseif ($label === 'home' && $other === '') {
                $other = $phoneNumber;
            } elseif ($telephone === '') {
                $telephone = $phoneNumber;
            } elseif ($mobile === '') {
                $mobile = $phoneNumber;
            } elseif ($other === '') {
                $other = $phoneNumber;
            }
        }
    }

    $groupNames = [
        'users' => 'Users',
        'groups' => 'Groups',
        'extensions' => 'Extensions',
    ];
    $group = $groupNames[$category] ?? '';
@endphp
@if ($show)
<DirectoryEntry>
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
    <Mobile></Mobile>
    <Other></Other>
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
    <Telephone>{{ $telephone }}</Telephone>
    <Mobile>{{ $mobile }}</Mobile>
    <Other>{{ $other }}</Other>
@endif
    <Ring>0</Ring>
    <Group>{{ $group }}</Group>
    <picture></picture>
</DirectoryEntry>
@endif
@endforeach
</FanvilIPPhoneDirectory>
