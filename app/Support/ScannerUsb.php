<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Is the USB barcode scanner plugged in and working?
 *
 * A Honeywell Genesis 7580g in its default mode is a keyboard: it types the
 * code and says nothing about itself, so the till has no way to tell "no
 * scanner" from "scanner that has not been used yet". Windows does know, and
 * answers through PnP (the same list Device Manager shows), which is what
 * this asks, through PowerShell.
 *
 * PowerShell takes ~2 s to start, and `php artisan serve` handles one request
 * at a time: asking inside a request would freeze the till, scans included,
 * for those 2 s. So the probe is launched detached and writes its answer to a
 * file; requests only ever read that file.
 *
 * It never throws and never blocks a sale. A host that cannot answer (Linux,
 * popen disabled, PowerShell missing) reports "unknown", because the scanner
 * types into the page either way: this is a convenience, not a condition.
 *
 * It describes the machine PHP runs on. That is the till when the shop runs
 * the app locally; on a remote server it says nothing about the cashier's PC.
 */
class ScannerUsb
{
    /** Honeywell's USB vendor id; the 7580g and its siblings all carry it. */
    private const VENDOR = 'VID_0C2E';

    /** How often Windows is asked again: unplugging shows up within this. */
    private const TTL = 10;

    /** An answer older than this means the probe stopped answering. */
    private const STALE = 60;

    /** One probe at a time, even if it hangs. */
    private const LOCK = 8;

    /**
     * What the last probe found. Reads a small file, nothing else, so it is
     * safe to call while rendering.
     *
     * connected: plugged in, keyboard mode, ready
     * serie:     plugged in but in serial (COM port) mode: it will beep and
     *            the till will receive nothing
     * erreur:    plugged in but Windows reports a driver problem
     * absent:    not plugged in
     * unknown:   not asked yet, or this host cannot be asked
     *
     * @return array{state: string, name: ?string}
     */
    public static function status(): array
    {
        $unknown = ['state' => 'unknown', 'name' => null];
        $file = static::file();

        if (! is_file($file) || time() - (int) @filemtime($file) > self::STALE) {
            return $unknown;
        }

        // A read that lands in the middle of a write is not valid JSON; the
        // next poll, under a second later, gets the whole answer.
        $found = json_decode((string) @file_get_contents($file), true);

        if (! is_array($found) || ! array_key_exists('present', $found)) {
            return $unknown;
        }

        if (! $found['present']) {
            return ['state' => 'absent', 'name' => null];
        }

        $name = trim((string) ($found['name'] ?? ''));

        return [
            'state' => match (true) {
                ! ($found['ok'] ?? false) => 'erreur',
                (bool) ($found['serie'] ?? false) => 'serie',
                default => 'connected',
            },
            'name' => $name === '' ? null : mb_substr($name, 0, 60),
        ];
    }

    /**
     * Ask Windows again if the last answer is getting old. Returns at once:
     * the answer arrives in the file a couple of seconds later.
     */
    public static function refresh(): void
    {
        // Tests describe the scanner by writing the answer file themselves.
        if (PHP_OS_FAMILY !== 'Windows' || ! function_exists('popen') || app()->runningUnitTests()) {
            return;
        }

        $file = static::file();

        clearstatcache(true, $file);

        if (is_file($file) && time() - (int) @filemtime($file) < self::TTL) {
            return;
        }

        if (! Cache::add('scanner-usb.probing', true, self::LOCK)) {
            return;
        }

        if (! is_dir(dirname($file))) {
            @mkdir(dirname($file), 0755, true);
        }

        // As -EncodedCommand: escapeshellarg() strips double quotes on
        // Windows, which would break every string in the script.
        $command = 'start /B "" powershell.exe -NoProfile -NonInteractive -WindowStyle Hidden'
            .' -ExecutionPolicy Bypass -EncodedCommand '
            .base64_encode(mb_convert_encoding(static::script($file), 'UTF-16LE', 'UTF-8'))
            .' >NUL 2>&1';

        $handle = @popen($command, 'r');

        if (is_resource($handle)) {
            pclose($handle);
        }
    }

    /**
     * -PresentOnly is the whole point: what is plugged in now, not every
     * scanner this machine has ever seen. One scanner shows up as several
     * nodes (USB, HID, keyboard or COM port), hence the counting.
     */
    private static function script(string $file): string
    {
        $path = str_replace("'", "''", $file);

        return '$ProgressPreference="SilentlyContinue";'
            .' $ds = @(Get-PnpDevice -PresentOnly -ErrorAction SilentlyContinue | Where-Object { $_.InstanceId -like "*'.static::vendor().'*" });'
            .' if ($ds.Count -gt 0) {'
            .'   $com = @($ds | Where-Object { $_.Class -eq "Ports" });'
            .'   $j = @{'
            .'     present = $true;'
            .'     ok = (@($ds | Where-Object { $_.Status -ne "OK" }).Count -eq 0);'
            .'     serie = ($com.Count -gt 0);'
            .'     name = [string]$(if ($com.Count -gt 0) { $com[0].FriendlyName } else { "" })'
            .'   } | ConvertTo-Json -Compress'
            .' } else { $j = \'{"present":false}\' };'
            // Not Out-File: PowerShell 5 would prepend a BOM and break json_decode.
            ." [IO.File]::WriteAllText('{$path}', \$j)";
    }

    /** Another brand of scanner: set SCANNER_USB_VID (e.g. VID_05E0 for Zebra). */
    private static function vendor(): string
    {
        $vid = strtoupper((string) config('services.scanner_usb.vid', self::VENDOR));

        // It ends up inside a script: accept a vendor id and nothing else.
        return preg_match('/^VID_[0-9A-F]{4}$/', $vid) ? $vid : self::VENDOR;
    }

    private static function file(): string
    {
        return storage_path('app'.DIRECTORY_SEPARATOR.'scanner-usb.json');
    }
}
