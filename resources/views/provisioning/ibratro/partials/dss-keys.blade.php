@php
    use App\Services\Provisioning\IbratroKeyXml;

    $layout = (string) ($keyLayout ?? 'advanced');
    $pages = (int) ($funcKeyPages ?? 1);
    $perPage = (int) ($keysPerPage ?? 8);
    $lineKeys = $keys['line'] ?? [];
    $memoryKeys = $keys['memory'] ?? [];
    $programmableKeys = $keys['programmable'] ?? [];
@endphp
    <dsskey>
        <SelectDsskeyAction>{{ $ibratro_select_dsskey_action ?? '0' }}</SelectDsskeyAction>
        <MemoryKeytoBXfer>{{ $ibratro_memory_key_to_bxfer ?? '3' }}</MemoryKeytoBXfer>
        <FuncKeyPageNum>{{ $pages }}</FuncKeyPageNum>
        <SideKeyPageNum>{{ $sideKeyPages ?? ($ibratro_side_key_pages ?? '1') }}</SideKeyPageNum>
        <DSSHomePage>{{ $ibratro_dss_home_page ?? '0' }}</DSSHomePage>
        <DSSLongPressAction>{{ $dssLongPressAction ?? '3' }}</DSSLongPressAction>
@if (($ibratro_dss_timeout_to_home ?? '') !== '')
        <DSSTimeoutToHome>{{ $ibratro_dss_timeout_to_home }}</DSSTimeoutToHome>
@endif

@if ($layout === 'entry')
        <AutoBLFList>{{ $ibratro_auto_blf_list ?? '1' }}</AutoBLFList>
        <dssSide index="1">
            @foreach ($lineKeys as $row)
                @include('provisioning.ibratro.partials.fkey', ['row' => $row, 'index' => $row['device_key_id'] ?? '', 'withIcon' => true])
            @endforeach
        </dssSide>
        @foreach ($programmableKeys as $row)
        <dssSoft index="{{ $row['device_key_id'] ?? '' }}">
@include('provisioning.ibratro.partials.key-fields', ['row' => $row, 'withIcon' => false])
        </dssSoft>
        @endforeach
@elseif ($layout === 'standard')
        @for ($page = 1; $page <= $pages; $page++)
        <internal index="{{ $page }}">
            @foreach (IbratroKeyXml::rowsForPage($lineKeys, $page, $perPage) as $row)
                @include('provisioning.ibratro.partials.fkey', ['row' => $row, 'index' => $row['page_index'], 'withIcon' => true])
            @endforeach
        </internal>
        @endfor
        @for ($page = 1; $page <= $pages; $page++)
        <internal index="{{ $page }}">
            @foreach (IbratroKeyXml::rowsForPage($memoryKeys, $page, $perPage) as $row)
                @include('provisioning.ibratro.partials.fkey', ['row' => $row, 'index' => $row['page_index'], 'withIcon' => true])
            @endforeach
        </internal>
        @endfor
@elseif ($layout === 'video')
        @php
            $internalKeys = array_merge($lineKeys, $memoryKeys);
        @endphp
        @for ($page = 1; $page <= $pages; $page++)
        <internal index="{{ $page }}">
            @foreach (IbratroKeyXml::rowsForPage($internalKeys, $page, $perPage) as $row)
                @include('provisioning.ibratro.partials.fkey', ['row' => $row, 'index' => $row['page_index'], 'withIcon' => true])
            @endforeach
        </internal>
        @endfor
        @foreach ($programmableKeys as $row)
        <dssSoft index="{{ $row['device_key_id'] ?? '' }}">
@include('provisioning.ibratro.partials.key-fields', ['row' => $row, 'withIcon' => false])
        </dssSoft>
        @endforeach
@else
        <dssSide index="1">
            @foreach ($lineKeys as $row)
                @include('provisioning.ibratro.partials.fkey', ['row' => $row, 'index' => $row['device_key_id'] ?? '', 'withIcon' => true])
            @endforeach
        </dssSide>
        @for ($page = 1; $page <= $pages; $page++)
        <internal index="{{ $page }}">
            @foreach (IbratroKeyXml::rowsForPage($memoryKeys, $page, $perPage) as $row)
                @include('provisioning.ibratro.partials.fkey', ['row' => $row, 'index' => $row['page_index'], 'withIcon' => true])
            @endforeach
        </internal>
        @endfor
        @foreach ($programmableKeys as $row)
        <dssSoft index="{{ $row['device_key_id'] ?? '' }}">
@include('provisioning.ibratro.partials.key-fields', ['row' => $row, 'withIcon' => false])
        </dssSoft>
        @endforeach
@endif
    </dsskey>
