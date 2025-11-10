import js from '@eslint/js';
import tsParser from '@typescript-eslint/parser';
import wpPlugin from '@wordpress/eslint-plugin';
import jsxA11yPlugin from 'eslint-plugin-jsx-a11y';
import reactPlugin from 'eslint-plugin-react';
import reactHooksPlugin from 'eslint-plugin-react-hooks';

export default [
  js.configs.recommended,
  reactPlugin.configs.flat.recommended,
  {
    ignores: [
      '*.config.js',
      '*.config.mjs',
      'node_modules/**',
      'dist/**',
      'assets/**',
      'vendor/**',
    ],
  },
  {
    files: ['src/**/*.{js,jsx}'],
    languageOptions: {
      ecmaVersion: 2020,
      sourceType: 'module',
      parserOptions: {
        ecmaFeatures: {
          jsx: true,
        },
      },
      globals: {
        wp: 'readonly',
        console: 'readonly',
        window: 'readonly',
        document: 'readonly',
      },
    },
    plugins: {
      '@wordpress': wpPlugin,
      'jsx-a11y': jsxA11yPlugin,
    },
    rules: {
      // Disable ESLint rules that conflict with Prettier
      indent: 'off',
      'no-tabs': 'off',
      quotes: 'off',
      semi: 'off',
      'no-mixed-spaces-and-tabs': 'off',
      'comma-dangle': 'off',
      'object-curly-spacing': 'off',
      'array-bracket-spacing': 'off',
      // React specific rules
      'react/react-in-jsx-scope': 'off', // Not needed in modern React
      'react/prop-types': 'off', // WordPress handles props differently
      // WordPress specific rules
      '@wordpress/no-unsafe-wp-apis': 'warn', // Warn about experimental APIs
      // Accessibility rules (jsx-a11y)
      'jsx-a11y/alt-text': 'error', // Images must have alt text
      'jsx-a11y/anchor-has-content': 'error', // Links must have content
      'jsx-a11y/aria-role': 'error', // ARIA roles must be valid
      'jsx-a11y/heading-has-content': 'error', // Headings must have content
    },
    settings: {
      react: {
        version: 'detect',
      },
    },
  },
  // TypeScript files configuration
  {
    files: ['src/**/*.{ts,tsx}'],
    languageOptions: {
      ecmaVersion: 2020,
      sourceType: 'module',
      parser: tsParser,
      parserOptions: {
        project: './tsconfig.json',
        ecmaFeatures: {
          jsx: true,
        },
        tsconfigRootDir: import.meta.dirname || process.cwd(),
      },
      globals: {
        wp: 'readonly',
        console: 'readonly',
        window: 'readonly',
        document: 'readonly',
        JSX: 'readonly',
        HTMLElement: 'readonly',
        HTMLInputElement: 'readonly',
        HTMLSelectElement: 'readonly',
        HTMLTextAreaElement: 'readonly',
        HTMLButtonElement: 'readonly',
        HTMLLabelElement: 'readonly',
        KeyboardEvent: 'readonly',
        FocusEvent: 'readonly',
        Event: 'readonly',
        setTimeout: 'readonly',
        clearTimeout: 'readonly',
        setInterval: 'readonly',
        clearInterval: 'readonly',
        requestAnimationFrame: 'readonly',
        cancelAnimationFrame: 'readonly',
        performance: 'readonly',
        localStorage: 'readonly',
        campaignbridgeAdmin: 'readonly',
      },
    },
    plugins: {
      '@wordpress': wpPlugin,
      'jsx-a11y': jsxA11yPlugin,
      'react-hooks': reactHooksPlugin,
    },
    rules: {
      // Disable ESLint rules that conflict with Prettier
      indent: 'off',
      'no-tabs': 'off',
      quotes: 'off',
      semi: 'off',
      'no-mixed-spaces-and-tabs': 'off',
      'comma-dangle': 'off',
      'object-curly-spacing': 'off',
      'array-bracket-spacing': 'off',
      // React specific rules
      'react/react-in-jsx-scope': 'off', // Not needed in modern React
      'react/prop-types': 'off', // WordPress handles props differently
      // WordPress specific rules
      '@wordpress/no-unsafe-wp-apis': 'warn', // Warn about experimental APIs
      // Accessibility rules (jsx-a11y)
      'jsx-a11y/alt-text': 'error', // Images must have alt text
      'jsx-a11y/anchor-has-content': 'error', // Links must have content
      'jsx-a11y/aria-role': 'error', // ARIA roles must be valid
      'jsx-a11y/heading-has-content': 'error', // Headings must have content
      // React hooks rules
      'react-hooks/rules-of-hooks': 'error',
      'react-hooks/exhaustive-deps': 'warn',
    },
    settings: {
      react: {
        version: 'detect',
      },
    },
  },
];
