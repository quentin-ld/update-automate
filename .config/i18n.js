const { exec } = require('child_process');
const { existsSync, readFileSync, mkdirSync } = require('fs');
const { resolve, basename, dirname } = require('path');

const getTextDomain = () => {
  const pluginSlug = basename(process.cwd());
  const mainPhpPath = resolve(process.cwd(), `${pluginSlug}.php`);
  if (!existsSync(mainPhpPath)) {
    return pluginSlug;
  }
  try {
    const content = readFileSync(mainPhpPath, 'utf8');
    const textDomainMatch = content.match(/Text Domain:\s*(.+)/i);
    return textDomainMatch?.[1]?.trim() ?? pluginSlug;
  } catch {
    return pluginSlug;
  }
};

const main = () => {
  const wpCliPath = resolve(process.cwd(), 'vendor/bin/wp');
  const textDomain = getTextDomain();
  const potFilePath = `languages/${textDomain}.pot`;
  const potFullPath = resolve(process.cwd(), potFilePath);
  const languagesDir = dirname(potFullPath);

  if (!existsSync(languagesDir)) {
    mkdirSync(languagesDir, { recursive: true });
  }

  if (!existsSync(wpCliPath)) {
    console.error('WP-CLI not found. Run: composer install');
    process.exit(1);
  }

  const cmd = `"${wpCliPath}" i18n make-pot . "${potFilePath}" --domain=${textDomain} --skip-js`;

  exec(cmd, { cwd: process.cwd() }, (err, stdout, stderr) => {
    if (err || !existsSync(potFullPath)) {
      if (stderr) console.error(stderr);
      console.error('Translation file generation failed');
      process.exit(1);
    }
    console.log(`Created ${potFilePath}`);
  });
};

main();
