import { useStorage } from '@vueuse/core';
import { ref } from 'vue';
import { toast } from 'vue-sonner';

/**
 * Printing always goes through the PDF so paper output equals the download (PRD-05 §7.3):
 * load the PDF in a hidden iframe and call print(); if the browser blocks that, open it in a
 * new tab with a "Ctrl+P" hint. The first time, show paper/scale tips.
 */
const tipsSeen = useStorage('print.tipsSeen', false);
const tipsOpen = ref(false);
const tipsPaper = ref<'f4' | 'a4'>('a4');
let pending: (() => void) | null = null;

function printNow(url: string): void {
    document.getElementById('pdf-print-frame')?.remove();
    const frame = document.createElement('iframe');
    frame.id = 'pdf-print-frame';
    frame.style.cssText =
        'position:fixed;right:0;bottom:0;width:0;height:0;border:0;visibility:hidden';
    frame.src = url;

    const fallback = () => {
        const win = window.open(url, '_blank', 'noopener');
        toast.info(
            win
                ? 'PDF dibuka di tab baru. Tekan Ctrl+P untuk mencetak.'
                : 'Izinkan pop-up untuk situs ini, lalu coba lagi.',
        );
    };

    frame.onload = () => {
        try {
            frame.contentWindow?.focus();
            frame.contentWindow?.print();
        } catch {
            fallback();
        }
    };

    // Phones / Safari often cannot print a PDF from an iframe.
    if (/iPhone|iPad|Android/i.test(navigator.userAgent)) {
        fallback();

        return;
    }

    document.body.appendChild(frame);
}

export function printPdf(url: string, paper: 'f4' | 'a4'): void {
    if (tipsSeen.value) {
        printNow(url);

        return;
    }

    tipsPaper.value = paper;
    pending = () => printNow(url);
    tipsOpen.value = true;
}

export function usePrintTips() {
    function confirm(dontShowAgain: boolean): void {
        tipsSeen.value = dontShowAgain;
        tipsOpen.value = false;
        pending?.();
        pending = null;
    }

    return { tipsOpen, tipsPaper, confirm };
}
