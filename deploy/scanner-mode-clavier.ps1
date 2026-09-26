<#
.SYNOPSIS
  Remet un scanner Honeywell (Genesis 7580g et modèles compatibles) du mode
  port série (COM) au mode clavier USB, sans imprimer ni scanner de code-barres.

.DESCRIPTION
  En mode série le scanner apparaît dans Windows comme « Périphérique série
  USB (COMx) » : il bipe mais la caisse ne reçoit rien. Ce script trouve ce
  port COM, y envoie la commande menu Honeywell « PAP124. » (USB PC Keyboard,
  réglage permanent), puis vérifie que Windows voit désormais un clavier.

  Le scanner se réinitialise tout seul après la commande (bip). Aucun pilote
  à installer.

.EXAMPLE
  powershell -ExecutionPolicy Bypass -File .\deploy\scanner-mode-clavier.ps1

.NOTES
  Vérifié le 26/09/2026 sur un Genesis 7580 réel (firmware 5646).
  Pour une autre marque, adapter -Vid (ex. VID_05E0 pour Zebra).
#>
param(
    [string] $Vid = 'VID_0C2E',
    [switch] $WhatIf
)

$ErrorActionPreference = 'Stop'

function Send-HoneywellMenu([string] $Port, [string] $Command) {
    # Trame menu Honeywell : SYN M CR puis la commande, « . » = permanent.
    $p = New-Object System.IO.Ports.SerialPort $Port, 115200, 'None', 8, 'One'
    $p.ReadTimeout = 1500; $p.WriteTimeout = 1500; $p.DtrEnable = $true
    $p.Open()
    try {
        $p.DiscardInBuffer()
        $bytes = [byte[]](0x16, 0x4D, 0x0D) + [Text.Encoding]::ASCII.GetBytes($Command)
        $p.Write($bytes, 0, $bytes.Length)
        Start-Sleep -Milliseconds 800
        $out = ''
        try {
            while ($p.BytesToRead -gt 0) {
                $b = $p.ReadByte()
                $out += if ($b -eq 6) { '<ACK>' } elseif ($b -eq 5) { '<ENQ>' } elseif ($b -eq 21) { '<NAK>' } elseif ($b -lt 32) { '' } else { [char]$b }
            }
        } catch { <# le port disparaît quand le scanner redémarre : normal #> }
        return $out
    } finally {
        try { $p.Close() } catch {}
    }
}

$devices = @(Get-PnpDevice -PresentOnly -ErrorAction SilentlyContinue | Where-Object { $_.InstanceId -like "*$Vid*" })

if ($devices.Count -eq 0) {
    Write-Host "Aucun scanner $Vid branché. Vérifier le câble USB." -ForegroundColor Red
    exit 1
}

$com = @($devices | Where-Object { $_.Class -eq 'Ports' })

if ($com.Count -eq 0) {
    if (@($devices | Where-Object { $_.Class -eq 'Keyboard' }).Count -gt 0) {
        Write-Host 'Le scanner est déjà en mode clavier USB. Rien à faire.' -ForegroundColor Green
        exit 0
    }
    Write-Host 'Scanner branché mais ni port COM ni clavier détecté :' -ForegroundColor Yellow
    $devices | Select-Object Status, Class, FriendlyName | Format-Table -AutoSize
    exit 2
}

$port = [regex]::Match($com[0].FriendlyName, 'COM\d+').Value
Write-Host "Scanner en mode série sur $port : $($com[0].FriendlyName)"

$answer = Send-HoneywellMenu $port 'TRMUSB?.'
Write-Host "Interface actuelle : $answer"

if ($WhatIf) { Write-Host 'Mode -WhatIf : commande PAP124. non envoyée.'; exit 0 }

Write-Host 'Envoi de PAP124. (USB PC Keyboard, permanent)...'
Send-HoneywellMenu $port 'PAP124.' | Out-Null

Write-Host 'Attente de la réinitialisation du scanner...'
Start-Sleep -Seconds 5

$after = @(Get-PnpDevice -PresentOnly -ErrorAction SilentlyContinue | Where-Object { $_.InstanceId -like "*$Vid*" })
$keyboard = @($after | Where-Object { $_.Class -eq 'Keyboard' })
$stillCom = @($after | Where-Object { $_.Class -eq 'Ports' })

if ($keyboard.Count -gt 0 -and $stillCom.Count -eq 0) {
    Write-Host 'OK : le scanner est en mode clavier USB. Tester dans le Bloc-notes.' -ForegroundColor Green
    exit 0
}

Write-Host 'Le scanner ne s''est pas présenté comme clavier. Débrancher, rebrancher, relancer.' -ForegroundColor Yellow
$after | Select-Object Status, Class, FriendlyName | Format-Table -AutoSize
exit 3
