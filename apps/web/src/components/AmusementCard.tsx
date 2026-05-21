import type { AmusementItem } from "../hooks/useAmusements";
import noImageSrc from "../assets/no-image.jpg";

type Props = {
  amusement: AmusementItem;
  apiKey?: string;
  showImage?: boolean;
  showBalance?: boolean;
  onCardClick?: (e: React.MouseEvent, amusement: AmusementItem) => void;
  actions?: React.ReactNode;
};

export default function AmusementCard({ amusement, apiKey, showImage = false, showBalance = false, onCardClick, actions }: Props) {
  const content = (
    <div className="card-body">
      {showImage && (
        <img className="card-image" src={amusement.image_url ?? noImageSrc} alt={amusement.name} />
      )}
      <div className="card-meta">
        <p className="card-title">{amusement.name}</p>
        <p className="card-tag">{amusement.type}</p>
      </div>
      {(amusement.price != null || amusement.player_payout != null || (showBalance && amusement.amusement_balance != null)) && (
        <div className="card-pills">
          {amusement.price != null && (
            <span className="card-pill card-pill--fee">Entrance €{amusement.price}</span>
          )}
          {amusement.player_payout != null && (
            <span className="card-pill card-pill--winnings">Winnings €{amusement.player_payout.toFixed(2)}</span>
          )}
          {showBalance && amusement.amusement_balance != null && (
            <span className="card-pill card-pill--balance">Balance €{amusement.amusement_balance.toFixed(2)}</span>
          )}
        </div>
      )}
      {amusement.description && <p className="card-description">{amusement.description}</p>}
      {!showImage && amusement.url && (
        <div className="card-api-key">
          <span className="card-api-key-label">URL</span>
          <code className="card-api-key-value">{amusement.url}</code>
        </div>
      )}
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
