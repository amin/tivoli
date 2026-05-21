import { useEffect } from "react";

type Props = {
  url: string;
  onClose: () => void;
};

export default function IframeModal({ url, onClose }: Props) {
  useEffect(() => {
    function handleMessage(event: MessageEvent) {
      if (event.data?.type === "AMUSEMENT_CLOSE") {
        onClose();
      }
    }
    window.addEventListener("message", handleMessage);
    return () => window.removeEventListener("message", handleMessage);
  }, [onClose]);

  return (
    <div className="modal-overlay">
      <div className="modal modal--iframe">
        <iframe
          className="iframe-modal-frame"
          src={url}
        />
      </div>
    </div>
  );
}
