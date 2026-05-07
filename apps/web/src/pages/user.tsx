import { useState, useEffect, useCallback } from "react";
import { Link } from "react-router-dom";
import "./user.css";

const BRAND_LETTERS = [
  ['T', 'red'],
  ['i', 'blue'],
  ['v', 'red'],
  ['o', 'yellow'],
  ['l', 'red'],
  ['i', 'blue'],
] as const;

type Animal = 'lion' | 'dolphin' | 'toucan' | 'beetlebug' | 'snake';
type Metal  = 'silver' | 'gold' | 'platinum';
type SetType = 'metal' | 'animal' | 'non_metal';

type StampType = {
  id: number;
  animal: Animal;
  metal: Metal | null;
  image_url: string;
};

type Stamp = {
  id: number;
  user_id: number;
  stamptype: StampType;
  image_url: string;
  created_at: string;
};

type UserProfile = {
  id: number;
  name: string;
  balance: number;
  github_link: string | null;
  website_link: string | null;
};

type ExchangeOption = {
  type: SetType;
  amount: number;
  stampIds: number[];
  label: string;
  description: string;
};

const ALL_ANIMALS: Animal[] = ['lion', 'dolphin', 'toucan', 'beetlebug', 'snake'];

const METAL_COLOR: Record<Metal, string> = {
  silver: 'var(--text-muted)',
  gold:   'var(--c-yellow-dark)',
  platinum: 'var(--c-blue)',
};

function stampImagePath(st: StampType): string {
  const file = st.metal ? `${st.metal}-${st.animal}.svg` : `${st.animal}.svg`;
  return `/images/stamps/${file}`;
}

function stampLabel(s: Stamp): string {
  const animal = s.stamptype.animal.charAt(0).toUpperCase() + s.stamptype.animal.slice(1);
  if (!s.stamptype.metal) return animal;
  const metal = s.stamptype.metal.charAt(0).toUpperCase() + s.stamptype.metal.slice(1);
  return `${metal} ${animal}`;
}

function detectExchangeOptions(stamps: Stamp[]): ExchangeOption[] {
  const options: ExchangeOption[] = [];

  // Metal set: one silver + one gold + one platinum
  const silver   = stamps.find(s => s.stamptype.metal === 'silver');
  const gold     = stamps.find(s => s.stamptype.metal === 'gold');
  const platinum = stamps.find(s => s.stamptype.metal === 'platinum');
  if (silver && gold && platinum) {
    options.push({
      type: 'metal', amount: 10,
      stampIds: [silver.id, gold.id, platinum.id],
      label: 'Metal Set',
      description: 'Silver + Gold + Platinum',
    });
  }

  // Animal set: one of each animal
  const animalStamps = ALL_ANIMALS.map(a => stamps.find(s => s.stamptype.animal === a));
  if (animalStamps.every(Boolean)) {
    options.push({
      type: 'animal', amount: 7,
      stampIds: animalStamps.map(s => s!.id),
      label: 'Animal Set',
      description: 'All 5 animals',
    });
  }

  // Non-metal set: 3 distinct animals with no metal
  const nonMetal = stamps.filter(s => s.stamptype.metal === null);
  const seen = new Set<string>();
  const picked: Stamp[] = [];
  for (const s of nonMetal) {
    if (!seen.has(s.stamptype.animal) && picked.length < 3) {
      seen.add(s.stamptype.animal);
      picked.push(s);
    }
  }
  if (picked.length === 3) {
    options.push({
      type: 'non_metal', amount: 3,
      stampIds: picked.map(s => s.id),
      label: 'Non-Metal Set',
      description: '3 distinct animals, no metal',
    });
  }

  return options;
}

const LS_KEY = 'tivoliAccessKey';

const PREVIEW_USER: UserProfile = {
  id: 0, name: 'Nathalie', balance: 42.00,
  github_link: null, website_link: null,
};

const PREVIEW_STAMPS: Stamp[] = [
  { id: 1, user_id: 0, created_at: '', image_url: '', stamptype: { id: 1, animal: 'lion',      metal: 'gold',     image_url: '' } },
  { id: 2, user_id: 0, created_at: '', image_url: '', stamptype: { id: 2, animal: 'dolphin',   metal: 'silver',   image_url: '' } },
  { id: 3, user_id: 0, created_at: '', image_url: '', stamptype: { id: 3, animal: 'toucan',    metal: 'platinum', image_url: '' } },
  { id: 4, user_id: 0, created_at: '', image_url: '', stamptype: { id: 4, animal: 'beetlebug', metal: null,       image_url: '' } },
  { id: 5, user_id: 0, created_at: '', image_url: '', stamptype: { id: 5, animal: 'snake',     metal: null,       image_url: '' } },
  { id: 6, user_id: 0, created_at: '', image_url: '', stamptype: { id: 6, animal: 'lion',      metal: null,       image_url: '' } },
];

const isPreview = new URLSearchParams(window.location.search).has('preview');

export default function User() {
  const [accessKey, setAccessKey] = useState<string>(() => localStorage.getItem(LS_KEY) ?? '');
  const [keyInput, setKeyInput]   = useState('');
  const [loggedIn, setLoggedIn]   = useState(isPreview);

  const [user,   setUser]   = useState<UserProfile | null>(isPreview ? PREVIEW_USER : null);
  const [stamps, setStamps] = useState<Stamp[]>(isPreview ? PREVIEW_STAMPS : []);
  const [loading, setLoading] = useState(false);
  const [error,   setError]   = useState<string | null>(null);

  const [exchangeLoading, setExchangeLoading] = useState(false);
  const [exchangeResult,  setExchangeResult]  = useState<string | null>(null);

  const fetchData = useCallback(async (key: string) => {
    setLoading(true);
    setError(null);
    try {
      const headers = { 'X-Access-Key': key, Accept: 'application/json' };
      const userRes = await fetch('/api/user', { headers });
      if (!userRes.ok) {
        setError('Invalid access key or session expired.');
        setLoggedIn(false);
        localStorage.removeItem(LS_KEY);
        return;
      }
      const userData: UserProfile = await userRes.json();
      setUser(userData);

      const stampsRes = await fetch(`/api/stamps?user_id=${userData.id}`, {
        headers: { Accept: 'application/json' },
      });
      const stampsData = await stampsRes.json();
      setStamps(stampsData.data ?? []);
      setLoggedIn(true);
    } catch {
      setError('Network error — is the API running?');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    if (accessKey) fetchData(accessKey);
  }, []);

  async function handleLogin(e: React.SyntheticEvent) {
    e.preventDefault();
    const key = keyInput.trim();
    if (!key) return;
    localStorage.setItem(LS_KEY, key);
    setAccessKey(key);
    await fetchData(key);
  }

  async function handleExchange(option: ExchangeOption) {
    if (!user) return;
    setExchangeLoading(true);
    setExchangeResult(null);
    try {
      const res = await fetch('/api/exchanges', {
        method: 'POST',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify({
          user_id:   user.id,
          set_type:  option.type,
          stamp_ids: option.stampIds,
        }),
      });
      const data = await res.json();
      if (res.ok) {
        setExchangeResult(`Earned €${data.amount.toFixed(2)} for your ${option.label}!`);
        await fetchData(accessKey);
      } else {
        setExchangeResult(data.message ?? 'Exchange failed.');
      }
    } catch {
      setExchangeResult('Network error.');
    } finally {
      setExchangeLoading(false);
    }
  }

  function handleLogout() {
    localStorage.removeItem(LS_KEY);
    setAccessKey('');
    setLoggedIn(false);
    setUser(null);
    setStamps([]);
    setExchangeResult(null);
  }

  const exchangeOptions = detectExchangeOptions(stamps);

  return (
    <>
      <header className="header">
        <Link to="/" className="brand-name">
          {BRAND_LETTERS.map(([letter, color], i) => (
            <span key={i} className={`col-diff-${color}`}>{letter}</span>
          ))}
        </Link>
        <nav className="nav">
          {loggedIn && user ? (
            <div className="auth-pill">
              <span className="avatar">{user.name[0].toUpperCase()}</span>
              <span className="auth-text">{user.name}</span>
              <span className="auth-divider">·</span>
              <span className="auth-balance">€{user.balance.toFixed(2)}</span>
            </div>
          ) : (
            <Link to="/activate" className="auth-pill">
              <span className="avatar">G</span>
              <span className="auth-text">Guest</span>
              <span className="auth-divider">·</span>
              <span className="auth-link">Log in / Activate</span>
            </Link>
          )}
        </nav>
      </header>

      {!loggedIn ? (
        <section className="user-login-wrap">
          <div className="modal">
            <p className="modal-title">Your collection</p>
            <p className="modal-sub">Enter your access key to view your stamps and balance.</p>
            <form onSubmit={handleLogin}>
              <div className="field">
                <label className="label" htmlFor="ak">Access key</label>
                <input
                  id="ak"
                  className="input"
                  placeholder="Paste your access key…"
                  value={keyInput}
                  onChange={e => setKeyInput(e.target.value)}
                  required
                />
              </div>
              {error && <p className="form-error">{error}</p>}
              <button className="btn btn-primary" style={{ width: '100%' }} disabled={loading}>
                {loading ? 'Loading…' : 'View my stamps'}
              </button>
            </form>
            <p className="user-login-hint">
              Don't have a key? <Link to="/activate" className="auth-link">Activate your account</Link>
            </p>
          </div>
        </section>
      ) : (
        <>
          <section className="user-hero">
            <div className="user-hero-info">
              <h1 className="user-hero-name">{user!.name}</h1>
            </div>
            <div className="user-balance-card">
              <span className="balance-label">Balance</span>
              <span className="balance-amount">€{user!.balance.toFixed(2)}</span>
            </div>
          </section>

          {exchangeOptions.length > 0 && (
            <section className="section exchange-section">
              <div className="section-head">
                <h2>Exchange Stamps</h2>
              </div>
              <p className="section-sub">You have complete sets ready to cash in.</p>

              {exchangeResult && (
                <div className={`exchange-result ${exchangeResult.startsWith('Earned') ? 'exchange-result--ok' : 'exchange-result--err'}`}>
                  {exchangeResult}
                </div>
              )}

              <div className="exchange-grid">
                {exchangeOptions.map(opt => (
                  <div key={opt.type} className="exchange-card">
                    <div className="exchange-card-info">
                      <p className="exchange-card-title">{opt.label}</p>
                      <p className="exchange-card-desc">{opt.description}</p>
                    </div>
                    <div className="exchange-card-right">
                      <span className="exchange-value">€{opt.amount}.00</span>
                      <button
                        className="sell-btn"
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

          {exchangeResult && exchangeOptions.length === 0 && (
            <section className="section" style={{ paddingBottom: 8 }}>
              <div className={`exchange-result ${exchangeResult.startsWith('Earned') ? 'exchange-result--ok' : 'exchange-result--err'}`}>
                {exchangeResult}
              </div>
            </section>
          )}

          <main className="section">
            <div className="section-head">
              <h2>My Stamps</h2>
              <span className="section-count">{stamps.length} collected</span>
            </div>
            <p className="section-sub">Collect stamps from rides and games, then exchange complete sets for credits.</p>

            {loading ? (
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
                        src={stampImagePath(stamp.stamptype)}
                        alt={stampLabel(stamp)}
                        className="stamp-img"
                        onError={e => { (e.currentTarget as HTMLImageElement).style.display = 'none'; }}
                      />
                    </div>
                    <div className="card-body">
                      <p className="card-title">{stampLabel(stamp)}</p>
                      {stamp.stamptype.metal ? (
                        <p className="card-tag" style={{ color: METAL_COLOR[stamp.stamptype.metal] }}>
                          {stamp.stamptype.metal.charAt(0).toUpperCase() + stamp.stamptype.metal.slice(1)}
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

          <div className="user-logout-wrap">
            <button className="logout-btn" onClick={handleLogout}>Log out</button>
          </div>
        </>
      )}

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
