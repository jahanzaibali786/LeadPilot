<?php

use App\Http\Controllers\AiLeadController;
use App\Http\Controllers\AiChatController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BlacklistController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\FollowUpController;
use App\Http\Controllers\LeadBoardController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\LeadNoteController;
use App\Http\Controllers\LeadStatusController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SavedSearchController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\SettingController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class,'loginForm'])->name('login');
    Route::post('/login', [AuthController::class,'login']);
    Route::get('/register', [AuthController::class,'registerForm'])->name('register');
    Route::post('/register', [AuthController::class,'register']);
    Route::get('/forgot-password', [AuthController::class,'forgotForm'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class,'forgot'])->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class,'resetForm'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class,'reset'])->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class,'logout'])->name('logout');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::resource('services', ServiceController::class)->only(['index','store','update','destroy']);
    Route::resource('campaigns', CampaignController::class)->only(['index','create','store','show','edit','update','destroy']);
    Route::post('/campaigns/{campaign}/run', [CampaignController::class,'run'])->name('campaigns.run');
    Route::get('/campaigns/{campaign}/status', [CampaignController::class,'status'])->name('campaigns.status');
    Route::post('/campaigns/{campaign}/cancel', [CampaignController::class,'cancel'])->name('campaigns.cancel');
    Route::get('/leads', [LeadController::class,'index'])->name('leads.index');
    Route::get('/leads/board', [LeadBoardController::class,'index'])->name('leads.board');
    Route::post('/leads/board/reorder', [LeadBoardController::class,'reorder'])->name('leads.board.reorder');
    Route::get('/leads/{lead}', [LeadController::class,'show'])->name('leads.show');
    Route::patch('/leads/{lead}', [LeadController::class,'update'])->name('leads.update');
    Route::delete('/leads/{lead}', [LeadController::class,'destroy'])->name('leads.destroy');
    Route::patch('/leads/{lead}/status', LeadStatusController::class)->name('leads.status');
    Route::post('/leads/{lead}/notes', [LeadNoteController::class,'store'])->name('leads.notes.store');
    Route::post('/leads/{lead}/follow-ups', [FollowUpController::class,'store'])->name('leads.followups.store');
    Route::patch('/follow-ups/{followUp}/complete', [FollowUpController::class,'complete'])->name('followups.complete');
    Route::post('/leads/{lead}/ai-analysis', AiLeadController::class)->name('leads.ai');
    Route::post('/ai/chat', AiChatController::class)->middleware('throttle:20,1')->name('ai.chat');
    Route::get('/exports/leads', ExportController::class)->name('exports.leads');
    Route::get('/settings', [SettingController::class,'index'])->name('settings.index');
    Route::put('/settings', [SettingController::class,'update'])->name('settings.update');
    Route::resource('blacklists', BlacklistController::class)->only(['index','store','destroy']);
    Route::resource('saved-searches', SavedSearchController::class)->only(['store','destroy']);
    Route::middleware('role:Super Admin')->prefix('admin')->name('admin.')->group(function(){
        Route::get('/', [AdminController::class,'index'])->name('index');
        Route::patch('/users/{user}/role', [AdminController::class,'role'])->name('users.role');
    });
});
