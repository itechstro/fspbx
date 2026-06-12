<?xml version="1.0" encoding="utf-8"?>
<tbook e="2" complete="true">
@foreach ($contacts as $index => $row)
@php
    $filter = strtolower((string) ($contacts_filter ?? 'all'));
    $category = (string) ($row['category'] ?? '');
    $show = $filter === 'all' || $filter === $category;
@endphp
@if ($show)
  <item context="active" type="{{ $category === 'extensions' ? 'colleagues' : 'none' }}" index="{{ $index }}">
    <first_name>{{ $row['contact_name_given'] ?? '' }}</first_name>
    <last_name>{{ $row['contact_name_family'] ?? '' }}</last_name>
    <number>@if ($category === 'extensions'){{ $row['phone_extension'] ?? '' }}@else{{ $row['phone_number'] ?? '' }}@endif</number>
    <number_type>business</number_type>
  </item>
@endif
@endforeach
</tbook>
