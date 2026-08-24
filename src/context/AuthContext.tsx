import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useState,
} from 'react';
import type { ReactNode } from 'react';
import { ApiErrorSchema, LoginResponseSchema, MeResponseSchema } from '@/schemas/auth.schema';
import type { AuthUser } from '@/schemas/auth.schema';

interface AuthContextValue {
  user: AuthUser | null;
  isLoading: boolean;
  login: (username: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
}

const AuthContext = createContext<AuthContextValue | null>(null);

interface Props {
  children: ReactNode;
}

export const AuthProvider = ({ children }: Props) => {
  const [user, setUser] = useState<AuthUser | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    const fetchCurrentUser = async () => {
      try {
        const res = await fetch('/api/me.php');
        const raw: unknown = await res.json();
        const parsed = MeResponseSchema.parse(raw);
        setUser(parsed.authenticated ? parsed : null);
      } catch {
        setUser(null);
      } finally {
        setIsLoading(false);
      }
    };

    void fetchCurrentUser();
  }, []);

  const login = useCallback(async (username: string, password: string) => {
    const res = await fetch('/api/login.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ username, password }),
    });

    if (!res.ok) {
      const raw: unknown = await res.json();
      const parsed = ApiErrorSchema.safeParse(raw);
      const message = parsed.success ? parsed.data.error : 'Login failed';
      throw new Error(message);
    }

    const raw: unknown = await res.json();
    const parsed = LoginResponseSchema.parse(raw);
    setUser({
      authenticated: true,
      username: parsed.username,
      name: parsed.name,
      is_admin: parsed.is_admin,
      status: parsed.status,
    });
  }, []);

  const logout = useCallback(async () => {
    try {
      await fetch('/api/logout.php', { method: 'POST' });
    } finally {
      setUser(null);
    }
  }, []);

  return (
    <AuthContext.Provider value={{ user, isLoading, login, logout }}>
      {children}
    </AuthContext.Provider>
  );
};

export const useAuth = (): AuthContextValue => {
  const ctx = useContext(AuthContext);
  if (!ctx) {
    throw new Error('useAuth must be used within AuthProvider');
  }
  return ctx;
};
