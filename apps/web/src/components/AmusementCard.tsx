import type { AmusementItem } from "../hooks/useAmusements";

type Props = {
  amusement: AmusementItem;
  apiKey?: string;
  showImage?: boolean;
  onCardClick?: (e: React.MouseEvent, amusement: AmusementItem) => void;
  actions?: React.ReactNode;
};

export default function AmusementCard({ amusement, apiKey, showImage = false, onCardClick, actions }: Props) {
  const content = (
    <div className="card-body">
      {showImage && (
        <img className="card-image" src={amusement.image_url ?? ""} alt={amusement.name} />
      )}
      <div className="card-meta">
        <p className="card-title">{amusement.name}</p>
        <p className="card-tag">{amusement.type}</p>
      </div>
      {(amusement.price != null || amusement.player_payout != null) && (
        <div className="card-pills">
          {amusement.price != null && (
            <span className="card-pill card-pill--fee">Entrance €{amusement.price}</span>
          )}
          {amusement.player_payout != null && (
            <span className="card-pill card-pill--winnings">Winnings €{amusement.player_payout.toFixed(2)}</span>
          )}
        </div>
      )}
      {amusement.description && <p className="card-description">{amusement.description}</p>}
      {!showImage && <p className="card-url">{amusement.url}</p>}
      {apiKey && (
        <div className="card-api-key">
          <span className="card-api-key-label">API Key</span>
          <code className="card-api-key-value">{apiKey}</code>
        </div>
      )}
      {actions && <div className="card-actions">{actions}</div>}
    </div>
  );

  if (onCardClick) {
    return (
      <a
        className="card"
        href={amusement.url}
        target="_blank"
        rel="noreferrer"
        onClick={(e) => onCardClick(e, amusement)}
      >
        {content}
      </a>
    );
  }

  return <div className="card">{content}</div>;
}
