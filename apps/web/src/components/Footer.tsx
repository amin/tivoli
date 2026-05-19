export default function Footer() {
  return (
    <footer className="footer">
      <div className="lights">
        {Array.from({ length: 9 }).map((_, i) => (
          <span key={i} className="light" />
        ))}
      </div>
      <div className="footer-content">
        <span className="footer-left">© {new Date().getFullYear()} Loopland</span>
        <nav className="footer-links">
          <a href="#">About</a>
          <a href="#">Contact</a>
        </nav>
      </div>
    </footer>
  );
}
