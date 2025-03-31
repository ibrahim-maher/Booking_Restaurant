import { Navigate } from "react-router-dom";
import { useAuth } from "../contexts/AuthContext";

const PrivateRoute = ({ element, ...rest }) => {
   const { isAuthenticated } = useAuth();

   return isAuthenticated ? element : <Navigate to="/login" replace />;
};

export default PrivateRoute;
