<?php

use Illuminate\Support\Facades\Route;
use Modules\ContactCenter\Http\Controllers\DashboardController;
use Modules\ContactCenter\Http\Controllers\AgentStatusController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::prefix('contact-center')->middleware('check.license')->group(function() {
    Route::get('/', [DashboardController::class, 'index'])->name('contactcenter.index');
    Route::post('/', [DashboardController::class, 'index']);
    Route::get('/create', [DashboardController::class, 'create'])->name('contactcenter.create');
    Route::get('/settings', [DashboardController::class, 'showSettings'])->name('contactcenter.settings.list');
    Route::post('/settings/queue/item-options', [DashboardController::class, 'getQueueItemOptions'])->name('contactcenter.settings.queue-item-options');
    Route::get('/settings/{callCenterQueues}', [DashboardController::class, 'showSettings'])->name('contactcenter.settings.show');
    Route::delete('/delete/{callCenterQueues}',[DashboardController::class, 'destroy'])->name('contactcenter.destroy');
    Route::put('/settings/{callCenterQueues}', [DashboardController::class, 'update'])->name('contactcenter.settings.update');
    Route::get('/settings/{callCenterQueues}/assign-agent/{callCenterAgents}', [DashboardController::class, 'assignAgent'])->name('contactcenter.settings.agents.assign');
    Route::get('/settings/{callCenterQueues}/unassign-agent/{callCenterAgents}', [DashboardController::class, 'unAssignAgent'])->name('contactcenter.settings.agents.unassign');
    Route::post('/update-agent-status', [DashboardController::class, 'updateAgentStatus'])->name('contactcenter.agents.update_status');
    Route::post('/stats/refresh', [DashboardController::class, 'getStatsData'])->name('contactcenter.stats.refresh');
    Route::post('/info/refresh', [DashboardController::class, 'getQueueInfoData'])->name('contactcenter.info.refresh');
    Route::post('/agent/status/update', [AgentStatusController::class, 'update'])->name('agent.status.update');
});
