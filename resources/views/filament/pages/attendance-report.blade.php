<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Filter Form -->
        <x-filament::section>
            <x-slot name="heading">
                Report Filters
            </x-slot>
            
            {{ $this->form }}
        </x-filament::section>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            @php
                $totalEmployees = $this->getTableQuery()->count();
                $totalAttendance = $this->getTableQuery()->sum('total_days');
                $totalPresent = $this->getTableQuery()->sum('present_days');
                $totalLate = $this->getTableQuery()->sum('late_days');
            @endphp

            <x-filament::section class="text-center">
                <div class="text-2xl font-bold text-primary-600">{{ $totalEmployees }}</div>
                <div class="text-sm text-gray-600">Total Employees</div>
            </x-filament::section>

            <x-filament::section class="text-center">
                <div class="text-2xl font-bold text-success-600">{{ $totalPresent }}</div>
                <div class="text-sm text-gray-600">Present Days</div>
            </x-filament::section>

            <x-filament::section class="text-center">
                <div class="text-2xl font-bold text-warning-600">{{ $totalLate }}</div>
                <div class="text-sm text-gray-600">Late Days</div>
            </x-filament::section>

            <x-filament::section class="text-center">
                <div class="text-2xl font-bold text-info-600">{{ $totalAttendance }}</div>
                <div class="text-sm text-gray-600">Total Attendance</div>
            </x-filament::section>
        </div>

        <!-- Data Table -->
        <x-filament::section>
            <x-slot name="heading">
                Detailed Report
            </x-slot>
            
            {{ $this->table }}
        </x-filament::section>
    </div>
</x-filament-panels::page>