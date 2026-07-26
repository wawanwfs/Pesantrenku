#!/usr/bin/env sh
set -eu

WORKFLOW_DIR="${1:-.github/workflows}"
if [ ! -d "$WORKFLOW_DIR" ]; then
    echo "Workflow directory not found: $WORKFLOW_DIR" >&2
    exit 1
fi

check_file() {
    awk -v file="$1" '
        /^[[:space:]]*(-[[:space:]]+)?uses:[[:space:]]+/ {
            action=$0;
            sub(/^[[:space:]]*(-[[:space:]]+)?uses:[[:space:]]+/, "", action);
            sub(/[[:space:]]+#.*$/, "", action);
            reference=action;
            sub(/^.*@/, "", reference);
            if (reference !~ /^[0-9a-f]{40}$/) {
                printf "%s:%d: mutable GitHub Action reference: %s\n", file, NR, action > "/dev/stderr";
                failed=1;
            }
        }
        END { exit failed ? 1 : 0 }
    ' "$1"
}

status=0
while IFS= read -r workflow; do
    check_file "$workflow" || status=1
done <<EOF_WORKFLOWS
$(find "$WORKFLOW_DIR" -type f \( -name '*.yml' -o -name '*.yaml' \) -print | sort)
EOF_WORKFLOWS

if [ "$status" -ne 0 ]; then
    exit 1
fi

echo "All GitHub Actions are pinned to immutable 40-character commit SHAs."
