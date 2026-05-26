import { useCallback, useEffect, useState } from "react";
import { apiFetch } from "../lib/api";

type GuestStatus = {
  /** null while the initial status request is in flight. */
  available: boolean | null;
  refresh: () => Promise<void>;
};

export function useGuestAvailable(): GuestStatus {
  const [available, setAvailable] = useState<boolean | null>(null);

  const refresh = useCallback(async () => {
    try {
      const res = await apiFetch("/guest");
      if (!res.ok) {
        setAvailable(false);
        return;
      }
      const data = (await res.json()) as { active?: boolean };
      setAvailable(Boolean(data.active));
    } catch {
      setAvailable(false);
    }
  }, []);

  useEffect(() => {
    void refresh();
  }, [refresh]);

  return { available, refresh };
}
