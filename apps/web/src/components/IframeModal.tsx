import { useEffect, useRef } from "react";
import { useFocusTrap } from "../hooks/useFocusTrap";

type Props = {
  url: string;
  onClose: () => void;
};

export default function IframeModal({ url, onClose }: Props) {
  const modalRef = useRef<HTMLDivElement>(null);
  useFocusTrap(modalRef, onClose);

  useEffect(() => {
    document.body.style.overflow = "hidden";
    return () => { document.body.style.overflow = ""; };
  }, []);

  useEffect(() => {
    function handleKey(e: KeyboardEvent) {
      if (e.key === "Escape") onClose();
    }
    window.addEventListener("keydown", handleKey);
    return () => window.removeEventListener("keydown", handleKey);
  }, [onClose]);

  useEffect(() => {
    function handleMessage(event: MessageEvent) {
      if (event.data?.type === "AMUSEMENT_CLOSE") onClose();
    }
    window.addEventListener("message", handleMessage);
    return () => window.removeEventListener("message", handleMessage);
  }, [onClose]);

  return (
    <div className="modal-overlay">
      <div ref={modalRef} className="modal modal--iframe">
        <div className="iframe-modal-header">
          <button className="iframe-modal-close" onClick={onClose} aria-label="Close">✕</button>
        </div>
        <iframe
          className="iframe-modal-frame"
          src={url}
        />
      </div>
    </div>
  );
}
