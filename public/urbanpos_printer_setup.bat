@echo off
title UrbanPOS TSC TE244 1-Click Print Setup
color 0A
echo ===============================================================
echo     UrbanPOS TSC TE244 1-Click Print Utility Setup
echo ===============================================================
echo.
echo Setting up automatic background print service for TSC TE244...
echo.

set TARGET_DIR=%LOCALAPPDATA%\UrbanPOS
if not exist "%TARGET_DIR%" mkdir "%TARGET_DIR%"

set PS_SCRIPT=%TARGET_DIR%\urbanpos_spooler.ps1
set STARTUP_VBS=%APPDATA%\Microsoft\Windows\Start Menu\Programs\Startup\urbanpos_spooler.vbs

REM Write the PowerShell Spooler Script
(
echo # UrbanPOS Native Windows PRN Spooler for TSC TE244
echo $code = @'
echo using System;
echo using System.IO;
echo using System.Runtime.InteropServices;
echo public class RawPrinter {
echo     [StructLayout(LayoutKind.Sequential, CharSet = CharSet.Ansi^)]
echo     public class DOCINFOA {
echo         [MarshalAs(UnmanagedType.LPStr^)] public string pDocName;
echo         [MarshalAs(UnmanagedType.LPStr^)] public string pOutputFile;
echo         [MarshalAs(UnmanagedType.LPStr^)] public string pDataType;
echo     }
echo     [DllImport("winspool.Drv", EntryPoint = "OpenPrinterA", SetLastError = true, CharSet = CharSet.Ansi, ExactSpelling = true, CallingConvention = CallingConvention.StdCall^)]
echo     public static extern bool OpenPrinter([MarshalAs(UnmanagedType.LPStr^)] string szPrinter, out IntPtr hPrinter, IntPtr pd^);
echo     [DllImport("winspool.Drv", EntryPoint = "ClosePrinter", SetLastError = true, ExactSpelling = true, CallingConvention = CallingConvention.StdCall^)]
echo     public static extern bool ClosePrinter(IntPtr hPrinter^);
echo     [DllImport("winspool.Drv", EntryPoint = "StartDocPrinterA", SetLastError = true, CharSet = CharSet.Ansi, ExactSpelling = true, CallingConvention = CallingConvention.StdCall^)]
echo     public static extern bool StartDocPrinter(IntPtr hPrinter, int level, [In] DOCINFOA di^);
echo     [DllImport("winspool.Drv", EntryPoint = "EndDocPrinter", SetLastError = true, ExactSpelling = true, CallingConvention = CallingConvention.StdCall^)]
echo     public static extern bool EndDocPrinter(IntPtr hPrinter^);
echo     [DllImport("winspool.Drv", EntryPoint = "StartPagePrinter", SetLastError = true, ExactSpelling = true, CallingConvention = CallingConvention.StdCall^)]
echo     public static extern bool StartPagePrinter(IntPtr hPrinter^);
echo     [DllImport("winspool.Drv", EntryPoint = "EndPagePrinter", SetLastError = true, ExactSpelling = true, CallingConvention = CallingConvention.StdCall^)]
echo     public static extern bool EndPagePrinter(IntPtr hPrinter^);
echo     [DllImport("winspool.Drv", EntryPoint = "WritePrinter", SetLastError = true, ExactSpelling = true, CallingConvention = CallingConvention.StdCall^)]
echo     public static extern bool WritePrinter(IntPtr hPrinter, IntPtr pBytes, int dwCount, out int dwWritten^);
echo     public static bool SendBytes(string printerName, byte[] bytes^) {
echo         IntPtr hPrinter;
echo         if (!OpenPrinter(printerName, out hPrinter, IntPtr.Zero^)^) return false;
echo         try {
echo             DOCINFOA di = new DOCINFOA { pDocName = "UrbanPOS Direct Print", pDataType = "RAW" };
echo             if (!StartDocPrinter(hPrinter, 1, di^)^) return false;
echo             try {
echo                 if (!StartPagePrinter(hPrinter^)^) return false;
echo                 try {
echo                     IntPtr pUnmanagedBytes = Marshal.AllocCoTaskMem(bytes.Length^);
echo                     try {
echo                         Marshal.Copy(bytes, 0, pUnmanagedBytes, bytes.Length^);
echo                         int written;
echo                         return WritePrinter(hPrinter, pUnmanagedBytes, bytes.Length, out written^);
echo                     } finally { Marshal.FreeCoTaskMem(pUnmanagedBytes^); }
echo                 } finally { EndPagePrinter(hPrinter^); }
echo             } finally { EndDocPrinter(hPrinter^); }
echo         } finally { ClosePrinter(hPrinter^); }
echo     }
echo }
echo '@
echo Add-Type -TypeDefinition $code -ErrorAction SilentlyContinue
echo $printerName = "TSC TE244"
echo $downloads = [System.IO.Path]::Combine($env:USERPROFILE, "Downloads"^)
echo while ($true^) {
echo     try {
echo         $files = Get-ChildItem -Path $downloads -Filter "*.prn" -File -ErrorAction SilentlyContinue
echo         foreach ($file in $files^) {
echo             try {
echo                 Start-Sleep -Milliseconds 120
echo                 $bytes = [System.IO.File]::ReadAllBytes($file.FullName^)
echo                 if ($bytes.Length -gt 0^) {
echo                     $text = [System.Text.Encoding]::ASCII.GetString($bytes^)
echo                     if ($text.Contains("PRINT"^) -or $text.Contains("SIZE"^)^) {
echo                         [RawPrinter]::SendBytes($printerName, $bytes^)
echo                         Remove-Item -Path $file.FullName -Force -ErrorAction SilentlyContinue
echo                     }
echo                 }
echo             } catch {}
echo         }
echo     } catch {}
echo     Start-Sleep -Milliseconds 350
echo }
) > "%PS_SCRIPT%"

REM Create Silent Startup VBS
(
echo Set WshShell = CreateObject("WScript.Shell"^)
echo WshShell.Run "powershell.exe -ExecutionPolicy Bypass -WindowStyle Hidden -File """ ^& "%PS_SCRIPT%" ^& """", 0, False
) > "%STARTUP_VBS%"

REM Kill any existing powershell spooler instance
taskkill /F /FI "WINDOWTITLE eq UrbanPOS_Spooler*" >nul 2>&1

REM Launch Spooler right now
wscript.exe "%STARTUP_VBS%"

echo.
echo ===============================================================
echo  SUCCESS! UrbanPOS TSC TE244 Print Utility is now ACTIVE!
echo  From now on, clicking "Print Labels" on UrbanPOS will
echo  instantly print directly to your TSC TE244 printer.
echo ===============================================================
echo.
mshta "javascript:alert('UrbanPOS TSC TE244 Printer Utility Setup Successful!\n\nYou can now print 1-click labels directly from pos.indianpetcompany.com');close();"
exit /b 0
