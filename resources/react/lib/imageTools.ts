/**
 * Product photo preparation, done in the browser before upload.
 *
 * Shop photos are taken on a counter or a plain wall: the background is one
 * roughly uniform colour touching the edges. So: flood-fill from the four
 * edges, turning every pixel close to the edge colour transparent, then trim
 * the transparent margin and put the product on a square canvas. Works well
 * on plain backgrounds; a busy background is left as is (the fill stops at
 * the first strong edge), which still looks fine on a tile.
 */

const OUTPUT = 512;

/** Tolerance in RGB distance; corners of a product photo are rarely pure white. */
const TOLERANCE = 40;

export interface PreparedImage {
    blob: Blob;
    previewUrl: string;
}

async function load(file: Blob): Promise<HTMLImageElement> {
    const url = URL.createObjectURL(file);
    try {
        const img = new Image();
        img.decoding = 'async';
        img.src = url;
        await img.decode();
        return img;
    } finally {
        URL.revokeObjectURL(url);
    }
}

/** Fit the photo into a working size so the fill stays fast on phone photos. */
function draw(img: HTMLImageElement, max = 1024): HTMLCanvasElement {
    const scale = Math.min(1, max / Math.max(img.naturalWidth, img.naturalHeight));
    const c = document.createElement('canvas');
    c.width = Math.max(1, Math.round(img.naturalWidth * scale));
    c.height = Math.max(1, Math.round(img.naturalHeight * scale));
    c.getContext('2d')!.drawImage(img, 0, 0, c.width, c.height);
    return c;
}

/** The background colour: the median of the pixels along the four edges. */
function edgeColor(d: Uint8ClampedArray, w: number, h: number): [number, number, number] {
    const rs: number[] = [], gs: number[] = [], bs: number[] = [];
    const push = (i: number) => { rs.push(d[i]!); gs.push(d[i + 1]!); bs.push(d[i + 2]!); };
    for (let x = 0; x < w; x += 2) { push((x) * 4); push(((h - 1) * w + x) * 4); }
    for (let y = 0; y < h; y += 2) { push((y * w) * 4); push((y * w + w - 1) * 4); }
    const med = (a: number[]) => a.sort((p, q) => p - q)[a.length >> 1] ?? 255;
    return [med(rs), med(gs), med(bs)];
}

function removeBackground(c: HTMLCanvasElement): { canvas: HTMLCanvasElement; box: [number, number, number, number] | null } {
    const w = c.width, h = c.height;
    const ctx = c.getContext('2d', { willReadFrequently: true })!;
    const image = ctx.getImageData(0, 0, w, h);
    const d = image.data;
    const [br, bg, bb] = edgeColor(d, w, h);
    const close = (i: number) => {
        const dr = d[i]! - br, dg = d[i + 1]! - bg, db = d[i + 2]! - bb;
        return Math.sqrt(dr * dr + dg * dg + db * db) <= TOLERANCE;
    };

    // Flood fill from every edge pixel through background-coloured pixels.
    const seen = new Uint8Array(w * h);
    const stack: number[] = [];
    for (let x = 0; x < w; x++) { stack.push(x, (h - 1) * w + x); }
    for (let y = 0; y < h; y++) { stack.push(y * w, y * w + w - 1); }
    while (stack.length) {
        const p = stack.pop()!;
        if (seen[p]) continue;
        seen[p] = 1;
        if (!close(p * 4)) continue;
        d[p * 4 + 3] = 0;
        const x = p % w, y = (p - x) / w;
        if (x > 0) stack.push(p - 1);
        if (x < w - 1) stack.push(p + 1);
        if (y > 0) stack.push(p - w);
        if (y < h - 1) stack.push(p + w);
    }

    // Soften the cut: a background-coloured pixel next to a removed one fades.
    for (let p = 0; p < w * h; p++) {
        if (d[p * 4 + 3] === 0) continue;
        const x = p % w, y = (p - x) / w;
        const nb = [p - 1, p + 1, p - w, p + w].filter((q) => q >= 0 && q < w * h && Math.abs((q % w) - x) <= 1 && Math.abs(((q - (q % w)) / w) - y) <= 1);
        if (nb.some((q) => d[q * 4 + 3] === 0) && close(p * 4)) d[p * 4 + 3] = 128;
    }
    ctx.putImageData(image, 0, 0);

    // Bounding box of what is left.
    let x0 = w, y0 = h, x1 = -1, y1 = -1;
    for (let y = 0; y < h; y++) for (let x = 0; x < w; x++) {
        if (d[(y * w + x) * 4 + 3]! > 8) { if (x < x0) x0 = x; if (x > x1) x1 = x; if (y < y0) y0 = y; if (y > y1) y1 = y; }
    }
    // Nothing removed, or everything removed: keep the photo untouched.
    const kept = x1 >= 0 ? (x1 - x0 + 1) * (y1 - y0 + 1) : 0;
    if (kept < w * h * 0.02 || kept > w * h * 0.98) return { canvas: c, box: null };
    return { canvas: c, box: [x0, y0, x1 - x0 + 1, y1 - y0 + 1] };
}

/** Square PNG, product centred with a little air around it. */
function square(c: HTMLCanvasElement, box: [number, number, number, number] | null): HTMLCanvasElement {
    const [sx, sy, sw, sh] = box ?? [0, 0, c.width, c.height];
    const out = document.createElement('canvas');
    out.width = out.height = OUTPUT;
    const ctx = out.getContext('2d')!;
    const pad = box ? 0.08 : 0;
    const fit = OUTPUT * (1 - 2 * pad);
    const scale = Math.min(fit / sw, fit / sh);
    const dw = sw * scale, dh = sh * scale;
    ctx.imageSmoothingQuality = 'high';
    ctx.drawImage(c, sx, sy, sw, sh, (OUTPUT - dw) / 2, (OUTPUT - dh) / 2, dw, dh);
    return out;
}

function toBlob(c: HTMLCanvasElement): Promise<Blob> {
    return new Promise((resolve, reject) => c.toBlob((b) => (b ? resolve(b) : reject(new Error('toBlob'))), 'image/png'));
}

/** The photo as it will be stored: square PNG, background removed when asked. */
export async function prepareProductImage(file: Blob, removeBg: boolean): Promise<PreparedImage> {
    const img = await load(file);
    const work = draw(img);
    const { canvas, box } = removeBg ? removeBackground(work) : { canvas: work, box: null };
    const blob = await toBlob(square(canvas, box));
    return { blob, previewUrl: URL.createObjectURL(blob) };
}
