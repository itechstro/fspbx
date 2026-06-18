{{-- version: 2.4.0 --}}

@switch($flavor)

@case('mac.cfg')
@include('provisioning.intrade.mac-cfg', [
    'modelLabel' => 'Standard',
    'modelProfile' => 'standard',
    'maxLines' => 12,
    'videoEnabled' => false,
    'includeLineVideoCodec' => false,
    'keyLayout' => 'standard',
    'funcKeyPages' => 5,
    'keysPerPage' => 7,
    'sideKeyPages' => 3,
])
@break

@endswitch
