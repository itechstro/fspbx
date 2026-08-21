@php
    use App\Services\Provisioning\IntradeKeyXml;

    $layout = (string) ($keyLayout ?? 'advanced');
    $pages = (int) ($funcKeyPages ?? 1);
    $perPage = (int) ($keysPerPage ?? 8);
    $sideKeysPerPage = (int) ($sideKeysPerPage ?? $perPage);
    $sideKeysConfigurablePerPage = (int) ($sideKeysConfigurablePerPage ?? $sideKeysPerPage);
    $softKeysMax = (int) ($softKeysMax ?? $sideKeysPerPage);
    $sideKeyPages = (int) ($sideKeyPages ?? ($intrade_side_key_pages ?? 1));
    $lineKeys = $keys['line'] ?? [];
    $memoryKeys = $keys['memory'] ?? [];
    $programmableKeys = $keys['programmable'] ?? [];
    // Entry: only emit configurable slots (1–2); omit Fkey 3 so page-switch stays intact.
    $entrySideEmitCount = $layout === 'entry' ? $sideKeysConfigurablePerPage : $sideKeysPerPage;
@endphp
    <dsskey>
        <SelectDsskeyAction>{{ $intrade_select_dsskey_action ?? '0' }}</SelectDsskeyAction>
        <MemoryKeytoBXfer>{{ $intrade_memory_key_to_bxfer ?? '3' }}</MemoryKeytoBXfer>
        <FuncKeyPageNum>{{ $pages }}</FuncKeyPageNum>
        <SideKeyPageNum>{{ $sideKeyPages }}</SideKeyPageNum>
        <DSSHomePage>{{ $intrade_dss_home_page ?? '0' }}</DSSHomePage>
@if ($layout === 'video')
{{-- Match Intrade Video 2.6 phone exports: these tags sit before Fkeys; DSSTimeoutToHome is absent. --}}
        <DisplayParkedInfo>0</DisplayParkedInfo>
        <DSSDIALSwitchMode>0</DSSDIALSwitchMode>
        <FirstCallWaitTime>16</FirstCallWaitTime>
        <FirstNumStartTime>360</FirstNumStartTime>
        <FirstNumEndTime>1080</FirstNumEndTime>
        <DSSLongPressAction>{{ $dssLongPressAction ?? '3' }}</DSSLongPressAction>
        <Extern1PageBelong>0</Extern1PageBelong>
        <Extern2PageBelong>0</Extern2PageBelong>
        <Extern3PageBelong>0</Extern3PageBelong>
        <Extern4PageBelong>0</Extern4PageBelong>
        <Extern5PageBelong>0</Extern5PageBelong>
        <DSSExtend1MAC></DSSExtend1MAC>
        <DSSExtend1IP></DSSExtend1IP>
        <DSSExtend2MAC></DSSExtend2MAC>
        <DSSExtend2IP></DSSExtend2IP>
        <DSSExtend3MAC></DSSExtend3MAC>
        <DSSExtend3IP></DSSExtend3IP>
        <DSSExtend4MAC></DSSExtend4MAC>
        <DSSExtend4IP></DSSExtend4IP>
        <DSSExtend5MAC></DSSExtend5MAC>
        <DSSExtend5IP></DSSExtend5IP>
@else
        <DSSLongPressAction>{{ $dssLongPressAction ?? '3' }}</DSSLongPressAction>
@if (($intrade_dss_timeout_to_home ?? '') !== '')
        <DSSTimeoutToHome>{{ $intrade_dss_timeout_to_home }}</DSSTimeoutToHome>
@endif
@endif

@if ($layout === 'entry')
        <AutoBLFList>{{ $intrade_auto_blf_list ?? '1' }}</AutoBLFList>
        <dssSide index="1">
            @foreach (IntradeKeyXml::configuredSideSlots($lineKeys, $entrySideEmitCount) as $slot)
                @include('provisioning.intrade.partials.fkey', ['row' => $slot['row'], 'index' => $slot['index'], 'withIcon' => true])
            @endforeach
        </dssSide>
        @for ($page = 2; $page <= max(2, $sideKeyPages); $page++)
        <dssSide index="{{ $page }}">
            @foreach (IntradeKeyXml::entryExtraSideSlots($memoryKeys, $page, $sideKeysConfigurablePerPage) as $slot)
                @include('provisioning.intrade.partials.fkey', ['row' => $slot['row'], 'index' => $slot['index'], 'withIcon' => true])
            @endforeach
        </dssSide>
        @endfor
        @for ($softIndex = 1; $softIndex <= $softKeysMax; $softIndex++)
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
        @for ($page = 2; $page <= max(2, $sideKeyPages); $page++)
        <dssSide index="{{ $page }}">
            @foreach (IntradeKeyXml::slotsForPage($memoryKeys, $page - 1, $perPage) as $slot)
                @include('provisioning.intrade.partials.fkey', ['row' => $slot['row'], 'index' => $slot['index'], 'withIcon' => true])
            @endforeach
        </dssSide>
        @endfor
        @for ($softIndex = 1; $softIndex <= $softKeysMax; $softIndex++)
        <dssSoft index="{{ $softIndex }}">
@include('provisioning.intrade.partials.key-fields', ['row' => $programmableKeys[$softIndex] ?? IntradeKeyXml::clearedRow(), 'withIcon' => false])
        </dssSoft>
        @endfor
@elseif ($layout === 'video')
        @php
            $internalKeys = IntradeKeyXml::mergeKeyedRows($lineKeys, $memoryKeys);
        @endphp
        @for ($page = 1; $page <= $pages; $page++)
        <internal index="{{ $page }}">
            @foreach (IntradeKeyXml::slotsForPage($internalKeys, $page, $perPage) as $slot)
                @include('provisioning.intrade.partials.fkey', ['row' => $slot['row'], 'index' => $slot['index'], 'withIcon' => true])
            @endforeach
        </internal>
        @endfor
        @for ($softIndex = 1; $softIndex <= $softKeysMax; $softIndex++)
        <dssSoft index="{{ $softIndex }}">
@include('provisioning.intrade.partials.key-fields', ['row' => $programmableKeys[$softIndex] ?? IntradeKeyXml::clearedRow(), 'withIcon' => true])
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
        @for ($softIndex = 1; $softIndex <= $softKeysMax; $softIndex++)
        <dssSoft index="{{ $softIndex }}">
@include('provisioning.intrade.partials.key-fields', ['row' => $programmableKeys[$softIndex] ?? IntradeKeyXml::clearedRow(), 'withIcon' => false])
        </dssSoft>
        @endfor
@endif
    </dsskey>
