import { HttpClient } from '@angular/common/http';
import { Injectable, inject, signal } from '@angular/core';
import { Observable, catchError, map, of, switchMap, tap, timeout } from 'rxjs';
import { User } from '../models/user';

interface SessionResponse {
  user: User | null;
  csrfToken: string;
}

interface UserResponse {
  user: User;
}

export interface LoginData {
  email: string;
  password: string;
}

export interface RegisterData {
  firstName: string;
  lastName: string;
  email: string;
  password: string;
}

// Data that the customer can change in "Mi cuenta" (the email and the role cannot be changed)
export interface ProfileData {
  firstName: string;
  lastName: string;
  phone: string;
  address: string;
  postalCode: string;
  city: string;
  province: string;
}

export interface PasswordData {
  currentPassword: string;
  newPassword: string;
}

// Maximum wait for the session when the app starts, so the shop never stays blocked
const SESSION_TIMEOUT_MS = 5000;

// Keeps the logged-in user and the CSRF token for the whole application
@Injectable({ providedIn: 'root' })
export class AuthService {
  private http = inject(HttpClient);
  private apiUrl = '/api/auth';
  private profileUrl = '/api/users/me';

  // Logged-in user, or null if nobody is logged in
  readonly user = signal<User | null>(null);

  // Token that the CSRF interceptor sends in requests that change data
  csrfToken = '';

  // Asks the API for the current session and a CSRF token.
  // If the API fails or does not answer, the shop still opens without a session.
  loadSession(): Observable<void> {
    return this.http.get<SessionResponse>(`${this.apiUrl}/session`).pipe(
      timeout(SESSION_TIMEOUT_MS),
      tap((response) => {
        this.user.set(response.user);
        this.csrfToken = response.csrfToken;
      }),
      map(() => undefined),
      catchError((error) => {
        console.error('No se ha podido cargar la sesión', error);
        this.user.set(null);
        return of(undefined);
      }),
    );
  }

  login(data: LoginData): Observable<User> {
    return this.http.post<UserResponse>(`${this.apiUrl}/login`, data).pipe(
      map((response) => response.user),
      tap((user) => this.user.set(user)),
    );
  }

  register(data: RegisterData): Observable<User> {
    return this.http.post<UserResponse>(`${this.apiUrl}/register`, data).pipe(
      map((response) => response.user),
      tap((user) => this.user.set(user)),
    );
  }

  getProfile(): Observable<User> {
    return this.http.get<UserResponse>(this.profileUrl).pipe(
      map((response) => response.user),
      tap((user) => this.user.set(user)),
    );
  }

  // Updating the user signal also updates the name in the header
  updateProfile(data: ProfileData): Observable<User> {
    return this.http.put<UserResponse>(this.profileUrl, data).pipe(
      map((response) => response.user),
      tap((user) => this.user.set(user)),
    );
  }

  changePassword(data: PasswordData): Observable<void> {
    return this.http.put<void>(`${this.profileUrl}/password`, data);
  }

  // The server destroys the session, so afterwards we ask for a new one (with a new CSRF token)
  logout(): Observable<void> {
    return this.http.post<void>(`${this.apiUrl}/logout`, {}).pipe(
      switchMap(() => this.loadSession()),
    );
  }
}
