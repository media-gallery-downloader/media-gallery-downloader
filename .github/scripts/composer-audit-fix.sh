#!/usr/bin/env bash
# Prepares a composer.lock update for the packages `composer audit` reports.
#
# Usage: .github/scripts/composer-audit-fix.sh <output-dir>
#
# Run it from the directory that holds composer.json and composer.lock. It
# updates the reported packages, and whatever they require, in composer.lock
# only: nothing is installed and no package code runs. It then writes to
# <output-dir>:
#   title.txt   pull request title (a Conventional Commit subject)
#   body.md     pull request description: advisories and version changes
#   report.md   what happened, for the job summary (written in every case)
# and, when $GITHUB_OUTPUT is set, the outputs `changed` (composer.lock has
# new versions), `clean` (the audit passes afterwards) and `title`.
#
# The audit workflow (.github/workflows/audit.yml) calls it when the daily
# audit fails. Run it by hand to see what that job would propose; it changes
# composer.lock in place, so `git restore composer.lock` undoes it.

set -euo pipefail

OUT="${1:?Usage: $0 <output-dir>}"
mkdir -p "$OUT"

set_output() {
    if [ -n "${GITHUB_OUTPUT:-}" ]; then
        echo "$1=$2" >> "$GITHUB_OUTPUT"
    fi
}

finish() {
    set_output changed "$1"
    set_output clean "$2"
    cat "$OUT/report.md"
    exit 0
}

# `composer audit` exits non-zero when it finds advisories, so its exit status
# says nothing about whether the command itself worked. The report does.
run_audit() {
    composer audit --locked --format=json --no-interaction --abandoned=report \
        > "$1" 2> "$OUT/audit.err" || true
    if ! jq -e 'has("advisories")' "$1" > /dev/null 2>&1; then
        echo "composer audit did not return a report:" >&2
        cat "$OUT/audit.err" >&2
        exit 2
    fi
}

# One Markdown table row per advisory. Titles and links come from the advisory
# feed, so they are treated as untrusted text: no line breaks, no table
# delimiters, no @mentions, and only plain https links.
advisory_rows() {
    jq -r '
        def cell: tostring | gsub("[\\r\\n]+"; " ") | gsub("\\|"; "\\|") | gsub("@"; "@​");
        .advisories | to_entries[] | .key as $package | .value[]
        | (.cve // .advisoryId // "advisory") as $id
        | ((.title // "") | ltrimstr($id + ": ")) as $title
        | (if ((.link // "") | test("^https://[^\\s()<>|]+$"))
            then "[\($id | cell)](\(.link))"
            else ($id | cell) end) as $advisory
        | "| `\($package)` | \($advisory) | \($title | cell) |"
    ' "$1"
}

advisory_table() {
    echo "| Package | Advisory | Title |"
    echo "| --- | --- | --- |"
    advisory_rows "$1"
}

# "a", "a and b", "a, b and c"
join_names() {
    local last="${*: -1}"
    if [ "$#" -eq 1 ]; then
        echo "$last"
        return
    fi
    local head=("${@:1:$#-1}")
    local joined
    joined=$(printf '%s, ' "${head[@]}")
    echo "${joined%, } and $last"
}

run_audit "$OUT/before.json"
mapfile -t PACKAGES < <(jq -r '.advisories | to_entries[] | .key' "$OUT/before.json")

if [ "${#PACKAGES[@]}" -eq 0 ]; then
    echo "No security advisories are reported for composer.lock." > "$OUT/report.md"
    finish false true
fi

# The names go on a command line and into a pull request title.
for PACKAGE in "${PACKAGES[@]}"; do
    if ! [[ "$PACKAGE" =~ ^[a-z0-9][a-z0-9_.-]*/[a-z0-9][a-z0-9_.-]*$ ]]; then
        echo "Unexpected package name in the audit report: $PACKAGE" >&2
        exit 2
    fi
done

cp composer.lock "$OUT/composer.lock.before"

# --with-all-dependencies lets a fix pull in what it requires (a patched
# symfony/http-kernel needs the matching symfony/http-foundation), and
# --minimal-changes keeps everything else where it is. Composer 2.9 and later
# refuse versions with a known advisory, so the result either passes the audit
# or the command fails and says which package has no safe version; an older
# Composer can leave some advisories in place, which the report then lists.
# Extensions are a property of the machine that installs the lock, not of the
# one resolving it.
UPDATED=true
composer update "${PACKAGES[@]}" \
    --with-all-dependencies --minimal-changes \
    --no-install --no-scripts --no-plugins \
    --no-interaction --no-progress --ignore-platform-req='ext-*' \
    > "$OUT/update.log" 2>&1 || UPDATED=false

if [ "$UPDATED" = "false" ]; then
    cp "$OUT/composer.lock.before" composer.lock
    {
        echo "## Composer could not update the affected packages"
        echo
        echo "At least one of them has no safe version that \`composer.json\` allows, so this needs"
        echo "a change by hand: a wider version constraint, a replacement package, or an ignore"
        echo "entry in Composer's advisory settings. Composer's explanation follows the list."
        echo
        advisory_table "$OUT/before.json"
        echo
        echo '```text'
        grep -v '^[[:space:]]*$' "$OUT/update.log" | head -n 40
        echo '```'
    } > "$OUT/report.md"
    finish false false
fi

# Version changes between the two lock files, as "name<TAB>from<TAB>to".
jq -rn --slurpfile before "$OUT/composer.lock.before" --slurpfile after composer.lock '
    def versions: (.packages + (.["packages-dev"] // []))
        | map({key: .name, value: (.version | ltrimstr("v"))}) | from_entries;
    ($before[0] | versions) as $old | ($after[0] | versions) as $new
    | (($old | keys) + ($new | keys) | unique)[]
    | select($old[.] != $new[.])
    | [., ($old[.] // "not installed"), ($new[.] // "removed")] | @tsv
' > "$OUT/changes.tsv"

if [ ! -s "$OUT/changes.tsv" ]; then
    # Composer may still have rewritten metadata in the lock file.
    cp "$OUT/composer.lock.before" composer.lock
    {
        echo "## No newer version is available for the affected packages"
        echo
        echo "\`composer.json\` allows nothing newer than what is locked, so these need a change by hand:"
        echo "a wider version constraint, a replacement package, or an ignore entry in Composer's"
        echo "advisory settings."
        echo
        advisory_table "$OUT/before.json"
    } > "$OUT/report.md"
    finish false false
fi

run_audit "$OUT/after.json"
REMAINING=$(jq '[.advisories | to_entries[] | .value[]] | length' "$OUT/after.json")
CLEAN=false
if [ "$REMAINING" -eq 0 ]; then
    CLEAN=true
fi

# Name the packages the advisories are about, not the ones that came along.
mapfile -t FIXED < <(
    comm -12 <(printf '%s\n' "${PACKAGES[@]}" | sort) <(cut -f1 "$OUT/changes.tsv" | sort)
)
if [ "${#FIXED[@]}" -eq 0 ]; then
    TITLE="fix(deps): update dependencies for security advisories"
else
    TITLE="fix(deps): update $(join_names "${FIXED[@]}") for security advisories"
    if [ "${#TITLE}" -gt 100 ]; then
        TITLE="fix(deps): update ${#FIXED[@]} packages for security advisories"
    fi
fi
echo "$TITLE" > "$OUT/title.txt"

{
    echo "## Advisories"
    echo
    advisory_table "$OUT/before.json"
    echo
    echo "## Updates"
    echo
    echo "| Package | From | To |"
    echo "| --- | --- | --- |"
    awk -F'\t' '{ printf "| `%s` | %s | %s |\n", $1, $2, $3 }' "$OUT/changes.tsv"
    if [ "$CLEAN" = "false" ]; then
        echo
        echo "## Still reported after this update"
        echo
        advisory_table "$OUT/after.json"
        echo
        echo "These need a change by hand: a wider version constraint in \`composer.json\`, a"
        echo "replacement package, or an ignore entry in Composer's advisory settings. The audit"
        echo "check keeps failing until then."
    fi
} > "$OUT/details.md"

{
    echo "\`composer audit\` reports security advisories against the lock file on the default"
    echo "branch. This updates the affected packages, and whatever they require, in"
    echo "\`composer.lock\`. Nothing else changes."
    echo
    cat "$OUT/details.md"
    echo
    echo "---"
    echo
    if [ -n "${GITHUB_RUN_ID:-}" ]; then
        RUN_URL="${GITHUB_SERVER_URL}/${GITHUB_REPOSITORY}/actions/runs/${GITHUB_RUN_ID}"
        echo "Opened by the Composer Audit workflow ([run](${RUN_URL})). It is updated in place"
    else
        echo "Opened by the Composer Audit workflow. It is updated in place"
    fi
    echo "when the audit result changes."
} > "$OUT/body.md"

{
    echo "## Proposed update: ${TITLE}"
    echo
    cat "$OUT/details.md"
} > "$OUT/report.md"

set_output title "$TITLE"
finish true "$CLEAN"
