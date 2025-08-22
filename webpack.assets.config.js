const path = require('path');
const { merge } = require('webpack-merge');
const wpConfig = require('@wordpress/scripts/config/webpack.config');
const RemoveEmptyScriptsPlugin = require('webpack-remove-empty-scripts');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const fg = require('fast-glob');
const DependencyExtractionWebpackPlugin = require('@wordpress/dependency-extraction-webpack-plugin');

function toPosix(p) {
  return p.split('\\').join('/');
}

function buildEntries() {
  const cwd = process.cwd();
  const entries = {};

  // Scripts: src/scripts/**/*.js -> dist/scripts/**/*.js (preserve structure)
  const jsFiles = fg.sync('src/scripts/**/*.js', { cwd });
  jsFiles.forEach((file) => {
    // Keep the full relative path from src/scripts
    const rel = toPosix(
      path.relative(path.join(cwd, 'src/scripts'), path.join(cwd, file))
    );
    const name = rel.replace(/\.js$/i, '');
    // This creates entries like: 'scripts/services/EmailGenerator'
    entries[`scripts/${name}`] = path.resolve(cwd, file);
  });

  // Styles: src/styles/**/*.{css,scss} -> dist/styles/**/*.css (preserve structure)
  const styleFiles = fg.sync('src/styles/**/*.{css,scss}', { cwd });
  styleFiles.forEach((file) => {
    const rel = toPosix(
      path.relative(path.join(cwd, 'src/styles'), path.join(cwd, file))
    );
    const name = rel.replace(/\.(css|scss)$/i, '');
    // This creates entries like: 'styles/styles'
    entries[`styles/${name}`] = path.resolve(cwd, file);
  });

  return entries;
}

module.exports = (env = {}, argv = {}) => {
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
    plugins: [
      new DependencyExtractionWebpackPlugin(),
      new MiniCssExtractPlugin({
        filename: '[name].css',
        chunkFilename: '[name].css',
      }),
      new RemoveEmptyScriptsPlugin({
        stage: RemoveEmptyScriptsPlugin.STAGE_AFTER_PROCESS_PLUGINS,
      }),
    ],
    stats: 'minimal',
  });
};
