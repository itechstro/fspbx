@php
    use App\Services\Provisioning\IntradeKeyXml;

    $layout = (string) ($keyLayout ?? 'advanced');
    $pages = (int) ($funcKeyPages ?? 1);
    $perPage = (int) ($keysPerPage ?? 8);
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
            @foreach ($lineKeys as $row)
                @include('provisioning.intrade.partials.fkey', ['row' => $row, 'index' => $row['device_key_id'] ?? '', 'withIcon' => true])
            @endforeach
        </dssSide>
        @foreach ($programmableKeys as $row)
        <dssSoft index="{{ $row['device_key_id'] ?? '' }}">
@include('provisioning.intrade.partials.key-fields', ['row' => $row, 'withIcon' => false])
        </dssSoft>
        @endforeach
@elseif ($layout === 'standard')
        <AutoBLFList>{{ $intrade_auto_blf_list ?? '1' }}</AutoBLFList>
        <dssSide index="1">
            @foreach ($lineKeys as $row)
                @include('provisioning.intrade.partials.fkey', ['row' => $row, 'index' => $row['device_key_id'] ?? '', 'withIcon' => true])
            @endforeach
        </dssSide>
        @for ($page = 2; $page <= (int) ($sideKeyPages ?? 3); $page++)
        <dssSide index="{{ $page }}">
            @foreach (IntradeKeyXml::rowsForPage($memoryKeys, $page - 1, $perPage) as $row)
                @include('provisioning.intrade.partials.fkey', ['row' => $row, 'index' => $row['page_index'], 'withIcon' => true])
            @endforeach
        </dssSide>
        @endfor
        @foreach ($programmableKeys as $row)
        <dssSoft index="{{ $row['device_key_id'] ?? '' }}">
@include('provisioning.intrade.partials.key-fields', ['row' => $row, 'withIcon' => false])
        </dssSoft>
        @endforeach
@elseif ($layout === 'video')
        @php
            $internalKeys = array_merge($lineKeys, $memoryKeys);
        @endphp
        @for ($page = 1; $page <= $pages; $page++)
        <internal index="{{ $page }}">
            @foreach (IntradeKeyXml::rowsForPage($internalKeys, $page, $perPage) as $row)
                @include('provisioning.intrade.partials.fkey', ['row' => $row, 'index' => $row['page_index'], 'withIcon' => true])
            @endforeach
        </internal>
        @endfor
        @foreach ($programmableKeys as $row)
        <dssSoft index="{{ $row['device_key_id'] ?? '' }}">
@include('provisioning.intrade.partials.key-fields', ['row' => $row, 'withIcon' => false])
        </dssSoft>
        @endforeach
@else
        <dssSide index="1">
            @foreach ($lineKeys as $row)
                @include('provisioning.intrade.partials.fkey', ['row' => $row, 'index' => $row['device_key_id'] ?? '', 'withIcon' => true])
            @endforeach
        </dssSide>
        @for ($page = 1; $page <= $pages; $page++)
        <internal index="{{ $page }}">
            @foreach (IntradeKeyXml::rowsForPage($memoryKeys, $page, $perPage) as $row)
                @include('provisioning.intrade.partials.fkey', ['row' => $row, 'index' => $row['page_index'], 'withIcon' => true])
            @endforeach
        </internal>
        @endfor
        @foreach ($programmableKeys as $row)
        <dssSoft index="{{ $row['device_key_id'] ?? '' }}">
@include('provisioning.intrade.partials.key-fields', ['row' => $row, 'withIcon' => false])
        </dssSoft>
        @endforeach
@endif
    </dsskey>
