<!doctype html>
<html>
  <body style="font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;color:#0f172a">
    <h2 style="margin:0 0 .25rem">Appointment Approved</h2>
    <p style="margin:.4rem 0 1rem">Hi {{ $studentName }},</p>
    <p style="margin:.4rem 0">Your appointment has been approved.</p>
    <ul style="margin:.6rem 0 1rem;padding-left:1rem">
      <li><strong>Counselor:</strong> {{ $counselorName }}</li>
      <li><strong>When:</strong> {{ $whenNice }}</li>
      <li><strong>Ref #:</strong> #{{ $appointmentId }}</li>
    </ul>
    <p style="margin-top:1rem;color:#475569">If you didn’t request this, reply to this email.</p>
  </body>
</html>
