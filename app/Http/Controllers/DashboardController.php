<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmploymentType;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $totalEmployees = Employee::count();
        $activeEmployees = Employee::where('status', 'active')->count();
        $separatedEmployees = Employee::whereIn('status', ['separated', 'resigned', 'retired'])->count();

        $byType = EmploymentType::query()
            ->withCount('employees')
            ->orderBy('sort_order')
            ->get();

        $recentEmployees = Employee::with(['employmentType', 'position'])
            ->latest()
            ->take(5)
            ->get();

        return view('dashboard', compact(
            'totalEmployees',
            'activeEmployees',
            'separatedEmployees',
            'byType',
            'recentEmployees'
        ));
    }
}
