import "./App.css";
import { Routes, Route } from "react-router-dom";
import { AuthProvider } from "./auth/AuthContext";
import ProtectedRoute from "./auth/ProtectedRoute";
import Home from "./pages/home";
import Login from "./pages/login";
import User from "./pages/user";
import ErrorPage from "./pages/error";
import BackToHomeButton from "./components/BackToHomeButton";

export default function App() {
  return (
    <AuthProvider>
      <Routes>
        <Route path="/" element={<Home />} />
        <Route path="/login" element={<Login />} />
        <Route
          path="/user"
          element={
            <ProtectedRoute>
              <User />
            </ProtectedRoute>
          }
        />
        <Route path="/error" element={<ErrorPage />} />
      </Routes>
      <BackToHomeButton />
    </AuthProvider>
  );
}
