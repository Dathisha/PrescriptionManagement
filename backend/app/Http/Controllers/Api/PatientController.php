<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use Illuminate\Http\Request;

class PatientController extends Controller
{
    // Get all patients
    public function index()
    {
        $patients = Patient::latest()->get();

        return response()->json($patients);
    }

    // Create a new patient
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'age' => 'required|integer|min:0|max:150',
        ]);

        $patient = Patient::create([
            'name' => $request->name,
            'age' => $request->age,
        ]);

        return response()->json([
            'message' => 'Patient created successfully',
            'patient' => $patient,
        ], 201);
    }

    // Get one patient
    public function show($id)
    {
        $patient = Patient::with('prescriptions')->findOrFail($id);

        return response()->json($patient);
    }
}