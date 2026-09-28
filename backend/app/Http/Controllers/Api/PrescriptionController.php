<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\Prescription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PrescriptionController extends Controller
{
    // Get all prescriptions for a patient
    public function index(Request $request, Patient $patient)
    {
        $prescriptions = $patient->prescriptions()
            ->latest()
            ->get()
            ->map(fn (Prescription $prescription) => $this->prescriptionResponse($request, $prescription));

        return response()->json($prescriptions);
    }

    // Upload a prescription (PDF for normal upload; PDF/JPG/JPEG/PNG for scan)
    public function store(Request $request, Patient $patient)
    {
        $source = $request->input('source', 'upload');

        // Scan uploads may be PDF or scanned images (JPG/JPEG/PNG).
        // Normal uploads are PDF only — but we accept all supported types here
        // and rely on the frontend to enforce per-action restrictions.
        $request->validate([
            'prescription' => 'required|file|mimes:pdf,jpg,jpeg,png|max:20480',
            'source'       => 'nullable|in:upload,scan',
        ]);

        $file = $request->file('prescription');

        $path = $file->store('prescriptions', 'public');

        $prescription = Prescription::create([
            'patient_id'         => $patient->id,
            'original_file_name' => $file->getClientOriginalName(),
            'file_path'          => $path,
            'source'             => in_array($source, ['upload', 'scan']) ? $source : 'upload',
        ]);

        return response()->json([
            'message'      => 'Prescription uploaded successfully',
            'prescription' => $this->prescriptionResponse($request, $prescription),
        ], 201);
    }

    // Delete a prescription
    public function destroy(Prescription $prescription)
    {
        if ($prescription->file_path && Storage::disk('public')->exists($prescription->file_path)) {
            Storage::disk('public')->delete($prescription->file_path);
        }

        $prescription->delete();

        return response()->json([
            'message' => 'Prescription deleted successfully',
        ]);
    }

    private function prescriptionResponse(Request $request, Prescription $prescription): array
    {
        return [
            'id'                 => $prescription->id,
            'patient_id'        => $prescription->patient_id,
            'original_file_name' => $prescription->original_file_name,
            'file_name'         => $prescription->original_file_name,
            'file_path'         => $prescription->file_path,
            'file_url'          => $request->getSchemeAndHttpHost() . '/storage/' . $prescription->file_path,
            'source'            => $prescription->source,
            'created_at'        => $prescription->created_at,
            'updated_at'        => $prescription->updated_at,
        ];
    }
}