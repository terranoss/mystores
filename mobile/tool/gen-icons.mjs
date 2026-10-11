import { readFileSync, mkdirSync } from "node:fs";
import { dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";
import sharp from "sharp";

const here = dirname(fileURLToPath(import.meta.url));
const repoRoot = resolve(here, "../..");
const mobileRoot = resolve(here, "..");
const faviconPath = resolve(repoRoot, "frontend/public/favicon.svg");
const outDir = resolve(mobileRoot, "assets/icon");

const PURPLE = "#863bff";
const SIZE = 1024;

mkdirSync(outDir, { recursive: true });

const svg = readFileSync(faviconPath, "utf8");

const viewBox = svg.match(/viewBox="([^"]+)"/)?.[1];
if (!viewBox) throw new Error("favicon.svg: no viewBox");

const [, , vbW, vbH] = viewBox.split(/\s+/).map(Number);

// The bolt silhouette is the first top-level path: a plain fill, no mask/filter.
const bolt = svg.match(/<path fill="#([0-9a-f]{6})" d="([^"]+)"\s*style=/i);
if (!bolt) throw new Error("favicon.svg: bolt silhouette path not found");
const boltColor = "#ffffff";
const boltPath = bolt[2];

const boltSvg = `<svg xmlns="http://www.w3.org/2000/svg" width="${vbW}" height="${vbH}" viewBox="${viewBox}"><path fill="${boltColor}" d="${boltPath}"/></svg>`;

// Rasterize the bolt big, then trim to its true bounding box so scaling is exact.
const raw = await sharp(Buffer.from(boltSvg), { density: 600 })
  .png()
  .toBuffer();

const trimmed = await sharp(raw)
  .trim({ threshold: 1 })
  .png()
  .toBuffer();

const meta = await sharp(trimmed).metadata();
console.log(`bolt bbox: ${meta.width}x${meta.height}`);

async function boltLayer(canvas, fillRatio, yOffsetRatio = 0) {
  const target = Math.round(canvas * fillRatio);
  const scale = Math.min(target / meta.width, target / meta.height);
  const w = Math.round(meta.width * scale);
  const h = Math.round(meta.height * scale);
  const resized = await sharp(trimmed).resize(w, h, { fit: "fill" }).png().toBuffer();
  const left = Math.round((canvas - w) / 2);
  const top = Math.round((canvas - h) / 2 + canvas * yOffsetRatio);
  return { input: resized, left, top };
}

const purpleBg = {
  create: { width: SIZE, height: SIZE, channels: 4, background: PURPLE },
};

// Legacy / fallback launcher icon: full-bleed purple with the bolt.
// Legacy / fallback launcher icon. Launchers below API 26 do not mask the icon,
// so the shape is baked in here rather than relying on the launcher to do it.
const legacyMask = await sharp(
  Buffer.from(
    `<svg width="${SIZE}" height="${SIZE}" xmlns="http://www.w3.org/2000/svg"><rect width="${SIZE}" height="${SIZE}" rx="${SIZE * 0.22}" fill="#fff"/></svg>`,
  ),
)
  .png()
  .toBuffer();

const master = await sharp(purpleBg)
  .composite([await boltLayer(SIZE, 0.62, -0.02), { input: legacyMask, blend: "dest-in" }])
  .png()
  .toBuffer();

await sharp(master).toFile(resolve(outDir, "icon_master_1024.png"));

// Adaptive foreground: transparent, bolt inside the 66% safe zone, no glow.
// The bolt is wider than tall, so its diagonal corners are what must clear the
// safe-zone circle. fillRatio 0.45 absorbs the extra 16% inset that
// flutter_launcher_icons applies to the foreground layer, landing the bolt's
// corners at ~94% of the visible safe-zone radius.
await sharp({
  create: { width: SIZE, height: SIZE, channels: 4, background: "#00000000" },
})
  .composite([await boltLayer(SIZE, 0.45, 0)])
  .png()
  .toFile(resolve(outDir, "icon_foreground.png"));

// Adaptive background: flat colour, no gradient (masks/tints it).
await sharp(purpleBg).png().toFile(resolve(outDir, "icon_background.png"));

// Play Store listing icon: 512x512, opaque, no rounded corners.
await sharp({ create: { width: 512, height: 512, channels: 4, background: PURPLE } })
  .composite([await boltLayer(512, 0.66, -0.02)])
  .png()
  .toFile(resolve(outDir, "play_icon_512.png"));

// Previews at the sizes that actually matter for legibility.
const previewDir = resolve(outDir, "preview");
mkdirSync(previewDir, { recursive: true });
for (const px of [192, 96, 48]) {
  await sharp(master)
    .resize(px, px)
    .png()
    .toFile(resolve(previewDir, `master_${px}.png`));
  await sharp(resolve(outDir, "icon_foreground.png"))
    .resize(px, px)
    .flatten({ background: PURPLE })
    .png()
    .toFile(resolve(previewDir, `adaptive_${px}.png`));
}

console.log("wrote assets to", outDir);