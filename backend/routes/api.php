<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\PatientController;
use App\Http\Controllers\Api\PrescriptionController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\PatientReportController;

Route::get('/dashboard', [DashboardController::class, 'index']);

Route::get('/patient-reports', [PatientReportController::class, 'index']);

Route::get('/patients', [PatientController::class, 'index']);

Route::post('/patients', [PatientController::class, 'store']);

Route::get('/patients/{id}', [PatientController::class, 'show']);

Route::get('/patients/{patient}/prescriptions', [PrescriptionController::class, 'index']);

Route::post('/patients/{patient}/prescriptions', [PrescriptionController::class, 'store']);

Route::delete('/prescriptions/{prescription}', [PrescriptionController::class, 'destroy']);

Route::delete('/patients/{patient}/prescriptions/{prescription}', [PrescriptionController::class, 'destroy']);
