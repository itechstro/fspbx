{{-- version: 2.4.7 --}}

@switch($flavor)

@case('mac.cfg')
@include('provisioning.intrade.mac-cfg', [
    'modelLabel' => 'Video',
    'modelProfile' => 'video',
    'maxLines' => 20,
    'videoEnabled' => true,
    'includeLineVideoCodec' => false,
    'keyLayout' => 'video',
    'funcKeyPages' => 4,
    'keysPerPage' => 29,
    'sideKeysPerPage' => 10,
])
@break

@endswitch
