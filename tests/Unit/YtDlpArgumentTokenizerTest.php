<?php

use App\Services\Download\InvalidYtDlpArgumentsException;
use App\Services\Download\YtDlpArgumentTokenizer;

describe('YtDlpArgumentTokenizer', function () {
    it('splits arguments on spaces and tabs, one or more per line', function () {
        expect(YtDlpArgumentTokenizer::tokenize("--limit-rate 1M\n--sleep-interval\t5   --max-sleep-interval 30"))
            ->toBe(['--limit-rate', '1M', '--sleep-interval', '5', '--max-sleep-interval', '30']);
    });

    it('keeps a double-quoted value with spaces as one argument', function () {
        expect(YtDlpArgumentTokenizer::tokenize('--add-headers "Referer: https://example.com"'))
            ->toBe(['--add-headers', 'Referer: https://example.com']);
    });

    it('keeps single-quoted text literally', function () {
        expect(YtDlpArgumentTokenizer::tokenize(<<<'TXT'
            --match-filters 'title ~= "a b" & !is_live'
            --user-agent 'back\slash $HOME'
            TXT))
            ->toBe(['--match-filters', 'title ~= "a b" & !is_live', '--user-agent', 'back\slash $HOME']);
    });

    it('applies backslash escapes the way a POSIX shell does', function () {
        // Inside double quotes only \" \\ \$ and \` are escapes; other
        // backslashes stay. Outside quotes a backslash escapes any character.
        expect(YtDlpArgumentTokenizer::tokenize(<<<'TXT'
            --add-headers "X-Quote: say \"hi\" \\ \d"
            --output-na-placeholder a\ b\#c
            TXT))
            ->toBe(['--add-headers', 'X-Quote: say "hi" \ \d', '--output-na-placeholder', 'a b#c']);
    });

    it('joins quoted text with adjacent text and keeps empty arguments', function () {
        expect(YtDlpArgumentTokenizer::tokenize('--impersonate="" --proxy "" -f "best"[height<=720]'))
            ->toBe(['--impersonate=', '--proxy', '', '-f', 'best[height<=720]']);
    });

    it('ignores blank lines, comment lines and trailing comments', function () {
        $text = "# Network\r\n\r\n  # indented comment\n--impersonate firefox # browser TLS\n\n--limit-rate 1M\r\n";

        expect(YtDlpArgumentTokenizer::tokenize($text))
            ->toBe(['--impersonate', 'firefox', '--limit-rate', '1M']);
    });

    it('only treats # as a comment at the start of an argument', function () {
        expect(YtDlpArgumentTokenizer::tokenize('--referer https://example.com/#top --add-headers "X-Tag: #1"'))
            ->toBe(['--referer', 'https://example.com/#top', '--add-headers', 'X-Tag: #1']);
    });

    it('returns nothing for empty or comment-only text', function (string $text) {
        expect(YtDlpArgumentTokenizer::tokenize($text))->toBe([]);
    })->with([
        'empty' => [''],
        'whitespace' => ["  \n\t\n"],
        'comments' => ["# one\n   # two"],
    ]);

    it('rejects text it cannot split unambiguously', function (string $text, string $message) {
        expect(fn () => YtDlpArgumentTokenizer::tokenize($text))
            ->toThrow(InvalidYtDlpArgumentsException::class, $message);
    })->with([
        'unclosed double quote' => ["--limit-rate 1M\n--add-headers \"Referer: x", 'Line 2: missing closing double quote'],
        'unclosed single quote' => ["--match-filters 'title", 'Line 1: missing closing single quote'],
        'quote spanning lines' => ["--add-headers \"a\nb\"", 'Line 1: missing closing double quote'],
        'trailing backslash' => ['--proxy socks5://host\\', 'Line 1: nothing follows the backslash'],
        'NUL byte' => ["--proxy\0x", 'Line 1: contains a NUL character'],
        'invalid UTF-8' => ["--user-agent \xff\xfe", 'not valid UTF-8'],
    ]);
});
