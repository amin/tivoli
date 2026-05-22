import { useRef } from "react";
import type { MouseEvent } from "react";
import { useNavigate } from "react-router-dom";
import { type AmusementItem } from "../hooks/useAmusements";
import { useFocusTrap } from "../hooks/useFocusTrap";

type Props = {
  amusement: AmusementItem;
  onClose: () => void;
  onContinueAsGuest: () => void;
};

const warnings = [
  { icon: "🎫", text: "Stamps won't be collected" },
  { icon: "🏆", text: "Victory points won't be saved" },
];

export default function GuestWarningModal({ amusement, onClose, onContinueAsGuest }: Props) {
  const navigate = useNavigate();
  const modalRef = useRef<HTMLDivElement>(null);
  useFocusTrap(modalRef, onClose);

  function handleOverlayClick(e: MouseEvent<HTMLDivElement>) {
    if (e.target === e.currentTarget) onClose();
  }

  function handleSignIn() {
    onClose();
    navigate("/login");
  }

  return (
    <div className="modal-overlay" onClick={handleOverlayClick}>
      <div ref={modalRef} className="modal guest-modal" role="dialog" aria-modal="true" aria-labelledby="guest-modal-title">
        <div className="guest-modal-icon" aria-hidden="true">👤</div>
        <h2 className="modal-title" id="guest-modal-title">You're browsing as a guest</h2>
        <p className="modal-sub">
          You can still enter <strong>{amusement.name}</strong>, but your progress won't be saved.
        </p>

        <ul className="guest-warnings">
          {warnings.map((w) => (
            <li key={w.text} className="guest-warning-item">
              <span className="guest-warning-icon" aria-hidden="true">{w.icon}</span>
              <span>{w.text}</span>
            </li>
          ))}
        </ul>

        <div className="guest-modal-actions">
          <button className="btn btn-primary" onClick={handleSignIn}>
            Sign in to an account
          </button>
          <button className="btn btn-secondary" onClick={onContinueAsGuest}>
            Continue as guest
          </button>
        </div>
      </div>
    </div>
  );
}
