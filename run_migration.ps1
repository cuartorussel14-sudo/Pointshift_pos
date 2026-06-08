# =====================================================
# RUN THIS IN POWERSHELL TO FIX DATABASE
# =====================================================

# Option 1: Using mysql command line (if mysql is in PATH)
Write-Host "Running SQL migration..." -ForegroundColor Cyan
Get-Content "D:\laragon\www\point-shift_pos-system\fix_inventory_reports_columns.sql" | mysql -u root -p pointshift_pos

# If that doesn't work, use Laragon's mysql:
# Get-Content "D:\laragon\www\point-shift_pos-system\fix_inventory_reports_columns.sql" | & "D:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe" -u root -p pointshift_pos

Write-Host "Database updated successfully!" -ForegroundColor Green
Write-Host "You can now edit products and track stock changes." -ForegroundColor Yellow
