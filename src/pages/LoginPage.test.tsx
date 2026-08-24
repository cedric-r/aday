import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { LoginPage } from './LoginPage';
import * as AuthModule from '@/context/AuthContext';

const mockLogin = vi.fn();

const mockUseAuth = () => {
  vi.spyOn(AuthModule, 'useAuth').mockReturnValue({
    user: null,
    isLoading: false,
    login: mockLogin,
    logout: vi.fn(),
  });
};

const renderLoginPage = (from = '/') =>
  render(
    <MemoryRouter initialEntries={[{ pathname: '/login', state: { from: from } }]}>
      <Routes>
        <Route path="/login" element={<LoginPage />} />
        <Route path="/" element={<span>Home</span>} />
        <Route path="/post" element={<span>Post page</span>} />
      </Routes>
    </MemoryRouter>,
  );

describe('LoginPage', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    mockUseAuth();
  });

  it('renders username and password fields', () => {
    renderLoginPage();
    expect(screen.getByLabelText(/username/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/password/i)).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /log in/i })).toBeInTheDocument();
  });

  it('shows 401 error message for invalid credentials', async () => {
    mockLogin.mockRejectedValueOnce(new Error('Invalid credentials'));
    renderLoginPage();

    await userEvent.type(screen.getByLabelText(/username/i), 'alice');
    await userEvent.type(screen.getByLabelText(/password/i), 'wrong');
    await userEvent.click(screen.getByRole('button', { name: /log in/i }));

    await waitFor(() =>
      expect(screen.getByText(/invalid username or password/i)).toBeInTheDocument(),
    );
  });

  it('shows pending account message on 403', async () => {
    mockLogin.mockRejectedValueOnce(new Error('Account pending approval'));
    renderLoginPage();

    await userEvent.type(screen.getByLabelText(/username/i), 'pending');
    await userEvent.type(screen.getByLabelText(/password/i), 'pass');
    await userEvent.click(screen.getByRole('button', { name: /log in/i }));

    await waitFor(() =>
      expect(screen.getByText(/pending approval/i)).toBeInTheDocument(),
    );
  });

  it('redirects to home on successful login', async () => {
    mockLogin.mockResolvedValueOnce(undefined);
    renderLoginPage('/');

    await userEvent.type(screen.getByLabelText(/username/i), 'alice');
    await userEvent.type(screen.getByLabelText(/password/i), 'pass');
    await userEvent.click(screen.getByRole('button', { name: /log in/i }));

    await waitFor(() => expect(screen.getByText('Home')).toBeInTheDocument());
  });

  it('redirects to location.state.from on successful login', async () => {
    mockLogin.mockResolvedValueOnce(undefined);
    renderLoginPage('/post');

    await userEvent.type(screen.getByLabelText(/username/i), 'alice');
    await userEvent.type(screen.getByLabelText(/password/i), 'pass');
    await userEvent.click(screen.getByRole('button', { name: /log in/i }));

    await waitFor(() => expect(screen.getByText('Post page')).toBeInTheDocument());
  });
});
