import { PDFDocument } from 'pdf-lib';

export function base64ToUint8Array(base64: string): Uint8Array {
  const binary = atob(base64);
  const bytes = new Uint8Array(binary.length);
  for (let i = 0; i < binary.length; i++) {
    bytes[i] = binary.charCodeAt(i);
  }
  return bytes;
}

export async function appendAwbAndPacking(
  merged: PDFDocument,
  awbPdf: Uint8Array,
  packingListPdf: Uint8Array,
): Promise<void> {
  const awbDoc = await PDFDocument.load(awbPdf);
  const packingDoc = await PDFDocument.load(packingListPdf);
  const awbPages = await merged.copyPages(awbDoc, awbDoc.getPageIndices());
  awbPages.forEach((page) => merged.addPage(page));
  const packingPages = await merged.copyPages(packingDoc, packingDoc.getPageIndices());
  packingPages.forEach((page) => merged.addPage(page));
}

export async function mergeAwbAndPacking(
  awbPdf: Uint8Array,
  packingListPdf: Uint8Array,
): Promise<Uint8Array> {
  const merged = await PDFDocument.create();
  await appendAwbAndPacking(merged, awbPdf, packingListPdf);
  return merged.save();
}

export async function mergeBulkShippingDocuments(
  documents: Array<{ awbPdf: Uint8Array; packingListPdf: Uint8Array }>,
): Promise<Uint8Array> {
  const merged = await PDFDocument.create();
  for (const doc of documents) {
    await appendAwbAndPacking(merged, doc.awbPdf, doc.packingListPdf);
  }
  return merged.save();
}

export function openPdfBlob(bytes: Uint8Array, filename: string): void {
  const blob = new Blob([new Uint8Array(bytes)], { type: 'application/pdf' });
  const url = URL.createObjectURL(blob);
  const tab = window.open(url, '_blank');
  if (tab) {
    setTimeout(() => URL.revokeObjectURL(url), 60_000);
    return;
  }
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  a.click();
  setTimeout(() => URL.revokeObjectURL(url), 5000);
}
