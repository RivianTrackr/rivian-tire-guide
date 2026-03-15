# RivianTrackr — Branding & Design System

Design tokens and conventions for RivianTrackr projects. All values sourced from the Rivian Tire Guide plugin (v1.28.0).

---

## Brand Identity

| Field       | Value                                                        |
|-------------|--------------------------------------------------------------|
| Product     | Rivian Tire Guide                                            |
| Author      | RivianTrackr                                                 |
| Description | Interactive tire guide for Rivian vehicles with filtering, comparison, and ratings |

---

## Dark Theme (Default — Frontend)

CSS custom properties prefixed with `--rtg-`. This is the user-facing theme.

| Token                | CSS Variable          | Value     | Usage                          |
|----------------------|-----------------------|-----------|--------------------------------|
| Primary Accent       | `--rtg-accent`        | `#fba919` | Buttons, links, active states  |
| Accent Hover         | `--rtg-accent-hover`  | `#fba919` | Hover state for accent items   |
| Background (Primary) | `--rtg-bg-primary`    | `#121e2b` | Page / section background      |
| Background (Card)    | `--rtg-bg-card`       | `#121e2b` | Card surfaces                  |
| Background (Input)   | `--rtg-bg-input`      | `#374151` | Form inputs, select fields     |
| Background (Deep)    | `--rtg-bg-deep`       | `#0f1a26` | Deeper containers, footers     |
| Text (Primary)       | `--rtg-text-primary`  | `#e5e7eb` | Body text                      |
| Text (Light)         | `--rtg-text-light`    | `#e5e7eb` | Emphasized text                |
| Text (Muted)         | `--rtg-text-muted`    | `#e5e7eb` | Secondary / helper text        |
| Text (Heading)       | `--rtg-text-heading`  | `#e5e7eb` | Headings, titles               |
| Border / Divider     | `--rtg-border`        | `#374151` | Card borders, dividers, rules  |
| Stars (Filled)       | `--rtg-star-filled`   | `#fba919` | Filled rating stars            |
| Stars (Your Rating)  | `--rtg-star-user`     | `#4ade80` | User's own rating stars        |
| Stars (Empty)        | `--rtg-star-empty`    | `#2d3a49` | Empty / unfilled stars         |

---

## Light Theme (Admin Panel)

Used in the WordPress admin settings screens.

| Token              | CSS Variable           | Value     |
|--------------------|------------------------|-----------|
| Action Primary     | `--rtg-action-primary` | `#0071e3` |
| Action Hover       | `--rtg-action-hover`   | `#0077ed` |
| Text Primary       | `--rtg-text-primary`   | `#1d1d1f` |
| Text Secondary     | `--rtg-text-secondary` | `#6e6e73` |
| Text Muted         | `--rtg-text-muted`     | `#86868b` |
| Success            | `--rtg-success`        | `#34c759` |
| Success Light      | `--rtg-success-light`  | `#d1f4e0` |
| Error              | `--rtg-error`          | `#ff3b30` |
| Error Light        | `--rtg-error-light`    | `#ffe5e5` |
| Warning Background | `--rtg-warning-bg`     | `#fff3cd` |
| Info Background    | `--rtg-info-bg`        | `#dbeafe` |
| Border             | `--rtg-border`         | `#d2d2d7` |
| Background Light   | `--rtg-bg-light`       | `#f5f5f7` |
| Background Hover   | `--rtg-bg-hover`       | `#e8e8ed` |
| White              | `--rtg-white`          | `#ffffff` |

---

## Grade / Score Colors

Used for tire performance ratings (A–F letter grades).

| Grade | Color     | Meaning   |
|-------|-----------|-----------|
| A     | `#34c759` | Excellent |
| B     | `#7dc734` | Good      |
| C     | `#facc15` | Fair      |
| D     | `#f97316` | Poor      |
| F     | `#b91c1c` | Failing   |

Note: Grade C text is overridden to `#1d1d1f` for contrast on the yellow background.

---

## Typography

### Font Stacks

| Purpose  | Stack                                                                                                  |
|----------|--------------------------------------------------------------------------------------------------------|
| Primary  | `-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif` |
| Monospace| `'SF Mono', Monaco, 'Cascadia Code', 'Roboto Mono', Consolas, 'Courier New', monospace`                |

### Size Scale

| Use Case    | Size   | Weight |
|-------------|--------|--------|
| Page title  | 32px   | 600    |
| Card header | 20px   | 600    |
| Body        | 14–15px| 400    |
| Small       | 12–13px| 400    |
| Labels      | 12px   | 600    |
| Badges      | 9–11px | 700    |

### Icons

Font Awesome 6 Free (solid + regular weights).

---

## Border Radius

| Token                | Value  | Usage                        |
|----------------------|--------|------------------------------|
| `--rtg-radius-card`  | `12px` | Cards, containers            |
| `--rtg-radius-input` | `8px`  | Inputs, standard buttons     |
| `--rtg-radius-badge` | `6px`  | Badges, small components     |
| `--rtg-radius-pill`  | `4px`  | Chips, label pills           |
| `--rtg-radius-toggle`| `34px` | Toggle switches              |
| Full round           | `9999px`| Pill buttons, fully rounded |

---

## Shadows

| Level    | Value                                   | Usage                    |
|----------|-----------------------------------------|--------------------------|
| Subtle   | `0 1px 3px rgba(0,0,0,0.2)`            | Small elements           |
| Light    | `0 2px 8px rgba(0,0,0,0.15)`           | Hover cards              |
| Medium   | `0 4px 12px rgba(0,0,0,0.4)`           | Elevated cards           |
| Large    | `0 8px 24px rgba(0,0,0,0.3)`           | Modals, drawers          |
| Overlay  | `0 25px 60px rgba(0,0,0,0.5)`          | Full-page overlays       |
| Focus    | `0 0 0 2px var(--rtg-accent)`           | Keyboard focus ring      |

---

## Transitions & Animation

| Speed    | Duration | Easing | Usage                         |
|----------|----------|--------|-------------------------------|
| Instant  | 0.1s     | ease   | Scale, active press           |
| Fast     | 0.15s    | ease   | Color, border, opacity        |
| Standard | 0.2s     | ease   | Buttons, backgrounds          |
| Smooth   | 0.3s     | ease   | Slide, transform, drawers     |

Reduced motion: all transitions and animations are disabled when `prefers-reduced-motion: reduce` is active.

---

## Button Conventions

- Buttons with `background: var(--rtg-accent)` (`#fba919`) use **dark text** `#0f172a`, not white.
- Secondary / outline buttons use `color: var(--rtg-accent)` with transparent or border-only backgrounds.
- Destructive buttons (delete, dismiss) use red (`#ef4444` / `#ee383a`).
- Review/special-action buttons use purple (`#7c3aed`).

---

## Brand Gradient

A multi-color signature gradient used for special decorative elements:

```css
linear-gradient(135deg, #fba919, #d2de24, #86c440, #5ec095, #34c5ec, #2b96d2, #3571b8, #534da0, #d11d55, #ef3d6c, #ed1a36, #ee383a)
```

---

## Accessibility

- **Focus rings**: `2px solid var(--rtg-accent)` with `2px` offset
- **Reduced motion**: `@media (prefers-reduced-motion: reduce)` — disables all transitions and animations
- **Color contrast**: Accent buttons use dark text (`#0f172a`) on gold (`#fba919`) for WCAG compliance
