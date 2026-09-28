# Bludit GitHub Backup

Bludit-3.x-Plugin für versionierte Backups von `bl-content`, `bl-plugins` und `bl-themes` in ein GitHub-Repository über die GitHub REST API.

[🇩🇪 Deutsch](README.de.md) · [🇬🇧 English](README.en.md)

## Kurzüberblick / Short overview

### 🇩🇪 Deutsch

**GitHub Backup** sichert ausgewählte Bludit-Dateien versioniert in einem GitHub-Repository.

Unterstützt werden:

- manuelle Backups aus dem Bludit-Administrationsbereich
- automatische Backups über normale Besucher-/Admin-Aufrufe – auch ohne lokalen Cron
- optionaler GitHub-Actions-Scheduler
- geschützter HTTP-Scheduler für externe Dienste
- konfigurierbare Backup-Intervalle
- Entfernung lokal gelöschter Dateien aus dem Backup
- Ausschluss von Cache-, Session-, Temp- und Log-Dateien

### 🇬🇧 English

**GitHub Backup** stores selected Bludit files as versioned backups in a GitHub repository.

Supported features include:

- manual backups from the Bludit administration area
- automatic backups through normal visitor/admin requests – without local cron
- optional GitHub Actions scheduler
- protected HTTP scheduler for external services
- configurable backup intervals
- removal of locally deleted files from the backup
- exclusion of cache, session, temp and log files

## Kompatibilität / Compatibility

| Information | Details |
|---|---|
| Bludit | **3.22+** |
| PHP | PHP version supported by the installed Bludit version |
| Required PHP extensions | **cURL**, **Sodium** |
| Git / SSH | Not required |
| Composer | Not required |
| License | **MIT** |
| Current version | **0.2.2** |

Sodium is required for automatic management of GitHub Actions repository secrets. Normal GitHub API backups require cURL.

## Installation

### 🇩🇪 Deutsch

1. Repository herunterladen oder klonen.
2. Den Ordner `ghbackup` nach `bl-plugins/` kopieren.
3. In Bludit unter **Plugins** das Plugin **GitHub Backup** aktivieren.
4. Ein GitHub-Repository für die Backups erstellen.
5. Einen Fine-grained Personal Access Token mit den erforderlichen Berechtigungen erstellen.
6. GitHub Owner, Repository und Branch im Plugin konfigurieren.
7. **GitHub-Verbindung testen**.
8. Ein erstes **Backup now** durchführen.
9. Optional einen automatischen Scheduler aktivieren.

Die vollständige Einrichtung einschließlich Token-Berechtigungen und GitHub Actions ist in der [deutschen Installations- und Konfigurationsanleitung](README.de.md) beschrieben.

### 🇬🇧 English

1. Download or clone this repository.
2. Copy the `ghbackup` directory to `bl-plugins/`.
3. In Bludit, open **Plugins** and activate **GitHub Backup**.
4. Create a GitHub repository for the backups.
5. Create a Fine-grained Personal Access Token with the required permissions.
6. Configure the GitHub owner, repository and branch in the plugin.
7. Use **Test GitHub connection**.
8. Run the first **Backup now**.
9. Optionally enable an automatic scheduler.

The complete setup, token permissions and GitHub Actions configuration are documented in the [English installation and configuration guide](README.en.md).

## GitHub permissions

For normal backups, the Fine-grained Personal Access Token needs:

- **Contents → Read and write**

For automatic GitHub Actions workflow and repository-secret management, additionally:

- **Workflows → Read and write**
- **Secrets → Read and write**

The token should be restricted to the dedicated backup repository whenever possible.

## Backup targets

The plugin can back up:

```
bl-content/
bl-plugins/
bl-themes/
```

By default, the backup uses the following prefix:

```
bludit-backup/
├── bl-content/
├── bl-plugins/
└── bl-themes/
```

The prefix and backup branch can be configured in the plugin.

## Scheduler options

The plugin provides several ways to trigger scheduled backups:

1. **Visitor/Admin scheduler** – runs when Bludit receives a request and a backup is due.
2. **GitHub Actions scheduler** – GitHub Actions calls the protected HTTP endpoint according to the configured schedule.
3. **HTTP scheduler** – external monitoring or scheduling services can call the protected endpoint.

Without any incoming request or external trigger, PHP cannot execute a scheduled backup on its own. For low-traffic websites, GitHub Actions or another external HTTP scheduler can therefore be used.

## Security

- Use HTTPS for the HTTP scheduler.
- Never publish scheduler or GitHub tokens.
- Prefer the `X-GHBackup-Token` HTTP header over a query-string token.
- Restrict the GitHub token to the backup repository.
- Rotate exposed tokens immediately.
- The GitHub Actions scheduler token is stored as the repository secret `GHBACKUP_SCHEDULER_TOKEN` and is not written into the workflow source.

## Documentation

- 🇩🇪 **[Deutsche Dokumentation](README.de.md)** – Installation, Token-Erstellung, GitHub Actions, Scheduler, HTTP-Trigger, Sicherheit und Fehlerbehebung.
- 🇬🇧 **[English documentation](README.en.md)** – installation, token setup, GitHub Actions, scheduling, HTTP trigger, security and troubleshooting.

## Repository structure

```
ghbackup/
├── plugin.php
├── metadata.json
└── languages/
    ├── de.json
    └── en.json

.github/
└── workflows/
    └── validate.yml
```

## License

MIT – see the repository for the license terms.

## Author / Repository

**TimUx**

Repository: https://github.com/TimUx/bludit-plugin-ghbackup
