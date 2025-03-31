import { Routes, Route, Navigate } from "react-router-dom";
import { useAuth } from "./contexts/AuthContext";
import Login from "./Pages/Auth/Login";
import Register from "./Pages/Auth/Register";
import Home from "./Pages/Home";
import Profile from "./Pages/Profile";
import PrivateRoute from "./Components/PrivateRoute";

function App() {
   const { isAuthenticated } = useAuth();

   return (
      <Routes>
         <Route
            path="/login"
            element={isAuthenticated ? <Navigate to="/" /> : <Login />}
         />
         <Route
            path="/register"
            element={isAuthenticated ? <Navigate to="/" /> : <Register />}
         />
         <Route path="/" element={<Home />} />
         <Route path="/home" element={<Home />} />
         <Route
            path="/profile"
            element={<PrivateRoute element={<Profile />} />}
         />
      </Routes>
   );
}

export default App;
