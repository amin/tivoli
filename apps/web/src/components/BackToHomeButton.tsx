import { useEffect, useState } from "react";
import { useLocation, Link } from "react-router-dom";

export default function BackToHomeButton() {
  const { pathname } = useLocation();
  const [bottom, setBottom] = useState(24);

  useEffect(() => {
    function updateBottom() {
      const footer = document.querySelector(".footer") as HTMLElement | null;
      if (!footer) { setBottom(24); return; }
      const footerTop = footer.getBoundingClientRect().top;
      if (footerTop < window.innerHeight) {
        setBottom(window.innerHeight - footerTop + 16);
      } else {
        setBottom(24);
      }
    }

    updateBottom();
    window.addEventListener("scroll", updateBottom, { passive: true });
    window.addEventListener("resize", updateBottom, { passive: true });
    return () => {
      window.removeEventListener("scroll", updateBottom);
      window.removeEventListener("resize", updateBottom);
    };
  }, [pathname]);

  if (pathname === "/") return null;

  return (
    <Link to="/" className="back-home-btn" style={{ bottom }}>
      ← Start
    </Link>
  );
}
