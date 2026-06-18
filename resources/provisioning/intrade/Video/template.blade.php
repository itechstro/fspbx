{{-- version: 2.4.0 --}}

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
])
@break

@endswitch
