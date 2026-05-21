type Props = {
  url: string;
};

export default function IframeModal({ url }: Props) {
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
