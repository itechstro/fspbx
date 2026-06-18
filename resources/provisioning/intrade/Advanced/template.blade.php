{{-- version: 2.4.0 --}}

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
])
@break

@endswitch
