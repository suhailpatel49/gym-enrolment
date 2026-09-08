---
version: alpha
name: Incline Fitness
description: A disciplined, high-energy visual system for gym enrollment and operations.
colors:
  primary: "#F41E1E"
  secondary: "#1D2229"
  tertiary: "#171717"
  muted: "#6A6A6A"
  border: "#D8DDE1"
  canvas: "#F8F8F8"
  white: "#FFFFFF"
typography:
  display:
    fontFamily: Kanit
    fontSize: 48px
    fontWeight: 700
    lineHeight: 1
    letterSpacing: -0.02em
  heading:
    fontFamily: Kanit
    fontSize: 28px
    fontWeight: 600
    lineHeight: 1.1
    letterSpacing: -0.01em
  body:
    fontFamily: Archivo
    fontSize: 16px
    fontWeight: 400
    lineHeight: 1.5
    letterSpacing: 0em
  label:
    fontFamily: Archivo
    fontSize: 12px
    fontWeight: 700
    lineHeight: 1.25
    letterSpacing: 0.08em
rounded:
  none: 0px
  sm: 4px
  md: 8px
  lg: 12px
  xl: 20px
  full: 999px
spacing:
  xs: 4px
  sm: 8px
  md: 12px
  lg: 16px
  xl: 24px
  2xl: 32px
  3xl: 48px
components:
  accent-bar:
    backgroundColor: "{colors.primary}"
    width: 4px
  button-primary:
    backgroundColor: "{colors.tertiary}"
    textColor: "{colors.white}"
    typography: "{typography.label}"
    rounded: "{rounded.sm}"
    padding: "{spacing.md} {spacing.xl}"
    height: 48px
  button-primary-hover:
    backgroundColor: "{colors.secondary}"
  button-secondary:
    backgroundColor: "{colors.secondary}"
    textColor: "{colors.white}"
    typography: "{typography.label}"
    rounded: "{rounded.sm}"
    padding: "{spacing.md} {spacing.lg}"
    height: 44px
  field:
    backgroundColor: "{colors.white}"
    textColor: "{colors.tertiary}"
    typography: "{typography.body}"
    rounded: "{rounded.sm}"
    padding: "{spacing.md}"
    height: 48px
  field-disabled:
    backgroundColor: "{colors.canvas}"
    textColor: "{colors.muted}"
  card:
    backgroundColor: "{colors.white}"
    textColor: "{colors.tertiary}"
    rounded: "{rounded.lg}"
    padding: "{spacing.xl}"
  divider:
    backgroundColor: "{colors.border}"
    height: 1px
---

# Incline Fitness Design System

## Overview

Incline Fitness is direct, disciplined, and energetic. Strong Kanit headlines, compact Archivo UI copy, dark structural surfaces, and one vivid red accent evoke a serious training environment without making enrollment feel hostile. The interface prioritizes rapid tablet entry, clear operational scanning, and confident calls to action.

Provenance: the type families, exact seven-color palette, and observed visual traits were measured for approved Kanban card `t_bac629bb`. This file adapts those traits to this application's existing enrollment, admin, mail, and PDF surfaces; it is not a pixel copy. The reference site is evidence only and is never a runtime dependency. Kanit and Archivo browser files come from the Google Fonts repositories under the SIL Open Font License 1.1. Email uses Arial/Helvetica fallbacks and PDF uses Dompdf's bundled DejaVu Sans because client and renderer portability take priority over typeface fidelity.

## Token Exports

`tailwind.theme.json` and `resources/design/tailwind.theme.css` are deterministic Tailwind exports. A DTCG export is intentionally not committed because the upstream `@google/design.md` CLI currently emits output that is invalid against its declared DTCG 2025.10 schema. Commit one only when the upstream CLI emits schema-valid 2025.10 output.

## Colors

- **Primary (`#F41E1E`)** is the sole high-energy accent: active controls, focus emphasis, progress, key dividers, and restrained status highlights.
- **Secondary (`#1D2229`)** is the raised charcoal used for navigation, header blocks, and secondary dark controls.
- **Tertiary (`#171717`)** is the deepest ink for headings, body text, and primary actions. It provides accessible contrast on white and canvas.
- **Muted (`#6A6A6A`)** is supporting copy and metadata on white only; do not use it for tiny text on canvas.
- **Border (`#D8DDE1`)** separates fields and cards. It is structural, never the only indicator of focus or error.
- **Canvas (`#F8F8F8`)** is the quiet application and document background.
- **White (`#FFFFFF`)** is the primary content surface and reversed text color on dark backgrounds.

Red is not approved for normal-sized text on white because that pairing does not reach WCAG AA. Use it as a non-text accent or pair it with an additional icon, label, or border; validation text uses tertiary ink inside a red-tinted treatment.

## Typography

Kanit is display-only: brand marks, page titles, section headings, dashboard headings, and large success references at weights 600–700. Do not use it for paragraphs or dense forms. Archivo is the browser UI face for labels, controls, tables, navigation, and body copy at weights 400–700. Numeric values use tabular figures when available.

Browser fonts are locally bundled WOFF2 assets and must use `font-display: swap`; system sans fallbacks remain mandatory. Email must use `Arial, Helvetica, sans-serif`. Dompdf must use `DejaVu Sans, sans-serif`. Do not link Google Fonts, the reference site, or any other remote font service.

## Layout

The 4px base rhythm scales through 8, 12, 16, 24, 32, and 48px. Public pages use a centered content width of 1040px. Cards use 24px internal padding on compact screens and 32px on tablets. Form controls are at least 48px high; secondary controls are at least 44px.

Mobile is the default: a single column, 16px outer gutters, stacked actions, and full-width submit controls. At 640px, field pairs become two columns and action rows may sit inline. At tablet widths, preserve generous touch targets and cap line length rather than increasing density. The login card stays readable in portrait and landscape and never depends on a fixed viewport height.

## Elevation & Depth

Prefer borders and tonal contrast. Standard cards have no shadow. Only isolated entry, success, dialog, and floating admin surfaces may use a soft `0 16px 48px rgba(23, 23, 23, 0.10)` shadow. Dialog backdrops use translucent tertiary. Email and PDF omit shadows because renderer support is inconsistent.

## Shapes

The identity is angular with controlled softness: 4px controls and buttons, 8px inline notices, 12px cards, and 20px hero/login containers. Full rounding is limited to circular icons and status dots. Accent bars may be square. Avoid decorative blobs and excessive pill-shaped controls.

## Components

Primary actions use tertiary ink with white text; hover/active states retain an accessible dark background and introduce primary red as a ring or edge, never color alone. Secondary actions use white or secondary charcoal with a visible border. Every keyboard control has a 2px primary outline with at least 2px offset, and disabled controls retain readable contrast.

Inputs, selects, and textareas share 48px minimum height, white fill, 1px border, 4px radius, and an explicit label. Validation messages are adjacent, programmatically announced with `role="alert"`, and use dark text on canvas with a red border—not only red text. Radio and checkbox groups retain native inputs and large label hit areas.

Public enrollment cards use numbered headings and short explanatory copy. Loading preserves the button width and disables repeat submission. Success uses a clear status region, check mark, retained reference code, and a single next action. Filament keeps its native behavior and layout; the custom theme changes only tokens, typography, radius, surfaces, focus, and brand treatment.

HTML email uses nested presentation tables, inline styles, a 620px fluid shell, system fonts, and no remote images, CSS imports, scripts, forms, SVG, or background images. Media queries are progressive enhancement; meaning must survive without them. Dompdf uses document-local CSS, DejaVu Sans, tables for two-column data, simple borders/backgrounds, and no flex, grid, CSS variables, external stylesheets, remote URLs, scripts, forms, or fixed-position decoration. `isRemoteEnabled` remains false.

## Do's and Don'ts

- Do use red sparingly to focus attention; do not turn every surface or label red.
- Do keep dark-on-light reading areas and white-on-dark headers; do not place normal red text on white.
- Do retain semantic labels, legends, native form controls, visible focus, reduced-motion compatibility, and 44px minimum targets.
- Do preserve content order and dynamic enrollment data at every breakpoint and in mail/PDF fallbacks.
- Do use local browser assets and renderer-safe fallbacks; do not introduce remote runtime resources or depend on the measured reference site.
- Do keep mail table-based and PDF CSS conservative; do not reuse browser-only Tailwind utilities in those renderers.
- Do preserve existing routes, permissions, Livewire behavior, mail queue behavior, and plaintext templates; do not let visual work change application behavior.
