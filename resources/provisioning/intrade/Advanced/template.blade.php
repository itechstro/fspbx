{{-- version: 2.4.5 --}}

@switch($flavor)

@case('mac.cfg')
@include('provisioning.intrade.mac-cfg', [
    'modelLabel' => 'Advanced',
    'modelProfile' => 'advanced',
    'maxLines' => 20,
    'videoEnabled' => true,
    'keyLayout' => 'advanced',
    'funcKeyPages' => 4,
    'keysPerPage' => 29,
    'sideKeysPerPage' => 11,
])
@break

@endswitch
