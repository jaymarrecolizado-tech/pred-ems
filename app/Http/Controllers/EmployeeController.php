<?php

namespace App\Http\Controllers;

use App\Models\Division;
use App\Models\Employee;
use App\Models\EmploymentType;
use App\Models\Position;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
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

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateEmployee($request);

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

    public function update(Request $request, Employee $employee): RedirectResponse
    {
        $data = $this->validateEmployee($request, $employee);

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

    private function validateEmployee(Request $request, ?Employee $employee = null): array
    {
        $uniqueRule = Rule::unique('employees', 'employee_number')
            ->ignore($employee?->id);

        $validated = $request->validate([
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'employee_number' => ['nullable', 'string', 'max:30', $uniqueRule],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'maiden_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'birth_date' => ['nullable', 'date'],
            'gender' => ['nullable', 'in:Male,Female'],
            'civil_status' => ['nullable', 'string', 'max:20'],
            'citizenship' => ['nullable', 'string', 'max:50'],
            'blood_type' => ['nullable', 'string', 'max:5'],
            'residential_address' => ['nullable', 'string', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:30'],
            'personal_email' => ['nullable', 'email', 'max:150'],
            'gov_email' => ['nullable', 'email', 'max:150'],
            'gsis_no' => ['nullable', 'string', 'max:30'],
            'philhealth_no' => ['nullable', 'string', 'max:30'],
            'pagibig_no' => ['nullable', 'string', 'max:30'],
            'tin_no' => ['nullable', 'string', 'max:30'],
            'sss_no' => ['nullable', 'string', 'max:30'],
            'employment_type_id' => ['required', 'exists:employment_types,id'],
            'division_id' => ['nullable', 'exists:divisions,id'],
            'position_id' => ['nullable', 'exists:positions,id'],
            'plantilla_item_no' => ['nullable', 'string', 'max:100'],
            'bp_number' => ['nullable', 'string', 'max:50'],
            'source_of_fund' => ['nullable', 'string', 'max:80'],
            'salary_grade' => ['nullable', 'integer', 'min:1', 'max:33'],
            'step' => ['nullable', 'integer', 'min:1', 'max:8'],
            'monthly_salary' => ['nullable', 'numeric', 'min:0'],
            'date_original_appointment' => ['nullable', 'date'],
            'date_last_promotion' => ['nullable', 'date'],
            'status' => ['required', 'in:active,on_leave,separated,resigned,retired'],
            'remarks' => ['nullable', 'string'],
        ]);

        return $validated;
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
