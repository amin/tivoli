import { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import logoImg from "../assets/logo_transparent.svg";

const BRAND_LETTERS = [
  ["T", "red"],
  ["i", "blue"],
  ["v", "red"],
  ["o", "yellow"],
  ["l", "red"],
  ["i", "blue"],
] as const;

type Amusement = {
  id: number;
  name: string;
  description: string | null;
  type: "game" | "attraction";
  url: string;
};

type Filter = "all" | "game" | "attraction";

export default function Home() {
  const [amusements, setAmusements] = useState<Amusement[]>([]);
  const [filter, setFilter] = useState<Filter>("all");

  useEffect(() => {
    const accessKey = localStorage.getItem("access_key") ?? "";
    fetch("/api/amusements", {
      headers: { "X-Access-Key": accessKey, Accept: "application/json" },
    })
      .then((r) => r.ok ? r.json() : Promise.reject())
      .then((body) => setAmusements(body.data ?? []))
      .catch(() => {});
  }, []);

  const visible = filter === "all" ? amusements : amusements.filter((a) => a.type === filter);

  return (
    <>
      <header className="header">
        <Link to="/" className="brand-name">
          {BRAND_LETTERS.map(([letter, color], i) => (
            <span key={i} className={`col-diff-${color}`}>
              {letter}
            </span>
          ))}
        </Link>
        <nav className="nav">
          <Link to="/login" className="auth-pill">
            <span className="avatar">G</span>
            <span className="auth-text">Guest</span>
            <span className="auth-divider">·</span>
            <span className="auth-link">Log in</span>
          </Link>
        </nav>
      </header>

      <section className="hero">
        <div className="hero-img-wrap">
          <img src={logoImg} alt="Tivoli park" className="hero-img" />
        </div>
        <div className="hero-content">
          <h1>
            Your digital
            <br />
            <span className="pixel">playground</span>
          </h1>
          <p>
            Games, rides, and attractions - all in one place. Grab a ticket and
            explore.
          </p>
          <div className="hero-ctas">
            <Link to="/login" className="btn btn-primary">
              Enter the park
            </Link>
            <Link to="/how-it-works" className="btn btn-secondary">
              How it works
            </Link>
          </div>
        </div>
      </section>

      <main className="section">
        <div className="section-head">
          <h2>Attractions & Games</h2>
        </div>
        <p className="section-sub">All the fun in one place.</p>

        <div className="filters">
          <button className={`chip${filter === "all" ? " active" : ""}`} onClick={() => setFilter("all")}>All</button>
          <button className={`chip${filter === "game" ? " active" : ""}`} onClick={() => setFilter("game")}>Games</button>
          <button className={`chip${filter === "attraction" ? " active" : ""}`} onClick={() => setFilter("attraction")}>Rides</button>
        </div>

        {visible.length === 0 ? (
          <div className="empty-state">
            <span className="empty-icon">🎪</span>
            <p className="empty-title">Coming soon</p>
            <p className="empty-sub">
              Attractions are being built. <br />
              Check back soon!
            </p>
          </div>
        ) : (
          <div className="grid">
            {visible.map((a) => (
              <a key={a.id} className="card" href={a.url} target="_blank" rel="noreferrer">
                <div className="card-illustration" />
                <div className="card-body">
                  <p className="card-title">{a.name}</p>
                  <p className="card-tag">{a.type}</p>
                </div>
              </a>
            ))}
          </div>
        )}
      </main>

      <div className="lights">
        {Array.from({ length: 9 }).map((_, i) => (
          <span key={i} className="light" />
        ))}
      </div>

      <footer className="footer">
        <span className="footer-left">© {new Date().getFullYear()} Tivoli</span>
        <nav className="footer-links">
          <a href="#">About</a>
          <a href="#">Contact</a>
        </nav>
      </footer>
    </>
  );
}
