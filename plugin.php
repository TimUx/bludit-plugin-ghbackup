<?php

/**
 * GitHub Backup for Bludit
 *
 * Creates a complete, versioned Git tree from selected Bludit directories and
 * pushes it to GitHub using the Git Data REST API. No git binary or SSH is used.
 */
class pluginGhbackup extends Plugin
{
    private const API_VERSION = '2022-11-28';
    private const USER_AGENT = 'Bludit-GitHub-Backup/0.1.0';
    private const MAX_FILE_BYTES = 52428800; // 50 MiB

    public function init()
    {
        $this->dbFields = [
            'token' => '',
            'owner' => '',
            'repo' => '',
            'branch' => 'main',
            'prefix' => 'bludit-backup',
            'backupContent' => true,
            'backupPlugins' => true,
            'backupThemes' => true,
            'autoEnabled' => false,
            'autoInterval' => 86400,
            'lastBackup' => 0
        ];
    }

    public function form()
    {
        $status = $this->getStatus();
        $lastBackup = (int)$this->getValue('lastBackup');

        $html = '<div>';
        $html .= '<p><strong>GitHub Backup</strong></p>';
        $html .= '<p class="tip">Creates one Git commit containing the selected Bludit files. The server does not need Git, SSH or shell access.</p>';
        $html .= '</div>';

        $html .= '<div>';
        $html .= '<label>GitHub Fine-grained Personal Access Token</label>';
        $html .= '<input name="token" type="password" value="" placeholder="' . ($this->getValue('token') ? 'Token already saved — leave empty to keep it' : 'github_pat_...') . '">';
        $html .= '<span class="tip">Use a token restricted to this repository with <strong>Contents: Read and write</strong>.</span>';
        $html .= '</div>';

        $html .= '<div>';
        $html .= '<label>Repository Owner</label>';
        $html .= '<input name="owner" type="text" value="' . htmlspecialchars($this->getValue('owner'), ENT_QUOTES, 'UTF-8') . '" placeholder="TimUx">';
        $html .= '</div>';

        $html .= '<div>';
        $html .= '<label>Repository</label>';
        $html .= '<input name="repo" type="text" value="' . htmlspecialchars($this->getValue('repo'), ENT_QUOTES, 'UTF-8') . '" placeholder="bludit-backup">';
        $html .= '</div>';

        $html .= '<div>';
        $html .= '<label>Branch</label>';
        $html .= '<input name="branch" type="text" value="' . htmlspecialchars($this->getValue('branch'), ENT_QUOTES, 'UTF-8') . '" placeholder="main">';
        $html .= '</div>';

        $html .= '<div>';
        $html .= '<label>Backup path prefix</label>';
        $html .= '<input name="prefix" type="text" value="' . htmlspecialchars($this->getValue('prefix'), ENT_QUOTES, 'UTF-8') . '" placeholder="bludit-backup">';
        $html .= '<span class="tip">The selected directories are stored below this path.</span>';
        $html .= '</div>';

        $html .= '<div style="margin-top:1.5em;padding-top:1em;border-top:1px solid #eee">';
        $html .= '<label>Backup contents</label>';
        $html .= '<label><input type="hidden" name="backupContent" value="0"><input name="backupContent" type="checkbox" value="1" ' . ($this->getValue('backupContent') ? 'checked' : '') . '> bl-content</label>';
        $html .= '<label><input type="hidden" name="backupPlugins" value="0"><input name="backupPlugins" type="checkbox" value="1" ' . ($this->getValue('backupPlugins') ? 'checked' : '') . '> bl-plugins</label>';
        $html .= '<label><input type="hidden" name="backupThemes" value="0"><input name="backupThemes" type="checkbox" value="1" ' . ($this->getValue('backupThemes') ? 'checked' : '') . '> bl-themes</label>';
        $html .= '</div>';

        $html .= '<div style="margin-top:1.5em;padding-top:1em;border-top:1px solid #eee">';
        $html .= '<label>Automatic backup check</label>';
        $html .= '<label><input type="hidden" name="autoEnabled" value="0"><input name="autoEnabled" type="checkbox" value="1" ' . ($this->getValue('autoEnabled') ? 'checked' : '') . '> Enable</label>';
        $html .= '<select name="autoInterval">';
        foreach ([3600 => 'Every hour', 21600 => 'Every 6 hours', 43200 => 'Every 12 hours', 86400 => 'Daily', 604800 => 'Weekly'] as $seconds => $label) {
            $selected = ((int)$this->getValue('autoInterval') === $seconds) ? ' selected' : '';
            $html .= '<option value="' . $seconds . '"' . $selected . '>' . $label . '</option>';
        }
        $html .= '</select>';
        $html .= '<span class="tip">Without cron, the backup runs when an administrator request occurs after the interval has elapsed.</span>';
        $html .= '</div>';

        $html .= '<div style="margin-top:1.5em;padding-top:1em;border-top:1px solid #eee">';
        $html .= '<input name="testConnection" type="submit" class="btn btn-secondary" value="Test GitHub connection">';
        $html .= '<input name="runBackup" type="submit" class="btn btn-primary" value="Backup now" style="margin-left:.5em">';
        $html .= '</div>';

        if ($lastBackup > 0) {
            $html .= '<p class="tip">Last successful backup: ' . htmlspecialchars(date('Y-m-d H:i:s', $lastBackup), ENT_QUOTES, 'UTF-8') . '</p>';
        }

        if ($status !== '') {
            $isError = stripos($status, 'Error') === 0;
            $html .= '<div class="alert ' . ($isError ? 'alert-danger' : 'alert-success') . '" role="alert">' . htmlspecialchars($status, ENT_QUOTES, 'UTF-8') . '</div>';
        }

        return $html;
    }

    public function post()
    {
        $oldToken = $this->getValue('token');
        parent::post();

        if (trim((string)($_POST['token'] ?? '')) === '' && $oldToken !== '') {
            $this->setValue('token', $oldToken);
        }

        if (!empty($_POST['testConnection'])) {
            $this->testConnection();
        }

        if (!empty($_POST['runBackup'])) {
            $this->runBackup();
        }
    }

    /**
     * Shared-hosting friendly scheduler. No cron is required.
     */
    public function afterAdminLoad()
    {
        if (!$this->getValue('autoEnabled') || !$this->isConfigured()) {
            return;
        }

        $last = (int)$this->getValue('lastBackup');
        $interval = max(3600, (int)$this->getValue('autoInterval'));

        if ($last > 0 && (time() - $last) < $interval) {
            return;
        }

        $this->runBackup(true);
    }

    private function runBackup($automatic = false)
    {
        if (!$this->isConfigured()) {
            $this->setStatus('Error: GitHub settings are incomplete.');
            return false;
        }

        $lockPath = $this->workspace() . 'backup.lock';
        $lock = @fopen($lockPath, 'c+');
        if (!$lock || !@flock($lock, LOCK_EX | LOCK_NB)) {
            if ($lock) {
                @fclose($lock);
            }
            return false;
        }

        try {
            $files = $this->collectFiles();
            if (empty($files)) {
                throw new Exception('No files selected or found.');
            }

            $remote = $this->getBranchState();
            $baseTreeSha = $remote['tree_sha'];
            $treeEntries = [];

            foreach ($files as $file) {
                if ($file['size'] > self::MAX_FILE_BYTES) {
                    throw new Exception('File is larger than 50 MiB: ' . $file['source']);
                }

                $data = @file_get_contents($file['source']);
                if ($data === false) {
                    throw new Exception('Unable to read file: ' . $file['source']);
                }

                $blob = $this->githubRequest('POST', '/git/blobs', [
                    'content' => base64_encode($data),
                    'encoding' => 'base64'
                ]);

                if (!isset($blob['sha'])) {
                    throw new Exception('GitHub did not return a blob SHA for ' . $file['path']);
                }

                $treeEntries[] = [
                    'path' => $file['path'],
                    'mode' => '100644',
                    'type' => 'blob',
                    'sha' => $blob['sha']
                ];
            }

            // Delete files from the previous backup tree that no longer exist locally.
            $existing = $this->getExistingBackupPaths($baseTreeSha);
            $wanted = [];
            foreach ($treeEntries as $entry) {
                $wanted[$entry['path']] = true;
            }

            foreach ($existing as $path) {
                if (!isset($wanted[$path])) {
                    $treeEntries[] = [
                        'path' => $path,
                        'mode' => '100644',
                        'type' => 'blob',
                        'sha' => null
                    ];
                }
            }

            $tree = $this->githubRequest('POST', '/git/trees', [
                'base_tree' => $baseTreeSha,
                'tree' => $treeEntries
            ]);

            if (!isset($tree['sha'])) {
                throw new Exception('GitHub did not return a tree SHA.');
            }

            // If the tree did not change, do not create a noisy commit.
            if (!empty($tree['sha']) && $tree['sha'] === $baseTreeSha) {
                $this->setStatus('Backup skipped: no file changes detected.');
                return true;
            }

            $message = ($automatic ? 'chore(backup): automatic Bludit backup' : 'chore(backup): Bludit backup') . ' — ' . date('Y-m-d H:i:s');

            $commit = $this->githubRequest('POST', '/git/commits', [
                'message' => $message,
                'tree' => $tree['sha'],
                'parents' => [$remote['commit_sha']]
            ]);

            if (!isset($commit['sha'])) {
                throw new Exception('GitHub did not return a commit SHA.');
            }

            $this->githubRequest('PATCH', '/git/refs/heads/' . rawurlencode($this->getValue('branch')), [
                'sha' => $commit['sha'],
                'force' => false
            ]);

            $this->setValue('lastBackup', time());
            $this->setStatus('Backup successful: ' . count($files) . ' files committed as ' . substr($commit['sha'], 0, 12) . '.');

            return true;
        } catch (Throwable $e) {
            $this->setStatus('Error: ' . $e->getMessage());
            return false;
        } finally {
            @flock($lock, LOCK_UN);
            @fclose($lock);
            @unlink($lockPath);
        }
    }

    private function testConnection()
    {
        try {
            $result = $this->githubRequest('GET', '');
            if (isset($result['full_name'])) {
                $this->setStatus('GitHub connection successful: ' . $result['full_name']);
            } else {
                $this->setStatus('GitHub connection successful.');
            }
        } catch (Throwable $e) {
            $this->setStatus('Error: ' . $e->getMessage());
        }
    }

    private function getBranchState()
    {
        $branch = $this->getValue('branch');
        $ref = $this->githubRequest('GET', '/git/ref/heads/' . rawurlencode($branch));

        if (!isset($ref['object']['sha'])) {
            throw new Exception('Branch not found: ' . $branch);
        }

        $commitSha = $ref['object']['sha'];
        $commit = $this->githubRequest('GET', '/git/commits/' . $commitSha);

        if (!isset($commit['tree']['sha'])) {
            throw new Exception('Unable to read branch tree.');
        }

        return [
            'commit_sha' => $commitSha,
            'tree_sha' => $commit['tree']['sha']
        ];
    }

    private function getExistingBackupPaths($baseTreeSha)
    {
        $prefix = $this->normalisePrefix();
        $tree = $this->githubRequest('GET', '/git/trees/' . $baseTreeSha . '?recursive=1');
        $paths = [];

        if (empty($tree['tree']) || $prefix === '') {
            return $paths;
        }

        $prefixSlash = $prefix . '/';
        foreach ($tree['tree'] as $entry) {
            if (($entry['type'] ?? '') === 'blob' && strpos($entry['path'], $prefixSlash) === 0) {
                $paths[] = $entry['path'];
            }
        }

        return $paths;
    }

    private function collectFiles()
    {
        $roots = [];

        if ($this->getValue('backupContent')) {
            $roots[] = ['name' => 'bl-content', 'path' => defined('PATH_CONTENT') ? PATH_CONTENT : dirname(BLUDIT_ROOT) . '/bl-content/'];
        }

        if ($this->getValue('backupPlugins')) {
            $roots[] = ['name' => 'bl-plugins', 'path' => defined('PATH_PLUGINS') ? PATH_PLUGINS : dirname(BLUDIT_ROOT) . '/bl-plugins/'];
        }

        if ($this->getValue('backupThemes')) {
            $roots[] = ['name' => 'bl-themes', 'path' => defined('PATH_THEMES') ? PATH_THEMES : dirname(BLUDIT_ROOT) . '/bl-themes/'];
        }

        $files = [];
        foreach ($roots as $root) {
            if (!is_dir($root['path'])) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root['path'], FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $item) {
                if (!$item->isFile() || $item->isLink()) {
                    continue;
                }

                $source = $item->getPathname();
                $relative = substr($source, strlen(rtrim($root['path'], '/\\')) + 1);
                $relative = str_replace('\\', '/', $relative);

                if ($this->isExcluded($root['name'] . '/' . $relative)) {
                    continue;
                }

                $files[] = [
                    'source' => $source,
                    'path' => $this->normalisePrefix() . '/' . $root['name'] . '/' . ltrim($relative, '/'),
                    'size' => $item->getSize()
                ];
            }
        }

        return $files;
    }

    private function isExcluded($path)
    {
        $path = str_replace('\\', '/', $path);

        $excluded = [
            '/.DS_Store',
            '/.git/',
            '/cache/',
            '/caches/',
            '/sessions/',
            '/session/',
            '/tmp/',
            '/temp/',
            '/logs/',
            '/log/'
        ];

        foreach ($excluded as $needle) {
            if ($needle[0] === '/' && substr($needle, -1) === '/') {
                if (strpos('/' . $path . '/', $needle) !== false) {
                    return true;
                }
            } elseif (substr($path, -strlen($needle)) === $needle) {
                return true;
            }
        }

        return false;
    }

    private function normalisePrefix()
    {
        $prefix = trim((string)$this->getValue('prefix'));
        $prefix = trim(str_replace('\\', '/', $prefix), '/');
        $prefix = preg_replace('#[^A-Za-z0-9._/-]+#', '-', $prefix);
        $prefix = preg_replace('#/+#', '/', $prefix);
        return $prefix !== '' ? $prefix : 'bludit-backup';
    }

    private function githubRequest($method, $endpoint, $data = null)
    {
        $token = trim((string)$this->getValue('token'));
        $owner = trim((string)$this->getValue('owner'));
        $repo = trim((string)$this->getValue('repo'));

        if ($token === '' || $owner === '' || $repo === '') {
            throw new Exception('GitHub settings are incomplete.');
        }

        $url = 'https://api.github.com/repos/' . rawurlencode($owner) . '/' . rawurlencode($repo) . $endpoint;

        $ch = curl_init($url);
        if ($ch === false) {
            throw new Exception('Unable to initialize cURL.');
        }

        $headers = [
            'Authorization: Bearer ' . $token,
            'Accept: application/vnd.github+json',
            'X-GitHub-Api-Version: ' . self::API_VERSION,
            'User-Agent: ' . self::USER_AGENT,
            'Content-Type: application/json'
        ];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 120
        ]);

        if ($method === 'POST' || $method === 'PATCH') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_SLASHES));
        } elseif ($method !== 'GET') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        }

        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new Exception('cURL: ' . ($error ?: 'unknown error'));
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            throw new Exception('GitHub returned an invalid JSON response (HTTP ' . $httpCode . ').');
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            $message = isset($decoded['message']) ? $decoded['message'] : ('HTTP ' . $httpCode);
            throw new Exception('GitHub API (' . $httpCode . '): ' . $message);
        }

        return $decoded;
    }

    private function isConfigured()
    {
        return trim((string)$this->getValue('token')) !== ''
            && trim((string)$this->getValue('owner')) !== ''
            && trim((string)$this->getValue('repo')) !== '';
    }

    private function setStatus($message)
    {
        @file_put_contents($this->workspace() . 'status.txt', $message);
    }

    private function getStatus()
    {
        $file = $this->workspace() . 'status.txt';
        return is_file($file) ? (string)@file_get_contents($file) : '';
    }
}
