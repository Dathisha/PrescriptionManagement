<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\Prescription;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $totalPatients = Patient::count();
        $totalPrescriptions = Prescription::count();
        $patientsWithPrescriptions = Prescription::distinct('patient_id')->count('patient_id');

        $recentPrescriptions = Prescription::with('patient:id,name,age')
            ->latest()
            ->take(5)
            ->get()
            ->map(function (Prescription $prescription) use ($request) {
                return [
                    'id'                 => $prescription->id,
                    'patient_id'        => $prescription->patient_id,
                    'patient_name'      => $prescription->patient?->name ?? 'Unknown',
                    'patient_age'       => $prescription->patient?->age,
                    'original_file_name'=> $prescription->original_file_name,
                    'file_name'         => $prescription->original_file_name,
                    'file_path'         => $prescription->file_path,
                    'file_url'          => $request->getSchemeAndHttpHost() . '/storage/' . $prescription->file_path,
                    'source'            => $prescription->source,
                    'created_at'        => $prescription->created_at,
                ];
            });

        $recentPatients = Patient::latest()
            ->take(5)
            ->get(['id', 'name', 'age', 'created_at']);

        return response()->json([
            'total_patients'             => $totalPatients,
            'total_prescriptions'        => $totalPrescriptions,
            'patients_with_prescriptions'=> $patientsWithPrescriptions,
            'recent_prescriptions'       => $recentPrescriptions,
            'recent_patients'            => $recentPatients,
        ]);
    }
}
