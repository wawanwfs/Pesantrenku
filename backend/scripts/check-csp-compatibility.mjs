import { readdir, readFile } from 'node:fs/promises';
import path from 'node:path';
import process from 'node:process';

const sourceRoot = path.resolve('resources/js');
const extensions = new Set(['.js', '.jsx', '.mjs']);
const forbiddenPatterns = [
  ['Inline style attribute or prop', /\bstyle\s*=/g],
  ['Inline style object prop', /\bstyle\s*:\s*\{/g],
  ['Inline style element', /<style\b/g],
  ['DOM inline style mutation', /\.style(?:\.|\[)/g],
  ['Runtime style attribute', /setAttribute\s*\(\s*['"]style['"]/g],
  ['Runtime style element', /createElement\s*\(\s*['"]style['"]/g],
  ['Inline cssText mutation', /\.cssText\s*=/g],
];

const files = [
  ...await collectFiles(sourceRoot),
  path.resolve('resources/views/app.blade.php'),
].sort();
const violations = [];

for (const file of files) {
  const source = await readFile(file, 'utf8');
  for (const [description, pattern] of forbiddenPatterns) {
    for (const match of source.matchAll(pattern)) {
      const line = source.slice(0, match.index).split('\n').length;
      violations.push(`${path.relative(process.cwd(), file)}:${line}: ${description}`);
    }
  }
}

if (violations.length) {
  console.error('CSP compatibility check failed:');
  violations.forEach((violation) => console.error(`- ${violation}`));
  process.exit(1);
}

console.log(`CSP compatibility check passed for ${files.length} frontend source files.`);

async function collectFiles(directory) {
  const entries = await readdir(directory, { withFileTypes: true });
  const filesInDirectory = await Promise.all(entries.map(async (entry) => {
    const entryPath = path.join(directory, entry.name);
    if (entry.isDirectory()) return collectFiles(entryPath);
    return extensions.has(path.extname(entry.name)) ? [entryPath] : [];
  }));

  return filesInDirectory.flat().sort();
}
