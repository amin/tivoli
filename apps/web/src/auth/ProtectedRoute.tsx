import { Navigate } from "react-router-dom";
import type { ReactNode } from "react";
import { useAuth } from "./AuthContext";

export default function ProtectedRoute({ children }: { children: ReactNode }) {
  const { accessKey, user, loading } = useAuth();

  if (!accessKey) return <Navigate to="/login" replace />;
  if (loading) return null;
  if (!user) return <Navigate to="/login" replace />;

  return <>{children}</>;
}
