<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\LeaveType;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class LeaveApplicationController extends Controller
{
    /**
     * Display a listing of the leave applications.
     */
    public function index(Request $request)
    {
        $query = LeaveApplication::with(['employee.user', 'employee.department', 'leaveType', 'approvedBy']);

        // Filter by status
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->has('from_date') && $request->from_date) {
            $query->where('start_date', '>=', $request->from_date);
        }

        if ($request->has('to_date') && $request->to_date) {
            $query->where('end_date', '<=', $request->to_date);
        }

        // Filter by employee
        if ($request->has('employee_id') && $request->employee_id) {
            $query->where('employee_id', $request->employee_id);
        }

        // Filter by department
        if ($request->has('department_id') && $request->department_id) {
            $query->whereHas('employee', function ($q) use ($request) {
                $q->where('department_id', $request->department_id);
            });
        }

        // Filter by leave type
        if ($request->has('leave_type_id') && $request->leave_type_id) {
            $query->where('leave_type_id', $request->leave_type_id);
        }

        // Filter by time period
        if ($request->has('period') && $request->period !== 'all') {
            if ($request->period === 'current') {
                $query->current();
            } elseif ($request->period === 'upcoming') {
                $query->upcoming();
            } elseif ($request->period === 'past') {
                $query->past();
            }
        }

        // Search by employee name or ID
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('employee', function ($query) use ($search) {
                    $query->where('employee_id', 'like', "%{$search}%")
                          ->orWhereHas('user', function ($query) use ($search) {
                              $query->where('name', 'like', "%{$search}%")
                                    ->orWhere('email', 'like', "%{$search}%");
                          });
                });
            });
        }

        // Apply sorting
        $query->orderBy('created_at', 'desc');

        $leaveApplications = $query->paginate(10)->withQueryString();

        // Load data for filters
        $employees = Employee::with('user')->where('is_active', true)->get();
        $departments = Department::where('is_active', true)->get();
        $leaveTypes = LeaveType::where('is_active', true)->get();

        return Inertia::render('LeaveApplications/Index', [
            'leaveApplications' => $leaveApplications,
            'employees' => $employees,
            'departments' => $departments,
            'leaveTypes' => $leaveTypes,
            'filters' => $request->only(['status', 'from_date', 'to_date', 'employee_id', 'department_id', 'leave_type_id', 'period', 'search']),
            'statusOptions' => [
                ['value' => 'all', 'label' => 'All Statuses'],
                ['value' => 'pending', 'label' => 'Pending'],
                ['value' => 'approved', 'label' => 'Approved'],
                ['value' => 'rejected', 'label' => 'Rejected'],
            ],
            'periodOptions' => [
                ['value' => 'all', 'label' => 'All Periods'],
                ['value' => 'current', 'label' => 'Current'],
                ['value' => 'upcoming', 'label' => 'Upcoming'],
                ['value' => 'past', 'label' => 'Past'],
            ],
        ]);
    }

    /**
     * Show the form for creating a new leave application.
     */
    public function create()
    {
        $employees = Employee::with(['user', 'leaveApplications'])->where('is_active', true)->get();
        $leaveTypes = LeaveType::where('is_active', true)->get();

        return Inertia::render('LeaveApplications/Create', [
            'employees' => $employees,
            'leaveTypes' => $leaveTypes,
        ]);
    }

    /**
     * Store a newly created leave application in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:employees,id',
            'leave_type_id' => 'required|exists:leave_types,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'required|string',
            'status' => 'required|in:pending,approved,rejected',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        // Calculate total days
        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);
        $totalDays = $startDate->diffInDays($endDate) + 1; // Include both start and end dates

        try {
            DB::beginTransaction();

            // Check if the employee has enough leave balance
            $employee = Employee::findOrFail($request->employee_id);
            $leaveType = LeaveType::findOrFail($request->leave_type_id);

            // Get already approved leaves of this type for the employee in the current year
            $currentYear = Carbon::now()->year;
            $usedLeaveDays = LeaveApplication::where('employee_id', $employee->id)
                ->where('leave_type_id', $leaveType->id)
                ->where('status', 'approved')
                ->whereYear('start_date', $currentYear)
                ->sum('total_days');

            $remainingLeaveDays = $leaveType->days_allowed_per_year - $usedLeaveDays;

            // Check if the requested leave days exceed the remaining balance
            if ($totalDays > $remainingLeaveDays && $request->status === 'approved') {
                return back()->withErrors([
                    'total_days' => "The employee only has {$remainingLeaveDays} days of {$leaveType->name} leave remaining for the year.",
                ])->withInput();
            }

            // Check for overlapping approved leaves
            $overlappingLeaves = LeaveApplication::where('employee_id', $employee->id)
                ->where('status', 'approved')
                ->where(function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('start_date', [$startDate, $endDate])
                        ->orWhereBetween('end_date', [$startDate, $endDate])
                        ->orWhere(function ($query) use ($startDate, $endDate) {
                            $query->where('start_date', '<=', $startDate)
                                ->where('end_date', '>=', $endDate);
                        });
                })
                ->count();

            if ($overlappingLeaves > 0 && $request->status === 'approved') {
                return back()->withErrors([
                    'period' => "The employee already has approved leave during this period.",
                ])->withInput();
            }

            // Create leave application
            $leaveApplication = LeaveApplication::create([
                'employee_id' => $request->employee_id,
                'leave_type_id' => $request->leave_type_id,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'total_days' => $totalDays,
                'reason' => $request->reason,
                'status' => $request->status,
                'approved_by' => $request->status !== 'pending' ? Auth::id() : null,
                'remarks' => $request->remarks,
            ]);

            DB::commit();

            return redirect()->route('leave-applications.show', $leaveApplication->id)
                ->with('success', 'Leave application created successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'An error occurred: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Display the specified leave application.
     */
    public function show(LeaveApplication $leaveApplication)
    {
        $leaveApplication->load(['employee.user', 'leaveType', 'approvedBy']);

        // Get leave balance information
        $currentYear = Carbon::now()->year;
        $employee = $leaveApplication->employee;
        $leaveType = $leaveApplication->leaveType;

        $usedLeaveDays = LeaveApplication::where('employee_id', $employee->id)
            ->where('leave_type_id', $leaveType->id)
            ->where('status', 'approved')
            ->whereYear('start_date', $currentYear)
            ->sum('total_days');

        $remainingLeaveDays = $leaveType->days_allowed_per_year - $usedLeaveDays;

        // If this application is approved, add back these days to calculate the balance before this application
        if ($leaveApplication->status === 'approved') {
            $remainingLeaveDays += $leaveApplication->total_days;
        }

        return Inertia::render('LeaveApplications/Show', [
            'leaveApplication' => $leaveApplication,
            'leaveBalance' => [
                'allowed' => $leaveType->days_allowed_per_year,
                'used' => $usedLeaveDays,
                'remaining' => $remainingLeaveDays,
            ],
        ]);
    }

    /**
     * Show the form for editing the specified leave application.
     */
    public function edit(LeaveApplication $leaveApplication)
    {
        // Only allow editing of pending applications
        if ($leaveApplication->status !== 'pending') {
            return redirect()->route('leave-applications.show', $leaveApplication->id)
                ->with('error', 'Only pending leave applications can be edited.');
        }

        $leaveApplication->load(['employee.user']);

        $employees = Employee::with(['user', 'leaveApplications'])->where('is_active', true)->get();
        $leaveTypes = LeaveType::where('is_active', true)->get();

        return Inertia::render('LeaveApplications/Edit', [
            'leaveApplication' => $leaveApplication,
            'employees' => $employees,
            'leaveTypes' => $leaveTypes,
        ]);
    }

    /**
     * Update the specified leave application in storage.
     */
    public function update(Request $request, LeaveApplication $leaveApplication)
    {
        // Only allow updating of pending applications
        if ($leaveApplication->status !== 'pending') {
            return redirect()->route('leave-applications.show', $leaveApplication->id)
                ->with('error', 'Only pending leave applications can be updated.');
        }

        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:employees,id',
            'leave_type_id' => 'required|exists:leave_types,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'required|string',
            'status' => 'required|in:pending,approved,rejected',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        // Calculate total days
        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);
        $totalDays = $startDate->diffInDays($endDate) + 1; // Include both start and end dates

        try {
            DB::beginTransaction();

            // Check if the employee has enough leave balance
            $employee = Employee::findOrFail($request->employee_id);
            $leaveType = LeaveType::findOrFail($request->leave_type_id);

            // Get already approved leaves of this type for the employee in the current year
            $currentYear = Carbon::now()->year;
            $usedLeaveDays = LeaveApplication::where('employee_id', $employee->id)
                ->where('leave_type_id', $leaveType->id)
                ->where('status', 'approved')
                ->whereYear('start_date', $currentYear)
                ->sum('total_days');

            $remainingLeaveDays = $leaveType->days_allowed_per_year - $usedLeaveDays;

            // Check if the requested leave days exceed the remaining balance
            if ($totalDays > $remainingLeaveDays && $request->status === 'approved') {
                return back()->withErrors([
                    'total_days' => "The employee only has {$remainingLeaveDays} days of {$leaveType->name} leave remaining for the year.",
                ])->withInput();
            }

            // Check for overlapping approved leaves
            $overlappingLeaves = LeaveApplication::where('employee_id', $employee->id)
                ->where('id', '!=', $leaveApplication->id) // Exclude current application
                ->where('status', 'approved')
                ->where(function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('start_date', [$startDate, $endDate])
                        ->orWhereBetween('end_date', [$startDate, $endDate])
                        ->orWhere(function ($query) use ($startDate, $endDate) {
                            $query->where('start_date', '<=', $startDate)
                                ->where('end_date', '>=', $endDate);
                        });
                })
                ->count();

            if ($overlappingLeaves > 0 && $request->status === 'approved') {
                return back()->withErrors([
                    'period' => "The employee already has approved leave during this period.",
                ])->withInput();
            }

            // Update leave application
            $leaveApplication->update([
                'employee_id' => $request->employee_id,
                'leave_type_id' => $request->leave_type_id,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'total_days' => $totalDays,
                'reason' => $request->reason,
                'status' => $request->status,
                'approved_by' => $request->status !== 'pending' ? Auth::id() : null,
                'remarks' => $request->remarks,
            ]);

            DB::commit();

            return redirect()->route('leave-applications.show', $leaveApplication->id)
                ->with('success', 'Leave application updated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'An error occurred: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Remove the specified leave application from storage.
     */
    public function destroy(LeaveApplication $leaveApplication)
    {
        // Only allow deletion of pending applications
        if ($leaveApplication->status !== 'pending') {
            return back()->with('error', 'Only pending leave applications can be deleted.');
        }

        try {
            $leaveApplication->delete();

            return redirect()->route('leave-applications.index')
                ->with('success', 'Leave application deleted successfully.');

        } catch (\Exception $e) {
            return back()->with('error', 'An error occurred: ' . $e->getMessage());
        }
    }

    /**
     * Approve the specified leave application.
     */
    public function approve(Request $request, LeaveApplication $leaveApplication)
    {
        if ($leaveApplication->status !== 'pending') {
            return back()->with('error', 'Only pending leave applications can be approved.');
        }

        $validator = Validator::make($request->all(), [
            'remarks' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            DB::beginTransaction();

            // Check if the employee has enough leave balance
            $employee = $leaveApplication->employee;
            $leaveType = $leaveApplication->leaveType;

            // Get already approved leaves of this type for the employee in the current year
            $currentYear = Carbon::now()->year;
            $usedLeaveDays = LeaveApplication::where('employee_id', $employee->id)
                ->where('leave_type_id', $leaveType->id)
                ->where('status', 'approved')
                ->whereYear('start_date', $currentYear)
                ->sum('total_days');

            $remainingLeaveDays = $leaveType->days_allowed_per_year - $usedLeaveDays;

            // Check if the requested leave days exceed the remaining balance
            if ($leaveApplication->total_days > $remainingLeaveDays) {
                return back()->withErrors([
                    'balance' => "The employee only has {$remainingLeaveDays} days of {$leaveType->name} leave remaining for the year.",
                ])->withInput();
            }

            // Check for overlapping approved leaves
            $overlappingLeaves = LeaveApplication::where('employee_id', $employee->id)
                ->where('id', '!=', $leaveApplication->id) // Exclude current application
                ->where('status', 'approved')
                ->where(function ($query) use ($leaveApplication) {
                    $query->whereBetween('start_date', [$leaveApplication->start_date, $leaveApplication->end_date])
                        ->orWhereBetween('end_date', [$leaveApplication->start_date, $leaveApplication->end_date])
                        ->orWhere(function ($query) use ($leaveApplication) {
                            $query->where('start_date', '<=', $leaveApplication->start_date)
                                ->where('end_date', '>=', $leaveApplication->end_date);
                        });
                })
                ->count();

            if ($overlappingLeaves > 0) {
                return back()->withErrors([
                    'period' => "The employee already has approved leave during this period.",
                ])->withInput();
            }

            // Update leave application
            $leaveApplication->update([
                'status' => 'approved',
                'approved_by' => Auth::id(),
                'remarks' => $request->remarks ?? $leaveApplication->remarks,
            ]);

            DB::commit();

            return redirect()->route('leave-applications.show', $leaveApplication->id)
                ->with('success', 'Leave application approved successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'An error occurred: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Reject the specified leave application.
     */
    public function reject(Request $request, LeaveApplication $leaveApplication)
    {
        if ($leaveApplication->status !== 'pending') {
            return back()->with('error', 'Only pending leave applications can be rejected.');
        }

        $validator = Validator::make($request->all(), [
            'remarks' => 'required|string',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            // Update leave application
            $leaveApplication->update([
                'status' => 'rejected',
                'approved_by' => Auth::id(),
                'remarks' => $request->remarks,
            ]);

            return redirect()->route('leave-applications.show', $leaveApplication->id)
                ->with('success', 'Leave application rejected successfully.');

        } catch (\Exception $e) {
            return back()->with('error', 'An error occurred: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Display the employee's leave balances.
     */
    public function employeeLeaveBalances(Request $request)
    {
        $query = Employee::with(['user', 'department', 'designation'])->where('is_active', true);

        // Filter by department
        if ($request->has('department_id') && $request->department_id) {
            $query->where('department_id', $request->department_id);
        }

        $employees = $query->orderBy('employee_id', 'asc')->get();
        $leaveTypes = LeaveType::where('is_active', true)->get();
        $departments = Department::where('is_active', true)->get();

        $currentYear = Carbon::now()->year;

        $employeeLeaveBalances = [];

        foreach ($employees as $employee) {
            $balances = [];

            foreach ($leaveTypes as $leaveType) {
                $usedLeaveDays = LeaveApplication::where('employee_id', $employee->id)
                    ->where('leave_type_id', $leaveType->id)
                    ->where('status', 'approved')
                    ->whereYear('start_date', $currentYear)
                    ->sum('total_days');

                $remainingLeaveDays = $leaveType->days_allowed_per_year - $usedLeaveDays;

                $balances[] = [
                    'leave_type' => $leaveType->name,
                    'leave_type_id' => $leaveType->id,
                    'allowed' => $leaveType->days_allowed_per_year,
                    'used' => $usedLeaveDays,
                    'remaining' => $remainingLeaveDays,
                    'is_paid' => $leaveType->is_paid,
                ];
            }

            $employeeLeaveBalances[] = [
                'employee_id' => $employee->id,
                'employee_code' => $employee->employee_id,
                'name' => $employee->user->name,
                'department' => $employee->department->name,
                'designation' => $employee->designation->name,
                'balances' => $balances,
            ];
        }

        return Inertia::render('LeaveApplications/EmployeeLeaveBalances', [
            'employees' => $employeeLeaveBalances,
            'leaveTypes' => $leaveTypes,
            'departments' => $departments,
            'filters' => $request->only(['department_id']),
            'currentYear' => $currentYear,
        ]);
    }

    /**
     * Generate leave calendar view.
     */
    public function leaveCalendar(Request $request)
    {
        // Default to current month if not specified
        $month = $request->month ? Carbon::parse($request->month . '-01') : Carbon::now()->startOfMonth();

        // Get all approved leaves that overlap with the selected month
        $query = LeaveApplication::with(['employee.user', 'leaveType'])
            ->where('status', 'approved')
            ->where(function ($query) use ($month) {
                $monthStart = $month->copy()->startOfMonth();
                $monthEnd = $month->copy()->endOfMonth();

                $query->whereBetween('start_date', [$monthStart, $monthEnd])
                    ->orWhereBetween('end_date', [$monthStart, $monthEnd])
                    ->orWhere(function ($query) use ($monthStart, $monthEnd) {
                        $query->where('start_date', '<=', $monthStart)
                            ->where('end_date', '>=', $monthEnd);
                    });
            });

        // Filter by department
        if ($request->has('department_id') && $request->department_id) {
            $query->whereHas('employee', function ($q) use ($request) {
                $q->where('department_id', $request->department_id);
            });
        }

        // Filter by leave type
        if ($request->has('leave_type_id') && $request->leave_type_id) {
            $query->where('leave_type_id', $request->leave_type_id);
        }

        $leaveApplications = $query->get();

        // Format for calendar
        $calendarData = [];

        $firstDay = $month->copy()->startOfMonth();
        $lastDay = $month->copy()->endOfMonth();

        for ($day = $firstDay->copy(); $day->lte($lastDay); $day->addDay()) {
            $dayData = [
                'date' => $day->format('Y-m-d'),
                'day' => $day->day,
                'dayOfWeek' => $day->dayOfWeek,
                'isWeekend' => $day->isWeekend(),
                'leaves' => [],
            ];

            foreach ($leaveApplications as $leave) {
                if ($day->between($leave->start_date, $leave->end_date)) {
                    $dayData['leaves'][] = [
                        'id' => $leave->id,
                        'employee_name' => $leave->employee->user->name,
                        'leave_type' => $leave->leaveType->name,
                        'leave_type_id' => $leave->leave_type_id,
                        'total_days' => $leave->total_days,
                    ];
                }
            }

            $calendarData[] = $dayData;
        }

        // Prepare data for month picker
        $currentMonth = Carbon::now()->startOfMonth();
        $monthOptions = [];

        // Show 6 months before and after current month
        for ($i = -6; $i <= 6; $i++) {
            $tempMonth = $currentMonth->copy()->addMonths($i);
            $monthOptions[] = [
                'value' => $tempMonth->format('Y-m'),
                'label' => $tempMonth->format('F Y'),
            ];
        }

        $departments = Department::where('is_active', true)->get();
        $leaveTypes = LeaveType::where('is_active', true)->get();

        return Inertia::render('LeaveApplications/LeaveCalendar', [
            'calendarData' => $calendarData,
            'month' => $month->format('F Y'),
            'monthValue' => $month->format('Y-m'),
            'monthOptions' => $monthOptions,
            'departments' => $departments,
            'leaveTypes' => $leaveTypes,
            'filters' => $request->only(['month', 'department_id', 'leave_type_id']),
        ]);
    }

    /**
     * Generate leave report.
     */
    public function leaveReport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date|after_or_equal:from_date',
            'department_id' => 'nullable|exists:departments,id',
            'leave_type_id' => 'nullable|exists:leave_types,id',
            'group_by' => 'required|in:department,leave_type,employee',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        // Default to current year if dates not provided
        $fromDate = $request->from_date ? Carbon::parse($request->from_date) : Carbon::now()->startOfYear();
        $toDate = $request->to_date ? Carbon::parse($request->to_date) : Carbon::now()->endOfYear();

        // Build query
        $query = LeaveApplication::with(['employee.user', 'employee.department', 'leaveType'])
            ->where('status', 'approved')
            ->whereBetween('start_date', [$fromDate, $toDate]);

        // Apply filters
        if ($request->has('department_id') && $request->department_id) {
            $query->whereHas('employee', function ($q) use ($request) {
                $q->where('department_id', $request->department_id);
            });
        }

        if ($request->has('leave_type_id') && $request->leave_type_id) {
            $query->where('leave_type_id', $request->leave_type_id);
        }

        $leaveApplications = $query->get();

        // Group data based on parameter
        $reportData = [];

        if ($request->group_by === 'department') {
            // Group by department
            $departmentWiseData = [];

            foreach ($leaveApplications as $leave) {
                $departmentId = $leave->employee->department_id;
                $departmentName = $leave->employee->department->name;

                if (!isset($departmentWiseData[$departmentId])) {
                    $departmentWiseData[$departmentId] = [
                        'name' => $departmentName,
                        'total_days' => 0,
                        'employee_count' => 0,
                        'employees' => [],
                        'leave_types' => [],
                    ];
                }

                $departmentWiseData[$departmentId]['total_days'] += $leave->total_days;

                // Track unique employees
                if (!isset($departmentWiseData[$departmentId]['employees'][$leave->employee_id])) {
                    $departmentWiseData[$departmentId]['employees'][$leave->employee_id] = true;
                    $departmentWiseData[$departmentId]['employee_count']++;
                }

                // Track leave types
                $leaveTypeId = $leave->leave_type_id;
                $leaveTypeName = $leave->leaveType->name;

                if (!isset($departmentWiseData[$departmentId]['leave_types'][$leaveTypeId])) {
                    $departmentWiseData[$departmentId]['leave_types'][$leaveTypeId] = [
                        'name' => $leaveTypeName,
                        'days' => 0,
                    ];
                }

                $departmentWiseData[$departmentId]['leave_types'][$leaveTypeId]['days'] += $leave->total_days;
            }

            // Clean up the data structure for frontend
            foreach ($departmentWiseData as &$department) {
                unset($department['employees']);
                $department['leave_types'] = array_values($department['leave_types']);
            }

            $reportData = array_values($departmentWiseData);

        } elseif ($request->group_by === 'leave_type') {
            // Group by leave type
            $leaveTypeWiseData = [];

            foreach ($leaveApplications as $leave) {
                $leaveTypeId = $leave->leave_type_id;
                $leaveTypeName = $leave->leaveType->name;

                if (!isset($leaveTypeWiseData[$leaveTypeId])) {
                    $leaveTypeWiseData[$leaveTypeId] = [
                        'name' => $leaveTypeName,
                        'is_paid' => $leave->leaveType->is_paid,
                        'total_days' => 0,
                        'employee_count' => 0,
                        'employees' => [],
                        'departments' => [],
                    ];
                }

                $leaveTypeWiseData[$leaveTypeId]['total_days'] += $leave->total_days;

                // Track unique employees
                if (!isset($leaveTypeWiseData[$leaveTypeId]['employees'][$leave->employee_id])) {
                    $leaveTypeWiseData[$leaveTypeId]['employees'][$leave->employee_id] = true;
                    $leaveTypeWiseData[$leaveTypeId]['employee_count']++;
                }

                // Track departments
                $departmentId = $leave->employee->department_id;
                $departmentName = $leave->employee->department->name;

                if (!isset($leaveTypeWiseData[$leaveTypeId]['departments'][$departmentId])) {
                    $leaveTypeWiseData[$leaveTypeId]['departments'][$departmentId] = [
                        'name' => $departmentName,
                        'days' => 0,
                    ];
                }

                $leaveTypeWiseData[$leaveTypeId]['departments'][$departmentId]['days'] += $leave->total_days;
            }

            // Clean up the data structure for frontend
            foreach ($leaveTypeWiseData as &$leaveType) {
                unset($leaveType['employees']);
                $leaveType['departments'] = array_values($leaveType['departments']);
            }

            $reportData = array_values($leaveTypeWiseData);

        } else { // group_by === 'employee'
            // Group by employee
            $employeeWiseData = [];

            foreach ($leaveApplications as $leave) {
                $employeeId = $leave->employee_id;
                $employeeName = $leave->employee->user->name;
                $employeeCode = $leave->employee->employee_id;
                $departmentName = $leave->employee->department->name;

                if (!isset($employeeWiseData[$employeeId])) {
                    $employeeWiseData[$employeeId] = [
                        'id' => $employeeId,
                        'name' => $employeeName,
                        'employee_code' => $employeeCode,
                        'department' => $departmentName,
                        'total_days' => 0,
                        'leave_types' => [],
                    ];
                }

                $employeeWiseData[$employeeId]['total_days'] += $leave->total_days;

                // Track leave types
                $leaveTypeId = $leave->leave_type_id;
                $leaveTypeName = $leave->leaveType->name;

                if (!isset($employeeWiseData[$employeeId]['leave_types'][$leaveTypeId])) {
                    $employeeWiseData[$employeeId]['leave_types'][$leaveTypeId] = [
                        'name' => $leaveTypeName,
                        'days' => 0,
                        'instances' => [],
                    ];
                }

                $employeeWiseData[$employeeId]['leave_types'][$leaveTypeId]['days'] += $leave->total_days;

                // Add leave instances
                $employeeWiseData[$employeeId]['leave_types'][$leaveTypeId]['instances'][] = [
                    'id' => $leave->id,
                    'start_date' => $leave->start_date->format('Y-m-d'),
                    'end_date' => $leave->end_date->format('Y-m-d'),
                    'total_days' => $leave->total_days,
                ];
            }

            // Clean up the data structure for frontend
            foreach ($employeeWiseData as &$employee) {
                $employee['leave_types'] = array_values($employee['leave_types']);
            }

            $reportData = array_values($employeeWiseData);
        }

        // Load data for filters
        $departments = Department::where('is_active', true)->get();
        $leaveTypes = LeaveType::where('is_active', true)->get();

        return Inertia::render('LeaveApplications/LeaveReport', [
            'reportData' => $reportData,
            'departments' => $departments,
            'leaveTypes' => $leaveTypes,
            'filters' => $request->only(['from_date', 'to_date', 'department_id', 'leave_type_id', 'group_by']),
            'dateRange' => [
                'from' => $fromDate->format('Y-m-d'),
                'to' => $toDate->format('Y-m-d'),
            ],
            'groupByOptions' => [
                ['value' => 'department', 'label' => 'Department'],
                ['value' => 'leave_type', 'label' => 'Leave Type'],
                ['value' => 'employee', 'label' => 'Employee'],
            ],
        ]);
    }

    /**
     * Display leave application form for self application (for employees).
     */
    public function selfApplication()
    {
        // Get the employee ID of the logged-in user
        $user = Auth::user();
        $employee = Employee::where('user_id', $user->id)->first();

        if (!$employee) {
            return redirect()->route('leave-applications.index')
                ->with('error', 'You do not have an employee profile.');
        }

        $leaveTypes = LeaveType::where('is_active', true)->get();

        // Get leave balances for the employee
        $currentYear = Carbon::now()->year;
        $leaveBalances = [];

        foreach ($leaveTypes as $leaveType) {
            $usedLeaveDays = LeaveApplication::where('employee_id', $employee->id)
                ->where('leave_type_id', $leaveType->id)
                ->where('status', 'approved')
                ->whereYear('start_date', $currentYear)
                ->sum('total_days');

            $remainingLeaveDays = $leaveType->days_allowed_per_year - $usedLeaveDays;

            $leaveBalances[] = [
                'leave_type_id' => $leaveType->id,
                'leave_type_name' => $leaveType->name,
                'allowed' => $leaveType->days_allowed_per_year,
                'used' => $usedLeaveDays,
                'remaining' => $remainingLeaveDays,
                'is_paid' => $leaveType->is_paid,
            ];
        }

        // Get recent leave applications
        $recentApplications = LeaveApplication::with(['leaveType'])
            ->where('employee_id', $employee->id)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return Inertia::render('LeaveApplications/SelfApplication', [
            'employee' => $employee,
            'leaveTypes' => $leaveTypes,
            'leaveBalances' => $leaveBalances,
            'recentApplications' => $recentApplications,
        ]);
    }

    /**
     * Submit a leave application for self.
     */
    public function storeSelfApplication(Request $request)
    {
        // Get the employee ID of the logged-in user
        $user = Auth::user();
        $employee = Employee::where('user_id', $user->id)->first();

        if (!$employee) {
            return redirect()->route('leave-applications.index')
                ->with('error', 'You do not have an employee profile.');
        }

        $validator = Validator::make($request->all(), [
            'leave_type_id' => 'required|exists:leave_types,id',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'required|string',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        // Calculate total days
        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);
        $totalDays = $startDate->diffInDays($endDate) + 1; // Include both start and end dates

        try {
            DB::beginTransaction();

            // Check if the employee has enough leave balance
            $leaveType = LeaveType::findOrFail($request->leave_type_id);

            // Get already approved leaves of this type for the employee in the current year
            $currentYear = Carbon::now()->year;
            $usedLeaveDays = LeaveApplication::where('employee_id', $employee->id)
                ->where('leave_type_id', $leaveType->id)
                ->where('status', 'approved')
                ->whereYear('start_date', $currentYear)
                ->sum('total_days');

            $remainingLeaveDays = $leaveType->days_allowed_per_year - $usedLeaveDays;

            // Check if the requested leave days exceed the remaining balance
            if ($totalDays > $remainingLeaveDays) {
                return back()->withErrors([
                    'total_days' => "You only have {$remainingLeaveDays} days of {$leaveType->name} leave remaining for the year.",
                ])->withInput();
            }

            // Check for overlapping approved leaves
            $overlappingLeaves = LeaveApplication::where('employee_id', $employee->id)
                ->whereIn('status', ['approved', 'pending'])
                ->where(function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('start_date', [$startDate, $endDate])
                        ->orWhereBetween('end_date', [$startDate, $endDate])
                        ->orWhere(function ($query) use ($startDate, $endDate) {
                            $query->where('start_date', '<=', $startDate)
                                ->where('end_date', '>=', $endDate);
                        });
                })
                ->count();

            if ($overlappingLeaves > 0) {
                return back()->withErrors([
                    'period' => "You already have leave applications for this period.",
                ])->withInput();
            }

            // Create leave application
            $leaveApplication = LeaveApplication::create([
                'employee_id' => $employee->id,
                'leave_type_id' => $request->leave_type_id,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'total_days' => $totalDays,
                'reason' => $request->reason,
                'status' => 'pending',
            ]);

            DB::commit();

            return redirect()->route('leave-applications.self-application')
                ->with('success', 'Leave application submitted successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'An error occurred: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Bulk approve leave applications.
     */
    public function bulkApprove(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'application_ids' => 'required|array|min:1',
            'application_ids.*' => 'exists:leave_applications,id',
            'remarks' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $successCount = 0;
        $errorCount = 0;
        $errors = [];

        foreach ($request->application_ids as $id) {
            try {
                $leaveApplication = LeaveApplication::find($id);

                if (!$leaveApplication || $leaveApplication->status !== 'pending') {
                    $errorCount++;
                    continue;
                }

                // Check leave balance and overlapping leaves
                $employee = $leaveApplication->employee;
                $leaveType = $leaveApplication->leaveType;

                // Get already approved leaves of this type for the employee in the current year
                $currentYear = Carbon::now()->year;
                $usedLeaveDays = LeaveApplication::where('employee_id', $employee->id)
                    ->where('leave_type_id', $leaveType->id)
                    ->where('status', 'approved')
                    ->whereYear('start_date', $currentYear)
                    ->sum('total_days');

                $remainingLeaveDays = $leaveType->days_allowed_per_year - $usedLeaveDays;

                // Check if the requested leave days exceed the remaining balance
                if ($leaveApplication->total_days > $remainingLeaveDays) {
                    $errors[] = "Employee {$employee->employee_id} only has {$remainingLeaveDays} days of {$leaveType->name} leave remaining.";
                    $errorCount++;
                    continue;
                }

                // Check for overlapping approved leaves
                $overlappingLeaves = LeaveApplication::where('employee_id', $employee->id)
                    ->where('id', '!=', $leaveApplication->id)
                    ->where('status', 'approved')
                    ->where(function ($query) use ($leaveApplication) {
                        $query->whereBetween('start_date', [$leaveApplication->start_date, $leaveApplication->end_date])
                            ->orWhereBetween('end_date', [$leaveApplication->start_date, $leaveApplication->end_date])
                            ->orWhere(function ($query) use ($leaveApplication) {
                                $query->where('start_date', '<=', $leaveApplication->start_date)
                                    ->where('end_date', '>=', $leaveApplication->end_date);
                            });
                    })
                    ->count();

                if ($overlappingLeaves > 0) {
                    $errors[] = "Employee {$employee->employee_id} already has approved leave during this period.";
                    $errorCount++;
                    continue;
                }

                // Approve the leave application
                $leaveApplication->update([
                    'status' => 'approved',
                    'approved_by' => Auth::id(),
                    'remarks' => $request->remarks,
                ]);

                $successCount++;

            } catch (\Exception $e) {
                $errorCount++;
                $errors[] = $e->getMessage();
            }
        }

        $message = "Successfully approved {$successCount} leave applications.";

        if ($errorCount > 0) {
            $message .= " Failed to approve {$errorCount} applications.";

            if (!empty($errors)) {
                $message .= " First error: " . $errors[0];
            }

            return back()->with('warning', $message);
        }

        return back()->with('success', $message);
    }

    /**
     * Bulk reject leave applications.
     */
    public function bulkReject(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'application_ids' => 'required|array|min:1',
            'application_ids.*' => 'exists:leave_applications,id',
            'remarks' => 'required|string',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $successCount = 0;
        $errorCount = 0;

        foreach ($request->application_ids as $id) {
            try {
                $leaveApplication = LeaveApplication::find($id);

                if (!$leaveApplication || $leaveApplication->status !== 'pending') {
                    $errorCount++;
                    continue;
                }

                // Reject the leave application
                $leaveApplication->update([
                    'status' => 'rejected',
                    'approved_by' => Auth::id(),
                    'remarks' => $request->remarks,
                ]);

                $successCount++;

            } catch (\Exception $e) {
                $errorCount++;
            }
        }

        $message = "Successfully rejected {$successCount} leave applications.";

        if ($errorCount > 0) {
            $message .= " Failed to reject {$errorCount} applications.";
            return back()->with('warning', $message);
        }

        return back()->with('success', $message);
    }

    /**
     * Export leave applications to PDF.
     */
    public function exportPdf(Request $request)
    {
        $query = LeaveApplication::with(['employee.user', 'employee.department', 'leaveType', 'approvedBy']);

        // Apply filters
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->has('from_date') && $request->from_date) {
            $query->where('start_date', '>=', $request->from_date);
        }

        if ($request->has('to_date') && $request->to_date) {
            $query->where('end_date', '<=', $request->to_date);
        }

        if ($request->has('employee_id') && $request->employee_id) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->has('department_id') && $request->department_id) {
            $query->whereHas('employee', function ($q) use ($request) {
                $q->where('department_id', $request->department_id);
            });
        }

        if ($request->has('leave_type_id') && $request->leave_type_id) {
            $query->where('leave_type_id', $request->leave_type_id);
        }

        $leaveApplications = $query->orderBy('start_date', 'desc')->get();

        // Group by department if requested
        $groupedData = null;
        if ($request->has('group_by') && $request->group_by === 'department') {
            $groupedData = $leaveApplications->groupBy(function ($item) {
                return $item->employee->department->name;
            });
        } elseif ($request->has('group_by') && $request->group_by === 'leave_type') {
            $groupedData = $leaveApplications->groupBy(function ($item) {
                return $item->leaveType->name;
            });
        }

        // Calculate totals
        $totalDays = $leaveApplications->sum('total_days');
        $employeeCount = $leaveApplications->pluck('employee_id')->unique()->count();

        $departments = Department::where('is_active', true)->get();
        $leaveTypes = LeaveType::where('is_active', true)->get();

        return Inertia::render('LeaveApplications/ExportPdf', [
            'leaveApplications' => $leaveApplications,
            'groupedData' => $groupedData ? $groupedData->toArray() : null,
            'departments' => $departments,
            'leaveTypes' => $leaveTypes,
            'filters' => $request->only(['status', 'from_date', 'to_date', 'employee_id', 'department_id', 'leave_type_id', 'group_by']),
            'totals' => [
                'total_days' => $totalDays,
                'employee_count' => $employeeCount,
            ],
        ]);
    }
}
