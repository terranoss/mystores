import { resolve, dirname } from "node:path";
import { fileURLToPath } from "node:url";
import sharp from "sharp";

const here = dirname(fileURLToPath(import.meta.url));
const iconDir = resolve(here, "../assets/icon");
const previewDir = resolve(iconDir, "preview");

// Mirrors what Android does: 108dp canvas, foreground inset 16%, then crop to
// the centre 72dp visible area and mask to the launcher's shape.
const CANVAS = 432;
const fgInset = Math.round(CANVAS * 0.16);
const fgSize = CANVAS - fgInset * 2;

const foreground = await sharp(resolve(iconDir, "icon_foreground.png"))
  .resize(fgSize, fgSize)
  .png()
  .toBuffer();

async function masked(px, shape) {
  const canvas = sharp({
    create: { width: CANVAS, height: CANVAS, channels: 4, background: "#863bff" },
  }).composite([{ input: foreground, left: fgInset, top: fgInset }]);

  const flat = await canvas.png().toBuffer();

  const visible = Math.round((CANVAS * 2) / 3);
  const off = Math.round((CANVAS - visible) / 2);
  let mask = Buffer.from(
    `<svg width="${visible}" height="${visible}" xmlns="http://www.w3.org/2000/svg">${
      shape === "circle"
        ? `<circle cx="${visible / 2}" cy="${visible / 2}" r="${visible / 2}" fill="#fff"/>`
        : shape === "squircle"
          ? `<rect width="${visible}" height="${visible}" rx="${visible * 0.28}" fill="#fff"/>`
          : `<rect width="${visible}" height="${visible}" rx="${visible * 0.5}" fill="#fff"/>`
    }</svg>`,
  );
  mask = await sharp(mask).png().toBuffer();

  const maskPx = await sharp(mask).resize(px, px).png().toBuffer();

  await sharp(flat)
    .extract({ left: off, top: off, width: visible, height: visible })
    .resize(px, px)
    .composite([{ input: maskPx, blend: "dest-in" }])
    .png()
    .toFile(resolve(previewDir, `adaptive_${shape}_${px}.png`));
}

for (const px of [192, 96, 48]) {
  for (const shape of ["circle", "squircle", "rounded"]) {
    await masked(px, shape);
  }
}
console.log("adaptive mask previews written");