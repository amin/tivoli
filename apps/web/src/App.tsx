import "./App.css";
import { Routes, Route } from "react-router-dom";
import Home from "./pages/home";
import Login from "./pages/login";
import User from "./pages/user";
import ErrorPage from "./pages/error";

export default function App() {
  return (
    <Routes>
      <Route path="/" element={<Home />} />
      <Route path="/login" element={<Login />} />
      <Route path="/user" element={<User />} />
      <Route path="/error" element={<ErrorPage />} />
    </Routes>
  );
}
