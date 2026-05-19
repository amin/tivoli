import type { MouseEvent } from "react";

type Props = { onClose: () => void };

const steps = [
  {
    number: 1,
    color: "blue",
    title: "Enter the park",
    body: "Activate your account using the startcode you received or use our Guest account.",
  },
  {
    number: 2,
    color: "orange",
    title: "Browse attractions",
    body: "Explore all available games and attractions. Filter by type to find what you're looking for.",
  },
  {
    number: 3,
    color: "yellow",
    title: "Play & explore",
    body: "Click any attraction to enter. A secure identity token is generated automatically.",
  },
  {
    number: 4,
    color: "red",
    title: "Earn & vote",
    body: "Win tokens by playing games and vote on your favorite attraction.",
  },
  {
    number: 5,
    color: "green",
    title: "Exchange your tokens",
    body: "Spend your earned tokens and exchange them for in-game currency.",
  },
];

export default function HowItWorksModal({ onClose }: Props) {
  function handleOverlayClick(e: MouseEvent<HTMLDivElement>) {
    if (e.target === e.currentTarget) onClose();
  }

  return (
    <div className="modal-overlay" onClick={handleOverlayClick}>
      <div className="modal hiw-modal" role="dialog" aria-modal="true" aria-labelledby="hiw-title">
        <div className="hiw-header">
          <h2 className="modal-title" id="hiw-title">How it works</h2>
          <button className="hiw-close" onClick={onClose} aria-label="Close">✕</button>
        </div>
        <p className="modal-sub">Five steps to make the most of Loopland.</p>

        <ol className="hiw-steps">
          {steps.map((s) => (
            <li key={s.number} className={`hiw-step hiw-step--${s.color}`}>
              <span className="hiw-step-num">{s.number}</span>
              <div className="hiw-step-body">
                <strong className="hiw-step-title">{s.title}</strong>
                <p className="hiw-step-text">{s.body}</p>
              </div>
            </li>
          ))}
        </ol>
      </div>
    </div>
  );
}
