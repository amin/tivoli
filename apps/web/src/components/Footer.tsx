export default function Footer() {
  return (
    <footer className="footer">
      <span className="footer-left">© {new Date().getFullYear()} Tivoli</span>
      <nav className="footer-links">
        <a href="#">About</a>
        <a href="#">Contact</a>
      </nav>
    </footer>
  );
}
