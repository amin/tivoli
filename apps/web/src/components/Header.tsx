import { Link } from "react-router-dom";

const BRAND_LETTERS = [
  ["T", "red"],
  ["i", "blue"],
  ["v", "red"],
  ["o", "yellow"],
  ["l", "red"],
  ["i", "blue"],
] as const;

type HeaderUser = { name: string; balance: number };

export default function Header({ user }: { user?: HeaderUser | null }) {
  return (
    <header className="header">
      <Link to="/" className="brand-name">
        {BRAND_LETTERS.map(([letter, color], i) => (
          <span key={i} className={`col-diff-${color}`}>
            {letter}
          </span>
        ))}
      </Link>
      <nav className="nav">
        {user ? (
          <div className="auth-pill">
            <span className="avatar">{user.name[0].toUpperCase()}</span>
            <span className="auth-text">{user.name}</span>
            <span className="auth-divider">·</span>
            <span className="auth-balance">€{user.balance.toFixed(2)}</span>
          </div>
        ) : (
          <Link to="/login" className="auth-pill">
            <span className="avatar">G</span>
            <span className="auth-text">Guest</span>
            <span className="auth-divider">·</span>
            <span className="auth-link">Log in</span>
          </Link>
        )}
      </nav>
    </header>
  );
}
