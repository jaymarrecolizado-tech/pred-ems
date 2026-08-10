<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmploymentType;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        [$totalEmployees, $activeEmployees, $separatedEmployees, $byType] = Cache::remember(
            'dashboard.stats',
            now()->addSeconds(60),
            fn () => [
                Employee::count(),
                Employee::where('status', 'active')->count(),
                Employee::whereIn('status', ['separated', 'resigned', 'retired'])->count(),
                EmploymentType::query()
                    ->withCount('employees')
                    ->orderBy('sort_order')
                    ->get(),
            ]
        );

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
