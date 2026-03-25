# Verificar si es administrador
$isAdmin = ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole] "Administrator")

if (-not $isAdmin) {
    Write-Host "Este script requiere permisos de Administrador. Reiniciando..." -ForegroundColor Red
    Start-Process PowerShell -ArgumentList "-File `"$PSCommandPath`"" -Verb RunAs
    exit
}

# Cambiar permisos de ejecución
Set-ExecutionPolicy -ExecutionPolicy Bypass -Scope Process -Force | Out-Null

# Detectar rutas automáticamente
$backendPath = Split-Path -Parent $PSCommandPath
$phpPath = (Get-Command php -ErrorAction SilentlyContinue)?.Source
if (-not $phpPath) {
    $phpPath = Read-Host "No se encontro 'php' en el PATH. Ingresa la ruta completa (ej: C:\xampp\php\php.exe)"
}
Write-Host "PHP: $phpPath" -ForegroundColor Gray
Write-Host "Backend: $backendPath" -ForegroundColor Gray

# Actualizar la tarea
try {
    Write-Host "Eliminando tarea anterior..." -ForegroundColor Yellow
    Unregister-ScheduledTask -TaskName "Gestix Backup Scheduler" -Confirm:$false -ErrorAction SilentlyContinue | Out-Null

    Write-Host "Creando nueva tarea..." -ForegroundColor Yellow
    $action = New-ScheduledTaskAction -Execute $phpPath -Argument 'artisan schedule:run' -WorkingDirectory $backendPath
    $trigger = New-ScheduledTaskTrigger -Once -At (Get-Date) -RepetitionInterval (New-TimeSpan -Minutes 1) -RepetitionDuration (New-TimeSpan -Days 365)
    Register-ScheduledTask -TaskName "Gestix Backup Scheduler" -Action $action -Trigger $trigger -Force | Out-Null

    Write-Host "Verificando tarea..." -ForegroundColor Yellow
    $task = Get-ScheduledTask -TaskName "Gestix Backup Scheduler"
    $task.Actions | Format-List Execute, Arguments, WorkingDirectory

    Write-Host "`nTarea actualizada correctamente!" -ForegroundColor Green
    Write-Host "La tarea ejecutara 'artisan schedule:run' cada minuto" -ForegroundColor Green
} catch {
    Write-Host "Error: $_" -ForegroundColor Red
}

Write-Host "`nPresiona cualquier tecla para cerrar..." -ForegroundColor Cyan
$null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
