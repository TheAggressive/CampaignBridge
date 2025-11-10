export default {
  '*.{js,jsx,ts,tsx}': files => {
    // Filter out config files and only lint src/ files
    const srcFiles = files.filter(file => file.startsWith('src/'));
    return srcFiles.length > 0 ? [`pnpm lint:js ${srcFiles.join(' ')}`] : [];
  },
  '*.php': () => 'pnpm lint:php',
};
