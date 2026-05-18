import { useSearchParams, Link } from "react-router-dom";
import Header from "../components/Header";
import Footer from "../components/Footer";

export default function ErrorPage() {
  const [params] = useSearchParams();
  const message = params.get("message") ?? "Something went wrong.";

  return (
    <>
      <Header />
      <section style={{
        padding: "64px 24px",
        display: "flex",
        flexDirection: "column",
        alignItems: "center",
        gap: 16,
        textAlign: "center",
        background: "var(--bg-tint)",
        minHeight: "60vh",
      }}>
        <span style={{ fontSize: 48 }}>⚠️</span>
        <h1 style={{ margin: 0 }}>Oops</h1>
        <p style={{ color: "var(--text-muted)", maxWidth: 360, margin: 0 }}>{message}</p>
        <Link to="/login" className="btn btn-primary" style={{ marginTop: 8, textDecoration: "none" }}>
          Back to sign in
        </Link>
      </section>
      <Footer />
    </>
  );
}
