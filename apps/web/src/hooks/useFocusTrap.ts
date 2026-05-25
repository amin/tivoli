import { useEffect, type RefObject } from "react";

const FOCUSABLE =
  'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), iframe, [tabindex]:not([tabindex="-1"])';

export function useFocusTrap(
  ref: RefObject<HTMLElement | null>,
  onClose: () => void,
) {
  useEffect(() => {
    const el = ref.current;
    if (!el) return;

    const previously = document.activeElement as HTMLElement | null;

    const focusable = () =>
      Array.from(el.querySelectorAll<HTMLElement>(FOCUSABLE));
    const items = focusable();
    (items[0] ?? el).focus();

    function handleKeyDown(e: KeyboardEvent) {
      if (e.key === "Escape") {
        onClose();
        return;
      }
      if (e.key !== "Tab") return;
      const items = focusable();
      if (!items.length) return;
      const first = items[0];
      const last = items[items.length - 1];
      if (e.shiftKey && document.activeElement === first) {
        e.preventDefault();
        last.focus();
      } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault();
        first.focus();
      }
    }

    function handleFocusIn(e: FocusEvent) {
      const target = e.target as Node | null;
      if (!target) return;
      if (el.contains(target)) return; // focus still inside modal
      // push focus back into the modal
      const items = focusable();
      (items[0] ?? el).focus();
    }

    // Listen on document in capture phase so we catch focus/keys even if they occur before reaching modal
    document.addEventListener("keydown", handleKeyDown, true);
    document.addEventListener("focusin", handleFocusIn, true);

    return () => {
      document.removeEventListener("keydown", handleKeyDown, true);
      document.removeEventListener("focusin", handleFocusIn, true);
      previously?.focus();
    };
  }, [ref, onClose]);
}
