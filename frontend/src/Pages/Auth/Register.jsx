import { useState } from "react";
import { useNavigate } from "react-router-dom";
import axiosInstance/*, { getCsrfToken }*/ from "../../api/axios";

export default function Register() {
   const [name, setName] = useState("");
   const [email, setEmail] = useState("");
   const [password, setPassword] = useState("");
   const [passwordConfirmation, setPasswordConfirmation] = useState("");
   const [errors, setErrors] = useState(null);
   const navigate = useNavigate();

   const handleSubmit = async (e) => {
      e.preventDefault();
      setErrors(null);

      // Simple validation
      if (password !== passwordConfirmation) {
         setErrors("Passwords don't match");
         return;
      }

      try {
         // await getCsrfToken();
         const response = await axiosInstance.post("/api/register", {
            name, // Sending name field now
            email,
            password,
         });

         // After successful registration, log in the user automatically
         localStorage.setItem("user", JSON.stringify(response.data.user));
         localStorage.setItem("token", response.data.token);
         navigate("/"); // Redirect to homepage or dashboard
      } catch (error) {
         setErrors(error.response?.data?.message || "Registration failed");
      }
   };

   return (
      <div className="max-w-md mx-auto mt-10 p-4 shadow-xl rounded-2xl bg-white">
         <h1 className="text-2xl font-bold mb-4">Register</h1>
         {errors && <p className="text-red-500 mb-2">{errors}</p>}
         <form onSubmit={handleSubmit} className="space-y-4">
            <input
               type="text"
               placeholder="Name"
               className="w-full p-2 border rounded"
               value={name}
               onChange={(e) => setName(e.target.value)}
               required
            />
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
            <input
               type="password"
               placeholder="Confirm Password"
               className="w-full p-2 border rounded"
               value={passwordConfirmation}
               onChange={(e) => setPasswordConfirmation(e.target.value)}
               required
            />
            <button
               type="submit"
               className="w-full bg-green-600 text-white p-2 rounded hover:bg-green-700"
            >
               Register
            </button>
         </form>
      </div>
   );
}
