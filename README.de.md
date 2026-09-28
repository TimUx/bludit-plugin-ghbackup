# Bludit GitHub Backup

Bludit-3.x-Plugin für versionierte Backups von `bl-content`, `bl-plugins` und `bl-themes` in ein GitHub-Repository über die GitHub REST API.

## Funktionen

- Kein Git, SSH, Composer oder Shell-Zugriff auf dem Bludit-Server erforderlich.
- Manuelles Backup über den Bludit-Administrationsbereich.
- Optionaler Scheduler über normale Besucher-/Admin-Aufrufe – auch ohne Cron.
- Optionaler **GitHub-Actions-Scheduler**, der vom Plugin verwaltet wird.
- Geschützter HTTP-Scheduler für GitHub Actions, KUMA, UptimeRobot, cron-job.org, curl, wget und andere HTTP-Dienste.
- Konfigurierbare Backup-Intervalle.
- Das Plugin erstellt bzw. aktualisiert bei aktivierten GitHub Actions automatisch `.github/workflows/bludit-ghbackup.yml`.
- Der Scheduler-Token wird als GitHub-Actions-Repository-Secret `GHBACKUP_SCHEDULER_TOKEN` gespeichert und nicht im Workflow hinterlegt.
- Lokal gelöschte Dateien werden auch aus dem Backup entfernt.
- Cache-, Session-, Temp- und Log-Verzeichnisse werden ausgeschlossen.
- Maximale Größe einer einzelnen Datei: 50 MiB.

## Installation

1. Den Ordner `ghbackup` nach `bl-plugins/` kopieren.
2. In Bludit unter **Plugins** das Plugin **GitHub Backup** aktivieren.
3. Ein GitHub-Repository für die Backups anlegen.
4. Einen **Fine-grained Personal Access Token** erstellen und die unten beschriebenen Berechtigungen vergeben.
5. GitHub Owner, Repository und Branch im Plugin eintragen.
6. Die zu sichernden Bludit-Verzeichnisse auswählen.
7. **GitHub-Verbindung testen**.
8. Mit **Backup now** ein erstes manuelles Backup durchführen.
9. Optional einen Scheduler aktivieren.

## GitHub-Token für normale Backups

In GitHub:

**Settings → Developer settings → Personal access tokens → Fine-grained tokens → Generate new token**

Empfohlene Einstellungen:

- **Repository access:** Only selected repositories → Backup-Repository auswählen.
- **Repository permissions:** **Contents → Read and write**.

Dieses Token wird in den Plugin-Einstellungen gespeichert und vom PHP-Code für die GitHub API verwendet.

## GitHub-Actions-Scheduler

Der GitHub-Actions-Scheduler ist optional.

Wenn **GitHub Actions verwenden** aktiviert und die Einstellungen gespeichert werden, erledigt das Plugin automatisch:

1. Einen zufälligen Scheduler-Token erzeugen, falls noch keiner vorhanden ist.
2. Den öffentlichen GitHub-Actions-Schlüssel des Repositorys abrufen.
3. Den Scheduler-Token mit LibSodium verschlüsseln.
4. Das Repository-Secret `GHBACKUP_SCHEDULER_TOKEN` anlegen bzw. aktualisieren.
5. `.github/workflows/bludit-ghbackup.yml` anlegen bzw. aktualisieren.
6. Den eingestellten Zeitplan hinterlegen.
7. `workflow_dispatch` für manuelle Starts aktiviert lassen.

### Zusätzliche Token-Berechtigungen

Für die automatische Verwaltung von Workflow und Secret benötigt der Fine-grained Token:

- **Contents → Read and write**
- **Workflows → Read and write**
- **Secrets → Read and write**

Mit nur **Contents** können normale manuelle Backups funktionieren; die automatische Einrichtung von GitHub Actions und Secrets kann dann jedoch fehlschlagen.

### Intervall ändern

Das gewählte Intervall wird als GitHub-Actions-Cron-Zeitplan in den Workflow geschrieben.

Der Workflow wird immer im **Default-Branch** des Repositorys verwaltet, da geplante GitHub-Actions-Workflows vom Default-Branch ausgeführt werden. Das eigentliche Backup kann trotzdem in den im Plugin konfigurierten Backup-Branch geschrieben werden.

Verfügbare Intervalle:

| Plugin-Einstellung | Zeitplan |
|---|---|
| Jede Stunde | stündlich |
| Alle 6 Stunden | alle 6 Stunden |
| Alle 12 Stunden | alle 12 Stunden |
| Täglich | täglich |
| Wöchentlich | wöchentlich |

GitHub Actions garantiert keine sekundengenaue Ausführung. Bei hoher Auslastung kann ein geplanter Workflow verzögert gestartet werden.

### GitHub Actions deaktivieren

Wenn **GitHub Actions verwenden** deaktiviert und gespeichert wird:

- wird der geplante Trigger aus dem generierten Workflow entfernt;
- `workflow_dispatch` bleibt erhalten;
- die Workflow-Datei wird nicht gelöscht;
- das Repository-Secret bleibt erhalten.

## HTTP-Scheduler

Das Plugin stellt einen geschützten HTTP-Endpunkt bereit:

```
https://example.com/?ghbackup_scheduler=1
```

Empfohlen wird die Authentifizierung über den HTTP-Header:

```
X-GHBackup-Token: <scheduler-token>
```

Beispiel mit curl:

```bash
curl --fail --silent --show-error \
  -H "X-GHBackup-Token: YOUR_SCHEDULER_TOKEN" \
  "https://example.com/?ghbackup_scheduler=1"
```

Der Endpunkt liefert JSON und einen passenden HTTP-Statuscode zurück.

Er kann unter anderem verwendet werden mit:

- KUMA
- UptimeRobot
- cron-job.org
- Zabbix
- Nagios-kompatiblen HTTP-Prüfungen
- NAS-Systemen
- Raspberry Pi
- Windows PowerShell
- curl/wget
- GitHub Actions

Der Scheduler-Token wird vom Plugin erzeugt und ist unabhängig vom GitHub Personal Access Token.

## GitHub-Actions-Workflow

Der generierte Workflow enthält **nicht** den Scheduler-Token. Stattdessen verwendet er das Repository-Secret:

```yaml
env:
  GHBACKUP_TOKEN: ${{ secrets.GHBACKUP_SCHEDULER_TOKEN }}
```

Anschließend wird der Bludit-Endpunkt mit folgendem Header aufgerufen:

```text
X-GHBackup-Token: <secret>
```

Damit bleibt das Geheimnis außerhalb des Workflow-Quellcodes und der Repository-Historie.

## Scheduler ohne GitHub Actions

Auf Shared Hosting ohne Cron kann der normale automatische Scheduler auch über öffentliche Seitenaufrufe ausgeführt werden.

Das bedeutet:

- kein Cron erforderlich;
- ein Besucher kann ein fälliges Backup auslösen;
- auch ein Admin-Aufruf kann ein fälliges Backup auslösen;
- eine exakte Uhrzeit kann ohne externen Trigger nicht garantiert werden.

Bei Websites mit wenig oder keinem Traffic empfiehlt sich GitHub Actions oder ein externer HTTP-Monitor.

## Sicherheit

- Für den HTTP-Scheduler immer HTTPS verwenden.
- Scheduler-Token niemals veröffentlichen.
- Möglichst den HTTP-Header statt eines Query-Parameters verwenden.
- Den GitHub Personal Access Token auf das Backup-Repository beschränken.
- Keine Tokens in das Repository committen.
- Bei einer Offenlegung Token sofort rotieren.

## Fehlerbehebung

### Einrichtung von GitHub Actions schlägt fehl

Berechtigungen des Fine-grained Tokens prüfen:

- Contents: Read and write
- Workflows: Read and write
- Secrets: Read and write

Außerdem prüfen, ob der Token auf das richtige Repository beschränkt ist.

### Workflow vorhanden, aber geplante Runs starten nicht

Unter **GitHub → Actions → Bludit GitHub Backup** prüfen, ob der Workflow aktiviert ist.

Mit **Run workflow** kann der Workflow manuell getestet werden.

Außerdem muss die konfigurierte Website von den GitHub-Runnern über HTTPS erreichbar sein.

### HTTP-Scheduler liefert 401

Der Scheduler-Token fehlt oder ist falsch.

Den vom Plugin erzeugten Token verwenden und als Header senden:

```text
X-GHBackup-Token: YOUR_SCHEDULER_TOKEN
```

### HTTP-Scheduler liefert 503

Der GitHub-Actions-Scheduler ist deaktiviert oder die GitHub-Konfiguration ist unvollständig.

**GitHub Actions verwenden** aktivieren und die Plugin-Einstellungen erneut speichern.

## Backup-Struktur

Standardmäßig werden die Dateien unter folgendem Prefix gespeichert:

```
bludit-backup/
├── bl-content/
├── bl-plugins/
└── bl-themes/
```

Der Prefix kann in der Plugin-Konfiguration geändert werden.

## Kompatibilität

- Bludit 3.22+
- PHP-Versionen, die von der installierten Bludit-Version unterstützt werden
- PHP-cURL-Erweiterung
- PHP-Sodium-Erweiterung für die Verwaltung von GitHub-Actions-Secrets

## Lizenz

MIT
