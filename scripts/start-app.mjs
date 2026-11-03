import { existsSync, readdirSync } from 'node:fs';
import { homedir } from 'node:os';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { spawn, spawnSync } from 'node:child_process';

const root = dirname(dirname(fileURLToPath(import.meta.url)));
let php = process.env.PHP_BINARY;
if (!php && spawnSync('php', ['-v'], { stdio: 'ignore' }).status === 0) php = 'php';
if (!php && process.platform === 'win32') {
  const herd = join(homedir(), '.config', 'herd', 'bin');
  if (existsSync(herd)) {
    for (const version of readdirSync(herd).filter(name => /^php\d+$/.test(name)).sort().reverse()) {
      const candidate = join(herd, version, 'php.exe');
      if (existsSync(candidate)) { php = candidate; break; }
    }
  }
}
if (!php) {
  console.error('PHP 8.2+ is required. Add php to PATH or set PHP_BINARY to its executable.');
  process.exit(1);
}

// Preserve Herd's PHP/DLL environment in the server subprocess on Windows.
const server = spawn(php, ['artisan', 'serve', '--no-reload', '--host=127.0.0.1', `--port=${process.env.PORT || 8000}`, ...process.argv.slice(2)], {
  cwd: join(root, 'backend'), stdio: 'inherit', env: process.env,
});
server.on('error', error => { console.error(error.message); process.exitCode = 1; });
server.on('exit', code => { process.exitCode = code ?? 1; });
for (const signal of ['SIGINT', 'SIGTERM']) process.on(signal, () => server.kill(signal));
