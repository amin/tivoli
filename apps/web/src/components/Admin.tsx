import { useEffect, useRef, useState } from "react";
import { apiUrl } from "../lib/api";

type Props = {
  accessKey: string;
};

type MoneyLeader = { name: string; group: string | null; balance: number };
type VpLeader    = { name: string; group: string | null; total_vp: number };
type VoteWinner  = { name: string; group: string | null; votes: number };

type Leaderboard = {
  money_leaders: MoneyLeader[];
  vp_leaders:    VpLeader[];
  vote_winners:  VoteWinner[];
};

export default function Admin({ accessKey }: Props) {
  const [loading, setLoading] = useState(false);
  const [scoreboard, setScoreboard] = useState<Leaderboard | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [confirmReset, setConfirmReset] = useState(false);
  const [resetting, setResetting] = useState(false);
  const [resetDone, setResetDone] = useState(false);
  const modalRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!scoreboard) return;
    const modal = modalRef.current;
    if (modal) { modal.focus(); modal.scrollTop = 0; }

    function onKey(e: KeyboardEvent) {
      if (e.key === 'Escape') setScoreboard(null);
    }
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [scoreboard]);

  async function handleReset() {
    setResetting(true);
    setResetDone(false);
    try {
      const res = await fetch(apiUrl('/reset'), {
        method: 'POST',
        headers: { 'X-Access-Key': accessKey, Accept: 'application/json' },
      });
      if (res.ok) {
        setResetDone(true);
        setConfirmReset(false);
      } else {
        const data = await res.json().catch(() => ({}));
        setError((data as { message?: string }).message ?? 'Reset failed.');
        setConfirmReset(false);
      }
    } catch {
      setError('Network error.');
      setConfirmReset(false);
    } finally {
      setResetting(false);
    }
  }

  async function handleEndGame() {
    setLoading(true);
    setError(null);
    try {
      const res = await fetch(apiUrl('/leaderboard'), {
        headers: { 'X-Access-Key': accessKey, Accept: 'application/json' },
      });
      if (!res.ok) {
        const data = await res.json().catch(() => ({}));
        setError((data as { message?: string }).message ?? 'Failed to load scoreboard.');
        return;
      }
      setScoreboard(await res.json());
    } catch {
      setError('Network error.');
    } finally {
      setLoading(false);
    }
  }

  return (
    <>
      <section className="section">
        <div className="section-head">
          <h2>Centralbank Admin</h2>
        </div>
        <p className="section-sub">Welcome to the centralbank dashboard.</p>

        <div className="admin-actions">
          <button
            className="btn btn-end-game"
            onClick={handleEndGame}
            disabled={loading}
          >
            {loading ? 'Counting results…' : 'End game'}
          </button>

          {!confirmReset ? (
            <button
              className="btn btn-reset"
              onClick={() => { setConfirmReset(true); setResetDone(false); setError(null); }}
              disabled={resetting}
            >
              Reset game
            </button>
          ) : (
            <div className="reset-confirm">
              <p className="reset-confirm-text">This wipes all stamps, votes and resets everyone's balance to €25. Continue?</p>
              <div className="reset-confirm-actions">
                <button className="btn btn-secondary" onClick={() => setConfirmReset(false)} disabled={resetting}>
                  Cancel
                </button>
                <button className="btn btn-reset" onClick={handleReset} disabled={resetting}>
                  {resetting ? 'Resetting…' : 'Yes, reset'}
                </button>
              </div>
            </div>
          )}

          {resetDone && <p className="reset-success">Game reset. Everyone starts fresh with €25.</p>}
          {error && <p className="form-error">{error}</p>}
        </div>
      </section>

      {scoreboard && (
        <div className="modal-overlay" onClick={() => setScoreboard(null)}>
          <div
            ref={modalRef}
            className="modal scoreboard-modal"
            role="dialog"
            aria-modal="true"
            aria-label="Final Scoreboard"
            tabIndex={-1}
            onClick={e => e.stopPropagation()}
          >
            <h2 className="modal-title">Scoreboard</h2>
            <p className="modal-sub">Final results for today</p>

            <div className="scoreboard-category">
              <h3 className="scoreboard-cat-title">Most money</h3>
              <ol className="scoreboard-list">
                {scoreboard.money_leaders.map((p, i) => (
                  <li key={p.name} className={`scoreboard-row${i === 0 ? ' scoreboard-row--first' : ''}`}>
                    <span className="scoreboard-rank">{i + 1}</span>
                    <span className="scoreboard-name">
                      {p.name}
                      {p.group && <span className="scoreboard-group">{p.group}</span>}
                    </span>
                    <span className="scoreboard-value">€{p.balance.toFixed(2)}</span>
                  </li>
                ))}
              </ol>
            </div>

            <div className="scoreboard-category">
              <h3 className="scoreboard-cat-title">Nicest amusement</h3>
              <ol className="scoreboard-list">
                {scoreboard.vote_winners.length === 0 ? (
                  <li className="scoreboard-empty">No votes cast yet.</li>
                ) : scoreboard.vote_winners.map((a, i) => (
                  <li key={a.name} className={`scoreboard-row${i === 0 ? ' scoreboard-row--first' : ''}`}>
                    <span className="scoreboard-rank">{i + 1}</span>
                    <span className="scoreboard-name">
                      {a.name}
                      {a.group && <span className="scoreboard-group">{a.group}</span>}
                    </span>
                    <span className="scoreboard-value">{a.votes} {a.votes === 1 ? 'vote' : 'votes'}</span>
                  </li>
                ))}
              </ol>
            </div>

            <div className="scoreboard-category">
              <h3 className="scoreboard-cat-title">Most VP</h3>
              <ol className="scoreboard-list">
                {scoreboard.vp_leaders.map((p, i) => (
                  <li key={p.name} className={`scoreboard-row${i === 0 ? ' scoreboard-row--first' : ''}`}>
                    <span className="scoreboard-rank">{i + 1}</span>
                    <span className="scoreboard-name">
                      {p.name}
                      {p.group && <span className="scoreboard-group">{p.group}</span>}
                    </span>
                    <span className="scoreboard-value">{p.total_vp} VP</span>
                  </li>
                ))}
              </ol>
            </div>

            <div className="modal-actions">
              <button className="btn btn-secondary" onClick={() => setScoreboard(null)}>
                Close
              </button>
            </div>
          </div>
        </div>
      )}
    </>
  );
}
