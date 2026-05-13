import { useEffect, useState } from "react";
import { apiUrl } from "../lib/api";

export type AmusementItem = {
  id: number;
  name: string;
  description: string | null;
  type: "game" | "attraction";
  url: string;
  price: number | null;
  player_payout: number | null;
  image_url: string | null;
};

export function useAmusements(accessKey: string) {
  const [amusements, setAmusements] = useState<AmusementItem[]>([]);
  const [loading, setLoading] = useState(false);

  function fetchAmusements() {
    setLoading(true);
    fetch(apiUrl("/amusements"), {
      headers: { "X-Access-Key": accessKey, Accept: "application/json" },
    })
      .then((res) => (res.ok ? res.json() : null))
      .then((data) => { if (data) setAmusements(data.data ?? []); })
      .catch(() => {})
      .finally(() => setLoading(false));
  }

  useEffect(() => {
    fetchAmusements();
  }, [accessKey]);

  return { amusements, loading, refetch: fetchAmusements };
}
