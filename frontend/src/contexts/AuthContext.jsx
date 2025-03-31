import { createContext, useContext, useEffect, useState } from "react";
import axios/*, { getCsrfToken }*/ from "../api/axios";
import { useNavigate } from "react-router-dom";

const AuthContext = createContext();

export const AuthProvider = ({ children }) => {
   const [user, setUser] = useState(null);
   const navigate = useNavigate(); // Use useNavigate here

   const fetchUser = async () => {
      try {
         const res = await axios.get("/api/user");
         setUser(res.data);
      } catch {
         setUser(null); // Not logged in
      }
   };

   const login = async (email, password) => {
      try {
         // await getCsrfToken();
         await axios.post("/api/login", { email, password });
         await fetchUser(); // Get logged-in user
         navigate("/"); // Redirect after login
      } catch (error) {
         console.error(
            "Login failed:",
            error.response?.data?.message || error.message
         );
         throw new Error(error.response?.data?.message || "Login failed");
      }
   };

   const logout = async () => {
      try {
         await axios.post("/api/logout");
         setUser(null);
         navigate("/login"); // Redirect after logout
      } catch (error) {
         console.error(
            "Logout failed:",
            error.response?.data?.message || error.message
         );
         throw new Error(error.response?.data?.message || "Logout failed");
      }
   };

   const isAuthenticated = !!user;

   useEffect(() => {
      fetchUser(); // On app load, try to fetch user
   }, []);

   return (
      <AuthContext.Provider value={{ user, login, logout, isAuthenticated }}>
         {children}
      </AuthContext.Provider>
   );
};

export const useAuth = () => useContext(AuthContext);
