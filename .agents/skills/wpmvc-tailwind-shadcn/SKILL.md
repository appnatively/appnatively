---
name: Setup Tailwind CSS v4 and Shadcn UI in WPMVC
description: A guide for integrating Tailwind CSS v4 and Shadcn UI into the WPMVC WordPress MVC framework.
---

# Setup Tailwind CSS v4 and Shadcn UI in WPMVC

This skill provides the necessary steps and best practices for integrating **Tailwind CSS v4** and **Shadcn UI** into a WordPress plugin built with the **WPMVC** framework, which uses `@wordpress/scripts` and Webpack for its build pipeline.

## 1. Prerequisites

- A project based on the **WPMVC** framework.
- Node.js and **pnpm** (preferred) or npm.

## 2. Dependency Installation

Install the core dependencies for Tailwind v4 and Shadcn:

```bash
pnpm install -D tailwindcss @tailwindcss/postcss postcss postcss-loader autoprefixer
pnpm install lucide-react clsx tailwind-merge @radix-ui/react-slot class-variance-authority
```

## 3. Configuration

### 3.1 PostCSS Setup
Create or update `postcss.config.js` to use the Tailwind v4 plugin:

```javascript
module.exports = {
  plugins: {
    '@tailwindcss/postcss': {},
    autoprefixer: {},
  },
}
```

### 3.2 Framework Path Mapping
Create `components.json` at the root, mapping paths to the WPMVC `resources` directory:

```json
{
  "$schema": "https://ui.shadcn.com/schema.json",
  "style": "new-york",
  "rsc": false,
  "tsx": true,
  "tailwind": {
    "config": "",
    "css": "resources/css/app.css",
    "baseColor": "zinc",
    "cssVariables": true,
    "prefix": ""
  },
  "aliases": {
    "components": "@/components",
    "utils": "@/lib/utils",
    "ui": "@/components/ui",
    "lib": "@/lib",
    "hooks": "@/hooks"
  },
  "iconLibrary": "lucide"
}
```

### 3.3 TypeScript Aliases (Required for CLI)
Even if not using full TypeScript, create `tsconfig.json` at the root for `shadcn` CLI to resolve aliases:

```json
{
  "compilerOptions": {
    "baseUrl": ".",
    "paths": {
      "@/*": ["./resources/js/*"]
    }
  }
}
```

## 4. Build Pipeline Adjustments

### 4.1 Transition to CSS
Rename the legacy `resources/sass` directory to `resources/css` and `app.scss` to `app.css`. 

### 4.2 Webpack Ignore (Prevents Infinite Loops)
WPMVC's watch process might re-trigger if it detects its own emitted assets. Update `webpack.config.js`:

```javascript
module.exports = {
  // ...
  entry: {
    'js/app': './resources/js/index.tsx', // Example
    'css/app': './resources/css/app.css',
  },
  watchOptions: {
    ignored: [
      '**/assets/build/**',
      '**/*.asset.php',
    ],
  },
  // ...
}
```

### 4.3 Tailwind v4 Stylesheet
Add the v4 directives and explicit sources to `resources/css/app.css`:

```css
@import "tailwindcss";

/* Explicitly target source files to avoid scanning build folders */
@source "../js/**/*.{js,jsx,ts,tsx}";
@source "../views/**/*.php";
@source "../../inc/**/*.php";

/* Exclude build folder */
@source "!../../assets/build/**/*";

@theme {
  /* Shadcn variables mapped to v4 colors */
  --color-border: var(--border);
  --color-input: var(--input);
  /* ... (see project for full theme variables) */
}

@layer base {
  :root {
    --background: hsl(0 0% 100%);
    /* ... (standard Shadcn variables) */
  }
}
```

## 5. Usage

1. **Add Utilities**: Create `resources/js/lib/utils.ts` with the `cn` helper.
2. **Add Components**: Use `npx shadcn@latest add [component]` to install components into `resources/js/components/ui`.
3. **Build**: Run `pnpm minify` or `wp-scripts build` to generate the CSS.
