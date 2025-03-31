import { Link } from "react-router-dom";
import { useAuth } from "../contexts/AuthContext";

export default function Home() {
   const { isAuthenticated, user } = useAuth();

   return (
      <div className="min-h-screen flex items-center justify-center bg-gray-100">
         <div className="text-center">
            <h1 className="text-4xl font-bold mb-4">Welcome to Our App!</h1>
            {isAuthenticated ? (
               <>
                  <p className="mb-4">Hello, {user?.name}!</p>
                  <Link
                     to="/profile"
                     className="bg-blue-600 text-white p-2 rounded hover:bg-blue-700"
                  >
                     Go to Profile
                  </Link>
               </>
            ) : (
               <>
                  <p className="mb-4">
                     Please login or register to access your profile.
                  </p>
                  <Link
                     to="/login"
                     className="bg-blue-600 text-white p-2 rounded hover:bg-blue-700"
                  >
                     Login
                  </Link>
                  <br />
                  <Link
                     to="/register"
                     className="mt-2 inline-block text-blue-600 hover:text-blue-800"
                  >
                     Register Here
                  </Link>
               </>
            )}
         </div>
      </div>
   );
}
