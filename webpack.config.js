const wpConfig = require('@wordpress/scripts/config/webpack.config');

// Thin wrapper: defer entirely to WordPress Scripts defaults.
// Assets (scripts/styles) are handled in a separate config: webpack.assets.config.js
module.exports = (env = {}, argv = {}) =>
  typeof wpConfig === 'function' ? wpConfig(env, argv) : wpConfig;
