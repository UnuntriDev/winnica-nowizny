import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { readdir, readFile } from 'node:fs/promises';
import { extname, join, resolve } from 'node:path';

async function filesIn(directory, extensions) {
  const entries = await readdir(directory, { withFileTypes: true });
  const nested = await Promise.all(entries.map(async (entry) => {
    const path = join(directory, entry.name);
    if (entry.isDirectory()) return filesIn(path, extensions);
    return extensions.includes(extname(entry.name)) ? [path] : [];
  }));
  return nested.flat();
}

const jsFiles = [
  ...(await filesIn(resolve('src/js'), ['.js'])),
  ...(await filesIn(resolve('tests'), ['.mjs'])),
  resolve('vite.config.js'),
];

for (const file of jsFiles) {
  execFileSync(process.execPath, ['--check', file], { stdio: 'pipe' });
}

for (const file of await filesIn(resolve('src/css'), ['.css'])) {
  const css = (await readFile(file, 'utf8')).replace(/\/\*[\s\S]*?\*\//g, '');
  let depth = 0;
  for (const character of css) {
    if (character === '{') depth += 1;
    if (character === '}') depth -= 1;
    assert.ok(depth >= 0, `${file} has an unexpected closing brace`);
  }
  assert.equal(depth, 0, `${file} has unbalanced braces`);

  for (const token of css.match(/#[\w-]+/g) || []) {
    if (/^#[0-9a-f]+$/i.test(token)) {
      assert.ok([4, 5, 7, 9].includes(token.length), `${file} contains invalid color ${token}`);
    }
  }
}

for (const file of await filesIn(resolve('acf-json'), ['.json'])) {
  JSON.parse(await readFile(file, 'utf8'));
}

const twigFiles = await filesIn(resolve('templates'), ['.twig']);
const allowedRaw = [
  'wp_kses_post',
  'wp_get_attachment_image',
  'wp_nonce_field',
  'winnica_kses_map_embed',
  "function('language_attributes')",
  "function('wp_head')",
  "function('wp_body_open')",
  "function('wp_footer')",
  'star | raw',
  'google_mark | raw',
];

for (const file of twigFiles) {
  const lines = (await readFile(file, 'utf8')).split(/\r?\n/);
  lines.forEach((line, index) => {
    if (line.includes('| raw')) {
      const context = lines.slice(Math.max(0, index - 8), index + 1).join('\n');
      assert.ok(
        allowedRaw.some((allowed) => context.includes(allowed)),
        `${file}:${index + 1} uses an unreviewed | raw`,
      );
    }
  });
}

console.log(`Lint passed: ${jsFiles.length} JS, ${twigFiles.length} Twig and all CSS/ACF files`);
