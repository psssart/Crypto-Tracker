import js from "@eslint/js";
import globals from "globals";
import tseslint from "typescript-eslint";
import reactPlugin from "eslint-plugin-react";
import hooksPlugin from "eslint-plugin-react-hooks";
import prettierPlugin from "eslint-config-prettier";

export default tseslint.config(
    {
        ignores: ["public/**", "vendor/**", "bootstrap/cache/**", "storage/**", "node_modules/**"],
    },
    // 1. CONFIG FOR NODE FILES (Vite, Tailwind, etc.)
    {
        files: ["*.config.js", "*.config.ts", "*.config.mjs"],
        languageOptions: {
            globals: {
                ...globals.node, // This defines 'process', '__dirname', etc.
            },
        },
    },
    // 2. CONFIG FOR FRONTEND FILES (React/TS)
    {
        files: ["resources/js/**/*.{ts,tsx}"], // Adjust path to your Laravel JS folder
        plugins: {
            react: reactPlugin,
            "react-hooks": hooksPlugin,
        },
        languageOptions: {
            globals: {
                ...globals.browser,
            },
            parserOptions: {
                ecmaFeatures: { jsx: true },
            },
        },
        settings: {
            react: { version: "detect" },
        },
        rules: {
            ...reactPlugin.configs.recommended.rules,
            ...hooksPlugin.configs.recommended.rules,
            "react/react-in-jsx-scope": "off",
            "react/prop-types": "off",
            "@typescript-eslint/no-explicit-any": "warn",
            "no-console": ["warn", { allow: ["warn", "error"] }],
        },
    },
    prettierPlugin
);
