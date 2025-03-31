import { useState } from "react";
import axiosInstance/*, { getCsrfToken }*/ from "../../api/axios";
import { useNavigate, Link } from "react-router-dom";

export default function Login() {
   const [email, setEmail] = useState("");
   const [password, setPassword] = useState("");
   const [errors, setErrors] = useState(null);
   const navigate = useNavigate();

   const handleSubmit = async (e) => {
      e.preventDefault();
      setErrors(null);

      try {
         // await getCsrfToken();
         const response = await axiosInstance.post("/api/login", {
            email,
            password,
         });
         localStorage.setItem("user", JSON.stringify(response.data.user));
         localStorage.setItem("token", response.data.token);
         navigate("/"); // redirect to homepage or dashboard
      } catch (error) {
         if (!error.response) {
            setErrors("Network error. Please try again later.");
         } else {
            setErrors(error.response?.data?.message || "Login failed");
         }
      }
   };

   return (
      <div className="max-w-md mx-auto mt-10 p-4 shadow-xl rounded-2xl bg-white">
         <h1 className="text-2xl font-bold mb-4">Login</h1>
         {errors && <p className="text-red-500 mb-2">{errors}</p>}
         <form onSubmit={handleSubmit} className="space-y-4">
            <input
               type="email"
               placeholder="Email"
               className="w-full p-2 border rounded"
               value={email}
               onChange={(e) => setEmail(e.target.value)}
               required
            />
            <input
               type="password"
               placeholder="Password"
               className="w-full p-2 border rounded"
               value={password}
               onChange={(e) => setPassword(e.target.value)}
               required
            />
            <button
               type="submit"
               className="w-full bg-blue-600 text-white p-2 rounded hover:bg-blue-700"
            >
               Login
            </button>
         </form>
         <p className="mt-4 text-center">
            Don't have an account?{" "}
            <Link to="/register" className="text-blue-600 hover:underline">
               Register new account
            </Link>
         </p>
      </div>
   );
}
