<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReportAbbreviationController;
use App\Http\Controllers\ReportSummaryController;
use App\Http\Controllers\IntroductionController;
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::apiResource('properties', App\Http\Controllers\PropertyController::class);
Route::apiResource('lookups',  App\Http\Controllers\LookupController::class);
Route::apiResource('abbreviations',  App\Http\Controllers\AbbreviationController::class);

Route::get('lookups-search', [App\Http\Controllers\LookupController::class, 'search']);



Route::apiResource('reports', ReportController::class);
Route::get('/report-pdf/{report_id}', [ReportController::class, 'generatePdf'])->name('reports.pdf');

Route::post('/report-abbreviations', [ReportAbbreviationController::class, 'createAssociations']);
Route::get('/report-abbreviations/{reportId}', [ReportAbbreviationController::class, 'getAssociations']);


// routes/api.php



Route::post('/report-summaries', [ReportSummaryController::class, 'store']);  // Create new summary
Route::get('/report-summaries/{id}', [ReportSummaryController::class, 'show']); // View a summary by ID
Route::put('/report-summaries/{id}', [ReportSummaryController::class, 'update']); // Update a summary by ID


// routes/api.php


Route::post('/report-introductions', [IntroductionController::class, 'store']);
Route::get('/report-introductions/{report_id}', [IntroductionController::class, 'show']);

use App\Http\Controllers\PropertyDeviceController;

Route::post('/property-devices/bulk', [PropertyDeviceController::class, 'storeBulk']);
Route::apiResource('property-devices', PropertyDeviceController::class);

use App\Http\Controllers\EnergySourceController;

Route::apiResource('energy-sources', EnergySourceController::class);

use App\Http\Controllers\TariffController;

Route::apiResource('tariffs', TariffController::class);
Route::post('tariffs-bulk', [TariffController::class, 'bulkStore']);


use App\Http\Controllers\DocumentController;

Route::middleware(['auth:sanctum'])->group(function(){
    Route::get('/documents',[DocumentController::class,'index']);
    Route::post('/documents',[DocumentController::class,'store']);
    Route::get('/documents/{document}',[DocumentController::class,'show']);
    Route::put('/documents/{document}',[DocumentController::class,'update']);
    Route::delete('/documents/{document}',[DocumentController::class,'destroy']);

    Route::post('/documents/{document}/subsections',[DocumentController::class,'addSubsection']);
    Route::put('/documents/{document}/order',[DocumentController::class,'setOrder']);
    Route::get('/documents/{document}/html',[DocumentController::class,'renderHtml']);
});