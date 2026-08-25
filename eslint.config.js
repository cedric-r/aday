import js from '@eslint/js';
import tsPlugin from '@typescript-eslint/eslint-plugin';
import tsParser from '@typescript-eslint/parser';
import reactHooksPlugin from 'eslint-plugin-react-hooks';
import globals from 'globals';

export default [
  js.configs.recommended,
  {
    files: ['src/**/*.{ts,tsx}'],
    languageOptions: {
      parser: tsParser,
      parserOptions: {
        ecmaVersion: 2020,
        sourceType: 'module',
        ecmaFeatures: { jsx: true },
      },
      // Browser + ES2020 globals so no-undef doesn't flag fetch/Response/File etc.
      globals: { ...globals.browser, ...globals.es2020 },
    },
    plugins: {
      '@typescript-eslint': tsPlugin,
      'react-hooks': reactHooksPlugin,
    },
    rules: {
      ...tsPlugin.configs.recommended.rules,
      ...reactHooksPlugin.configs.recommended.rules,
      'no-undef': 'off', // TypeScript already reports undeclared names
      '@typescript-eslint/no-explicit-any': 'error',
      '@typescript-eslint/no-var-requires': 'error',
      'no-var': 'error',
      'no-console': 'error',
      'prefer-const': 'error',
    },
  },
  {
    files: ['src/**/*.test.{ts,tsx}'],
    rules: {
      'no-console': 'off',
    },
  },
];
