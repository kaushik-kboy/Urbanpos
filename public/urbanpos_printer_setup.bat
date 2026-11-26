@echo off
title UrbanPOS TSC TE244 1-Click Print Setup
color 0A
setlocal enabledelayedexpansion

echo ===============================================================
echo     UrbanPOS TSC TE244 1-Click Print Utility Setup
echo ===============================================================
echo.

set "TARGET_DIR=%LOCALAPPDATA%\UrbanPOS"
if not exist "%TARGET_DIR%" mkdir "%TARGET_DIR%"

set "PS_SCRIPT=%TARGET_DIR%\urbanpos_spooler.ps1"
set "STARTUP_VBS=%APPDATA%\Microsoft\Windows\Start Menu\Programs\Startup\urbanpos_spooler.vbs"

echo [1/3] Downloading print spooler service...
powershell -NoProfile -ExecutionPolicy Bypass -Command "[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12; $wc = New-Object System.Net.WebClient; $wc.Headers.Add('User-Agent', 'UrbanPOS-Setup'); try { $wc.DownloadFile('https://pos.indianpetcompany.com/printer-spooler', '%PS_SCRIPT%'); Write-Host '>> Spooler service downloaded successfully!' -ForegroundColor Green } catch { Write-Host '>> Could not download fresh script, checking local cache...' -ForegroundColor Yellow }"

if not exist "%PS_SCRIPT%" (
    echo [ERROR] Could not download spooler service. Please ensure you are connected to internet.
    pause
    exit /b 1
)

echo.
echo [2/3] Detecting printers on this computer...
powershell -NoProfile -ExecutionPolicy Bypass -Command "$printers = Get-WmiObject Win32_Printer; foreach ($p in $printers) { Write-Host ('  - ' + $p.Name + ' [' + $p.PortName + '] [Offline: ' + $p.WorkOffline + ']') }; $target = $printers | Where-Object { $_.Name -match 'TSC|TE244|TE 244|TTP|Thermal|Label|BarCode' } | Select-Object -First 1; if ($target) { Write-Host ''; Write-Host ('>> Detected Thermal Printer: ' + $target.Name) -ForegroundColor Green; if ($target.WorkOffline) { Write-Host '>> Setting printer to ONLINE mode...' -ForegroundColor Yellow; $wmi = Get-WmiObject Win32_Printer -Filter ('Name=''' + $target.Name + ''''); $wmi.WorkOffline = $false; $wmi.Put() | Out-Null }; [System.IO.File]::WriteAllText('%TARGET_DIR%\printer_name.txt', $target.Name) } else { Write-Host '>> Using default TSC TE244' -ForegroundColor Yellow; [System.IO.File]::WriteAllText('%TARGET_DIR%\printer_name.txt', 'TSC TE244') }"

echo.
echo [3/3] Starting background auto-print service...

:: Stop any old running instance
powershell -NoProfile -ExecutionPolicy Bypass -Command "Get-CimInstance Win32_Process -ErrorAction SilentlyContinue | Where-Object { $_.CommandLine -match 'urbanpos_spooler\.ps1' } | ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }"

:: Create startup VBS launcher
(
echo Set WshShell = CreateObject("WScript.Shell"^)
echo WshShell.Run "powershell.exe -NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File ""%PS_SCRIPT%""", 0, False
) > "%STARTUP_VBS%"

:: Start the background spooler
wscript.exe "%STARTUP_VBS%"

:: Wait 1.5 seconds and confirm running state
powershell -NoProfile -ExecutionPolicy Bypass -Command "Start-Sleep -Milliseconds 1500; $proc = Get-CimInstance Win32_Process -ErrorAction SilentlyContinue | Where-Object { $_.CommandLine -match 'urbanpos_spooler\.ps1' }; if ($proc) { Write-Host ''; Write-Host ('>> SUCCESS: Auto-Print Service is ACTIVE and RUNNING! (PID: ' + $proc.ProcessId + ')') -ForegroundColor Green; Write-Host ('>> Watching folder: ' + [System.IO.Path]::Combine($env:USERPROFILE, 'Downloads')) -ForegroundColor Cyan } else { Write-Host '>> Service registered in Windows Startup.' -ForegroundColor Yellow }"

echo.
echo ===============================================================
echo  SETUP COMPLETE!
echo.
echo  Ab aap https://pos.indianpetcompany.com par jakar
echo  "Print Labels" dabayein - print sidha machine se niklega!
echo ===============================================================
echo.
pause
exit /b 0
