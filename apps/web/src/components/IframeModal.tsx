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
    function handleMessage(event: MessageEvent) {
      if (event.data?.type === "AMUSEMENT_CLOSE") onClose();
    }
    window.addEventListener("message", handleMessage);
    return () => window.removeEventListener("message", handleMessage);
  }, [onClose]);

  return (
    <div className="modal-overlay">
      <div ref={modalRef} className="modal modal--iframe">
        <iframe
          className="iframe-modal-frame"
          src={url}
        />
      </div>
    </div>
  );
}
