import { useState } from "react";
import { Link, useNavigate } from "react-router-dom";
import logoImg from "../assets/loopland_transparent.svg";
import Header from "../components/Header";
import Footer from "../components/Footer";
import AmusementCard from "../components/AmusementCard";
import HowItWorksModal from "../components/HowItWorksModal";
import GuestWarningModal from "../components/GuestWarningModal";
import IframeModal from "../components/IframeModal";
import { useAmusements, type AmusementItem } from "../hooks/useAmusements";
import { apiFetch } from "../lib/api";
import { useAuth } from "../auth/AuthContext";
import { useGuestAvailable } from "../hooks/useGuestAvailable";

type Filter = "all" | "game" | "attraction";

export default function Home() {
  const { user, refresh } = useAuth();
  const { amusements } = useAmusements();
  const [filter, setFilter] = useState<Filter>("all");
  const [showHiw, setShowHiw] = useState(false);
  const [guestPending, setGuestPending] = useState<AmusementItem | null>(null);
  const [iframe, setIframe] = useState<{ url: string; } | null>(null);
  const navigate = useNavigate();
  const { available: guestAvailable } = useGuestAvailable();

  async function openAmusement(e: React.MouseEvent, a: AmusementItem) {
    e.preventDefault();
    if (!user) {
      if (guestAvailable === false) {
        navigate("/login");
      } else {
        setGuestPending(a);
      }
      return;
    }
    try {
      const res = await apiFetch("/identity-tokens", { method: "POST" });
      if (!res.ok) {
        navigate("/login");
        return;
      }
      const body = await res.json();
      const url = new URL(a.url);
      url.searchParams.set("identity_token", body.identity_token);
      setIframe({ url: url.toString() });
    } catch {
      // Network error — silently no-op; user can retry
    }
  }

  const gameSettled = amusements.length > 0 && amusements.every((a) => a.settled_at !== null);
  const visible = filter === "all" ? amusements : amusements.filter((a) => a.type === filter);

  return (
    <>
      <Header />

      {gameSettled && (
        <div className="game-ended-banner" role="alert">
          Loopland is closed, you can no longer play.
        </div>
      )}

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
            Attractions and games, all in one place. Grab a ticket and
            explore.
          </p>
          <div className="hero-ctas">
            {user ? (
              <Link to="/user" className="btn btn-primary">
                My Account
              </Link>
            ) : (
              <Link to="/login" className="btn btn-primary">
                Enter the park
              </Link>
            )}
            <button className="btn btn-secondary" onClick={() => setShowHiw(true)}>
              How it works
            </button>
          </div>
        </div>
      </section>

      <main className="section">
        <div className="section-head">
          <h2>Attractions & Games</h2>
        </div>
        <p className="section-sub">All the fun in one place.</p>

        <div className="filters">
          <button className={`chip${filter === "all" ? " active" : ""}`} aria-pressed={filter === "all"} onClick={() => setFilter("all")}>All</button>
          <button className={`chip${filter === "game" ? " active" : ""}`} aria-pressed={filter === "game"} onClick={() => setFilter("game")}>Games</button>
          <button className={`chip${filter === "attraction" ? " active" : ""}`} aria-pressed={filter === "attraction"} onClick={() => setFilter("attraction")}>Attractions</button>
        </div>

        {visible.length === 0 ? (
          <div className="empty-state">
            <span className="empty-icon" aria-hidden="true">🎪</span>
            <p className="empty-title">Coming soon</p>
            <p className="empty-sub">
              Attractions are being built. <br />
              Check back soon!
            </p>
          </div>
        ) : (
          <div className="grid">
            {visible.map((a) => (
              <AmusementCard
                key={a.id}
                amusement={a}
                showImage
                onCardClick={openAmusement}
              />
            ))}
          </div>
        )}
      </main>

      <Footer />

      {showHiw && <HowItWorksModal onClose={() => setShowHiw(false)} />}

      {guestPending && (
        <GuestWarningModal
          amusement={guestPending}
          onClose={() => setGuestPending(null)}
          onContinueAsGuest={async () => {
            const amusement = guestPending;
            setGuestPending(null);
            try {
              const loginRes = await apiFetch("/login/guest", { method: "POST" });
              if (!loginRes.ok) return;
              await refresh();
              const tokenRes = await apiFetch("/identity-tokens", { method: "POST" });
              if (!tokenRes.ok) return;
              const { identity_token } = await tokenRes.json();
              const url = new URL(amusement.url);
              url.searchParams.set("identity_token", identity_token);
              setIframe({ url: url.toString() });
            } catch {
              // silently no-op; user can retry
            }
          }}
        />
      )}
      
      {iframe && (
        <IframeModal
          url={iframe.url}
          onClose={() => setIframe(null)}
        />
      )}
    </>
  );
}
