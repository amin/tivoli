const BASE =
  import.meta.env.VITE_API_URL ??
  (import.meta.env.DEV ? "http://localhost:8000" : "");

export const apiUrl = (path: string) => `${BASE}${path}`;

function readCookie(name: string): string | null {
  const prefix = `${name}=`;
  for (const part of document.cookie.split("; ")) {
    if (part.startsWith(prefix)) {
      return decodeURIComponent(part.slice(prefix.length));
    }
  }
  return null;
}

let csrfPromise: Promise<void> | null = null;
export function getCsrfCookie(): Promise<void> {
  if (!csrfPromise) {
    csrfPromise = fetch(apiUrl("/sanctum/csrf-cookie"), {
      credentials: "include",
    }).then(() => undefined);
  }
  return csrfPromise;
}

export async function apiFetch(
  path: string,
  init: RequestInit = {},
): Promise<Response> {
  const method = (init.method ?? "GET").toUpperCase();
  const isMutating = method !== "GET" && method !== "HEAD";

  if (isMutating) {
    await getCsrfCookie();
  }

  const headers = new Headers(init.headers);
  if (!headers.has("Accept")) headers.set("Accept", "application/json");
  if (init.body && !headers.has("Content-Type")) {
    headers.set("Content-Type", "application/json");
  }
  if (isMutating) {
    const xsrf = readCookie("XSRF-TOKEN");
    if (xsrf) headers.set("X-XSRF-TOKEN", xsrf);
  }

  return fetch(apiUrl(path), {
    ...init,
    headers,
    credentials: "include",
  });
}
