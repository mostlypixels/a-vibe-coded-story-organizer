# Dim images on dark themes — resolution log

Feedback/decisions, deviations from the spec/plan, and issues → resolutions found while
implementing this feature. Read it before extending the feature.

An exception log: a task that went to plan gets no entry, because the diff and the task
file already record what was built. Bullets under the headings below, root cause first.

## Feedback & decisions

- **Scalar key.** The theme vocabulary is colour-only, so dimming is an `image_opacity` preset key and a `--image-opacity` property. Not a `ThemeTokens` entry.
- **Hover target.** Hover on an enclosing link or button restores the image, not only hover on the image.
- **Default dimming.** Accepted: the default preset is `low-glare-dark`, so logged-out pages dim too.
- **Show-page lightbox.** The codex show page gets a lightbox, so touch users can see its gallery at full brightness.
- **Shared component.** The lightbox becomes `x-image-lightbox`, opened by the `open-lightbox` window event. The main show-page image opens it. Not built on `x-modal`.
- **Tuned by eye (task 04).** Kept `low-glare-dark` 0.8 and `no-halation` 0.65. Checked 1.0, 0.9, 0.8, 0.75, 0.65, 0.5 on a test image; no change made.
- **Values follow the body text.** On real images, 0.65 did not mute enough (Cindy, 2026-10-06). Rule: image white over `surface` reaches the lightness of `content`. Result: `low-glare-dark` 0.7, `no-halation` 0.36.
- **Task split.** Four tasks: setting, lightbox component, show-page lightbox, CSS and docs.

## Deviations from the spec/plan

- The spec's touch decision said "no new interaction". The grill added a show-page lightbox, so that decision no longer holds for the codex gallery. Covers and thumbnails elsewhere still have no touch path.

## Issues → resolutions

- The driver had no pointer-hover command, so hover and focus rules could not be checked. Added `hover <css>` to `.claude/skills/run-imagoldfish/driver.mjs` and its SKILL.md table.
- Dev data had one image per entry, so no browser run covered "switch thumbnail, then open lightbox". The button reads `images[current]` at click time; checked by reading the code.
