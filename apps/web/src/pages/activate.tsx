import { useState } from "react";
import type { FormEvent } from "react";
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

  async function handleSubmit(e: FormEvent) {
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
        setResult(`Activation successful. Access key: ${json.access_key}`);
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
        <label className="formLabel">
          Name
          <input
            className="textInput"
            value={name}
            onChange={(e) => setName((e.target as HTMLInputElement).value)}
            required
          />
        </label>

        <label className="formLabel">
          Startcode
          <input
            className="textInput"
            value={startcode}
            onChange={(e) => setStartcode((e.target as HTMLInputElement).value)}
            required
          />
        </label>

        <button type="submit" disabled={loading} className="primaryButton">
          {loading ? "Activating…" : "Get access key"}
        </button>
      </form>

      {result && (
        <div className="result">
          <pre>{result}</pre>
        </div>
      )}

      <section className="loginSection">
        <h2>Log in with access key</h2>
        <div className="loginGrid">
          <input
            className="textInput"
            placeholder="Paste access key here"
            value={accessKeyInput}
            onChange={(e) => setAccessKeyInput((e.target as HTMLInputElement).value)}
          />

          <div className="loginActions">
            <button
              className="primaryButton"
              onClick={() => loginWithKey()}
              disabled={loginLoading || !accessKeyInput}
            >
              {loginLoading ? "Logging in…" : "Log in"}
            </button>

            <button
              type="button"
              className="secondaryButton"
              onClick={() => {
                setAccessKeyInput("");
                setLoggedInUser(null);
                setLoginError(null);
              }}
            >
              Clear
            </button>
          </div>

          {loginError && <div className="loginError">{loginError}</div>}

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
