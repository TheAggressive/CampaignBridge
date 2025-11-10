import wpConfig from '@wordpress/scripts/config/webpack.config.js';
import fg from 'fast-glob';
import MiniCssExtractPlugin from 'mini-css-extract-plugin';
import path from 'path';
import { merge } from 'webpack-merge';
import RemoveEmptyScriptsPlugin from 'webpack-remove-empty-scripts';

function toPosix(p) {
  return p.split('\\').join('/');
}

function buildEntries() {
  const cwd = process.cwd();
  const entries = {};

  // Scripts: src/scripts/**/*.js -> dist/scripts/**/*.js (preserve structure)
  const jsFiles = fg.sync('src/scripts/**/*.{js,ts,tsx}', { cwd });
  jsFiles.forEach(file => {
    // Keep the full relative path from src/scripts
    const rel = toPosix(
      path.relative(path.join(cwd, 'src/scripts'), path.join(cwd, file))
    );
    const name = rel.replace(/\.(js|ts|tsx)$/i, '');
    // This creates entries like: 'scripts/admin/template-manager'
    entries[`scripts/${name}`] = path.resolve(cwd, file);
  });

  // Styles: src/styles/**/*.{css,scss} -> dist/styles/**/*.css (preserve structure)
  const styleFiles = fg.sync('src/styles/**/*.{css,scss}', { cwd });
  styleFiles.forEach(file => {
    const rel = toPosix(
      path.relative(path.join(cwd, 'src/styles'), path.join(cwd, file))
    );
    const name = rel.replace(/\.(css|scss)$/i, '');
    // This creates entries like: 'styles/styles'
    entries[`styles/${name}`] = path.resolve(cwd, file);
  });

  return entries;
}

export default (env = {}, argv = {}) => {
  const base = typeof wpConfig === 'function' ? wpConfig(env, argv) : wpConfig;
  const template = Array.isArray(base) ? base[0] : base;

  return merge(template, {
    name: 'assets',
    entry: { ...wpConfig.entry(), ...buildEntries() },
    output: {
      path: path.resolve(process.cwd(), 'dist'),
      filename: '[name].js',
      chunkFilename: '[name].js',
      publicPath: '',
      clean: false,
    },
    // Completely override optimization settings
    optimization: {
      splitChunks: false,
      runtimeChunk: false,
      concatenateModules: true,
      // Use named chunks instead of numbered ones
      chunkIds: 'named',
      moduleIds: 'named',
    },
    // Override CSS output filename to preserve directory structure
    plugins: [
      new MiniCssExtractPlugin({
        filename: '[name].css', // This will output styles/styles.css
        chunkFilename: '[name].css',
      }),
      new RemoveEmptyScriptsPlugin({
        stage: RemoveEmptyScriptsPlugin.STAGE_AFTER_PROCESS_PLUGINS,
      }),
    ],
    stats: 'minimal',
    // Suppress postcss-calc warnings that are harmless
    ignoreWarnings: [
      warning => {
        return (
          warning.name === 'ModuleWarning' &&
          warning.message.includes('postcss-calc: Lexical error')
        );
      },
    ],
  });
};
