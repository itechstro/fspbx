{{-- version: 2.4.8 --}}

@switch($flavor)

@case('mac.cfg')
@include('provisioning.intrade.mac-cfg', [
    'modelLabel' => 'Entry',
    'modelProfile' => 'entry',
    'maxLines' => 6,
    'videoEnabled' => false,
    'includeLineVideoCodec' => false,
    'keyLayout' => 'entry',
    'funcKeyPages' => 1,
    'keysPerPage' => 8,
    'sideKeyPages' => 3,
    'sideKeysPerPage' => 3,
    'sideKeysConfigurablePerPage' => 2,
    'softKeysMax' => 10,
])
@break

@endswitch
