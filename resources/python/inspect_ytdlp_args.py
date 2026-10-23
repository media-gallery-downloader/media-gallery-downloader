#!/usr/bin/env python3
"""Report how yt-dlp's own option parser reads a list of arguments.

Usage:  python3 -I inspect_ytdlp_args.py [--yt-dlp PATH] -- ARG...
        python3 -I inspect_ytdlp_args.py [--yt-dlp PATH] --list-destinations

--list-destinations prints {"ok": true, "version": ..., "destinations": [...]}:
every option destination of that yt-dlp, sorted. It regenerates
ytdlp_known_destinations.json, the snapshot of reviewed destinations.

The option parser is imported from the installed yt-dlp zipapp: the `yt-dlp`
found on PATH (the same one the app runs), or PATH given with --yt-dlp. ARGs
are parsed with no configuration files, and the result is compared with a
parse of no arguments at all. Because yt-dlp does the parsing, every spelling
it accepts is covered: abbreviated long options, short-option clusters
("-xo/x" is -x plus -o /x), attached values, aliases, and so on.

Exactly one JSON object is written to stdout. On success (exit status 0):

  {"ok": true, "version": "<yt-dlp version>",
   "changed": [...],     option destinations whose value now differs from
                         yt-dlp's default, sorted
   "positional": [...],  non-option arguments (yt-dlp would treat them as URLs)
   "aliases": [...],     option strings defined or redefined by --alias
   "used": [...]}        {"token", "option", "dest"} for each option yt-dlp
                         processed that changed a destination: the argument
                         as given, the option it was read as, and its
                         destination

On failure: {"ok": false, "error_type": ..., "error": "..."}, where error_type
is "invalid_arguments" (yt-dlp rejects the arguments; exit status 2),
"unavailable" (yt-dlp can't be imported), "usage" or "internal" (exit
status 1).

This script only reports; the caller decides which destinations are allowed.
"""

import contextlib
import copy
import io
import json
import optparse
import os
import shutil
import sys
import zipfile

EXIT_OK = 0
EXIT_ERROR = 1
EXIT_INVALID_ARGUMENTS = 2


def failure(error_type, message, status=EXIT_ERROR):
    return {'ok': False, 'error_type': error_type, 'error': message}, status


def split_command_line(argv):
    """Return (yt-dlp path or None, whether to list destinations, arguments to inspect)."""
    ytdlp = None
    list_destinations = False
    i = 0
    while i < len(argv):
        if argv[i] == '--':
            return ytdlp, list_destinations, argv[i + 1:]
        if argv[i] == '--yt-dlp' and i + 1 < len(argv):
            ytdlp = argv[i + 1]
            i += 2
            continue
        if argv[i] == '--list-destinations':
            list_destinations = True
            i += 1
            continue
        raise ValueError(f'unexpected argument {argv[i]!r}; put the arguments to inspect after "--"')
    if list_destinations:
        return ytdlp, True, []
    raise ValueError('missing "--" before the arguments to inspect')


def import_create_parser(ytdlp):
    """Import create_parser() from the yt-dlp zipapp; return it and the version."""
    if ytdlp is None:
        ytdlp = shutil.which('yt-dlp')
        if ytdlp is None:
            raise LookupError('yt-dlp was not found on PATH')

    ytdlp = os.path.realpath(ytdlp)
    if not zipfile.is_zipfile(ytdlp):
        raise LookupError(f'{ytdlp} is not a yt-dlp zipapp, so its option parser cannot be imported')

    sys.path.insert(0, ytdlp)
    import yt_dlp
    from yt_dlp.options import create_parser
    from yt_dlp.version import __version__

    origin = os.path.realpath(yt_dlp.__file__ or '')
    if not origin.startswith(ytdlp + os.sep):
        raise LookupError(f'yt_dlp was imported from {origin} instead of {ytdlp}')

    return create_parser, __version__


def option_strings(parser):
    table = dict(parser._short_opt)
    table.update(parser._long_opt)
    return table


def record_options(parser, used):
    """Record each option occurrence: the argument as given, the option string
    optparse matched it to, and the destination."""
    current = {'token': None}

    def remember_token(process_args):
        def wrapper(rargs, values):
            current['token'] = rargs[0] if rargs else None
            return process_args(rargs, values)
        return wrapper

    parser._process_long_opt = remember_token(parser._process_long_opt)
    parser._process_short_opts = remember_token(parser._process_short_opts)

    for option in parser._get_all_options():
        def process(opt, value, values, parser_, _option=option, _process=option.process):
            used.append({'token': current['token'], 'option': opt, 'dest': _option.dest})
            return _process(opt, value, values, parser_)
        option.process = process


def parse(create_parser, args, used=None):
    """Parse args with a fresh parser (--alias changes the parser it runs in).

    Returns (option values, positional arguments, alias option strings)."""
    parser = create_parser()
    before = option_strings(parser)
    if used is not None:
        record_options(parser, used)

    values, positional = parser.parse_args(list(args))

    after = option_strings(parser)
    aliases = sorted(name for name, option in after.items() if before.get(name) is not option)
    return copy.deepcopy(vars(values)), list(positional), aliases


def differs(baseline, values, dest):
    if dest not in baseline or dest not in values:
        return True
    try:
        return bool(baseline[dest] != values[dest])
    except Exception:
        return True


def parse_error_message(error):
    # yt-dlp's message is "<usage>\n\n<prog>: error: <problem>"
    return str(error).strip().rsplit(': error: ', 1)[-1].strip()


def inspect(argv):
    """Return (JSON payload, exit status)."""
    try:
        ytdlp, list_destinations, args = split_command_line(argv)
    except ValueError as error:
        return failure('usage', str(error))

    try:
        create_parser, version = import_create_parser(ytdlp)
    except Exception as error:
        return failure('unavailable', f'cannot load the yt-dlp option parser: {error}')

    if list_destinations:
        try:
            destinations = sorted({option.dest for option in create_parser()._get_all_options() if option.dest})
        except Exception as error:
            return failure('internal', f'{type(error).__name__}: {error}')
        return {'ok': True, 'version': version, 'destinations': destinations}, EXIT_OK

    used = []
    try:
        baseline, _, _ = parse(create_parser, [])
        values, positional, aliases = parse(create_parser, args, used)
    except optparse.OptParseError as error:
        return failure('invalid_arguments', parse_error_message(error), EXIT_INVALID_ARGUMENTS)
    except SystemExit:
        return failure('invalid_arguments', 'they make yt-dlp exit instead of downloading', EXIT_INVALID_ARGUMENTS)
    except Exception as error:
        return failure('internal', f'{type(error).__name__}: {error}')

    changed = sorted(dest for dest in set(baseline) | set(values) if differs(baseline, values, dest))

    return {
        'ok': True,
        'version': version,
        'changed': changed,
        'positional': positional,
        'aliases': aliases,
        'used': [entry for entry in used if entry['dest'] in changed],
    }, EXIT_OK


def main(argv):
    # Nothing yt-dlp prints while being imported or parsing (e.g. --version)
    # may reach stdout, which carries only the JSON result.
    with contextlib.redirect_stdout(io.StringIO()):
        payload, status = inspect(argv)

    sys.stdout.write(json.dumps(payload) + '\n')
    sys.stdout.flush()
    return status


if __name__ == '__main__':
    sys.exit(main(sys.argv[1:]))
