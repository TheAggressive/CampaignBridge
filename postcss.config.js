export default {
  plugins: {
    "postcss-nested": {},
    "@tailwindcss/postcss": {
      optimize: false,
    },
    "postcss-lightningcss": {
      minify: true,
    },
  },
};
