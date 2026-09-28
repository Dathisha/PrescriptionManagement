<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PatientReportController extends Controller
{
    /**
     * Get patient reports combined for yearly (month-wise) and monthly (day-wise).
     */
    public function index(Request $request)
    {
        // 1. Get available registration years from patients table based on real created_at data
        $availableYears = Patient::selectRaw('YEAR(created_at) as year')
            ->whereNotNull('created_at')
            ->distinct()
            ->orderBy('year', 'desc')
            ->pluck('year')
            ->map(fn($y) => (int)$y)
            ->values()
            ->all();

        $currentYear = (int)date('Y');
        if (empty($availableYears)) {
            $availableYears = [$currentYear];
        }

        // Selected year
        $selectedYear = $request->has('year') && is_numeric($request->year)
            ? (int)$request->year
            : (int)$availableYears[0];

        // 2. Month summary for all 12 calendar months for the selected year
        $monthNames = [
            1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
            5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
            9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
        ];

        $countsByMonth = Patient::selectRaw('MONTH(created_at) as month, count(*) as count')
            ->whereYear('created_at', $selectedYear)
            ->groupBy(DB::raw('MONTH(created_at)'))
            ->pluck('count', 'month')
            ->all();

        $monthSummary = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthSummary[] = [
                'month' => $m,
                'name' => $monthNames[$m],
                'patient_count' => (int)($countsByMonth[$m] ?? 0),
            ];
        }

        // 3. Selected month
        $selectedMonth = null;
        if ($request->has('month') && is_numeric($request->month)) {
            $m = (int)$request->month;
            if ($m >= 1 && $m <= 12) {
                $selectedMonth = $m;
            }
        }

        if ($selectedMonth === null) {
            if ($selectedYear === $currentYear) {
                $selectedMonth = (int)date('n');
            } else {
                $activeMonths = array_keys(array_filter($countsByMonth, fn($c) => $c > 0));
                $selectedMonth = !empty($activeMonths) ? (int)max($activeMonths) : 1;
            }
        }

        // 4. Complete list of patients registered in the selected year and month
        $patients = Patient::whereYear('created_at', $selectedYear)
            ->whereMonth('created_at', $selectedMonth)
            ->orderBy('created_at', 'desc')
            ->get(['id', 'name', 'age', 'created_at']);

        return response()->json([
            'available_years' => $availableYears,
            'selected_year'   => $selectedYear,
            'selected_month'  => $selectedMonth,
            'month_summary'   => $monthSummary,
            'patients'        => $patients,
            'total_in_month'  => $patients->count(),
            'total_in_year'   => array_sum($countsByMonth),
        ]);
    }
}
