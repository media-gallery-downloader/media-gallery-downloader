<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

/**
 * Probes this container for available hardware video encoders, so you can tell
 * what `media:reencode --accel=…` can actually use here — without assuming any
 * GPU vendor. Reports the render devices present (/dev/dri), the hardware
 * encoders ffmpeg was built with, and (if the tools exist) VAAPI capabilities
 * via vainfo and NVIDIA presence via nvidia-smi.
 */
class Encoders extends Command
{
    protected $signature = 'media:encoders';

    protected $description = 'Probe available hardware video encoders (VAAPI / NVENC / QSV) in this container';

    public function handle(): int
    {
        $this->line('<info>Render devices (/dev/dri)</info>');
        $dri = glob('/dev/dri/*') ?: [];
        if ($dri === []) {
            $this->line('  none — no GPU passed in; software encode only (--accel=none)');
        } else {
            foreach ($dri as $node) {
                $this->line('  '.$node);
            }
        }

        $this->newLine();
        $this->line('<info>ffmpeg hardware encoders</info>');
        $encoders = $this->ffmpegHwEncoders();
        if ($encoders === null) {
            $this->line('  ffmpeg not available');
        } elseif ($encoders === []) {
            $this->line('  none compiled in');
        } else {
            foreach ($encoders as $name => $desc) {
                $this->line(sprintf('  %-13s %s', $name, $desc));
            }
        }

        $this->newLine();
        $this->line('<info>VAAPI capabilities (vainfo)</info>');
        [$ok, $out] = $this->probe(['vainfo']);
        $encLines = $ok ? array_filter(preg_split('/\R/', $out) ?: [], fn ($l) => str_contains($l, 'Enc')) : [];
        if (! $ok) {
            $this->line('  vainfo unavailable, or no working VAAPI driver/device here');
        } elseif ($encLines === []) {
            $this->line('  VAAPI present but reports no encode entrypoints');
        } else {
            foreach ($encLines as $l) {
                $this->line('  '.trim($l));
            }
        }

        $this->newLine();
        $this->line('<info>NVIDIA (nvidia-smi)</info>');
        [$ok, $out] = $this->probe(['nvidia-smi', '--query-gpu=name,driver_version', '--format=csv,noheader']);
        if (! $ok) {
            $this->line('  no NVIDIA runtime — NVENC needs the NVIDIA Container Toolkit + a GPU reservation');
        } else {
            foreach (array_filter(preg_split('/\R/', trim($out)) ?: []) as $l) {
                $this->line('  '.trim($l));
            }
        }

        $this->newLine();
        $this->line('<comment>media:reencode --accel=vaapi</comment> needs a render device above AND a *_vaapi encoder.');
        $this->line('<comment>media:reencode --accel=nvenc</comment> needs nvidia-smi above AND a *_nvenc encoder.');

        return self::SUCCESS;
    }

    /**
     * Hardware (non-software) encoders ffmpeg exposes, name => description.
     *
     * @return array<string, string>|null null if ffmpeg is unavailable
     */
    private function ffmpegHwEncoders(): ?array
    {
        [$ok, $out] = $this->probe(['ffmpeg', '-hide_banner', '-encoders']);
        if (! $ok) {
            return null;
        }

        $found = [];
        foreach (preg_split('/\R/', $out) ?: [] as $line) {
            // e.g. " V....D h264_vaapi   H.264/AVC (VAAPI) (codec h264)"
            if (preg_match('/^\s*V\S*\s+(\S*(?:vaapi|nvenc|qsv|_amf|videotoolbox))\s+(.*)$/', $line, $m)) {
                $found[$m[1]] = trim($m[2]);
            }
        }
        ksort($found);

        return $found;
    }

    /**
     * Run a probe command. Never throws (uses run(), not mustRun()) so a missing
     * binary is reported, not fatal.
     *
     * @param  list<string>  $cmd
     * @return array{0: bool, 1: string}
     */
    private function probe(array $cmd): array
    {
        try {
            $p = new Process($cmd);
            $p->setTimeout(10);
            $p->run();

            return [$p->isSuccessful(), $p->getOutput()];
        } catch (\Throwable) {
            return [false, ''];
        }
    }
}
