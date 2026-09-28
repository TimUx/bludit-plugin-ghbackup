# Bludit GitHub Backup

A Bludit 3.x plugin that creates versioned backups of selected Bludit data in a GitHub repository using the GitHub REST API.

## Features

- No SSH, Git binary, Composer or shell access required.
- Uses HTTPS directly from PHP to GitHub.
- Creates one Git commit per backup.
- Backs up `bl-content`, `bl-plugins` and `bl-themes`.
- Detects deleted files and removes them from the backup tree.
- Manual backup from the Bludit admin area.
- Optional scheduled backup check on admin requests (useful on shared hosting without cron).
- Fine-grained GitHub Personal Access Token support.
- Configurable repository, branch and backup prefix.
- Exclusion patterns for cache/session/temp data.

## GitHub token

Use a fine-grained Personal Access Token restricted to the target repository with:

- Contents: Read and write

The token is stored in Bludit's plugin settings and is never written into the GitHub backup itself.

## Installation

Copy the `ghbackup` directory into `bl-plugins/`, activate **GitHub Backup** in Bludit and configure the repository.

## Current scope

Version 0.1 focuses on a reliable complete tree backup. Restore functionality and an external cron endpoint are intentionally separate follow-up features.

## License

MIT
