import { Link } from "react-router-dom";
import { useEffect, useRef, useState } from "react";
import { apiUrl } from "../lib/api";
import CustomSelect from "./CustomSelect";

type AmusementItem = {
    id: number;
    name: string;
    type: string;
    image_url: string | null;
    price: number | null;
    player_payout: number | null;
    url: string;
};

type Props = {
  accessKey: string;
};

type FormData = {
  name: string;
  description: string;
  url: string;
  image_url: string;
  price: string;
  player_payout: string;
  type: 'game' | 'attraction' | '';
  visits: number;
  delete?: string;
};

const EMPTY_FORM: FormData = {
  name: '',
  description: '',
  url: '',
  image_url: '',
  price: '',
  player_payout: '',
  type: '',
  visits: 0,
  delete: '',
};

const isPreview = new URLSearchParams(window.location.search).has('preview');

const PREVIEW_AMUSEMENTS: AmusementItem[] = [
  {
    id: 1,
    name: 'Bumper Cars',
    type: 'attraction',
    image: '',
    fee: '€5.00',
    link: '#',
    visits: 100,
    delete: '#',
  },
  {
    id: 2,
    name: 'Ring Toss',
    type: 'game',
    image: '',
    fee: '€3.00',
    winnings: '€10.00',
    link: '#',
    visits: 200,
    delete: '#',
  },
];

export default function Amusement({ accessKey }: Props) {
  const [amusements, setAmusements] = useState<AmusementItem[]>(
    isPreview ? PREVIEW_AMUSEMENTS : []
  );
  const [loading, setLoading] = useState(false);
  const [showModal, setShowModal] = useState(false);
  const [form, setForm] = useState<FormData>(EMPTY_FORM);
  const [submitting, setSubmitting] = useState(false);
  const [formError, setFormError] = useState<string | null>(null);
  const [deletingId, setDeletingId] = useState<number | null>(null);
  const modalRef = useRef<HTMLDivElement>(null);

  function fetchAmusements() {
    setLoading(true);
    fetch(apiUrl('/amusements'), {
      headers: { 'X-Access-Key': accessKey, Accept: 'application/json' },
    })
      .then(res => res.ok ? res.json() : null)
      .then(data => { if (data) setAmusements(data.data ?? []); })
      .catch(() => {})
      .finally(() => setLoading(false));
  }

  useEffect(() => {
    fetchAmusements();
  }, [accessKey]);

  useEffect(() => {
    if (!showModal) return;
    const modal = modalRef.current;
    modal?.querySelector<HTMLElement>('input:not([disabled]), button:not([disabled])')?.focus();

    function onKey(e: KeyboardEvent) {
      if (e.key === 'Escape') { setShowModal(false); return; }
      if (e.key !== 'Tab' || !modal) return;

      // Don't interfere while a Radix portal (dropdown) is open
      if (document.activeElement?.closest('[data-radix-popper-content-wrapper]')) return;
      const focusable = Array.from(modal.querySelectorAll<HTMLElement>(
        'a[href], button:not([disabled]), input:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
      ));
      if (!focusable.length) return;
      const first = focusable[0];
      const last = focusable[focusable.length - 1];
      if (e.shiftKey && document.activeElement === first) {
        e.preventDefault(); last.focus();
      } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault(); first.focus();
      }
    }

    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [showModal]);

  async function handleDelete(id: number) {
    setDeletingId(id);
    try {
      await fetch(apiUrl(`/amusements/${id}`), {
        method: 'DELETE',
        headers: { 'X-Access-Key': accessKey, Accept: 'application/json' },
      });
      fetchAmusements();
    } catch {
    } finally {
      setDeletingId(null);
    }
  }

  function openModal() {
    setForm(EMPTY_FORM);
    setFormError(null);
    setShowModal(true);
  }

  function handleChange(e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>) {
    const { name, value } = e.target;
    setForm(prev => ({ ...prev, [name]: value }));
  }

  async function handleSubmit(e: React.SyntheticEvent<HTMLFormElement>) {
    e.preventDefault();
    if (!form.type) { setFormError('Please select a type.'); return; }
    setSubmitting(true);
    setFormError(null);
    try {
      const body: Record<string, unknown> = {
        name: form.name,
        description: form.description,
        url: form.url,
        price: parseFloat(form.price),
        type: form.type,
      };
      if (form.image_url) body.image_url = form.image_url;
      if (form.player_payout) body.player_payout = parseFloat(form.player_payout);

      const res = await fetch(apiUrl('/amusements'), {
        method: 'POST',
        headers: {
          'X-Access-Key': accessKey,
          Accept: 'application/json',
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(body),
      });

      if (res.ok) {
        setShowModal(false);
        fetchAmusements();
      } else {
        const data = await res.json();
        setFormError(data.message ?? 'Something went wrong. Please try again.');
      }
    } catch {
      setFormError('Network error.');
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <>
      <main className="section">
        <div className="section-head">
          <h2>My Amusements</h2>
          <button className="btn btn-primary" onClick={openModal}>+ New</button>
        </div>
        <p className="section-sub">Rides and games you manage.</p>

        {loading ? (
          <div className="empty-state">
            <span className="empty-icon">⏳</span>
            <p className="empty-title">Loading…</p>
          </div>
        ) : amusements.length === 0 ? (
          <div className="empty-state">
            <span className="empty-icon">🎪</span>
            <p className="empty-title">No amusements yet</p>
            <p className="empty-sub">Create your first amusement to get started.</p>
          </div>
        ) : (
          <div className="exchange-grid">
            {amusements.map((amusement) => (
              <div key={amusement.id} className="exchange-card">
                <div className="exchange-card-info">
                  <p className="exchange-card-title">{amusement.name}</p>
                  <p className="card-tag">{amusement.type.charAt(0).toUpperCase() + amusement.type.slice(1)}</p>
                  {amusement.price != null && <p className="exchange-card-desc">Entrance Fee: €{amusement.price.toFixed(2)}</p>}
                  {amusement.player_payout != null && <p className="exchange-card-desc">Winnings: €{amusement.player_payout.toFixed(2)}</p>}
                </div>
                <div className="exchange-card-right">
                  <Link to={`/edit-amusement/${amusement.id}`} className="btn btn-primary btn-edit">Edit</Link>
                  <button
                    className="btn btn-secondary"
                    disabled={deletingId === amusement.id}
                    onClick={() => handleDelete(amusement.id)}
                  >
                    {deletingId === amusement.id ? 'Deleting…' : 'Delete'}
                  </button>
                </div>
              </div>
            ))}
          </div>
        )}
      </main>

      {showModal && (
        <div className="modal-overlay" onClick={() => setShowModal(false)}>
          <div
            ref={modalRef}
            className="modal"
            role="dialog"
            aria-modal="true"
            style={{ maxWidth: 480, maxHeight: '90vh', overflowY: 'auto' }}
            onClick={e => e.stopPropagation()}
          >
            <h2 className="modal-title">New Amusement</h2>
            <p className="modal-sub">Register a new ride or game.</p>

            <form onSubmit={handleSubmit}>
              <div className="field">
                <label className="label" htmlFor="am-name">Name you ride or game <span className="required-star">*</span></label>
                <input
                  id="am-name"
                  className="input"
                  name="name"
                  value={form.name}
                  onChange={handleChange}
                  required
                  maxLength={100}
                  placeholder="e.g. Bumper Cars"
                />
              </div>

              <div className="field">
                <label className="label" htmlFor="am-type">Choose a type <span className="required-star">*</span></label>
                <CustomSelect
                  id="am-type"
                  value={form.type}
                  onChange={v => setForm(prev => ({ ...prev, type: v as "game" | "attraction" }))}
                  placeholder="Select type…"
                  options={[
                    { value: 'attraction', label: 'Attraction' },
                    { value: 'game', label: 'Game' },
                  ]}
                />
              </div>

              <div className="field">
                <label className="label" htmlFor="am-url">Add your amusements URL <span className="required-star">*</span></label>
                <input
                  id="am-url"
                  className="input"
                  name="url"
                  type="url"
                  value={form.url}
                  onChange={handleChange}
                  required
                  placeholder="http://your-amusement.com"
                />
              </div>

              <div className="field">
                <label className="label" htmlFor="am-price">Entry fee (€)</label>
                <input
                  id="am-price"
                  className="input"
                  name="price"
                  type="number"
                  min="0"
                  max="999.99"
                  step="0.01"
                  value={form.price}
                  onChange={handleChange}
                  placeholder="0.00"
                />
              </div>

              <div className="field">
                <label className="label" htmlFor="am-payout">Player payout (€)</label>
                <input
                  id="am-payout"
                  className="input"
                  name="player_payout"
                  type="number"
                  min="0"
                  max="999.99"
                  step="0.01"
                  value={form.player_payout}
                  onChange={handleChange}
                  placeholder="0.00"
                />
              </div>

              <div className="field">
                <label className="label" htmlFor="am-image">Image URL <br /><span className="img-url-desc">(This will show on the landing page)</span></label>
                <input
                  id="am-image"
                  className="input"
                  name="image_url"
                  type="url"
                  value={form.image_url}
                  onChange={handleChange}
                  placeholder="http://test.com/image.png"
                />
              </div>

              {formError && <p className="form-error">{formError}</p>}

              <div className="modal-actions">
                <button
                  type="button"
                  className="btn btn-secondary"
                  onClick={() => setShowModal(false)}
                  disabled={submitting}
                >
                  Cancel
                </button>
                <button type="submit" className="btn btn-primary" disabled={submitting}>
                  {submitting ? 'Creating…' : 'Create'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </>
  );
}
