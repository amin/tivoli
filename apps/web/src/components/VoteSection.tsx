import { useState, useEffect } from "react";
import { apiUrl } from "../lib/api";

type Amusement = {
  id: number;
  name: string;
  type: 'game' | 'attraction';
};

type Props = {
  accessKey: string;
  userId: number;
};

export default function VoteSection({ accessKey, userId }: Props) {
  const [amusements,  setAmusements]  = useState<Amusement[]>([]);
  const [voteChoice,  setVoteChoice]  = useState('');
  const [voteLoading, setVoteLoading] = useState(false);
  const [voteResult,  setVoteResult]  = useState<string | null>(null);
  const [voteOk,      setVoteOk]      = useState(false);
  const [hasVoted,    setHasVoted]    = useState(
    () => !!localStorage.getItem(`tivoliVoted_${userId}`)
  );

  useEffect(() => {
    fetch(apiUrl('/amusements'), {
      headers: { 'X-Access-Key': accessKey, Accept: 'application/json' },
    })
      .then(r => r.ok ? r.json() : null)
      .then(data => { if (data) setAmusements(data.data ?? []); })
      .catch(() => {});
  }, [accessKey]);

  async function handleVote() {
    if (!voteChoice) return;
    setVoteLoading(true);
    setVoteResult(null);
    try {
      const res = await fetch(apiUrl('/votes'), {
        method: 'POST',
        headers: {
          'X-Access-Key': accessKey,
          Accept: 'application/json',
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ amusement_id: Number(voteChoice) }),
      });
      const data = await res.json();
      if (res.ok) {
        setVoteOk(true);
        setVoteResult('Your vote has been recorded!');
        setHasVoted(true);
        localStorage.setItem(`tivoliVoted_${userId}`, '1');
      } else {
        setVoteOk(false);
        setVoteResult(data.error ?? data.message ?? 'Could not submit vote.');
      }
    } catch {
      setVoteOk(false);
      setVoteResult('Network error.');
    } finally {
      setVoteLoading(false);
    }
  }

  return (
    <section className="section vote-section">
      <h2>Vote for Best Amusement</h2>
      <p className="section-sub vote-warning">You can only vote once. Choose carefully!</p>

      {hasVoted || voteOk ? (
        <div className="exchange-result exchange-result--ok">
          {voteResult ?? 'You have already cast your vote. Thank you!'}
        </div>
      ) : (
        <>
          {voteResult && (
            <div className="exchange-result exchange-result--err">{voteResult}</div>
          )}
          <div className="vote-form">
            <select
              className="vote-select"
              value={voteChoice}
              onChange={e => setVoteChoice(e.target.value)}
              disabled={voteLoading}
            >
              <option value="">Select an amusement</option>
              {(['attraction', 'game'] as const).map(type => {
                const group = amusements.filter(a => a.type === type);
                if (!group.length) return null;
                return (
                  <optgroup key={type} label={type === 'attraction' ? 'Attractions' : 'Games'}>
                    {group.map(a => (
                      <option key={a.id} value={a.id}>{a.name}</option>
                    ))}
                  </optgroup>
                );
              })}
            </select>
            <button
              className="btn vote-btn"
              onClick={handleVote}
              disabled={!voteChoice || voteLoading}
            >
              {voteLoading ? '…' : 'Submit Vote'}
            </button>
          </div>
        </>
      )}
    </section>
  );
}
