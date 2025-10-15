import wpConfig from '@wordpress/scripts/config/webpack.config.js';

// Use WordPress Scripts defaults for blocks so it emits index.js and index.asset.php per block.json
export default (env = {}, argv = {}) =>
  typeof wpConfig === 'function' ? wpConfig(env, argv) : wpConfig;
