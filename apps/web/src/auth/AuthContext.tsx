import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from "react";
import { apiUrl } from "../lib/api";

const LS_KEY = "tivoliAccessKey";

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
  accessKey: string;
  loading: boolean;
  login: (key: string) => void;
  logout: () => void;
  refresh: () => Promise<void>;
};

const AuthContext = createContext<AuthValue | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [accessKey, setAccessKey] = useState<string>(
    () => localStorage.getItem(LS_KEY) ?? "",
  );
  const [user, setUser] = useState<AuthUser | null>(null);
  const [loading, setLoading] = useState<boolean>(!!accessKey);

  const fetchUser = useCallback(async (key: string) => {
    if (!key) {
      setUser(null);
      setLoading(false);
      return;
    }
    setLoading(true);
    try {
      const res = await fetch(apiUrl("/user"), {
        headers: { "X-Access-Key": key, Accept: "application/json" },
      });
      if (!res.ok) {
        localStorage.removeItem(LS_KEY);
        setAccessKey("");
        setUser(null);
        return;
      }
      const data = await res.json();
      setUser(data);
    } catch {
      setUser(null);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchUser(accessKey);
  }, [accessKey, fetchUser]);

  const login = useCallback((key: string) => {
    localStorage.setItem(LS_KEY, key);
    setAccessKey(key);
    // accessKey effect triggers fetchUser
  }, []);

  const logout = useCallback(() => {
    localStorage.removeItem(LS_KEY);
    setAccessKey("");
    setUser(null);
  }, []);

  const refresh = useCallback(() => fetchUser(accessKey), [accessKey, fetchUser]);

  const value = useMemo<AuthValue>(
    () => ({ user, accessKey, loading, login, logout, refresh }),
    [user, accessKey, loading, login, logout, refresh],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthValue {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error("useAuth must be used inside AuthProvider");
  return ctx;
}
