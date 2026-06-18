@php
    $settings = is_array($settings ?? null) ? $settings : [];
    $slots = [];

    for ($slot = 1; $slot <= 5; $slot++) {
        $urlKey = $slot === 1 ? 'intrade_directory_url' : 'intrade_directory_url_' . $slot;
        $contactsKey = $slot === 1 ? 'intrade_directory_contacts' : 'intrade_directory_contacts_' . $slot;
        $nameKey = 'intrade_directory_name_' . $slot;
        $siplineKey = 'intrade_directory_sipline_' . $slot;
        $bindLineKey = 'intrade_directory_bind_line_' . $slot;

        $url = trim((string) ($settings[$urlKey] ?? ''));
        $contacts = trim((string) ($settings[$contactsKey] ?? ''));

        if ($url === '' && $contacts === '') {
            continue;
        }

        $name = trim((string) ($settings[$nameKey] ?? ''));
        if ($name === '' && $slot === 1) {
            $name = (string) ($domain_name ?? '');
        }

        $slots[] = [
            'index' => $slot,
            'name' => $name,
            'url' => $url,
            'sipline' => $settings[$siplineKey] ?? '1',
            'bind_line' => $settings[$bindLineKey] ?? '1',
        ];
    }
@endphp
@foreach ($slots as $slot)
        <xmlContact index="{{ $slot['index'] }}">
            <Name>{{ $slot['name'] }}</Name>
            <Addr>{{ $slot['url'] }}</Addr>
            <UserName>{{ $http_auth_username ?? '' }}</UserName>
            <PassWd>{{ $http_auth_password ?? '' }}</PassWd>
            <Sipline>{{ $slot['sipline'] }}</Sipline>
            <BindLine>{{ $slot['bind_line'] }}</BindLine>
        </xmlContact>
@endforeach
