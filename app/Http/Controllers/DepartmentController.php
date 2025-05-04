<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class DepartmentController extends Controller
{
    /**
     * Display a listing of the departments.
     */
    public function index(Request $request)
    {
        $query = Department::with('manager');

        // Filter by status
        if ($request->has('status')) {
            if ($request->status === 'active') {
                $query->where('is_active', true);
            } elseif ($request->status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        // Search
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                  ->orWhere('description', 'like', '%' . $search . '%')
                  ->orWhereHas('manager', function ($query) use ($search) {
                      $query->where('name', 'like', '%' . $search . '%');
                  });
            });
        }

        $departments = $query->orderBy('name')->paginate(10)->withQueryString();

        // Load employee counts for each department
        foreach ($departments as $department) {
            $department->employee_count = $department->employees()->count();
            $department->designation_count = $department->designations()->count();
        }

        return Inertia::render('Departments/Index', [
            'departments' => $departments,
            'filters' => $request->only(['status', 'search']),
        ]);
    }

    /**
     * Show the form for creating a new department.
     */
    public function create()
    {
        // Get users who can be managers (admin, manager roles)
        $managers = User::whereIn('role', ['admin', 'manager'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return Inertia::render('Departments/Create', [
            'managers' => $managers,
        ]);
    }

    /**
     * Store a newly created department in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:departments',
            'description' => 'nullable|string',
            'manager_id' => 'nullable|exists:users,id',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            Department::create([
                'name' => $request->name,
                'description' => $request->description,
                'manager_id' => $request->manager_id,
                'is_active' => $request->is_active ?? true,
            ]);

            return redirect()->route('departments.index')
                ->with('success', 'Department created successfully.');

        } catch (\Exception $e) {
            return back()->with('error', 'An error occurred: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Display the specified department.
     */
    public function show(Department $department)
    {
        $department->load('manager', 'designations');

        // Get employees in this department
        $employees = Employee::with(['user', 'designation'])
            ->where('department_id', $department->id)
            ->orderBy('joining_date', 'desc')
            ->paginate(10);

        // Get employee count
        $employeeCount = $department->employees()->count();

        // Get active employee count
        $activeEmployeeCount = $department->employees()
            ->whereHas('user', function ($query) {
                $query->where('is_active', true);
            })
            ->count();

        // Get designation count
        $designationCount = $department->designations()->count();

        return Inertia::render('Departments/Show', [
            'department' => $department,
            'employees' => $employees,
            'employeeCount' => $employeeCount,
            'activeEmployeeCount' => $activeEmployeeCount,
            'designationCount' => $designationCount,
        ]);
    }

    /**
     * Show the form for editing the specified department.
     */
    public function edit(Department $department)
    {
        $department->load('manager');

        // Get users who can be managers (admin, manager roles)
        $managers = User::whereIn('role', ['admin', 'manager'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return Inertia::render('Departments/Edit', [
            'department' => $department,
            'managers' => $managers,
        ]);
    }

    /**
     * Update the specified department in storage.
     */
    public function update(Request $request, Department $department)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:departments,name,' . $department->id,
            'description' => 'nullable|string',
            'manager_id' => 'nullable|exists:users,id',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            $department->update([
                'name' => $request->name,
                'description' => $request->description,
                'manager_id' => $request->manager_id,
                'is_active' => $request->is_active ?? $department->is_active,
            ]);

            return redirect()->route('departments.index')
                ->with('success', 'Department updated successfully.');

        } catch (\Exception $e) {
            return back()->with('error', 'An error occurred: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Remove the specified department from storage.
     */
    public function destroy(Department $department)
    {
        try {
            // Check if there are employees in this department
            $employeeCount = $department->employees()->count();

            if ($employeeCount > 0) {
                return back()->with('error', 'Cannot delete department. It has ' . $employeeCount . ' employee(s).');
            }

            // Check if there are designations in this department
            $designationCount = $department->designations()->count();

            if ($designationCount > 0) {
                return back()->with('error', 'Cannot delete department. It has ' . $designationCount . ' designation(s).');
            }

            $department->delete();

            return redirect()->route('departments.index')
                ->with('success', 'Department deleted successfully.');

        } catch (\Exception $e) {
            return back()->with('error', 'An error occurred: ' . $e->getMessage());
        }
    }

    /**
     * Toggle the active status of the department.
     */
    public function toggleStatus(Department $department)
    {
        try {
            $department->update([
                'is_active' => !$department->is_active,
            ]);

            return back()->with('success', 'Department status updated successfully.');

        } catch (\Exception $e) {
            return back()->with('error', 'An error occurred: ' . $e->getMessage());
        }
    }

    /**
     * Display the department's organizational chart.
     */
    public function orgChart(Department $department)
    {
        $department->load('manager');

        // Get designations within the department
        $designations = Designation::where('department_id', $department->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        // Load employees for each designation
        $designationData = [];

        foreach ($designations as $designation) {
            $employees = Employee::with('user')
                ->where('department_id', $department->id)
                ->where('designation_id', $designation->id)
                ->whereHas('user', function ($query) {
                    $query->where('is_active', true);
                })
                ->get();

            if ($employees->isNotEmpty()) {
                $designationData[] = [
                    'id' => $designation->id,
                    'name' => $designation->name,
                    'employees' => $employees->map(function ($employee) {
                        return [
                            'id' => $employee->id,
                            'name' => $employee->user->name,
                            'email' => $employee->user->email,
                            'employee_id' => $employee->employee_id,
                        ];
                    }),
                ];
            }
        }

        // Get department manager information
        $managerInfo = null;
        if ($department->manager) {
            $managerEmployee = Employee::with('user', 'designation')
                ->where('user_id', $department->manager_id)
                ->first();

            if ($managerEmployee) {
                $managerInfo = [
                    'id' => $managerEmployee->id,
                    'name' => $managerEmployee->user->name,
                    'email' => $managerEmployee->user->email,
                    'employee_id' => $managerEmployee->employee_id,
                    'designation' => $managerEmployee->designation ? $managerEmployee->designation->name : 'Manager',
                ];
            } else {
                $managerInfo = [
                    'id' => null,
                    'name' => $department->manager->name,
                    'email' => $department->manager->email,
                    'employee_id' => null,
                    'designation' => 'Manager',
                ];
            }
        }

        return Inertia::render('Departments/OrgChart', [
            'department' => $department,
            'manager' => $managerInfo,
            'designations' => $designationData,
        ]);
    }

    /**
     * Display the department's employee list.
     */
    public function employees(Department $department, Request $request)
    {
        $query = Employee::with(['user', 'designation'])
            ->where('department_id', $department->id);

        // Filter by designation
        if ($request->has('designation_id') && $request->designation_id) {
            $query->where('designation_id', $request->designation_id);
        }

        // Filter by status
        if ($request->has('status')) {
            if ($request->status === 'active') {
                $query->whereHas('user', function ($q) {
                    $q->where('is_active', true);
                });
            } elseif ($request->status === 'inactive') {
                $query->whereHas('user', function ($q) {
                    $q->where('is_active', false);
                });
            }
        }

        // Filter by employment status
        if ($request->has('employment_status') && $request->employment_status !== 'all') {
            $query->where('employment_status', $request->employment_status);
        }

        // Search
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('employee_id', 'like', '%' . $search . '%')
                  ->orWhereHas('user', function ($query) use ($search) {
                      $query->where('name', 'like', '%' . $search . '%')
                            ->orWhere('email', 'like', '%' . $search . '%')
                            ->orWhere('phone', 'like', '%' . $search . '%');
                  });
            });
        }

        $employees = $query->orderBy('joining_date', 'desc')->paginate(10)->withQueryString();

        // Get designations for filter
        $designations = Designation::where('department_id', $department->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return Inertia::render('Departments/Employees', [
            'department' => $department,
            'employees' => $employees,
            'designations' => $designations,
            'filters' => $request->only(['designation_id', 'status', 'employment_status', 'search']),
            'employmentStatusOptions' => [
                ['value' => 'all', 'label' => 'All Statuses'],
                ['value' => 'permanent', 'label' => 'Permanent'],
                ['value' => 'probation', 'label' => 'Probation'],
                ['value' => 'contract', 'label' => 'Contract'],
                ['value' => 'part-time', 'label' => 'Part-time'],
            ],
        ]);
    }

    /**
     * Display the department's designations.
     */
    public function designations(Department $department)
    {
        $designations = Designation::where('department_id', $department->id)
            ->orderBy('name')
            ->get();

        // Load employee counts for each designation
        foreach ($designations as $designation) {
            $designation->employee_count = Employee::where('designation_id', $designation->id)
                ->where('department_id', $department->id)
                ->count();
        }

        return Inertia::render('Departments/Designations', [
            'department' => $department,
            'designations' => $designations,
        ]);
    }

    /**
     * Display department report.
     */
    public function report()
    {
        $departments = Department::with('manager')->orderBy('name')->get();

        $departmentData = [];

        foreach ($departments as $department) {
            // Get employees count
            $totalEmployees = Employee::where('department_id', $department->id)->count();

            // Get active employees count
            $activeEmployees = Employee::where('department_id', $department->id)
                ->whereHas('user', function ($query) {
                    $query->where('is_active', true);
                })
                ->count();

            // Get designation count
            $designationCount = Designation::where('department_id', $department->id)->count();

            // Get employment status distribution
            $permanentCount = Employee::where('department_id', $department->id)
                ->where('employment_status', 'permanent')
                ->count();

            $probationCount = Employee::where('department_id', $department->id)
                ->where('employment_status', 'probation')
                ->count();

            $contractCount = Employee::where('department_id', $department->id)
                ->where('employment_status', 'contract')
                ->count();

            $partTimeCount = Employee::where('department_id', $department->id)
                ->where('employment_status', 'part-time')
                ->count();

            $departmentData[] = [
                'id' => $department->id,
                'name' => $department->name,
                'manager' => $department->manager ? $department->manager->name : 'Not Assigned',
                'total_employees' => $totalEmployees,
                'active_employees' => $activeEmployees,
                'designation_count' => $designationCount,
                'employment_status' => [
                    'permanent' => $permanentCount,
                    'probation' => $probationCount,
                    'contract' => $contractCount,
                    'part_time' => $partTimeCount,
                ],
                'is_active' => $department->is_active,
            ];
        }

        // Calculate company totals
        $companyTotals = [
            'total_departments' => $departments->count(),
            'active_departments' => $departments->where('is_active', true)->count(),
            'total_employees' => array_sum(array_column($departmentData, 'total_employees')),
            'active_employees' => array_sum(array_column($departmentData, 'active_employees')),
            'total_designations' => array_sum(array_column($departmentData, 'designation_count')),
            'employment_status' => [
                'permanent' => 0,
                'probation' => 0,
                'contract' => 0,
                'part_time' => 0,
            ],
        ];

        foreach ($departmentData as $dept) {
            $companyTotals['employment_status']['permanent'] += $dept['employment_status']['permanent'];
            $companyTotals['employment_status']['probation'] += $dept['employment_status']['probation'];
            $companyTotals['employment_status']['contract'] += $dept['employment_status']['contract'];
            $companyTotals['employment_status']['part_time'] += $dept['employment_status']['part_time'];
        }

        return Inertia::render('Departments/Report', [
            'departments' => $departmentData,
            'companyTotals' => $companyTotals,
        ]);
    }

    /**
     * Export department list to PDF.
     */
    public function exportPdf()
    {
        $departments = Department::with('manager')->orderBy('name')->get();

        $departmentData = [];

        foreach ($departments as $department) {
            // Get employees count
            $totalEmployees = Employee::where('department_id', $department->id)->count();

            // Get active employees count
            $activeEmployees = Employee::where('department_id', $department->id)
                ->whereHas('user', function ($query) {
                    $query->where('is_active', true);
                })
                ->count();

            // Get designation count
            $designationCount = Designation::where('department_id', $department->id)->count();

            $departmentData[] = [
                'id' => $department->id,
                'name' => $department->name,
                'description' => $department->description,
                'manager' => $department->manager ? $department->manager->name : 'Not Assigned',
                'total_employees' => $totalEmployees,
                'active_employees' => $activeEmployees,
                'designation_count' => $designationCount,
                'is_active' => $department->is_active,
            ];
        }

        return Inertia::render('Departments/ExportPdf', [
            'departments' => $departmentData,
        ]);
    }
}
