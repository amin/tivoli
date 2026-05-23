import { useEffect, useRef } from "react";
import { Link } from "react-router-dom";
import { useAuth } from "../auth/AuthContext";

const BRAND_LETTERS = [
  ["L", "red"],
  ["o", "blue"],
  ["o", "red"],
  ["p", "yellow"],
  ["l", "red"],
  ["a", "blue"],
  ["n", "red"],
  ["d", "yellow"],
] as const;

export default function Header() {
  const { user } = useAuth();
  const headerRef = useRef<HTMLElement>(null);

  useEffect(() => {
    const el = headerRef.current;
    if (!el) return;
    const ro = new ResizeObserver(() => {
      document.documentElement.style.setProperty("--header-h", `${el.getBoundingClientRect().height}px`);
    });
    ro.observe(el);
    return () => ro.disconnect();
  }, []);

  return (
    <header ref={headerRef} className="header">
      <Link to="/" className="brand-name">
        {BRAND_LETTERS.map(([letter, color], i) => (
          <span key={i} className={`col-diff-${color}`}>
            {letter}
          </span>
        ))}
      </Link>
      <nav className="nav">
        {user ? (
          <Link to="/user" className="auth-pill">
            <span className="avatar">{user.name[0].toUpperCase()}</span>
            <span className="auth-text">{user.name}</span>
            <span className="auth-divider">·</span>
            <span className="auth-balance">€{user.balance.toFixed(2)}</span>
          </Link>
        ) : (
          <Link to="/login" className="auth-pill">
            <span className="avatar">G</span>
            <span className="auth-text">Guest</span>
            <span className="auth-link">Log in / Activate</span>
          </Link>
        )}
      </nav>
    </header>
  );
}
