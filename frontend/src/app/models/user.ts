// User data returned by the API (User::toArray() in the backend)
export interface User {
  id: number;
  firstName: string;
  lastName: string;
  email: string;
  phone: string | null;
  role: 'customer' | 'admin';
  address: string | null;
  postalCode: string | null;
  city: string | null;
  province: string | null;
}
