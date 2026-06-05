<?php

namespace Modules\ContactCenter\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\FreeswitchEslService;

class AgentStatusController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('contactcenter::index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('contactcenter::create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store()
    {
        //
    }

    /**
     * Show the specified resource.
     */
    public function show($id)
    {
        return view('contactcenter::show');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        return view('contactcenter::edit');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update()
    {

        $freeSwitchService = new FreeswitchEslService();
        $command = sprintf(
            "bgapi callcenter_config agent set status %s '%s'",
            request('agentUuid'),
            request('status')
        );
        $result = $freeSwitchService->executeCommand($command);

        return $result;

    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        //
    }
}
