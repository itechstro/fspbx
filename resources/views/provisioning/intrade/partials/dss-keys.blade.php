@php
    use App\Services\Provisioning\IntradeKeyXml;

    $layout = (string) ($keyLayout ?? 'advanced');
    $pages = (int) ($funcKeyPages ?? 1);
    $perPage = (int) ($keysPerPage ?? 8);
    $sideKeysPerPage = (int) ($sideKeysPerPage ?? $perPage);
    $lineKeys = $keys['line'] ?? [];
    $memoryKeys = $keys['memory'] ?? [];
    $programmableKeys = $keys['programmable'] ?? [];
@endphp
    <dsskey>
        <SelectDsskeyAction>{{ $intrade_select_dsskey_action ?? '0' }}</SelectDsskeyAction>
        <MemoryKeytoBXfer>{{ $intrade_memory_key_to_bxfer ?? '3' }}</MemoryKeytoBXfer>
        <FuncKeyPageNum>{{ $pages }}</FuncKeyPageNum>
        <SideKeyPageNum>{{ $sideKeyPages ?? ($intrade_side_key_pages ?? '1') }}</SideKeyPageNum>
        <DSSHomePage>{{ $intrade_dss_home_page ?? '0' }}</DSSHomePage>
        <DSSLongPressAction>{{ $dssLongPressAction ?? '3' }}</DSSLongPressAction>
@if (($intrade_dss_timeout_to_home ?? '') !== '')
        <DSSTimeoutToHome>{{ $intrade_dss_timeout_to_home }}</DSSTimeoutToHome>
@endif

@if ($layout === 'entry')
        <AutoBLFList>{{ $intrade_auto_blf_list ?? '1' }}</AutoBLFList>
        <dssSide index="1">
            @foreach (IntradeKeyXml::configuredSideSlots($lineKeys, $sideKeysPerPage) as $slot)
                @include('provisioning.intrade.partials.fkey', ['row' => $slot['row'], 'index' => $slot['index'], 'withIcon' => true])
            @endforeach
        </dssSide>
        @for ($softIndex = 1; $softIndex <= $sideKeysPerPage; $softIndex++)
        <dssSoft index="{{ $softIndex }}">
@include('provisioning.intrade.partials.key-fields', ['row' => $programmableKeys[$softIndex] ?? IntradeKeyXml::clearedRow(), 'withIcon' => false])
        </dssSoft>
        @endfor
@elseif ($layout === 'standard')
        <AutoBLFList>{{ $intrade_auto_blf_list ?? '1' }}</AutoBLFList>
        <dssSide index="1">
            @foreach (IntradeKeyXml::configuredSideSlots($lineKeys, $sideKeysPerPage) as $slot)
                @include('provisioning.intrade.partials.fkey', ['row' => $slot['row'], 'index' => $slot['index'], 'withIcon' => true])
            @endforeach
        </dssSide>
        @for ($page = 2; $page <= (int) ($sideKeyPages ?? 3); $page++)
        <dssSide index="{{ $page }}">
            @foreach (IntradeKeyXml::slotsForPage($memoryKeys, $page - 1, $perPage) as $slot)
                @include('provisioning.intrade.partials.fkey', ['row' => $slot['row'], 'index' => $slot['index'], 'withIcon' => true])
            @endforeach
        </dssSide>
        @endfor
        @for ($softIndex = 1; $softIndex <= $sideKeysPerPage; $softIndex++)
        <dssSoft index="{{ $softIndex }}">
@include('provisioning.intrade.partials.key-fields', ['row' => $programmableKeys[$softIndex] ?? IntradeKeyXml::clearedRow(), 'withIcon' => false])
        </dssSoft>
        @endfor
@elseif ($layout === 'video')
        @php
            $internalKeys = array_merge($lineKeys, $memoryKeys);
        @endphp
        @for ($page = 1; $page <= $pages; $page++)
        <internal index="{{ $page }}">
            @foreach (IntradeKeyXml::slotsForPage($internalKeys, $page, $perPage) as $slot)
                @include('provisioning.intrade.partials.fkey', ['row' => $slot['row'], 'index' => $slot['index'], 'withIcon' => true])
            @endforeach
        </internal>
        @endfor
        @for ($softIndex = 1; $softIndex <= $sideKeysPerPage; $softIndex++)
        <dssSoft index="{{ $softIndex }}">
@include('provisioning.intrade.partials.key-fields', ['row' => $programmableKeys[$softIndex] ?? IntradeKeyXml::clearedRow(), 'withIcon' => false])
        </dssSoft>
        @endfor
@else
        <dssSide index="1">
            @foreach (IntradeKeyXml::configuredSideSlots($lineKeys, $sideKeysPerPage) as $slot)
                @include('provisioning.intrade.partials.fkey', ['row' => $slot['row'], 'index' => $slot['index'], 'withIcon' => true])
            @endforeach
        </dssSide>
        @for ($page = 1; $page <= $pages; $page++)
        <internal index="{{ $page }}">
            @foreach (IntradeKeyXml::slotsForPage($memoryKeys, $page, $perPage) as $slot)
                @include('provisioning.intrade.partials.fkey', ['row' => $slot['row'], 'index' => $slot['index'], 'withIcon' => true])
            @endforeach
        </internal>
        @endfor
        @for ($softIndex = 1; $softIndex <= $sideKeysPerPage; $softIndex++)
        <dssSoft index="{{ $softIndex }}">
@include('provisioning.intrade.partials.key-fields', ['row' => $programmableKeys[$softIndex] ?? IntradeKeyXml::clearedRow(), 'withIcon' => false])
        </dssSoft>
        @endfor
@endif
    </dsskey>
