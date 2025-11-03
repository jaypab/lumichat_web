<!doctype html>
<html>
  <body style="font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;color:#0f172a">
    <h2 style="margin:0 0 .25rem">New Appointment Assigned</h2>
    <p style="margin:.4rem 0 1rem">Hello,</p>
    <p style="margin:.4rem 0">A new appointment has been assigned to you.</p>
    <ul style="margin:.6rem 0 1rem;padding-left:1rem">
      <li><strong>Student:</strong> {{ $studentName }}</li>
      <li><strong>When:</strong> {{ $whenNice }}</li>
      <li><strong>Ref #:</strong> #{{ $appointmentId }}</li>
    </ul>
    <p style="margin-top:1rem;color:#475569">Please prepare accordingly.</p>
  </body>
</html>
