const fs = require('fs');
const path = require('path');
const archiver = require('archiver');

const startTime = Date.now();
const currentDir = path.basename(process.cwd());
const zipFileName = `${currentDir}.zip`;
const output = fs.createWriteStream(path.join('.', zipFileName));
const archive = archiver('zip', { zlib: { level: 9 } });

output.on('close', () => {
  const duration = Date.now() - startTime;
  const size = archive.pointer();
  const sizeStr = size < 1024 ? `${size} bytes` : `${(size / 1024).toFixed(2)} KB`;
  console.log(`Zipped: ${zipFileName} (${sizeStr}) in ${duration}ms`);
});

archive.on('error', (err) => {
  throw err;
});

archive.pipe(output);

archive.glob('**/*', {
  ignore: [
    '.DS_Store',
    '.editorconfig',
    '.eslintignore',
    '.eslintrc*',
    '.git/**',
    '.gitignore',
    '.gitattributes',
    '.config/**',
    '.distignore',
    '.php-cs-fixer.php',
    '.prettierrc*',
    '.vscode/**',
    '.wordpress-org/**',
    'composer.json',
    'composer.lock',
    'node_modules/**',
    'package-lock.json',
    'package.json',
    'vendor/**',
    'assets/src/**',
    '*.tar.gz',
    '*.zip',
    'phpstan.neon',
    'phpstan-bootstrap.php',
    'workflow.md',
  ],
});

archive.finalize();
