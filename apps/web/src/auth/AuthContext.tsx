import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from "react";
import { apiFetch, getCsrfCookie } from "../lib/api";

export type GroupSummary = {
  id: number;
  name: string;
  member_count: number;
};

export type AuthUser = {
  id: number;
  name: string;
  balance: number;
  group: GroupSummary | null;
  stamp_count: number;
};

type AuthValue = {
  user: AuthUser | null;
  loading: boolean;
  login: (name: string, accessKey: string) => Promise<void>;
  logout: () => Promise<void>;
  refresh: () => Promise<void>;
};

const AuthContext = createContext<AuthValue | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<AuthUser | null>(null);
  const [loading, setLoading] = useState<boolean>(true);

  const fetchUser = useCallback(async () => {
    try {
      const res = await apiFetch("/user");
      if (!res.ok) {
        setUser(null);
        return;
      }
      setUser(await res.json());
    } catch {
      setUser(null);
    }
  }, []);

  useEffect(() => {
    let cancelled = false;
    (async () => {
      await fetchUser();
      if (!cancelled) setLoading(false);
    })();
    return () => {
      cancelled = true;
    };
  }, [fetchUser]);

  const login = useCallback(async (name: string, accessKey: string) => {
    await getCsrfCookie();
    const res = await apiFetch("/login", {
      method: "POST",
      body: JSON.stringify({ name, access_key: accessKey }),
    });
    const body = await res.json().catch(() => ({}));
    if (!res.ok) {
      throw new Error(body.message ?? `Login failed (${res.status})`);
    }
    setUser(body);
  }, []);

  const logout = useCallback(async () => {
    try {
      await apiFetch("/logout", { method: "POST" });
    } finally {
      setUser(null);
    }
  }, []);

  const value = useMemo<AuthValue>(
    () => ({ user, loading, login, logout, refresh: fetchUser }),
    [user, loading, login, logout, fetchUser],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthValue {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error("useAuth must be used inside AuthProvider");
  return ctx;
}
