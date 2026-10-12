@extends('layouts.app', ['page_title' => 'Contact Center Settings'])

@section('content')
    <div class="container-fluid">

        <!-- start page title -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box">
                    <div class="page-title-right">
                        <ol class="breadcrumb m-0">
                            <li class="breadcrumb-item"><a href="/dashboard">Dashboard</a></li>
                            <li class="breadcrumb-item active">Contact Center Settings</li>
                        </ol>
                    </div>
                    <h4 class="page-title">Contact Center Settings</h4>
                </div>
            </div>
        </div>
        <!-- end page title -->

        <div class="row">

            <!-- Right Sidebar -->
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <!-- Left sidebar -->
                        <div class="page-aside-left">
                            <div class="card">
                                <div id="tooltip-container-actions" class="card-header d-flex justify-content-between align-items-center">
                                    <h4 class="header-title mb-0">Contact Centers</h4>
                                    <a href="{{route('contactcenter.create')}}"
                                       class="btn btn-sm btn-light">
                                        <i class="mdi mdi-plus" data-bs-container="#tooltip-container-actions" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Add Contact Center"></i>
                                    </a>
                                </div>

                                <div class="card-body py-0 mb-2">
                                    <div class="list-group list-group-flush mt-2 font-16">
                                        @foreach ($queues as $item)
                                            <a href="{{route('contactcenter.settings.show', ['callCenterQueues' => $item])}}" class="list-group-item list-group-item-action text-primary border-0"
                                               @if($callCenterQueue && $callCenterQueue->call_center_queue_uuid == $item->call_center_queue_uuid) style="background-color: #f8f9fa" @endif
                                               > {{ $item->queue_name }}</a>
                                        @endforeach

                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- End Left sidebar -->

                        <div class="page-aside-right">
                            @if($callCenterQueue)
                                @include('contactcenter::layouts.partials.settingsShow', ['queue' => $callCenterQueue ])
                            @else
                                <div class="container d-flex align-items-center justify-content-center">
                                    <h4>To modify settings, choose a Contact Center on the left or create a new one.</h4>
                                </div>
                            @endif
                        </div>
                        <!-- end inbox-rightbar-->
                    </div>
                    <!-- end card-body -->
                    <div class="clearfix"></div>
                </div> <!-- end card-box -->

            </div> <!-- end Col -->
        </div><!-- End row -->

    </div>

@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            $('[data-bs-toggle="tooltip"]').tooltip();
        });
    </script>
@endpush
