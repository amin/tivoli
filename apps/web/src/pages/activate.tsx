import { useState } from "react";
import "./activate.css";

export default function Activate() {
  const [name, setName] = useState("");
  const [startcode, setStartcode] = useState("");
  const [result, setResult] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);
  const [accessKeyInput, setAccessKeyInput] = useState("");
  const [loggedInUser, setLoggedInUser] = useState<any | null>(null);
  const [loginLoading, setLoginLoading] = useState(false);
  const [loginError, setLoginError] = useState<string | null>(null);

  async function handleSubmit(e) {
    e.preventDefault();
    setLoading(true);
    setResult(null);

    try {
      const res = await fetch("/api/activate", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
        },
        body: JSON.stringify({ name, startcode }),
      });

      const json = await res.json();
      if (res.ok) {
        setResult("Activation successful!");
        // Auto-fill access key into login input for convenience
        if (json.access_key) setAccessKeyInput(json.access_key);
      } else {
        setResult(json.error || "Activation failed");
      }
    } catch (err) {
      setResult("Network error");
    } finally {
      setLoading(false);
    }
  }

  async function loginWithKey(key?: string) {
    setLoginError(null);
    setLoginLoading(true);
    const k = key ?? accessKeyInput;
    try {
      const res = await fetch("/api/user", {
        headers: { "x-access-key": k, Accept: "application/json" },
      });
      if (!res.ok) {
        const body = await res.json().catch(() => ({}));
        setLoginError(body.error || `Login failed (${res.status})`);
        setLoggedInUser(null);
      } else {
        const user = await res.json();
        setLoggedInUser(user);
        setLoginError(null);
      }
    } catch (err) {
      setLoginError("Network error");
      setLoggedInUser(null);
    } finally {
      setLoginLoading(false);
    }
  }

  return (
    <section className="activatePage">
      <h1>Activate your account</h1>

      <form onSubmit={handleSubmit} className="activateForm">
        <div className="field">
          <label className="label">Name</label>
          <input
            className="input"
            placeholder="Enter your name"
            value={name}
            onChange={(e) => setName((e.target as HTMLInputElement).value)}
            required
          />
        </div>

        <div className="field">
          <label className="label">Startcode</label>
          <input
            className="input"
            placeholder="Enter your startcode"
            value={startcode}
            onChange={(e) => setStartcode((e.target as HTMLInputElement).value)}
            required
          />
        </div>

        <button type="submit" disabled={loading} className="btn btn-primary">
          {loading ? "Activating…" : "Get access key"}
        </button>
      </form>

      {result && (
        <div className="result">
          <p>{result}</p>
          {accessKeyInput && <p className="accessKey">{accessKeyInput}</p>}
        </div>
      )}

      <section className="loginSection">
        <h2>Log in with access key</h2>
        <div className="loginGrid">
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
              disabled={loginLoading || !accessKeyInput}
            >
              {loginLoading ? "Logging in…" : "Log in"}
            </button>

            <button
              type="button"
              className="btn btn-secondary"
              onClick={() => {
                setAccessKeyInput("");
                setLoggedInUser(null);
                setLoginError(null);
              }}
            >
              Clear
            </button>
          </div>

          {loginError && <div className="form-error">{loginError}</div>}

          {loggedInUser ? (
            <div className="authenticated">
              <p>
                <strong>Logged in as:</strong>
                <span className="userName">{loggedInUser.name}</span>
              </p>
              <p>
                <strong>Balance: </strong>
                {loggedInUser.balance}
              </p>
              <p>
                <strong>GitHub: </strong>
                {loggedInUser.github_link}
              </p>
              <p>
                <strong>Website: </strong>
                {loggedInUser.website_link}
              </p>
            </div>
          ) : (
            <div className="notAuthenticated">Not authenticated</div>
          )}
        </div>
      </section>
    </section>
  );
}
