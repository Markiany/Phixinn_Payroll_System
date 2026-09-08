# PHIXINN Payroll System

## Clean payroll flow

Dashboard
→ Employees
→ Attendance
→ Salary Calculation

### Attendance
1. Click **Upload**.
2. Select the NGTeco Office Excel/CSV export.
3. The system validates the file and creates a preview.
4. Review matched/unmatched rows.
5. Click **Confirm Import**.
6. Attendance records are saved.
7. The next step is Salary Calculation.

There is **no Sync from NGTeco** button or direct NGTeco sync flow. Attendance is imported from the NGTeco Office export.

### Salary Calculation
1. Select a weekly period.
2. Generate the payroll run.
3. Open the generated run.
4. Download the Excel payroll or payslips.
5. A downloaded run shows **Already downloaded**.
6. Download remains available and can be clicked again at any time.

Downloading never locks or deletes the payroll run.

## Important existing-data rule

Do not drop or reset the existing `payroll_system` database.

The application adds the new payroll download tracking columns automatically when Salary Calculation is opened:

- `payroll_runs.download_count`
- `payroll_runs.last_downloaded_at`

The attendance importer also ensures the NGTeco metadata columns exist before importing.

## Run locally

```powershell
cd C:\Phixinn_Payroll_System\payroll-system
C:\PayRoll\php.exe -S localhost:8000 -t public
```

Open:

```text
http://localhost:8000
```

## NGTeco import columns

The importer accepts:

- Person ID / Employee ID / Employee Code
- Person Name / Employee Name
- Punch Date / Attendance Date / Date
- Attendance record / Time / Punch Time

Optional NGTeco fields are preserved:

- Verify Type
- TimeZone
- Source
- all other original columns

Employee matching uses:

```text
NGTeco Person ID
      ↓
employees.employee_code
      ↓
employees.id
      ↓
attendance.employee_id
```

The importer supports numeric ID normalization such as `883` ↔ `0883` when the match is unambiguous.

## Validation performed for this rebuild

- PHP syntax checked for all application PHP files.
- Routes checked for the cleaned Attendance import/cancel flow.
- Direct NGTeco sync UI removed.
- Employee edit route/form corrected.
- Existing payroll runs are retained.
- Payroll download is repeatable.
