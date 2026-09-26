/**
 * Product photo preparation, done in the browser before upload: the photo
 * is scaled down and cropped to its centre square, so every tile in the
 * grid has the same shape and no phone-sized original is uploaded.
 *
 * (An automatic background remover used to run here. It cut into the
 * product as often as it cleaned the background, so the photo is now kept
 * exactly as taken.)
 */

const OUTPUT = 512;

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

/** Fit the photo into a working size so the crop stays fast on phone photos. */
function draw(img: HTMLImageElement, max = 1024): HTMLCanvasElement {
    const scale = Math.min(1, max / Math.max(img.naturalWidth, img.naturalHeight));
    const c = document.createElement('canvas');
    c.width = Math.max(1, Math.round(img.naturalWidth * scale));
    c.height = Math.max(1, Math.round(img.naturalHeight * scale));
    c.getContext('2d')!.drawImage(img, 0, 0, c.width, c.height);
    return c;
}

/** Centre square of the photo, scaled to OUTPUT x OUTPUT. */
function square(c: HTMLCanvasElement): HTMLCanvasElement {
    const side = Math.min(c.width, c.height);
    const sx = (c.width - side) / 2, sy = (c.height - side) / 2;
    const out = document.createElement('canvas');
    out.width = out.height = OUTPUT;
    const ctx = out.getContext('2d')!;
    ctx.imageSmoothingQuality = 'high';
    ctx.drawImage(c, sx, sy, side, side, 0, 0, OUTPUT, OUTPUT);
    return out;
}

function toBlob(c: HTMLCanvasElement): Promise<Blob> {
    return new Promise((resolve, reject) => c.toBlob((b) => (b ? resolve(b) : reject(new Error('toBlob'))), 'image/png'));
}

/** The photo as it will be stored: a square PNG, otherwise untouched. */
export async function prepareProductImage(file: Blob): Promise<PreparedImage> {
    const img = await load(file);
    const blob = await toBlob(square(draw(img)));
    return { blob, previewUrl: URL.createObjectURL(blob) };
}
