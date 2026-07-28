<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 12px; color: #222; }
        h1 { font-size: 18px; text-align: center; margin-bottom: 4px; }
        h2 { font-size: 13px; text-align: center; color: #555; margin-top: 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        td, th { padding: 6px 8px; border: 1px solid #ccc; text-align: left; }
        .label { font-weight: bold; width: 180px; background: #f5f5f5; }
        .verdict { font-weight: bold; text-transform: uppercase; }
        .footer { margin-top: 40px; display: flex; justify-content: space-between; }
        .signature { width: 30%; text-align: center; border-top: 1px solid #333; padding-top: 4px; }
    </style>
</head>
<body>
    <h1>University Thesis Defense Report</h1>
    <h2>{{ $schedule->thesis->department->name }} — {{ $schedule->thesis->program->name }}</h2>

    <table>
        <tr><td class="label">Thesis Title</td><td>{{ $schedule->thesis->title }}</td></tr>
        <tr><td class="label">Student</td><td>{{ $schedule->thesis->student->user->full_name }} ({{ $schedule->thesis->student->student_number }})</td></tr>
        <tr><td class="label">Supervisor</td><td>{{ $schedule->thesis->supervisor?->user->full_name ?? 'N/A' }}</td></tr>
        <tr><td class="label">Defense Date</td><td>{{ $schedule->scheduled_at->format('F j, Y g:i A') }}</td></tr>
        <tr><td class="label">Room</td><td>{{ $schedule->room->name }} ({{ $schedule->room->building }})</td></tr>
    </table>

    <table>
        <tr><th>Jury Role</th><th>Name</th><th>Specialization</th></tr>
        @foreach ($schedule->juryMembers as $jury)
            <tr>
                <td>{{ ucfirst($jury->pivot->role) }}</td>
                <td>{{ $jury->user->full_name }}</td>
                <td>{{ $jury->specialization }}</td>
            </tr>
        @endforeach
    </table>

    @if ($schedule->result)
        <table>
            <tr><td class="label">Final Grade</td><td>{{ $schedule->result->final_grade ?? 'N/A' }} / 20</td></tr>
            <tr><td class="label">Verdict</td><td class="verdict">{{ str_replace('_', ' ', $schedule->result->verdict) }}</td></tr>
            <tr><td class="label">Remarks</td><td>{{ $schedule->result->remarks ?? '—' }}</td></tr>
        </table>
    @endif

    <div class="footer">
        <div class="signature">President</div>
        <div class="signature">Examiner</div>
        <div class="signature">Reporter</div>
    </div>
</body>
</html>