import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import * as AuthModule from '@/context/AuthContext';
import { Header } from './Header';

vi.mock('@/context/AuthContext', async (importOriginal) => {
  const mod = await importOriginal<typeof AuthModule>();
  return { ...mod };
});

describe('Header', () => {
  beforeEach(() => {
    vi.spyOn(AuthModule, 'useAuth').mockReturnValue({
      user: null,
      isLoading: false,
      login: vi.fn(),
      logout: vi.fn(),
    });
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('renders logo image with correct alt text', () => {
    render(
      <MemoryRouter>
        <Header />
      </MemoryRouter>,
    );
    const logo = screen.getByRole('img', { name: /a day in the life/i });
    expect(logo).toBeInTheDocument();
    expect(logo).toHaveAttribute('src', '/adayinthelife.png');
  });

  it('logo links to home page', () => {
    render(
      <MemoryRouter>
        <Header />
      </MemoryRouter>,
    );
    const link = screen.getByRole('link', { name: /a day in the life/i });
    expect(link).toHaveAttribute('href', '/');
  });
});
