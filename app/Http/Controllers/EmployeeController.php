<?php

namespace App\Http\Controllers;

use App\Http\Requests\EmployeeRequest;
use App\Models\Division;
use App\Models\Employee;
use App\Models\EmploymentType;
use App\Models\Position;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    /**
     * List employees with search + employment type + status filters.
     */
    public function index(Request $request): View
    {
        $query = Employee::query()
            ->with(['employmentType', 'position', 'division'])
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = trim($request->string('search'));
                $q->where(function ($inner) use ($search) {
                    $inner->where('employee_number', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('middle_name', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('employment_type'), fn ($q) => $q->where('employment_type_id', $request->integer('employment_type')))
            ->when($request->filled('division'), fn ($q) => $q->where('division_id', $request->integer('division')))
            ->when($request->filled('source_of_fund'), fn ($q) => $q->where('source_of_fund', $request->string('source_of_fund')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')));

        $employees = $query->orderBy('last_name')->orderBy('first_name')->paginate(15)->withQueryString();

        return view('employees.index', [
            'employees' => $employees,
            'employmentTypes' => EmploymentType::orderBy('sort_order')->get(),
            'divisions' => Division::orderBy('name')->get(),
            'sourceFunds' => Employee::query()
                ->whereNotNull('source_of_fund')
                ->distinct()
                ->orderBy('source_of_fund')
                ->pluck('source_of_fund'),
        ]);
    }

    public function create(): View
    {
        return view('employees.create', $this->formOptions());
    }

    public function store(EmployeeRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $data['employee_number'] = $data['employee_number'] ?: $this->nextEmployeeNumber();

        $employee = Employee::create($data);
        $this->storePhoto($request, $employee);

        Audit::record('created', $employee, [], $employee->toArray());

        return redirect()
            ->route('employees.show', $employee)
            ->with('success', "Employee {$employee->full_name} created successfully.");
    }

    public function show(Employee $employee): View
    {
        // RBAC: roles without view.employees (e.g. `employee`) may only view
        // their own 201-file record, never another employee's.
        $user = auth()->user();
        if (! $user->hasPermission('view.employees') && $employee->user_id !== $user->id) {
            abort(403, 'You do not have permission to access this page.');
        }

        $employee->load(['employmentType', 'division', 'position', 'appointments.position', 'educations', 'civilServiceEligibilities']);

        return view('employees.show', [
            'employee' => $employee,
            'leaveBalances' => $employee->leaveBalances(),
        ]);
    }

    public function edit(Employee $employee): View
    {
        return view('employees.edit', array_merge(['employee' => $employee], $this->formOptions()));
    }

    public function update(EmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $data = $request->validated();

        $old = $employee->toArray();
        $employee->update($data);
        $this->storePhoto($request, $employee);

        Audit::record('updated', $employee, $old, $employee->toArray());

        return redirect()
            ->route('employees.show', $employee)
            ->with('success', "Employee {$employee->full_name} updated successfully.");
    }

    public function destroy(Employee $employee): RedirectResponse
    {
        if ($employee->appointments()->exists()) {
            return back()->with('error', 'Cannot delete: this employee has appointment records. Mark as separated instead.');
        }

        Audit::record('deleted', $employee, $employee->toArray(), []);

        if ($employee->profile_photo_path) {
            Storage::disk('public')->delete($employee->profile_photo_path);
        }

        $employee->delete();

        return redirect()
            ->route('employees.index')
            ->with('success', 'Employee deleted.');
    }

    /* ------------------------------------------------------------------ */
    /*  Helpers                                                            */
    /* ------------------------------------------------------------------ */

    private function formOptions(): array
    {
        return [
            'employmentTypes' => EmploymentType::orderBy('sort_order')->get(),
            'divisions' => Division::orderBy('name')->get(),
            'positions' => Position::orderBy('title')->get(),
            'statuses' => ['active' => 'Active', 'on_leave' => 'On Leave', 'separated' => 'Separated', 'resigned' => 'Resigned', 'retired' => 'Retired'],
        ];
    }

    /**
     * Persist an uploaded profile photo (admin/HR side) and clean up any
     * previous file. Runs only when a file was actually submitted.
     */
    private function storePhoto(Request $request, Employee $employee): void
    {
        if (! $request->hasFile('photo')) {
            return;
        }

        if ($employee->profile_photo_path) {
            Storage::disk('public')->delete($employee->profile_photo_path);
        }

        $path = $request->file('photo')->store('photos', 'public');
        $employee->update(['profile_photo_path' => $path]);
    }

    private function nextEmployeeNumber(): string
    {
        $last = Employee::query()
            ->where('employee_number', 'like', 'RO2-%')
            ->orderByRaw('CAST(SUBSTRING(employee_number, 5) AS UNSIGNED) DESC')
            ->value('employee_number');

        $next = $last ? ((int) substr($last, 4)) + 1 : 1;

        return 'RO2-' . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
