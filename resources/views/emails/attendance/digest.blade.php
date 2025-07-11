<x-mail::message>
# {{ ucfirst($period) }} Attendance Summary

Hello! Here's your {{ $period }} attendance summary for **{{ $summaryData['tenant_name'] }}**.

## Overview
@if($period === 'daily')
**Date:** {{ \Carbon\Carbon::parse($summaryData['date'])->format('M d, Y') }}
@else
**Week:** {{ \Carbon\Carbon::parse($summaryData['week_start'])->format('M d') }} - {{ \Carbon\Carbon::parse($summaryData['week_end'])->format('M d, Y') }}
@endif

<x-mail::table>
| Metric | Count | Percentage |
|:-------|:------|:-----------|
| **Total Employees** | {{ $summaryData['total_employees'] }} | 100% |
| **Present** | {{ $summaryData['present'] }} | {{ $summaryData['present_percentage'] }}% |
| **Late** | {{ $summaryData['late'] }} | {{ $summaryData['late_percentage'] }}% |
| **Absent** | {{ $summaryData['absent'] }} | {{ $summaryData['absent_percentage'] }}% |
</x-mail::table>

@if($summaryData['top_late_employee'] ?? null)
## 🚨 Attention Required
**Most Late Employee:** {{ $summaryData['top_late_employee'] }}  
**Late Minutes:** {{ $summaryData['top_late_minutes'] }} minutes

@if($period === 'weekly' && isset($summaryData['avg_late_minutes']))
**Average Late Minutes:** {{ $summaryData['avg_late_minutes'] }} minutes
@endif
@endif

## Performance Indicators
@if($summaryData['present_percentage'] >= 95)
✅ **Excellent** attendance rate!
@elseif($summaryData['present_percentage'] >= 85)
⚠️ **Good** attendance rate, but room for improvement.
@else
🚨 **Poor** attendance rate - immediate attention required.
@endif

<x-mail::button :url="url('/admin')" color="primary">
View Dashboard
</x-mail::button>

Thanks for using our attendance management system!

Best regards,  
{{ config('app.name') }}
</x-mail::message>