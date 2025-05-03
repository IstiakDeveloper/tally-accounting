<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class DesignationController extends Controller
{
    /**
     * Display a listing of the designations.
     */
    public function index(Request $request)
    {
        $query = Designation::with('department');

        // Filter by department
        if ($request->has('department_id') && $request->department_id) {
            $query->where('department_id', $request->department_id);
        }

        // Filter by status
        if ($request->has('status')) {
            if ($request->status === 'active') {
                $query->where('is_active', true);
            } elseif ($request->status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        // Search by name
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%");
        }

        $designations = $query->orderBy('name', 'asc')->paginate(10)->withQueryString();

        // Load employee count for each designation
        $designations->each(function ($designation) {
            $designation->employee_count = $designation->employees()->count();
        });

        $departments = Department::where('is_active', true)->orderBy('name', 'asc')->get();

        return Inertia::render('Designations/Index', [
            'designations' => $designations,
            'departments' => $departments,
            'filters' => $request->only(['department_id', 'status', 'search']),
        ]);
    }

    /**
     * Show the form for creating a new designation.
     */
    public function create()
    {
        $departments = Department::where('is_active', true)->orderBy('name', 'asc')->get();

        return Inertia::render('Designations/Create', [
            'departments' => $departments,
        ]);
    }

    /**
     * Store a newly created designation in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:designations',
            'description' => 'nullable|string',
            'department_id' => 'required|exists:departments,id',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $designation = Designation::create([
            'name' => $request->name,
            'description' => $request->description,
            'department_id' => $request->department_id,
            'is_active' => $request->is_active ?? true,
        ]);

        return redirect()->route('designations.index')
            ->with('success', 'Designation created successfully.');
    }

    /**
     * Display the specified designation.
     */
    public function show(Designation $designation)
    {
        $designation->load('department');

        // Get employees with this designation
        $employees = Employee::with(['user', 'department'])
            ->where('designation_id', $designation->id)
            ->orderBy('joining_date', 'desc')
            ->paginate(10);

        return Inertia::render('Designations/Show', [
            'designation' => $designation,
            'employees' => $employees,
        ]);
    }

    /**
     * Show the form for editing the specified designation.
     */
    public function edit(Designation $designation)
    {
        $departments = Department::where('is_active', true)->orderBy('name', 'asc')->get();

        return Inertia::render('Designations/Edit', [
            'designation' => $designation,
            'departments' => $departments,
        ]);
    }

    /**
     * Update the specified designation in storage.
     */
    public function update(Request $request, Designation $designation)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:designations,name,' . $designation->id,
            'description' => 'nullable|string',
            'department_id' => 'required|exists:departments,id',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $designation->update([
            'name' => $request->name,
            'description' => $request->description,
            'department_id' => $request->department_id,
            'is_active' => $request->is_active ?? $designation->is_active,
        ]);

        return redirect()->route('designations.index')
            ->with('success', 'Designation updated successfully.');
    }

    /**
     * Remove the specified designation from storage.
     */
    public function destroy(Designation $designation)
    {
        // Check if employees are using this designation
        $employeeCount = $designation->employees()->count();

        if ($employeeCount > 0) {
            return back()->with('error', 'Cannot delete designation. It is assigned to ' . $employeeCount . ' employee(s).');
        }

        $designation->delete();

        return redirect()->route('designations.index')
            ->with('success', 'Designation deleted successfully.');
    }

    /**
     * Toggle the status of the designation.
     */
    public function toggleStatus(Designation $designation)
    {
        $designation->update([
            'is_active' => !$designation->is_active,
        ]);

        return back()->with('success', 'Designation status updated successfully.');
    }

    /**
     * Get designations by department for dynamic loading in forms.
     */
    public function getByDepartment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'department_id' => 'required|exists:departments,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Invalid department'], 422);
        }

        $designations = Designation::where('department_id', $request->department_id)
            ->where('is_active', true)
            ->orderBy('name', 'asc')
            ->get();

        return response()->json($designations);
    }

    /**
     * Export designations data to PDF.
     */
    public function exportPdf(Request $request)
    {
        $query = Designation::with('department');

        // Apply filters
        if ($request->has('department_id') && $request->department_id) {
            $query->where('department_id', $request->department_id);
        }

        if ($request->has('status')) {
            if ($request->status === 'active') {
                $query->where('is_active', true);
            } elseif ($request->status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        $designations = $query->orderBy('name', 'asc')->get();

        // Load employee count for each designation
        $designations->each(function ($designation) {
            $designation->employee_count = $designation->employees()->count();
        });

        return Inertia::render('Designations/ExportPdf', [
            'designations' => $designations,
            'filters' => $request->only(['department_id', 'status']),
        ]);
    }

    /**
     * Generate department-wise designation report.
     */
    public function departmentReport()
    {
        $departments = Department::with(['designations' => function ($query) {
                $query->withCount('employees');
            }])
            ->where('is_active', true)
            ->orderBy('name', 'asc')
            ->get();

        $departmentStats = $departments->map(function ($department) {
            $totalEmployees = $department->designations->sum('employees_count');

            return [
                'id' => $department->id,
                'name' => $department->name,
                'designation_count' => $department->designations->count(),
                'active_designation_count' => $department->designations->where('is_active', true)->count(),
                'employee_count' => $totalEmployees,
                'designations' => $department->designations->map(function ($designation) {
                    return [
                        'id' => $designation->id,
                        'name' => $designation->name,
                        'is_active' => $designation->is_active,
                        'employee_count' => $designation->employees_count,
                    ];
                }),
            ];
        });

        return Inertia::render('Designations/DepartmentReport', [
            'departmentStats' => $departmentStats,
        ]);
    }
}
