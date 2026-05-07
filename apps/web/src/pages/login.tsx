import { useState } from "react";
import { useNavigate } from "react-router-dom";
import "./login.css";

import Header from "../components/Header";
import Footer from "../components/Footer";

export default function Login() {
  const navigate = useNavigate();
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

  async function handleSubmit(e) {
    e.preventDefault();
    setLoading(true);
    setResult(null);
    setResultType(null);

    try {
      const res = await fetch("/api/activate", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
        },
        body: JSON.stringify({ name: activationName, startcode }),
      });

      const json = await res.json();
      if (res.ok) {
        setResult("Activation successful!");
        setResultType("success");
        // Auto-fill access key into login input for convenience
        if (json.access_key) setAccessKeyInput(json.access_key);
      } else {
        setResult(json.error || "Activation failed");
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
      // Authenticate using name + access_key
      const res = await fetch("/api/auth/login", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
        },
        body: JSON.stringify({ name: loginName.trim(), access_key: k }),
      });

      const body = await res.json().catch(() => ({}));
      if (!res.ok) {
        setLoginError(body.error || `Login failed (${res.status})`);
      } else {
        setLoginError(null);
        // Redirect to user's admin dashboard
        navigate("/user");
      }
    } catch (err) {
      setLoginError("Network error");
    } finally {
      setLoginLoading(false);
    }
  }

  return (
    <>
      <Header />
      <section className="activatePage">
        <h1>Activate your account</h1>

        {/* Primary: Login form */}
        <section className="loginSection">
          <h2>Log in with name & access key</h2>
          <div className="loginGrid">
            <input
              className="input"
              placeholder="Enter your name"
              value={loginName}
              onChange={(e) => setLoginName((e.target as HTMLInputElement).value)}
            />
            <input
              className="input"
              placeholder="Enter your access key"
              value={accessKeyInput}
              onChange={(e) =>
                setAccessKeyInput((e.target as HTMLInputElement).value)
              }
            />
            <div className="loginActions">
              <button
                className="btn btn-primary"
                onClick={() => loginWithKey()}
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

              <button
                type="button"
                className="btn btn-link"
                onClick={() => setShowActivate((s) => !s)}
              >
                Activate Account
              </button>
            </div>

            {loginError && <div className="form-error">{loginError}</div>}
          </div>
        </section>

        {/* Activation form: toggled below login */}
        {showActivate && (
          <form onSubmit={handleSubmit} className="activateForm">
            <div className="field">
              <label className="label">Name</label>
              <input
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
              <label className="label">Startcode</label>
              <input
                className="input"
                placeholder="Enter your startcode"
                value={startcode}
                onChange={(e) =>
                  setStartcode((e.target as HTMLInputElement).value)
                }
                required
              />
            </div>

            <button type="submit" disabled={loading} className="btn btn-primary">
              {loading ? "Activating…" : "Get access key"}
            </button>
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
