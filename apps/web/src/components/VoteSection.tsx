import { useState, useEffect } from "react";
import { apiFetch } from "../lib/api";
import CustomSelect from "./CustomSelect";

type Amusement = {
  id: number;
  name: string;
  type: 'game' | 'attraction';
  image_url: string | null;
};

type Props = {
  userId: number;
};

export default function VoteSection({ userId }: Props) {
  const [amusements,  setAmusements]  = useState<Amusement[]>([]);
  const [voteChoice,  setVoteChoice]  = useState('');
  const [voteLoading, setVoteLoading] = useState(false);
  const [voteResult,  setVoteResult]  = useState<string | null>(null);
  const [voteOk,      setVoteOk]      = useState(false);
  const [hasVoted,    setHasVoted]    = useState(
    () => !!localStorage.getItem(`tivoliVoted_${userId}`)
  );

  useEffect(() => {
    apiFetch('/amusements')
      .then(r => r.ok ? r.json() : null)
      .then(data => { if (data) setAmusements(data.data ?? []); })
      .catch(() => {});
  }, []);

  async function handleVote() {
    if (!voteChoice) return;
    setVoteLoading(true);
    setVoteResult(null);
    try {
      const res = await apiFetch('/votes', {
        method: 'POST',
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
            <CustomSelect
              value={voteChoice}
              onChange={setVoteChoice}
              disabled={voteLoading}
              placeholder="Select an amusement"
              groups={(['attraction', 'game'] as const)
                .map(type => ({
                  label: type === 'attraction' ? 'Attractions' : 'Games',
                  options: amusements
                    .filter(a => a.type === type)
                    .map(a => ({ value: String(a.id), label: a.name, image: a.image_url ?? undefined })),
                }))
                .filter(g => g.options.length > 0)
              }
            />
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
