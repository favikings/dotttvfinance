---
name: Horizon Finance
colors:
  surface: '#faf8ff'
  surface-dim: '#dbd9e0'
  surface-bright: '#faf8ff'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#f4f3f9'
  surface-container: '#efedf3'
  surface-container-high: '#e9e7ee'
  surface-container-highest: '#e3e1e8'
  on-surface: '#1a1b20'
  on-surface-variant: '#444650'
  inverse-surface: '#2f3035'
  inverse-on-surface: '#f2f0f6'
  outline: '#757682'
  outline-variant: '#c5c6d2'
  surface-tint: '#455b9f'
  primary: '#000d35'
  on-primary: '#ffffff'
  primary-container: '#001f63'
  on-primary-container: '#7489d1'
  inverse-primary: '#b5c4ff'
  secondary: '#006782'
  on-secondary: '#ffffff'
  secondary-container: '#11cbfd'
  on-secondary-container: '#005268'
  tertiary: '#280400'
  on-tertiary: '#ffffff'
  tertiary-container: '#4c1001'
  on-tertiary-container: '#d0745a'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#dce1ff'
  primary-fixed-dim: '#b5c4ff'
  on-primary-fixed: '#00164d'
  on-primary-fixed-variant: '#2c4386'
  secondary-fixed: '#bbe9ff'
  secondary-fixed-dim: '#5dd4ff'
  on-secondary-fixed: '#001f29'
  on-secondary-fixed-variant: '#004d62'
  tertiary-fixed: '#ffdbd1'
  tertiary-fixed-dim: '#ffb5a0'
  on-tertiary-fixed: '#3b0900'
  on-tertiary-fixed-variant: '#78301b'
  background: '#faf8ff'
  on-background: '#1a1b20'
  surface-variant: '#e3e1e8'
typography:
  display-lg:
    fontFamily: Inter
    fontSize: 32px
    fontWeight: '600'
    lineHeight: '1.2'
    letterSpacing: -0.02em
  headline-md:
    fontFamily: Inter
    fontSize: 24px
    fontWeight: '600'
    lineHeight: '1.3'
    letterSpacing: -0.01em
  headline-sm:
    fontFamily: Inter
    fontSize: 20px
    fontWeight: '600'
    lineHeight: '1.4'
  body-lg:
    fontFamily: Inter
    fontSize: 16px
    fontWeight: '400'
    lineHeight: '1.6'
  body-md:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '400'
    lineHeight: '1.5'
  label-md:
    fontFamily: Inter
    fontSize: 13px
    fontWeight: '500'
    lineHeight: '1.2'
    letterSpacing: 0.01em
  label-sm:
    fontFamily: Inter
    fontSize: 11px
    fontWeight: '600'
    lineHeight: '1'
  headline-md-mobile:
    fontFamily: Inter
    fontSize: 20px
    fontWeight: '600'
    lineHeight: '1.3'
rounded:
  sm: 0.25rem
  DEFAULT: 0.5rem
  md: 0.75rem
  lg: 1rem
  xl: 1.5rem
  full: 9999px
spacing:
  sidebar-width: 260px
  container-padding: 2rem
  stack-gap: 1.5rem
  grid-gutter: 1.25rem
  card-padding: 1.5rem
---

## Brand & Style

This design system is engineered for high-trust financial environments, blending **Corporate Modernism** with **Sleek Minimalism**. The personality is authoritative yet accessible—prioritizing clarity of data and ease of navigation. 

The aesthetic is defined by a "Deep Navigation / Light Content" paradigm. A rich, primary-saturated sidebar provides a grounded anchor for the application, while the main workspace utilizes a light, airy canvas with soft-edged containers. The emotional response should be one of professional confidence, precision, and calm efficiency. Visual complexity is minimized through generous whitespace and a disciplined color application.

## Colors

The palette centers on a "Professional Blue" foundation.
- **Primary (#001F63):** Reserved for high-level navigation backgrounds and primary branding elements. It establishes the "anchor" of the interface.
- **Secondary (#00C8FA):** Used strategically for action buttons, progress indicators, and focal points to drive user attention without overwhelming the data.
- **Neutrals:** The system relies on a tiered greyscale. A very soft grey (`#F8FAFC`) serves as the application backdrop, while pure white (`#FFFFFF`) is used for elevated cards to create distinct content grouping.
- **Status Tones:** Success (Emerald), Warning (Amber), and Error (Rose) should be used in desaturated, "soft" variations for badges to maintain the sophisticated mood.

## Typography

The design system utilizes **Inter** exclusively to ensure maximum legibility in data-heavy views. The typographic hierarchy relies on weight contrast and subtle shifts in color (Primary vs. Muted) rather than extreme size differentials.

Headlines use a semi-bold weight (`600`) with tighter letter-spacing to feel "locked-in" and professional. Body text is optimized for readability with a `1.6` line-height. Small labels and status badges use a medium weight (`500`) to remain clear at reduced sizes. In the sidebar, typography should be rendered in high-contrast white or light-blue-tinted greys against the deep navy background.

## Layout & Spacing

The layout follows a **Fixed-Sidebar Fluid-Content** model.
- **Sidebar:** Fixed at 260px. This area uses a "dense but breathable" vertical rhythm.
- **Main Canvas:** A fluid area using a 12-column grid. The canvas background is the neutral-soft-grey, which provides context for white cards.
- **Spacing Rhythm:** Based on an 8px (0.5rem) scale. Standard card margins and gutters are set to `1.25rem` (20px) to provide a modern, airy feel without wasting excessive screen real estate.
- **Mobile Reflow:** On screens below 768px, the sidebar collapses into a bottom-tab bar or a hamburger-triggered overlay. Main content padding reduces to 1rem.

## Elevation & Depth

This design system avoids heavy shadows, instead using **Tonal Layering** and **Subtle Ambient Depth**.
- **Level 0 (Background):** The neutral grey surface.
- **Level 1 (Cards):** Pure white surfaces with a 1px border (`#E2E8F0`). A very soft, highly diffused shadow (0px 4px 12px, 4% opacity) may be applied to separate cards from the background.
- **Level 2 (Dropdowns/Modals):** High-contrast white surfaces with a more pronounced shadow (0px 12px 24px, 8% opacity) to indicate temporary overlay status.
- **Sidebar depth:** The primary blue sidebar is visually "deeper" than the content, acting as the foundation layer. Active states within the sidebar use subtle semi-transparent white overlays (10-15% opacity) rather than elevation.

## Shapes

The shape language is **Rounded**, favoring a "soft-corporate" feel. 
- **Standard (0.5rem):** Used for input fields, buttons, and small cards.
- **Large (1rem):** Used for main content containers and dashboard widgets.
- **Pill:** Reserved specifically for status badges and tags to distinguish them from interactive buttons.
- **Sidebar Elements:** Navigation items use a 0.5rem radius for active state highlights, ensuring they feel integrated with the overall system.

## Components

### Buttons
- **Primary:** Solid `#00C8FA` with white text. 0.5rem roundedness. High-visibility for "Action" items.
- **Secondary:** Transparent with a `#001F63` border or light grey ghost style.
- **Sidebar Nav:** High-contrast icons with 16px size. Active state includes a soft-tinted background highlight and a vertical bar on the left edge.

### Cards & Containers
- Standard white background, 1rem roundedness, 1px subtle border. 
- Dashboard "Metric" cards feature large `display-lg` numbers and `label-sm` trend indicators (e.g., +12% in green).

### Status Badges
- Soft-tinted backgrounds (e.g., light green background with dark green text) using pill-shaped geometry. High-trust, non-aggressive color signaling.

### Inputs & Progress Bars
- **Inputs:** White background, 0.5rem roundedness, 1px border. Focused state uses a 2px `#00C8FA` ring.
- **Progress Bars:** Thin tracks (4px-6px height) using `#00C8FA` for the fill and a very light grey for the track. Used for financial goal tracking.

### Sidebar Stats
- A unique "Status Box" at the bottom of the sidebar for account health or license usage, using semi-transparent bars to maintain the "Glass-on-Navy" aesthetic.