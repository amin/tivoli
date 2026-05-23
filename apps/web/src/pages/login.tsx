import { useEffect, useRef, useState } from "react";
import { useNavigate } from "react-router-dom";
import "./login.css";

import Header from "../components/Header";
import Footer from "../components/Footer";
import { apiFetch } from "../lib/api";
import { useAuth } from "../auth/AuthContext";

function InfoTip({ id, children }: { id: string; children: React.ReactNode }) {
  const [open, setOpen] = useState(false);
  const ref = useRef<HTMLSpanElement>(null);

  useEffect(() => {
    if (!open) return;
    function handleClickOutside(e: MouseEvent) {
      if (ref.current && !ref.current.contains(e.target as Node)) {
        setOpen(false);
      }
    }
    document.addEventListener("mousedown", handleClickOutside);
    return () => document.removeEventListener("mousedown", handleClickOutside);
  }, [open]);

  return (
    <span className="infotip" ref={ref}>
      <button
        type="button"
        className="infotip-btn"
        aria-label="More information"
        aria-expanded={open}
        aria-controls={id}
        onClick={() => setOpen((s) => !s)}
        onKeyDown={(e) => e.key === "Escape" && setOpen(false)}
      >
        i
      </button>
      <span id={id} className={`infotip-content${open ? " open" : ""}`} role="tooltip">
        {children}
      </span>
    </span>
  );
}

export default function Login() {
  const navigate = useNavigate();
  const { user, loading: authLoading, login } = useAuth();

  useEffect(() => {
    if (!authLoading && user) {
      navigate("/user", { replace: true });
    }
  }, [user?.id, authLoading, navigate]);

  // Activation form state
  const [activationName, setActivationName] = useState("");
  const [startcode, setStartcode] = useState("");
  // Login form state (primary)
  const [loginName, setLoginName] = useState("");
  const [result, setResult] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);
  const [resultType, setResultType] = useState<"success" | "error" | null>(
    null,
  );
  const [accessKeyInput, setAccessKeyInput] = useState("");
  const [showActivate, setShowActivate] = useState(false);
  const [loginLoading, setLoginLoading] = useState(false);
  const [loginError, setLoginError] = useState<string | null>(null);

  async function handleSubmit(e: React.FormEvent<HTMLFormElement>) {
    e.preventDefault();
    setLoading(true);
    setResult(null);
    setResultType(null);

    try {
      const res = await apiFetch("/activate", {
        method: "POST",
        body: JSON.stringify({ name: activationName, startcode }),
      });

      const json = await res.json();
      if (res.ok) {
        setResult("Activation successful!");
        setResultType("success");
        // Auto-fill access key into login input for convenience
        if (json.access_key) setAccessKeyInput(json.access_key);
      } else {
        setResult(json.error || json.message || "Activation failed");
        setResultType("error");
      }
    } catch (err) {
      setResult("Network error");
      setResultType("error");
    } finally {
      setLoading(false);
    }
  }

  async function loginWithKey(key?: string) {
    setLoginError(null);
    setLoginLoading(true);
    const k = (key ?? accessKeyInput).trim();
    try {
      await login(loginName.trim(), k);
      navigate("/user");
    } catch (err) {
      setLoginError(err instanceof Error ? err.message : "Network error");
    } finally {
      setLoginLoading(false);
    }
  }

  return (
    <>
      <Header />
      <section className="activatePage">
        <h1>
          Log in or Activate your account
        </h1>

        {/* Primary: Login form */}
        <section className="loginSection">
          <h2 className="desc-text">Log in with name & access key</h2>
          <form className="loginGrid" onSubmit={(e) => { e.preventDefault(); loginWithKey(); }}>
            <label className="label" htmlFor="login-name">Name</label>
            <input
              id="login-name"
              className="input"
              placeholder="Enter your name"
              value={loginName}
              onChange={(e) => setLoginName((e.target as HTMLInputElement).value)}
            />
            <span className="label-row">
              <label className="label" htmlFor="login-access-key">Access Key</label>
              <InfoTip id="tip-access-key">
                Generated when you activated your account. Copy it from the activation confirmation — it's your only way to log in.
              </InfoTip>
            </span>
            <input
              id="login-access-key"
              className="input"
              placeholder="Enter your access key"
              value={accessKeyInput}
              onChange={(e) =>
                setAccessKeyInput((e.target as HTMLInputElement).value)
              }
            />
            <div className="loginActions">
              <button
                type="submit"
                className="btn btn-primary"
                disabled={loginLoading || !accessKeyInput || !loginName}
              >
                {loginLoading ? "Logging in…" : "Log in"}
              </button>

              <button
                type="button"
                className="btn btn-secondary"
                onClick={() => {
                  setAccessKeyInput("");
                  setLoginError(null);
                }}
              >
                Clear
              </button>
            </div>

            <div className="activateToggle">
              <button
                type="button"
                className="btn btn-link"
                onClick={() => setShowActivate((s) => !s)}
              >
                Activate Account
              </button>
            </div>

            {loginError && <div className="form-error">{loginError}</div>}
          </form>
        </section>

        {/* Activation form: toggled below login */}
        {showActivate && (
          <form onSubmit={handleSubmit} className="activateForm">
            <h2 className="desc-text">Activate your account</h2>
            <div className="field">
              <label className="label" htmlFor="activate-name">Name</label>
              <input
                id="activate-name"
                className="input"
                placeholder="Enter your name"
                value={activationName}
                onChange={(e) =>
                  setActivationName((e.target as HTMLInputElement).value)
                }
                required
              />
            </div>

            <div className="field">
              <span className="label-row">
                <label className="label" htmlFor="activate-startcode">Startcode</label>
                <InfoTip id="tip-startcode">
                  A one-time code given to you when you registered for Loopland.
                </InfoTip>
              </span>
              <input
                id="activate-startcode"
                className="input"
                placeholder="Enter your startcode"
                value={startcode}
                onChange={(e) =>
                  setStartcode((e.target as HTMLInputElement).value)
                }
                required
              />
            </div>

            <div className="activateToggle">
              <button type="submit" disabled={loading} className="btn btn-primary">
                {loading ? "Activating…" : "Get access key"}
              </button>
            </div>
          </form>
        )}

        {result && (
          <div
            className={`result ${resultType === "success" ? "result-success" : resultType === "error" ? "result-error" : ""}`}
          >
            <p>
              <strong>
                {resultType === "success"
                  ? "Success!"
                  : resultType === "error"
                    ? "Error!"
                    : ""}
              </strong>{" "}
              {result}
            </p>
            {accessKeyInput && (
              <>
                <p className="accessKey">{accessKeyInput}</p>
                {resultType === "success" && (
                  <p className="saveNote">
                    Save your <strong>access_key</strong> in a safe place!
                  </p>
                )}
              </>
            )}
          </div>
        )}
      </section>
      <Footer />
    </>
  );
}
