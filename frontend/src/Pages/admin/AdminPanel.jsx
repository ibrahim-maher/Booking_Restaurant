import { useState, useEffect } from "react";
import axiosInstance from "../api/axios";
import { useAuth } from "../contexts/AuthContext";

export default function AdminPanel() {
   const { user } = useAuth(); // Access current user to check if admin
   const [users, setUsers] = useState([]);
   const [newUser, setNewUser] = useState({
      name: "",
      email: "",
      password: "",
      role: "user",
   });

   // Fetch users (admin-only route)
   const fetchUsers = async () => {
      try {
         const res = await axiosInstance.get("/api/admin/users");
         setUsers(res.data);
      } catch (error) {
         console.error(error);
      }
   };

   const handleRoleChange = async (userId, role) => {
      try {
         await axiosInstance.put(`/api/admin/users/${userId}/role`, { role });
         fetchUsers(); // Refresh users list
      } catch (error) {
         console.error("Failed to update role", error);
      }
   };

   const handleAddUser = async () => {
      try {
         await axiosInstance.post("/api/admin/users", newUser);
         fetchUsers(); // Refresh users list after adding new user
         setNewUser({ name: "", email: "", password: "", role: "user" }); // Clear form
      } catch (error) {
         console.error("Failed to add user", error);
      }
   };

   useEffect(() => {
      if (user?.role === "admin") fetchUsers();
   }, [user]);

   return (
      <div>
         <h1>Admin Panel</h1>
         {user?.role === "admin" && (
            <>
               <div>
                  <h2>Add New User</h2>
                  <input
                     type="text"
                     placeholder="Name"
                     value={newUser.name}
                     onChange={(e) =>
                        setNewUser({ ...newUser, name: e.target.value })
                     }
                  />
                  <input
                     type="email"
                     placeholder="Email"
                     value={newUser.email}
                     onChange={(e) =>
                        setNewUser({ ...newUser, email: e.target.value })
                     }
                  />
                  <input
                     type="password"
                     placeholder="Password"
                     value={newUser.password}
                     onChange={(e) =>
                        setNewUser({ ...newUser, password: e.target.value })
                     }
                  />
                  <select
                     value={newUser.role}
                     onChange={(e) =>
                        setNewUser({ ...newUser, role: e.target.value })
                     }
                  >
                     <option value="user">User</option>
                     <option value="admin">Admin</option>
                  </select>
                  <button onClick={handleAddUser}>Add User</button>
               </div>

               <div>
                  <h2>User List</h2>
                  {users.map((user) => (
                     <div key={user.id}>
                        <span>
                           {user.name} - {user.role}
                        </span>
                        <select
                           value={user.role}
                           onChange={(e) =>
                              handleRoleChange(user.id, e.target.value)
                           }
                        >
                           <option value="user">User</option>
                           <option value="admin">Admin</option>
                        </select>
                     </div>
                  ))}
               </div>
            </>
         )}
      </div>
   );
}
