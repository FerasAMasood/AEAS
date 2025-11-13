<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReportAbbreviationController;
use App\Http\Controllers\ReportSummaryController;
use App\Http\Controllers\IntroductionController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;

// Authentication routes (public)
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Get authenticated user
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Logout route (protected)
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

// User management routes (protected)
Route::middleware(['auth:sanctum'])->group(function () {
    Route::apiResource('users', UserController::class);
});

Route::apiResource('properties', App\Http\Controllers\PropertyController::class);
Route::apiResource('lookups',  App\Http\Controllers\LookupController::class);
Route::apiResource('abbreviations',  App\Http\Controllers\AbbreviationController::class);

Route::get('lookups-search', [App\Http\Controllers\LookupController::class, 'search']);



// Reports routes (protected)
Route::middleware(['auth:sanctum'])->group(function () {
    Route::apiResource('reports', ReportController::class);
    // POST route for updates with FormData (Laravel doesn't parse multipart/form-data for PUT)
    Route::post('/reports/{id}/update', [ReportController::class, 'update'])->name('reports.update.post');
    Route::get('/report-pdf/{report_id}', [ReportController::class, 'generatePdf'])->name('reports.pdf');
});

Route::post('/report-abbreviations', [ReportAbbreviationController::class, 'createAssociations']);
Route::get('/report-abbreviations/{reportId}', [ReportAbbreviationController::class, 'getAssociations']);


// routes/api.php

// Report summaries routes (protected)
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/report-summaries', [ReportSummaryController::class, 'index']); // List summaries (with optional report_id query)
    Route::post('/report-summaries', [ReportSummaryController::class, 'store']);  // Create new summary
    Route::get('/report-summaries/{id}', [ReportSummaryController::class, 'show']); // View a summary by ID
    Route::put('/report-summaries/{id}', [ReportSummaryController::class, 'update']); // Update a summary by ID
});

// routes/api.php


// Report introductions routes (protected)
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/report-introductions', [IntroductionController::class, 'store']);
    Route::get('/report-introductions/{report_id}', [IntroductionController::class, 'show']);
    Route::put('/report-introductions/{id}', [IntroductionController::class, 'update']);
});

use App\Http\Controllers\PropertyDeviceController;

Route::post('/property-devices/bulk', [PropertyDeviceController::class, 'storeBulk']);
Route::apiResource('property-devices', PropertyDeviceController::class);

use App\Http\Controllers\EnergySourceController;

Route::apiResource('energy-sources', EnergySourceController::class);

use App\Http\Controllers\TariffController;

Route::apiResource('tariffs', TariffController::class);
Route::post('tariffs-bulk', [TariffController::class, 'bulkStore']);

use App\Http\Controllers\EbillController;
use App\Http\Controllers\BillsAnalysisController;
use App\Http\Controllers\ElectricityBalanceController;

Route::apiResource('ebills', EbillController::class);
Route::post('/ebills/bulk', [EbillController::class, 'storeBulk']);

// Bills analysis routes (protected)
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/bills-analysis/analyze', [BillsAnalysisController::class, 'analyze']);
    Route::post('/bills-analysis/store', [BillsAnalysisController::class, 'store']);
    Route::get('/bills-analysis/{propertyId}', [BillsAnalysisController::class, 'show']);
});

// Electricity balance routes (protected)
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/electricity-balance/analyze', [ElectricityBalanceController::class, 'analyze']);
    Route::post('/electricity-balance/store', [ElectricityBalanceController::class, 'store']);
    Route::get('/electricity-balance/{propertyId}', [ElectricityBalanceController::class, 'show']);
});


use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DocumentSectionController;

Route::middleware(['auth:sanctum'])->group(function(){
    Route::get('/documents',[DocumentController::class,'index']);
    Route::post('/documents',[DocumentController::class,'store']);
    Route::get('/documents/by-report/{reportId}',[DocumentController::class,'showByReportId']);
    Route::get('/documents/{document}',[DocumentController::class,'show']);
    Route::put('/documents/{document}',[DocumentController::class,'update']);
    Route::delete('/documents/{document}',[DocumentController::class,'destroy']);

    // Sections
    Route::get('/documents/{document}/sections',[DocumentSectionController::class,'index']);
    Route::post('/documents/{document}/sections',[DocumentSectionController::class,'store']);
    Route::put('/documents/{document}/sections/order',[DocumentSectionController::class,'setOrder']);
    Route::get('/documents/{document}/sections/{section}',[DocumentSectionController::class,'show']);
    Route::put('/documents/{document}/sections/{section}',[DocumentSectionController::class,'update']);
    Route::delete('/documents/{document}/sections/{section}',[DocumentSectionController::class,'destroy']);

    // Subsections
    Route::post('/documents/{document}/subsections',[DocumentController::class,'addSubsection']);
    Route::put('/documents/{document}/subsections/order',[DocumentController::class,'setSubsectionOrder']);
    Route::put('/documents/{document}/subsections/{subsection}',[DocumentController::class,'updateSubsection']);
    Route::delete('/documents/{document}/subsections/{subsection}',[DocumentController::class,'deleteSubsection']);

    // Legacy routes (for backward compatibility)
    Route::put('/documents/{document}/order',[DocumentController::class,'setOrder']);
    Route::get('/documents/{document}/html',[DocumentController::class,'renderHtml']);
});