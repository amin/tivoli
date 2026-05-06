import "./App.css";
import { Routes, Route } from "react-router-dom";
import Home from "./pages/home";
import Activate from "./pages/activate";

export default function App() {
  return (
    <Routes>
      <Route path="/activate" element={<Activate />} />
      <Route path="/" element={<Home />} />
    </Routes>
  );
}
