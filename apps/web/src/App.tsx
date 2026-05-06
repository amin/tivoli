import "./App.css";
import { Routes, Route } from "react-router-dom";
import Home from "./pages/home";
import Activate from "./pages/activate";
import User from "./pages/user";

export default function App() {
  return (
    <Routes>
      <Route path="/activate" element={<Activate />} />
      <Route path="/user" element={<User />} />
      <Route path="/" element={<Home />} />
    </Routes>
  );
}
