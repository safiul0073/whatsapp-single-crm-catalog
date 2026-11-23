import { test } from 'node:test';
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { readdirSync } from 'node:fs';

// A syntax error in a plain public script silently breaks the Alpine
// component it defines (e.g. the storefront page builder).
test('public scripts parse', () => {
    for (const file of readdirSync('public/js').filter((f) => f.endsWith('.js'))) {
        assert.doesNotThrow(() => execFileSync(process.execPath, ['--check', `public/js/${file}`], { stdio: 'pipe' }), file);
    }
});
