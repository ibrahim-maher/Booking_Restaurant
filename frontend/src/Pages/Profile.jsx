import { useAuth } from "../contexts/AuthContext";
import { Navigate } from "react-router-dom";

export default function Profile() {
   const { isAuthenticated, user, logout } = useAuth();

   if (!isAuthenticated) {
      return <Navigate to="/login" replace />;
   }

   return (
      <div className="min-h-screen flex items-center justify-center bg-gray-100">
         <div className="text-center p-6">
            <h1 className="text-3xl font-bold mb-4">Profile Page</h1>
            <p className="mb-4">Name: {user?.name}</p>
            <p className="mb-4">Email: {user?.email}</p>
            <button
               onClick={logout}
               className="bg-red-600 text-white p-2 rounded hover:bg-red-700"
            >
               Logout
            </button>
         </div>
      </div>
   );
}
