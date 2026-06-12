<?xml version="1.0" encoding="UTF-8"?>
<AddressBook>
    <pbgroup>
        <id>1</id>
        <name>Users</name>
        <ringtones>default ringtone</ringtones>
    </pbgroup>
    <pbgroup>
        <id>2</id>
        <name>Groups</name>
        <ringtones>default ringtone</ringtones>
    </pbgroup>
    <pbgroup>
        <id>3</id>
        <name>Extensions</name>
        <ringtones>system</ringtones>
    </pbgroup>

@php $entryId = 0; @endphp
@foreach ($contacts as $row)
@php
    $filter = strtolower((string) ($contacts_filter ?? 'all'));
    $category = (string) ($row['category'] ?? '');
    $show = $filter === 'all' || $filter === $category;
    $given = trim((string) ($row['contact_name_given'] ?? ''));
    $family = trim((string) ($row['contact_name_family'] ?? ''));
    $org = trim((string) ($row['contact_organization'] ?? ''));
@endphp
@if ($show && in_array($category, ['users', 'groups', 'extensions'], true))
    <Contact>
        <id>{{ $entryId++ }}</id>
        @if ($category === 'extensions')
            <FirstName>{{ $given !== '' ? trim($given . ' ' . $family) : ($row['effective_caller_id_name'] ?? '') }}</FirstName>
            <Phone type="Work">
                <phonenumber>{{ $row['phone_extension'] ?? $row['phone_number'] ?? '' }}</phonenumber>
                <accountindex>0</accountindex>
            </Phone>
            <Group>3</Group>
        @else
            @if ($given !== '')
                @if ($org !== '')
                <FirstName>{{ trim($given . ' ' . $family) }}</FirstName>
                <Company>{{ $org }}</Company>
                @else
                <FirstName>{{ $given }}</FirstName>
                <LastName>{{ $family }}</LastName>
                @endif
            @else
                <FirstName>{{ $row['effective_caller_id_name'] ?? $org }}</FirstName>
            @endif
            @foreach (($row['numbers'] ?? []) as $number)
                @php $label = strtolower((string) ($number['phone_label'] ?? '')); @endphp
                @if (($label === 'work' || $label === '') && !empty($number['phone_number']))
                <Phone type="Work">
                    <phonenumber>{{ $number['phone_number'] }}</phonenumber>
                    <accountindex>0</accountindex>
                </Phone>
                @elseif ($label === 'home' && !empty($number['phone_number']))
                <Phone type="Home">
                    <phonenumber>{{ $number['phone_number'] }}</phonenumber>
                    <accountindex>0</accountindex>
                </Phone>
                @elseif ($label === 'mobile' && !empty($number['phone_number']))
                <Phone type="Cell">
                    <phonenumber>{{ $number['phone_number'] }}</phonenumber>
                    <accountindex>0</accountindex>
                </Phone>
                @endif
            @endforeach
            <Group>{{ $category === 'users' ? 1 : 2 }}</Group>
        @endif
        <Primary>0</Primary>
    </Contact>
@endif
@endforeach
</AddressBook>
