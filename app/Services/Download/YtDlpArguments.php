<?php

namespace App\Services\Download;

use App\Models\Site;
use App\Services\Sites\SiteCookieCopy;
use App\Services\Sites\SiteResolver;
use App\Settings\MaintenanceSettings;
use Closure;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * The arguments every yt-dlp run gets besides its own fixed options: the
 * cookies of the site the URL belongs to (Settings → Sites), and the
 * user-supplied extra arguments: global, then the site's, then the
 * download's own one-off arguments.
 *
 * User-supplied arguments are checked with yt-dlp's own option parser (see
 * resources/python/inspect_ytdlp_args.py), so what gets judged is what yt-dlp
 * will actually do with them, however they are spelled. They are refused when
 * they contain a positional argument (yt-dlp would download it as another
 * URL), define an alias, change any of DENIED_DESTINATIONS or a destination
 * that is missing from the reviewed snapshot (an option added by a newer
 * yt-dlp) - and whenever they can't be checked at all.
 */
class YtDlpArguments
{
    /**
     * yt-dlp option destinations that user-supplied arguments may not change,
     * with the reason shown to the user. A destination covers every option
     * string that sets it: aliases, abbreviations, short forms.
     *
     * @var array<string, string>
     */
    public const DENIED_DESTINATIONS = [
        // Running commands or other programs
        'exec_cmd' => 'runs commands', // --exec
        'exec_before_dl_cmd' => 'runs commands', // --exec-before-download
        'netrc_cmd' => 'runs commands', // --netrc-cmd
        'external_downloader' => 'runs an external program', // --downloader, --external-downloader
        'external_downloader_args' => 'passes arguments to external programs', // --downloader-args
        'postprocessor_args' => 'passes arguments to ffmpeg and other post-processors', // --postprocessor-args, --ppa
        'ffmpeg_location' => 'runs ffmpeg from a custom location', // --ffmpeg-location
        'js_runtimes' => 'chooses the JavaScript runtime yt-dlp runs, possibly from a custom location', // --js-runtimes, --no-js-runtimes
        // Loading code, plugins or configuration
        'plugin_dirs' => 'changes where yt-dlp loads plugin code from', // --plugin-dirs, --no-plugin-dirs
        'add_postprocessors' => 'loads plugin post-processors', // --use-postprocessor
        'remote_components' => 'downloads and runs remote code', // --remote-components
        'config_locations' => 'loads options from a config file', // --config-locations
        'compat_opts' => 'can turn off yt-dlp safety checks', // --compat-options
        'update_self' => 'controls yt-dlp self-updates (use Settings → Maintenance → yt-dlp update instead)', // -U, --update, --update-to, --no-update
        // Reading local files
        'batchfile' => 'reads a local file', // -a, --batch-file
        'load_info_filename' => 'reads a local file', // --load-info-json
        'netrc_location' => 'reads a local file', // --netrc-location
        'client_certificate' => 'reads a local file', // --client-certificate
        'client_certificate_key' => 'reads a local file', // --client-certificate-key
        'load_pages' => 'reads local files', // --load-pages
        'enable_file_urls' => 'lets yt-dlp read local files through file:// URLs', // --enable-file-urls
        'cookiefile' => 'reads and writes a local cookie file (add the site\'s cookies in Settings → Sites instead)', // --cookies
        'cookiesfrombrowser' => 'reads browser profiles from disk (add the site\'s cookies in Settings → Sites instead)', // --cookies-from-browser
        // Writing files or changing where they are saved
        'outtmpl' => 'changes where files are saved', // -o, --output
        'paths' => 'changes where files are saved', // -P, --paths
        'useid' => 'changes where files are saved', // --id
        'split_chapters' => 'changes where files are saved', // --split-chapters (chapter files ignore -o)
        'print_to_file' => 'writes to a local file', // --print-to-file
        'download_archive' => 'writes to a local file', // --download-archive
        'write_pages' => 'writes files into the working directory', // --write-pages
        'cachedir' => 'changes or disables the cache directory', // --cache-dir, --no-cache-dir
        // yt-dlp keeps each format's "cookies" field in the info JSON.
        'embed_infojson' => 'embeds download details, including the site\'s cookies, in the saved file', // --embed-info-json, --no-embed-info-json
        'rm_cachedir' => 'deletes cached files', // --rm-cache-dir
        // Other
        'parse_metadata' => 'can rewrite internal fields such as the download URL', // --parse-metadata, --replace-in-metadata
        'metafromtitle' => 'can rewrite internal fields such as the download URL', // --metadata-from-title
        'debug_printtraffic' => 'prints raw HTTP traffic, including cookies', // --print-traffic
    ];

    private const HELPER_TIMEOUT_SECONDS = 30;

    /** @var Closure(list<string>): Process */
    private Closure $processFactory;

    /**
     * $processFactory builds the helper's process; tests pass a fake.
     *
     * @param  (Closure(list<string>): Process)|null  $processFactory
     */
    public function __construct(?Closure $processFactory = null)
    {
        $this->processFactory = $processFactory ?? static fn (array $command): Process => new Process($command);
    }

    /**
     * A private copy, for one yt-dlp run in $directory, of the cookies of the
     * site $url belongs to (see SiteCookieCopy). Null when no site covers the
     * URL or it has no cookies: there is no fallback.
     */
    public function copyCookies(string $url, string $directory): ?SiteCookieCopy
    {
        $site = $this->siteFor($url);

        return $site === null ? null : SiteCookieCopy::create($site, $directory);
    }

    /**
     * The user-supplied arguments for one yt-dlp run on $url, checked right
     * before use. When they can't be parsed, aren't allowed or can't be
     * checked, the download fails with the reason rather than quietly
     * running without arguments someone asked for.
     *
     * Put them after all of the command's own options, immediately before
     * "--" and the URL: that lets them override the app's defaults (e.g. -f)
     * while no option the app adds can be affected by them.
     *
     * @return list<string>
     *
     * @throws InvalidYtDlpArgumentsException
     */
    public function extraArguments(string $url, ?DownloadOptions $options = null): array
    {
        $args = self::merge($this->layers($url, $options ?? DownloadOptions::none()));
        $this->validate($args);

        return $args;
    }

    /**
     * Parse and check extra-arguments text, e.g. before saving it.
     *
     * @return list<string>
     *
     * @throws InvalidYtDlpArgumentsException
     */
    public function validateText(string $text): array
    {
        $args = YtDlpArgumentTokenizer::tokenize($text);
        $this->validate($args);

        return $args;
    }

    /**
     * @param  list<string>  $args
     *
     * @throws InvalidYtDlpArgumentsException
     */
    public function validate(array $args): void
    {
        if ($args === []) {
            return;
        }

        $report = $this->inspect($args);
        $violations = [];

        foreach ($report['positional'] as $value) {
            $violations[] = sprintf(
                '"%s" is not an option or an option\'s value, so yt-dlp would read it as another URL to download',
                self::excerpt($value)
            );
        }

        if ($report['aliases'] !== []) {
            $violations[] = '--alias is not allowed: it can redefine the options this app passes to yt-dlp';
        }

        foreach ($report['changed'] as $dest) {
            if (isset(self::DENIED_DESTINATIONS[$dest])) {
                $violations[] = sprintf(
                    '%s is not allowed: it %s',
                    self::describeOption($dest, $report['used']),
                    self::DENIED_DESTINATIONS[$dest]
                );
            } elseif (! isset(self::knownDestinations()[$dest])) {
                $violations[] = sprintf(
                    '%s is not allowed yet: it is new in this version of yt-dlp and has not been reviewed',
                    self::describeOption($dest, $report['used'])
                );
            }
        }

        if ($violations !== []) {
            throw InvalidYtDlpArgumentsException::notAllowed($violations);
        }
    }

    /**
     * Fold argument layers, in order, into one argument list.
     *
     * @param  list<YtDlpArgumentLayer>  $layers
     * @return list<string>
     *
     * @throws InvalidYtDlpArgumentsException when a layer's text can't be parsed
     */
    public static function merge(array $layers): array
    {
        $args = [];

        foreach ($layers as $layer) {
            try {
                $layerArgs = YtDlpArgumentTokenizer::tokenize($layer->text);
            } catch (InvalidYtDlpArgumentsException $e) {
                throw InvalidYtDlpArgumentsException::inSource($layer->source, $e);
            }

            $args = $layer->overrides ? $layerArgs : [...$args, ...$layerArgs];
        }

        return $args;
    }

    /**
     * The user-supplied argument layers for $url, in the order they apply:
     * the global Settings field, the matching site's arguments, then the
     * download's one-off arguments, each combining with or overriding the
     * layers before it. The merged list is validated as a whole, the way
     * yt-dlp will read it.
     *
     * @return list<YtDlpArgumentLayer>
     */
    protected function layers(string $url, DownloadOptions $options): array
    {
        $layers = [YtDlpArgumentLayer::combine('Settings → yt-dlp Extra Arguments', $this->globalSettingsText())];

        $site = $this->siteFor($url);
        if ($site !== null) {
            $source = sprintf('Site "%s"', $site->name);
            $layers[] = $site->combine_args
                ? YtDlpArgumentLayer::combine($source, (string) $site->extra_args)
                : YtDlpArgumentLayer::override($source, (string) $site->extra_args);
        }

        $source = 'This download\'s extra arguments';
        $layers[] = $options->combineArgs
            ? YtDlpArgumentLayer::combine($source, $options->extraArgs)
            : YtDlpArgumentLayer::override($source, $options->extraArgs);

        return $layers;
    }

    private function siteFor(string $url): ?Site
    {
        return app(SiteResolver::class)->forUrl($url);
    }

    /**
     * Every option destination of the yt-dlp version that was reviewed
     * (resources/python/ytdlp_known_destinations.json, generated with the
     * helper's --list-destinations). Unreadable means none, so everything
     * not reviewed is refused.
     *
     * @return array<string, true>
     */
    private static function knownDestinations(): array
    {
        static $known = null;

        if ($known === null) {
            $snapshot = json_decode((string) @file_get_contents(resource_path('python/ytdlp_known_destinations.json')), true);
            $destinations = is_array($snapshot['destinations'] ?? null) ? $snapshot['destinations'] : [];
            $known = array_fill_keys(array_filter($destinations, 'is_string'), true);
        }

        return $known;
    }

    private function globalSettingsText(): string
    {
        try {
            return app(MaintenanceSettings::class)->ytdlp_extra_args ?? '';
        } catch (\Exception) {
            // Settings unavailable (e.g. not migrated yet): no extra arguments.
            return '';
        }
    }

    /**
     * Ask yt-dlp's option parser, through the helper script, what $args do.
     *
     * @param  non-empty-list<string>  $args
     * @return array{changed: list<string>, positional: list<string>, aliases: list<string>, used: list<array<mixed>>}
     *
     * @throws InvalidYtDlpArgumentsException
     */
    private function inspect(array $args): array
    {
        // An argument array, so no shell is involved; -I keeps Python from
        // importing modules from the script's directory or honouring PYTHON*
        // environment variables.
        $command = ['python3', '-I', resource_path('python/inspect_ytdlp_args.py'), '--', ...$args];

        try {
            $process = ($this->processFactory)($command);
            $process->setTimeout(self::HELPER_TIMEOUT_SECONDS);
            $process->run();
        } catch (Throwable $e) {
            throw InvalidYtDlpArgumentsException::uncheckable($e->getMessage());
        }

        $report = json_decode(trim($process->getOutput()), true);

        if (! is_array($report)) {
            throw InvalidYtDlpArgumentsException::uncheckable(self::describeFailure($process));
        }

        if (($report['ok'] ?? null) !== true) {
            $error = is_string($report['error'] ?? null) ? $report['error'] : self::describeFailure($process);

            throw ($report['error_type'] ?? null) === 'invalid_arguments'
                ? InvalidYtDlpArgumentsException::rejectedByYtDlp($error)
                : InvalidYtDlpArgumentsException::uncheckable($error);
        }

        if (! $process->isSuccessful()) {
            throw InvalidYtDlpArgumentsException::uncheckable(self::describeFailure($process));
        }

        foreach (['changed', 'positional', 'aliases'] as $key) {
            if (! self::isListOfStrings($report[$key] ?? null)) {
                throw InvalidYtDlpArgumentsException::uncheckable("the helper returned a malformed report ({$key})");
            }
        }

        return [
            'changed' => $report['changed'],
            'positional' => $report['positional'],
            'aliases' => $report['aliases'],
            'used' => is_array($report['used'] ?? null) ? array_values(array_filter($report['used'], 'is_array')) : [],
        ];
    }

    /**
     * Name the argument(s) that changed $dest as the user wrote them, adding
     * the option yt-dlp read them as when the spelling differs.
     *
     * @param  list<array<mixed>>  $used
     */
    private static function describeOption(string $dest, array $used): string
    {
        $labels = [];

        foreach ($used as $entry) {
            $option = $entry['option'] ?? null;

            if (($entry['dest'] ?? null) !== $dest || ! is_string($option)) {
                continue;
            }

            $token = is_string($entry['token'] ?? null) ? $entry['token'] : $option;
            $labels[] = $token === $option || str_starts_with($token, $option.'=')
                ? $option
                : sprintf('%s (read by yt-dlp as %s)', self::excerpt($token), $option);
        }

        return $labels === [] ? sprintf('The "%s" option', $dest) : implode(' / ', array_unique($labels));
    }

    private static function describeFailure(Process $process): string
    {
        $stderr = trim($process->getErrorOutput());

        return sprintf(
            'the helper exited with code %s (%s)%s',
            $process->getExitCode() ?? '?',
            $process->getExitCodeText() ?? 'unknown error',
            $stderr === '' ? '' : ': '.Str::limit(Str::afterLast($stderr, "\n"), 300)
        );
    }

    private static function excerpt(string $value): string
    {
        return Str::limit($value, 60);
    }

    private static function isListOfStrings(mixed $value): bool
    {
        return is_array($value) && array_is_list($value) && array_filter($value, 'is_string') === $value;
    }
}
