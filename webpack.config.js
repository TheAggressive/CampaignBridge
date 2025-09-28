const wpConfig = require("@wordpress/scripts/config/webpack.config");

// Use WordPress Scripts defaults for blocks so it emits index.js and index.asset.php per block.json
module.exports = (env = {}, argv = {}) =>
  typeof wpConfig === "function" ? wpConfig(env, argv) : wpConfig;
