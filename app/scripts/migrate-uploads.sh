#!/usr/bin/env bash
# Run from the repository root with: docker compose exec -T --user root php bash /var/www/html/scripts/migrate-uploads.sh
set -euo pipefail

legacy_dir=${LEGACY_UPLOAD_DIR:-/var/www/html/uploads}
volume_dir=${UPLOAD_VOLUME_DIR:-/var/www/uploads}

if [[ ! -d "$legacy_dir" ]]; then
    printf 'Legacy uploads directory not found; nothing to migrate: %s\n' "$legacy_dir"
    exit 0
fi

if [[ ! -d "$volume_dir" ]]; then
    printf 'Upload volume directory does not exist: %s\n' "$volume_dir" >&2
    exit 1
fi

moved=0
skipped=0

while IFS= read -r -d '' source_file; do
    file_name=${source_file##*/}
    target_file=$volume_dir/$file_name

    mv -n -- "$source_file" "$target_file"
    if [[ -e "$source_file" ]]; then
        printf 'Skipped existing target: %s\n' "$file_name"
        ((skipped += 1))
        continue
    fi

    chown www-data:www-data "$target_file"
    printf 'Migrated: %s\n' "$file_name"
    ((moved += 1))
done < <(find "$legacy_dir" -mindepth 1 -maxdepth 1 -type f -print0)

printf 'Migration complete: %d moved, %d skipped.\n' "$moved" "$skipped"
