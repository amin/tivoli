import { useEffect, useRef, useState } from "react";
import { apiFetch } from "../lib/api";
import CustomSelect from "./CustomSelect";
import AmusementCard from "./AmusementCard";
import { useAmusements, type AmusementItem } from "../hooks/useAmusements";

type FormData = {
  name: string;
  description: string;
  url: string;
  image_url: string;
  price: string;
  player_payout: string;
  type: 'game' | 'attraction' | '';
};

const EMPTY_FORM: FormData = {
  name: '',
  description: '',
  url: '',
  image_url: '',
  price: '',
  player_payout: '',
  type: '',
};

export default function Amusement() {
  const { amusements, loading, refetch } = useAmusements(true);
  const [showModal, setShowModal] = useState(false);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [form, setForm] = useState<FormData>(EMPTY_FORM);
  const [submitting, setSubmitting] = useState(false);
  const [formError, setFormError] = useState<string | null>(null);
  const [deletingId, setDeletingId] = useState<number | null>(null);
  const [deleteError, setDeleteError] = useState<string | null>(null);
  const modalRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!showModal) return;
    const modal = modalRef.current;
    modal?.querySelector<HTMLElement>('input:not([disabled]), button:not([disabled])')?.focus();

    function onKey(e: KeyboardEvent) {
      if (e.key === 'Escape') { setShowModal(false); return; }
      if (e.key !== 'Tab' || !modal) return;

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
    setDeleteError(null);
    try {
      const res = await apiFetch(`/amusements/${id}`, { method: 'DELETE' });
      if (res.ok) {
        refetch();
      } else {
        const data = await res.json().catch(() => ({}));
        setDeleteError(data.message ?? 'Could not delete amusement.');
      }
    } catch {
      setDeleteError('Network error.');
    } finally {
      setDeletingId(null);
    }
  }

  function openCreateModal() {
    setEditingId(null);
    setForm(EMPTY_FORM);
    setFormError(null);
    setShowModal(true);
  }

  async function openEditModal(amusement: AmusementItem) {
    setFormError(null);
    setEditingId(amusement.id);

    let description = amusement.description ?? '';
    try {
      const res = await apiFetch(`/amusements/${amusement.id}`);
      if (res.ok) {
        const data = await res.json();
        description = data.description ?? '';
      }
    } catch {}

    setForm({
      name: amusement.name,
      description,
      url: amusement.url,
      image_url: amusement.image_url ?? '',
      price: amusement.price != null ? String(amusement.price) : '',
      player_payout: amusement.player_payout != null ? String(amusement.player_payout) : '',
      type: amusement.type as 'game' | 'attraction',
    });
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
        price: form.price ? parseFloat(form.price) : null,
        type: form.type,
        image_url: form.image_url || null,
        player_payout: form.player_payout ? parseFloat(form.player_payout) : null,
      };

      const isEdit = editingId !== null;
      const res = await apiFetch(
        isEdit ? `/amusements/${editingId}` : '/amusements',
        {
          method: isEdit ? 'PATCH' : 'POST',
          body: JSON.stringify(body),
        }
      );

      if (res.ok) {
        setShowModal(false);
        refetch();
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

  const isEdit = editingId !== null;

  return (
    <>
      <main className="section">
        <div className="section-head">
          <h2>My Amusements</h2>
          <button className="btn btn-primary" onClick={openCreateModal}>+ New</button>
        </div>
        <p className="section-sub">Attractions and games you manage.</p>

        {deleteError && <p className="form-error">{deleteError}</p>}

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
          <div className="grid">
            {amusements.map((amusement) => (
              <AmusementCard
                key={amusement.id}
                amusement={amusement}
                apiKey={amusement.api_key}
                showBalance
                actions={
                  <>
                    <button
                      className="btn btn-primary btn-edit"
                      onClick={() => openEditModal(amusement)}
                    >
                      Edit
                    </button>
                    <button
                      className="btn btn-secondary"
                      disabled={deletingId === amusement.id}
                      onClick={() => handleDelete(amusement.id)}
                    >
                      {deletingId === amusement.id ? 'Deleting…' : 'Delete'}
                    </button>
                  </>
                }
              />
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
            <h2 className="modal-title">{isEdit ? 'Edit Amusement' : 'New Amusement'}</h2>
            <p className="modal-sub">{isEdit ? 'Update your ride or game.' : 'Register a new ride or game.'}</p>

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
                  {submitting ? (isEdit ? 'Saving…' : 'Creating…') : (isEdit ? 'Save' : 'Create')}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </>
  );
}
