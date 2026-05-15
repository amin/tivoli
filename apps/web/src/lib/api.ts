const BASE =
  import.meta.env.VITE_API_URL ??
  (import.meta.env.DEV ? "http://localhost:8000" : "");

export const apiUrl = (path: string) => `${BASE}${path}`;

let csrfTokenPromise: Promise<string> | null = null;

function fetchCsrfToken(): Promise<string> {
  return fetch(apiUrl("/csrf-token"), { credentials: "include" })
    .then((res) => res.json())
    .then((data) => data.csrf_token as string);
}

function getCsrfToken(): Promise<string> {
  if (!csrfTokenPromise) {
    csrfTokenPromise = fetchCsrfToken();
  }
  return csrfTokenPromise;
}

function invalidateCsrfToken(): void {
  csrfTokenPromise = null;
}

export async function apiFetch(
  path: string,
  init: RequestInit = {},
  isRetry = false,
): Promise<Response> {
  const method = (init.method ?? "GET").toUpperCase();
  const isMutating = method !== "GET" && method !== "HEAD";

  const headers = new Headers(init.headers);
  if (!headers.has("Accept")) headers.set("Accept", "application/json");
  if (init.body && !headers.has("Content-Type")) {
    headers.set("Content-Type", "application/json");
  }
  if (isMutating) {
    headers.set("X-CSRF-TOKEN", await getCsrfToken());
  }

  const res = await fetch(apiUrl(path), {
    ...init,
    headers,
    credentials: "include",
  });

  // After /login the session regenerates and the CSRF token rotates;
  // also covers any other token drift. Retry once with a fresh token.
  if (res.status === 419 && isMutating && !isRetry) {
    invalidateCsrfToken();
    return apiFetch(path, init, true);
  }

  return res;
}
