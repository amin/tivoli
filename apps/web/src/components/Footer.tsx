export default function Footer() {
  return (
    <footer className="footer">
      <div className="lights" aria-hidden="true">
        {Array.from({ length: 9 }).map((_, i) => (
          <span key={i} className="light" />
        ))}
      </div>
      <div className="footer-content">
        <span className="footer-left">
          © {new Date().getFullYear()} Loopland &mdash; A school project by{" "}
          <a href="https://www.yrgo.se/program/webbutvecklare/" target="_blank" rel="noreferrer">
            Yrgo, Webbutvecklare
          </a>
        </span>
        <nav className="footer-links">
          <a href="https://www.yrgo.se/program/webbutvecklare/">Yrgo</a>
        </nav>
      </div>
    </footer>
  );
}
