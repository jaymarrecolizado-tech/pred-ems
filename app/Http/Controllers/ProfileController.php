<?php

namespace App\Http\Controllers;

use App\Http\Requests\PasswordUpdateRequest;
use App\Http\Requests\ProfilePhotoRequest;
use App\Http\Requests\ProfileUpdateRequest;
use App\Models\Employee;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Self-service "My Profile" area.
 *
 * Any authenticated user may view their own profile, upload/remove their
 * photo, and change their password. Users whose account is linked to an
 * employee 201-file record may also edit their PERSONAL information only —
 * employment details (salary, grade, status, position …) stay HR-managed.
 */
class ProfileController extends Controller
{
    /**
     * Personal fields an employee may self-edit. Employment snapshot fields
     * (employment_type_id, division_id, position_id, salary_grade, step,
     * monthly_salary, status, employee_number, …) are deliberately excluded.
     */
    private const SELF_EDITABLE_FIELDS = [
        'first_name', 'middle_name', 'last_name', 'suffix',
        'birth_date', 'birth_place', 'gender', 'civil_status', 'citizenship',
        'blood_type', 'residential_address', 'contact_number', 'personal_email',
        'gsis_no', 'philhealth_no', 'pagibig_no', 'tin_no', 'sss_no',
        'remarks',
    ];

    public function show(): View
    {
        $employee = auth()->user()->employee;

        if ($employee) {
            $employee->load(['employmentType', 'division', 'position']);
        }

        return view('profile.show', ['employee' => $employee]);
    }

    public function edit(): View|RedirectResponse
    {
        $employee = $this->ownEmployee();

        return view('profile.edit', [
            'employee' => $employee,
        ]);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $employee = $this->ownEmployee();

        $data = $request->validated();

        $old = collect(self::SELF_EDITABLE_FIELDS)
            ->mapWithKeys(fn ($f) => [$f => $employee->getRawOriginal($f)])
            ->filter(fn ($v) => $v !== null)
            ->all();

        $employee->update($data);

        Audit::record('profile_updated', $employee, $old, $employee->only(array_keys($data)));

        return redirect()
            ->route('profile.show')
            ->with('success', 'Your personal information has been updated.');
    }

    public function uploadPhoto(ProfilePhotoRequest $request): RedirectResponse
    {
        $employee = $this->ownEmployee();

        $oldPath = $employee->profile_photo_path;

        $path = $request->file('photo')->store('photos', 'public');

        if ($oldPath && $oldPath !== $path) {
            Storage::disk('public')->delete($oldPath);
        }

        $employee->update(['profile_photo_path' => $path]);

        Audit::record('photo_updated', $employee, ['profile_photo_path' => $oldPath], ['profile_photo_path' => $path]);

        return back()->with('success', 'Profile photo updated.');
    }

    public function removePhoto(): RedirectResponse
    {
        $employee = $this->ownEmployee();

        $oldPath = $employee->profile_photo_path;

        if ($oldPath) {
            Storage::disk('public')->delete($oldPath);
        }

        $employee->update(['profile_photo_path' => null]);

        Audit::record('photo_removed', $employee, ['profile_photo_path' => $oldPath], []);

        return back()->with('success', 'Profile photo removed.');
    }

    public function password(): View
    {
        return view('profile.password');
    }

    public function updatePassword(PasswordUpdateRequest $request): RedirectResponse
    {

        $user = auth()->user();
        $user->update(['password' => Hash::make($request->password)]);

        Audit::record('password_changed', $user, [], ['email' => $user->email]);

        return redirect()
            ->route('profile.password')
            ->with('success', 'Your password has been changed.');
    }

    /* ------------------------------------------------------------------ */
    /*  Helpers                                                            */
    /* ------------------------------------------------------------------ */

    private function ownEmployee(): Employee
    {
        $employee = auth()->user()->employee;

        if (! $employee) {
            abort(403, 'No employee record is linked to your account. Contact HR.');
        }

        return $employee;
    }

    private function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'birth_date' => ['nullable', 'date'],
            'birth_place' => ['nullable', 'string', 'max:150'],
            'gender' => ['nullable', 'in:Male,Female'],
            'civil_status' => ['nullable', 'string', 'max:20'],
            'citizenship' => ['nullable', 'string', 'max:50'],
            'blood_type' => ['nullable', 'string', 'max:5'],
            'residential_address' => ['nullable', 'string', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:30'],
            'personal_email' => ['nullable', 'email', 'max:150'],
            'gsis_no' => ['nullable', 'string', 'max:30'],
            'philhealth_no' => ['nullable', 'string', 'max:30'],
            'pagibig_no' => ['nullable', 'string', 'max:30'],
            'tin_no' => ['nullable', 'string', 'max:30'],
            'sss_no' => ['nullable', 'string', 'max:30'],
            'remarks' => ['nullable', 'string'],
        ];
    }
}
