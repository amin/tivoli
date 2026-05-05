import tivoliImg from "./assets/tivoli.jpg"
import './App.css'

type Attraction = {
  id: string
  name: string
  tag: string
  emoji: string
  bg: string
}

const attractions: Attraction[] = []
// Add attractions here as they are created, e.g.:

export default function App() {
  return (
    <>
      <header className="header">
        <span className="brand-name">Tivoli</span>
        <nav className="nav">
          <a href="#" className="auth-pill">
            <span className="avatar">G</span>
            <span className="auth-text">Guest</span>
            <span className="auth-divider">·</span>
            <span className="auth-link">Log in</span>
          </a>
        </nav>
      </header>

      <section className="hero">
        <div className="hero-content">
          <span className="eyebrow">NOW OPEN</span>
          <h1>Your digital<br /><span className="pixel">playground</span></h1>
          <p>Games, rides, and attractions - all in one place. Grab a ticket and explore.</p>
          <div className="hero-ctas">
            <button className="btn btn-primary">Enter the park</button>
            <button className="btn btn-secondary">How it works</button>
          </div>
        </div>
        <div className="hero-img-wrap">
          <img src={tivoliImg} alt="Tivoli park" className="hero-img" />
        </div>
      </section>

      <main className="section">
        <div className="section-head">
          <h2>Attractions & Games</h2>
        </div>
        <p className="section-sub">All the fun in one place.</p>

        <div className="filters">
          <button className="chip active">All</button>
          <button className="chip">Games</button>
          <button className="chip">Rides</button>
        </div>

        {attractions.length === 0 ? (
          <div className="empty-state">
            <span className="empty-icon">🎪</span>
            <p className="empty-title">Coming soon</p>
            <p className="empty-sub">Attractions are being built — check back soon!</p>
          </div>
        ) : (
          <div className="grid">
            {attractions.map(a => (
              <div key={a.id} className="card">
                <div className="card-illustration" style={{ background: a.bg }}>
                  <span style={{ fontSize: 56 }}>{a.emoji}</span>
                </div>
                <div className="card-body">
                  <p className="card-title">{a.name}</p>
                  <p className="card-tag">{a.tag}</p>
                </div>
              </div>
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
        <span className="footer-left">© 2025 Tivoli</span>
        <nav className="footer-links">
          <a href="#">About</a>
          <a href="#">Contact</a>
        </nav>
      </footer>
    </>
  )
}
