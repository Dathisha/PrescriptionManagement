<#
.SYNOPSIS
    Windows WIA (Windows Image Acquisition) scanner interface for the
    Prescription Management Scanner Agent.

.PARAMETER Action
    "list"  — enumerate connected WIA scanner devices (outputs JSON)
    "scan"  — acquire an image from the specified device

.PARAMETER DeviceId
    WIA device ID string. If empty, the first available scanner is used.

.PARAMETER OutputPath
    Full path where the scanned PNG file should be written (required for scan).

.EXAMPLE
    .\scan-wia.ps1 -Action list
    .\scan-wia.ps1 -Action scan -OutputPath "C:\Temp\scan.png"
    .\scan-wia.ps1 -Action scan -DeviceId "{...}" -OutputPath "C:\Temp\scan.png"
#>

param(
    [string]$Action     = "list",
    [string]$DeviceId   = "",
    [string]$OutputPath = ""
)

$ErrorActionPreference = "Stop"

# ── WIA property constants ─────────────────────────────────────────
# WIA_IPS_XRES  = 6147  (horizontal DPI)
# WIA_IPS_YRES  = 6148  (vertical DPI)
# WIA_IPS_XPOS  = 6149  (scan area left offset)
# WIA_IPS_YPOS  = 6150  (scan area top offset)
# WIA_IPS_XEXTENT = 6151 (scan width in pixels)
# WIA_IPS_YEXTENT = 6152 (scan height in pixels)
#
# At 200 DPI: A4 page ≈ 1654×2339 px, US Letter ≈ 1700×2200 px
$ScanDPI    = 200
$ScanWidth  = 1700   # US Letter width at 200 DPI (~8.5 in)
$ScanHeight = 2200   # US Letter height at 200 DPI (~11 in)

# PNG format GUID for WIA Transfer
$PNG_FORMAT = "{B96B3CAE-0728-11D3-9D7B-0000F81EF32E}"

# ── Helper: safely set a WIA property (ignore unsupported props) ───
function Set-WIAProperty {
    param($Item, [int]$PropId, $Value)
    try {
        $prop = $Item.Properties($PropId)
        # Only set if the property exists and is writable
        if ($null -ne $prop) {
            $prop.Value = $Value
        }
    } catch {
        # Silently skip unsupported or read-only properties
    }
}

# ── Helper: find a WIA scanner device ────────────────────────────
function Find-ScannerDevice {
    param($Manager, [string]$TargetDeviceId)

    for ($i = 1; $i -le $Manager.DeviceInfos.Count; $i++) {
        try {
            $info = $Manager.DeviceInfos.Item($i)
            # WIA DeviceType 1 = Scanner (excludes cameras, printers)
            if ($info.Type -eq 1) {
                if (-not $TargetDeviceId -or $info.DeviceID -eq $TargetDeviceId) {
                    return $info.Connect()
                }
            }
        } catch {
            continue
        }
    }
    return $null
}

# ══════════════════════════════════════════════════════════════════
try {
    $manager = New-Object -ComObject WIA.DeviceManager

    # ── ACTION: list ───────────────────────────────────────────────
    if ($Action -eq "list") {
        $scanners = @()

        for ($i = 1; $i -le $manager.DeviceInfos.Count; $i++) {
            try {
                $info = $manager.DeviceInfos.Item($i)
                if ($info.Type -eq 1) {
                    $name = "Scanner"
                    try { $name = $info.Properties("Name").Value } catch {}

                    $scanners += [PSCustomObject]@{
                        id   = $info.DeviceID
                        name = $name
                    }
                }
            } catch {
                continue
            }
        }

        # Ensure output is always a JSON array (even with 0 or 1 items)
        if ($scanners.Count -eq 0) {
            Write-Output "[]"
        } elseif ($scanners.Count -eq 1) {
            $single = $scanners[0] | ConvertTo-Json -Compress
            Write-Output "[$single]"
        } else {
            Write-Output ($scanners | ConvertTo-Json -Compress)
        }

        exit 0
    }

    # ── ACTION: scan ───────────────────────────────────────────────
    if ($Action -eq "scan") {
        if (-not $OutputPath) {
            throw "OutputPath parameter is required for the scan action."
        }

        $device = Find-ScannerDevice -Manager $manager -TargetDeviceId $DeviceId

        if ($null -eq $device) {
            if ($DeviceId) {
                throw "Scanner with ID '$DeviceId' not found. It may have been disconnected."
            } else {
                throw "No scanner found. Please connect a scanner, power it on, and try again."
            }
        }

        # Get the first scanning item (flatbed or feeder)
        $scanItem = $device.Items.Item(1)

        # Configure scan settings (skip unsupported properties silently)
        Set-WIAProperty $scanItem 6147 $ScanDPI    # X resolution
        Set-WIAProperty $scanItem 6148 $ScanDPI    # Y resolution
        Set-WIAProperty $scanItem 6149 0           # X start position
        Set-WIAProperty $scanItem 6150 0           # Y start position
        Set-WIAProperty $scanItem 6151 $ScanWidth  # Width in pixels
        Set-WIAProperty $scanItem 6152 $ScanHeight # Height in pixels

        # Acquire image from scanner (this blocks until scanning is complete)
        $image = $scanItem.Transfer($PNG_FORMAT)

        # Save to the requested output path
        $image.SaveFile($OutputPath)

        exit 0
    }

    throw "Unknown action '$Action'. Valid actions: list, scan"

} catch {
    # Write clean error to stderr so Node.js can extract it
    $Host.UI.WriteErrorLine($_.Exception.Message)
    exit 1
}
