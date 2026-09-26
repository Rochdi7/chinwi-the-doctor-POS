# Honeywell USB scanner setup (Genesis 7580g)

How to get a Honeywell Genesis 7580g barcode scanner working with the POS on a
new Windows PC. Run every command in **PowerShell**, with the scanner plugged in.

The POS needs the scanner in **USB keyboard mode**: it "types" the barcode like
a keyboard. If the scanner is in **USB serial (COM port) mode**, it beeps when
you scan but nothing reaches the POS. That was the problem on the first PC.

For the full installer guide (status indicator, other brands, troubleshooting),
see [deploy/SCANNER-USB.md](deploy/SCANNER-USB.md).

---

## Shortcut: one script does everything

From the project's `app` folder:

```powershell
powershell -ExecutionPolicy Bypass -File .\deploy\scanner-mode-clavier.ps1
```

It finds the scanner, detects the mode, switches it to keyboard mode if needed
and verifies the result. Add `-WhatIf` to only show the current state.

If you prefer to do it by hand, follow the steps below.

---

## 1. Check that Windows sees the scanner

```powershell
Get-PnpDevice -PresentOnly | Where-Object { $_.InstanceId -like '*VID_0C2E*' } |
  Select-Object Status, Class, FriendlyName, InstanceId | Format-Table -AutoSize -Wrap
```

`VID_0C2E` is Honeywell's USB vendor ID. Read the result like this:

| What you see | Meaning | Next step |
|:--|:--|:--|
| No lines | Windows does not see the scanner | Check the cable, try another USB port |
| A line with class `Keyboard` | Already in keyboard mode | Nothing to fix, go to step 6 |
| A line with class `Ports`, e.g. "USB Serial Device (COM9)" | Serial mode, the POS receives nothing | Continue to step 2 |

## 2. Find the COM port number

```powershell
[System.IO.Ports.SerialPort]::GetPortNames()
```

It was `COM9` on the first PC, but it can be different on another PC. Replace
`COM9` in the next commands with the number you get.

## 3. Optional: ask the scanner which mode it is in

```powershell
$p = New-Object System.IO.Ports.SerialPort 'COM9',115200,'None',8,'One'
$p.DtrEnable = $true; $p.Open()
$cmd = [byte[]](0x16,0x4D,0x0D) + [Text.Encoding]::ASCII.GetBytes('TRMUSB?.')
$p.Write($cmd, 0, $cmd.Length); Start-Sleep -Milliseconds 700
$p.ReadExisting(); $p.Close()
```

- `TRMUSB130` means USB serial mode, which is the wrong mode.
- Replace `TRMUSB?.` with `REVINF.` to see the model, firmware and serial number.

## 4. Switch the scanner to USB keyboard mode (the fix)

```powershell
$p = New-Object System.IO.Ports.SerialPort 'COM9',115200,'None',8,'One'
$p.DtrEnable = $true; $p.Open()
$cmd = [byte[]](0x16,0x4D,0x0D) + [Text.Encoding]::ASCII.GetBytes('PAP124.')
$p.Write($cmd, 0, $cmd.Length); Start-Sleep -Milliseconds 1000
try { $p.Close() } catch {}
```

- `PAP124.` is the Honeywell command for "USB PC Keyboard".
- The final dot saves it permanently, so it survives unplugging.
- The scanner beeps and restarts, and the COM port disappears.
- An error while closing the port is normal: the port vanished during the restart.

## 5. Verify

Wait about 5 seconds, then run the step 1 command again. You should see these
lines with status `OK`:

- `Keyboard`: HID Keyboard Device
- `BarcodeScanner`: POS HID Barcode scanner

There must be no `Ports` line anymore.

## 6. Test for real

1. Open **Notepad** and scan a barcode. The digits should appear.
2. Open the POS at `/admin/pos` and wait about 3 seconds. The scanner indicator
   at the top of the cart should turn **green**.
3. Scan a product. It should be added to the cart.

---

## Notes

- **Strange characters in Notepad** like `&é"'(` instead of digits mean the
  French AZERTY keyboard layout is active. The POS converts them back to digits,
  so sales still work.
- **No driver to install.** In keyboard mode, Windows recognizes the scanner
  on its own.
- **Other scanner brands:** steps 3 and 4 only work on Honeywell scanners. For
  other brands, scan the "USB Keyboard" barcode from the scanner's manual, and
  set the brand's USB ID in `.env` (see
  [deploy/SCANNER-USB.md](deploy/SCANNER-USB.md), section 5).
- **Printed barcode fallback:** the Honeywell 7580g user guide has a
  "USB Keyboard (PC)" barcode in its *Terminal Interfaces* chapter. Scanning it
  does the same as step 4.
