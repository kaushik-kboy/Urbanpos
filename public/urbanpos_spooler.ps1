# UrbanPOS TSC TE244 Native Windows Spooler
# Auto-detects TSC / Thermal printer and prints downloaded .prn files instantly

$code = @'
using System;
using System.IO;
using System.Runtime.InteropServices;

public class RawPrinter {
    [StructLayout(LayoutKind.Sequential, CharSet = CharSet.Ansi)]
    public class DOCINFOA {
        [MarshalAs(UnmanagedType.LPStr)] public string pDocName;
        [MarshalAs(UnmanagedType.LPStr)] public string pOutputFile;
        [MarshalAs(UnmanagedType.LPStr)] public string pDataType;
    }

    [DllImport("winspool.Drv", EntryPoint = "OpenPrinterA", SetLastError = true, CharSet = CharSet.Ansi, ExactSpelling = true, CallingConvention = CallingConvention.StdCall)]
    public static extern bool OpenPrinter([MarshalAs(UnmanagedType.LPStr)] string szPrinter, out IntPtr hPrinter, IntPtr pd);

    [DllImport("winspool.Drv", EntryPoint = "ClosePrinter", SetLastError = true, ExactSpelling = true, CallingConvention = CallingConvention.StdCall)]
    public static extern bool ClosePrinter(IntPtr hPrinter);

    [DllImport("winspool.Drv", EntryPoint = "StartDocPrinterA", SetLastError = true, CharSet = CharSet.Ansi, ExactSpelling = true, CallingConvention = CallingConvention.StdCall)]
    public static extern bool StartDocPrinter(IntPtr hPrinter, int level, [In] DOCINFOA di);

    [DllImport("winspool.Drv", EntryPoint = "EndDocPrinter", SetLastError = true, ExactSpelling = true, CallingConvention = CallingConvention.StdCall)]
    public static extern bool EndDocPrinter(IntPtr hPrinter);

    [DllImport("winspool.Drv", EntryPoint = "StartPagePrinter", SetLastError = true, ExactSpelling = true, CallingConvention = CallingConvention.StdCall)]
    public static extern bool StartPagePrinter(IntPtr hPrinter);

    [DllImport("winspool.Drv", EntryPoint = "EndPagePrinter", SetLastError = true, ExactSpelling = true, CallingConvention = CallingConvention.StdCall)]
    public static extern bool EndPagePrinter(IntPtr hPrinter);

    [DllImport("winspool.Drv", EntryPoint = "WritePrinter", SetLastError = true, ExactSpelling = true, CallingConvention = CallingConvention.StdCall)]
    public static extern bool WritePrinter(IntPtr hPrinter, IntPtr pBytes, int dwCount, out int dwWritten);

    public static bool SendBytes(string printerName, byte[] bytes) {
        IntPtr hPrinter;
        if (!OpenPrinter(printerName, out hPrinter, IntPtr.Zero)) return false;
        try {
            DOCINFOA di = new DOCINFOA();
            di.pDocName = "UrbanPOS Direct Print";
            di.pDataType = "RAW";
            if (!StartDocPrinter(hPrinter, 1, di)) return false;
            try {
                if (!StartPagePrinter(hPrinter)) return false;
                try {
                    IntPtr pUnmanagedBytes = Marshal.AllocCoTaskMem(bytes.Length);
                    try {
                        Marshal.Copy(bytes, 0, pUnmanagedBytes, bytes.Length);
                        int written;
                        return WritePrinter(hPrinter, pUnmanagedBytes, bytes.Length, out written);
                    } finally { Marshal.FreeCoTaskMem(pUnmanagedBytes); }
                } finally { EndPagePrinter(hPrinter); }
            } finally { EndDocPrinter(hPrinter); }
        } finally { ClosePrinter(hPrinter); }
    }
}
'@

try {
    Add-Type -TypeDefinition $code -ErrorAction Stop
} catch {
    # Type might already be loaded in session
}

$targetDir = [System.IO.Path]::Combine($env:LOCALAPPDATA, "UrbanPOS")
if (-not (Test-Path $targetDir)) {
    New-Item -ItemType Directory -Path $targetDir -Force | Out-Null
}

$cfgFile = [System.IO.Path]::Combine($targetDir, "printer_name.txt")
$logFile = [System.IO.Path]::Combine($targetDir, "spooler.log")

function Write-SpoolLog($msg) {
    try {
        $timestamp = (Get-Date).ToString("yyyy-MM-dd HH:mm:ss")
        $entry = "$timestamp - $msg`r`n"
        [System.IO.File]::AppendAllText($logFile, $entry)
    } catch {}
}

function Get-TargetPrinter {
    if (Test-Path $cfgFile) {
        $saved = (Get-Content -Path $cfgFile -Raw -ErrorAction SilentlyContinue)
        if ($saved) {
            $trimmed = $saved.Trim()
            if ($trimmed) { return $trimmed }
        }
    }

    $printers = Get-WmiObject Win32_Printer
    $detected = $printers | Where-Object { $_.Name -match "TSC|TE244|TE 244|TTP|Thermal|Label|BarCode" -and -not $_.WorkOffline } | Select-Object -First 1
    if (-not $detected) {
        $detected = $printers | Where-Object { $_.Name -match "TSC|TE244|TE 244|TTP|Thermal|Label|BarCode" } | Select-Object -First 1
    }
    if ($detected) {
        return $detected.Name
    }

    return "TSC TE244"
}

$regDownloads = [Microsoft.Win32.Registry]::GetValue("HKEY_CURRENT_USER\Software\Microsoft\Windows\CurrentVersion\Explorer\User Shell Folders", "{374DE290-123F-4565-9164-39C4925E467B}", $null)
if ($regDownloads) {
    $regDownloads = [System.Environment]::ExpandEnvironmentVariables($regDownloads)
}
$watchFolders = @(
    [System.IO.Path]::Combine($env:USERPROFILE, "Downloads"),
    [System.IO.Path]::Combine($env:USERPROFILE, "OneDrive\Downloads")
)
if ($regDownloads -and (Test-Path $regDownloads) -and ($watchFolders -notcontains $regDownloads)) {
    $watchFolders += $regDownloads
}

Write-SpoolLog "UrbanPOS Spooler started. Watching: $($watchFolders -join ', ')"
$activePrinter = Get-TargetPrinter
Write-SpoolLog "Initial target printer: $activePrinter"

while ($true) {
    try {
        foreach ($folder in $watchFolders) {
            if (Test-Path $folder) {
                $files = Get-ChildItem -Path $folder -Filter "*.prn" -File -ErrorAction SilentlyContinue
                foreach ($file in $files) {
                    try {
                        Start-Sleep -Milliseconds 250
                        $retries = 4
                        $bytes = $null
                        while ($retries -gt 0 -and ($bytes -eq $null -or $bytes.Length -eq 0)) {
                            try {
                                $bytes = [System.IO.File]::ReadAllBytes($file.FullName)
                            } catch {
                                Start-Sleep -Milliseconds 150
                                $retries--
                            }
                        }

                        if ($bytes -and $bytes.Length -gt 0) {
                            $text = [System.Text.Encoding]::ASCII.GetString($bytes)
                            if ($text.Contains("PRINT") -or $text.Contains("SIZE")) {
                                $printer = Get-TargetPrinter
                                
                                # Ensure printer is online
                                $wmi = Get-WmiObject Win32_Printer -Filter "Name='$printer'" -ErrorAction SilentlyContinue
                                if ($wmi -and $wmi.WorkOffline) {
                                    $wmi.WorkOffline = $false
                                    $wmi.Put() | Out-Null
                                }

                                $res = [RawPrinter]::SendBytes($printer, $bytes)
                                Write-SpoolLog "Print job for '$($file.Name)' to '$printer': Success=$res"

                                if ($res) {
                                    Remove-Item -Path $file.FullName -Force -ErrorAction SilentlyContinue
                                } else {
                                    $errCode = [System.Runtime.InteropServices.Marshal]::GetLastWin32Error()
                                    Write-SpoolLog "SendBytes failed for '$($file.Name)' (Win32Error=$errCode). Moving to .failed"
                                    Move-Item -Path $file.FullName -Destination ($file.FullName + ".failed") -Force -ErrorAction SilentlyContinue
                                }
                            }
                        }
                    } catch {
                        Write-SpoolLog "Error processing $($file.FullName): $_"
                    }
                }
            }
        }
    } catch {
        Write-SpoolLog "Loop error: $_"
    }

    Start-Sleep -Milliseconds 450
}
