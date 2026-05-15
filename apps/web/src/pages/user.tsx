import { useState, useEffect, useCallback } from "react";
import { useNavigate } from "react-router-dom";
import "./user.css";

import Header from "../components/Header";
import Footer from "../components/Footer";
import VoteSection from "../components/VoteSection";
import Amusement from "../components/Amusement";
import { apiUrl, apiFetch } from "../lib/api";
import { useAuth } from "../auth/AuthContext";

type Animal = 'lion' | 'dolphin' | 'toucan' | 'beetlebug' | 'snake';
type Metal  = 'silver' | 'gold' | 'platinum';

type Stamp = {
  id: number;
  animal: Animal;
  metal: Metal | null;
  source_amusement_id: number;
  created_at: string;
};

type ExchangeOption = {
  stampIds: number[];
  label: string;
  description: string;
  amount: number;
};

const ALL_ANIMALS: Animal[] = ['lion', 'dolphin', 'toucan', 'beetlebug', 'snake'];

const METAL_COLOR: Record<Metal, string> = {
  silver:   'var(--text-muted)',
  gold:     'var(--c-yellow-dark)',
  platinum: 'var(--c-blue)',
};

function stampImagePath(s: Stamp): string {
  const file = s.metal ? `${s.metal}-${s.animal}.svg` : `${s.animal}.svg`;
  return apiUrl(`/images/stamps/${file}`);
}

function capitalize(str: string): string {
  return str.charAt(0).toUpperCase() + str.slice(1);
}

function stampLabel(s: Stamp): string {
  return s.metal ? `${capitalize(s.metal)} ${capitalize(s.animal)}` : capitalize(s.animal);
}

function detectExchangeOptions(stamps: Stamp[]): ExchangeOption[] {
  const options: ExchangeOption[] = [];

  // Metal set: one silver + one gold + one platinum
  const silver   = stamps.find(s => s.metal === 'silver');
  const gold     = stamps.find(s => s.metal === 'gold');
  const platinum = stamps.find(s => s.metal === 'platinum');

  if (silver && gold && platinum) {
    options.push({
      stampIds: [silver.id, gold.id, platinum.id],
      label: 'Metal Set',
      description: 'Silver + Gold + Platinum',
      amount: 10,
    });
  }

  // Animal set: one of each animal
  const animalStamps = ALL_ANIMALS.map(a => stamps.find(s => s.animal === a));
  if (animalStamps.every(Boolean)) {
    options.push({
      stampIds: animalStamps.map(s => s!.id),
      label: 'Animal Set',
      description: 'All 5 animals',
      amount: 7,
    });
  }

  // Non-metal set: 3 distinct animals with no metal
  const nonMetal = stamps.filter(s => s.metal === null);
  const seen = new Set<string>();
  const picked: Stamp[] = [];

  for (const s of nonMetal) {
    if (!seen.has(s.animal) && picked.length < 3) {
      seen.add(s.animal);
      picked.push(s);
    }
  }
  if (picked.length === 3) {
    options.push({
      stampIds: picked.map(s => s.id),
      label: 'Non-Metal Set',
      description: '3 distinct animals, no metal',
      amount: 3,
    });
  }

  return options;
}

// VP formula from the leaderboard spec (metal-first greedy partition)
function calcVP(stamps: Stamp[]): number {
  const metalCount: Record<Metal, number> = { silver: 0, gold: 0, platinum: 0 };
  for (const s of stamps) if (s.metal) metalCount[s.metal]++;

  const metalSets = Math.min(metalCount.silver, metalCount.gold, metalCount.platinum);

  const usedInMetal = new Set<number>();
  for (const metal of ['silver', 'gold', 'platinum'] as Metal[]) {
    let need = metalSets;
    for (const s of stamps) {
      if (!need) break;
      if (s.metal === metal && !usedInMetal.has(s.id)) { usedInMetal.add(s.id); need--; }
    }
  }

  const afterMetal = stamps.filter(s => !usedInMetal.has(s.id));
  const remAnimal: Record<Animal, number> = { lion: 0, dolphin: 0, toucan: 0, beetlebug: 0, snake: 0 };
  for (const s of afterMetal) remAnimal[s.animal]++;

  const animalSets = Math.min(...ALL_ANIMALS.map(a => remAnimal[a]));
  const loose = afterMetal.length - animalSets * 5;
  return 40 * metalSets + 25 * animalSets + (loose * (loose + 1)) / 2;
}

export default function User() {
  const navigate = useNavigate();
  const { user, refresh, logout } = useAuth();

  const [stamps, setStamps] = useState<Stamp[]>([]);
  const [stampsLoading, setStampsLoading] = useState(false);

  const [view, setView] = useState<'stamps' | 'amusements'>('stamps');

  const [exchangeLoading, setExchangeLoading] = useState(false);
  const [exchangeResult,  setExchangeResult]  = useState<string | null>(null);
  const [exchangeOk,      setExchangeOk]      = useState(false);

  const fetchStamps = useCallback(async () => {
    setStampsLoading(true);
    try {
      const stampsRes = await apiFetch('/stamps');
      const stampsData = await stampsRes.json();
      setStamps(stampsData.data ?? []);
    } catch {
      navigate('/error?message=Network+error+—+could+not+reach+the+server.');
    } finally {
      setStampsLoading(false);
    }
  }, [navigate]);

  useEffect(() => {
    if (user) fetchStamps();
  }, [user, fetchStamps]);

  async function doExchange(stampIds: number[], label: string) {
    setExchangeLoading(true);
    setExchangeResult(null);
    try {
      const res = await apiFetch('/exchanges', {
        method: 'POST',
        body: JSON.stringify({ stamp_ids: stampIds }),
      });

      const data = await res.json();
      if (res.ok) {
        if (data.stamps_consumed === 0) {
          setExchangeOk(false);
          setExchangeResult('No complete sets found — keep collecting!');
        } else {
          setExchangeOk(true);
          setExchangeResult(`Earned €${data.amount.toFixed(2)} for your ${label}!`);
        }
        await refresh();
        if (user) await fetchStamps();
      } else {
        setExchangeOk(false);
        setExchangeResult(data.message ?? 'Exchange failed.');
      }
    } catch {
      setExchangeOk(false);
      setExchangeResult('Network error.');
    } finally {
      setExchangeLoading(false);
    }
  }

  function handleExchange(option: ExchangeOption) {
    doExchange(option.stampIds, option.label);
  }

  function handleExchangeAll() {
    doExchange(stamps.map(s => s.id), 'All Stamps');
  }

  async function handleLogout() {
    await logout();
    navigate('/login');
  }

  const exchangeOptions = detectExchangeOptions(stamps);
  const vp = calcVP(stamps);

  // ProtectedRoute guarantees user is non-null here, but keep a guard
  // in case the context becomes stale during navigation.
  if (!user) {
    return (
      <>
        <Header />
        <section className="user-hero">
          <p className="user-hero-sub">{stampsLoading ? 'Loading…' : ''}</p>
        </section>
        <Footer />
      </>
    );
  }

  return (
    <>
      <Header />

      <section className="user-hero">
        <div className="user-hero-info">
          <div className="user-hero-name-column">
            <h1 className="user-hero-name">{user.name}</h1>
            {user.group && (
              <p className="user-hero-sub">{user.group.name} · {user.group.member_count} members</p>
            )}
          </div>
          <div className="user-hero-vp-column">
            <h3 className="vp-text">Victory points</h3>
            <span className="user-vp-chip">{vp} VP</span>
          </div>
        </div>
      </section>

      <nav className="view-tabs">
        <button
          className={`view-tab${view === 'stamps' ? ' active' : ''}`}
          onClick={() => setView('stamps')}
        >
          My Stamps
        </button>
        <button
          className={`view-tab${view === 'amusements' ? ' active' : ''}`}
          onClick={() => setView('amusements')}
        >
          My Amusements
        </button>
      </nav>

      {view === 'amusements' && <Amusement />}

      {view === 'stamps' && (exchangeOptions.length > 0 || exchangeResult) && (
        <section className="section exchange-section">
          <div className="section-head">
            <h2>Exchange Stamps</h2>
            <button
              className="btn sell-btn"
              onClick={handleExchangeAll}
              disabled={exchangeLoading || stamps.length === 0}
            >
              {exchangeLoading ? '…' : 'Sell All'}
            </button>
          </div>
          <p className="section-sub">
            You have complete sets ready to cash in.
          </p>

          {exchangeResult && (
            <div className={`exchange-result ${exchangeOk ? 'exchange-result--ok' : 'exchange-result--err'}`}>
              {exchangeResult}
            </div>
          )}

          <div className="exchange-grid">
            {exchangeOptions.map(opt => (
              <div key={opt.label} className="exchange-card">
                <div className="exchange-card-info">
                  <span className="exchange-value">€{opt.amount}.00</span>
                  <p className="exchange-card-title">{opt.label}</p>
                  <p className="exchange-card-desc">{opt.description}</p>
                </div>
                <div className="exchange-card-right">
                  <button
                    className="btn sell-btn"
                    onClick={() => handleExchange(opt)}
                    disabled={exchangeLoading}
                  >
                    {exchangeLoading ? '…' : 'Exchange'}
                  </button>
                </div>
              </div>
            ))}
          </div>
        </section>
      )}

      {view === 'stamps' && exchangeResult && exchangeOptions.length === 0 && (
        <section className="section" style={{ paddingBottom: 8 }}>
          <div className={`exchange-result ${exchangeOk ? 'exchange-result--ok' : 'exchange-result--err'}`}>
            {exchangeResult}
          </div>
        </section>
      )}

      {view === 'stamps' && (
        <>
          <main className="section">
            <div className="section-head">
              <h2>My Stamps</h2>
              <span className="section-count">{stamps.length} collected · {vp} VP</span>
            </div>
            <p className="section-sub">Collect stamps from rides and games, then exchange complete sets for credits.</p>

            {stampsLoading ? (
              <div className="empty-state">
                <span className="empty-icon">⏳</span>
                <p className="empty-title">Loading…</p>
              </div>
            ) : stamps.length === 0 ? (
              <div className="empty-state">
                <span className="empty-icon">🎟️</span>
                <p className="empty-title">No stamps yet</p>
                <p className="empty-sub">Visit attractions and play games to earn stamps!</p>
              </div>
            ) : (
              <div className="grid stamp-grid">
                {stamps.map(stamp => (
                  <div key={stamp.id} className="card">
                    <div className="card-illustration stamp-illustration">
                      <img
                        src={stampImagePath(stamp)}
                        alt={stampLabel(stamp)}
                        className="stamp-img"
                        onError={e => { (e.currentTarget as HTMLImageElement).style.display = 'none'; }}
                      />
                    </div>
                    <div className="card-body">
                      <p className="card-title">{stampLabel(stamp)}</p>
                      {stamp.metal ? (
                        <p className="card-tag" style={{ color: METAL_COLOR[stamp.metal] }}>
                          {capitalize(stamp.metal)}
                        </p>
                      ) : (
                        <p className="card-tag">Common</p>
                      )}
                    </div>
                  </div>
                ))}
              </div>
            )}
          </main>

          <VoteSection userId={user.id} />
        </>
      )}

      <div className="user-logout-wrap">
        <button className="logout-btn" onClick={handleLogout}>Log out</button>
      </div>

      <Footer />
    </>
  );
}
