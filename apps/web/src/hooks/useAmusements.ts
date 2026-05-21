import { useEffect, useState } from "react";
import { apiFetch } from "../lib/api";
import { useAuth } from "../auth/AuthContext";

export type AmusementItem = {
  id: number;
  name: string;
  description: string | null;
  type: "game" | "attraction";
  url: string;
  price: number | null;
  player_payout: number | null;
  amusement_balance?: number;
  image_url: string | null;
  api_key?: string;
};

export function useAmusements(owned = false) {
  const { user } = useAuth();
  const [amusements, setAmusements] = useState<AmusementItem[]>([]);
  const [loading, setLoading] = useState(false);

  function fetchAmusements() {
    setLoading(true);
    apiFetch(owned ? "/amusements?owned=true" : "/amusements")
      .then((res) => (res.ok ? res.json() : null))
      .then((data) => { if (data) setAmusements(data.data ?? []); })
      .catch(() => {})
      .finally(() => setLoading(false));
  }

  useEffect(() => {
    fetchAmusements();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [user?.id, owned]);

  return { amusements, loading, refetch: fetchAmusements };
}
