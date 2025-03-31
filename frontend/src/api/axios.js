import axios from "axios";

const axiosInstance = axios.create({
   baseURL: "http://127.0.0.1:8000",
   withCredentials: true,
   headers: {
      "X-Requested-With": "XMLHttpRequest",
   },
});

// // Get CSRF token before any POST request
// export const getCsrfToken = async () => {
//    await axiosInstance.get("/sanctum/csrf-cookie");
// };

export default axiosInstance;
